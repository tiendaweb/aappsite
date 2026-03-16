<?php

declare(strict_types=1);

require_once __DIR__ . '/template-helpers.php';
require_once __DIR__ . '/template-manager.php';
require_once __DIR__ . '/content-repo.php';

function normalize_template_slug(string $slug): string
{
    $slug = trim(mb_strtolower($slug));
    $slug = preg_replace('/[^a-z0-9\-]+/', '-', $slug) ?: '';
    $slug = trim($slug, '-');

    return $slug;
}

function import_backups_dir(): string
{
    return dirname(__DIR__) . '/data/template-imports';
}

function save_html_backup(string $slug, string $html): ?string
{
    $dir = import_backups_dir();
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        return null;
    }

    $timestamp = (new DateTimeImmutable())->format('YmdHis');
    $filename = sprintf('%s-%s.html', $slug, $timestamp);
    $path = $dir . '/' . $filename;

    if (file_put_contents($path, $html, LOCK_EX) === false) {
        return null;
    }

    return $path;
}

function import_template_from_html(string $slug, string $html): array
{
    $slug = normalize_template_slug($slug);
    if ($slug === '' || preg_match('/^[a-z0-9\-]+$/', $slug) !== 1) {
        throw new InvalidArgumentException('Slug inválido');
    }

    if (trim($html) === '') {
        throw new InvalidArgumentException('HTML vacío');
    }

    $backupPath = save_html_backup($slug, $html);
    if ($backupPath === null) {
        throw new RuntimeException('No se pudo guardar respaldo HTML');
    }

    libxml_use_internal_errors(true);
    $dom = new DOMDocument('1.0', 'UTF-8');
    $loaded = $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();

    if ($loaded === false) {
        throw new RuntimeException('No se pudo parsear HTML');
    }

    $xpath = new DOMXPath($dom);
    $slotCounter = 0;
    $slotMap = [];
    $usedKeys = [];
    $conflicts = [];
    $created = [];
    $defaults = [];

    $slugify = static function (string $value): string {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?: '';
        $value = trim($value, '_');

        return $value !== '' ? $value : 'item';
    };

    $allocateKey = static function (string $candidate) use (&$usedKeys, &$conflicts): string {
        if (!isset($usedKeys[$candidate])) {
            $usedKeys[$candidate] = 1;
            return $candidate;
        }

        $usedKeys[$candidate]++;
        $suffix = $usedKeys[$candidate];
        $resolved = $candidate . '_' . $suffix;
        $conflicts[] = ['base' => $candidate, 'resolved' => $resolved];
        return $resolved;
    };

    $setDetected = static function (string $key, $value) use (&$defaults, &$created): void {
        set_value_by_path($defaults, $key, $value);
        $created[] = $key;
    };

    $buildKey = static function (DOMElement $element, string $suffix) use ($slugify): string {
        if ($element->tagName === 'title') {
            return 'site.title';
        }

        $segments = [];
        $current = $element;
        while ($current instanceof DOMElement && !in_array($current->tagName, ['html', 'body'], true)) {
            $tag = $slugify($current->tagName);
            if ($current->hasAttribute('id')) {
                $label = $slugify((string) $current->getAttribute('id'));
            } else {
                $classes = preg_split('/\s+/', trim((string) $current->getAttribute('class'))) ?: [];
                $label = $tag;
                foreach ($classes as $className) {
                    $normalized = $slugify($className);
                    if ($normalized !== '' && !str_starts_with($normalized, 'w_') && !str_starts_with($normalized, 'text_')) {
                        $label = $normalized;
                        break;
                    }
                }

                $position = 1;
                if ($current->parentNode instanceof DOMElement) {
                    foreach ($current->parentNode->childNodes as $sibling) {
                        if (!$sibling instanceof DOMElement) {
                            continue;
                        }
                        if ($sibling->tagName === $current->tagName) {
                            if ($sibling->isSameNode($current)) {
                                break;
                            }
                            $position++;
                        }
                    }
                }
                $label .= '_' . $position;
            }

            array_unshift($segments, $tag . '_' . $label);
            $current = $current->parentNode;
        }

        return 'sections.' . implode('.', $segments) . '.' . $suffix;
    };

    $createSlot = static function (string $key, string $fallback) use (&$slotCounter, &$slotMap): string {
        $slotCounter++;
        $slot = '__PHP_SLOT_' . $slotCounter . '__';
        $slotMap[$slot] = sprintf('<?= esc(content_get($initialContent, %s, %s)) ?>', var_export($key, true), var_export($fallback, true));
        return $slot;
    };

    $nodes = $xpath->query('//text()[normalize-space(.) != "" and not(ancestor::script) and not(ancestor::style)]');
    if ($nodes !== false) {
        /** @var DOMText $textNode */
        foreach ($nodes as $textNode) {
            $parent = $textNode->parentNode;
            if (!$parent instanceof DOMElement) {
                continue;
            }

            $text = trim((string) $textNode->nodeValue);
            if ($text === '') {
                continue;
            }

            $suffix = match (strtolower($parent->tagName)) {
                'h1', 'h2', 'h3', 'h4', 'h5', 'h6' => 'title',
                'button' => 'cta_label',
                'a' => 'link_label',
                default => 'text',
            };

            $key = $allocateKey($buildKey($parent, $suffix));
            $parent->setAttribute('data-edit-key', $key);
            $parent->setAttribute('data-edit-type', 'text');
            $setDetected($key, $text);

            while ($parent->firstChild !== null) {
                $parent->removeChild($parent->firstChild);
            }
            $parent->appendChild($dom->createTextNode($createSlot($key, $text)));
        }
    }

    $attrNodes = $xpath->query('//*[@alt or @href]');
    if ($attrNodes !== false) {
        /** @var DOMElement $node */
        foreach ($attrNodes as $node) {
            foreach (['alt' => 'alt', 'href' => 'href'] as $attr => $suffix) {
                if (!$node->hasAttribute($attr)) {
                    continue;
                }
                $value = trim((string) $node->getAttribute($attr));
                if ($value === '') {
                    continue;
                }

                $key = $allocateKey($buildKey($node, $suffix));
                if (!$node->hasAttribute('data-edit-key')) {
                    $node->setAttribute('data-edit-key', $key);
                    $node->setAttribute('data-edit-type', 'text');
                }
                $node->setAttribute('data-edit-key-' . $attr, $key);
                $node->setAttribute('data-edit-type-' . $attr, 'text');
                $setDetected($key, $value);
                $node->setAttribute($attr, $createSlot($key, $value));
            }
        }
    }

    $jsonScripts = $xpath->query('//script[@type="application/ld+json"]');
    if ($jsonScripts !== false) {
        $jsonIndex = 0;
        /** @var DOMElement $script */
        foreach ($jsonScripts as $script) {
            $decoded = json_decode(trim((string) $script->textContent), true);
            if (!is_array($decoded)) {
                continue;
            }
            $jsonIndex++;
            set_value_by_path($defaults, 'embedded.ld_json_' . $jsonIndex, $decoded);
            $created[] = 'embedded.ld_json_' . $jsonIndex;
        }
    }

    $inlineScripts = $xpath->query('//script[not(@type) or @type="text/javascript"]');
    if ($inlineScripts !== false) {
        $inlineIndex = 0;
        /** @var DOMElement $script */
        foreach ($inlineScripts as $script) {
            $scriptText = trim((string) $script->textContent);
            if ($scriptText === '') {
                continue;
            }

            if (preg_match_all('/(?:const|let|var)\s+([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(\{[\s\S]*?\});/m', $scriptText, $matches, PREG_SET_ORDER) < 1) {
                continue;
            }

            foreach ($matches as $match) {
                $objectSource = trim((string) ($match[2] ?? ''));
                if ($objectSource === '') {
                    continue;
                }

                $candidate = json_decode($objectSource, true);
                if (!is_array($candidate)) {
                    $normalized = preg_replace('/([\{,]\s*)([A-Za-z_][A-Za-z0-9_]*)\s*:/', '$1"$2":', $objectSource) ?: $objectSource;
                    $normalized = str_replace("'", '"', $normalized);
                    $candidate = json_decode($normalized, true);
                }

                if (!is_array($candidate)) {
                    continue;
                }

                $inlineIndex++;
                $varName = $slugify((string) ($match[1] ?? ('object_' . $inlineIndex)));
                set_value_by_path($defaults, 'embedded.inline_js.' . $varName, $candidate);
                $created[] = 'embedded.inline_js.' . $varName;
            }
        }
    }

    $templateDir = dirname(__DIR__) . '/templates/' . $slug;
    if (!is_dir($templateDir) && !mkdir($templateDir, 0775, true) && !is_dir($templateDir)) {
        throw new RuntimeException('No se pudo crear directorio de plantilla');
    }

    $normalizedHtml = $dom->saveHTML();
    if (!is_string($normalizedHtml) || trim($normalizedHtml) === '') {
        throw new RuntimeException('No se pudo generar HTML normalizado');
    }

    $normalizedHtml = str_replace('<?xml encoding="UTF-8">', '', $normalizedHtml);

    foreach ($slotMap as $slot => $phpExpression) {
        $normalizedHtml = str_replace($slot, $phpExpression, $normalizedHtml);
    }

    $phpTemplate = "<?php\n\ndeclare(strict_types=1);\n\nrequire_once __DIR__ . '/../../includes/content-repo.php';\nrequire_once __DIR__ . '/../../includes/template-helpers.php';\n\n\$initialContent = read_content_file();\n?>\n" . $normalizedHtml;

    $targetFile = $templateDir . '/index.php';
    if (file_put_contents($targetFile, $phpTemplate, LOCK_EX) === false) {
        throw new RuntimeException('No se pudo escribir el archivo de plantilla');
    }

    if (!register_template_slug($slug)) {
        throw new RuntimeException('No se pudo registrar la plantilla');
    }

    $existingContent = read_content_file();
    $mergedContent = array_replace_recursive($existingContent, $defaults);
    if (!save_content_file($mergedContent)) {
        throw new RuntimeException('No se pudo actualizar data/content.json');
    }

    return [
        'slug' => $slug,
        'backup_path' => $backupPath,
        'template_path' => $targetFile,
        'editable_nodes' => count($created),
        'report' => [
            'variables_created' => count($created),
            'keys' => $created,
            'name_conflicts' => $conflicts,
        ],
    ];
}
