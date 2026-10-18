import assert from 'node:assert/strict';
import test from 'node:test';
import {
    STUDIO_THEME_STORAGE_KEY,
    applyStudioTheme,
    bindStudioThemeToggle,
    resolveStudioTheme,
} from '../../resources/js/studio-theme.js';

function createFakeEnvironment(initialStoredTheme = null) {
    const store = new Map();
    if (initialStoredTheme !== null) {
        store.set(STUDIO_THEME_STORAGE_KEY, initialStoredTheme);
    }

    const storage = {
        getItem(key) {
            return store.has(key) ? store.get(key) : null;
        },
        setItem(key, value) {
            store.set(key, String(value));
        },
    };

    const makeButton = (theme) => {
        const attrs = new Map([
            ['data-studio-theme-btn', theme],
            ['aria-pressed', theme === 'light' ? 'true' : 'false'],
        ]);
        const classes = new Set(theme === 'light' ? ['studio-theme-btn', 'is-active'] : ['studio-theme-btn']);
        const listeners = new Map();

        return {
            getAttribute(name) {
                return attrs.get(name) ?? null;
            },
            setAttribute(name, value) {
                attrs.set(name, String(value));
            },
            classList: {
                toggle(cls, force) {
                    if (force) {
                        classes.add(cls);
                    } else {
                        classes.delete(cls);
                    }
                },
                contains(cls) {
                    return classes.has(cls);
                },
            },
            addEventListener(type, fn) {
                listeners.set(type, fn);
            },
            click() {
                listeners.get('click')?.();
            },
        };
    };

    const lightBtn = makeButton('light');
    const darkBtn = makeButton('dark');

    const rootAttrs = new Map();
    const stageAttrs = new Map([['data-studio-theme', 'light']]);

    const stage = {
        faderValue: '100',
        isOnAir: true,
        setAttribute(name, value) {
            stageAttrs.set(name, String(value));
        },
        getAttribute(name) {
            return stageAttrs.get(name) ?? null;
        },
    };

    const doc = {
        documentElement: {
            style: { colorScheme: 'light' },
            setAttribute(name, value) {
                rootAttrs.set(name, String(value));
            },
            getAttribute(name) {
                return rootAttrs.get(name) ?? null;
            },
        },
        getElementById(id) {
            return id === 'studio-stage' ? stage : null;
        },
        querySelectorAll(selector) {
            if (selector === '[data-studio-theme-btn]') {
                return [lightBtn, darkBtn];
            }
            return [];
        },
    };

    return { storage, doc, stage, lightBtn, darkBtn };
}

test('resolveStudioTheme defaults to light when no preference is saved and never falls back to system dark', () => {
    assert.equal(resolveStudioTheme(null), 'light');
    assert.equal(resolveStudioTheme(undefined), 'light');
    assert.equal(resolveStudioTheme(''), 'light');
    assert.equal(resolveStudioTheme('system'), 'light');
    assert.equal(resolveStudioTheme('light'), 'light');
    assert.equal(resolveStudioTheme('dark'), 'dark');
});

test('bindStudioThemeToggle opens in light mode by default when no preference is saved', () => {
    const { storage, doc, stage, lightBtn, darkBtn } = createFakeEnvironment(null);

    const initial = bindStudioThemeToggle({ doc, storage });

    assert.equal(initial, 'light');
    assert.equal(doc.documentElement.getAttribute('data-studio-theme'), 'light');
    assert.equal(doc.documentElement.getAttribute('data-theme'), 'light');
    assert.equal(doc.documentElement.style.colorScheme, 'light');
    assert.equal(stage.getAttribute('data-studio-theme'), 'light');
    assert.equal(lightBtn.getAttribute('aria-pressed'), 'true');
    assert.equal(lightBtn.classList.contains('is-active'), true);
    assert.equal(darkBtn.getAttribute('aria-pressed'), 'false');
    assert.equal(darkBtn.classList.contains('is-active'), false);
    assert.equal(storage.getItem(STUDIO_THEME_STORAGE_KEY), null);
});

test('bindStudioThemeToggle restores dark mode when soundmix-studio-theme is saved as dark', () => {
    const { storage, doc, stage, lightBtn, darkBtn } = createFakeEnvironment('dark');

    const initial = bindStudioThemeToggle({ doc, storage });

    assert.equal(initial, 'dark');
    assert.equal(doc.documentElement.getAttribute('data-studio-theme'), 'dark');
    assert.equal(doc.documentElement.getAttribute('data-theme'), 'dark');
    assert.equal(doc.documentElement.style.colorScheme, 'dark');
    assert.equal(stage.getAttribute('data-studio-theme'), 'dark');
    assert.equal(darkBtn.getAttribute('aria-pressed'), 'true');
    assert.equal(darkBtn.classList.contains('is-active'), true);
    assert.equal(lightBtn.getAttribute('aria-pressed'), 'false');
    assert.equal(lightBtn.classList.contains('is-active'), false);
});

test('clicking theme toggle buttons switches immediately, persists preference, and preserves Studio state', () => {
    const { storage, doc, stage, lightBtn, darkBtn } = createFakeEnvironment(null);
    bindStudioThemeToggle({ doc, storage });

    stage.faderValue = '122';
    stage.isOnAir = true;

    darkBtn.click();

    assert.equal(storage.getItem(STUDIO_THEME_STORAGE_KEY), 'dark');
    assert.equal(doc.documentElement.getAttribute('data-studio-theme'), 'dark');
    assert.equal(stage.getAttribute('data-studio-theme'), 'dark');
    assert.equal(darkBtn.getAttribute('aria-pressed'), 'true');
    assert.equal(lightBtn.getAttribute('aria-pressed'), 'false');
    assert.equal(stage.faderValue, '122');
    assert.equal(stage.isOnAir, true);

    lightBtn.click();

    assert.equal(storage.getItem(STUDIO_THEME_STORAGE_KEY), 'light');
    assert.equal(doc.documentElement.getAttribute('data-studio-theme'), 'light');
    assert.equal(stage.getAttribute('data-studio-theme'), 'light');
    assert.equal(lightBtn.getAttribute('aria-pressed'), 'true');
    assert.equal(darkBtn.getAttribute('aria-pressed'), 'false');
    assert.equal(stage.faderValue, '122');
    assert.equal(stage.isOnAir, true);
});

test('applyStudioTheme normalizes unknown values safely to light', () => {
    const { storage, doc, stage } = createFakeEnvironment('dark');

    const applied = applyStudioTheme('unexpected', { doc, storage, persist: true });

    assert.equal(applied, 'light');
    assert.equal(storage.getItem(STUDIO_THEME_STORAGE_KEY), 'light');
    assert.equal(stage.getAttribute('data-studio-theme'), 'light');
});
