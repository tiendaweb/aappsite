<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/content-repo.php';
require_once __DIR__ . '/includes/url.php';
require_once __DIR__ . '/includes/template-manager.php';
require_once __DIR__ . '/includes/template-helpers.php';

$isLoggedIn = current_user() !== null;
$content = read_content_file();
$activeTemplate = resolve_active_template($content, 'artistas');
$templateFile = template_index_path($activeTemplate);

if ($templateFile === null) {
    $templateFile = template_index_path('artistas');
}

if ($templateFile === null) {
    http_response_code(500);
    echo 'No se encontró una plantilla válida.';
    exit;
}

require $templateFile;
