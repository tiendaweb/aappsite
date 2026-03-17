<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/url.php';
require_once __DIR__ . '/includes/billing.php';
require_once __DIR__ . '/includes/tenants.php';
require_once __DIR__ . '/includes/template-manager.php';

function next_user_id(array $users): int
{
    $max = 0;
    foreach ($users as $user) {
        $id = (int) ($user['id'] ?? 0);
        if ($id > $max) {
            $max = $id;
        }
    }

    return $max + 1;
}

$status = '';
$error = '';

if (isset($_POST['action']) && (string) $_POST['action'] === 'super_login') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $users = all_global_users();

    if ($users === []) {
        if ($username === '' || strlen($password) < 10) {
            $error = 'Configuración inicial: usuario requerido y contraseña de al menos 10 caracteres.';
        } else {
            $seed = [
                'id' => 1,
                'username' => $username,
                'name' => 'Super Administrador',
                'role' => ROLE_SUPER_ADMIN,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ];

            if (!save_global_users([$seed])) {
                $error = 'No se pudo crear el super-admin inicial.';
            } else {
                $_SESSION['user_id'] = 1;
                $_SESSION['auth_role'] = ROLE_SUPER_ADMIN;
                unset($_SESSION['tenant_id']);
                header('Location: ' . url_for('/super-admin.php'));
                exit;
            }
        }
    } else {
        $user = find_global_user_by_username($username);
        if ($user === null || !password_verify($password, (string) ($user['password_hash'] ?? ''))) {
            $error = 'Credenciales inválidas.';
        } else {
            $_SESSION['user_id'] = (string) ($user['id'] ?? '');
            $_SESSION['auth_role'] = ROLE_SUPER_ADMIN;
            unset($_SESSION['tenant_id']);
            header('Location: ' . url_for('/super-admin.php'));
            exit;
        }
    }
}

if (isset($_GET['logout']) && (string) $_GET['logout'] === '1') {
    unset($_SESSION['user_id'], $_SESSION['auth_role']);
    header('Location: ' . url_for('/super-admin.php'));
    exit;
}

