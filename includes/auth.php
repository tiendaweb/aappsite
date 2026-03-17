<?php

declare(strict_types=1);

require_once __DIR__ . '/url.php';
require_once __DIR__ . '/tenant.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function users_path(?string $tenantId = null): string
{
    $tenantId = sanitize_tenant_id($tenantId ?? resolve_tenant_id());

    return tenant_file_path($tenantId, 'users.json');
}

function all_users(?string $tenantId = null): array
{
    $tenantId = sanitize_tenant_id($tenantId ?? resolve_tenant_id());
    run_initial_tenant_migration($tenantId);

    $path = users_path($tenantId);
    if (!file_exists($path)) {
        return [];
    }

    $raw = file_get_contents($path);
    $decoded = json_decode($raw ?: '[]', true);

    return is_array($decoded) ? $decoded : [];
}

function has_users(?string $tenantId = null): bool
{
    return count(all_users($tenantId)) > 0;
}

function save_users(array $users, ?string $tenantId = null): bool
{
    $tenantId = sanitize_tenant_id($tenantId ?? resolve_tenant_id());
    if (!ensure_tenant_directories($tenantId)) {
        return false;
    }

    $path = users_path($tenantId);
    $json = json_encode(array_values($users), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }

    return file_put_contents($path, $json . PHP_EOL, LOCK_EX) !== false;
}

function find_user_by_username(string $username, ?string $tenantId = null): ?array
{
    foreach (all_users($tenantId) as $user) {
        if (isset($user['username']) && strcasecmp((string) $user['username'], $username) === 0) {
            return $user;
        }
    }

    return null;
}

function current_user(?string $tenantId = null): ?array
{
    $userId = $_SESSION['user_id'] ?? null;
    $sessionTenant = $_SESSION['tenant_id'] ?? null;

    if ($userId === null || !is_string($sessionTenant)) {
        return null;
    }

    $tenantId = sanitize_tenant_id($tenantId ?? resolve_tenant_id());
    if ($sessionTenant !== $tenantId) {
        return null;
    }

    foreach (all_users($tenantId) as $user) {
        if (isset($user['id']) && (string) $user['id'] === (string) $userId) {
            return $user;
        }
    }

    return null;
}

function require_auth(?string $tenantId = null): void
{
    if (current_user($tenantId) !== null) {
        return;
    }

    header('Location: ' . url_for('/login'));
    exit;
}
