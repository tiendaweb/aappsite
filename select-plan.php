<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/url.php';
require_once __DIR__ . '/includes/billing.php';

$plans = read_plans();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $planId = (string) ($_POST['plan_id'] ?? default_plan_id());
    $plan = find_plan_by_id($planId);

    if ($plan === null) {
        $error = 'Plan inválido.';
    } else {
        $_SESSION['selected_plan_id'] = $planId;
        $_SESSION['checkout_completed'] = false;

        if (!empty($plan['price_monthly'])) {
            header('Location: ' . url_for('/checkout'));
            exit;
        }

        header('Location: ' . url_for('/register'));
        exit;
    }
}
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Elegir plan</title><script src="https://cdn.tailwindcss.com"></script></head>
<body class="bg-slate-950 text-slate-100 min-h-screen p-6"><main class="max-w-4xl mx-auto"><h1 class="text-3xl font-bold mb-6">Elegí tu plan</h1>
<?php if ($error !== ''): ?><p class="mb-4 text-red-300"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<form method="post" class="grid md:grid-cols-2 gap-4"><?php foreach ($plans as $plan): ?><label class="border border-white/15 rounded-xl p-5 bg-white/5 block cursor-pointer"><input type="radio" name="plan_id" value="<?= htmlspecialchars((string) ($plan['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="mr-2" <?= !empty($plan['is_default']) ? 'checked' : '' ?>><strong><?= htmlspecialchars((string) ($plan['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong><p class="text-sm text-slate-300 mt-2"><?= htmlspecialchars((string) ($plan['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p><p class="text-cyan-300 mt-2"><?= !empty($plan['price_monthly']) ? '$' . htmlspecialchars((string) $plan['price_monthly'], ENT_QUOTES, 'UTF-8') . '/mes' : 'Gratis' ?></p></label><?php endforeach; ?><div class="md:col-span-2"><button class="bg-cyan-300 text-slate-950 px-5 py-2 rounded-lg font-semibold" type="submit">Continuar</button></div></form></main></body></html>
