<?php

declare(strict_types=1);

function content_file_path(): string
{
    return dirname(__DIR__) . '/data/content.json';
}

function read_content_file(): array
{
    $target = content_file_path();
    if (!file_exists($target)) {
        return [];
    }

    $decoded = json_decode(file_get_contents($target) ?: '{}', true);

    return is_array($decoded) ? $decoded : [];
}

function save_content_file(array $data): bool
{
    $target = content_file_path();
    $tmp = $target . '.tmp.' . uniqid('', true);
    $lockFile = $target . '.lock';
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    if ($json === false) {
        return false;
    }

    $lockHandle = @fopen($lockFile, 'c');
    if ($lockHandle === false) {
        return false;
    }

    if (!flock($lockHandle, LOCK_EX)) {
        fclose($lockHandle);
        return false;
    }

    $tmpHandle = @fopen($tmp, 'wb');
    if ($tmpHandle === false) {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
        return false;
    }

    $bytes = fwrite($tmpHandle, $json . PHP_EOL);
    if ($bytes === false) {
        fclose($tmpHandle);
        @unlink($tmp);
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
        return false;
    }

    fflush($tmpHandle);
    fclose($tmpHandle);

    if (!@rename($tmp, $target)) {
        @unlink($tmp);
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
        return false;
    }

    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);

    return true;
}

function set_value_by_path(array &$data, string $path, $value): void
{
    $normalized = preg_replace('/\[(\d+)\]/', '.$1', $path) ?: $path;
    $segments = array_filter(explode('.', $normalized), static fn ($segment) => $segment !== '');

    $current = &$data;
    foreach ($segments as $index => $segment) {
        $isLast = $index === array_key_last($segments);
        if ($isLast) {
            $current[$segment] = $value;
            return;
        }

        if (!isset($current[$segment]) || !is_array($current[$segment])) {
            $current[$segment] = [];
        }

        $current = &$current[$segment];
    }
}
