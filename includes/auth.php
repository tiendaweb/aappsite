<?php

declare(strict_types=1);

require_once __DIR__ . '/url.php';
require_once __DIR__ . '/tenant.php';
require_once __DIR__ . '/json-store.php';

const ROLE_SUPER_ADMIN = 'super-admin';
const ROLE_TENANT_ADMIN = 'admin';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function users_path(?string $tenantId = null): string
{
    $tenantId = sanitize_tenant_id($tenantId ?? resolve_tenant_id());

    return tenant_file_path($tenantId, 'users.json');
}

function global_users_path(): string
{
    return dirname(__DIR__) . '/data/users.json';
}

function all_users(?string $tenantId = null): array
{
    $tenantId = sanitize_tenant_id($tenantId ?? resolve_tenant_id());
    run_initial_tenant_migration($tenantId);

    $path = users_path($tenantId);
    if (!file_exists($path)) {
        return [];
    }

    $decoded = read_json_file($path, []);
    if (!is_array($decoded)) {
        return [];
    }

    $normalized = [];
    foreach ($decoded as $user) {
        if (!is_array($user) || !isset($user['username'])) {
            continue;
        }

        $user['role'] = ROLE_TENANT_ADMIN;
        $normalized[] = $user;
    }

    return $normalized;
}

function all_global_users(): array
{
    $decoded = read_json_file(global_users_path(), []);
    if (!is_array($decoded)) {
        return [];
    }

    $normalized = [];
    foreach ($decoded as $user) {
        if (!is_array($user) || !isset($user['username'])) {
            continue;
        }

        $user['role'] = ROLE_SUPER_ADMIN;
        $normalized[] = $user;
    }

    return $normalized;
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
    $normalized = [];
    foreach (array_values($users) as $user) {
        if (!is_array($user)) {
            continue;
        }
        $user['role'] = ROLE_TENANT_ADMIN;
        $normalized[] = $user;
    }

    return write_json_file_atomic($path, $normalized);
}

function save_global_users(array $users): bool
{
    $normalized = [];
    foreach (array_values($users) as $user) {
        if (!is_array($user)) {
            continue;
        }
        $user['role'] = ROLE_SUPER_ADMIN;
        $normalized[] = $user;
    }

    return write_json_file_atomic(global_users_path(), $normalized);
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

function find_global_user_by_username(string $username): ?array
{
    foreach (all_global_users() as $user) {
        if (isset($user['username']) && strcasecmp((string) $user['username'], $username) === 0) {
            return $user;
        }
    }

    return null;
}

function current_user(?string $tenantId = null): ?array
{
    $sessionRole = (string) ($_SESSION['auth_role'] ?? ROLE_TENANT_ADMIN);
    if ($sessionRole === ROLE_SUPER_ADMIN) {
        return null;
    }

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

function current_super_admin(): ?array
{
    if ((string) ($_SESSION['auth_role'] ?? '') !== ROLE_SUPER_ADMIN) {
        return null;
    }

    $userId = $_SESSION['user_id'] ?? null;
    if ($userId === null) {
        return null;
    }

    foreach (all_global_users() as $user) {
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

function require_tenant_admin(?string $tenantId = null): void
{
    if (current_user($tenantId) !== null) {
        return;
    }

    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'No autorizado para el tenant.'], JSON_UNESCAPED_UNICODE);
    exit;
}

function require_super_admin(): void
{
    if (current_super_admin() !== null) {
        return;
    }

    header('Location: ' . url_for('/super-admin.php'));
    exit;
}
