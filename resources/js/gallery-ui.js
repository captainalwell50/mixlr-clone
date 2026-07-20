/**
 * Listener gallery: photos + video reels, paged viewport, auto-scroll, lightbox.
 */

/** @type {{ id: string, url: string, caption: string, type: 'image'|'video', duration_seconds: number|null, poster_url: string|null }[]} */
let galleryItems = [];
let lightboxIndex = 0;
let pageIndex = 0;
let autoTimer = 0;
let resizeBound = false;
let lastPageSize = 0;
let resizeTimer = 0;

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');
}

function pageSize() {
    return window.matchMedia('(max-width: 959px)').matches ? 1 : 4;
}

function pageCount() {
    const size = pageSize();
    return Math.max(1, Math.ceil(galleryItems.length / size));
}

function normalizeItem(raw) {
    return {
        id: String(raw.id),
        url: raw.url,
        caption: raw.caption || '',
        type: raw.type === 'video' ? 'video' : 'image',
        duration_seconds: raw.duration_seconds != null ? Number(raw.duration_seconds) : null,
        poster_url: raw.poster_url || null,
    };
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
            <video id="gallery-lightbox-video" class="gallery-lightbox-video" controls playsinline></video>
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

function stopLightboxVideo() {
    const video = document.getElementById('gallery-lightbox-video');
    if (!video) {
        return;
    }
    video.pause();
    video.removeAttribute('src');
    video.load();
    video.hidden = true;
}

function renderLightbox() {
    const item = galleryItems[lightboxIndex];
    if (!item) {
        return;
    }
    const img = document.getElementById('gallery-lightbox-img');
    const video = document.getElementById('gallery-lightbox-video');
    const caption = document.getElementById('gallery-lightbox-caption');
    const count = document.getElementById('gallery-lightbox-count');

    if (item.type === 'video') {
        if (img) {
            img.hidden = true;
            img.removeAttribute('src');
        }
        if (video) {
            video.hidden = false;
            video.poster = item.poster_url || '';
            video.src = item.url;
            void video.play().catch(() => {});
        }
    } else {
        stopLightboxVideo();
        if (img) {
            img.hidden = false;
            img.src = item.url;
            img.alt = item.caption || 'Service photo';
        }
    }

    if (caption) {
        const label = item.type === 'video'
            ? (item.caption || 'Video reel')
            : (item.caption || '');
        caption.textContent = label;
        caption.hidden = !label;
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
    pauseAutoScroll();
    renderLightbox();
}

export function closeLightbox() {
    stopLightboxVideo();
    const root = document.getElementById('gallery-lightbox');
    if (root) {
        root.hidden = true;
    }
    document.body.classList.remove('gallery-lightbox-open');
    startAutoScroll();
}

function stepLightbox(delta) {
    if (!galleryItems.length) {
        return;
    }
    stopLightboxVideo();
    lightboxIndex = (lightboxIndex + delta + galleryItems.length) % galleryItems.length;
    renderLightbox();
}

function pauseAutoScroll() {
    if (autoTimer) {
        window.clearInterval(autoTimer);
        autoTimer = 0;
    }
}

function startAutoScroll() {
    pauseAutoScroll();
    if (galleryItems.length <= pageSize()) {
        return;
    }
    autoTimer = window.setInterval(() => {
        pageIndex = (pageIndex + 1) % pageCount();
        applyPage();
    }, 4200);
}

function applyPage() {
    const track = document.getElementById('gallery-track');
    const dots = document.getElementById('gallery-dots');
    if (!track) {
        return;
    }
    const pages = pageCount();
    pageIndex = ((pageIndex % pages) + pages) % pages;
    track.style.transform = `translateX(-${pageIndex * 100}%)`;
    if (dots) {
        for (const btn of dots.querySelectorAll('button')) {
            btn.classList.toggle('is-active', Number(btn.dataset.page) === pageIndex);
        }
    }
}

function thumbMarkup(item, globalIndex) {
    if (item.type === 'video') {
        return `
            <button type="button" class="portal-gallery-item is-video" data-id="${escapeHtml(item.id)}" data-index="${globalIndex}" aria-label="Open video reel">
                <video src="${escapeHtml(item.url)}" ${item.poster_url ? `poster="${escapeHtml(item.poster_url)}"` : ''} muted playsinline preload="metadata"></video>
                <span class="portal-gallery-reel-badge" aria-hidden="true">Reel</span>
                ${item.duration_seconds ? `<span class="portal-gallery-duration">${escapeHtml(String(item.duration_seconds))}s</span>` : ''}
                ${item.caption ? `<span class="portal-gallery-caption">${escapeHtml(item.caption)}</span>` : ''}
            </button>`;
    }

    return `
        <button type="button" class="portal-gallery-item" data-id="${escapeHtml(item.id)}" data-index="${globalIndex}" aria-label="Open photo">
            <img src="${escapeHtml(item.url)}" alt="${escapeHtml(item.caption || 'Service photo')}" loading="lazy">
            ${item.caption ? `<span class="portal-gallery-caption">${escapeHtml(item.caption)}</span>` : ''}
        </button>`;
}

/**
 * @param {{ id: string|number, url: string, caption?: string, type?: string, duration_seconds?: number|null, poster_url?: string|null }[]} images
 */
export function renderGalleryGrid(images) {
    const host = document.getElementById('gallery-grid');
    if (!host) {
        return;
    }

    galleryItems = images.map(normalizeItem);

    if (galleryItems.length === 0) {
        pauseAutoScroll();
        host.innerHTML = '<p class="portal-empty" id="gallery-empty">No photos or video reels yet — they’ll appear here when the studio posts them.</p>';
        return;
    }

    const size = pageSize();
    const pages = [];
    for (let i = 0; i < galleryItems.length; i += size) {
        pages.push(galleryItems.slice(i, i + size));
    }

    host.innerHTML = `
        <div class="portal-gallery-viewport" id="gallery-viewport">
            <div class="portal-gallery-track" id="gallery-track">
                ${pages.map((page, pIndex) => `
                    <div class="portal-gallery-page" data-page="${pIndex}">
                        ${page.map((image, i) => thumbMarkup(image, pIndex * size + i)).join('')}
                    </div>
                `).join('')}
            </div>
        </div>
        <div class="portal-gallery-dots" id="gallery-dots" ${pages.length < 2 ? 'hidden' : ''}>
            ${pages.map((_, i) => `<button type="button" data-page="${i}" aria-label="Gallery page ${i + 1}"></button>`).join('')}
        </div>
    `;

    for (const btn of host.querySelectorAll('.portal-gallery-item')) {
        btn.addEventListener('click', () => {
            openLightbox(Number(btn.getAttribute('data-index') || 0));
        });
    }

    const dots = document.getElementById('gallery-dots');
    dots?.querySelectorAll('button').forEach((btn) => {
        btn.addEventListener('click', () => {
            pageIndex = Number(btn.dataset.page || 0);
            applyPage();
            startAutoScroll();
        });
    });

    pageIndex = 0;
    lastPageSize = size;
    applyPage();
    startAutoScroll();

    const viewport = document.getElementById('gallery-viewport');
    viewport?.addEventListener('mouseenter', pauseAutoScroll);
    viewport?.addEventListener('mouseleave', startAutoScroll);
    viewport?.addEventListener('touchstart', pauseAutoScroll, { passive: true });
    viewport?.addEventListener('touchend', startAutoScroll, { passive: true });

    if (!resizeBound) {
        resizeBound = true;
        window.addEventListener('resize', () => {
            window.clearTimeout(resizeTimer);
            resizeTimer = window.setTimeout(() => {
                if (!galleryItems.length) {
                    return;
                }
                const nextSize = pageSize();
                if (nextSize === lastPageSize) {
                    return;
                }
                renderGalleryGrid(galleryItems);
            }, 150);
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
        const nextKey = images.map((i) => `${i.id}:${i.type || 'image'}`).join(',');
        const prevKey = galleryItems.map((i) => `${i.id}:${i.type}`).join(',');
        if (nextKey === prevKey && galleryItems.length > 0) {
            return;
        }
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
        type: el.getAttribute('data-type') || 'image',
        duration_seconds: el.getAttribute('data-duration') ? Number(el.getAttribute('data-duration')) : null,
        poster_url: el.getAttribute('data-poster') || null,
    })));
}
