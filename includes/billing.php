<?php

declare(strict_types=1);

require_once __DIR__ . '/tenant.php';
require_once __DIR__ . '/json-store.php';

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
    $path = plans_path();
    if (!is_file($path)) {
        $seedPlans = [
            [
                'id' => 'free',
                'name' => 'Gratis',
                'description' => 'Ideal para empezar y probar el producto.',
                'price_monthly' => 0,
                'is_default' => true,
            ],
            [
                'id' => 'pro',
                'name' => 'Pro',
                'description' => 'Incluye soporte prioritario y onboarding asistido.',
                'price_monthly' => 29,
                'is_default' => false,
            ],
        ];
        @file_put_contents($path, json_encode($seedPlans, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL, LOCK_EX);
    }

    $decoded = read_json_file($path, []);

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
        @file_put_contents($path, "[]\n", LOCK_EX);
        return [];
    }

    $decoded = read_json_file($path, []);

    return is_array($decoded) ? $decoded : [];
}

function save_subscriptions(array $subscriptions): bool
{
    return write_json_file_atomic(subscriptions_path(), array_values($subscriptions));
}

function save_plans(array $plans): bool
{
    return write_json_file_atomic(plans_path(), array_values($plans));
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
    $normalizedEmail = mb_strtolower(trim($email));
    $updated = false;

    foreach ($subscriptions as &$subscription) {
        if ((string) ($subscription['tenant_id'] ?? '') !== $tenantId) {
            continue;
        }

        $subscription['email'] = $normalizedEmail;
        $subscription['plan_id'] = $planId;
        $subscription['status'] = 'active';
        $subscription['updated_at'] = gmdate('c');
        $updated = true;
        break;
    }
    unset($subscription);

    if (!$updated) {
        $subscriptions[] = [
            'id' => uniqid('sub_', true),
            'tenant_id' => $tenantId,
            'email' => $normalizedEmail,
            'plan_id' => $planId,
            'status' => 'active',
            'created_at' => gmdate('c'),
        ];
    }

    return save_subscriptions($subscriptions);
}
