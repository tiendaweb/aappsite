<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/url.php';
require_once __DIR__ . '/includes/tenant.php';
require_once __DIR__ . '/includes/billing.php';

$tenantId = resolve_tenant_id();
run_initial_tenant_migration($tenantId);

if ($tenantId === DEFAULT_TENANT_ID && !has_users($tenantId)) {
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

if (current_user($tenantId) !== null) {
    header('Location: ' . url_for('/admin'));
    exit;
}

$error = '';
$username = '';
$isInitialSetup = !has_users($tenantId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($isInitialSetup) {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($username === '' || mb_strlen($username) < 3 || mb_strlen($username) > 50) {
            $error = 'El usuario/email debe tener entre 3 y 50 caracteres.';
        } elseif (!preg_match('/^[A-Za-z0-9._@+-]+$/', $username)) {
            $error = 'El usuario/email contiene caracteres no permitidos.';
        } elseif (str_contains($username, '@') && filter_var($username, FILTER_VALIDATE_EMAIL) === false) {
            $error = 'El email no es válido.';
        } elseif (
            strlen($password) < 10
            || !preg_match('/[A-Z]/', $password)
            || !preg_match('/[a-z]/', $password)
            || !preg_match('/[0-9]/', $password)
            || !preg_match('/[^A-Za-z0-9]/', $password)
        ) {
            $error = 'La contraseña debe tener al menos 10 caracteres, con mayúsculas, minúsculas, número y símbolo.';
        } elseif (has_users($tenantId)) {
            $error = 'La configuración inicial ya fue completada.';
            $isInitialSetup = false;
        } else {
            $user = [
                'id' => 1,
                'username' => $username,
                'name' => 'Administrador',
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ];

            if (!save_users([$user], $tenantId)) {
                $error = 'No se pudo crear el usuario inicial.';
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['tenant_id'] = $tenantId;
                header('Location: ' . url_for('/admin'));
                exit;
            }
        }
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        $user = find_user_by_username($username, $tenantId);

        if ($user !== null && isset($user['password_hash']) && password_verify($password, (string) $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
                $_SESSION['tenant_id'] = $tenantId;
            header('Location: ' . url_for('/admin'));
            exit;
        }

        $error = 'Usuario o contraseña inválidos.';
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-950 text-slate-100 flex items-center justify-center p-6">
    <div class="w-full max-w-md rounded-3xl border border-white/20 bg-white/10 backdrop-blur-xl p-8 shadow-2xl shadow-cyan-500/10">
        <h1 class="text-2xl font-semibold mb-2"><?= $isInitialSetup ? 'Crear administrador inicial' : 'Iniciar sesión' ?></h1>
        <p class="text-sm text-slate-300 mb-6">Panel administrativo Claudia Fasce</p>

        <?php if ($error !== ''): ?>
            <p class="mb-4 rounded-xl bg-red-500/20 border border-red-400/40 text-red-200 p-3 text-sm"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>

        <form method="post" action="<?= htmlspecialchars(url_for('/login'), ENT_QUOTES, 'UTF-8') ?>" class="space-y-4">
            <label class="block space-y-2">
                <span class="text-sm text-slate-200">Usuario o email</span>
                <input type="text" name="username" required value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-xl border border-white/20 bg-slate-900/70 px-4 py-3 outline-none focus:ring-2 focus:ring-cyan-400">
            </label>
            <label class="block space-y-2">
                <span class="text-sm text-slate-200">Contraseña</span>
                <input type="password" name="password" required class="w-full rounded-xl border border-white/20 bg-slate-900/70 px-4 py-3 outline-none focus:ring-2 focus:ring-cyan-400">
            </label>
            <button type="submit" class="w-full rounded-xl bg-cyan-300 text-slate-900 font-semibold py-3 hover:bg-cyan-200 transition">
                <?= $isInitialSetup ? 'Crear administrador' : 'Entrar' ?>
            </button>
        </form>

        <?php if ($isInitialSetup): ?>
            <p class="mt-4 text-xs text-slate-300">La contraseña debe tener mínimo 10 caracteres e incluir mayúsculas, minúsculas, número y símbolo.</p>
        <?php endif; ?>
    </div>
</body>
</html>
