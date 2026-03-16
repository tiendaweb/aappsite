<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/url.php';
require_once __DIR__ . '/includes/content-repo.php';

require_auth();
$user = current_user();

function admin_content_get(array $data, string $path, string $default = ''): string
{
    $segments = explode('.', $path);
    $current = $data;

    foreach ($segments as $segment) {
        if (!is_array($current) || !array_key_exists($segment, $current)) {
            return $default;
        }
        $current = $current[$segment];
    }

    return is_string($current) ? $current : $default;
}

$status = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'change_password') {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if (!isset($user['password_hash']) || !password_verify($currentPassword, (string) $user['password_hash'])) {
            $error = 'La contraseña actual es incorrecta.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'La nueva contraseña y la confirmación no coinciden.';
        } elseif (
            strlen($newPassword) < 10
            || !preg_match('/[A-Z]/', $newPassword)
            || !preg_match('/[a-z]/', $newPassword)
            || !preg_match('/[0-9]/', $newPassword)
            || !preg_match('/[^A-Za-z0-9]/', $newPassword)
        ) {
            $error = 'La nueva contraseña debe tener al menos 10 caracteres, mayúscula, minúscula, número y símbolo.';
        } else {
            $users = all_users();
            foreach ($users as &$storedUser) {
                if ((string) ($storedUser['id'] ?? '') === (string) ($user['id'] ?? '')) {
                    $storedUser['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
                    break;
                }
            }
            unset($storedUser);

            if (!save_users($users)) {
                $error = 'No se pudo actualizar la contraseña.';
            } else {
                $status = 'Contraseña actualizada correctamente.';
                $user = current_user();
            }
        }
    }

    if ($action === 'update_seo') {
        $seoTitle = trim((string) ($_POST['seo_title'] ?? ''));
        $seoDescription = trim((string) ($_POST['seo_description'] ?? ''));
        $seoKeywords = trim((string) ($_POST['seo_keywords'] ?? ''));
        $ogImage = trim((string) ($_POST['og_image'] ?? ''));

        if ($seoTitle === '' || $seoDescription === '') {
            $error = 'SEO title y SEO description son obligatorios.';
        } else {
            $content = read_content_file();
            if ($content === []) {
                $content = json_decode(file_get_contents(__DIR__ . '/data/content.seed.json') ?: '{}', true);
                $content = is_array($content) ? $content : [];
            }

            $content['site']['title'] = $seoTitle;
            $content['site']['seo'] = [
                'description' => $seoDescription,
                'keywords' => $seoKeywords,
                'og_image' => $ogImage,
            ];

            if (!save_content_file($content)) {
                $error = 'No se pudieron guardar los datos SEO.';
            } else {
                $status = 'Datos SEO guardados.';
            }
        }
    }
}

