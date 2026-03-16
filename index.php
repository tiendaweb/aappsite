<?php
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

$defaults = [
    'site' => [
        'lang' => 'es',
        'title' => 'Claudia Nuñez Fasce | Galería Inmersiva',
        'name' => 'Claudia Nuñez Fasce',
        'tagline' => 'Fine Art & Digital Visions',
        'availability' => 'Disponible para comisiones',
        'nav' => [
            'inicio' => 'Bio',
            'obras' => 'Galería',
            'mercado' => 'Market',
            'academia' => 'Clases',
        ],
    ],
    'hero' => [
        'headline_prefix' => 'Más de',
        'headline_highlight' => '30 años',
        'headline_suffix' => 'capturando la esencia.',
        'description' => 'Formada bajo la tutela de maestros como Alberto Bruzzone y Manlio Cecotti, Claudia ha transitado un camino de más de 200 exposiciones. Su obra, que oscila entre lo sacro y lo cotidiano, es un diálogo constante entre el óleo tradicional y la nueva era digital.',
        'description_emphasis' => 'Alberto Bruzzone y Manlio Cecotti',
        'featured_image' => [
            'url' => 'https://images.unsplash.com/photo-1541963463532-d68292c34b19?q=80&w=800',
            'alt' => 'Artist Work',
        ],
        'quote' => 'El arte es la interfaz entre el alma y el espectador',
    ],
    'stats' => [
        ['value' => '200+', 'label' => 'Exposiciones'],
        ['value' => '1987', 'label' => 'Taller Fundado'],
    ],
    'tabs' => [
        'inicio' => ['id' => 'inicio'],
        'obras' => [
            'id' => 'obras',
            'title_prefix' => 'Colección',
            'title_highlight' => 'Selecta',
            'items' => [],
        ],
        'mercado' => [
            'id' => 'mercado',
            'title_prefix' => 'Galería',
            'title_highlight' => 'Colectiva',
            'description' => 'Un espacio curado por Claudia para artistas que buscan la excelencia. Vende tus obras en una plataforma de alto nivel.',
            'cta_symbol' => '+',
            'cta_label' => 'Postular Obra',
            'featured_artist' => [
                'name' => 'Marina V.',
                'style' => 'ABSTRACTO',
                'image' => 'https://images.unsplash.com/photo-1605721911519-3dfeb3be25e7?q=80&w=400',
                'alt' => 'Obra abstracta de Marina V.',
            ],
        ],
        'academia' => [
            'id' => 'academia',
            'title_prefix' => 'El Arte de la',
            'title_highlight' => 'Caricatura',
            'description' => 'Aprende los secretos del dibujo fisionómico de la mano de una experta. Clases online y presenciales para todos los niveles.',
            'button' => 'Inscribirme',
            'image' => [
                'url' => 'https://images.unsplash.com/photo-1513364776144-60967b0f800f?q=80&w=800',
                'alt' => 'Sesión de dibujo artístico',
            ],
        ],
    ],
    'backgrounds' => [
        ['url' => 'https://images.unsplash.com/photo-1549490349-8643362247b5?q=80&w=2000'],
        ['url' => 'https://images.unsplash.com/photo-1579783902614-a3fb3927b6a5?q=80&w=2000'],
        ['url' => 'https://images.unsplash.com/photo-1578301978693-85fa9c0320b9?q=80&w=2000'],
    ],
];

$backgrounds = content_get($content, 'backgrounds', $defaults['backgrounds']);
if (!is_array($backgrounds) || $backgrounds === []) {
    $backgrounds = $defaults['backgrounds'];
}

$stats = content_get($content, 'stats', $defaults['stats']);
if (!is_array($stats) || $stats === []) {
    $stats = $defaults['stats'];
}

$obrasItems = content_get($content, 'tabs.obras.items', []);
if (!is_array($obrasItems) || $obrasItems === []) {
    $obrasItems = content_get($defaults, 'tabs.obras.items', []);
    if ($obrasItems === []) {
        $obrasItems = content_get(json_decode(file_get_contents(__DIR__ . '/data/content.seed.json'), true) ?: [], 'tabs.obras.items', []);
    }
}

