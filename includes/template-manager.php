<?php

declare(strict_types=1);

require_once __DIR__ . '/tenant.php';
require_once __DIR__ . '/json-store.php';

function templates_dir_path(): string
{
    return dirname(__DIR__) . '/templates';
}

function template_registry_path(?string $tenantId = null): string
{
    $tenantId = sanitize_tenant_id($tenantId ?? resolve_tenant_id());

    return tenant_file_path($tenantId, 'templates-index.json');
}

function read_template_registry(?string $tenantId = null): array
{
    $tenantId = sanitize_tenant_id($tenantId ?? resolve_tenant_id());
    run_initial_tenant_migration($tenantId);

    $path = template_registry_path($tenantId);
    if (!is_file($path)) {
        return [];
    }

    $decoded = read_json_file($path, []);
    if (!is_array($decoded)) {
        return [];
    }

    $valid = [];
    foreach ($decoded as $slug) {
        if (is_string($slug) && preg_match('/^[a-z0-9\-]+$/', $slug) === 1) {
            $valid[] = $slug;
        }
    }

    return array_values(array_unique($valid));
}

function save_template_registry(array $slugs, ?string $tenantId = null): bool
{
    $tenantId = sanitize_tenant_id($tenantId ?? resolve_tenant_id());
    if (!ensure_tenant_directories($tenantId)) {
        return false;
    }

    $path = template_registry_path($tenantId);
    $normalized = [];

    foreach ($slugs as $slug) {
        if (is_string($slug) && preg_match('/^[a-z0-9\-]+$/', $slug) === 1) {
            $normalized[] = $slug;
        }
    }

    $normalized = array_values(array_unique($normalized));
    sort($normalized);

    return write_json_file_atomic($path, $normalized);
}

function global_template_registry_path(): string
{
    return dirname(__DIR__) . '/data/templates-index.json';
}

function read_global_template_registry(): array
{
    $decoded = read_json_file(global_template_registry_path(), []);
    if (!is_array($decoded)) {
        return [];
    }

    $slugs = [];
    foreach ($decoded as $slug) {
        if (is_string($slug) && is_valid_template_slug($slug)) {
            $slugs[] = $slug;
        }
    }

    $slugs = array_values(array_unique($slugs));
    sort($slugs);
    return $slugs;
}

function save_global_template_registry(array $slugs): bool
{
    $normalized = [];
    foreach ($slugs as $slug) {
        if (is_string($slug) && is_valid_template_slug($slug)) {
            $normalized[] = $slug;
        }
    }

    $normalized = array_values(array_unique($normalized));
    sort($normalized);
    return write_json_file_atomic(global_template_registry_path(), $normalized);
}

function register_template_slug(string $slug, ?string $tenantId = null): bool
{
    if (preg_match('/^[a-z0-9\-]+$/', $slug) !== 1) {
        return false;
    }

    $registry = read_template_registry($tenantId);
    $registry[] = $slug;

    return save_template_registry($registry, $tenantId);
}

function unregister_template_slug(string $slug, ?string $tenantId = null): bool
{
    if (!is_valid_template_slug($slug)) {
        return false;
    }

    $registry = array_values(array_filter(
        read_template_registry($tenantId),
        static fn (string $registeredSlug): bool => $registeredSlug !== $slug
    ));

    return save_template_registry($registry, $tenantId);
}

function delete_template_directory(string $slug): bool
{
    if (!is_valid_template_slug($slug)) {
        return false;
    }

    // Las plantillas físicas son globales al sistema. Para evitar cruces entre tenants,
    // el borrado de un tenant solo desregistra su uso y no elimina archivos compartidos.
    return true;
}

function list_available_templates(?string $tenantId = null): array
{
    $templatesDir = templates_dir_path();
    if (!is_dir($templatesDir)) {
        return [];
    }

    $entries = scandir($templatesDir);
    if ($entries === false) {
        return [];
    }

    $templates = [];
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        if (!preg_match('/^[a-z0-9\-]+$/', $entry)) {
            continue;
        }

        $indexFile = $templatesDir . '/' . $entry . '/index.php';
        if (is_file($indexFile)) {
            $templates[] = $entry;
        }
    }

    $templates = array_values(array_unique(array_merge($templates, read_global_template_registry(), read_template_registry($tenantId))));
    sort($templates);

    return $templates;
}

function is_valid_template_slug(string $slug): bool
{
    return preg_match('/^[a-z0-9\-]+$/', $slug) === 1;
}

function template_index_path(string $slug): ?string
{
    if (!is_valid_template_slug($slug)) {
        return null;
    }

    $path = templates_dir_path() . '/' . $slug . '/index.php';

    return is_file($path) ? $path : null;
}

function resolve_active_template(array $content, string $fallback = 'artistas', ?string $tenantId = null): string
{
    $available = list_available_templates($tenantId);
    if ($available === []) {
        return $fallback;
    }

    if (!in_array($fallback, $available, true)) {
        $fallback = $available[0];
    }

    $configured = $content['site']['template'] ?? null;
    if (!is_string($configured) || $configured === '') {
        return $fallback;
    }

    if (!in_array($configured, $available, true)) {
        return $fallback;
    }

    return $configured;
}
