(() => {
    window.showTab = window.showTab || function showTab(tabId) {
        document.querySelectorAll('.tab-content').forEach((el) => el.classList.remove('active'));
        document.querySelectorAll('.nav-btn').forEach((el) => el.classList.remove('active'));
        const target = document.getElementById(tabId);
        if (target) target.classList.add('active');
        const nav = document.querySelector(`[data-tab="${tabId}"]`);
        if (nav) nav.classList.add('active');
    };

    const layers = document.querySelectorAll('.bg-layer');
    let currentLayer = 0;
    if (layers.length > 1) {
        setInterval(() => {
            layers[currentLayer].classList.remove('active');
            currentLayer = (currentLayer + 1) % layers.length;
            layers[currentLayer].classList.add('active');
        }, 20000);
    }
    if (window.__ADMIN_EDITOR_BOOTSTRAPPED) return;
    window.__ADMIN_EDITOR_BOOTSTRAPPED = true;

    const isAuthenticated = window.APP_IS_AUTHENTICATED === true;
    if (!isAuthenticated) return;

    const contentState = window.APP_CONTENT_STATE || {};
    const activeTemplate = typeof window.APP_ACTIVE_TEMPLATE === 'string' ? window.APP_ACTIVE_TEMPLATE : '';
    const endpoints = window.ADMIN_EDITOR_ENDPOINTS || {};
    let editMode = false;

    function pathSegments(key) {
        return String(key || '').replace(/\[(\d+)\]/g, '.$1').split('.').filter(Boolean);
    }

    function setByPath(obj, key, value) {
        const segs = pathSegments(key);
        if (segs.length === 0) return;
        let cur = obj;
        for (let i = 0; i < segs.length - 1; i += 1) {
            if (cur[segs[i]] === undefined || typeof cur[segs[i]] !== 'object' || cur[segs[i]] === null) cur[segs[i]] = {};
            cur = cur[segs[i]];
        }
        cur[segs[segs.length - 1]] = value;
    }

    async function persistContent() {
        if (!endpoints.saveContent) throw new Error('Endpoint de guardado no configurado.');
        const headers = { 'Content-Type': 'application/json' };
        if (activeTemplate) headers['X-Active-Template'] = activeTemplate;

        const response = await fetch(endpoints.saveContent, {
            method: 'POST',
            headers,
            body: JSON.stringify(contentState),
        });
        const result = await response.json();
        if (!response.ok || !result.ok) {
            throw new Error(result.error || 'Error al guardar');
        }
    }

    function ensureEditorButtons() {
        let toggleBtn = document.getElementById('toggleEditBtn');
        let saveBtn = document.getElementById('saveContentBtn');
        let backBtn = document.getElementById('backToAdminBtn');

        if (!toggleBtn || !saveBtn || !backBtn) {
            const panel = document.createElement('div');
            panel.style.cssText = 'position:fixed;right:16px;bottom:16px;z-index:99999;display:flex;gap:8px;align-items:center;font-family:system-ui,sans-serif;';

            backBtn = document.createElement('a');
            backBtn.id = 'backToAdminBtn';
            backBtn.href = '/admin';
            backBtn.textContent = '↩️ Volver al admin';
            backBtn.style.cssText = 'background:#334155;color:#fff;border:1px solid #64748b;padding:10px 12px;border-radius:10px;text-decoration:none;font-weight:600;';

            toggleBtn = document.createElement('button');
            toggleBtn.id = 'toggleEditBtn';
            toggleBtn.type = 'button';
            toggleBtn.textContent = '✏️ Editar';
            toggleBtn.style.cssText = 'background:#111827;color:#fff;border:1px solid #374151;padding:10px 12px;border-radius:10px;cursor:pointer;';

            saveBtn = document.createElement('button');
            saveBtn.id = 'saveContentBtn';
            saveBtn.type = 'button';
            saveBtn.textContent = '💾 Guardar';
            saveBtn.classList.add('hidden');
            saveBtn.style.cssText = 'background:#06b6d4;color:#031016;border:1px solid #67e8f9;padding:10px 12px;border-radius:10px;cursor:pointer;';

            panel.append(backBtn, toggleBtn, saveBtn);
            document.body.appendChild(panel);
        }

        return { toggleBtn, saveBtn };
    }

    const { toggleBtn, saveBtn } = ensureEditorButtons();
    const editableText = Array.from(document.querySelectorAll('[data-edit-type="text"][data-edit-key]')).filter((el) => el.closest('body'));

    function collectAttributeBindings() {
        const bindings = [];
        document.querySelectorAll('[data-edit-key-href]').forEach((el) => bindings.push({ el, attr: 'href', key: el.dataset.editKeyHref }));
        document.querySelectorAll('[data-edit-key-alt]').forEach((el) => bindings.push({ el, attr: 'alt', key: el.dataset.editKeyAlt }));
        document.querySelectorAll('[data-edit-key-src]').forEach((el) => bindings.push({ el, attr: 'src', key: el.dataset.editKeySrc }));
        return bindings.filter((item) => item.key);
    }

    function ensureLinkEditBadges() {
        if (document.getElementById('admin-link-badge-style')) return;
        const style = document.createElement('style');
        style.id = 'admin-link-badge-style';
        style.textContent = '.admin-link-edit-badge{display:none;position:absolute;top:-10px;right:-10px;width:24px;height:24px;border-radius:999px;border:1px solid #94a3b8;background:#0f172a;color:#e2e8f0;align-items:center;justify-content:center;font-size:13px;cursor:pointer;z-index:60}.admin-link-wrapper{position:relative;display:inline-flex}.admin-link-wrapper:hover .admin-link-edit-badge{display:flex}.admin-link-edit-badge[data-visible="1"]{display:flex}';
        document.head.appendChild(style);

        document.querySelectorAll('a[data-edit-key-href]').forEach((anchor) => {
            if (!(anchor instanceof HTMLAnchorElement)) return;
            if (anchor.parentElement && anchor.parentElement.classList.contains('admin-link-wrapper')) return;
            const wrapper = document.createElement('span');
            wrapper.className = 'admin-link-wrapper';
            anchor.parentNode?.insertBefore(wrapper, anchor);
            wrapper.appendChild(anchor);

            const badge = document.createElement('button');
            badge.type = 'button';
            badge.className = 'admin-link-edit-badge';
            badge.dataset.linkTarget = anchor.dataset.editKeyHref || '';
            badge.title = 'Editar enlace';
            badge.setAttribute('aria-label', 'Editar enlace');
            badge.textContent = '🔗';
            wrapper.appendChild(badge);
        });
    }

    const modal = (() => {
        let root;
        let title;
        let feedback;
        let urlInput;
        let imageUploadInput;
        let imageUploadButton;
        let libraryGrid;
        let libraryStatus;
        let confirmLibrarySelection;
        let saveBtnModal;
        let activeMode = 'url';
        let context = null;
        let selectedLibraryUrl = '';

        function setHidden(element, hidden) {
            if (!element) return;
            element.classList.toggle('hidden', hidden);
            element.style.display = hidden ? 'none' : '';
        }

        function close() {
            if (!root) return;
            setHidden(root, true);
            context = null;
            feedback.textContent = '';
            selectedLibraryUrl = '';
        }

        function setFeedback(message, isError = false) {
            feedback.textContent = message || '';
            feedback.style.color = isError ? '#fca5a5' : '#93c5fd';
        }

        function switchMode(mode) {
            activeMode = mode;
            root.querySelectorAll('[data-modal-pane]').forEach((pane) => setHidden(pane, true));
            setHidden(root.querySelector(`[data-modal-pane="${mode}"]`), false);
            root.querySelectorAll('[data-modal-mode]').forEach((btn) => {
                btn.classList.toggle('bg-cyan-400', btn.getAttribute('data-modal-mode') === mode);
                btn.classList.toggle('text-black', btn.getAttribute('data-modal-mode') === mode);
            });
        }

        async function loadLibrary() {
            if (!endpoints.listImages) {
                setFeedback('No hay endpoint de biblioteca configurado.', true);
                return;
            }
            libraryStatus.textContent = 'Cargando biblioteca...';
            libraryGrid.innerHTML = '';
            try {
                const response = await fetch(endpoints.listImages);
                const result = await response.json();
                if (!response.ok || !result.ok) throw new Error(result.error || 'No se pudo listar biblioteca');

                const images = Array.isArray(result.images) ? result.images : [];
                if (!images.length) {
                    libraryStatus.textContent = 'No hay imágenes subidas todavía.';
                    return;
                }

                libraryStatus.textContent = 'Elegí una imagen de la biblioteca:';
                images.forEach((item) => {
                    const cell = document.createElement('button');
                    cell.type = 'button';
                    cell.className = 'border border-white/20 rounded-lg overflow-hidden hover:border-cyan-400';
                    cell.innerHTML = `<img src="${item.url}" alt="${item.name || 'Imagen'}" class="w-full h-24 object-cover">`;
                    cell.addEventListener('click', () => {
                        selectedLibraryUrl = item.url;
                        libraryGrid.querySelectorAll('button').forEach((btn) => btn.classList.remove('ring-2', 'ring-cyan-400'));
                        cell.classList.add('ring-2', 'ring-cyan-400');
                    });
                    libraryGrid.appendChild(cell);
                });
            } catch (error) {
                libraryStatus.textContent = 'No se pudo cargar la biblioteca.';
                setFeedback(String(error), true);
            }
        }

        async function uploadImageForKey() {
            if (!context || context.type !== 'image') return null;
            const file = imageUploadInput.files && imageUploadInput.files[0];
            if (!file) {
                setFeedback('Seleccioná una imagen para subir.', true);
                return null;
            }
            if (!endpoints.uploadImage) {
                setFeedback('No hay endpoint de subida configurado.', true);
                return null;
            }
            const formData = new FormData();
            formData.append('key', context.key);
            formData.append('image', file);
            const response = await fetch(endpoints.uploadImage, { method: 'POST', body: formData });
            const result = await response.json();
            if (!response.ok || !result.ok) throw new Error(result.error || 'Error subiendo imagen');
            return result.url;
        }

        function applyValue() {
            if (!context) return;

            if (context.type === 'link') {
                context.element.setAttribute('href', urlInput.value.trim());
                setByPath(contentState, context.key, urlInput.value.trim());
                close();
                return;
            }

            if (context.type === 'image') {
                const value = urlInput.value.trim();
                context.element.src = value;
                setByPath(contentState, context.key, { source_type: 'url', value });
                close();
            }
        }

        function init() {
            root = document.createElement('div');
            root.className = 'fixed inset-0 z-[100000] bg-black/70 px-4 items-center justify-center';
            root.innerHTML = `
            <div class="w-full max-w-xl rounded-2xl border border-white/20 bg-slate-900 text-slate-100 p-6 space-y-4">
                <div class="flex items-center justify-between gap-3">
                    <h3 id="adminEditorModalTitle" class="text-xl font-semibold">Editar</h3>
                    <button type="button" data-close-modal class="px-3 py-1 rounded bg-white/10">Cerrar</button>
                </div>
                <div class="flex gap-2 text-sm">
                    <button type="button" data-modal-mode="url" class="px-3 py-2 rounded bg-cyan-400 text-black font-medium">URL</button>
                    <button type="button" data-modal-mode="upload" class="px-3 py-2 rounded bg-white/10">Subir</button>
                    <button type="button" data-modal-mode="library" class="px-3 py-2 rounded bg-white/10">Biblioteca</button>
                </div>
                <div data-modal-pane="url" class="space-y-2">
                    <label class="text-xs block">URL</label>
                    <input id="adminEditorModalUrl" type="text" class="w-full rounded bg-white/5 border border-white/20 px-3 py-2 outline-none focus:border-cyan-300" placeholder="https://..., mailto:..., #seccion">
                </div>
                <div data-modal-pane="upload" class="hidden space-y-2">
                    <label class="text-xs block">Archivo (jpg/png/webp, máx 5MB)</label>
                    <input id="adminEditorModalUpload" type="file" accept="image/png,image/jpeg,image/webp" class="w-full text-xs">
                    <button id="adminEditorModalUploadBtn" type="button" class="px-3 py-2 rounded bg-white/10">Subir y usar</button>
                </div>
                <div data-modal-pane="library" class="hidden space-y-2">
                    <p id="adminEditorLibraryStatus" class="text-xs text-slate-300">Explorá la biblioteca.</p>
                    <div id="adminEditorLibraryGrid" class="grid grid-cols-2 sm:grid-cols-3 gap-3 max-h-72 overflow-y-auto"></div>
                    <button id="adminEditorConfirmLibrary" type="button" class="px-3 py-2 rounded bg-white/10">Usar seleccionada</button>
                </div>
                <p id="adminEditorModalFeedback" class="text-xs"></p>
                <div class="flex justify-end gap-2">
                    <button type="button" data-close-modal class="px-4 py-2 rounded bg-white/10">Cancelar</button>
                    <button id="adminEditorModalSave" type="button" class="px-4 py-2 rounded bg-cyan-400 text-black font-semibold">Guardar</button>
                </div>
            </div>`;

            document.body.appendChild(root);
            root.style.display = 'none';

            title = root.querySelector('#adminEditorModalTitle');
            feedback = root.querySelector('#adminEditorModalFeedback');
            urlInput = root.querySelector('#adminEditorModalUrl');
            imageUploadInput = root.querySelector('#adminEditorModalUpload');
            imageUploadButton = root.querySelector('#adminEditorModalUploadBtn');
            libraryGrid = root.querySelector('#adminEditorLibraryGrid');
            libraryStatus = root.querySelector('#adminEditorLibraryStatus');
            confirmLibrarySelection = root.querySelector('#adminEditorConfirmLibrary');
            saveBtnModal = root.querySelector('#adminEditorModalSave');

            root.querySelectorAll('[data-modal-mode]').forEach((btn) => {
                btn.addEventListener('click', async () => {
                    const mode = btn.getAttribute('data-modal-mode') || 'url';
                    switchMode(mode);
                    if (mode === 'library') await loadLibrary();
                });
            });
            root.querySelectorAll('[data-close-modal]').forEach((btn) => btn.addEventListener('click', close));

            saveBtnModal.addEventListener('click', applyValue);

            imageUploadButton.addEventListener('click', async () => {
                try {
                    const uploadedUrl = await uploadImageForKey();
                    if (!uploadedUrl || !context || context.type !== 'image') return;
                    context.element.src = uploadedUrl;
                    setByPath(contentState, context.key, { source_type: 'upload', value: uploadedUrl });
                    setFeedback('Imagen subida correctamente.');
                    close();
                } catch (error) {
                    setFeedback(String(error), true);
                }
            });

            confirmLibrarySelection.addEventListener('click', () => {
                if (!selectedLibraryUrl || !context || context.type !== 'image') {
                    setFeedback('Seleccioná una imagen primero.', true);
                    return;
                }
                context.element.src = selectedLibraryUrl;
                setByPath(contentState, context.key, { source_type: 'upload', value: selectedLibraryUrl });
                close();
            });
        }

        function open(nextContext) {
            if (!root) init();
            context = nextContext;
            selectedLibraryUrl = '';
            feedback.textContent = '';
            imageUploadInput.value = '';
            if (nextContext.type === 'link') {
                title.textContent = 'Editar enlace';
                urlInput.value = nextContext.element.getAttribute('href') || '';
                switchMode('url');
                setHidden(root.querySelector('[data-modal-mode="upload"]'), true);
                setHidden(root.querySelector('[data-modal-mode="library"]'), true);
            } else {
                title.textContent = 'Editar imagen';
                urlInput.value = nextContext.element.getAttribute('src') || '';
                switchMode('url');
                setHidden(root.querySelector('[data-modal-mode="upload"]'), false);
                setHidden(root.querySelector('[data-modal-mode="library"]'), false);
            }
            setHidden(root, false);
            root.style.display = 'flex';
        }

        return { open };
    })();

    function applyEditMode(enabled) {
        editableText.forEach((el) => {
            el.contentEditable = enabled ? 'true' : 'false';
            el.style.outline = enabled ? '2px dashed #22d3ee' : '';
            el.style.outlineOffset = enabled ? '2px' : '';
        });

        document.querySelectorAll('[data-edit-type="image"][data-edit-key]').forEach((img) => {
            img.style.cursor = enabled ? 'pointer' : '';
            img.style.outline = enabled ? '2px dashed #f59e0b' : '';
            img.style.outlineOffset = enabled ? '2px' : '';
        });

        document.querySelectorAll('.admin-link-edit-badge').forEach((badge) => {
            badge.setAttribute('data-visible', enabled ? '1' : '0');
        });
    }

    ensureLinkEditBadges();

    toggleBtn.addEventListener('click', () => {
        editMode = !editMode;
        applyEditMode(editMode);
        if (saveBtn) saveBtn.classList.toggle('hidden', !editMode);
        toggleBtn.textContent = editMode ? '✅ Modo edición activo' : '✏️ Editar';
    });

    saveBtn.addEventListener('click', async () => {
        editableText.forEach((el) => {
            setByPath(contentState, el.dataset.editKey, (el.textContent || '').trim());
        });

        collectAttributeBindings().forEach(({ el, attr, key }) => {
            setByPath(contentState, key, String(el.getAttribute(attr) || '').trim());
        });

        try {
            await persistContent();
            saveBtn.textContent = '✅ Guardado';
            setTimeout(() => { saveBtn.textContent = '💾 Guardar'; }, 1200);
        } catch (error) {
            saveBtn.textContent = '❌ Error';
            console.error(error);
            setTimeout(() => { saveBtn.textContent = '💾 Guardar'; }, 1500);
        }
    });

    document.addEventListener('click', (event) => {
        if (!editMode) return;

        const editIcon = event.target instanceof Element ? event.target.closest('[data-edit-target]') : null;
        if (editIcon instanceof Element) {
            const key = editIcon.getAttribute('data-edit-target') || '';
            const imageTarget = document.querySelector(`[data-edit-type="image"][data-edit-key="${CSS.escape(key)}"]`);
            if (imageTarget instanceof HTMLImageElement) {
                event.preventDefault();
                modal.open({ type: 'image', element: imageTarget, key });
                return;
            }
        }

        const imageTarget = event.target instanceof Element ? event.target.closest('[data-edit-type="image"][data-edit-key]') : null;
        if (imageTarget instanceof HTMLImageElement) {
            event.preventDefault();
            modal.open({ type: 'image', element: imageTarget, key: imageTarget.dataset.editKey || '' });
            return;
        }

        const linkBadge = event.target instanceof Element ? event.target.closest('.admin-link-edit-badge') : null;
        if (linkBadge instanceof HTMLButtonElement) {
            event.preventDefault();
            const anchor = linkBadge.parentElement?.querySelector('a[data-edit-key-href]');
            if (anchor instanceof HTMLAnchorElement && anchor.dataset.editKeyHref) {
                modal.open({ type: 'link', element: anchor, key: anchor.dataset.editKeyHref });
            }
            return;
        }

        const hrefTarget = event.target instanceof Element ? event.target.closest('a[data-edit-key-href]') : null;
        if (hrefTarget instanceof HTMLAnchorElement && hrefTarget.dataset.editKeyHref) {
            event.preventDefault();
            modal.open({ type: 'link', element: hrefTarget, key: hrefTarget.dataset.editKeyHref });
        }
    });
})();
