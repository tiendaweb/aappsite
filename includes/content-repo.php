<?php

declare(strict_types=1);

require_once __DIR__ . '/tenant.php';
require_once __DIR__ . '/json-store.php';

const TEMPLATE_CUSTOM_CSS_MAX_BYTES_DEFAULT = 102400;

function content_file_path(?string $tenantId = null): string
{
    $tenantId = sanitize_tenant_id($tenantId ?? resolve_tenant_id());

    return tenant_file_path($tenantId, 'content.json');
}

function normalize_template_slug(?string $templateSlug): ?string
{
    if (!is_string($templateSlug)) {
        return null;
    }

    $normalized = mb_strtolower(trim($templateSlug));

    if (preg_match('/^[a-z0-9\-]+$/', $normalized) !== 1) {
        return null;
    }

    return $normalized;
}

function template_content_dir_path(?string $tenantId = null, ?string $templateSlug = null): ?string
{
    $tenantId = sanitize_tenant_id($tenantId ?? resolve_tenant_id());
    $templateSlug = normalize_template_slug($templateSlug);

    if ($templateSlug === null) {
        return null;
    }

    return tenant_data_dir($tenantId) . '/templates/' . $templateSlug;
}

function template_content_file_path(?string $tenantId = null, ?string $templateSlug = null): ?string
{
    $templateDir = template_content_dir_path($tenantId, $templateSlug);

    if ($templateDir === null) {
        return null;
    }

    return $templateDir . '/content.json';
}

function template_meta_file_path(?string $tenantId = null, ?string $templateSlug = null): ?string
{
    $templateDir = template_content_dir_path($tenantId, $templateSlug);

    if ($templateDir === null) {
        return null;
    }

    return $templateDir . '/meta.json';
}

function template_custom_css_max_bytes(): int
{
    $configured = getenv('TEMPLATE_CUSTOM_CSS_MAX_BYTES');
    if ($configured === false) {
        return TEMPLATE_CUSTOM_CSS_MAX_BYTES_DEFAULT;
    }

    $parsed = filter_var($configured, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1024, 'max_range' => 1048576]]);

    return is_int($parsed) ? $parsed : TEMPLATE_CUSTOM_CSS_MAX_BYTES_DEFAULT;
}

function normalize_template_custom_css(string $css): ?string
{
    $css = str_replace(["\r\n", "\r"], "\n", $css);
    $css = rtrim($css);

    if ($css !== '' && !mb_check_encoding($css, 'UTF-8')) {
        return null;
    }

    if (strlen($css) > template_custom_css_max_bytes()) {
        return null;
    }

    return $css;
}

function read_template_meta(?string $tenantId = null, ?string $templateSlug = null): array
{
    $path = template_meta_file_path($tenantId, $templateSlug);
    if (!is_string($path) || !is_file($path)) {
        return [];
    }

    $meta = read_json_file($path, []);

    return is_array($meta) ? $meta : [];
}

function read_template_custom_css(?string $tenantId = null, ?string $templateSlug = null): string
{
    $meta = read_template_meta($tenantId, $templateSlug);
    $rawCss = $meta['custom_css'] ?? '';

    if (!is_string($rawCss)) {
        return '';
    }

    $normalized = normalize_template_custom_css($rawCss);

    return is_string($normalized) ? $normalized : '';
}

function save_template_custom_css(string $css, ?string $tenantId = null, ?string $templateSlug = null): bool
{
    $tenantId = sanitize_tenant_id($tenantId ?? resolve_tenant_id());
    $normalizedSlug = normalize_template_slug($templateSlug);
    $normalizedCss = normalize_template_custom_css($css);

    if ($normalizedSlug === null || $normalizedCss === null) {
        return false;
    }

    $path = template_meta_file_path($tenantId, $normalizedSlug);
    if (!is_string($path)) {
        return false;
    }

    $meta = read_template_meta($tenantId, $normalizedSlug);
    $meta['custom_css'] = $normalizedCss;

    return write_json_file_atomic($path, $meta);
}

function decode_content_file(string $path): array
{
    $decoded = json_decode(file_get_contents($path) ?: '{}', true);

    return is_array($decoded) ? $decoded : [];
}

function infer_template_slug_from_content(array $data): ?string
{
    $configured = $data['site']['template'] ?? null;

    return is_string($configured) ? normalize_template_slug($configured) : null;
}

