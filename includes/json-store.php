<?php

declare(strict_types=1);

function read_json_file(string $path, $default)
{
    if (!is_file($path)) {
        return $default;
    }

    $raw = @file_get_contents($path);
    $decoded = json_decode($raw ?: 'null', true);

    if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
        return $default;
    }

    return $decoded;
}

function write_json_file_atomic(string $path, $data): bool
{
    $directory = dirname($path);
    if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
        return false;
    }

    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }

    $tmp = $path . '.tmp.' . uniqid('', true);
    $lockFile = $path . '.lock';
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

    if (!@rename($tmp, $path)) {
        @unlink($tmp);
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
        return false;
    }

    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);

    return true;
}

