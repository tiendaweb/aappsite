<?php

declare(strict_types=1);

function content_get(array $data, string $path, $default = null)
{
    $segments = explode('.', $path);
    $current = $data;

    foreach ($segments as $segment) {
        if (!is_array($current) || !array_key_exists($segment, $current)) {
            return $default;
        }
        $current = $current[$segment];
    }

    return $current;
}

function esc($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function resolve_image_url($image): string
{
    if (is_array($image)) {
        $value = $image['value'] ?? $image['url'] ?? '';
        return is_string($value) ? $value : '';
    }

    return is_string($image) ? $image : '';
}

function resolve_image_source_type($image): string
{
    if (is_array($image) && isset($image['source_type']) && is_string($image['source_type'])) {
        return $image['source_type'];
    }

    return 'url';
}
