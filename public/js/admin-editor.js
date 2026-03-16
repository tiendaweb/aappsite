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


const MAX_UPLOAD_SIZE = 5 * 1024 * 1024;
const ALLOWED_UPLOAD_TYPES = new Set(['image/jpeg', 'image/png', 'image/webp']);
const ALLOWED_UPLOAD_EXTENSIONS = ['.jpg', '.jpeg', '.png', '.webp'];

function setModalFeedback(message, ok = false) {
    const feedback = document.getElementById('modalFeedback');
    if (!feedback) return;
    feedback.textContent = message;
    feedback.className = `text-xs ${ok ? 'text-green-400' : 'text-red-400'}`;
}

function updateUploadFileInfo(file = null, isError = false) {
    const info = document.getElementById('uploadFileInfo');
    if (!info) return;

    if (!file) {
        info.textContent = 'No hay archivo seleccionado.';
        info.className = 'text-xs text-white/70';
        return;
    }

    info.textContent = `Archivo seleccionado: ${file.name}`;
    info.className = `text-xs ${isError ? 'text-red-400' : 'text-green-400'}`;
}

function validateImageFile(file) {
    if (!file) return { ok: false, error: 'Selecciona un archivo.' };

    const fileType = (file.type || '').toLowerCase();
    const fileName = (file.name || '').toLowerCase();
    const hasAllowedType = ALLOWED_UPLOAD_TYPES.has(fileType);
    const hasAllowedExtension = ALLOWED_UPLOAD_EXTENSIONS.some((ext) => fileName.endsWith(ext));

    if (!hasAllowedType && !hasAllowedExtension) {
        return { ok: false, error: 'Formato inválido. Usa JPG, PNG o WEBP.' };
    }

    if (file.size > MAX_UPLOAD_SIZE) {
        return { ok: false, error: 'El archivo supera 5MB.' };
    }

    return { ok: true };
}

function syncSelectedUploadFile(file) {
    const input = document.getElementById('imageFileInput');
    if (!input) return { ok: false };

    if (!file) {
        input.value = '';
        updateUploadFileInfo(null);
        return { ok: false };
    }

    const validation = validateImageFile(file);
    if (!validation.ok) {
        input.value = '';
        updateUploadFileInfo(file, true);
        setModalFeedback(validation.error, false);
        return validation;
    }

    const transfer = new DataTransfer();
    transfer.items.add(file);
    input.files = transfer.files;
    updateUploadFileInfo(file);
    setModalFeedback(`Archivo listo: ${file.name}`, true);
    return { ok: true, file };
}

