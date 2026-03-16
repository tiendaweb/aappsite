<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/content-repo.php';
require_once __DIR__ . '/../../includes/template-helpers.php';
require_once __DIR__ . '/../../includes/auth.php';

$initialContent = read_content_file();
$isAdminPreview = current_user() !== null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-edit-key="site.title" data-edit-type="text"><?= esc(content_get($initialContent, 'site.title', 'Ezequiel Usay | Suplementación Avanzada')) ?></title>
    <meta name="description" content="Preparador f&iacute;sico especializado en hipertrofia y suplementaci&oacute;n avanzada.">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="<?= esc(content_get($initialContent, 'sections.head_item_1.link_item_1.href', 'https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;700;900&family=Inter:wght@300;400;600&display=swap')) ?>" rel="stylesheet" data-edit-key="sections.head_item_1.link_item_1.href" data-edit-type="text" data-edit-key-href="sections.head_item_1.link_item_1.href" data-edit-type-href="text">
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            --black: #050505;
            --gray-dark: #121212;
            
            /* Colores de identidad por secci&oacute;n */
            --red-main: #ef4444;
            --red-dark: #991b1b;
            --yellow-main: #FFD700;
            --yellow-dark: #C5A000;
            --blue-main: #3b82f6;
            --blue-dark: #1e40af;
        }
        
        body {
            background-color: var(--black);
            color: white;
            font-family: 'Inter', sans-serif;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        h1, h2, h3, h4, .font-heading {
            font-family: 'Montserrat', sans-serif;
        }

        /* Fondo con imagen de gimnasio muy sutil */
        .bg-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                linear-gradient(to bottom, rgb(5 5 5 / 52%), rgb(5 5 5 / 16%)),
                url('https://aapp.space/storage/images/609c03880ee47-69b873b049ffe.png');
            background-size: cover;
            background-position: center;
            z-index: -1;
        }

        /* Glassmorphism Premium */
        .glass-panel {
            background: rgba(18, 18, 18, 0.4);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-radius: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.05);
            transition: transform 0.4s ease, box-shadow 0.4s ease, border-color 0.4s ease;
            position: relative;
            overflow: hidden;
        }
        .glass-panel:hover {
            transform: translateY(-5px);
        }

        /* Temas de Color por Secci&oacute;n */
        .panel-red:hover { border-color: rgba(239, 68, 68, 0.4); box-shadow: 0 10px 40px -10px rgba(239, 68, 68, 0.15); }
        .panel-yellow:hover { border-color: rgba(255, 215, 0, 0.4); box-shadow: 0 10px 40px -10px rgba(255, 215, 0, 0.15); }
        .panel-blue:hover { border-color: rgba(59, 130, 246, 0.4); box-shadow: 0 10px 40px -10px rgba(59, 130, 246, 0.15); }

        .text-red-accent { color: var(--red-main); }
        .text-yellow-accent { color: var(--yellow-main); }
        .text-blue-accent { color: var(--blue-main); }

        /* Botones personalizados */
        .btn-base {
            font-family: 'Montserrat', sans-serif;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            cursor: pointer;
            padding: 1.25rem 2.5rem;
            border-radius: 1rem;
            font-size: 0.9rem;
        }

        .btn-red {
            background: linear-gradient(135deg, var(--red-main) 0%, var(--red-dark) 100%);
            color: white;
            box-shadow: 0 4px 20px rgba(239, 68, 68, 0.25);
        }
        .btn-red:hover { transform: translateY(-3px); box-shadow: 0 8px 30px rgba(239, 68, 68, 0.4); }

        .btn-yellow {
            background: linear-gradient(135deg, var(--yellow-main) 0%, var(--yellow-dark) 100%);
            color: var(--black);
            box-shadow: 0 4px 20px rgba(255, 215, 0, 0.25);
        }
        .btn-yellow:hover { transform: translateY(-3px); box-shadow: 0 8px 30px rgba(255, 215, 0, 0.4); }

        .btn-blue {
            background: linear-gradient(135deg, var(--blue-main) 0%, var(--blue-dark) 100%);
            color: white;
            box-shadow: 0 4px 20px rgba(59, 130, 246, 0.25);
        }
        .btn-blue:hover { transform: translateY(-3px); box-shadow: 0 8px 30px rgba(59, 130, 246, 0.4); }

        .btn-outline {
            background: transparent;
            border: 1px solid rgba(255,255,255,0.2);
            color: white;
        }
        .btn-outline:hover {
            background: rgba(255,255,255,0.05);
            border-color: rgba(255,255,255,0.4);
        }

        /* SLIDER DE MARCAS */
        .slider {
            width: 100%;
            overflow: hidden;
            position: relative;
            padding: 30px 0;
            background: rgba(0,0,0,0.2);
            border-radius: 1rem;
            border: 1px solid rgba(255,215,0,0.05);
        }
        .slider::before, .slider::after {
            content: "";
            position: absolute;
            top: 0;
            width: 80px;
            height: 100%;
            z-index: 2;
        }
        .slider::before {
            left: 0;
            background: linear-gradient(to right, var(--gray-dark) 0%, transparent 100%);
        }
        .slider::after {
            right: 0;
            background: linear-gradient(to left, var(--gray-dark) 0%, transparent 100%);
        }
        .slide-track {
            display: flex;
            width: calc(180px * 14); 
            animation: scroll 30s linear infinite;
        }
        .slide-track:hover {
            animation-play-state: paused;
        }
        .slide {
            width: 180px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 20px;
        }
        .slide img {
            max-width: 100%;
            max-height: 80px;
            object-fit: contain;
            filter: grayscale(100%) brightness(1.2) opacity(0.6);
            transition: all 0.4s ease;
        }
        .slide img:hover {
            filter: grayscale(0%) brightness(1) opacity(1);
            transform: scale(1.05);
        }
        @keyframes scroll {
            0% { transform: translateX(0); }
            100% { transform: translateX(calc(-180px * 7)); } 
        }

        /* Animaci&oacute;n Fade In */
        .fade-in-section {
            opacity: 0;
            transform: translateY(40px);
            transition: opacity 0.8s ease-out, transform 0.8s ease-out;
            will-change: opacity, visibility;
        }
        .fade-in-section.is-visible {
            opacity: 1;
            transform: none;
        }
    </style>
