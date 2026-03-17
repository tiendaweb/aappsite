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
<html lang="es" class="antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin OS | Liquid Glass</title>
    
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        glass: {
                            100: 'rgba(255, 255, 255, 0.03)',
                            200: 'rgba(255, 255, 255, 0.05)',
                            300: 'rgba(255, 255, 255, 0.08)',
                            border: 'rgba(255, 255, 255, 0.1)',
                            highlight: 'rgba(255, 255, 255, 0.15)',
                        }
                    },
                    animation: {
                        'blob': 'blob 10s infinite',
                        'blob-reverse': 'blob-reverse 12s infinite',
                    },
                    keyframes: {
                        blob: {
                            '0%, 100%': { transform: 'translate(0, 0) scale(1)' },
                            '33%': { transform: 'translate(30px, -50px) scale(1.1)' },
                            '66%': { transform: 'translate(-20px, 20px) scale(0.9)' },
                        },
                        'blob-reverse': {
                            '0%, 100%': { transform: 'translate(0, 0) scale(1)' },
                            '33%': { transform: 'translate(-30px, 50px) scale(1.1)' },
                            '66%': { transform: 'translate(20px, -20px) scale(0.9)' },
                        }
                    }
                }
            }
        }
    </script>

    <style>
        body { 
            background-color: #050505;
            color: #f1f5f9;
            overflow: hidden;
        }
        
        .glass-panel { 
            background: linear-gradient(135deg, var(--tw-colors-glass-200), var(--tw-colors-glass-100));
            backdrop-filter: blur(40px);
            -webkit-backdrop-filter: blur(40px);
            border: 1px solid var(--tw-colors-glass-border);
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.3), inset 0 1px 0 0 var(--tw-colors-glass-highlight);
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.02);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            box-shadow: 0 4px 24px -1px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
        }

        .glass-card:hover {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .glass-input {
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.08);
            color: #f8fafc;
            transition: all 0.2s;
        }
        .glass-input:focus {
            background: rgba(0, 0, 0, 0.4);
            border-color: rgba(34, 211, 238, 0.5);
            box-shadow: 0 0 0 2px rgba(34, 211, 238, 0.15), inset 0 1px 0 0 rgba(255,255,255,0.05);
            outline: none;
        }
        .glass-input::placeholder { color: rgba(255, 255, 255, 0.3); }

        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.2); }

        .bg-blobs {
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
            z-index: -1; overflow: hidden; pointer-events: none;
        }
        .blob { position: absolute; filter: blur(100px); opacity: 0.6; border-radius: 50%; }
        .blob-1 { top: -10%; left: -10%; width: 50vw; height: 50vw; background: radial-gradient(circle, rgba(14,165,233,0.4) 0%, rgba(0,0,0,0) 70%); }
        .blob-2 { bottom: -20%; right: -10%; width: 60vw; height: 60vw; background: radial-gradient(circle, rgba(139,92,246,0.3) 0%, rgba(0,0,0,0) 70%); }
        .blob-3 { top: 40%; left: 40%; width: 40vw; height: 40vw; background: radial-gradient(circle, rgba(16,185,129,0.2) 0%, rgba(0,0,0,0) 70%); }

        /* Tabs logic */
        .tab-content { display: none; }
        .tab-content.active { display: block; animation: fadeIn 0.4s ease-out; }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .nav-link.active {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            border-color: rgba(255, 255, 255, 0.05);
            box-shadow: inset 0 0 20px rgba(0,0,0,0.2);
        }
        .nav-link.active i { color: #22d3ee; }
    </style>
</head>
<body class="text-slate-200 text-sm md:text-base selection:bg-cyan-500/30">

    <div class="bg-blobs">
        <div class="blob blob-1 animate-blob"></div>
        <div class="blob blob-2 animate-blob-reverse"></div>
        <div class="blob blob-3 animate-blob" style="animation-delay: 2s;"></div>
    </div>

    <div class="h-screen w-screen p-0 sm:p-4 md:p-6 lg:p-8 flex items-center justify-center">
        <div class="glass-panel w-full h-full max-w-[1600px] sm:rounded-[2rem] flex flex-col md:flex-row overflow-hidden relative">
            
            <!-- Sidebar / Dock -->
            <aside class="w-full md:w-64 lg:w-72 border-b md:border-b-0 md:border-r border-white/10 flex flex-col bg-black/20 backdrop-blur-md flex-shrink-0">
                <div class="p-6 md:p-8 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-cyan-400 to-blue-600 flex items-center justify-center shadow-[0_0_20px_rgba(34,211,238,0.4)]">
                        <i class="ph ph-gear-six text-xl text-white"></i>
                    </div>
                    <div>
                        <h2 class="font-bold text-lg tracking-tight text-white leading-none">AAPP SPACE</h2>
                        <span class="text-[10px] text-cyan-300 uppercase tracking-widest font-semibold">Dashboard</span>
                    </div>
                </div>

                <nav class="flex-1 px-4 md:px-6 py-2 space-y-1 overflow-y-auto" id="main-nav">
                    <div class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-3 mt-4 px-2">General</div>
                    <button data-tab="tab-dashboard" class="nav-link w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-400 hover:text-white hover:bg-white/5 transition-all active">
                        <i class="ph ph-squares-four text-lg"></i> Dashboard
                    </button>
                    
                    <div class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-3 mt-8 px-2">Sistema</div>
                    <button data-tab="tab-seo" class="nav-link w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-400 hover:text-white hover:bg-white/5 transition-all">
                        <i class="ph ph-magnifying-glass text-lg"></i> SEO & Meta
                    </button>
                    <button data-tab="tab-integrations" class="nav-link w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-400 hover:text-white hover:bg-white/5 transition-all">
                        <i class="ph ph-plugs-connected text-lg"></i> Integraciones
                    </button>
                    <button data-tab="tab-templates" class="nav-link w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-400 hover:text-white hover:bg-white/5 transition-all">
                        <i class="ph ph-layout text-lg"></i> Plantillas
                    </button>
                    <button data-tab="tab-security" class="nav-link w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-400 hover:text-white hover:bg-white/5 transition-all">
                        <i class="ph ph-shield-check text-lg"></i> Seguridad
                    </button>
                </nav>

                <div class="p-4 md:p-6 border-t border-white/10">
                    <div class="flex items-center gap-3 px-3 py-2 mb-4">
                        <div class="w-8 h-8 rounded-full bg-slate-700 flex items-center justify-center border border-white/20">
                            <i class="ph ph-user text-slate-300"></i>
                        </div>
                        <div class="overflow-hidden">
                            <p class="text-xs text-slate-400 truncate">Sesión iniciada</p>
                            <p class="text-sm font-medium text-white truncate"><?= htmlspecialchars((string) ($user['name'] ?? $user['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                    </div>
                    <a href="<?= htmlspecialchars(url_for('/logout'), ENT_QUOTES, 'UTF-8') ?>" class="w-full flex items-center justify-center gap-2 py-2.5 rounded-xl text-rose-400 hover:text-rose-300 hover:bg-rose-500/10 border border-transparent hover:border-rose-500/20 transition-all text-sm font-medium">
                        <i class="ph ph-sign-out text-lg"></i> Cerrar sesión
                    </a>
                </div>
            </aside>

            <!-- Main Content Area -->
            <main class="flex-1 overflow-y-auto scroll-smooth relative h-full">
                
                <header class="sticky top-0 z-20 px-6 py-5 md:px-10 md:py-8 flex flex-col md:flex-row md:items-center justify-between gap-4 bg-black/10 backdrop-blur-xl border-b border-white/5">
                    <div>
                        <h1 class="text-2xl md:text-3xl font-bold text-white tracking-tight" id="active-tab-title">Panel de Control</h1>
                        <p class="text-slate-400 text-sm mt-1 flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-emerald-400 shadow-[0_0_8px_rgba(52,211,153,0.8)]"></span>
                            Sistema operativo y en línea
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <button class="w-10 h-10 rounded-full glass-card flex items-center justify-center text-slate-300 hover:text-white">
                            <i class="ph ph-bell text-lg"></i>
                        </button>
                    </div>
                </header>

                <div class="p-6 md:p-10 space-y-8 max-w-7xl mx-auto">
                    
                    <?php if ($status !== ''): ?>
                        <div class="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 backdrop-blur-md p-4 flex items-start gap-3 shadow-[0_0_30px_rgba(16,185,129,0.1)]">
                            <i class="ph-fill ph-check-circle text-emerald-400 text-xl mt-0.5"></i>
                            <div>
                                <h4 class="text-emerald-300 font-semibold text-sm">Operación exitosa</h4>
                                <p class="text-emerald-200/80 text-sm mt-0.5"><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error !== ''): ?>
                        <div class="rounded-2xl border border-rose-500/30 bg-rose-500/10 backdrop-blur-md p-4 flex items-start gap-3 shadow-[0_0_30px_rgba(244,63,94,0.1)]">
                            <i class="ph-fill ph-warning-circle text-rose-400 text-xl mt-0.5"></i>
                            <div>
                                <h4 class="text-rose-300 font-semibold text-sm">Atención requerida</h4>
                                <p class="text-rose-200/80 text-sm mt-0.5"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- TABS CONTAINER -->
                    <div id="tabs-container">

                        <!-- TAB: DASHBOARD -->
                        <div id="tab-dashboard" class="tab-content active space-y-8">
                            <!-- Aviso de Edición -->
                            <div class="glass-card rounded-3xl p-6 md:p-8 flex flex-col md:flex-row items-center justify-between gap-6 border-cyan-500/20 bg-cyan-500/5">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 rounded-2xl bg-cyan-500/20 flex items-center justify-center text-cyan-400">
                                        <i class="ph ph-info text-2xl"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-semibold text-white">Edición de Contenido Web</h3>
                                        <p class="text-slate-400 text-sm">Los textos, imágenes y enlaces se editan directamente desde la página web principal para una experiencia visual en tiempo real.</p>
                                    </div>
                                </div>
                                <a href="/" class="shrink-0 flex items-center gap-2 px-6 py-3 rounded-xl bg-white/10 hover:bg-white/20 text-white font-semibold border border-white/10 transition-all">
                                    <i class="ph ph-browser text-lg"></i>
                                    Ir a la web principal
                                </a>
                            </div>

                            <div class="grid md:grid-cols-3 gap-6">
                                <div class="glass-card rounded-2xl p-6">
                                    <p class="text-xs font-semibold text-slate-500 uppercase">Estado del Servidor</p>
                                    <p class="text-2xl font-bold text-white mt-2">Óptimo</p>
                                </div>
                                <div class="glass-card rounded-2xl p-6">
                                    <p class="text-xs font-semibold text-slate-500 uppercase">Plantilla Actual</p>
                                    <p class="text-2xl font-bold text-white mt-2"><?= htmlspecialchars(ucfirst($activeTemplate)) ?></p>
                                </div>
                                <div class="glass-card rounded-2xl p-6">
                                    <p class="text-xs font-semibold text-slate-500 uppercase">Versión del Sistema</p>
                                    <p class="text-2xl font-bold text-white mt-2">v4.2.0</p>
                                </div>
                            </div>
                        </div>

                        <!-- TAB: SEO -->
                        <div id="tab-seo" class="tab-content">
                            <section id="settings-seo" class="glass-card rounded-3xl p-6 md:p-8 relative overflow-hidden">
                                <header class="flex items-center gap-3 mb-6">
                                    <div class="w-10 h-10 rounded-xl bg-fuchsia-500/20 border border-fuchsia-500/30 flex items-center justify-center text-fuchsia-400">
                                        <i class="ph ph-magnifying-glass text-xl"></i>
                                    </div>
                                    <h2 class="text-xl font-semibold text-white">Motor de Búsqueda (SEO)</h2>
                                </header>
                                <form method="post" class="space-y-4">
                                    <input type="hidden" name="action" value="update_seo">
                                    <label class="block space-y-1.5">
                                        <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Título de Página</span>
                                        <input type="text" name="seo_title" required value="<?= htmlspecialchars($seoTitle, ENT_QUOTES, 'UTF-8') ?>" class="w-full glass-input rounded-xl px-4 py-2.5">
                                    </label>
                                    <label class="block space-y-1.5">
                                        <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Descripción (Meta)</span>
                                        <textarea name="seo_description" required rows="2" class="w-full glass-input rounded-xl px-4 py-2.5 resize-none"><?= htmlspecialchars($seoDescription, ENT_QUOTES, 'UTF-8') ?></textarea>
                                    </label>
                                    <label class="block space-y-1.5">
                                        <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Palabras Clave</span>
                                        <input type="text" name="seo_keywords" value="<?= htmlspecialchars($seoKeywords, ENT_QUOTES, 'UTF-8') ?>" class="w-full glass-input rounded-xl px-4 py-2.5">
                                    </label>
                                    <label class="block space-y-1.5">
                                        <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Imagen Social (OG)</span>
                                        <div class="relative">
                                            <i class="ph ph-link absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
                                            <input type="url" name="og_image" placeholder="https://" value="<?= htmlspecialchars($ogImage, ENT_QUOTES, 'UTF-8') ?>" class="w-full glass-input rounded-xl pl-10 pr-4 py-2.5">
                                        </div>
                                    </label>
                                    <button class="w-full mt-2 rounded-xl bg-gradient-to-r from-fuchsia-500 to-purple-600 text-white font-semibold px-5 py-3 shadow-lg transition-all">
                                        Indexar Cambios
                                    </button>
                                </form>
                            </section>
                        </div>

                        <!-- TAB: INTEGRATIONS -->
                        <div id="tab-integrations" class="tab-content">
                            <section id="settings-integrations" class="glass-card rounded-3xl p-6 md:p-8 relative overflow-hidden">
                                <header class="flex items-center gap-3 mb-6">
                                    <div class="w-10 h-10 rounded-xl bg-lime-500/20 border border-lime-500/30 flex items-center justify-center text-lime-400">
                                        <i class="ph ph-plugs-connected text-xl"></i>
                                    </div>
                                    <h2 class="text-xl font-semibold text-white">Dominio & Telemetría</h2>
                                </header>
                                <form method="post" class="space-y-4">
                                    <input type="hidden" name="action" value="update_integrations">
                                    <label class="block space-y-1.5">
                                        <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Dominio Enlazado</span>
                                        <div class="relative">
                                            <i class="ph ph-globe absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
                                            <input type="text" name="site_domain" placeholder="app.midominio.com" value="<?= htmlspecialchars($siteDomain, ENT_QUOTES, 'UTF-8') ?>" class="w-full glass-input rounded-xl pl-10 pr-4 py-2.5 font-mono text-sm">
                                        </div>
                                    </label>
                                    <label class="block space-y-1.5 pt-2">
                                        <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Facebook Pixel ID</span>
                                        <div class="relative">
                                            <i class="ph ph-meta-logo absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
                                            <input type="text" name="facebook_pixel_id" placeholder="123456789" value="<?= htmlspecialchars($facebookPixelId, ENT_QUOTES, 'UTF-8') ?>" class="w-full glass-input rounded-xl pl-10 pr-4 py-2.5 font-mono text-sm">
                                        </div>
                                    </label>
                                    <label class="block space-y-1.5 pt-2">
                                        <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Google Analytics ID</span>
                                        <div class="relative">
                                            <i class="ph ph-chart-line-up absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
                                            <input type="text" name="google_analytics_id" placeholder="G-XXXXXX" value="<?= htmlspecialchars($googleAnalyticsId, ENT_QUOTES, 'UTF-8') ?>" class="w-full glass-input rounded-xl pl-10 pr-4 py-2.5 font-mono text-sm uppercase">
                                        </div>
                                    </label>
                                    <button class="w-full mt-4 rounded-xl bg-gradient-to-r from-lime-400 to-emerald-500 text-black font-semibold px-5 py-3 shadow-lg transition-all">
                                        Sincronizar APIs
                                    </button>
                                </form>
                            </section>
                        </div>

                        <!-- TAB: TEMPLATES -->
                        <div id="tab-templates" class="tab-content space-y-8">
                            <section id="settings-templates" class="glass-card rounded-3xl p-6 md:p-8 flex flex-col relative overflow-hidden">
                                <header class="flex items-center gap-3 mb-6">
                                    <div class="w-10 h-10 rounded-xl bg-amber-500/20 border border-amber-500/30 flex items-center justify-center text-amber-400">
                                        <i class="ph ph-paint-brush-broad text-xl"></i>
                                    </div>
                                    <h2 class="text-xl font-semibold text-white">Plantilla Activa</h2>
                                </header>
                                <form method="post" class="space-y-5">
                                    <input type="hidden" name="action" value="update_template">
                                    <select name="site_template" class="w-full glass-input rounded-xl px-4 py-3 appearance-none cursor-pointer" required>
                                        <?php foreach ($availableTemplates as $templateSlug): ?>
                                            <option class="bg-slate-900 text-white" value="<?= htmlspecialchars($templateSlug, ENT_QUOTES, 'UTF-8') ?>" <?= $templateSlug === $activeTemplate ? 'selected' : '' ?>>
                                                <?= htmlspecialchars(ucfirst($templateSlug)) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="w-full rounded-xl bg-gradient-to-r from-amber-400 to-orange-500 text-black font-semibold px-5 py-3 shadow-lg transition-all">
                                        Aplicar Plantilla
                                    </button>
                                </form>
                                <div class="mt-8 pt-6 border-t border-white/10">
                                    <p class="text-xs font-medium text-slate-400 uppercase tracking-wider mb-3">Gestión de Archivos</p>
                                    <div class="space-y-2">
                                        <?php foreach ($availableTemplates as $templateSlug): ?>
                                            <?php $canDelete = count($availableTemplates) > 1; ?>
                                            <form method="post" class="flex items-center justify-between gap-3 rounded-xl border border-white/5 bg-black/20 p-3">
                                                <input type="hidden" name="action" value="delete_template">
                                                <input type="hidden" name="delete_template_slug" value="<?= htmlspecialchars($templateSlug) ?>">
                                                <span class="text-sm font-medium text-slate-200"><?= htmlspecialchars($templateSlug) ?></span>
                                                <button class="w-8 h-8 flex items-center justify-center rounded-lg <?= $canDelete ? 'text-slate-400 hover:text-rose-400' : 'text-slate-700' ?>" <?= $canDelete ? '' : 'disabled' ?>>
                                                    <i class="ph ph-trash text-lg"></i>
                                                </button>
                                            </form>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </section>

                            <section class="glass-card rounded-3xl p-6 md:p-8 relative overflow-hidden">
                                <header class="flex items-center gap-3 mb-6">
                                    <div class="w-10 h-10 rounded-xl bg-blue-500/20 border border-blue-500/30 flex items-center justify-center text-blue-300">
                                        <i class="ph ph-images-square text-xl"></i>
                                    </div>
                                    <h2 class="text-xl font-semibold text-white">Galería de Imágenes</h2>
                                </header>
                                <p class="text-sm text-slate-400 mb-4">Subí archivos, previsualizá y copiá enlaces públicos para usar en enlaces o contenido.</p>
                                <div class="space-y-4">
                                    <div class="flex flex-col sm:flex-row gap-3 sm:items-center">
                                        <input id="gallery-upload-input" type="file" accept="image/png,image/jpeg,image/webp" class="w-full sm:flex-1 glass-input rounded-xl px-4 py-2.5 text-sm">
                                        <button id="gallery-upload-btn" type="button" class="rounded-xl bg-blue-400 text-black font-semibold px-5 py-2.5 transition-all">Subir imagen</button>
                                    </div>
                                    <p id="gallery-status" class="text-xs text-slate-400"></p>
                                    <div id="gallery-grid" class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-3"></div>
                                </div>
                            </section>

                            <?php /* Herramienta para dev: dejar desactivada por defecto
                            <section class="glass-card rounded-3xl p-6 md:p-8 relative overflow-hidden">
                                <header class="flex items-center gap-3 mb-6">
                                    <div class="w-10 h-10 rounded-xl bg-cyan-500/20 border border-cyan-500/30 flex items-center justify-center text-cyan-400">
                                        <i class="ph ph-code-block text-xl"></i>
                                    </div>
                                    <h2 class="text-xl font-semibold text-white">Consola de Importación HTML</h2>
                                </header>
                                <form id="import-template-form" class="space-y-5">
                                    <input type="text" name="import_slug" placeholder="Slug de plantilla" required class="w-full glass-input rounded-xl px-4 py-2.5 font-mono text-sm">
                                    <textarea name="import_html" rows="8" placeholder="<!doctype html>..." required class="w-full glass-input rounded-xl px-4 py-4 font-mono text-[13px] leading-relaxed resize-y"></textarea>
                                    <div class="flex items-center justify-between">
                                        <p id="import-template-status" class="text-xs text-slate-500"></p>
                                        <button id="import-template-submit" class="rounded-xl bg-cyan-400 text-black font-semibold px-6 py-3 transition-all">Ejecutar Importación</button>
                                    </div>
                                </form>
                            </section>
                            */ ?>
                        </div>

                        <!-- TAB: SECURITY -->
                        <div id="tab-security" class="tab-content">
                            <section id="settings-security" class="glass-card rounded-3xl p-6 md:p-8 relative overflow-hidden max-w-2xl">
                                <header class="flex items-center gap-3 mb-6">
                                    <div class="w-10 h-10 rounded-xl bg-rose-500/20 border border-rose-500/30 flex items-center justify-center text-rose-400">
                                        <i class="ph ph-shield-check text-xl"></i>
                                    </div>
                                    <h2 class="text-xl font-semibold text-white">Seguridad de Acceso</h2>
                                </header>
                                <form method="post" class="space-y-4">
                                    <input type="hidden" name="action" value="change_password">
                                    <label class="block space-y-1.5">
                                        <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Contraseña Actual</span>
                                        <input type="password" name="current_password" required class="w-full glass-input rounded-xl px-4 py-2.5">
                                    </label>
                                    <div class="grid grid-cols-2 gap-4">
                                        <label class="block space-y-1.5">
                                            <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Nueva Clave</span>
                                            <input type="password" name="new_password" required class="w-full glass-input rounded-xl px-4 py-2.5">
                                        </label>
                                        <label class="block space-y-1.5">
                                            <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Confirmar</span>
                                            <input type="password" name="confirm_password" required class="w-full glass-input rounded-xl px-4 py-2.5">
                                        </label>
                                    </div>
                                    <button class="w-full mt-2 rounded-xl bg-gradient-to-r from-rose-500 to-pink-600 text-white font-semibold px-5 py-3 shadow-lg transition-all">
                                        Actualizar Credenciales
                                    </button>
                                </form>
                            </section>
                        </div>

                    </div>
                    
                    <!-- Footer -->
                    <footer class="pt-8 pb-4 text-center text-xs text-slate-500 font-medium tracking-wide">
                        <p>AAPP SPACE &copy; <?= date('Y') ?>. Todos los derechos reservados.</p>
                    </footer>

                </div>
            </main>
        </div>
    </div>

    <script>
        // TABS LOGIC
        const navLinks = document.querySelectorAll('.nav-link');
        const tabContents = document.querySelectorAll('.tab-content');
        const activeTitle = document.getElementById('active-tab-title');

        navLinks.forEach(link => {
            link.addEventListener('click', () => {
                const targetTab = link.getAttribute('data-tab');
                
                // Active Class on Nav
                navLinks.forEach(l => l.classList.remove('active'));
                link.classList.add('active');

                // Toggle visibility
                tabContents.forEach(content => {
                    content.classList.remove('active');
                });
                document.getElementById(targetTab).classList.add('active');

                // Update Header Title
                activeTitle.innerText = link.innerText.trim();
            });
        });

        const galleryGrid = document.getElementById('gallery-grid');
        const galleryStatus = document.getElementById('gallery-status');
        const galleryUploadInput = document.getElementById('gallery-upload-input');
        const galleryUploadBtn = document.getElementById('gallery-upload-btn');

        function setGalleryStatus(message, isError = false) {
            if (!galleryStatus) return;
            galleryStatus.innerText = message;
            galleryStatus.classList.toggle('text-rose-300', isError);
            galleryStatus.classList.toggle('text-slate-400', !isError);
        }

        function renderGallery(images) {
            if (!galleryGrid) return;
            galleryGrid.innerHTML = '';

            if (!images.length) {
                setGalleryStatus('No hay imágenes en la galería todavía.');
                return;
            }

            images.forEach((item) => {
                const card = document.createElement('article');
                card.className = 'rounded-xl border border-white/10 bg-black/20 overflow-hidden';
                card.innerHTML = `
                    <img src="${item.url}" alt="${item.name || 'Imagen'}" class="w-full h-28 object-cover">
                    <div class="p-3 space-y-2">
                        <p class="text-xs text-slate-300 truncate" title="${item.name || ''}">${item.name || 'archivo'}</p>
                        <div class="flex gap-2">
                            <a href="${item.url}" target="_blank" rel="noopener" class="flex-1 text-center text-xs rounded-lg border border-white/20 px-2 py-1.5 hover:border-cyan-300">Ver</a>
                            <button type="button" data-copy-url="${item.url}" class="flex-1 text-xs rounded-lg bg-cyan-400 text-black font-semibold px-2 py-1.5">Copiar link</button>
                        </div>
                    </div>
                `;
                galleryGrid.appendChild(card);
            });

            galleryGrid.querySelectorAll('[data-copy-url]').forEach((btn) => {
                btn.addEventListener('click', async () => {
                    const url = btn.getAttribute('data-copy-url') || '';
                    if (!url) return;
                    try {
                        await navigator.clipboard.writeText(url);
                        setGalleryStatus('Enlace copiado al portapapeles.');
                    } catch (error) {
                        setGalleryStatus('No se pudo copiar automáticamente. Copiá manualmente: ' + url, true);
                    }
                });
            });
        }

        async function loadGallery() {
            if (!galleryGrid) return;
            setGalleryStatus('Cargando galería...');
            try {
                const response = await fetch('<?= htmlspecialchars(url_for('/api/list-images.php')) ?>');
                const result = await response.json();
                if (!response.ok || !result.ok) {
                    throw new Error(result.error || 'No se pudo cargar la galería');
                }
                renderGallery(Array.isArray(result.images) ? result.images : []);
            } catch (error) {
                setGalleryStatus('Error cargando galería: ' + error.message, true);
            }
        }

        if (galleryUploadBtn && galleryUploadInput) {
            galleryUploadBtn.addEventListener('click', async () => {
                const file = galleryUploadInput.files && galleryUploadInput.files[0];
                if (!file) {
                    setGalleryStatus('Seleccioná un archivo para subir.', true);
                    return;
                }

                galleryUploadBtn.disabled = true;
                setGalleryStatus('Subiendo imagen...');
                const formData = new FormData();
                formData.append('key', 'site.assets.gallery_upload');
                formData.append('image', file);

                try {
                    const response = await fetch('<?= htmlspecialchars(url_for('/api/upload-image.php')) ?>', {
                        method: 'POST',
                        body: formData,
                    });
                    const result = await response.json();
                    if (!response.ok || !result.ok) {
                        throw new Error(result.error || 'No se pudo subir');
                    }
                    galleryUploadInput.value = '';
                    setGalleryStatus('Imagen subida correctamente.');
                    await loadGallery();
                } catch (error) {
                    setGalleryStatus('Error subiendo imagen: ' + error.message, true);
                } finally {
                    galleryUploadBtn.disabled = false;
                }
            });
        }

        loadGallery();
    </script>
</body>
</html>
