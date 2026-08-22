/**
 * Church Studio: cue song / announcement slides on the listen portal.
 */

export function bindSongStudio(root) {
    if (!root || root.dataset.scriptureEnabled !== '1') {
        return;
    }

    const panel = document.getElementById('studio-songs');
    if (!panel) {
        return;
    }

    const listEl = document.getElementById('song-list');
    const formEl = document.getElementById('song-form');
    const formPreviewEl = document.getElementById('song-form-preview');
    const titleInput = document.getElementById('song-title');
    const bodyInput = document.getElementById('song-body');
    const statusEl = document.getElementById('song-status');
    const btnNew = document.getElementById('btn-song-new');
    const btnSave = document.getElementById('btn-song-save');
    const btnCancel = document.getElementById('btn-song-cancel');
    const btnClear = document.getElementById('btn-song-clear');
    const btnPrev = document.getElementById('btn-song-prev');
    const btnNext = document.getElementById('btn-song-next');
    const liveWrap = document.getElementById('song-live');
    const liveEl = document.getElementById('song-live-meta');
    const livePreviewEl = document.getElementById('song-live-preview');

    const indexUrl = root.dataset.songsIndexUrl || '';
    const storeUrl = root.dataset.songsStoreUrl || '';
    const clearUrl = root.dataset.songsCueClearUrl || '';
    const nextUrl = root.dataset.songsCueNextUrl || '';
    const previousUrl = root.dataset.songsCuePreviousUrl || '';

    /** @type {Array<{id:number,title:string,slides:string[],slide_count:number,update_url?:string,destroy_url?:string,cue_url?:string}>} */
    let songs = [];
    /** @type {{id:number|null,title:string,text:string,slide_index:number,slide_count:number}|null} */
    let cue = null;
    /** @type {number|null} */
    let editingId = null;

    function csrf() {
        return root.dataset.csrf || '';
    }

    function setStatus(msg) {
        if (statusEl) {
            statusEl.textContent = msg;
        }
    }

    function slidesFromBody(body) {
        return String(body || '')
            .split(/\n\s*\n/)
            .map((part) => part.trim())
            .filter(Boolean);
    }

    function slidesFor(song) {
        const slides = Array.isArray(song?.slides) ? song.slides.filter((s) => String(s || '').trim()) : [];
        if (slides.length) {
            return slides;
        }
        if (cue?.id === song?.id && cue.text) {
            return [cue.text];
        }
        return [];
    }

    function applyLocalCue(song, index) {
        const slides = slidesFor(song);
        if (!slides.length) {
            return;
        }
        const i = Math.max(0, Math.min(index, slides.length - 1));
        cue = {
            id: song.id,
            title: song.title,
            text: slides[i] || '',
            slide_index: i,
            slide_count: slides.length,
        };
        renderList();
        setLiveMeta();
        renderFormPreview();
    }

    function setLiveMeta() {
        if (!cue) {
            liveEl && (liveEl.textContent = '');
            livePreviewEl && (livePreviewEl.textContent = '');
            liveWrap?.setAttribute('hidden', '');
            liveEl?.setAttribute('hidden', '');
            livePreviewEl?.setAttribute('hidden', '');
            return;
        }
        const n = (cue.slide_index ?? 0) + 1;
        const total = cue.slide_count || 1;
        if (liveEl) {
            liveEl.textContent = `Live: ${cue.title} · slide ${n}/${total}`;
            liveEl.removeAttribute('hidden');
        }
        if (livePreviewEl) {
            livePreviewEl.textContent = cue.text || '';
            if (cue.text) {
                livePreviewEl.removeAttribute('hidden');
            } else {
                livePreviewEl.setAttribute('hidden', '');
            }
        }
        liveWrap?.removeAttribute('hidden');
    }

    function renderSlideList(song, { interactive = true } = {}) {
        const slides = slidesFor(song);
        if (!slides.length) {
            return '';
        }
        const live = cue?.id === song.id;
        const active = live ? (cue.slide_index ?? 0) : -1;
        return (
            `<ol class="song-slide-list">` +
            slides
                .map((text, i) => {
                    const cls = i === active ? ' is-active' : '';
                    const action = interactive ? ` data-action="cue-slide" data-slide-index="${i}"` : '';
                    const tag = interactive ? 'button' : 'div';
                    const type = interactive ? ' type="button"' : '';
                    return (
                        `<li>` +
                        `<${tag} class="song-slide-excerpt${cls}"${type}${action}>` +
                        `<span class="song-slide-n">${i + 1}</span>` +
                        `<span class="song-slide-body">${escapeHtml(text)}</span>` +
                        `</${tag}>` +
                        `</li>`
                    );
                })
                .join('') +
            `</ol>`
        );
    }

    function renderFormPreview() {
        if (!formPreviewEl) {
            return;
        }
        const slides = slidesFromBody(bodyInput?.value || '');
        if (!slides.length) {
            formPreviewEl.innerHTML = '';
            formPreviewEl.setAttribute('hidden', '');
            return;
        }
        formPreviewEl.innerHTML = renderSlideList({ id: editingId, slides }, { interactive: false });
        formPreviewEl.removeAttribute('hidden');
    }

    function hideForm() {
        editingId = null;
        formEl?.setAttribute('hidden', '');
        if (titleInput) {
            titleInput.value = '';
        }
        if (bodyInput) {
            bodyInput.value = '';
        }
        if (formPreviewEl) {
            formPreviewEl.innerHTML = '';
            formPreviewEl.setAttribute('hidden', '');
        }
    }

    function showForm(song = null) {
        editingId = song?.id ?? null;
        if (titleInput) {
            titleInput.value = song?.title || '';
        }
        if (bodyInput) {
            bodyInput.value = (song?.slides || []).join('\n\n');
        }
        formEl?.removeAttribute('hidden');
        renderFormPreview();
        titleInput?.focus();
    }

    async function api(url, options = {}) {
        const { headers: extraHeaders, ...rest } = options;
        const res = await fetch(url, {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf(),
                ...(extraHeaders || {}),
            },
            ...rest,
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            throw new Error(data.message || 'Request failed');
        }
        return data;
    }

    function renderList() {
        if (!listEl) {
            return;
        }
        if (!songs.length) {
            listEl.innerHTML = '<p class="mixer-hint">No songs yet</p>';
            return;
        }
        listEl.innerHTML = songs
            .map((s) => {
                const live = cue?.id === s.id ? ' is-live' : '';
                const count = s.slide_count || (s.slides || []).length || 0;
                return (
                    `<div class="song-list-item${live}" data-song-id="${s.id}">` +
                    `<div class="song-list-main">` +
                    `<strong>${escapeHtml(s.title)}</strong>` +
                    `<span>${count} slide${count === 1 ? '' : 's'}</span>` +
                    `<div class="song-list-actions">` +
                    `<button type="button" class="song-action song-action--cue" data-action="cue">Go live</button>` +
                    `<button type="button" class="song-action song-action--edit" data-action="edit">Edit</button>` +
                    `<button type="button" class="song-action song-action--delete" data-action="delete">Delete</button>` +
                    `</div>` +
                    `</div>` +
                    renderSlideList(s) +
                    `</div>`
                );
            })
            .join('');
        const active = listEl.querySelector('.song-slide-excerpt.is-active');
        const scroller = active?.closest('.song-slide-list');
        if (active instanceof HTMLElement && scroller instanceof HTMLElement) {
            const top = active.offsetTop - scroller.clientHeight / 2 + active.offsetHeight / 2;
            scroller.scrollTop = Math.max(0, top);
        }
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    async function refresh() {
        if (!indexUrl) {
            return;
        }
        try {
            const data = await api(indexUrl, { method: 'GET', headers: { Accept: 'application/json' } });
            songs = data.songs || [];
            cue = data.cue || null;
            renderList();
            setLiveMeta();
            renderFormPreview();
            if (cue) {
                setStatus(`Showing “${cue.title}” on listen`);
            } else if (!statusEl?.textContent || statusEl.textContent.startsWith('Showing')) {
                setStatus('No song on listen');
            }
        } catch (err) {
            setStatus(err instanceof Error ? err.message : 'Could not load songs.');
        }
    }

    async function save() {
        const title = (titleInput?.value || '').trim();
        const body = (bodyInput?.value || '').trim();
        if (!title || !body) {
            setStatus('Title and slides are required.');
            return;
        }
        try {
            if (editingId != null) {
                const song = songs.find((s) => s.id === editingId);
                if (!song?.update_url) {
                    setStatus('Missing update URL for that song.');
                    return;
                }
                await api(song.update_url, {
                    method: 'PUT',
                    body: JSON.stringify({ title, body }),
                });
                setStatus('Song updated');
            } else {
                if (!storeUrl) {
                    return;
                }
                await api(storeUrl, {
                    method: 'POST',
                    body: JSON.stringify({ title, body }),
                });
                setStatus('Song saved');
            }
            hideForm();
            await refresh();
        } catch (err) {
            setStatus(err instanceof Error ? err.message : 'Could not save song.');
        }
    }

    async function cueSong(song, slideIndex = 0) {
        if (!song?.cue_url) {
            return;
        }
        applyLocalCue(song, slideIndex);
        setStatus(`Showing “${song.title}” on listen`);
        try {
            const data = await api(song.cue_url, {
                method: 'POST',
                body: JSON.stringify({ slide_index: slideIndex }),
            });
            cue = data.song || cue;
            renderList();
            setLiveMeta();
            renderFormPreview();
            setStatus(cue ? `Showing “${cue.title}” on listen` : 'Song cued');
            window.dispatchEvent(new CustomEvent('live-board-changed', {
                detail: { mode: 'song', song: cue },
            }));
        } catch (err) {
            setStatus(err instanceof Error ? err.message : 'Could not cue song.');
            await refresh();
        }
    }

    async function clearCue() {
        if (!clearUrl) {
            return;
        }
        try {
            await api(clearUrl, { method: 'DELETE' });
            cue = null;
            renderList();
            setLiveMeta();
            renderFormPreview();
            setStatus('Song cleared from listen');
            window.dispatchEvent(new CustomEvent('live-board-changed', {
                detail: { mode: null },
            }));
        } catch (err) {
            setStatus(err instanceof Error ? err.message : 'Could not clear song.');
        }
    }

    async function nudge(url, delta) {
        if (!url || !cue) {
            return;
        }
        const song = songs.find((s) => s.id === cue.id);
        const from = cue.slide_index ?? 0;
        if (song) {
            applyLocalCue(song, from + delta);
            setStatus(`Showing “${cue.title}” · slide ${(cue.slide_index ?? 0) + 1}/${cue.slide_count || 1}`);
        }
        try {
            const data = await api(url, { method: 'POST', body: '{}' });
            cue = data.song || null;
            renderList();
            setLiveMeta();
            renderFormPreview();
            if (cue) {
                setStatus(`Showing “${cue.title}” · slide ${(cue.slide_index ?? 0) + 1}/${cue.slide_count || 1}`);
            }
        } catch (err) {
            setStatus(err instanceof Error ? err.message : 'Could not change slide.');
            await refresh();
        }
    }

    listEl?.addEventListener('click', (ev) => {
        const btn = ev.target.closest('[data-action]');
        const row = ev.target.closest('[data-song-id]');
        if (!btn || !row || !(btn instanceof HTMLElement) || !(row instanceof HTMLElement)) {
            return;
        }
        const id = Number(row.dataset.songId);
        const song = songs.find((s) => s.id === id);
        if (!song) {
            return;
        }
        const action = btn.dataset.action;
        if (action === 'cue') {
            void cueSong(song, 0);
        } else if (action === 'cue-slide') {
            void cueSong(song, Number(btn.dataset.slideIndex || 0));
        } else if (action === 'edit') {
            showForm(song);
        } else if (action === 'delete') {
            if (!song.destroy_url) {
                return;
            }
            if (!window.confirm(`Delete “${song.title}”?`)) {
                return;
            }
            void api(song.destroy_url, { method: 'DELETE' })
                .then(() => {
                    setStatus('Song deleted');
                    return refresh();
                })
                .catch((err) => {
                    setStatus(err instanceof Error ? err.message : 'Could not delete song.');
                });
        }
    });

    btnNew?.addEventListener('click', () => showForm());
    btnCancel?.addEventListener('click', () => hideForm());
    btnSave?.addEventListener('click', () => void save());
    btnClear?.addEventListener('click', () => void clearCue());
    btnPrev?.addEventListener('click', () => void nudge(previousUrl, -1));
    btnNext?.addEventListener('click', () => void nudge(nextUrl, +1));
    bodyInput?.addEventListener('input', () => renderFormPreview());

    window.addEventListener('live-board-changed', (ev) => {
        const mode = ev?.detail?.mode;
        if (mode !== 'scripture') {
            return;
        }
        cue = null;
        renderList();
        setLiveMeta();
        renderFormPreview();
        setStatus('No song on listen');
    });

    void refresh();
}