</head>
<body class="antialiased selection:bg-yellow-500 selection:text-black">

    <div class="bg-overlay"></div>

    <!-- NAVEGACI&Oacute;N SIMPLE (Opcional, para dar look de Landing) -->
    <nav class="w-full absolute top-0 left-0 z-50 px-6 py-6 flex justify-between items-center max-w-7xl mx-auto right-0">
        <div class="text-white/50 text-xs tracking-widest uppercase font-bold" data-edit-key="sections.nav_absolute_1.div_tracking_widest_1.text" data-edit-type="text"><?= esc(content_get($initialContent, 'sections.nav_absolute_1.div_tracking_widest_1.text', 'Ezequiel Usay')) ?></div>
        <div class="flex gap-4 items-center">
            <?php if ($isAdminPreview): ?>
                <a href="<?= esc(url_for('/admin')) ?>" class="text-[11px] uppercase tracking-wider px-3 py-2 rounded-lg border border-white/20 text-white/80 hover:text-white hover:border-white/60 transition-colors">Volver al admin</a>
            <?php endif; ?>
            <a href="<?= esc(content_get($initialContent, 'sections.nav_absolute_1.div_flex_2.a_hover_text_white_1.href', 'https://www.instagram.com/suplementacionam')) ?>" target="_blank" class="text-white/60 hover:text-white transition-colors" data-edit-key="sections.nav_absolute_1.div_flex_2.a_hover_text_white_1.href" data-edit-type="text" data-edit-key-href="sections.nav_absolute_1.div_flex_2.a_hover_text_white_1.href" data-edit-type-href="text">
                <i data-lucide="instagram" class="w-5 h-5"></i>
            </a>
            <a href="<?= esc(content_get($initialContent, 'sections.nav_absolute_1.div_flex_2.a_hover_text_white_2.href', 'mailto:suplementacionavanzadamoderna@hotmail.com')) ?>" class="text-white/60 hover:text-white transition-colors" data-edit-key="sections.nav_absolute_1.div_flex_2.a_hover_text_white_2.href" data-edit-type="text" data-edit-key-href="sections.nav_absolute_1.div_flex_2.a_hover_text_white_2.href" data-edit-type-href="text">
                <i data-lucide="mail" class="w-5 h-5"></i>
            </a>
        </div>
    </nav>

    <!-- HERO SECTION -->
    <header class="min-h-[90vh] flex flex-col justify-center relative px-4 sm:px-6 lg:px-8 pt-24 pb-16 max-w-5xl mx-auto text-center fade-in-section">
        
        <div class="inline-block relative mb-8 mx-auto">
            <!-- Logo Circular -->
            <div class="w-32 h-32 sm:w-40 sm:h-40 rounded-full overflow-hidden border-2 border-white/10 shadow-2xl relative z-10 bg-black">
                <img src="<?= esc(resolve_image_url(content_get($initialContent, 'sections.header_min_h_90vh_1.div_inline_block_1.div_h_32_1.img_h_full_1.src', ['source_type' => 'url', 'value' => 'https://aapp.space/storage/images/609c03880ee47-69b4aa55c2d3e.jpg']))) ?>" alt="<?= esc(content_get($initialContent, 'sections.header_min_h_90vh_1.div_inline_block_1.div_h_32_1.img_h_full_1.alt', 'Logo Ezequiel')) ?>" class="w-full h-full object-cover" data-edit-key="sections.header_min_h_90vh_1.div_inline_block_1.div_h_32_1.img_h_full_1.src" data-edit-type="image" data-edit-key-alt="sections.header_min_h_90vh_1.div_inline_block_1.div_h_32_1.img_h_full_1.alt" data-edit-type-alt="text">
            </div>
            <!-- Glow detr&aacute;s del logo -->
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full h-full bg-yellow-500/20 blur-[50px] rounded-full z-0 pointer-events-none"></div>
        </div>
        
        <h1 class="text-4xl sm:text-5xl lg:text-7xl font-black mb-4 tracking-tighter uppercase text-transparent bg-clip-text bg-gradient-to-r from-white via-gray-200 to-gray-500" data-edit-key="sections.header_min_h_90vh_1.h1_sm_text_5xl_1.title" data-edit-type="text"><?= esc(content_get($initialContent, 'sections.header_min_h_90vh_1.h1_sm_text_5xl_1.title', 'Ezequiel Usay')) ?></h1>
        
        <h2 class="text-lg sm:text-xl md:text-2xl font-semibold text-yellow-accent mb-8 uppercase tracking-[0.15em]" data-edit-key="sections.header_min_h_90vh_1.h2_sm_text_xl_1.title" data-edit-type="text"><?= esc(content_get($initialContent, 'sections.header_min_h_90vh_1.h2_sm_text_xl_1.title', 'ASESOR EN SUPLEMENTACION AVANZADA Y PREPARADOR FISICO ESPECIALIZADO EN HIPER')) ?></h2>
        
        <p class="text-gray-300 text-base sm:text-lg lg:text-xl font-light leading-relaxed max-w-3xl mx-auto px-4" data-edit-key="sections.header_min_h_90vh_1.p_sm_text_lg_1.text" data-edit-type="text"><?= esc(content_get($initialContent, 'sections.header_min_h_90vh_1.p_sm_text_lg_1.text', 'Mi trabajo es simple: separar la ciencia del marketing para que sepas exactamente qué suplementos usar, cuándo usarlos y cuáles evitar, siempre priorizando tu')) ?></p>
        
        <div class="mt-12 flex flex-col sm:flex-row gap-4 justify-center items-center">
            <a href="<?= esc(content_get($initialContent, 'sections.header_min_h_90vh_1.div_mt_12_2.a_btn_base_1.href', '#asesoria')) ?>" class="btn-base btn-blue w-full sm:w-auto" data-edit-key="sections.header_min_h_90vh_1.div_mt_12_2.a_btn_base_1.link_label" data-edit-type="text" data-edit-key-href="sections.header_min_h_90vh_1.div_mt_12_2.a_btn_base_1.href" data-edit-type-href="text"><?= esc(content_get($initialContent, 'sections.header_min_h_90vh_1.div_mt_12_2.a_btn_base_1.link_label', 'Solicitar Asesoría')) ?></a>
           
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-16 sm:space-y-32">
        
        <!-- SECCI&Oacute;N 1: ROJO - EBOOK -->
        <section id="ebook" class="fade-in-section">
    <div class="glass-panel panel-red p-8 md:p-12 lg:p-16">
        <div class="absolute top-0 right-0 w-96 h-96 bg-red-600/10 blur-[100px] rounded-full pointer-events-none"></div>
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center relative z-10">
            <div class="order-2 lg:order-1 text-center lg:text-left">
                <div class="flex items-center justify-center lg:justify-start gap-3 mb-4">
                    <div class="p-3 bg-red-500/10 rounded-xl">
                        <i data-lucide="book-open" class="text-red-main w-6 h-6"></i>
                    </div>
                    <span class="text-red-accent font-bold tracking-[0.2em] uppercase text-xs" data-edit-key="sections.main_max_w_7xl_1.section_ebook.div_glass_panel_1.div_grid_2.div_order_2_1.div_flex_1.span_font_bold_1.text" data-edit-type="text"><?= esc(content_get($initialContent, 'sections.main_max_w_7xl_1.section_ebook.div_glass_panel_1.div_grid_2.div_order_2_1.div_flex_1.span_font_bold_1.text', 'Lanzamiento Oficial')) ?></span>
                </div>
                
                <h2 class="text-3xl sm:text-4xl md:text-5xl font-black mb-4 uppercase leading-tight" data-edit-key="sections.main_max_w_7xl_1.section_ebook.div_glass_panel_1.div_grid_2.div_order_2_1.h2_sm_text_4xl_1.title" data-edit-type="text"><?= esc(content_get($initialContent, 'sections.main_max_w_7xl_1.section_ebook.div_glass_panel_1.div_grid_2.div_order_2_1.h2_sm_text_4xl_1.title', 'Suplementación')) ?></h2>
                
                <p class="text-gray-300 text-base sm:text-lg mb-8 leading-relaxed mx-auto lg:mx-0 max-w-xl" data-edit-key="sections.main_max_w_7xl_1.section_ebook.div_glass_panel_1.div_grid_2.div_order_2_1.p_sm_text_lg_1.text" data-edit-type="text"><?= esc(content_get($initialContent, 'sections.main_max_w_7xl_1.section_ebook.div_glass_panel_1.div_grid_2.div_order_2_1.p_sm_text_lg_1.text', 'Guía práctica sobre suplementación aplicada al entrenamiento y al rendimiento físico, pensada para hombres y mujeres que quieren informarse mejor antes de elegir qué suplementos usar.')) ?></p>
    
                <a href="<?= esc(content_get($initialContent, 'sections.main_max_w_7xl_1.section_ebook.div_glass_panel_1.div_grid_2.div_order_2_1.a_btn_base_1.href', '#')) ?>" class="btn-base btn-red w-full sm:w-auto" data-edit-key="sections.main_max_w_7xl_1.section_ebook.div_glass_panel_1.div_grid_2.div_order_2_1.a_btn_base_1.link_label" data-edit-type="text" data-edit-key-href="sections.main_max_w_7xl_1.section_ebook.div_glass_panel_1.div_grid_2.div_order_2_1.a_btn_base_1.href" data-edit-type-href="text"><?= esc(content_get($initialContent, 'sections.main_max_w_7xl_1.section_ebook.div_glass_panel_1.div_grid_2.div_order_2_1.a_btn_base_1.link_label', 'Acceder al Ebook')) ?></a>
            </div>
            
            <div class="order-1 lg:order-2 flex justify-center items-center">
                <div class="relative group perspective-1000 w-full max-w-[400px] sm:max-w-[500px]">
                    <div class="absolute -inset-4 bg-red-600/15 blur-3xl rounded-full opacity-50 group-hover:opacity-80 transition-opacity duration-500"></div>
                    
                    <div class="relative w-full aspect-[16/10] transform rotate-y-12 rotate-x-2 group-hover:rotate-y-0 group-hover:rotate-x-0 transition-transform duration-700 ease-out shadow-[30px_30px_60px_rgba(0,0,0,0.7)] rounded-r-xl overflow-hidden border border-white/10 bg-black">
                        
                        <div class="absolute left-0 top-0 w-6 h-full bg-gradient-to-r from-black/80 via-black/40 to-transparent z-20 border-r border-white/5"></div>
                        
                        <img src="<?= esc(resolve_image_url(content_get($initialContent, 'sections.main_max_w_7xl_1.section_ebook.div_glass_panel_1.div_grid_2.div_order_1_2.div_relative_1.div_relative_2.img_h_full_1.src', ['source_type' => 'url', 'value' => 'https://aapp.space/storage/images/609c03880ee47-69b86cca5fcc3.jpg']))) ?>" alt="<?= esc(content_get($initialContent, 'sections.main_max_w_7xl_1.section_ebook.div_glass_panel_1.div_grid_2.div_order_1_2.div_relative_1.div_relative_2.img_h_full_1.alt', 'Portada Ebook Suplementación')) ?>" class="w-full h-full object-cover relative z-10" data-edit-key="sections.main_max_w_7xl_1.section_ebook.div_glass_panel_1.div_grid_2.div_order_1_2.div_relative_1.div_relative_2.img_h_full_1.src" data-edit-type="image" data-edit-key-alt="sections.main_max_w_7xl_1.section_ebook.div_glass_panel_1.div_grid_2.div_order_1_2.div_relative_1.div_relative_2.img_h_full_1.alt" data-edit-type-alt="text">
                        
                        <div class="absolute inset-0 bg-gradient-to-tr from-transparent via-white/5 to-white/10 z-20 pointer-events-none"></div>
                        
                        <div class="absolute inset-0 shadow-[inset_10px_0px_20px_rgba(0,0,0,0.5)] z-30 pointer-events-none"></div>
                    </div>

                    <div class="absolute right-[-4px] top-[4%] h-[92%] w-[10px] bg-gradient-to-b from-gray-300 via-white to-gray-300 rounded-r-sm transform translate-z-[-1px] opacity-20 group-hover:opacity-40 transition-opacity"></div>
                </div>
            </div>
        </div>
    </div>
