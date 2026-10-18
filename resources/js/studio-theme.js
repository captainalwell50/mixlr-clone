export const STUDIO_THEME_STORAGE_KEY = 'soundmix-studio-theme';

/**
 * Resolve the Studio theme with strict precedence:
 * 1. Explicit saved Studio preference ('dark' | 'light')
 * 2. Otherwise default to 'light' (never system preference)
 *
 * @param {string | null | undefined} storedValue
 * @returns {'light' | 'dark'}
 */
export function resolveStudioTheme(storedValue) {
    return storedValue === 'dark' ? 'dark' : 'light';
}

/**
 * Apply the resolved Studio theme to the document and Studio stage without reloading
 * or disturbing audio/broadcast state.
 *
 * @param {string | null | undefined} theme
 * @param {{ doc?: Document, storage?: Storage | null, persist?: boolean }} [options]
 * @returns {'light' | 'dark'}
 */
export function applyStudioTheme(theme, options = {}) {
    const next = resolveStudioTheme(theme);
    const doc = options.doc ?? (typeof document !== 'undefined' ? document : null);
    const storage = options.storage !== undefined
        ? options.storage
        : (typeof window !== 'undefined' ? window.localStorage : null);
    const persist = Boolean(options.persist);

    if (persist && storage) {
        try {
            storage.setItem(STUDIO_THEME_STORAGE_KEY, next);
        } catch {
            /* ignore storage quota / privacy mode errors */
        }
    }

    if (doc?.documentElement) {
        doc.documentElement.setAttribute('data-studio-theme', next);
        doc.documentElement.setAttribute('data-theme', next);
        if (doc.documentElement.style) {
            doc.documentElement.style.colorScheme = next;
        }
    }

    const stage = doc?.getElementById?.('studio-stage');
    if (stage) {
        stage.setAttribute('data-studio-theme', next);
    }

    const buttons = doc?.querySelectorAll?.('[data-studio-theme-btn]') || [];
    buttons.forEach((btn) => {
        const active = btn.getAttribute('data-studio-theme-btn') === next;
        btn.classList.toggle('is-active', active);
        btn.setAttribute('aria-pressed', active ? 'true' : 'false');
    });

    return next;
}

/**
 * Initialize and bind the Studio theme toggle controls.
 *
 * @param {{ doc?: Document, storage?: Storage | null }} [options]
 * @returns {'light' | 'dark'}
 */
export function bindStudioThemeToggle(options = {}) {
    const doc = options.doc ?? (typeof document !== 'undefined' ? document : null);
    const storage = options.storage !== undefined
        ? options.storage
        : (typeof window !== 'undefined' ? window.localStorage : null);

    let stored = null;
    if (storage) {
        try {
            stored = storage.getItem(STUDIO_THEME_STORAGE_KEY);
        } catch {
            stored = null;
        }
    }

    const initial = applyStudioTheme(resolveStudioTheme(stored), {
        doc,
        storage,
        persist: false,
    });

    const buttons = doc?.querySelectorAll?.('[data-studio-theme-btn]') || [];
    buttons.forEach((btn) => {
        btn.addEventListener('click', () => {
            const targetTheme = btn.getAttribute('data-studio-theme-btn');
            applyStudioTheme(targetTheme, { doc, storage, persist: true });
        });
    });

    return initial;
}
