<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/url.php';

require_auth();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

$uploadsDir = dirname(__DIR__) . '/public/uploads';
$allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

if (!is_dir($uploadsDir)) {
    echo json_encode(['ok' => true, 'images' => []]);
    exit;
}

$entries = scandir($uploadsDir);
if ($entries === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'No se pudo leer la biblioteca']);
    exit;
}

$images = [];
foreach ($entries as $entry) {
    if ($entry === '.' || $entry === '..') {
        continue;
    }

    $filePath = $uploadsDir . '/' . $entry;
    if (!is_file($filePath)) {
        continue;
    }

    $extension = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions, true)) {
        continue;
    }

    $mtime = filemtime($filePath);
    if ($mtime === false) {
        $mtime = 0;
    }

    $images[] = [
        'url' => url_for('/public/uploads/' . rawurlencode($entry)),
        'name' => $entry,
        'mtime' => $mtime,
    ];
}

usort($images, static function (array $a, array $b): int {
    return ($b['mtime'] ?? 0) <=> ($a['mtime'] ?? 0);
});

echo json_encode(['ok' => true, 'images' => $images]);
