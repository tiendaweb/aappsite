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
        const response = await fetch(endpoints.saveContent, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
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

        if (!toggleBtn || !saveBtn) {
            const panel = document.createElement('div');
            panel.style.cssText = 'position:fixed;right:16px;bottom:16px;z-index:99999;display:flex;gap:8px;font-family:system-ui,sans-serif;';

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

            panel.append(toggleBtn, saveBtn);
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
    }

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

        const imageTarget = event.target instanceof Element ? event.target.closest('[data-edit-type="image"][data-edit-key]') : null;
        if (imageTarget instanceof HTMLImageElement) {
            event.preventDefault();
            const key = imageTarget.dataset.editKey;
            const current = imageTarget.getAttribute('src') || '';
            const next = window.prompt('Nueva URL de imagen', current);
            if (next && /^https?:\/\//i.test(next.trim())) {
                imageTarget.src = next.trim();
                setByPath(contentState, key, { source_type: 'url', value: next.trim() });
            }
        }

        const hrefTarget = event.target instanceof Element ? event.target.closest('[data-edit-key-href]') : null;
        if (hrefTarget instanceof HTMLAnchorElement && hrefTarget.dataset.editKeyHref) {
            event.preventDefault();
            const nextHref = window.prompt('Editar enlace', hrefTarget.getAttribute('href') || '');
            if (nextHref !== null) {
                hrefTarget.setAttribute('href', nextHref.trim());
                setByPath(contentState, hrefTarget.dataset.editKeyHref, nextHref.trim());
            }
        }
    });
})();
