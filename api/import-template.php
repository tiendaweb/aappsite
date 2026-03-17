<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/tenant.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/template-importer.php';

set_current_tenant_id(resolve_tenant_id());
require_auth();

header('Content-Type: application/json; charset=utf-8');

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
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'JSON inválido']);
    exit;
}

$html = is_array($data) ? (string) ($data['html'] ?? '') : '';
$slug = is_array($data) ? (string) ($data['slug'] ?? '') : '';

if (trim($html) === '' || trim($slug) === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'html y slug son obligatorios']);
    exit;
}

try {
    $result = import_template_from_html($slug, $html, current_tenant_id());
} catch (InvalidArgumentException $exception) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => $exception->getMessage()]);
    exit;
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'No se pudo importar la plantilla']);
    exit;
}

echo json_encode(['ok' => true, 'result' => $result]);
