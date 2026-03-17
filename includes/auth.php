<?php

declare(strict_types=1);

require_once __DIR__ . '/url.php';
require_once __DIR__ . '/tenant.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function users_path(?string $tenantId = null): string
{
    $tenantId = $tenantId !== null ? (sanitize_tenant_id($tenantId) ?? default_tenant_id()) : current_tenant_id();

    return tenant_data_dir($tenantId) . '/users.json';
}

function all_users(?string $tenantId = null): array
{
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
    $tenantId = $tenantId !== null ? (sanitize_tenant_id($tenantId) ?? default_tenant_id()) : current_tenant_id();
    if (!ensure_tenant_storage($tenantId)) {
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

function current_user(): ?array
{
    $sessionTenantId = sanitize_tenant_id((string) ($_SESSION['tenant_id'] ?? ''));
    if ($sessionTenantId === null || $sessionTenantId !== current_tenant_id()) {
        return null;
    }

    $userId = $_SESSION['user_id'] ?? null;
    if ($userId === null) {
        return null;
    }

    foreach (all_users($sessionTenantId) as $user) {
        if (isset($user['id']) && (string) $user['id'] === (string) $userId) {
            return $user;
        }
    }

    return null;
}

function login_user(array $user, ?string $tenantId = null): void
{
    $_SESSION['user_id'] = $user['id'] ?? null;
    $_SESSION['tenant_id'] = $tenantId ?? current_tenant_id();
}

function logout_user(): void
{
    unset($_SESSION['user_id'], $_SESSION['tenant_id']);
}

function require_auth(): void
{
    if (current_user() !== null) {
        return;
    }

    header('Location: ' . url_for('/login'));
    exit;
}
