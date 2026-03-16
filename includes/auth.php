<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function users_path(): string
{
    return dirname(__DIR__) . '/data/users.json';
}

function all_users(): array
{
    $path = users_path();
    if (!file_exists($path)) {
        return [];
    }

    $raw = file_get_contents($path);
    $decoded = json_decode($raw ?: '[]', true);

    return is_array($decoded) ? $decoded : [];
}

function has_users(): bool
{
    return count(all_users()) > 0;
}

function save_users(array $users): bool
{
    $path = users_path();
    $dir = dirname($path);

    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        return false;
    }

    $json = json_encode(array_values($users), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }

    return file_put_contents($path, $json . PHP_EOL, LOCK_EX) !== false;
}

function find_user_by_username(string $username): ?array
{
    foreach (all_users() as $user) {
        if (isset($user['username']) && strcasecmp((string) $user['username'], $username) === 0) {
            return $user;
        }
    }

    return null;
}

function current_user(): ?array
{
    $userId = $_SESSION['user_id'] ?? null;
    if ($userId === null) {
        return null;
    }

    foreach (all_users() as $user) {
        if (isset($user['id']) && (string) $user['id'] === (string) $userId) {
            return $user;
        }
    }

    return null;
}

function require_auth(): void
{
    if (current_user() !== null) {
        return;
    }

    header('Location: /login.php');
    exit;
}