</section>
<!-- SECCI&Oacute;N 2: AMARILLO- SUPLEMENTOS-->

<section id="suplementos" class="fade-in-section">
    <div class="glass-panel panel-yellow p-8 md:p-12 lg:p-16">
        <div class="absolute top-0 left-0 w-96 h-96 bg-yellow-500/10 blur-[100px] rounded-full pointer-events-none"></div>
        
        <div class="text-center max-w-3xl mx-auto relative z-10">
            <div class="flex justify-center items-center gap-3 mb-4">
                <div class="p-3 bg-yellow-500/10 rounded-xl">
                    <i data-lucide="pill" class="text-yellow-main w-6 h-6"></i>
                </div>
                <span class="text-yellow-accent font-bold tracking-[0.2em] uppercase text-xs" data-edit-key="sections.main_max_w_7xl_1.section_suplementos.div_glass_panel_1.div_max_w_3xl_2.div_flex_1.span_font_bold_1.text" data-edit-type="text"><?= esc(content_get($initialContent, 'sections.main_max_w_7xl_1.section_suplementos.div_glass_panel_1.div_max_w_3xl_2.div_flex_1.span_font_bold_1.text', 'Solo para Argentina')) ?></span>
            </div>

            <h2 class="text-3xl sm:text-4xl md:text-5xl font-black mb-6 uppercase leading-tight" data-edit-key="sections.main_max_w_7xl_1.section_suplementos.div_glass_panel_1.div_max_w_3xl_2.h2_sm_text_4xl_1.title" data-edit-type="text"><?= esc(content_get($initialContent, 'sections.main_max_w_7xl_1.section_suplementos.div_glass_panel_1.div_max_w_3xl_2.h2_sm_text_4xl_1.title', 'Suplementos')) ?></h2>
            
            <p class="text-gray-300 text-base sm:text-lg mb-4 leading-relaxed" data-edit-key="sections.main_max_w_7xl_1.section_suplementos.div_glass_panel_1.div_max_w_3xl_2.p_sm_text_lg_1.text" data-edit-type="text"><?= esc(content_get($initialContent, 'sections.main_max_w_7xl_1.section_suplementos.div_glass_panel_1.div_max_w_3xl_2.p_sm_text_lg_1.text', 'Accedé a suplementos deportivos seleccionados para entrenamiento y rendimiento, disponibles con envíos a todo el país.')) ?></p>
        </div>

        <div class="relative z-10 mt-8 border-t border-white/10 pt-10 mb-12">
            <p class="text-center text-[10px] text-gray-500 uppercase font-bold tracking-widest mb-6" data-edit-key="sections.main_max_w_7xl_1.section_suplementos.div_glass_panel_1.div_relative_3.p_uppercase_1.text" data-edit-type="text"><?= esc(content_get($initialContent, 'sections.main_max_w_7xl_1.section_suplementos.div_glass_panel_1.div_relative_3.p_uppercase_1.text', 'Trabajamos con')) ?></p>
            <div class="slider">
                <div class="slide-track" id="brands-track">
                    </div>
            </div>
        </div>

        <div class="flex flex-col sm:flex-row gap-4 justify-center items-center relative z-10">
            <a href="<?= esc(content_get($initialContent, 'sections.main_max_w_7xl_1.section_suplementos.div_glass_panel_1.div_flex_4.a_btn_base_1.href', 'https://wa.me/5491126333194?text=Hola%20Ezequiel,%20quiero%20consultar%20suplementos')) ?>" target="_blank" class="btn-base btn-yellow w-full sm:w-auto" data-edit-key="sections.main_max_w_7xl_1.section_suplementos.div_glass_panel_1.div_flex_4.a_btn_base_1.link_label" data-edit-type="text" data-edit-key-href="sections.main_max_w_7xl_1.section_suplementos.div_glass_panel_1.div_flex_4.a_btn_base_1.href" data-edit-type-href="text"><?= esc(content_get($initialContent, 'sections.main_max_w_7xl_1.section_suplementos.div_glass_panel_1.div_flex_4.a_btn_base_1.link_label', 'WhatsApp')) ?></a>
            <a href="<?= esc(content_get($initialContent, 'sections.main_max_w_7xl_1.section_suplementos.div_glass_panel_1.div_flex_4.a_btn_base_2.href', 'https://t.me/+541126704423')) ?>" target="_blank" class="btn-base btn-outline w-full sm:w-auto border-yellow-500/30 text-yellow-500 hover:bg-yellow-500/10" data-edit-key="sections.main_max_w_7xl_1.section_suplementos.div_glass_panel_1.div_flex_4.a_btn_base_2.link_label" data-edit-type="text" data-edit-key-href="sections.main_max_w_7xl_1.section_suplementos.div_glass_panel_1.div_flex_4.a_btn_base_2.href" data-edit-type-href="text"><?= esc(content_get($initialContent, 'sections.main_max_w_7xl_1.section_suplementos.div_glass_panel_1.div_flex_4.a_btn_base_2.link_label', 'Telegram')) ?></a>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const brandImages = [
                    "https://aapp.space/storage/images/609c03880ee47-69b4a7857c60c.jpg",
                    "https://aapp.space/storage/images/609c03880ee47-69b4a7853fc0b.jpg",
                    "https://aapp.space/storage/images/609c03880ee47-69b4a78528e66.jpg",
                    "https://aapp.space/storage/images/609c03880ee47-69b4a784cb638.jpg",
                    "https://aapp.space/storage/images/609c03880ee47-69b4a784cb220.jpg",
                    "https://aapp.space/storage/images/609c03880ee47-69b4a7832158f.jpg",
                    "https://aapp.space/storage/images/609c03880ee47-69b4a7831207d.jpg"
                ];
                
                const track = document.getElementById('brands-track');
                const allBrands = [...brandImages, ...brandImages]; 
                
                allBrands.forEach(src => {
                    const slide = document.createElement('div');
                    slide.className = 'slide';
                    const img = document.createElement('img');
                    img.src = src;
                    img.alt = 'Marca de suplemento';
                    img.loading = 'lazy';
                    slide.appendChild(img);
                    track.appendChild(slide);
                });
            });
        </script>
    </div>
