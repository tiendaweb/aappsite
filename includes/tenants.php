<?php

declare(strict_types=1);

require_once __DIR__ . '/tenant.php';
require_once __DIR__ . '/json-store.php';

function tenants_path(): string
{
    return dirname(__DIR__) . '/data/tenants.json';
}

function read_tenants(): array
{
    $decoded = read_json_file(tenants_path(), []);
    if (!is_array($decoded)) {
        return [];
    }

    $tenants = [];
    foreach ($decoded as $tenant) {
        if (!is_array($tenant)) {
            continue;
        }

        $id = sanitize_tenant_id((string) ($tenant['id'] ?? ''));
        if ($id === '') {
            continue;
        }

        $tenants[] = [
            'id' => $id,
            'name' => trim((string) ($tenant['name'] ?? $id)),
            'status' => ((string) ($tenant['status'] ?? 'active')) === 'disabled' ? 'disabled' : 'active',
            'created_at' => (string) ($tenant['created_at'] ?? gmdate('c')),
        ];
    }

    usort($tenants, static fn (array $a, array $b): int => strcmp($a['id'], $b['id']));
    return $tenants;
}

function save_tenants(array $tenants): bool
{
    return write_json_file_atomic(tenants_path(), array_values($tenants));
}

function upsert_tenant(string $tenantId, ?string $name = null, string $status = 'active'): bool
{
    $tenantId = sanitize_tenant_id($tenantId);
    if ($tenantId === '') {
        return false;
    }

    $tenants = read_tenants();
    $updated = false;

    foreach ($tenants as &$tenant) {
        if ((string) ($tenant['id'] ?? '') !== $tenantId) {
            continue;
        }

        if ($name !== null && trim($name) !== '') {
            $tenant['name'] = trim($name);
        }

        $tenant['status'] = $status === 'disabled' ? 'disabled' : 'active';
        $updated = true;
        break;
    }
    unset($tenant);

    if (!$updated) {
        $tenants[] = [
            'id' => $tenantId,
            'name' => trim((string) ($name ?? $tenantId)),
            'status' => $status === 'disabled' ? 'disabled' : 'active',
            'created_at' => gmdate('c'),
        ];
    }

    return save_tenants($tenants);
}