$content = read_content_file();
$seoTitle = admin_content_get($content, 'site.title', '');
$seoDescription = admin_content_get($content, 'site.seo.description', '');
$seoKeywords = admin_content_get($content, 'site.seo.keywords', '');
$ogImage = admin_content_get($content, 'site.seo.og_image', '');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background: radial-gradient(circle at top, #15324d 0%, #090d18 55%, #04050b 100%); }
        .glass { background: linear-gradient(135deg, rgba(255,255,255,0.16), rgba(255,255,255,0.04)); backdrop-filter: blur(18px); border: 1px solid rgba(255,255,255,0.25); }
    </style>
</head>
<body class="min-h-screen text-slate-100">
    <div class="max-w-7xl mx-auto p-6 md:p-10 space-y-8">
        <header class="glass rounded-3xl p-6 md:p-8 flex flex-col md:flex-row justify-between gap-4">
            <div>
                <p class="text-cyan-200 text-xs uppercase tracking-[0.2em]">Liquid Glass Premium SaaS</p>
                <h1 class="text-3xl md:text-4xl font-semibold">Dashboard Administrativo</h1>
                <p class="text-slate-300 mt-2">Sesión iniciada como <?= htmlspecialchars((string) ($user['name'] ?? $user['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div class="flex items-start">
                <a href="<?= htmlspecialchars(url_for('/logout'), ENT_QUOTES, 'UTF-8') ?>" class="rounded-xl bg-white/10 border border-white/20 px-4 py-2 hover:bg-white/20">Cerrar sesión</a>
            </div>
        </header>

        <?php if ($status !== ''): ?>
            <div class="rounded-xl border border-emerald-300/40 bg-emerald-400/10 text-emerald-200 p-4"><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="rounded-xl border border-red-300/40 bg-red-400/10 text-red-200 p-4"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <div class="grid lg:grid-cols-2 gap-6">
            <section class="glass rounded-3xl p-6 md:p-8">
                <h2 class="text-xl font-semibold mb-5">Cambiar contraseña</h2>
                <form method="post" class="space-y-4">
                    <input type="hidden" name="action" value="change_password">
                    <label class="block space-y-2">
                        <span class="text-sm text-slate-300">Contraseña actual</span>
                        <input type="password" name="current_password" required class="w-full rounded-xl border border-white/20 bg-slate-900/60 px-4 py-3 focus:ring-2 focus:ring-cyan-300 outline-none">
                    </label>
                    <label class="block space-y-2">
                        <span class="text-sm text-slate-300">Nueva contraseña</span>
                        <input type="password" name="new_password" required class="w-full rounded-xl border border-white/20 bg-slate-900/60 px-4 py-3 focus:ring-2 focus:ring-cyan-300 outline-none">
                    </label>
                    <label class="block space-y-2">
                        <span class="text-sm text-slate-300">Confirmar nueva contraseña</span>
                        <input type="password" name="confirm_password" required class="w-full rounded-xl border border-white/20 bg-slate-900/60 px-4 py-3 focus:ring-2 focus:ring-cyan-300 outline-none">
                    </label>
                    <button class="rounded-xl bg-cyan-300 text-slate-900 font-semibold px-5 py-3 hover:bg-cyan-200">Actualizar contraseña</button>
                </form>
            </section>

            <section class="glass rounded-3xl p-6 md:p-8">
                <h2 class="text-xl font-semibold mb-5">Datos SEO</h2>
                <form method="post" class="space-y-4">
                    <input type="hidden" name="action" value="update_seo">
                    <label class="block space-y-2">
                        <span class="text-sm text-slate-300">SEO Title</span>
                        <input type="text" name="seo_title" required value="<?= htmlspecialchars($seoTitle, ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-xl border border-white/20 bg-slate-900/60 px-4 py-3 focus:ring-2 focus:ring-cyan-300 outline-none">
                    </label>
                    <label class="block space-y-2">
                        <span class="text-sm text-slate-300">SEO Description</span>
                        <textarea name="seo_description" required rows="3" class="w-full rounded-xl border border-white/20 bg-slate-900/60 px-4 py-3 focus:ring-2 focus:ring-cyan-300 outline-none"><?= htmlspecialchars($seoDescription, ENT_QUOTES, 'UTF-8') ?></textarea>
                    </label>
                    <label class="block space-y-2">
                        <span class="text-sm text-slate-300">SEO Keywords</span>
                        <input type="text" name="seo_keywords" value="<?= htmlspecialchars($seoKeywords, ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-xl border border-white/20 bg-slate-900/60 px-4 py-3 focus:ring-2 focus:ring-cyan-300 outline-none">
                    </label>
                    <label class="block space-y-2">
                        <span class="text-sm text-slate-300">OG Image URL</span>
                        <input type="url" name="og_image" value="<?= htmlspecialchars($ogImage, ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-xl border border-white/20 bg-slate-900/60 px-4 py-3 focus:ring-2 focus:ring-cyan-300 outline-none">
                    </label>
                    <button class="rounded-xl bg-fuchsia-300 text-slate-900 font-semibold px-5 py-3 hover:bg-fuchsia-200">Guardar SEO</button>
                </form>
            </section>
        </div>
    </div>
</body>
</html>
