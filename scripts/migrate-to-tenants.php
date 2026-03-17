<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/tenant.php';

$defaultTenant = default_tenant_id();
set_current_tenant_id($defaultTenant);

if (!ensure_tenant_storage($defaultTenant)) {
    fwrite(STDERR, "No se pudo crear la estructura base del tenant default.\n");
    exit(1);
}

$repoRoot = dirname(__DIR__);
$migrations = [
    'data/content.json' => 'data/tenants/' . $defaultTenant . '/content.json',
    'data/users.json' => 'data/tenants/' . $defaultTenant . '/users.json',
    'data/templates-index.json' => 'data/tenants/' . $defaultTenant . '/templates-index.json',
];

foreach ($migrations as $sourceRel => $targetRel) {
    $source = $repoRoot . '/' . $sourceRel;
    $target = $repoRoot . '/' . $targetRel;

    if (!is_file($source)) {
        echo "SKIP {$sourceRel}: no existe.\n";
        continue;
    }

    if (is_file($target)) {
        echo "SKIP {$targetRel}: ya existe.\n";
        continue;
    }

    if (!copy($source, $target)) {
        fwrite(STDERR, "ERROR copiando {$sourceRel} -> {$targetRel}\n");
        exit(1);
    }

    echo "OK {$sourceRel} -> {$targetRel}\n";
}

echo "Migración inicial completada.\n";