</section>


<section id="asesoria" class="fade-in-section">
    <div class="glass-panel panel-blue p-8 md:p-12 lg:p-16 overflow-hidden relative">
        <div class="absolute bottom-0 right-0 w-[500px] h-[500px] bg-blue-600/10 blur-[120px] rounded-full pointer-events-none"></div>
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-12 lg:gap-16 items-start relative z-10">
            
            <div class="order-2 lg:order-1">
                <div class="bg-black/40 p-2 sm:p-4 rounded-3xl border border-white/10 relative group">
                    <p class="text-[10px] text-blue-accent font-bold uppercase tracking-[0.2em] mb-4 px-2 text-center" data-edit-key="sections.main_max_w_7xl_1.section_asesoria.div_glass_panel_1.div_grid_2.div_order_2_1.div_bg_black_40_1.p_font_bold_1.text" data-edit-type="text"><?= esc(content_get($initialContent, 'sections.main_max_w_7xl_1.section_asesoria.div_glass_panel_1.div_grid_2.div_order_2_1.div_bg_black_40_1.p_font_bold_1.text', 'Certificaciones')) ?></p>
                    
                    <div class="overflow-hidden rounded-2xl">
                        <div class="flex transition-transform duration-700 ease-in-out" id="diplomas-slider">
                            <div class="min-w-full"><img src="https://aapp.space/storage/images/609c03880ee47-69b86c6269606.jpg" class="w-full h-auto object-contain rounded-xl shadow-2xl"></div>
                            <div class="min-w-full"><img src="https://aapp.space/storage/images/609c03880ee47-69b86c640b23a.jpg" class="w-full h-auto object-contain rounded-xl shadow-2xl"></div>
                            <div class="min-w-full"><img src="https://aapp.space/storage/images/609c03880ee47-69b86c72f1b46.jpg" class="w-full h-auto object-contain rounded-xl shadow-2xl"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="order-1 lg:order-2 lg:col-span-2 space-y-12">
                
                <div class="text-center lg:text-left">
                    <div class="flex items-center justify-center lg:justify-start gap-3 mb-4">
                        <div class="p-3 bg-blue-500/10 rounded-xl">
                            <i data-lucide="brain" class="text-blue-main w-6 h-6"></i>
                        </div>
                        <span class="text-blue-accent font-bold tracking-[0.2em] uppercase text-xs" data-edit-key="sections.main_max_w_7xl_1.section_asesoria.div_glass_panel_1.div_grid_2.div_order_1_2.div_lg_text_left_1.div_flex_1.span_font_bold_1.text" data-edit-type="text"><?= esc(content_get($initialContent, 'sections.main_max_w_7xl_1.section_asesoria.div_glass_panel_1.div_grid_2.div_order_1_2.div_lg_text_left_1.div_flex_1.span_font_bold_1.text', 'Alto Rendimiento')) ?></span>
                    </div>

                    <h2 class="text-3xl sm:text-4xl md:text-5xl font-black mb-6 uppercase leading-tight" data-edit-key="sections.main_max_w_7xl_1.section_asesoria.div_glass_panel_1.div_grid_2.div_order_1_2.div_lg_text_left_1.h2_sm_text_4xl_1.title" data-edit-type="text"><?= esc(content_get($initialContent, 'sections.main_max_w_7xl_1.section_asesoria.div_glass_panel_1.div_grid_2.div_order_1_2.div_lg_text_left_1.h2_sm_text_4xl_1.title', 'Asesoría')) ?></h2>
                    
                    <p class="text-gray-300 text-base sm:text-lg mb-8 leading-relaxed max-w-2xl" data-edit-key="sections.main_max_w_7xl_1.section_asesoria.div_glass_panel_1.div_grid_2.div_order_1_2.div_lg_text_left_1.p_sm_text_lg_1.text" data-edit-type="text"><?= esc(content_get($initialContent, 'sections.main_max_w_7xl_1.section_asesoria.div_glass_panel_1.div_grid_2.div_order_1_2.div_lg_text_left_1.p_sm_text_lg_1.text', 'aplicada al entrenamiento, adaptada a tu objetivo y nivel. Enfoque basado en experiencia práctica y análisis científico.')) ?></p>

                    <div class="flex flex-col sm:flex-row gap-4 justify-center lg:justify-start items-center mb-10">
                        <a href="<?= esc(content_get($initialContent, 'sections.main_max_w_7xl_1.section_asesoria.div_glass_panel_1.div_grid_2.div_order_1_2.div_lg_text_left_1.div_flex_2.a_btn_base_1.href', 'https://t.me/+541126704423')) ?>" target="_blank" class="btn-base btn-blue w-full sm:w-auto" data-edit-key="sections.main_max_w_7xl_1.section_asesoria.div_glass_panel_1.div_grid_2.div_order_1_2.div_lg_text_left_1.div_flex_2.a_btn_base_1.link_label" data-edit-type="text" data-edit-key-href="sections.main_max_w_7xl_1.section_asesoria.div_glass_panel_1.div_grid_2.div_order_1_2.div_lg_text_left_1.div_flex_2.a_btn_base_1.href" data-edit-type-href="text"><?= esc(content_get($initialContent, 'sections.main_max_w_7xl_1.section_asesoria.div_glass_panel_1.div_grid_2.div_order_1_2.div_lg_text_left_1.div_flex_2.a_btn_base_1.link_label', 'Telegram')) ?></a>
                        <a href="<?= esc(content_get($initialContent, 'sections.main_max_w_7xl_1.section_asesoria.div_glass_panel_1.div_grid_2.div_order_1_2.div_lg_text_left_1.div_flex_2.a_btn_base_2.href', 'mailto:suplementacionavanzadamoderna@hotmail.com?subject=Quiero%20solicitar%20asesoría')) ?>" class="btn-base btn-outline w-full sm:w-auto" data-edit-key="sections.main_max_w_7xl_1.section_asesoria.div_glass_panel_1.div_grid_2.div_order_1_2.div_lg_text_left_1.div_flex_2.a_btn_base_2.link_label" data-edit-type="text" data-edit-key-href="sections.main_max_w_7xl_1.section_asesoria.div_glass_panel_1.div_grid_2.div_order_1_2.div_lg_text_left_1.div_flex_2.a_btn_base_2.href" data-edit-type-href="text"><?= esc(content_get($initialContent, 'sections.main_max_w_7xl_1.section_asesoria.div_glass_panel_1.div_grid_2.div_order_1_2.div_lg_text_left_1.div_flex_2.a_btn_base_2.link_label', 'Email')) ?></a>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-black/30 p-6 rounded-2xl border border-white/5">
                        <i data-lucide="check-circle" class="w-6 h-6 text-blue-main mb-3"></i>
                        <p class="text-sm font-bold text-white mb-2 uppercase tracking-tighter" data-edit-key="sections.main_max_w_7xl_1.section_asesoria.div_glass_panel_1.div_grid_2.div_order_1_2.div_grid_2.div_bg_black_30_1.p_font_bold_1.text" data-edit-type="text"><?= esc(content_get($initialContent, 'sections.main_max_w_7xl_1.section_asesoria.div_glass_panel_1.div_grid_2.div_order_1_2.div_grid_2.div_bg_black_30_1.p_font_bold_1.text', 'Protocolo Individual')) ?></p>
                        <p class="text-gray-400 text-xs leading-relaxed" data-edit-key="sections.main_max_w_7xl_1.section_asesoria.div_glass_panel_1.div_grid_2.div_order_1_2.div_grid_2.div_bg_black_30_1.p_leading_relaxed_2.text" data-edit-type="text"><?= esc(content_get($initialContent, 'sections.main_max_w_7xl_1.section_asesoria.div_glass_panel_1.div_grid_2.div_order_1_2.div_grid_2.div_bg_black_30_1.p_leading_relaxed_2.text', 'Adaptado a tu genética y estilo de vida real.')) ?></p>
                    </div>
                    <div class="bg-black/30 p-6 rounded-2xl border border-white/5">
                        <i data-lucide="check-circle" class="w-6 h-6 text-blue-main mb-3"></i>
                        <p class="text-sm font-bold text-white mb-2 uppercase tracking-tighter" data-edit-key="sections.main_max_w_7xl_1.section_asesoria.div_glass_panel_1.div_grid_2.div_order_1_2.div_grid_2.div_bg_black_30_2.p_font_bold_1.text" data-edit-type="text"><?= esc(content_get($initialContent, 'sections.main_max_w_7xl_1.section_asesoria.div_glass_panel_1.div_grid_2.div_order_1_2.div_grid_2.div_bg_black_30_2.p_font_bold_1.text', 'Ciencia Aplicada')) ?></p>
                        <p class="text-gray-400 text-xs leading-relaxed" data-edit-key="sections.main_max_w_7xl_1.section_asesoria.div_glass_panel_1.div_grid_2.div_order_1_2.div_grid_2.div_bg_black_30_2.p_leading_relaxed_2.text" data-edit-type="text"><?= esc(content_get($initialContent, 'sections.main_max_w_7xl_1.section_asesoria.div_glass_panel_1.div_grid_2.div_order_1_2.div_grid_2.div_bg_black_30_2.p_leading_relaxed_2.text', 'Ahorrá dinero usando solo lo que realmente funciona.')) ?></p>
                    </div>
                    <div class="bg-black/30 p-6 rounded-2xl border border-white/5">
                        <i data-lucide="check-circle" class="w-6 h-6 text-blue-main mb-3"></i>
                        <p class="text-sm font-bold text-white mb-2 uppercase tracking-tighter" data-edit-key="sections.main_max_w_7xl_1.section_asesoria.div_glass_panel_1.div_grid_2.div_order_1_2.div_grid_2.div_bg_black_30_3.p_font_bold_1.text" data-edit-type="text"><?= esc(content_get($initialContent, 'sections.main_max_w_7xl_1.section_asesoria.div_glass_panel_1.div_grid_2.div_order_1_2.div_grid_2.div_bg_black_30_3.p_font_bold_1.text', 'Seguimiento')) ?></p>
                        <p class="text-gray-400 text-xs leading-relaxed" data-edit-key="sections.main_max_w_7xl_1.section_asesoria.div_glass_panel_1.div_grid_2.div_order_1_2.div_grid_2.div_bg_black_30_3.p_leading_relaxed_2.text" data-edit-type="text"><?= esc(content_get($initialContent, 'sections.main_max_w_7xl_1.section_asesoria.div_glass_panel_1.div_grid_2.div_order_1_2.div_grid_2.div_bg_black_30_3.p_leading_relaxed_2.text', 'Feedback constante para asegurar tu progreso semanal.')) ?></p>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const slider = document.getElementById('diplomas-slider');
            const items = slider.children;
            let counter = 0;

            setInterval(() => {
                counter++;
                if (counter >= items.length) {
                    counter = 0;
                }
                slider.style.transform = `translateX(-${counter * 100}%)`;
            }, 3500); 
        });
    </script>
