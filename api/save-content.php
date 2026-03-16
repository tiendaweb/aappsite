<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_auth();

header('Content-Type: application/json; charset=utf-8');

function read_content_file(): array
{
    $target = __DIR__ . '/../data/content.json';
    if (!file_exists($target)) {
        return [];
    }

    $decoded = json_decode(file_get_contents($target) ?: '{}', true);
    return is_array($decoded) ? $decoded : [];
}

function save_content_file(array $data): bool
{
    $target = __DIR__ . '/../data/content.json';
    return file_put_contents($target, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL) !== false;
}

function set_value_by_path(array &$data, string $path, $value): void
{
    $normalized = preg_replace('/\[(\d+)\]/', '.$1', $path) ?: $path;
    $segments = array_filter(explode('.', $normalized), static fn ($s) => $s !== '');

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'upload-image') {
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

    $tmpPath = (string) ($file['tmp_name'] ?? '');
    $mime = mime_content_type($tmpPath) ?: '';
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($allowed[$mime])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Formato de imagen no permitido']);
        exit;
    }

    if ((int) ($file['size'] ?? 0) > 5 * 1024 * 1024) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'La imagen supera 5MB']);
        exit;
    }

    $uploadsDir = dirname(__DIR__) . '/uploads';
    if (!is_dir($uploadsDir) && !mkdir($uploadsDir, 0775, true) && !is_dir($uploadsDir)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'No se pudo crear directorio de subidas']);
        exit;
    }

    $filename = sprintf('img_%s.%s', bin2hex(random_bytes(8)), $allowed[$mime]);
    $destination = $uploadsDir . '/' . $filename;
    if (!move_uploaded_file($tmpPath, $destination)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'No se pudo guardar la imagen']);
        exit;
    }

    $publicUrl = '/uploads/' . $filename;
    $currentContent = read_content_file();
    set_value_by_path($currentContent, $key, $publicUrl);

    if (!save_content_file($currentContent)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'No se pudo persistir el contenido']);
        exit;
    }

    echo json_encode(['ok' => true, 'key' => $key, 'url' => $publicUrl]);
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
