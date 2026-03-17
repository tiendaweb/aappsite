<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/url.php';
require_once __DIR__ . '/includes/billing.php';

$planId = (string) ($_SESSION['selected_plan_id'] ?? default_plan_id());
$plan = find_plan_by_id($planId);
if ($plan === null || empty($plan['price_monthly'])) {
    header('Location: ' . url_for('/register'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['checkout_completed'] = true;
    header('Location: ' . url_for('/register'));
    exit;
}
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Checkout</title><script src="https://cdn.tailwindcss.com"></script></head>
<body class="bg-slate-950 text-slate-100 min-h-screen p-6"><main class="max-w-xl mx-auto"><h1 class="text-3xl font-bold">Checkout simulado</h1><p class="text-slate-300 mt-4">Plan: <?= htmlspecialchars((string) ($plan['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?> · $<?= htmlspecialchars((string) ($plan['price_monthly'] ?? ''), ENT_QUOTES, 'UTF-8') ?>/mes</p><form method="post" class="mt-8"><button type="submit" class="bg-cyan-300 text-slate-950 px-5 py-2 rounded-lg font-semibold">Pagar y continuar</button></form></main></body></html>
