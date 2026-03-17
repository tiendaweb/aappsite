<?php

declare(strict_types=1);

require_once __DIR__ . '/tenant.php';

function templates_dir_path(): string
{
    return dirname(__DIR__) . '/templates';
}

function template_registry_path(?string $tenantId = null): string
{
    $tenantId = $tenantId !== null ? (sanitize_tenant_id($tenantId) ?? default_tenant_id()) : current_tenant_id();

    return tenant_data_dir($tenantId) . '/templates-index.json';
}

function read_template_registry(?string $tenantId = null): array
{
    $path = template_registry_path($tenantId);
    if (!is_file($path)) {
        return [];
    }

    $decoded = json_decode(file_get_contents($path) ?: '[]', true);
    if (!is_array($decoded)) {
        return [];
    }

    $valid = [];
    foreach ($decoded as $slug) {
        if (is_string($slug) && preg_match('/^[a-z0-9\-]+$/', $slug) === 1) {
            $valid[] = $slug;
        }
    }

    return array_values(array_unique($valid));
}

function save_template_registry(array $slugs, ?string $tenantId = null): bool
{
    $tenantId = $tenantId !== null ? (sanitize_tenant_id($tenantId) ?? default_tenant_id()) : current_tenant_id();
    if (!ensure_tenant_storage($tenantId)) {
        return false;
    }

    $path = template_registry_path($tenantId);
    $normalized = [];

    foreach ($slugs as $slug) {
        if (is_string($slug) && preg_match('/^[a-z0-9\-]+$/', $slug) === 1) {
            $normalized[] = $slug;
        }
    }

    $normalized = array_values(array_unique($normalized));
    sort($normalized);

    $json = json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }

    return file_put_contents($path, $json . PHP_EOL, LOCK_EX) !== false;
}

function register_template_slug(string $slug, ?string $tenantId = null): bool
{
    if (preg_match('/^[a-z0-9\-]+$/', $slug) !== 1) {
        return false;
    }

    $registry = read_template_registry($tenantId);
    $registry[] = $slug;

    return save_template_registry($registry, $tenantId);
}

function unregister_template_slug(string $slug, ?string $tenantId = null): bool
{
    if (!is_valid_template_slug($slug)) {
        return false;
    }

    $registry = array_values(array_filter(
        read_template_registry($tenantId),
        static fn (string $registeredSlug): bool => $registeredSlug !== $slug
    ));

    return save_template_registry($registry, $tenantId);
}

function delete_template_directory(string $slug): bool
{
    if (!is_valid_template_slug($slug)) {
        return false;
    }

    $directory = templates_dir_path() . '/' . $slug;
    if (!is_dir($directory)) {
        return true;
    }

    $entries = scandir($directory);
    if ($entries === false) {
        return false;
    }

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $path = $directory . '/' . $entry;
        if (is_dir($path)) {
            return false;
        }

        if (!unlink($path)) {
            return false;
        }
    }

    return rmdir($directory);
}

function list_available_templates(?string $tenantId = null): array
{
    $templates = [];

    foreach (['artistas', 'fitness'] as $sharedTemplate) {
        $indexFile = templates_dir_path() . '/' . $sharedTemplate . '/index.php';
        if (is_file($indexFile)) {
            $templates[] = $sharedTemplate;
        }
    }

    foreach (read_template_registry($tenantId) as $slug) {
        $indexFile = templates_dir_path() . '/' . $slug . '/index.php';
        if (is_file($indexFile)) {
            $templates[] = $slug;
        }
    }

    $templates = array_values(array_unique($templates));
    sort($templates);

    return $templates;
}

function is_valid_template_slug(string $slug): bool
{
    return preg_match('/^[a-z0-9\-]+$/', $slug) === 1;
}

function template_index_path(string $slug): ?string
{
    if (!is_valid_template_slug($slug)) {
        return null;
    }

    $path = templates_dir_path() . '/' . $slug . '/index.php';

    return is_file($path) ? $path : null;
}

function resolve_active_template(array $content, string $fallback = 'artistas', ?string $tenantId = null): string
{
    $available = list_available_templates($tenantId);
    if ($available === []) {
        return $fallback;
    }

    if (!in_array($fallback, $available, true)) {
        $fallback = $available[0];
    }

    $configured = $content['site']['template'] ?? null;
    if (!is_string($configured) || $configured === '') {
        return $fallback;
    }

    if (!in_array($configured, $available, true)) {
        return $fallback;
    }

    return $configured;
}
