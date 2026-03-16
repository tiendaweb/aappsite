<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_auth();

header('Content-Type: application/json; charset=utf-8');

function log_app_error(string $message): void
{
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir) && !mkdir($logDir, 0775, true) && !is_dir($logDir)) {
        return;
    }

    $logFile = $logDir . '/app.log';
    $entry = sprintf("[%s] %s\n", date('c'), $message);
    @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
}

function validate_payload(array $data): bool
{
    $required = [
        'site' => 'array',
        'hero' => 'array',
        'stats' => 'array',
        'tabs' => 'array',
        'backgrounds' => 'array',
    ];

    foreach ($required as $key => $type) {
        if (!array_key_exists($key, $data) || gettype($data[$key]) !== $type) {
            return false;
        }
    }

    return true;
}

function save_content_file(array $data): bool
{
    $target = __DIR__ . '/../data/content.json';
    $tmp = $target . '.tmp.' . uniqid('', true);
    $lockFile = $target . '.lock';
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    if ($json === false) {
        log_app_error('save-content: failed to encode JSON payload');
        return false;
    }

    $lockHandle = @fopen($lockFile, 'c');
    if ($lockHandle === false) {
        log_app_error('save-content: failed to open lock file');
        return false;
    }

    if (!flock($lockHandle, LOCK_EX)) {
        fclose($lockHandle);
        log_app_error('save-content: failed to acquire file lock');
        return false;
    }

    $tmpHandle = @fopen($tmp, 'wb');
    if ($tmpHandle === false) {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
        log_app_error('save-content: failed to open temporary file');
        return false;
    }

    $bytes = fwrite($tmpHandle, $json . PHP_EOL);
    if ($bytes === false) {
        fclose($tmpHandle);
        @unlink($tmp);
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
        log_app_error('save-content: failed to write temporary file');
        return false;
    }

    fflush($tmpHandle);
    fclose($tmpHandle);

    if (!@rename($tmp, $target)) {
        @unlink($tmp);
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
        log_app_error('save-content: failed to move temporary file into place');
        return false;
    }

    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);

    return true;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

$payload = file_get_contents('php://input');
if ($payload === false) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'JSON inválido']);
    exit;
}

$data = json_decode($payload, true);
if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'JSON inválido']);
    exit;
}

if (!validate_payload($data)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Payload inválido']);
    exit;
}

if (!save_content_file($data)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'No se pudo guardar']);
    exit;
}

echo json_encode(['ok' => true]);