function resolve_stored_template_slug(?string $tenantId = null): ?string
{
    $tenantId = sanitize_tenant_id($tenantId ?? resolve_tenant_id());
    run_initial_tenant_migration($tenantId);

    $legacyPath = content_file_path($tenantId);
    if (is_file($legacyPath)) {
        $legacySlug = infer_template_slug_from_content(decode_content_file($legacyPath));
        if ($legacySlug !== null) {
            return $legacySlug;
        }
    }

    foreach (list_tenant_template_slugs($tenantId) as $templateSlug) {
        $metaPath = template_meta_file_path($tenantId, $templateSlug);
        if (!is_string($metaPath) || !is_file($metaPath)) {
            continue;
        }

        $meta = json_decode(file_get_contents($metaPath) ?: '{}', true);
        if (is_array($meta) && ($meta['active'] ?? false) === true) {
            return $templateSlug;
        }
    }

    return null;
}

function list_tenant_template_slugs(string $tenantId): array
{
    $templatesRoot = tenant_data_dir($tenantId) . '/templates';
    if (!is_dir($templatesRoot)) {
        return [];
    }

    $entries = scandir($templatesRoot);
    if ($entries === false) {
        return [];
    }

    $slugs = [];
    foreach ($entries as $entry) {
        $slug = normalize_template_slug($entry);
        if ($slug === null) {
            continue;
        }

        if (is_file($templatesRoot . '/' . $slug . '/content.json')) {
            $slugs[] = $slug;
        }
    }

    return array_values(array_unique($slugs));
}

function read_content_file(?string $tenantId = null, ?string $templateSlug = null): array
{
    $tenantId = sanitize_tenant_id($tenantId ?? resolve_tenant_id());
    run_initial_tenant_migration($tenantId);

    $resolvedSlug = normalize_template_slug($templateSlug);
    if ($resolvedSlug !== null) {
        $target = template_content_file_path($tenantId, $resolvedSlug);
        if (is_string($target) && is_file($target)) {
            return decode_content_file($target);
        }
    }

    $legacyPath = content_file_path($tenantId);
    $legacyData = is_file($legacyPath) ? decode_content_file($legacyPath) : [];

    if ($resolvedSlug === null) {
        $fallbackCandidates = [];
        $legacySlug = infer_template_slug_from_content($legacyData);
        if ($legacySlug !== null) {
            $fallbackCandidates[] = $legacySlug;
        }

        $fallbackCandidates[] = 'artistas';
        $fallbackCandidates = array_merge($fallbackCandidates, list_tenant_template_slugs($tenantId));

        foreach (array_values(array_unique($fallbackCandidates)) as $candidateSlug) {
            $target = template_content_file_path($tenantId, $candidateSlug);
            if (is_string($target) && is_file($target)) {
                return decode_content_file($target);
            }
        }

        $resolvedSlug = $legacySlug ?? 'artistas';
    }

    if ($legacyData === []) {
        return [];
    }

    save_content_file($legacyData, $tenantId, $resolvedSlug);

    return $legacyData;
}

function save_content_file(array $data, ?string $tenantId = null, ?string $templateSlug = null): bool
{
    $tenantId = sanitize_tenant_id($tenantId ?? resolve_tenant_id());
    if (!ensure_tenant_directories($tenantId)) {
        return false;
    }

    $resolvedSlug = normalize_template_slug($templateSlug) ?? infer_template_slug_from_content($data) ?? 'artistas';
    $target = template_content_file_path($tenantId, $resolvedSlug);
    if (!is_string($target)) {
        return false;
    }

    $templateDir = dirname($target);
    if (!is_dir($templateDir) && !mkdir($templateDir, 0775, true) && !is_dir($templateDir)) {
        return false;
    }

    $tmp = $target . '.tmp.' . uniqid('', true);
    $lockFile = $target . '.lock';
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    if ($json === false) {
        return false;
    }

    $lockHandle = @fopen($lockFile, 'c');
    if ($lockHandle === false) {
        return false;
    }

    if (!flock($lockHandle, LOCK_EX)) {
        fclose($lockHandle);
        return false;
    }

    $tmpHandle = @fopen($tmp, 'wb');
    if ($tmpHandle === false) {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
        return false;
    }

    $bytes = fwrite($tmpHandle, $json . PHP_EOL);
    if ($bytes === false) {
        fclose($tmpHandle);
        @unlink($tmp);
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
        return false;
    }

    fflush($tmpHandle);
    fclose($tmpHandle);

    if (!@rename($tmp, $target)) {
        @unlink($tmp);
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
        return false;
    }

    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);

    return true;
}

function set_value_by_path(array &$data, string $path, $value): void
{
    $normalized = preg_replace('/\[(\d+)\]/', '.$1', $path) ?: $path;
    $segments = array_filter(explode('.', $normalized), static fn ($segment) => $segment !== '');

    $current = &$data;
    foreach ($segments as $index => $segment) {
        $isLast = $index === array_key_last($segments);
        if ($isLast) {
            $current[$segment] = $value;
            return;
        }

        if (!isset($current[$segment]) || !is_array($current[$segment])) {
            $current[$segment] = [];
        }

        $current = &$current[$segment];
    }
}
