<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/tenant.php';

$tenantId = DEFAULT_TENANT_ID;
run_initial_tenant_migration($tenantId);

$files = [
    tenant_file_path($tenantId, 'content.json'),
    tenant_file_path($tenantId, 'users.json'),
    tenant_file_path($tenantId, 'templates-index.json'),
];

foreach ($files as $file) {
    echo (is_file($file) ? '[ok] ' : '[missing] ') . $file . PHP_EOL;
}
