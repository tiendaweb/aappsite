<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/url.php';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Claudia Fasce App | Landing</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen">
<main class="max-w-5xl mx-auto px-6 py-16">
    <span class="inline-flex items-center px-3 py-1 rounded-full border border-cyan-400/30 text-cyan-300 text-xs">Nuevo: onboarding multi-tenant</span>
    <h1 class="text-4xl md:text-6xl font-bold mt-6">Construí tu sitio y gestioná tu contenido en minutos</h1>
    <p class="text-slate-300 mt-6 text-lg max-w-3xl">Claudia Fasce App te permite crear un tenant propio, elegir un plan, publicar tu web y administrar SEO, contenidos e integraciones desde un panel visual.</p>

    <div class="grid md:grid-cols-3 gap-4 mt-10">
        <article class="rounded-2xl border border-white/10 bg-white/5 p-5"><h2 class="font-semibold">1. Elegí un plan</h2><p class="text-sm text-slate-300 mt-2">Plan gratis para empezar o plan pago con soporte prioritario.</p></article>
        <article class="rounded-2xl border border-white/10 bg-white/5 p-5"><h2 class="font-semibold">2. Registrá tu tenant</h2><p class="text-sm text-slate-300 mt-2">Definí el identificador de tu sitio y tu usuario administrador.</p></article>
        <article class="rounded-2xl border border-white/10 bg-white/5 p-5"><h2 class="font-semibold">3. Activá y publicá</h2><p class="text-sm text-slate-300 mt-2">Completá checkout (si aplica) y accedé directo al admin.</p></article>
    </div>

    <a href="<?= htmlspecialchars(url_for('/select-plan'), ENT_QUOTES, 'UTF-8') ?>" class="inline-block mt-10 bg-cyan-300 text-slate-950 font-semibold px-6 py-3 rounded-xl">Comenzar ahora</a>
</main>
</body>
</html>
