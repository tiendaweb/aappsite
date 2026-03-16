<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/url.php';
require_once __DIR__ . '/includes/content-repo.php';
require_once __DIR__ . '/includes/template-manager.php';

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


    if ($action === 'update_template') {
        $selectedTemplate = trim((string) ($_POST['site_template'] ?? ''));
        $availableTemplates = list_available_templates();

        if ($selectedTemplate === '' || !in_array($selectedTemplate, $availableTemplates, true)) {
            $error = 'La plantilla seleccionada no es válida.';
        } else {
            $content = read_content_file();
            if ($content === []) {
                $content = json_decode(file_get_contents(__DIR__ . '/data/content.seed.json') ?: '{}', true);
                $content = is_array($content) ? $content : [];
            }

            $content['site']['template'] = $selectedTemplate;

            if (!save_content_file($content)) {
                $error = 'No se pudo guardar la plantilla activa.';
            } else {
                $status = 'Plantilla activa actualizada.';
            }
        }
    }

    if ($action === 'delete_template') {
        $templateToDelete = trim((string) ($_POST['delete_template_slug'] ?? ''));
        $availableTemplates = list_available_templates();

        if ($templateToDelete === '' || !in_array($templateToDelete, $availableTemplates, true)) {
            $error = 'La plantilla a borrar no es válida.';
        } elseif (count($availableTemplates) <= 1) {
            $error = 'No se puede borrar la única plantilla disponible.';
        } else {
            $content = read_content_file();
            if ($content === []) {
                $content = json_decode(file_get_contents(__DIR__ . '/data/content.seed.json') ?: '{}', true);
                $content = is_array($content) ? $content : [];
            }

            if (!delete_template_directory($templateToDelete)) {
                $error = 'No se pudo borrar el directorio de la plantilla.';
            } elseif (!unregister_template_slug($templateToDelete)) {
                $error = 'No se pudo actualizar el índice de plantillas.';
            } else {
                $remainingTemplates = list_available_templates();
                if ($remainingTemplates === []) {
                    $error = 'No quedó ninguna plantilla disponible tras borrar.';
                } else {
                    $activeTemplate = admin_content_get($content, 'site.template', 'artistas');
                    if ($activeTemplate === $templateToDelete || !in_array($activeTemplate, $remainingTemplates, true)) {
                        $content['site']['template'] = $remainingTemplates[0];
                    }

                    if (!save_content_file($content)) {
                        $error = 'La plantilla se borró, pero no se pudo actualizar la plantilla activa.';
                    } else {
                        $status = 'Plantilla borrada correctamente.';
                    }
                }
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

    if ($action === 'update_integrations') {
        $domain = strtolower(trim((string) ($_POST['site_domain'] ?? '')));
        $facebookPixelId = trim((string) ($_POST['facebook_pixel_id'] ?? ''));
        $googleAnalyticsId = strtoupper(trim((string) ($_POST['google_analytics_id'] ?? '')));

        if ($domain !== '') {
            $domain = preg_replace('#^https?://#i', '', $domain) ?? $domain;
            $domain = trim($domain, "/ \t\n\r\0\x0B");
            if (preg_match('/^[a-z0-9.-]+$/', $domain) !== 1) {
                $error = 'El dominio tiene un formato inválido.';
            }
        }

        if ($error === '' && $facebookPixelId !== '' && preg_match('/^[0-9]{8,20}$/', $facebookPixelId) !== 1) {
            $error = 'El Pixel de Facebook debe ser numérico.';
        }

        if ($error === '' && $googleAnalyticsId !== '' && preg_match('/^(G|UA)-[A-Z0-9\-]+$/', $googleAnalyticsId) !== 1) {
            $error = 'El ID de Google Analytics debe ser tipo G-XXXX o UA-XXXX.';
        }

        if ($error === '') {
            $content = read_content_file();
            if ($content === []) {
                $content = json_decode(file_get_contents(__DIR__ . '/data/content.seed.json') ?: '{}', true);
                $content = is_array($content) ? $content : [];
            }

            $content['site']['domain'] = $domain;
            $content['site']['integrations'] = [
                'facebook_pixel_id' => $facebookPixelId,
                'google_analytics_id' => $googleAnalyticsId,
            ];

            if (!save_content_file($content)) {
                $error = 'No se pudieron guardar las integraciones.';
            } else {
                $status = 'Dominio e integraciones guardados.';
            }
        }
    }
}

$content = read_content_file();
$availableTemplates = list_available_templates();
$activeTemplate = admin_content_get($content, 'site.template', 'artistas');
if (!in_array($activeTemplate, $availableTemplates, true)) {
    $activeTemplate = 'artistas';
}
$seoTitle = admin_content_get($content, 'site.title', '');
$seoDescription = admin_content_get($content, 'site.seo.description', '');
$seoKeywords = admin_content_get($content, 'site.seo.keywords', '');
$ogImage = admin_content_get($content, 'site.seo.og_image', '');
$siteDomain = admin_content_get($content, 'site.domain', '');
$facebookPixelId = admin_content_get($content, 'site.integrations.facebook_pixel_id', '');
$googleAnalyticsId = admin_content_get($content, 'site.integrations.google_analytics_id', '');
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
                <h2 class="text-xl font-semibold mb-5">Plantilla activa</h2>
                <form method="post" class="space-y-4">
                    <input type="hidden" name="action" value="update_template">
                    <label class="block space-y-2">
                        <span class="text-sm text-slate-300">Seleccionar plantilla</span>
                        <select name="site_template" class="w-full rounded-xl border border-white/20 bg-slate-900/60 px-4 py-3 focus:ring-2 focus:ring-cyan-300 outline-none" required>
                            <?php foreach ($availableTemplates as $templateSlug): ?>
                                <option value="<?= htmlspecialchars($templateSlug, ENT_QUOTES, 'UTF-8') ?>" <?= $templateSlug === $activeTemplate ? 'selected' : '' ?>>
                                    <?= htmlspecialchars(ucfirst($templateSlug), ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button class="rounded-xl bg-amber-300 text-slate-900 font-semibold px-5 py-3 hover:bg-amber-200">Guardar plantilla</button>
                </form>

                <div class="mt-6 space-y-3">
                    <p class="text-sm text-slate-300">Borrar plantilla (si solo hay una, no se puede borrar)</p>
                    <?php foreach ($availableTemplates as $templateSlug): ?>
                        <?php $canDelete = count($availableTemplates) > 1; ?>
                        <form method="post" class="flex items-center justify-between gap-3 rounded-xl border border-white/15 bg-slate-900/40 p-3">
                            <input type="hidden" name="action" value="delete_template">
                            <input type="hidden" name="delete_template_slug" value="<?= htmlspecialchars($templateSlug, ENT_QUOTES, 'UTF-8') ?>">
                            <div class="text-sm">
                                <p class="font-semibold text-slate-100"><?= htmlspecialchars($templateSlug, ENT_QUOTES, 'UTF-8') ?></p>
                                <?php if ($templateSlug === $activeTemplate): ?>
                                    <p class="text-xs text-cyan-200">Activa</p>
                                <?php endif; ?>
                            </div>
                            <button class="rounded-lg px-3 py-2 font-semibold <?= $canDelete ? 'bg-red-300 text-slate-900 hover:bg-red-200' : 'bg-slate-700 text-slate-300 cursor-not-allowed' ?>" <?= $canDelete ? '' : 'disabled' ?>>
                                Borrar
                            </button>
                        </form>
                    <?php endforeach; ?>
                </div>
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

            <section class="glass rounded-3xl p-6 md:p-8">
                <h2 class="text-xl font-semibold mb-5">Dominio e integraciones</h2>
                <form method="post" class="space-y-4">
                    <input type="hidden" name="action" value="update_integrations">
                    <label class="block space-y-2">
                        <span class="text-sm text-slate-300">Dominio personalizado</span>
                        <input type="text" name="site_domain" placeholder="midominio.com" value="<?= htmlspecialchars($siteDomain, ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-xl border border-white/20 bg-slate-900/60 px-4 py-3 focus:ring-2 focus:ring-cyan-300 outline-none">
                        <span class="text-xs text-slate-400">Configura este dominio en DNS apuntando al hosting de esta app.</span>
                    </label>
                    <label class="block space-y-2">
                        <span class="text-sm text-slate-300">Facebook Pixel ID</span>
                        <input type="text" name="facebook_pixel_id" placeholder="123456789012345" value="<?= htmlspecialchars($facebookPixelId, ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-xl border border-white/20 bg-slate-900/60 px-4 py-3 focus:ring-2 focus:ring-cyan-300 outline-none">
                    </label>
                    <label class="block space-y-2">
                        <span class="text-sm text-slate-300">Google Analytics ID</span>
                        <input type="text" name="google_analytics_id" placeholder="G-XXXXXXXXXX" value="<?= htmlspecialchars($googleAnalyticsId, ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-xl border border-white/20 bg-slate-900/60 px-4 py-3 focus:ring-2 focus:ring-cyan-300 outline-none">
                    </label>
                    <button class="rounded-xl bg-lime-300 text-slate-900 font-semibold px-5 py-3 hover:bg-lime-200">Guardar integraciones</button>
                </form>
            </section>


            <section class="glass rounded-3xl p-6 md:p-8 lg:col-span-2">
                <h2 class="text-xl font-semibold mb-5">Importar HTML a Plantilla</h2>
                <form id="import-template-form" class="space-y-4">
                    <label class="block space-y-2">
                        <span class="text-sm text-slate-300">Slug de plantilla destino</span>
                        <input type="text" name="import_slug" placeholder="landing-nueva" required class="w-full rounded-xl border border-white/20 bg-slate-900/60 px-4 py-3 focus:ring-2 focus:ring-cyan-300 outline-none">
                    </label>
                    <label class="block space-y-2">
                        <span class="text-sm text-slate-300">HTML completo</span>
                        <textarea name="import_html" rows="12" placeholder="<!doctype html>..." required class="w-full rounded-xl border border-white/20 bg-slate-900/60 px-4 py-3 font-mono text-sm focus:ring-2 focus:ring-cyan-300 outline-none"></textarea>
                    </label>
                    <div class="flex items-center gap-3">
                        <button id="import-template-submit" class="rounded-xl bg-emerald-300 text-slate-900 font-semibold px-5 py-3 hover:bg-emerald-200">Importar plantilla</button>
                        <p id="import-template-status" class="text-sm text-slate-300"></p>
                    </div>
                </form>
            </section>
        </div>
    </div>
    <script>
        const importForm = document.getElementById('import-template-form');
        const importSubmit = document.getElementById('import-template-submit');
        const importStatus = document.getElementById('import-template-status');

        if (importForm && importSubmit && importStatus) {
            importForm.addEventListener('submit', async (event) => {
                event.preventDefault();
                importStatus.textContent = 'Importando...';
                importStatus.className = 'text-sm text-slate-300';
                importSubmit.setAttribute('disabled', 'disabled');

                const formData = new FormData(importForm);
                const payload = {
                    slug: String(formData.get('import_slug') ?? '').trim(),
                    html: String(formData.get('import_html') ?? ''),
                };

                try {
                    const response = await fetch('<?= htmlspecialchars(url_for('/api/import-template.php'), ENT_QUOTES, 'UTF-8') ?>', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify(payload),
                    });

                    const data = await response.json();
                    if (!response.ok || !data.ok) {
                        throw new Error(data.error || 'No se pudo importar la plantilla');
                    }

                    const report = data.result.report || {};
                    const variablesCreated = Number(report.variables_created || data.result.editable_nodes || 0);
                    const conflictCount = Array.isArray(report.name_conflicts) ? report.name_conflicts.length : 0;
                    const conflictSummary = conflictCount > 0 ? ` Conflictos de nombre: ${conflictCount}.` : '';
                    importStatus.textContent = `Plantilla ${data.result.slug} importada (${variablesCreated} variables detectadas).${conflictSummary}`;
                    importStatus.className = 'text-sm text-emerald-300';
                    setTimeout(() => window.location.reload(), 700);
                } catch (error) {
                    importStatus.textContent = error instanceof Error ? error.message : 'Error inesperado';
                    importStatus.className = 'text-sm text-red-300';
                } finally {
                    importSubmit.removeAttribute('disabled');
                }
            });
        }
    </script>
</body>
</html>
