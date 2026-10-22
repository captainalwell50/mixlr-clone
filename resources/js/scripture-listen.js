// Keep tight while live — scripture/song/NDI are poll-only (no websocket). Rate limit: listen-poll 900/min.
const POLL_MS = 1500;
const DEFAULT_VERSION = 'KJV';

/**
 * Poll current live board and drive both:
 * 1. The premium live player scripture overlay (lower-third inside portal-artwork-frame).
 * 2. The below-the-fold/tab scripture slide and live board details.
 * Last successful cue wins (scripture vs song vs NDI).
 */
export function bindScriptureListen(root) {
    const host = root?.dataset?.scriptureUrl
        ? root
        : document.querySelector('[data-scripture-url]');
    const url = host?.dataset?.scriptureUrl;
    if (!url) {
        return;
    }

    // Right-column / tab elements
    const scriptureSlide = document.getElementById('scripture-slide');
    const songSlide = document.getElementById('song-slide');
    const ndiSlide = document.getElementById('ndi-slide');
    const scriptureRefEl = scriptureSlide?.querySelector('.scripture-ref');
    const scriptureTextEl = scriptureSlide?.querySelector('.scripture-text');
    const songTitleEl = songSlide?.querySelector('.scripture-ref');
    const songTextEl = songSlide?.querySelector('.scripture-text');
    const songMetaEl = songSlide?.querySelector('.song-slide-meta');
    const ndiImage = document.getElementById('ndi-slide-image');
    const ndiSourceEl = document.getElementById('ndi-slide-source');
    const art = document.querySelector('.portal-art');
    const scriptureEmpty = document.getElementById('scripture-empty-state');
    const scriptureDot = document.getElementById('scripture-tab-dot');
    const cards = document.getElementById('portal-cards');

    // Premium live player overlay elements
    const overlay = document.getElementById('live-scripture-overlay');
    const overlayCard = document.getElementById('live-scripture-card');
    const overlayRef = document.getElementById('live-scripture-ref');
    const overlayText = document.getElementById('live-scripture-text');
    const overlayHint = document.getElementById('live-scripture-expand-hint');

    if (!scriptureSlide && !overlay) {
        return;
    }

    let lastKey = '';
    let timer = null;
    let hideOverlayTimer = null;

    // Mobile tap-to-expand behavior for full verse
    if (overlayCard) {
        overlayCard.addEventListener('click', () => {
            const selection = window.getSelection();
            if (selection && selection.toString().length > 0) {
                return;
            }
            const isExpanded = overlayCard.classList.toggle('is-expanded');
            overlayCard.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
            if (overlayHint) {
                const label = overlayHint.querySelector('.expand-label');
                if (label) {
                    label.textContent = isExpanded ? 'Tap to collapse' : 'Tap to expand';
                }
            }
        });

        overlayCard.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                overlayCard.click();
            }
        });
    }

    function notifyActive() {
        if (scriptureEmpty) {
            scriptureEmpty.classList.add('hidden');
        }
        if (scriptureDot && cards && cards.dataset.activeTab !== 'scripture' && cards.dataset.activeTab !== 'board') {
            scriptureDot.classList.remove('hidden');
        }
    }

    function setReference(refEl, ref, version) {
        if (!refEl) return;
        const label = (version || DEFAULT_VERSION).trim() || DEFAULT_VERSION;
        refEl.replaceChildren();
        refEl.append(document.createTextNode(ref));
        const ver = document.createElement('span');
        ver.className = 'scripture-version';
        ver.textContent = ` (${label})`;
        refEl.append(ver);
    }

    function showOverlay(scripture) {
        if (!overlay || !overlayRef || !overlayText) {
            return;
        }
        if (hideOverlayTimer) {
            window.clearTimeout(hideOverlayTimer);
            hideOverlayTimer = null;
        }

        const version = (scripture.version || DEFAULT_VERSION).trim() || DEFAULT_VERSION;
        overlayRef.replaceChildren();
        overlayRef.append(document.createTextNode(scripture.ref));
        const verSpan = document.createElement('span');
        verSpan.className = 'live-scripture-version';
        verSpan.textContent = ` (${version})`;
        overlayRef.append(verSpan);

        overlayText.textContent = `“${scripture.text}”`;

        if (overlayCard) {
            overlayCard.classList.remove('is-expanded');
            overlayCard.setAttribute('aria-expanded', 'false');
        }
        if (overlayHint) {
            const label = overlayHint.querySelector('.expand-label');
            if (label) {
                label.textContent = 'Tap to expand';
            }
        }

        const accBadge = document.getElementById('scripture-accordion-badge');
        if (accBadge) {
            accBadge.textContent = scripture.ref;
            accBadge.classList.remove('hidden');
        }

        if (overlay.hidden || !overlay.classList.contains('is-visible')) {
            overlay.classList.remove('is-leaving');
            overlay.hidden = false;
            void overlay.offsetWidth;
            requestAnimationFrame(() => {
                overlay.classList.add('is-visible');
            });
        } else {
            // Subtle transition on content change
            overlayText.style.transition = 'opacity 120ms ease';
            overlayText.style.opacity = '0.5';
            requestAnimationFrame(() => {
                overlayText.style.opacity = '1';
            });
        }
    }

    function hideOverlay() {
        const accBadge = document.getElementById('scripture-accordion-badge');
        if (accBadge) {
            accBadge.textContent = '';
            accBadge.classList.add('hidden');
        }

        if (!overlay || overlay.hidden) {
            return;
        }

        overlay.classList.remove('is-visible');
        overlay.classList.add('is-leaving');

        if (hideOverlayTimer) {
            window.clearTimeout(hideOverlayTimer);
        }
        hideOverlayTimer = window.setTimeout(() => {
            overlay.hidden = true;
            overlay.classList.remove('is-leaving');
            if (overlayRef) {
                overlayRef.replaceChildren();
            }
            if (overlayText) {
                overlayText.textContent = '';
            }
            hideOverlayTimer = null;
        }, 170);
    }

    function hideAll() {
        hideOverlay();
        if (scriptureSlide) {
            scriptureSlide.hidden = true;
        }
        if (songSlide) {
            songSlide.hidden = true;
        }
        if (ndiSlide) {
            ndiSlide.hidden = true;
        }
        if (scriptureEmpty) {
            scriptureEmpty.classList.remove('hidden');
        }
        if (scriptureDot) {
            scriptureDot.classList.add('hidden');
        }
        art?.classList.remove('has-scripture', 'has-song', 'has-ndi');
        if (scriptureRefEl) {
            scriptureRefEl.replaceChildren();
        }
        if (scriptureTextEl) {
            scriptureTextEl.textContent = '';
        }
        if (songTitleEl) {
            songTitleEl.replaceChildren();
        }
        if (songTextEl) {
            songTextEl.textContent = '';
        }
        if (songMetaEl) {
            songMetaEl.textContent = '';
        }
        if (ndiImage) {
            ndiImage.removeAttribute('src');
        }
        if (ndiSourceEl) {
            ndiSourceEl.textContent = '';
        }
    }

    async function tick() {
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
            if (!data.enabled) {
                return;
            }
            const mode = data.live_board || '';
            const scripture = data.scripture;
            const song = data.song;
            const ndi = data.ndi;
            const key = [
                mode,
                ndi?.image_url || '',
                ndi?.updated_at || '',
                song
                    ? `song:${song.title}\n${song.text}\n${song.slide_index}\n${song.updated_at || ''}`
                    : '',
                scripture
                    ? `scripture:${scripture.ref}\n${scripture.text}\n${scripture.version || ''}\n${scripture.updated_at || ''}`
                    : '',
            ].join('|');
            if (key === lastKey) {
                return;
            }
            lastKey = key;

            if (mode === 'ndi' && ndi?.image_url && ndiSlide && ndiImage) {
                hideOverlay();
                if (scriptureSlide) {
                    scriptureSlide.hidden = true;
                }
                if (songSlide) {
                    songSlide.hidden = true;
                }
                if (ndiImage.getAttribute('src') !== ndi.image_url) {
                    ndiImage.src = ndi.image_url;
                }
                if (ndiSourceEl) {
                    ndiSourceEl.textContent = ndi.source || '';
                }
                ndiSlide.hidden = false;
                art?.classList.add('has-ndi');
                art?.classList.remove('has-scripture', 'has-song');
                notifyActive();
                return;
            }

            if (ndiSlide) {
                ndiSlide.hidden = true;
            }
            art?.classList.remove('has-ndi');

            if (mode === 'song' && song?.title && song?.text && songSlide && songTitleEl && songTextEl) {
                hideOverlay();
                if (scriptureSlide) {
                    scriptureSlide.hidden = true;
                }
                songTitleEl.textContent = song.title;
                songTextEl.textContent = song.text;
                if (songMetaEl) {
                    const n = (song.slide_index ?? 0) + 1;
                    const total = song.slide_count || 1;
                    songMetaEl.textContent = total > 1 ? `${n} / ${total}` : '';
                }
                songSlide.hidden = false;
                art?.classList.add('has-song');
                art?.classList.remove('has-scripture');
                notifyActive();
                return;
            }

            if (songSlide) {
                songSlide.hidden = true;
            }
            art?.classList.remove('has-song');

            if (mode !== 'scripture' || !scripture?.ref || !scripture?.text) {
                hideAll();
                return;
            }

            // Mode is scripture: update both overlay and right-column slide
            showOverlay(scripture);

            if (scriptureSlide && scriptureRefEl && scriptureTextEl) {
                setReference(scriptureRefEl, scripture.ref, scripture.version);
                scriptureTextEl.textContent = scripture.text;
                scriptureSlide.hidden = false;
            }
            art?.classList.add('has-scripture');
            notifyActive();
        } catch {
            /* ignore transient errors */
        } finally {
            timer = window.setTimeout(tick, POLL_MS);
        }
    }

    void tick();

    window.addEventListener('beforeunload', () => {
        if (timer) {
            window.clearTimeout(timer);
        }
        if (hideOverlayTimer) {
            window.clearTimeout(hideOverlayTimer);
        }
    });
}
