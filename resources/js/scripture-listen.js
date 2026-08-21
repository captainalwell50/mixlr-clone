// Keep tight while live — scripture/song are poll-only (no websocket). Rate limit: listen-poll 900/min.
const POLL_MS = 1500;
const DEFAULT_VERSION = 'KJV';

/**
 * Poll current scripture / song and drive the EasyWorship-style portal-art slide.
 * Song cue takes precedence when both are live.
 */
export function bindScriptureListen(root) {
    const host = root?.dataset?.scriptureUrl
        ? root
        : document.querySelector('[data-scripture-url]');
    const url = host?.dataset?.scriptureUrl;
    if (!url) {
        return;
    }

    const scriptureSlide = document.getElementById('scripture-slide');
    const songSlide = document.getElementById('song-slide');
    const scriptureRefEl = scriptureSlide?.querySelector('.scripture-ref');
    const scriptureTextEl = scriptureSlide?.querySelector('.scripture-text');
    const songTitleEl = songSlide?.querySelector('.scripture-ref');
    const songTextEl = songSlide?.querySelector('.scripture-text');
    const songMetaEl = songSlide?.querySelector('.song-slide-meta');
    const art = document.querySelector('.portal-art');
    if (!scriptureSlide || !scriptureRefEl || !scriptureTextEl) {
        return;
    }

    let lastKey = '';
    let timer = null;

    function setReference(refEl, ref, version) {
        const label = (version || DEFAULT_VERSION).trim() || DEFAULT_VERSION;
        refEl.replaceChildren();
        refEl.append(document.createTextNode(ref));
        const ver = document.createElement('span');
        ver.className = 'scripture-version';
        ver.textContent = ` (${label})`;
        refEl.append(ver);
    }

    function hideAll() {
        scriptureSlide.hidden = true;
        if (songSlide) {
            songSlide.hidden = true;
        }
        art?.classList.remove('has-scripture', 'has-song');
        scriptureRefEl.replaceChildren();
        scriptureTextEl.textContent = '';
        if (songTitleEl) {
            songTitleEl.replaceChildren();
        }
        if (songTextEl) {
            songTextEl.textContent = '';
        }
        if (songMetaEl) {
            songMetaEl.textContent = '';
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
            const scripture = data.scripture;
            const song = data.song;
            const key = [
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

            if (song?.title && song?.text && songSlide && songTitleEl && songTextEl) {
                scriptureSlide.hidden = true;
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
                return;
            }

            if (songSlide) {
                songSlide.hidden = true;
            }
            art?.classList.remove('has-song');

            if (!scripture?.ref || !scripture?.text) {
                hideAll();
                return;
            }

            setReference(scriptureRefEl, scripture.ref, scripture.version);
            scriptureTextEl.textContent = scripture.text;
            scriptureSlide.hidden = false;
            art?.classList.add('has-scripture');
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
    });
}
