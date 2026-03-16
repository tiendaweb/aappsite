<?php

declare(strict_types=1);

function templates_dir_path(): string
{
    return dirname(__DIR__) . '/templates';
}

function list_available_templates(): array
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

function resolve_active_template(array $content, string $fallback = 'artistas'): string
{
    $available = list_available_templates();
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