$superAdmin = current_super_admin();
if ($superAdmin !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create_plan' || $action === 'update_plan' || $action === 'delete_plan') {
        $plans = read_plans();
        $planId = strtolower(trim((string) ($_POST['plan_id'] ?? '')));
        if ($action !== 'delete_plan' && preg_match('/^[a-z0-9\-]{2,30}$/', $planId) !== 1) {
            $error = 'Plan ID inválido.';
        } else {
            if ($action === 'create_plan') {
                foreach ($plans as $plan) {
                    if ((string) ($plan['id'] ?? '') === $planId) {
                        $error = 'El plan ya existe.';
                        break;
                    }
                }

                if ($error === '') {
                    $plans[] = [
                        'id' => $planId,
                        'name' => trim((string) ($_POST['name'] ?? '')),
                        'description' => trim((string) ($_POST['description'] ?? '')),
                        'price_monthly' => max(0, (int) ($_POST['price_monthly'] ?? 0)),
                        'is_default' => false,
                    ];
                    $status = save_plans($plans) ? 'Plan creado.' : '';
                    $error = $status === '' ? 'No se pudo guardar el plan.' : '';
                }
            } elseif ($action === 'update_plan') {
                foreach ($plans as &$plan) {
                    if ((string) ($plan['id'] ?? '') !== $planId) {
                        continue;
                    }

                    $plan['name'] = trim((string) ($_POST['name'] ?? $plan['name'] ?? ''));
                    $plan['description'] = trim((string) ($_POST['description'] ?? $plan['description'] ?? ''));
                    $plan['price_monthly'] = max(0, (int) ($_POST['price_monthly'] ?? $plan['price_monthly'] ?? 0));
                    if (!empty($_POST['is_default'])) {
                        foreach ($plans as &$other) {
                            $other['is_default'] = false;
                        }
                        unset($other);
                        $plan['is_default'] = true;
                    }
                    break;
                }
                unset($plan);

                $status = save_plans($plans) ? 'Plan actualizado.' : '';
                $error = $status === '' ? 'No se pudo actualizar el plan.' : '';
            } else {
                $plans = array_values(array_filter($plans, static fn (array $plan): bool => (string) ($plan['id'] ?? '') !== $planId));
                $status = save_plans($plans) ? 'Plan eliminado.' : '';
                $error = $status === '' ? 'No se pudo eliminar el plan.' : '';
            }
        }
    }

    if ($action === 'create_global_user' || $action === 'delete_global_user') {
        $users = all_global_users();
        $username = trim((string) ($_POST['username'] ?? ''));

        if ($action === 'create_global_user') {
            $password = (string) ($_POST['password'] ?? '');
            if ($username === '' || strlen($password) < 10) {
                $error = 'Usuario obligatorio y contraseña mínima de 10 caracteres.';
            } elseif (find_global_user_by_username($username) !== null) {
                $error = 'El usuario global ya existe.';
            } else {
                $users[] = [
                    'id' => next_user_id($users),
                    'username' => $username,
                    'name' => trim((string) ($_POST['name'] ?? 'Super Admin')),
                    'role' => ROLE_SUPER_ADMIN,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ];
                $status = save_global_users($users) ? 'Usuario global creado.' : '';
                $error = $status === '' ? 'No se pudo crear el usuario global.' : '';
            }
        } else {
            $users = array_values(array_filter($users, static fn (array $user): bool => (string) ($user['username'] ?? '') !== $username));
            $status = save_global_users($users) ? 'Usuario global eliminado.' : '';
            $error = $status === '' ? 'No se pudo eliminar el usuario global.' : '';
        }
    }

    if ($action === 'upsert_tenant' || $action === 'delete_tenant') {
        $tenantId = sanitize_tenant_id((string) ($_POST['tenant_id'] ?? ''));
        if ($tenantId === '' || $tenantId === DEFAULT_TENANT_ID) {
            $error = 'Tenant inválido.';
        } elseif ($action === 'upsert_tenant') {
            $name = trim((string) ($_POST['name'] ?? $tenantId));
            $statusTenant = (string) ($_POST['status'] ?? 'active');
            $ok = ensure_tenant_directories($tenantId) && upsert_tenant($tenantId, $name, $statusTenant);
            $status = $ok ? 'Tenant guardado.' : '';
            $error = $status === '' ? 'No se pudo guardar el tenant.' : '';
        } else {
            $tenants = array_values(array_filter(read_tenants(), static fn (array $tenant): bool => (string) ($tenant['id'] ?? '') !== $tenantId));
            $status = save_tenants($tenants) ? 'Tenant eliminado del registro.' : '';
            $error = $status === '' ? 'No se pudo eliminar el tenant.' : '';
        }
    }

    if ($action === 'add_global_template' || $action === 'remove_global_template') {
        $slug = strtolower(trim((string) ($_POST['slug'] ?? '')));
        if (!is_valid_template_slug($slug)) {
            $error = 'Slug de plantilla inválido.';
        } else {
            $registry = read_global_template_registry();
            if ($action === 'add_global_template') {
                $registry[] = $slug;
                $registry = array_values(array_unique($registry));
                sort($registry);
                $status = save_global_template_registry($registry) ? 'Plantilla global agregada.' : '';
                $error = $status === '' ? 'No se pudo guardar la plantilla global.' : '';
            } else {
                $registry = array_values(array_filter($registry, static fn (string $item): bool => $item !== $slug));
                $status = save_global_template_registry($registry) ? 'Plantilla global eliminada.' : '';
                $error = $status === '' ? 'No se pudo eliminar la plantilla global.' : '';
            }
        }
    }

    $superAdmin = current_super_admin();
}

if ($superAdmin === null):
?>
<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Super Admin</title><script src="https://cdn.tailwindcss.com"></script></head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-6">
<form method="post" class="w-full max-w-md bg-white/10 rounded-2xl border border-white/20 p-6 space-y-4">
<h1 class="text-2xl font-semibold">Super Admin</h1>
<?php if ($error !== ''): ?><p class="text-red-300 text-sm"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<input type="hidden" name="action" value="super_login">
<input class="w-full rounded-lg bg-slate-900/60 border border-white/20 p-3" name="username" placeholder="Usuario">
<input class="w-full rounded-lg bg-slate-900/60 border border-white/20 p-3" type="password" name="password" placeholder="Contraseña">
<button class="w-full bg-cyan-300 text-slate-900 rounded-lg px-4 py-2 font-semibold">Entrar</button>
</form></body></html>
<?php
exit;
endif;

$plans = read_plans();
$globalUsers = all_global_users();
$tenants = read_tenants();
$globalTemplates = read_global_template_registry();
?>
<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Panel Super Admin</title><script src="https://cdn.tailwindcss.com"></script></head>
<body class="bg-slate-950 text-slate-100 min-h-screen p-6">
<main class="max-w-6xl mx-auto space-y-8">
<header class="flex justify-between items-center"><h1 class="text-3xl font-bold">Panel Super Admin</h1><a href="<?= htmlspecialchars(url_for('/super-admin.php?logout=1'), ENT_QUOTES, 'UTF-8') ?>" class="text-sm text-cyan-300">Cerrar sesión</a></header>
<?php if ($status !== ''): ?><p class="text-emerald-300"><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<?php if ($error !== ''): ?><p class="text-red-300"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

