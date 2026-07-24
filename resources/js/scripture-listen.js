const POLL_MS = 5000;
const DEFAULT_VERSION = 'KJV';

/**
 * Poll current scripture and drive the EasyWorship-style portal-art slide.
 */
export function bindScriptureListen(root) {
    const host = root?.dataset?.scriptureUrl
        ? root
        : document.querySelector('[data-scripture-url]');
    const url = host?.dataset?.scriptureUrl;
    if (!url) {
        return;
    }

    const slide = document.getElementById('scripture-slide');
    const refEl = slide?.querySelector('.scripture-ref');
    const textEl = slide?.querySelector('.scripture-text');
    const art = document.querySelector('.portal-art');
    if (!slide || !refEl || !textEl) {
        return;
    }

    let lastKey = '';
    let timer = null;

    function setReference(ref, version) {
        const label = (version || DEFAULT_VERSION).trim() || DEFAULT_VERSION;
        refEl.replaceChildren();
        refEl.append(document.createTextNode(ref));
        const ver = document.createElement('span');
        ver.className = 'scripture-version';
        ver.textContent = ` (${label})`;
        refEl.append(ver);
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
            const key = scripture
                ? `${scripture.ref}\n${scripture.text}\n${scripture.version || ''}\n${scripture.updated_at || ''}`
                : '';
            if (key === lastKey) {
                return;
            }
            lastKey = key;

            if (!scripture?.ref || !scripture?.text) {
                slide.hidden = true;
                art?.classList.remove('has-scripture');
                refEl.replaceChildren();
                textEl.textContent = '';
                return;
            }

            setReference(scripture.ref, scripture.version);
            textEl.textContent = scripture.text;
            slide.hidden = false;
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
