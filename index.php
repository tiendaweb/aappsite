<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$isLoggedIn = current_user() !== null;

$contentPath = __DIR__ . '/data/content.json';
$rawContent = file_exists($contentPath) ? file_get_contents($contentPath) : '';
$decoded = json_decode($rawContent ?: '{}', true);
$content = is_array($decoded) ? $decoded : [];

function content_get(array $data, string $path, $default = null) {
    $segments = explode('.', $path);
    $current = $data;

    foreach ($segments as $segment) {
        if (!is_array($current) || !array_key_exists($segment, $current)) {
            return $default;
        }
        $current = $current[$segment];
    }

    return $current;
}

function esc($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function resolve_image_url($image): string {
    if (is_array($image)) {
        $value = $image['value'] ?? $image['url'] ?? '';
        return is_string($value) ? $value : '';
    }

    return is_string($image) ? $image : '';
}

function resolve_image_source_type($image): string {
    if (is_array($image) && isset($image['source_type']) && is_string($image['source_type'])) {
        return $image['source_type'];
    }

    return 'url';
}

$defaults = json_decode(file_get_contents(__DIR__ . '/data/content.seed.json') ?: '{}', true);
$defaults = is_array($defaults) ? $defaults : [];

$backgrounds = content_get($content, 'backgrounds', content_get($defaults, 'backgrounds', []));
$stats = content_get($content, 'stats', content_get($defaults, 'stats', []));
$obrasItems = content_get($content, 'tabs.obras.items', content_get($defaults, 'tabs.obras.items', []));

if (!is_array($backgrounds) || $backgrounds === []) {
    $backgrounds = content_get($defaults, 'backgrounds', []);
}
if (!is_array($stats) || $stats === []) {
    $stats = content_get($defaults, 'stats', []);
}
if (!is_array($obrasItems) || $obrasItems === []) {
    $obrasItems = content_get($defaults, 'tabs.obras.items', []);
}

$backgrounds = array_values(array_filter(array_map(static function ($background): array {
    $url = '';
    if (is_array($background)) {
        $url = resolve_image_url($background['image'] ?? $background['url'] ?? []);
    }

    if ($url === '') {
        return [];
    }

    return [
        'source_type' => resolve_image_source_type($background['image'] ?? $background['url'] ?? []),
        'value' => $url,
    ];
}, $backgrounds), static fn ($item) => $item !== []));

$obrasItems = array_map(static function ($item): array {
    if (!is_array($item)) {
        return [];
    }

    $item['image'] = [
        'source_type' => resolve_image_source_type($item['image'] ?? []),
        'value' => resolve_image_url($item['image'] ?? []),
    ];

    return $item;
}, $obrasItems);

$initialContent = array_replace_recursive($defaults, $content);
?>
<!DOCTYPE html>
<html lang="<?= esc(content_get($initialContent, 'site.lang', 'es')) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc(content_get($initialContent, 'site.title', 'Galería')) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        serif: ['"Playfair Display"', 'serif'],
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: { art: { gold: '#C5A059', neon: '#00F2FF', deep: '#0A0A0B' } },
                },
            },
        };
    </script>
    <style>
        :root { --blur: 20px; }
        body { margin: 0; overflow-x: hidden; background: #000; font-family: 'Inter', sans-serif; }
        .bg-layer { position: absolute; inset: 0; background-size: cover; background-position: center; opacity: 0; transition: opacity 2.5s ease-in-out; }
        .bg-layer.active { opacity: 0.6; }
        .overlay { position: fixed; inset: 0; background: radial-gradient(circle at center, transparent 0%, rgba(0,0,0,0.85) 100%); z-index: -1; }
        .glass { background: rgba(255,255,255,.03); backdrop-filter: blur(var(--blur)); border: 1px solid rgba(255,255,255,.08); }
        .tab-content { display: none; }
        .tab-content.active { display: block; animation: slideUp .8s ease forwards; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        .nav-btn.active { background: rgba(255,255,255,.1); color: #00F2FF; }
        .editable-wrapper { position: relative; }
        .edit-icon { display: none; position: absolute; top: -.5rem; right: -.5rem; border-radius: 9999px; background: #00F2FF; color: black; font-size: .75rem; width: 1.5rem; height: 1.5rem; align-items: center; justify-content: center; cursor: pointer; }
        body.edit-mode [data-edit-key] { outline: 1px dashed rgba(0,242,255,.45); outline-offset: 4px; border-radius: .2rem; }
        body.edit-mode [data-edit-key][data-edit-type="text"] { cursor: text; }
        body.edit-mode .edit-icon { display: inline-flex; }
        .field-message { display:none; font-size:.7rem; margin-top:.3rem; }
        .field-message.ok { color: #34d399; display:block; }
        .field-message.error { color: #f87171; display:block; }
    </style>
</head>
<body class="text-white selection:bg-art-neon selection:text-black" data-auth="<?= $isLoggedIn ? '1' : '0' ?>">
<div id="bg-container" class="fixed inset-0 -z-10">
    <?php foreach ($backgrounds as $index => $background): ?>
        <div class="bg-layer<?= $index === 0 ? ' active' : '' ?>" style="background-image: url('<?= esc($background['value'] ?? '') ?>')"></div>
    <?php endforeach; ?>
</div>
<div class="overlay"></div>

<?php if ($isLoggedIn): ?>
    <div class="fixed top-4 right-4 z-50 flex gap-2">
        <button id="toggleEditBtn" class="bg-white/80 text-black px-4 py-2 rounded-full text-xs font-bold">✏️ Editar</button>
        <button id="saveContentBtn" class="hidden bg-art-neon text-black px-4 py-2 rounded-full text-xs font-bold">Guardar cambios</button>
    </div>
<?php endif; ?>

<header class="p-8 md:p-12 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
    <div class="space-y-1 editable-wrapper">
        <h1 class="font-serif text-4xl md:text-6xl tracking-tighter" data-edit-key="site.name" data-edit-type="text"><?= esc(content_get($initialContent, 'site.name', '')) ?></h1>
        <span class="edit-icon" data-edit-target="site.name">✎</span>
        <p class="text-art-neon uppercase tracking-[0.3em] text-xs" data-edit-key="site.tagline" data-edit-type="text"><?= esc(content_get($initialContent, 'site.tagline', '')) ?></p>
        <span class="field-message" data-message-for="site.name"></span>
    </div>
    <div class="glass px-6 py-3 rounded-full flex items-center gap-4 editable-wrapper">
        <span class="w-2 h-2 rounded-full bg-green-400 animate-pulse"></span>
        <span class="text-[10px] uppercase tracking-widest opacity-70" data-edit-key="site.availability" data-edit-type="text"><?= esc(content_get($initialContent, 'site.availability', '')) ?></span>
        <span class="edit-icon" data-edit-target="site.availability">✎</span>
    </div>
</header>

<main class="max-w-7xl mx-auto px-6 pb-32">
    <div id="inicio" class="tab-content active">
        <div class="grid lg:grid-cols-2 gap-12 items-center min-h-[60vh]">
            <div class="space-y-8">
                <h2 class="font-serif text-5xl md:text-7xl leading-tight">
                    <span data-edit-key="hero.headline_prefix" data-edit-type="text"><?= esc(content_get($initialContent, 'hero.headline_prefix', '')) ?></span>
                    <span class="italic text-art-gold" data-edit-key="hero.headline_highlight" data-edit-type="text"><?= esc(content_get($initialContent, 'hero.headline_highlight', '')) ?></span>
                    <span data-edit-key="hero.headline_suffix" data-edit-type="text"><?= esc(content_get($initialContent, 'hero.headline_suffix', '')) ?></span>
                </h2>
                <p class="text-lg text-gray-300 leading-relaxed font-light" data-edit-key="hero.description" data-edit-type="text"><?= esc(content_get($initialContent, 'hero.description', '')) ?></p>
                <div class="flex gap-4">
                    <?php foreach ($stats as $i => $stat): ?>
                        <div class="glass p-6 rounded-2xl flex-1">
                            <h3 class="text-art-neon text-2xl font-serif" data-edit-key="stats[<?= $i ?>].value" data-edit-type="text"><?= esc($stat['value'] ?? '') ?></h3>
                            <p class="text-xs uppercase tracking-tighter opacity-50" data-edit-key="stats[<?= $i ?>].label" data-edit-type="text"><?= esc($stat['label'] ?? '') ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="relative group editable-wrapper">
                <img src="<?= esc(resolve_image_url(content_get($initialContent, 'hero.featured_image', []))) ?>" data-edit-key="hero.featured_image" data-edit-type="image" data-source-type="<?= esc(resolve_image_source_type(content_get($initialContent, 'hero.featured_image', []))) ?>" class="rounded-3xl border border-white/10 shadow-2xl" alt="<?= esc(content_get($initialContent, 'hero.featured_image.alt', '')) ?>">
                <span class="edit-icon" data-edit-target="hero.featured_image">✎</span>
            </div>
        </div>
    </div>

    <div id="obras" class="tab-content">
        <h2 class="font-serif text-4xl mb-12"><span data-edit-key="tabs.obras.title_prefix" data-edit-type="text"><?= esc(content_get($initialContent, 'tabs.obras.title_prefix', '')) ?></span> <span class="italic" data-edit-key="tabs.obras.title_highlight" data-edit-type="text"><?= esc(content_get($initialContent, 'tabs.obras.title_highlight', '')) ?></span></h2>
        <div class="columns-1 md:columns-2 lg:columns-3 gap-6 space-y-6">
            <?php foreach ($obrasItems as $i => $item): ?>
                <div class="glass p-4 rounded-3xl break-inside-avoid editable-wrapper">
                    <img src="<?= esc(resolve_image_url($item['image'] ?? [])) ?>" data-edit-key="tabs.obras.items[<?= $i ?>].image" data-edit-type="image" data-source-type="<?= esc(resolve_image_source_type($item['image'] ?? [])) ?>" class="rounded-2xl w-full mb-4" alt="<?= esc($item['alt'] ?? '') ?>">
                    <span class="edit-icon" data-edit-target="tabs.obras.items[<?= $i ?>].image">✎</span>
                    <h3 class="font-serif text-xl" data-edit-key="tabs.obras.items[<?= $i ?>].title" data-edit-type="text"><?= esc($item['title'] ?? '') ?></h3>
                    <p class="text-xs text-art-neon mb-2" data-edit-key="tabs.obras.items[<?= $i ?>].subtitle" data-edit-type="text"><?= esc($item['subtitle'] ?? '') ?></p>
                    <p class="text-sm opacity-60" data-edit-key="tabs.obras.items[<?= $i ?>].description" data-edit-type="text"><?= esc($item['description'] ?? '') ?></p>
                    <span class="field-message" data-message-for="tabs.obras.items[<?= $i ?>].title"></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div id="mercado" class="tab-content">
        <div class="glass p-12 rounded-[3rem] text-center space-y-6">
            <h2 class="font-serif text-5xl"><span data-edit-key="tabs.mercado.title_prefix" data-edit-type="text"><?= esc(content_get($initialContent, 'tabs.mercado.title_prefix', '')) ?></span> <span class="italic" data-edit-key="tabs.mercado.title_highlight" data-edit-type="text"><?= esc(content_get($initialContent, 'tabs.mercado.title_highlight', '')) ?></span></h2>
            <p class="max-w-2xl mx-auto opacity-70" data-edit-key="tabs.mercado.description" data-edit-type="text"><?= esc(content_get($initialContent, 'tabs.mercado.description', '')) ?></p>
        </div>
    </div>

    <div id="academia" class="tab-content">
        <div class="flex flex-col lg:flex-row gap-8 items-stretch">
            <div class="glass p-10 rounded-[3rem] flex-1 space-y-6 flex flex-col justify-center">
                <h2 class="font-serif text-5xl"><span data-edit-key="tabs.academia.title_prefix" data-edit-type="text"><?= esc(content_get($initialContent, 'tabs.academia.title_prefix', '')) ?></span> <span class="text-art-neon italic" data-edit-key="tabs.academia.title_highlight" data-edit-type="text"><?= esc(content_get($initialContent, 'tabs.academia.title_highlight', '')) ?></span></h2>
                <p class="opacity-70" data-edit-key="tabs.academia.description" data-edit-type="text"><?= esc(content_get($initialContent, 'tabs.academia.description', '')) ?></p>
                <button class="bg-art-neon text-black px-8 py-4 rounded-full font-bold self-start uppercase text-xs tracking-widest" data-edit-key="tabs.academia.button" data-edit-type="text"><?= esc(content_get($initialContent, 'tabs.academia.button', '')) ?></button>
            </div>
            <div class="flex-1 glass rounded-[3rem] overflow-hidden min-h-[400px] editable-wrapper">
                <img src="<?= esc(resolve_image_url(content_get($initialContent, 'tabs.academia.image', []))) ?>" data-edit-key="tabs.academia.image" data-edit-type="image" data-source-type="<?= esc(resolve_image_source_type(content_get($initialContent, 'tabs.academia.image', []))) ?>" class="w-full h-full object-cover opacity-50" alt="<?= esc(content_get($initialContent, 'tabs.academia.image.alt', '')) ?>">
                <span class="edit-icon" data-edit-target="tabs.academia.image">✎</span>
            </div>
        </div>
    </div>
</main>

<nav class="fixed bottom-8 left-1/2 -translate-x-1/2 z-50">
    <div class="glass px-4 py-3 rounded-full flex gap-2 border-white/20">
        <button onclick="showTab('inicio')" data-tab="inicio" class="nav-btn active px-6 py-2 rounded-full text-[10px] font-bold uppercase" data-edit-key="site.nav.inicio" data-edit-type="text"><?= esc(content_get($initialContent, 'site.nav.inicio', 'Bio')) ?></button>
        <button onclick="showTab('obras')" data-tab="obras" class="nav-btn px-6 py-2 rounded-full text-[10px] font-bold uppercase" data-edit-key="site.nav.obras" data-edit-type="text"><?= esc(content_get($initialContent, 'site.nav.obras', 'Obras')) ?></button>
        <button onclick="showTab('mercado')" data-tab="mercado" class="nav-btn px-6 py-2 rounded-full text-[10px] font-bold uppercase" data-edit-key="site.nav.mercado" data-edit-type="text"><?= esc(content_get($initialContent, 'site.nav.mercado', 'Mercado')) ?></button>
        <button onclick="showTab('academia')" data-tab="academia" class="nav-btn px-6 py-2 rounded-full text-[10px] font-bold uppercase" data-edit-key="site.nav.academia" data-edit-type="text"><?= esc(content_get($initialContent, 'site.nav.academia', 'Academia')) ?></button>
    </div>
</nav>

<div id="imageModal" class="hidden fixed inset-0 bg-black/70 z-[100] items-center justify-center px-4">
    <div class="glass rounded-2xl p-6 max-w-xl w-full space-y-4">
        <h3 class="font-serif text-2xl">Editar imagen</h3>
        <div class="flex gap-2 text-sm">
            <button type="button" data-mode="url" class="modal-mode bg-white/10 px-3 py-2 rounded">URL</button>
            <button type="button" data-mode="upload" class="modal-mode bg-white/10 px-3 py-2 rounded">Subir archivo</button>
        </div>
        <div id="urlPane" class="space-y-2">
            <label class="block text-xs">URL de imagen</label>
            <input id="imageUrlInput" type="url" class="w-full text-black px-3 py-2 rounded" placeholder="https://...">
        </div>
        <div id="uploadPane" class="hidden space-y-2">
            <label class="block text-xs">Archivo (jpg/png/webp, máx 5MB)</label>
            <input id="imageFileInput" type="file" accept="image/png,image/jpeg,image/webp" class="w-full text-xs">
        </div>
        <p id="modalFeedback" class="text-xs"></p>
        <div class="flex justify-end gap-2">
            <button id="cancelModal" class="px-4 py-2 rounded bg-white/10">Cancelar</button>
            <button id="saveModal" class="px-4 py-2 rounded bg-art-neon text-black font-bold">Guardar</button>
        </div>
    </div>
</div>

<script>
function showTab(tabId) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.nav-btn').forEach(el => el.classList.remove('active'));
    document.getElementById(tabId).classList.add('active');
    const nav = document.querySelector(`[data-tab="${tabId}"]`);
    if (nav) nav.classList.add('active');
}

const layers = document.querySelectorAll('.bg-layer');
let currentLayer = 0;
if (layers.length > 1) {
    setInterval(() => {
        layers[currentLayer].classList.remove('active');
        currentLayer = (currentLayer + 1) % layers.length;
        layers[currentLayer].classList.add('active');
    }, 20000);
}

const isAuthenticated = document.body.dataset.auth === '1';
const contentState = <?= json_encode($initialContent, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
let editMode = false;
let currentImageKey = '';
let imageMode = 'url';

function switchImageMode(mode) {
    imageMode = mode === 'upload' ? 'upload' : 'url';
    document.getElementById('urlPane').classList.toggle('hidden', imageMode !== 'url');
    document.getElementById('uploadPane').classList.toggle('hidden', imageMode !== 'upload');
    document.querySelectorAll('.modal-mode').forEach((btn) => {
        btn.classList.toggle('bg-art-neon', btn.dataset.mode === imageMode);
        btn.classList.toggle('text-black', btn.dataset.mode === imageMode);
    });
}

function pathSegments(key) {
    return key.replace(/\[(\d+)\]/g, '.$1').split('.');
}
function setByPath(obj, key, value) {
    const segs = pathSegments(key);
    let cur = obj;
    for (let i = 0; i < segs.length - 1; i++) {
        if (cur[segs[i]] === undefined) cur[segs[i]] = {};
        cur = cur[segs[i]];
    }
    cur[segs[segs.length - 1]] = value;
}

function fieldMessage(key, msg, ok) {
    const target = document.querySelector(`[data-message-for="${key}"]`);
    if (!target) return;
    target.textContent = msg;
    target.className = `field-message ${ok ? 'ok' : 'error'}`;
}

async function persistContent(changedKeys = []) {
    const response = await fetch('/api/save-content.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(contentState),
    });
    const result = await response.json();
    if (!response.ok || !result.ok) {
        changedKeys.forEach((k) => fieldMessage(k, result.error || 'Error al guardar', false));
        throw new Error(result.error || 'Error de guardado');
    }
    changedKeys.forEach((k) => fieldMessage(k, 'Guardado', true));
}

if (isAuthenticated) {
    const toggleBtn = document.getElementById('toggleEditBtn');
    const saveBtn = document.getElementById('saveContentBtn');
    const editableText = Array.from(document.querySelectorAll('[data-edit-type="text"]'));

    toggleBtn.addEventListener('click', () => {
        editMode = !editMode;
        document.body.classList.toggle('edit-mode', editMode);
        saveBtn.classList.toggle('hidden', !editMode);
        toggleBtn.textContent = editMode ? '✅ Modo edición activo' : '✏️ Editar';
        editableText.forEach((el) => { el.contentEditable = editMode ? 'true' : 'false'; });
    });

    saveBtn.addEventListener('click', async () => {
        const changed = [];
        let hasError = false;
        editableText.forEach((el) => {
            const key = el.dataset.editKey;
            const value = (el.textContent || '').trim();
            if (!value) {
                fieldMessage(key, 'Este campo no puede quedar vacío.', false);
                hasError = true;
                return;
            }
            setByPath(contentState, key, value);
            changed.push(key);
        });
        if (hasError) return;
        try { await persistContent(changed); } catch (_) {}
    });

    document.querySelectorAll('.edit-icon').forEach((btn) => {
        btn.addEventListener('click', () => {
            const key = btn.dataset.editTarget;
            const imageEl = document.querySelector(`[data-edit-key="${key}"][data-edit-type="image"]`);
            if (!imageEl) {
                const textEl = document.querySelector(`[data-edit-key="${key}"][data-edit-type="text"]`);
                if (textEl && editMode) textEl.focus();
                return;
            }
            currentImageKey = key;
            document.getElementById('imageModal').classList.remove('hidden');
            document.getElementById('imageModal').classList.add('flex');
            document.getElementById('imageUrlInput').value = imageEl.getAttribute('src') || '';
            document.getElementById('imageFileInput').value = '';
            switchImageMode(imageEl.dataset.sourceType || 'url');
            document.getElementById('modalFeedback').textContent = '';
        });
    });

    document.querySelectorAll('.modal-mode').forEach((button) => {
        button.addEventListener('click', () => switchImageMode(button.dataset.mode));
    });

    document.getElementById('cancelModal').addEventListener('click', () => {
        document.getElementById('imageModal').classList.add('hidden');
        document.getElementById('imageModal').classList.remove('flex');
    });

    document.getElementById('saveModal').addEventListener('click', async () => {
        const feedback = document.getElementById('modalFeedback');
        const imageEl = document.querySelector(`[data-edit-key="${currentImageKey}"][data-edit-type="image"]`);
        if (!imageEl) return;

        if (imageMode === 'url') {
            const newUrl = document.getElementById('imageUrlInput').value.trim();
            if (!/^https?:\/\//i.test(newUrl)) {
                feedback.textContent = 'Ingresa una URL válida (http/https).';
                feedback.className = 'text-xs text-red-400';
                return;
            }
            imageEl.src = newUrl;
            setByPath(contentState, currentImageKey, { source_type: 'url', value: newUrl });
            imageEl.dataset.sourceType = 'url';
            try {
                await persistContent([currentImageKey]);
                feedback.textContent = 'Imagen actualizada.';
                feedback.className = 'text-xs text-green-400';
            } catch (e) {
                feedback.textContent = e.message;
                feedback.className = 'text-xs text-red-400';
            }
            return;
        }

        const file = document.getElementById('imageFileInput').files[0];
        if (!file) {
            feedback.textContent = 'Selecciona un archivo.';
            feedback.className = 'text-xs text-red-400';
            return;
        }
        if (file.size > 5 * 1024 * 1024) {
            feedback.textContent = 'El archivo supera 5MB.';
            feedback.className = 'text-xs text-red-400';
            return;
        }

        imageEl.src = URL.createObjectURL(file);
        const form = new FormData();
        form.append('key', currentImageKey);
        form.append('image', file);

        const response = await fetch('/api/upload-image.php', { method: 'POST', body: form });
        const result = await response.json();
        if (!response.ok || !result.ok) {
            feedback.textContent = result.error || 'No se pudo subir la imagen.';
            feedback.className = 'text-xs text-red-400';
            return;
        }

        imageEl.src = result.url;
        setByPath(contentState, currentImageKey, { source_type: 'upload', value: result.url });
        imageEl.dataset.sourceType = 'upload';
        fieldMessage(currentImageKey, 'Imagen guardada', true);
        feedback.textContent = 'Imagen subida correctamente.';
        feedback.className = 'text-xs text-green-400';
    });
}
</script>
</body>
</html>