<section class="bg-white/5 rounded-xl p-4 space-y-3"><h2 class="text-xl font-semibold">Gestión de planes</h2>
<?php foreach ($plans as $plan): ?><form method="post" class="grid grid-cols-5 gap-2 items-center"><input type="hidden" name="action" value="update_plan"><input type="hidden" name="plan_id" value="<?= htmlspecialchars((string) ($plan['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><input name="name" value="<?= htmlspecialchars((string) ($plan['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="bg-slate-900/60 p-2 rounded"><input name="description" value="<?= htmlspecialchars((string) ($plan['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="bg-slate-900/60 p-2 rounded"><input name="price_monthly" type="number" min="0" value="<?= (int) ($plan['price_monthly'] ?? 0) ?>" class="bg-slate-900/60 p-2 rounded"><label><input type="checkbox" name="is_default" value="1" <?= !empty($plan['is_default']) ? 'checked' : '' ?>> default</label><div class="space-x-2"><button class="bg-cyan-300 text-slate-900 px-3 py-2 rounded">Guardar</button></form><form method="post" class="inline"><input type="hidden" name="action" value="delete_plan"><input type="hidden" name="plan_id" value="<?= htmlspecialchars((string) ($plan['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><button class="bg-red-400/80 px-3 py-2 rounded">Borrar</button></form></div><?php endforeach; ?>
<form method="post" class="grid grid-cols-4 gap-2"><input type="hidden" name="action" value="create_plan"><input name="plan_id" placeholder="id" class="bg-slate-900/60 p-2 rounded"><input name="name" placeholder="nombre" class="bg-slate-900/60 p-2 rounded"><input name="description" placeholder="descripción" class="bg-slate-900/60 p-2 rounded"><input type="number" min="0" name="price_monthly" placeholder="precio" class="bg-slate-900/60 p-2 rounded"><button class="bg-emerald-400 text-slate-900 px-3 py-2 rounded">Crear plan</button></form>
</section>

<section class="bg-white/5 rounded-xl p-4 space-y-3"><h2 class="text-xl font-semibold">Gestión de usuarios globales</h2>
<ul class="space-y-2"><?php foreach ($globalUsers as $user): ?><li class="flex justify-between"><span><?= htmlspecialchars((string) ($user['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span><form method="post"><input type="hidden" name="action" value="delete_global_user"><input type="hidden" name="username" value="<?= htmlspecialchars((string) ($user['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><button class="text-red-300">Borrar</button></form></li><?php endforeach; ?></ul>
<form method="post" class="grid grid-cols-4 gap-2"><input type="hidden" name="action" value="create_global_user"><input name="username" placeholder="usuario" class="bg-slate-900/60 p-2 rounded"><input name="name" placeholder="nombre" class="bg-slate-900/60 p-2 rounded"><input type="password" name="password" placeholder="contraseña" class="bg-slate-900/60 p-2 rounded"><button class="bg-emerald-400 text-slate-900 px-3 py-2 rounded">Crear usuario</button></form>
</section>

<section class="bg-white/5 rounded-xl p-4 space-y-3"><h2 class="text-xl font-semibold">Gestión de sitios / tenants</h2>
<ul class="space-y-2"><?php foreach ($tenants as $tenant): ?><li class="flex justify-between"><span><?= htmlspecialchars((string) ($tenant['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars((string) ($tenant['status'] ?? 'active'), ENT_QUOTES, 'UTF-8') ?>)</span><form method="post"><input type="hidden" name="action" value="delete_tenant"><input type="hidden" name="tenant_id" value="<?= htmlspecialchars((string) ($tenant['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><button class="text-red-300">Borrar</button></form></li><?php endforeach; ?></ul>
<form method="post" class="grid grid-cols-4 gap-2"><input type="hidden" name="action" value="upsert_tenant"><input name="tenant_id" placeholder="tenant-id" class="bg-slate-900/60 p-2 rounded"><input name="name" placeholder="nombre" class="bg-slate-900/60 p-2 rounded"><select name="status" class="bg-slate-900/60 p-2 rounded"><option value="active">active</option><option value="disabled">disabled</option></select><button class="bg-emerald-400 text-slate-900 px-3 py-2 rounded">Guardar tenant</button></form>
</section>

<section class="bg-white/5 rounded-xl p-4 space-y-3"><h2 class="text-xl font-semibold">Gestión de plantillas globales</h2>
<ul class="space-y-2"><?php foreach ($globalTemplates as $slug): ?><li class="flex justify-between"><span><?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?></span><form method="post"><input type="hidden" name="action" value="remove_global_template"><input type="hidden" name="slug" value="<?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?>"><button class="text-red-300">Quitar</button></form></li><?php endforeach; ?></ul>
<form method="post" class="flex gap-2"><input type="hidden" name="action" value="add_global_template"><input name="slug" placeholder="slug" class="bg-slate-900/60 p-2 rounded"><button class="bg-emerald-400 text-slate-900 px-3 py-2 rounded">Agregar</button></form>
</section>
</main>
</body></html>
