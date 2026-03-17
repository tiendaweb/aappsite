<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/content-repo.php';
require_once __DIR__ . '/../includes/tenant.php';

$tenantId = resolve_tenant_id();
require_tenant_admin($tenantId);

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
    if ($data === []) {
        return false;
    }

    $validateNode = static function ($value, int $depth = 0) use (&$validateNode): bool {
        if ($depth > 32) {
            return false;
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                if (!is_int($key) && !is_string($key)) {
                    return false;
                }

                if (is_string($key) && trim($key) === '') {
                    return false;
                }

                if (!$validateNode($item, $depth + 1)) {
                    return false;
                }
            }

            return true;
        }

        return is_scalar($value) || $value === null;
    };

    return $validateNode($data);
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
    log_app_error('save-content: invalid JSON payload tenant=' . $tenantId . ' (' . $exception->getCode() . ')');
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

if (!save_content_file($data, $tenantId)) {
    log_app_error('save-content: failed to persist content JSON atomically tenant=' . $tenantId);
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'No se pudo guardar']);
    exit;
}

echo json_encode(['ok' => true]);