</section>

        <!-- SECCI&Oacute;N 4: BIO DE EZEQUIEL (Layout Vertical Profesional) -->
        <section class="fade-in-section pb-20">
            <div class="glass-panel p-0 overflow-hidden border-white/5">
                <div class="flex flex-col md:flex-row">
                    <!-- Imagen Vertical -->
                    <div class="w-full md:w-2/5 lg:w-1/3 h-[400px] md:h-auto relative">
                        <img src="<?= esc(resolve_image_url(content_get($initialContent, 'sections.main_max_w_7xl_1.section_fade_in_section_4.div_glass_panel_1.div_flex_1.div_md_w_2_5_1.img_absolute_1.src', ['source_type' => 'url', 'value' => 'https://aapp.space/storage/images/609c03880ee47-69b4a902f20ae.jpg']))) ?>" alt="<?= esc(content_get($initialContent, 'sections.main_max_w_7xl_1.section_fade_in_section_4.div_glass_panel_1.div_flex_1.div_md_w_2_5_1.img_absolute_1.alt', 'Ezequiel Usay Bio')) ?>" class="absolute inset-0 w-full h-full object-cover object-top filter grayscale-[20%]" data-edit-key="sections.main_max_w_7xl_1.section_fade_in_section_4.div_glass_panel_1.div_flex_1.div_md_w_2_5_1.img_absolute_1.src" data-edit-type="image" data-edit-key-alt="sections.main_max_w_7xl_1.section_fade_in_section_4.div_glass_panel_1.div_flex_1.div_md_w_2_5_1.img_absolute_1.alt" data-edit-type-alt="text">
                        <div class="absolute inset-0 bg-gradient-to-t md:bg-gradient-to-r from-gray-dark via-transparent to-transparent"></div>
                    </div>
                    
                    <!-- Texto Bio -->
                    <div class="w-full md:w-3/5 lg:w-2/3 p-8 md:p-12 lg:p-16 flex flex-col justify-center">
                        <p class="text-yellow-accent text-xs font-bold tracking-[0.2em] mb-2 uppercase" data-edit-key="sections.main_max_w_7xl_1.section_fade_in_section_4.div_glass_panel_1.div_flex_1.div_md_w_3_5_2.p_font_bold_1.text" data-edit-type="text"><?= esc(content_get($initialContent, 'sections.main_max_w_7xl_1.section_fade_in_section_4.div_glass_panel_1.div_flex_1.div_md_w_3_5_2.p_font_bold_1.text', 'Asesor, escritor, coach')) ?></p>
                        <h2 class="text-3xl md:text-4xl font-black mb-6 uppercase tracking-tight" data-edit-key="sections.main_max_w_7xl_1.section_fade_in_section_4.div_glass_panel_1.div_flex_1.div_md_w_3_5_2.h2_md_text_4xl_1.title" data-edit-type="text"><?= esc(content_get($initialContent, 'sections.main_max_w_7xl_1.section_fade_in_section_4.div_glass_panel_1.div_flex_1.div_md_w_3_5_2.h2_md_text_4xl_1.title', 'Ezequiel Usay')) ?></h2>
                        
                        <p class="text-gray-300 text-base md:text-lg leading-relaxed mb-8" data-edit-key="sections.main_max_w_7xl_1.section_fade_in_section_4.div_glass_panel_1.div_flex_1.div_md_w_3_5_2.p_md_text_lg_2.text" data-edit-type="text"><?= esc(content_get($initialContent, 'sections.main_max_w_7xl_1.section_fade_in_section_4.div_glass_panel_1.div_flex_1.div_md_w_3_5_2.p_md_text_lg_2.text', 'A través de')) ?></p>

                        <div class="flex flex-wrap gap-4 mt-auto">
                            <a href="<?= esc(content_get($initialContent, 'sections.main_max_w_7xl_1.section_fade_in_section_4.div_glass_panel_1.div_flex_1.div_md_w_3_5_2.div_flex_1.a_flex_1.href', 'https://www.instagram.com/suplementacionam')) ?>" target="_blank" class="flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-white hover:text-yellow-main transition-colors bg-white/5 px-6 py-3 rounded-xl border border-white/10 hover:border-yellow-main/50" data-edit-key="sections.main_max_w_7xl_1.section_fade_in_section_4.div_glass_panel_1.div_flex_1.div_md_w_3_5_2.div_flex_1.a_flex_1.link_label" data-edit-type="text" data-edit-key-href="sections.main_max_w_7xl_1.section_fade_in_section_4.div_glass_panel_1.div_flex_1.div_md_w_3_5_2.div_flex_1.a_flex_1.href" data-edit-type-href="text"><?= esc(content_get($initialContent, 'sections.main_max_w_7xl_1.section_fade_in_section_4.div_glass_panel_1.div_flex_1.div_md_w_3_5_2.div_flex_1.a_flex_1.link_label', 'Instagram')) ?></a>
                           
                        </div>
                    </div>
                </div>
            </div>
        </section>

    </main>

    <!-- FOOTER -->
    <footer class="text-center py-10 border-t border-white/5 bg-black">
        <div class="max-w-7xl mx-auto px-4">
            <h2 class="text-xl font-black mb-2 tracking-tighter text-white/80" data-edit-key="sections.footer_py_10_1.div_max_w_7xl_1.h2_font_black_1.title" data-edit-type="text"><?= esc(content_get($initialContent, 'sections.footer_py_10_1.div_max_w_7xl_1.h2_font_black_1.title', 'EZEQUIEL USAY')) ?></h2>
            <p class="text-[10px] text-gray-500 font-bold uppercase tracking-[0.2em] mb-8" data-edit-key="sections.footer_py_10_1.div_max_w_7xl_1.p_font_bold_1.text" data-edit-type="text"><?= esc(content_get($initialContent, 'sections.footer_py_10_1.div_max_w_7xl_1.p_font_bold_1.text', 'Fitness & Suplementación Avanzada')) ?></p>
            
            <div class="flex justify-center items-center gap-6 opacity-40 mb-8">
                <a href="<?= esc(content_get($initialContent, 'sections.footer_py_10_1.div_max_w_7xl_1.div_flex_1.a_hover_text_white_1.href', 'mailto:suplementacionavanzadamoderna@hotmail.com')) ?>" class="hover:text-white transition-colors" data-edit-key="sections.footer_py_10_1.div_max_w_7xl_1.div_flex_1.a_hover_text_white_1.href" data-edit-type="text" data-edit-key-href="sections.footer_py_10_1.div_max_w_7xl_1.div_flex_1.a_hover_text_white_1.href" data-edit-type-href="text"><i data-lucide="mail" class="w-5 h-5"></i></a>
                <a href="<?= esc(content_get($initialContent, 'sections.footer_py_10_1.div_max_w_7xl_1.div_flex_1.a_hover_text_white_2.href', 'https://www.instagram.com/misprote')) ?>" target="_blank" class="hover:text-white transition-colors" data-edit-key="sections.footer_py_10_1.div_max_w_7xl_1.div_flex_1.a_hover_text_white_2.href" data-edit-type="text" data-edit-key-href="sections.footer_py_10_1.div_max_w_7xl_1.div_flex_1.a_hover_text_white_2.href" data-edit-type-href="text"><i data-lucide="instagram" class="w-5 h-5"></i></a>
            </div>

            <p class="text-[10px] text-gray-700 font-semibold uppercase tracking-widest" data-edit-key="sections.footer_py_10_1.div_max_w_7xl_1.p_font_semibold_2.text" data-edit-type="text"><?= esc(content_get($initialContent, 'sections.footer_py_10_1.div_max_w_7xl_1.p_font_semibold_2.text', 'Ⓒ 2026 Todos los derechos reservados.')) ?></p>
        </div>
    </footer>

    <!-- Scripts -->
    <script>
        // Inicializar iconos de Lucide
        lucide.createIcons();

        // Animaci&oacute;n Fade In al hacer Scroll
        document.addEventListener('DOMContentLoaded', () => {
            const sections = document.querySelectorAll('.fade-in-section');
            
            const observerOptions = {
                threshold: 0.15,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        observer.unobserve(entry.target);
                    }
                });
            }, observerOptions);

            sections.forEach(section => {
                observer.observe(section);
            });
        });
    </script>
</body>
</html>
