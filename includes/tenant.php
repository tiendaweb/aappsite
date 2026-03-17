<?php

declare(strict_types=1);

function default_tenant_id(): string
{
    return 'default';
}

function sanitize_tenant_id(string $tenantId): ?string
{
    $normalized = strtolower(trim($tenantId));
    if ($normalized === '' || preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/', $normalized) !== 1) {
        return null;
    }

    return $normalized;
}

function resolve_tenant_id(): string
{
    $header = $_SERVER['HTTP_X_TENANT_ID'] ?? '';
    if (is_string($header)) {
        $resolved = sanitize_tenant_id($header);
        if ($resolved !== null) {
            return $resolved;
        }
    }

    $uriPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    if (is_string($uriPath) && $uriPath !== '') {
        $segments = array_values(array_filter(explode('/', trim($uriPath, '/')), static fn (string $segment): bool => $segment !== ''));
        if (count($segments) >= 2 && in_array($segments[0], ['t', 'tenant'], true)) {
            $resolved = sanitize_tenant_id($segments[1]);
            if ($resolved !== null) {
                return $resolved;
            }
        }
    }

    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $host = preg_replace('/:\d+$/', '', $host) ?: '';
    if ($host !== '') {
        $parts = explode('.', $host);
        if (count($parts) > 2) {
            $candidate = $parts[0];
            if ($candidate !== 'www') {
                $resolved = sanitize_tenant_id($candidate);
                if ($resolved !== null) {
                    return $resolved;
                }
            }
        }
    }

    return default_tenant_id();
}

function set_current_tenant_id(string $tenantId): void
{
    $resolved = sanitize_tenant_id($tenantId);
    $GLOBALS['app_tenant_id'] = $resolved ?? default_tenant_id();
}

function current_tenant_id(): string
{
    $current = $GLOBALS['app_tenant_id'] ?? null;
    if (is_string($current)) {
        $resolved = sanitize_tenant_id($current);
        if ($resolved !== null) {
            return $resolved;
        }
    }

    $resolved = resolve_tenant_id();
    set_current_tenant_id($resolved);

    return $resolved;
}

function tenant_data_dir(string $tenantId): string
{
    return dirname(__DIR__) . '/data/tenants/' . $tenantId;
}

function tenant_uploads_dir(string $tenantId): string
{
    return dirname(__DIR__) . '/public/uploads/' . $tenantId;
}

function ensure_tenant_storage(string $tenantId): bool
{
    $dataDir = tenant_data_dir($tenantId);
    $uploadsDir = tenant_uploads_dir($tenantId);

    if (!is_dir($dataDir) && !mkdir($dataDir, 0775, true) && !is_dir($dataDir)) {
        return false;
    }

    if (!is_dir($uploadsDir) && !mkdir($uploadsDir, 0775, true) && !is_dir($uploadsDir)) {
        return false;
    }

    return true;
}
