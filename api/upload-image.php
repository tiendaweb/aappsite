<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/tenant.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/content-repo.php';

set_current_tenant_id(resolve_tenant_id());
require_auth();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

$key = trim((string) ($_POST['key'] ?? ''));
if ($key === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Falta la clave del campo']);
    exit;
}

if (!isset($_FILES['image']) || !is_array($_FILES['image'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No se recibió imagen']);
    exit;
}

$file = $_FILES['image'];
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Error al subir la imagen']);
    exit;
}

if ((int) ($file['size'] ?? 0) > 5 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'La imagen supera 5MB']);
    exit;
}

$tmpPath = (string) ($file['tmp_name'] ?? '');
$originalName = (string) ($file['name'] ?? '');
$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

$allowed = [
    'jpg' => ['image/jpeg', 'image/jpg', 'image/pjpeg'],
    'jpeg' => ['image/jpeg', 'image/jpg', 'image/pjpeg'],
    'png' => ['image/png'],
    'webp' => ['image/webp'],
];

if (!isset($allowed[$extension])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Extensión de imagen no permitida']);
    exit;
}

$mime = '';
if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo !== false) {
        $detected = finfo_file($finfo, $tmpPath);
        $mime = is_string($detected) ? $detected : '';
        finfo_close($finfo);
    }
} elseif (function_exists('mime_content_type')) {
    $detected = mime_content_type($tmpPath);
    $mime = is_string($detected) ? $detected : '';
}

if ($mime !== '' && !in_array($mime, $allowed[$extension], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'El MIME no coincide con la extensión']);
    exit;
}

$tenantId = current_tenant_id();
$uploadsDir = tenant_uploads_dir($tenantId);
if (!is_dir($uploadsDir) && !mkdir($uploadsDir, 0775, true) && !is_dir($uploadsDir)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'No se pudo crear directorio de subidas']);
    exit;
}

$timestamp = (new DateTimeImmutable())->format('YmdHis');
$random = bin2hex(random_bytes(6));
$normalizedExt = $extension === 'jpeg' ? 'jpg' : $extension;
$filename = sprintf('img_%s_%s.%s', $timestamp, $random, $normalizedExt);
$destination = $uploadsDir . '/' . $filename;

if (!move_uploaded_file($tmpPath, $destination)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'No se pudo guardar la imagen']);
    exit;
}

$publicUrl = '/public/uploads/' . rawurlencode($tenantId) . '/' . rawurlencode($filename);
$currentContent = read_content_file($tenantId);
set_value_by_path($currentContent, $key, [
    'source_type' => 'upload',
    'value' => $publicUrl,
]);

if (!save_content_file($currentContent, $tenantId)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'No se pudo persistir el contenido']);
    exit;
}

echo json_encode(['ok' => true, 'key' => $key, 'url' => $publicUrl]);