$heroDescription = (string) content_get($content, 'hero.description', content_get($defaults, 'hero.description', ''));
$heroEmphasis = (string) content_get($content, 'hero.description_emphasis', content_get($defaults, 'hero.description_emphasis', ''));
$heroDescriptionEscaped = esc($heroDescription);
if ($heroEmphasis !== '' && mb_stripos($heroDescription, $heroEmphasis) !== false) {
    $heroDescriptionEscaped = preg_replace(
        '/' . preg_quote(esc($heroEmphasis), '/') . '/u',
        '<span class="text-white font-semibold">' . esc($heroEmphasis) . '</span>',
        $heroDescriptionEscaped,
        1
    );
}
?>
<!DOCTYPE html>
<html lang="<?= esc(content_get($content, 'site.lang', content_get($defaults, 'site.lang', 'es'))) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc(content_get($content, 'site.title', content_get($defaults, 'site.title', ''))) ?></title>
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
                    colors: {
                        art: {
                            gold: '#C5A059',
                            neon: '#00F2FF',
                            deep: '#0A0A0B'
                        }
                    }
                }
            }
        }
    </script>

    <style>
        :root { --blur: 20px; }
        body { margin: 0; overflow-x: hidden; background: #000; font-family: 'Inter', sans-serif; }
        #bg-container { position: fixed; inset: 0; z-index: -2; transition: opacity 2s ease-in-out; }
        .bg-layer { position: absolute; inset: 0; background-size: cover; background-position: center; opacity: 0; transition: opacity 2.5s ease-in-out; }
        .bg-layer.active { opacity: 0.6; }
        .overlay { position: fixed; inset: 0; background: radial-gradient(circle at center, transparent 0%, rgba(0,0,0,0.85) 100%); z-index: -1; }
        .glass { background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(var(--blur)); -webkit-backdrop-filter: blur(var(--blur)); border: 1px solid rgba(255, 255, 255, 0.08); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); }
        .glass-hover:hover { background: rgba(255, 255, 255, 0.07); border: 1px solid rgba(0, 242, 255, 0.3); transform: translateY(-5px); transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        .tab-content { display: none; animation: slideUp 0.8s ease forwards; }
        .tab-content.active { display: block; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(30px); filter: blur(10px); } to { opacity: 1; transform: translateY(0); filter: blur(0); } }
        .nav-btn.active { background: rgba(255, 255, 255, 0.1); color: #00F2FF; box-shadow: 0 0 20px rgba(0, 242, 255, 0.2); border-color: rgba(0, 242, 255, 0.5); }
        .glow-text { text-shadow: 0 0 15px rgba(0, 242, 255, 0.4); }
    </style>
</head>
<body class="text-white selection:bg-art-neon selection:text-black">

    <div id="bg-container">
        <?php foreach ($backgrounds as $index => $background): ?>
            <div class="bg-layer<?= $index === 0 ? ' active' : '' ?>" style="background-image: url('<?= esc($background['url'] ?? $defaults['backgrounds'][$index]['url'] ?? '') ?>')"></div>
        <?php endforeach; ?>
    </div>
    <div class="overlay"></div>

    <header class="p-8 md:p-12 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div class="space-y-1">
            <h1 class="font-serif text-4xl md:text-6xl tracking-tighter glow-text"><?= esc(content_get($content, 'site.name', content_get($defaults, 'site.name', ''))) ?></h1>
            <p class="text-art-neon font-sans uppercase tracking-[0.3em] text-xs font-semibold"><?= esc(content_get($content, 'site.tagline', content_get($defaults, 'site.tagline', ''))) ?></p>
        </div>
        <div class="glass px-6 py-3 rounded-full flex items-center gap-4">
            <span class="w-2 h-2 rounded-full bg-green-400 animate-pulse"></span>
            <span class="text-[10px] uppercase tracking-widest opacity-70"><?= esc(content_get($content, 'site.availability', content_get($defaults, 'site.availability', ''))) ?></span>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-6 pb-32">
        <div id="inicio" class="tab-content active">
            <div class="grid lg:grid-cols-2 gap-12 items-center min-h-[60vh]">
                <div class="space-y-8">
                    <h2 class="font-serif text-5xl md:text-7xl leading-tight"><?= esc(content_get($content, 'hero.headline_prefix', content_get($defaults, 'hero.headline_prefix', ''))) ?> <span class="italic text-art-gold"><?= esc(content_get($content, 'hero.headline_highlight', content_get($defaults, 'hero.headline_highlight', ''))) ?></span> <?= esc(content_get($content, 'hero.headline_suffix', content_get($defaults, 'hero.headline_suffix', ''))) ?></h2>
                    <p class="text-lg text-gray-300 leading-relaxed font-light"><?= $heroDescriptionEscaped ?></p>
                    <div class="flex gap-4">
                        <?php foreach ($stats as $stat): ?>
                            <div class="glass p-6 rounded-2xl flex-1">
                                <h3 class="text-art-neon text-2xl font-serif"><?= esc($stat['value'] ?? '') ?></h3>
                                <p class="text-xs uppercase tracking-tighter opacity-50"><?= esc($stat['label'] ?? '') ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="relative group">
                    <div class="absolute -inset-4 bg-art-neon/20 blur-3xl opacity-0 group-hover:opacity-100 transition duration-700"></div>
                    <img src="<?= esc(content_get($content, 'hero.featured_image.url', content_get($defaults, 'hero.featured_image.url', ''))) ?>" class="rounded-3xl border border-white/10 shadow-2xl transition-transform duration-700 group-hover:scale-[1.02]" alt="<?= esc(content_get($content, 'hero.featured_image.alt', content_get($defaults, 'hero.featured_image.alt', ''))) ?>">
                    <div class="absolute bottom-6 left-6 glass p-4 rounded-xl">
                        <p class="text-xs italic">"<?= esc(content_get($content, 'hero.quote', content_get($defaults, 'hero.quote', ''))) ?>"</p>
                    </div>
                </div>
            </div>
        </div>

        <div id="obras" class="tab-content">
            <h2 class="font-serif text-4xl mb-12"><?= esc(content_get($content, 'tabs.obras.title_prefix', content_get($defaults, 'tabs.obras.title_prefix', ''))) ?> <span class="italic"><?= esc(content_get($content, 'tabs.obras.title_highlight', content_get($defaults, 'tabs.obras.title_highlight', ''))) ?></span></h2>
            <div class="columns-1 md:columns-2 lg:columns-3 gap-6 space-y-6">
                <?php foreach ($obrasItems as $item): ?>
                    <div class="glass p-4 rounded-3xl glass-hover break-inside-avoid">
                        <img src="<?= esc($item['image'] ?? '') ?>" class="rounded-2xl w-full mb-4" alt="<?= esc($item['alt'] ?? '') ?>">
                        <h3 class="font-serif text-xl"><?= esc($item['title'] ?? '') ?></h3>
                        <p class="text-xs text-art-neon mb-2"><?= esc($item['subtitle'] ?? '') ?></p>
                        <p class="text-sm opacity-60"><?= esc($item['description'] ?? '') ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div id="mercado" class="tab-content">
            <div class="glass p-12 rounded-[3rem] text-center space-y-6">
                <h2 class="font-serif text-5xl"><?= esc(content_get($content, 'tabs.mercado.title_prefix', content_get($defaults, 'tabs.mercado.title_prefix', ''))) ?> <span class="italic"><?= esc(content_get($content, 'tabs.mercado.title_highlight', content_get($defaults, 'tabs.mercado.title_highlight', ''))) ?></span></h2>
                <p class="max-w-2xl mx-auto opacity-70"><?= esc(content_get($content, 'tabs.mercado.description', content_get($defaults, 'tabs.mercado.description', ''))) ?></p>
                <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-4 pt-8">
                    <div class="p-8 border border-white/5 rounded-3xl bg-white/5 flex flex-col items-center justify-center hover:bg-art-neon/10 transition">
                        <span class="text-4xl mb-4"><?= esc(content_get($content, 'tabs.mercado.cta_symbol', content_get($defaults, 'tabs.mercado.cta_symbol', '+'))) ?></span>
                        <p class="text-xs font-bold tracking-widest uppercase"><?= esc(content_get($content, 'tabs.mercado.cta_label', content_get($defaults, 'tabs.mercado.cta_label', ''))) ?></p>
                    </div>
                    <div class="glass p-4 rounded-2xl">
                        <div class="aspect-square bg-gray-800 rounded-xl mb-4 overflow-hidden">
                            <img src="<?= esc(content_get($content, 'tabs.mercado.featured_artist.image', content_get($defaults, 'tabs.mercado.featured_artist.image', ''))) ?>" class="w-full h-full object-cover" alt="<?= esc(content_get($content, 'tabs.mercado.featured_artist.alt', content_get($defaults, 'tabs.mercado.featured_artist.alt', ''))) ?>">
                        </div>
                        <p class="text-sm font-bold"><?= esc(content_get($content, 'tabs.mercado.featured_artist.name', content_get($defaults, 'tabs.mercado.featured_artist.name', ''))) ?></p>
                        <p class="text-[10px] text-art-neon"><?= esc(content_get($content, 'tabs.mercado.featured_artist.style', content_get($defaults, 'tabs.mercado.featured_artist.style', ''))) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div id="academia" class="tab-content">
            <div class="flex flex-col lg:flex-row gap-8 items-stretch">
                <div class="glass p-10 rounded-[3rem] flex-1 space-y-6 flex flex-col justify-center">
                    <h2 class="font-serif text-5xl"><?= esc(content_get($content, 'tabs.academia.title_prefix', content_get($defaults, 'tabs.academia.title_prefix', ''))) ?> <span class="text-art-neon italic"><?= esc(content_get($content, 'tabs.academia.title_highlight', content_get($defaults, 'tabs.academia.title_highlight', ''))) ?></span></h2>
                    <p class="opacity-70"><?= esc(content_get($content, 'tabs.academia.description', content_get($defaults, 'tabs.academia.description', ''))) ?></p>
                    <button class="bg-art-neon text-black px-8 py-4 rounded-full font-bold hover:shadow-[0_0_30px_rgba(0,242,255,0.4)] transition self-start uppercase text-xs tracking-widest"><?= esc(content_get($content, 'tabs.academia.button', content_get($defaults, 'tabs.academia.button', ''))) ?></button>
                </div>
                <div class="flex-1 glass rounded-[3rem] overflow-hidden min-h-[400px]">
                    <img src="<?= esc(content_get($content, 'tabs.academia.image.url', content_get($defaults, 'tabs.academia.image.url', ''))) ?>" class="w-full h-full object-cover opacity-50" alt="<?= esc(content_get($content, 'tabs.academia.image.alt', content_get($defaults, 'tabs.academia.image.alt', ''))) ?>">
                </div>
            </div>
        </div>

    </main>

    <nav class="fixed bottom-8 left-1/2 -translate-x-1/2 z-50">
        <div class="glass px-4 py-3 rounded-full flex gap-2 border-white/20">
            <button onclick="showTab('inicio')" data-tab="inicio" class="nav-btn active px-6 py-2 rounded-full text-[10px] font-bold tracking-[0.2em] uppercase transition"><?= esc(content_get($content, 'site.nav.inicio', content_get($defaults, 'site.nav.inicio', ''))) ?></button>
            <button onclick="showTab('obras')" data-tab="obras" class="nav-btn px-6 py-2 rounded-full text-[10px] font-bold tracking-[0.2em] uppercase transition"><?= esc(content_get($content, 'site.nav.obras', content_get($defaults, 'site.nav.obras', ''))) ?></button>
            <button onclick="showTab('mercado')" data-tab="mercado" class="nav-btn px-6 py-2 rounded-full text-[10px] font-bold tracking-[0.2em] uppercase transition"><?= esc(content_get($content, 'site.nav.mercado', content_get($defaults, 'site.nav.mercado', ''))) ?></button>
            <button onclick="showTab('academia')" data-tab="academia" class="nav-btn px-6 py-2 rounded-full text-[10px] font-bold tracking-[0.2em] uppercase transition"><?= esc(content_get($content, 'site.nav.academia', content_get($defaults, 'site.nav.academia', ''))) ?></button>
        </div>
    </nav>

    <script>
        function showTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.nav-btn').forEach(el => el.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            document.querySelector(`[data-tab="${tabId}"]`).classList.add('active');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        const layers = document.querySelectorAll('.bg-layer');
        let currentLayer = 0;

        function rotateBackgrounds() {
            layers[currentLayer].classList.remove('active');
            currentLayer = (currentLayer + 1) % layers.length;
            layers[currentLayer].classList.add('active');
        }

        setInterval(rotateBackgrounds, 20000);
    </script>
</body>
</html>