async function uploadSelectedImage(file, imageEl) {
    const objectUrl = URL.createObjectURL(file);
    imageEl.src = objectUrl;

    const form = new FormData();
    form.append('key', currentImageKey);
    form.append('image', file);

    try {
        const response = await fetch(window.ADMIN_EDITOR_ENDPOINTS.uploadImage, { method: 'POST', body: form });
        const result = await response.json();
        if (!response.ok || !result.ok) {
            setModalFeedback(result.error || 'No se pudo subir la imagen.', false);
            return;
        }

        imageEl.src = result.url;
        setByPath(contentState, currentImageKey, { source_type: 'upload', value: result.url });
        imageEl.dataset.sourceType = 'upload';
        fieldMessage(currentImageKey, 'Imagen guardada', true);
        setModalFeedback('Imagen subida correctamente.', true);
    } finally {
        URL.revokeObjectURL(objectUrl);
    }
}


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
    const imageEl = document.querySelector(`[data-edit-key="${currentImageKey}"][data-edit-type="image"]`);
    if (!imageEl) return;
    if (!selectedLibraryUrl) {
        setModalFeedback('Selecciona una imagen de la biblioteca.', false);
        return;
    }

    imageEl.src = selectedLibraryUrl;
    setByPath(contentState, currentImageKey, { source_type: 'upload', value: selectedLibraryUrl });
    imageEl.dataset.sourceType = 'upload';

    try {
        await persistContent([currentImageKey]);
        setModalFeedback('Imagen actualizada desde biblioteca.', true);
    } catch (e) {
        setModalFeedback(e.message, false);
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

function isImageModalOpen() {
    const modal = document.getElementById('imageModal');
    return Boolean(modal) && !modal.classList.contains('hidden');
}

function getClipboardImageFile(event) {
    const items = event && event.clipboardData ? event.clipboardData.items : null;
    if (!items || items.length === 0) {
        return { ok: false, error: 'El portapapeles no contiene una imagen.' };
    }

    const imageItem = Array.from(items).find((item) => (item.type || '').toLowerCase().startsWith('image/'));
    if (!imageItem) {
        return { ok: false, error: 'El portapapeles no contiene una imagen.' };
    }

    const file = imageItem.getAsFile();
    if (!file) {
        return { ok: false, error: 'No se pudo leer la imagen pegada.' };
    }

    return { ok: true, file };
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
            updateUploadFileInfo(null);

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

    const imageFileInput = document.getElementById('imageFileInput');
    const uploadDropzone = document.getElementById('uploadDropzone');

    imageFileInput.addEventListener('change', () => {
        syncSelectedUploadFile(imageFileInput.files[0]);
    });

    uploadDropzone.addEventListener('click', () => imageFileInput.click());
    uploadDropzone.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            imageFileInput.click();
        }
    });

    ['dragenter', 'dragover'].forEach((eventName) => {
        uploadDropzone.addEventListener(eventName, (event) => {
            event.preventDefault();
            uploadDropzone.classList.add('ring-2', 'ring-art-neon', 'border-art-neon', 'text-art-neon');
        });
    });

    ['dragleave', 'drop'].forEach((eventName) => {
        uploadDropzone.addEventListener(eventName, (event) => {
            event.preventDefault();
            uploadDropzone.classList.remove('ring-2', 'ring-art-neon', 'border-art-neon', 'text-art-neon');
        });
    });

    uploadDropzone.addEventListener('drop', (event) => {
        const droppedFile = event.dataTransfer && event.dataTransfer.files ? event.dataTransfer.files[0] : null;
        syncSelectedUploadFile(droppedFile);
    });

    document.addEventListener('paste', (event) => {
        if (!isImageModalOpen()) return;

        const clipboardFile = getClipboardImageFile(event);
        if (!clipboardFile.ok) {
            setModalFeedback(clipboardFile.error, false);
            return;
        }

        const synced = syncSelectedUploadFile(clipboardFile.file);
        if (!synced.ok) {
            setModalFeedback(synced.error || 'No se pudo usar la imagen pegada.', false);
            return;
        }

        if (imageMode !== 'upload') {
            switchImageMode('upload');
        }
        setModalFeedback('Imagen pegada lista para subir.', true);
    });

    document.getElementById('saveModal').addEventListener('click', async () => {
        const imageEl = document.querySelector(`[data-edit-key="${currentImageKey}"][data-edit-type="image"]`);
        if (!imageEl) return;

        if (imageMode === 'url') {
            const newUrl = document.getElementById('imageUrlInput').value.trim();
            if (!/^https?:\/\//i.test(newUrl)) {
                setModalFeedback('Ingresa una URL válida (http/https).', false);
                return;
            }
            imageEl.src = newUrl;
            setByPath(contentState, currentImageKey, { source_type: 'url', value: newUrl });
            imageEl.dataset.sourceType = 'url';
            try {
                await persistContent([currentImageKey]);
                setModalFeedback('Imagen actualizada.', true);
            } catch (e) {
                setModalFeedback(e.message, false);
            }
            return;
        }

        if (imageMode === 'library') {
            await applyLibrarySelection();
            return;
        }

        const file = imageFileInput.files[0];
        const validation = validateImageFile(file);
        if (!validation.ok) {
            setModalFeedback(validation.error, false);
            updateUploadFileInfo(file, true);
            return;
        }

        await uploadSelectedImage(file, imageEl);
    });
}
