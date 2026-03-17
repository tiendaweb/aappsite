<?php

declare(strict_types=1);

const DEFAULT_TENANT_ID = 'default';

function sanitize_tenant_id(string $candidate): string
{
    $normalized = mb_strtolower(trim($candidate));
    $normalized = preg_replace('/[^a-z0-9\-]+/', '-', $normalized) ?: '';
    $normalized = trim($normalized, '-');

    if ($normalized === '' || strlen($normalized) > 64) {
        return DEFAULT_TENANT_ID;
    }

    return $normalized;
}

function resolve_tenant_id(): string
{
    static $resolved = null;
    if (is_string($resolved)) {
        return $resolved;
    }

    $headerTenant = (string) ($_SERVER['HTTP_X_TENANT_ID'] ?? '');
    if ($headerTenant !== '') {
        $resolved = sanitize_tenant_id($headerTenant);
        return $resolved;
    }

    $queryTenant = (string) ($_GET['tenant'] ?? '');
    if ($queryTenant !== '') {
        $resolved = sanitize_tenant_id($queryTenant);
        return $resolved;
    }

    if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['tenant_id']) && is_string($_SESSION['tenant_id'])) {
        $resolved = sanitize_tenant_id((string) $_SESSION['tenant_id']);
        return $resolved;
    }

    $requestPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    if (preg_match('#^/t/([a-zA-Z0-9\-]+)(?:/|$)#', $requestPath, $matches) === 1) {
        $resolved = sanitize_tenant_id((string) $matches[1]);
        return $resolved;
    }

    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $host = preg_replace('/:\d+$/', '', $host) ?: $host;
    $segments = array_values(array_filter(explode('.', $host), static fn (string $part): bool => $part !== ''));
    if (count($segments) >= 3) {
        $subdomain = $segments[0];
        if ($subdomain !== 'www') {
            $resolved = sanitize_tenant_id($subdomain);
            return $resolved;
        }
    }

    $resolved = DEFAULT_TENANT_ID;

    return $resolved;
}

function tenant_data_dir(string $tenantId): string
{
    return dirname(__DIR__) . '/data/tenants/' . sanitize_tenant_id($tenantId);
}

function tenant_uploads_dir(string $tenantId): string
{
    return dirname(__DIR__) . '/public/uploads/' . sanitize_tenant_id($tenantId);
}

function ensure_tenant_directories(string $tenantId): bool
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

function tenant_file_path(string $tenantId, string $filename): string
{
    return tenant_data_dir($tenantId) . '/' . ltrim($filename, '/');
}

function run_initial_tenant_migration(string $tenantId = DEFAULT_TENANT_ID): void
{
    $tenantId = sanitize_tenant_id($tenantId);
    if ($tenantId !== DEFAULT_TENANT_ID) {
        return;
    }

    if (!ensure_tenant_directories($tenantId)) {
        return;
    }

    $maps = [
        'content.json' => dirname(__DIR__) . '/data/content.json',
        'users.json' => dirname(__DIR__) . '/data/users.json',
        'templates-index.json' => dirname(__DIR__) . '/data/templates-index.json',
    ];

    foreach ($maps as $targetFile => $legacyFile) {
        $tenantFile = tenant_file_path($tenantId, $targetFile);
        if (is_file($tenantFile) || !is_file($legacyFile)) {
            continue;
        }

        $raw = file_get_contents($legacyFile);
        if ($raw === false) {
            continue;
        }

        @file_put_contents($tenantFile, $raw, LOCK_EX);
    }
}

