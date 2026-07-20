/**
 * Listener gallery grid + lightbox carousel.
 */

/** @type {{ id: string, url: string, caption: string }[]} */
let galleryItems = [];
let lightboxIndex = 0;

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');
}

function ensureLightbox() {
    let root = document.getElementById('gallery-lightbox');
    if (root) {
        return root;
    }
    root = document.createElement('div');
    root.id = 'gallery-lightbox';
    root.className = 'gallery-lightbox';
    root.hidden = true;
    root.innerHTML = `
        <button type="button" class="gallery-lightbox-close" aria-label="Close">✕</button>
        <button type="button" class="gallery-lightbox-nav is-prev" aria-label="Previous">‹</button>
        <figure class="gallery-lightbox-frame">
            <img id="gallery-lightbox-img" alt="">
            <figcaption id="gallery-lightbox-caption"></figcaption>
        </figure>
        <button type="button" class="gallery-lightbox-nav is-next" aria-label="Next">›</button>
        <p class="gallery-lightbox-count" id="gallery-lightbox-count"></p>
    `;
    document.body.appendChild(root);

    root.querySelector('.gallery-lightbox-close')?.addEventListener('click', closeLightbox);
    root.querySelector('.is-prev')?.addEventListener('click', () => stepLightbox(-1));
    root.querySelector('.is-next')?.addEventListener('click', () => stepLightbox(1));
    root.addEventListener('click', (ev) => {
        if (ev.target === root) {
            closeLightbox();
        }
    });
    document.addEventListener('keydown', (ev) => {
        if (root.hidden) {
            return;
        }
        if (ev.key === 'Escape') {
            closeLightbox();
        } else if (ev.key === 'ArrowLeft') {
            stepLightbox(-1);
        } else if (ev.key === 'ArrowRight') {
            stepLightbox(1);
        }
    });

    return root;
}

function renderLightbox() {
    const item = galleryItems[lightboxIndex];
    if (!item) {
        return;
    }
    const img = document.getElementById('gallery-lightbox-img');
    const caption = document.getElementById('gallery-lightbox-caption');
    const count = document.getElementById('gallery-lightbox-count');
    if (img) {
        img.src = item.url;
        img.alt = item.caption || 'Service photo';
    }
    if (caption) {
        caption.textContent = item.caption || '';
        caption.hidden = !item.caption;
    }
    if (count) {
        count.textContent = `${lightboxIndex + 1} / ${galleryItems.length}`;
    }
}

export function openLightbox(index) {
    if (!galleryItems.length) {
        return;
    }
    lightboxIndex = Math.max(0, Math.min(galleryItems.length - 1, index));
    const root = ensureLightbox();
    root.hidden = false;
    document.body.classList.add('gallery-lightbox-open');
    renderLightbox();
}

export function closeLightbox() {
    const root = document.getElementById('gallery-lightbox');
    if (root) {
        root.hidden = true;
    }
    document.body.classList.remove('gallery-lightbox-open');
}

function stepLightbox(delta) {
    if (!galleryItems.length) {
        return;
    }
    lightboxIndex = (lightboxIndex + delta + galleryItems.length) % galleryItems.length;
    renderLightbox();
}

/**
 * @param {{ id: string|number, url: string, caption?: string }[]} images
 */
export function renderGalleryGrid(images) {
    const grid = document.getElementById('gallery-grid');
    if (!grid) {
        return;
    }

    galleryItems = images.map((image) => ({
        id: String(image.id),
        url: image.url,
        caption: image.caption || '',
    }));

    const empty = document.getElementById('gallery-empty');
    if (galleryItems.length === 0) {
        grid.innerHTML = '';
        if (empty) {
            grid.appendChild(empty);
            empty.hidden = false;
        } else {
            grid.innerHTML = '<p class="portal-empty" id="gallery-empty">No photos yet — they’ll appear here when the studio posts them.</p>';
        }
        return;
    }

    empty?.remove();
    grid.innerHTML = galleryItems.map((image, index) => `
        <button type="button" class="portal-gallery-item" data-id="${escapeHtml(image.id)}" data-index="${index}" aria-label="Open photo ${index + 1}">
            <img src="${escapeHtml(image.url)}" alt="${escapeHtml(image.caption || 'Service photo')}" loading="lazy">
            ${image.caption ? `<span class="portal-gallery-caption">${escapeHtml(image.caption)}</span>` : ''}
        </button>
    `).join('');

    for (const btn of grid.querySelectorAll('.portal-gallery-item')) {
        btn.addEventListener('click', () => {
            openLightbox(Number(btn.getAttribute('data-index') || 0));
        });
    }
}

/**
 * @param {string} url
 */
export async function refreshGalleryFromUrl(url) {
    if (!url || !document.getElementById('gallery-grid')) {
        return;
    }
    try {
        const res = await fetch(url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            cache: 'no-store',
        });
        if (!res.ok) {
            return;
        }
        const data = await res.json();
        const images = Array.isArray(data.images) ? data.images : [];
        renderGalleryGrid(images);
    } catch {
        /* ignore */
    }
}

export function bindInitialGalleryFromDom() {
    const grid = document.getElementById('gallery-grid');
    if (!grid) {
        return;
    }
    const buttons = [...grid.querySelectorAll('[data-id][data-url]')];
    if (buttons.length === 0) {
        return;
    }
    renderGalleryGrid(buttons.map((el) => ({
        id: el.getAttribute('data-id'),
        url: el.getAttribute('data-url'),
        caption: el.getAttribute('data-caption') || '',
    })));
}
