function showTab(tabId) {
    document.querySelectorAll('.tab-content').forEach((el) => el.classList.remove('active'));
    document.querySelectorAll('.nav-btn').forEach((el) => el.classList.remove('active'));
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

const isAuthenticated = window.APP_IS_AUTHENTICATED === true;
const contentState = window.APP_CONTENT_STATE || {};
let editMode = false;
let currentImageKey = '';
let imageMode = 'url';
let libraryLoading = false;
let selectedLibraryUrl = '';

function normalizeImageUrl(url) {
    if (!url) return '';
    if (/^https?:\/\//i.test(url)) return url;
    try {
        return new URL(url, window.location.origin).toString();
    } catch (_) {
        return url;
    }
}

function renderLibrary(images = [], selectedUrl = '') {
    const grid = document.getElementById('libraryGrid');
    const normalizedSelectedUrl = normalizeImageUrl(selectedUrl);
    grid.innerHTML = '';

    images.forEach((image) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.dataset.url = image.url;
        button.className = 'border border-white/20 rounded-lg overflow-hidden bg-white/5 transition hover:border-art-neon';

        const thumb = document.createElement('img');
        thumb.src = image.url;
        thumb.alt = image.name || 'Imagen subida';
        thumb.className = 'w-full h-24 object-cover block';

        const name = document.createElement('span');
        name.className = 'block text-[10px] p-2 truncate text-left';
        name.textContent = image.name || image.url;

        button.appendChild(thumb);
        button.appendChild(name);
        button.addEventListener('click', () => {
            selectedLibraryUrl = image.url;
            grid.querySelectorAll('[data-url]').forEach((candidate) => {
                candidate.classList.remove('ring-2', 'ring-art-neon');
            });
            button.classList.add('ring-2', 'ring-art-neon');
        });

        if (normalizeImageUrl(image.url) === normalizedSelectedUrl) {
            selectedLibraryUrl = image.url;
            button.classList.add('ring-2', 'ring-art-neon');
        }

        grid.appendChild(button);
    });
}

async function loadImageLibrary(currentSrc = '') {
    if (libraryLoading) return;
    libraryLoading = true;
    const status = document.getElementById('libraryStatus');
    status.textContent = 'Cargando imágenes...';
    status.className = 'text-xs text-white/70';

    try {
        const response = await fetch(window.ADMIN_EDITOR_ENDPOINTS.listImages, { method: 'GET' });
        const result = await response.json();
        if (!response.ok || !result.ok || !Array.isArray(result.images)) {
            throw new Error(result.error || 'No se pudo cargar la biblioteca.');
        }

        renderLibrary(result.images, currentSrc);
        if (result.images.length === 0) {
            status.textContent = 'No hay imágenes subidas todavía.';
            status.className = 'text-xs text-yellow-300';
        } else {
            status.textContent = 'Selecciona una imagen de la biblioteca.';
            status.className = 'text-xs text-white/70';
        }
    } catch (error) {
        status.textContent = error.message || 'Error cargando la biblioteca.';
        status.className = 'text-xs text-red-400';
        document.getElementById('libraryGrid').innerHTML = '';
    } finally {
        libraryLoading = false;
    }
}

async function applyLibrarySelection() {
    const feedback = document.getElementById('modalFeedback');
    const imageEl = document.querySelector(`[data-edit-key="${currentImageKey}"][data-edit-type="image"]`);
    if (!imageEl) return;
    if (!selectedLibraryUrl) {
        feedback.textContent = 'Selecciona una imagen de la biblioteca.';
        feedback.className = 'text-xs text-red-400';
        return;
    }

    imageEl.src = selectedLibraryUrl;
    setByPath(contentState, currentImageKey, { source_type: 'upload', value: selectedLibraryUrl });
    imageEl.dataset.sourceType = 'upload';

    try {
        await persistContent([currentImageKey]);
        feedback.textContent = 'Imagen actualizada desde biblioteca.';
        feedback.className = 'text-xs text-green-400';
    } catch (e) {
        feedback.textContent = e.message;
        feedback.className = 'text-xs text-red-400';
    }
}

function switchImageMode(mode) {
    imageMode = mode === 'upload' || mode === 'library' ? mode : 'url';
    document.getElementById('urlPane').classList.toggle('hidden', imageMode !== 'url');
    document.getElementById('uploadPane').classList.toggle('hidden', imageMode !== 'upload');
    document.getElementById('libraryPane').classList.toggle('hidden', imageMode !== 'library');
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
    for (let i = 0; i < segs.length - 1; i += 1) {
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
    const response = await fetch(window.ADMIN_EDITOR_ENDPOINTS.saveContent, {
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
        editableText.forEach((el) => {
            el.contentEditable = editMode ? 'true' : 'false';
        });
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
        try {
            await persistContent(changed);
        } catch (_) {
            // handled with field messages
        }
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
            selectedLibraryUrl = '';
            switchImageMode(imageEl.dataset.sourceType || 'url');
            document.getElementById('modalFeedback').textContent = '';

            if (imageEl.dataset.sourceType === 'upload') {
                switchImageMode('library');
                loadImageLibrary(imageEl.getAttribute('src') || '');
            }
        });
    });

    document.querySelectorAll('.modal-mode').forEach((button) => {
        button.addEventListener('click', () => {
            switchImageMode(button.dataset.mode);
            if (button.dataset.mode === 'library') {
                const imageEl = document.querySelector(`[data-edit-key="${currentImageKey}"][data-edit-type="image"]`);
                loadImageLibrary(imageEl ? (imageEl.getAttribute('src') || '') : '');
            }
        });
    });

    document.getElementById('confirmLibrarySelection').addEventListener('click', async () => {
        if (imageMode !== 'library') return;
        await applyLibrarySelection();
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

        if (imageMode === 'library') {
            await applyLibrarySelection();
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

        const response = await fetch(window.ADMIN_EDITOR_ENDPOINTS.uploadImage, { method: 'POST', body: form });
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
