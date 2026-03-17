<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/content-repo.php';
require_once __DIR__ . '/includes/url.php';
require_once __DIR__ . '/includes/tenant.php';
require_once __DIR__ . '/includes/template-manager.php';
require_once __DIR__ . '/includes/template-helpers.php';
require_once __DIR__ . '/includes/integrations.php';
require_once __DIR__ . '/includes/billing.php';

$tenantId = resolve_tenant_id();
run_initial_tenant_migration($tenantId);

if ($tenantId === DEFAULT_TENANT_ID && !has_users($tenantId) && current_user($tenantId) === null) {
    if (isset($_SESSION['selected_plan_id'])) {
        $selectedPlan = find_plan_by_id((string) $_SESSION['selected_plan_id']);
        if ($selectedPlan !== null && !empty($selectedPlan['price_monthly']) && empty($_SESSION['checkout_completed'])) {
            header('Location: ' . url_for('/checkout'));
            exit;
        }

        header('Location: ' . url_for('/register'));
        exit;
    }

    header('Location: ' . url_for('/app-home'));
    exit;
}

$isLoggedIn = current_user($tenantId) !== null;
$storedTemplate = resolve_stored_template_slug($tenantId);
$activeTemplate = resolve_active_template(['site' => ['template' => $storedTemplate]], 'artistas', $tenantId);
$content = read_content_file($tenantId, $activeTemplate);
$activeTemplate = resolve_active_template($content, $activeTemplate, $tenantId);
$templateFile = template_index_path($activeTemplate);

if ($templateFile === null) {
    $templateFile = template_index_path('artistas');
}

if ($templateFile === null) {
    http_response_code(500);
    echo 'No se encontró una plantilla válida.';
    exit;
}

ob_start();
require $templateFile;
$output = (string) ob_get_clean();

$headInjection = build_head_injections($content);
if ($headInjection !== '') {
    if (stripos($output, '</head>') !== false) {
        $output = preg_replace('/<\/head>/i', $headInjection . "\n</head>", $output, 1) ?? $output;
    } else {
        $output = $headInjection . "\n" . $output;
    }
}

if ($isLoggedIn) {
    $bootstrap = '<script>window.APP_IS_AUTHENTICATED=true;window.APP_ACTIVE_TEMPLATE=' . json_encode($activeTemplate, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';window.APP_CONTENT_STATE=' . json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';window.ADMIN_EDITOR_ENDPOINTS={saveContent:' . json_encode(url_for('/api/save-content.php'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ',uploadImage:' . json_encode(url_for('/api/upload-image.php'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ',listImages:' . json_encode(url_for('/api/list-images.php'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '};</script>';
    $editorScript = '<script src="' . esc(url_for('/public/js/admin-editor.js')) . '"></script>';
    $injection = $bootstrap . "\n" . $editorScript;

    if (stripos($output, '</body>') !== false) {
        $output = preg_replace('/<\/body>/i', $injection . "\n</body>", $output, 1) ?? $output;
    } else {
        $output .= "\n" . $injection;
    }
}

echo $output;
