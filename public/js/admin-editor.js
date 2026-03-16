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
