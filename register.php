<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/url.php';
require_once __DIR__ . '/includes/billing.php';
require_once __DIR__ . '/includes/onboarding.php';

$planId = (string) ($_SESSION['selected_plan_id'] ?? default_plan_id());
$plan = find_plan_by_id($planId);
if ($plan === null) {
    $planId = default_plan_id();
    $plan = find_plan_by_id($planId);
}

if ($plan !== null && !empty($plan['price_monthly']) && empty($_SESSION['checkout_completed'])) {
    header('Location: ' . url_for('/checkout'));
    exit;
}

$error = '';
$tenantIdInput = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tenantIdInput = (string) ($_POST['tenant_id'] ?? '');
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (mb_strlen($tenantIdInput) < 3) {
        $error = 'El identificador del sitio debe tener al menos 3 caracteres.';
    } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $error = 'Email inválido.';
    } elseif (strlen($password) < 10) {
        $error = 'La contraseña debe tener al menos 10 caracteres.';
    } else {
        $result = create_tenant_account($tenantIdInput, $email, $password, $planId);
        if (!($result['ok'] ?? false)) {
            $error = (string) ($result['error'] ?? 'No se pudo crear la cuenta.');
        } else {
            $_SESSION['tenant_id'] = (string) $result['tenant_id'];
            $_SESSION['user_id'] = (string) (($result['user']['id'] ?? '1'));
            unset($_SESSION['selected_plan_id'], $_SESSION['checkout_completed']);
            header('Location: ' . url_for('/admin'));
            exit;
        }
    }
}
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Registro de sitio</title><script src="https://cdn.tailwindcss.com"></script></head>
<body class="bg-slate-950 text-slate-100 min-h-screen p-6"><main class="max-w-xl mx-auto"><h1 class="text-3xl font-bold">Crear tu sitio</h1><p class="text-slate-300 mt-3">Plan seleccionado: <strong><?= htmlspecialchars((string) ($plan['name'] ?? 'Gratis'), ENT_QUOTES, 'UTF-8') ?></strong></p>
<?php if ($error !== ''): ?><p class="mt-4 text-red-300"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<form method="post" class="space-y-4 mt-6"><input name="tenant_id" placeholder="mi-sitio" value="<?= htmlspecialchars($tenantIdInput, ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-lg bg-white/5 border border-white/20 p-3"><input name="email" placeholder="admin@email.com" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-lg bg-white/5 border border-white/20 p-3"><input name="password" type="password" placeholder="Contraseña" class="w-full rounded-lg bg-white/5 border border-white/20 p-3"><button type="submit" class="bg-cyan-300 text-slate-950 px-5 py-2 rounded-lg font-semibold">Crear tenant y entrar</button></form></main></body></html>
