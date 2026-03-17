<?php

declare(strict_types=1);

require_once __DIR__ . '/tenant.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/content-repo.php';
require_once __DIR__ . '/template-manager.php';
require_once __DIR__ . '/billing.php';

function tenant_exists(string $tenantId): bool
{
    $tenantId = sanitize_tenant_id($tenantId);

    return is_dir(tenant_data_dir($tenantId)) || tenant_has_subscription($tenantId);
}

function seed_tenant_files(string $tenantId): bool
{
    if (!ensure_tenant_directories($tenantId)) {
        return false;
    }

    $content = json_decode(file_get_contents(dirname(__DIR__) . '/data/content.seed.json') ?: '{}', true);
    if (!is_array($content)) {
        $content = [];
    }

    if (!save_content_file($content, $tenantId)) {
        return false;
    }

    return save_template_registry(['artistas', 'fitness'], $tenantId);
}

function create_tenant_account(string $tenantId, string $email, string $password, string $planId): array
{
    $tenantId = sanitize_tenant_id($tenantId);
    $email = trim($email);

    if ($tenantId === DEFAULT_TENANT_ID) {
        return ['ok' => false, 'error' => 'Elegí un identificador de sitio diferente.'];
    }

    if (tenant_exists($tenantId)) {
        return ['ok' => false, 'error' => 'Ese identificador de sitio ya está en uso.'];
    }

    if (!seed_tenant_files($tenantId)) {
        return ['ok' => false, 'error' => 'No se pudo crear la estructura inicial del sitio.'];
    }

    $user = [
        'id' => 1,
        'username' => $email,
        'name' => 'Administrador',
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    ];

    if (!save_users([$user], $tenantId)) {
        return ['ok' => false, 'error' => 'No se pudo guardar el usuario administrador.'];
    }

    if (!add_subscription($tenantId, $planId, $email)) {
        return ['ok' => false, 'error' => 'No se pudo registrar la suscripción.'];
    }

    return ['ok' => true, 'user' => $user, 'tenant_id' => $tenantId];
}
