<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/tenant.php';
require_once __DIR__ . '/../includes/tenants.php';
require_once __DIR__ . '/../includes/json-store.php';
require_once __DIR__ . '/../includes/content-repo.php';
require_once __DIR__ . '/../includes/template-manager.php';

function collect_tenant_ids(): array
{
    $ids = [DEFAULT_TENANT_ID];

    foreach (read_tenants() as $tenant) {
        if (!is_array($tenant)) {
            continue;
        }

        $tenantId = sanitize_tenant_id((string) ($tenant['id'] ?? ''));
        if ($tenantId !== '') {
            $ids[] = $tenantId;
        }
    }

    $tenantsRoot = dirname(__DIR__) . '/data/tenants';
    if (is_dir($tenantsRoot)) {
        $entries = scandir($tenantsRoot);
        if ($entries !== false) {
            foreach ($entries as $entry) {
                $tenantId = sanitize_tenant_id((string) $entry);
                if ($tenantId !== '' && $entry !== '.' && $entry !== '..') {
                    $ids[] = $tenantId;
                }
            }
        }
    }

    $ids = array_values(array_unique($ids));
    sort($ids);

    return $ids;
}

function resolve_template_targets(string $tenantId, array $legacyContent): array
{
    $active = infer_template_slug_from_content($legacyContent);
    if ($active !== null) {
        return [$active];
    }

    $registered = read_template_registry($tenantId);
    if ($registered !== []) {
        return $registered;
    }

    return ['artistas'];
}

function backup_legacy_content(string $legacyPath): bool
{
    $backupPath = $legacyPath . '.bak';

    if (is_file($backupPath)) {
        return true;
    }

    return @copy($legacyPath, $backupPath);
}

$summary = [
    'migrated' => 0,
    'skipped' => 0,
    'error' => 0,
];

foreach (collect_tenant_ids() as $tenantId) {
    $legacyPath = content_file_path($tenantId);

    if (!is_file($legacyPath)) {
        echo sprintf("[tenant:%s] [template:-] omitted (sin content.json legacy)\n", $tenantId);
        $summary['skipped']++;
        continue;
    }

    $legacyContent = read_json_file($legacyPath, null);
    if (!is_array($legacyContent)) {
        echo sprintf("[tenant:%s] [template:-] error (content.json inválido)\n", $tenantId);
        $summary['error']++;
        continue;
    }

    if (!backup_legacy_content($legacyPath)) {
        echo sprintf("[tenant:%s] [template:-] error (no se pudo crear backup)\n", $tenantId);
        $summary['error']++;
        continue;
    }

    foreach (resolve_template_targets($tenantId, $legacyContent) as $templateSlug) {
        $targetPath = template_content_file_path($tenantId, $templateSlug);

        if (!is_string($targetPath)) {
            echo sprintf("[tenant:%s] [template:%s] error (slug inválido)\n", $tenantId, (string) $templateSlug);
            $summary['error']++;
            continue;
        }

        $current = read_json_file($targetPath, null);
        if (is_array($current)) {
            if ($current === $legacyContent) {
                echo sprintf("[tenant:%s] [template:%s] omitted (ya migrado)\n", $tenantId, $templateSlug);
                $summary['skipped']++;
                continue;
            }

            echo sprintf("[tenant:%s] [template:%s] omitted (destino ya existe con contenido distinto)\n", $tenantId, $templateSlug);
            $summary['skipped']++;
            continue;
        }

        if (write_json_file_atomic($targetPath, $legacyContent)) {
            echo sprintf("[tenant:%s] [template:%s] migrated\n", $tenantId, $templateSlug);
            $summary['migrated']++;
            continue;
        }

        echo sprintf("[tenant:%s] [template:%s] error (no se pudo escribir destino)\n", $tenantId, $templateSlug);
        $summary['error']++;
    }
}

echo PHP_EOL;
echo sprintf(
    "Resumen => migrated: %d | omitted: %d | error: %d\n",
    $summary['migrated'],
    $summary['skipped'],
    $summary['error']
);
