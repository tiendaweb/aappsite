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
    $requiredTopLevel = [
        'site' => 'array',
        'hero' => 'array',
        'stats' => 'array',
        'tabs' => 'array',
        'backgrounds' => 'array',
    ];

    foreach ($requiredTopLevel as $key => $type) {
        if (!array_key_exists($key, $data) || gettype($data[$key]) !== $type) {
            return false;
        }
    }

    $requiredSite = [
        'lang' => 'string',
        'title' => 'string',
        'name' => 'string',
        'tagline' => 'string',
        'availability' => 'string',
        'nav' => 'array',
    ];

    foreach ($requiredSite as $key => $type) {
        if (!array_key_exists($key, $data['site']) || gettype($data['site'][$key]) !== $type) {
            return false;
        }
    }

    $requiredHero = [
        'headline_prefix' => 'string',
        'headline_highlight' => 'string',
        'headline_suffix' => 'string',
        'description' => 'string',
        'description_emphasis' => 'string',
        'featured_image' => 'array',
        'quote' => 'string',
    ];

    foreach ($requiredHero as $key => $type) {
        if (!array_key_exists($key, $data['hero']) || gettype($data['hero'][$key]) !== $type) {
            return false;
        }
    }

    return true;
}

function save_content_atomically(array $data): bool
{
    $target = __DIR__ . '/../data/content.json';
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

try {
    $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $exception) {
    log_app_error('save-content: invalid JSON payload (' . $exception->getCode() . ')');
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'JSON inválido']);
    exit;
}

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'JSON inválido']);
    exit;
}

if (!validate_payload($data)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Payload inválido']);
    exit;
}

if (!save_content_atomically($data)) {
    log_app_error('save-content: failed to persist content JSON atomically');
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'No se pudo guardar']);
    exit;
}

echo json_encode(['ok' => true]);
