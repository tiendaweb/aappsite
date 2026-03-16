<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_auth();

header('Content-Type: application/json; charset=utf-8');

function save_content_file(array $data): bool
{
    $target = __DIR__ . '/../data/content.json';
    return file_put_contents($target, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL) !== false;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

$payload = file_get_contents('php://input');
$data = json_decode($payload ?: '', true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'JSON inválido']);
    exit;
}

if (!save_content_file($data)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'No se pudo guardar']);
    exit;
}

echo json_encode(['ok' => true]);
