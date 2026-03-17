<?php

declare(strict_types=1);

require_once __DIR__ . '/tenant.php';

function plans_path(): string
{
    return dirname(__DIR__) . '/data/plans.json';
}

function subscriptions_path(): string
{
    return dirname(__DIR__) . '/data/subscriptions.json';
}

function read_plans(): array
{
    $raw = @file_get_contents(plans_path());
    $decoded = json_decode($raw ?: '[]', true);

    return is_array($decoded) ? $decoded : [];
}

function find_plan_by_id(string $planId): ?array
{
    foreach (read_plans() as $plan) {
        if (is_array($plan) && (string) ($plan['id'] ?? '') === $planId) {
            return $plan;
        }
    }

    return null;
}

function default_plan_id(): string
{
    foreach (read_plans() as $plan) {
        if (is_array($plan) && !empty($plan['is_default']) && isset($plan['id'])) {
            return (string) $plan['id'];
        }
    }

    return 'free';
}

function read_subscriptions(): array
{
    $path = subscriptions_path();
    if (!is_file($path)) {
        return [];
    }

    $raw = @file_get_contents($path);
    $decoded = json_decode($raw ?: '[]', true);

    return is_array($decoded) ? $decoded : [];
}

function save_subscriptions(array $subscriptions): bool
{
    $json = json_encode(array_values($subscriptions), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }

    return file_put_contents(subscriptions_path(), $json . PHP_EOL, LOCK_EX) !== false;
}

function tenant_has_subscription(string $tenantId): bool
{
    $tenantId = sanitize_tenant_id($tenantId);

    foreach (read_subscriptions() as $subscription) {
        if ((string) ($subscription['tenant_id'] ?? '') === $tenantId) {
            return true;
        }
    }

    return false;
}

function add_subscription(string $tenantId, string $planId, string $email): bool
{
    $tenantId = sanitize_tenant_id($tenantId);
    $plan = find_plan_by_id($planId);

    if ($plan === null) {
        return false;
    }

    $subscriptions = read_subscriptions();
    $subscriptions[] = [
        'id' => uniqid('sub_', true),
        'tenant_id' => $tenantId,
        'email' => mb_strtolower(trim($email)),
        'plan_id' => $planId,
        'status' => 'active',
        'created_at' => gmdate('c'),
    ];

    return save_subscriptions($subscriptions);
}
