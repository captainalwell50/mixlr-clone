/**
 * Church Studio: cue KJV scripture on the listen portal (manual + speech).
 */

const WORDS = {
    zero: 0, oh: 0, o: 0, one: 1, two: 2, three: 3, four: 4, five: 5, six: 6, seven: 7, eight: 8, nine: 9,
    ten: 10, eleven: 11, twelve: 12, thirteen: 13, fourteen: 14, fifteen: 15, sixteen: 16,
    seventeen: 17, eighteen: 18, nineteen: 19, twenty: 20, thirty: 30, forty: 40, fifty: 50,
    sixty: 60, seventy: 70, eighty: 80, ninety: 90,
};

const BOOK_SPOKEN = [
    ['first corinthians', '1 Corinthians'],
    ['second corinthians', '2 Corinthians'],
    ['first thessalonians', '1 Thessalonians'],
    ['second thessalonians', '2 Thessalonians'],
    ['first timothy', '1 Timothy'],
    ['second timothy', '2 Timothy'],
    ['first samuel', '1 Samuel'],
    ['second samuel', '2 Samuel'],
    ['first kings', '1 Kings'],
    ['second kings', '2 Kings'],
    ['first chronicles', '1 Chronicles'],
    ['second chronicles', '2 Chronicles'],
    ['first peter', '1 Peter'],
    ['second peter', '2 Peter'],
    ['first john', '1 John'],
    ['second john', '2 John'],
    ['third john', '3 John'],
    ['song of solomon', 'Song of Solomon'],
    ['song of songs', 'Song of Solomon'],
    ['genesis', 'Genesis'],
    ['exodus', 'Exodus'],
    ['leviticus', 'Leviticus'],
    ['numbers', 'Numbers'],
    ['deuteronomy', 'Deuteronomy'],
    ['joshua', 'Joshua'],
    ['judges', 'Judges'],
    ['ruth', 'Ruth'],
    ['ezra', 'Ezra'],
    ['nehemiah', 'Nehemiah'],
    ['esther', 'Esther'],
    ['job', 'Job'],
    ['psalms', 'Psalms'],
    ['psalm', 'Psalms'],
    ['proverbs', 'Proverbs'],
    ['ecclesiastes', 'Ecclesiastes'],
    ['isaiah', 'Isaiah'],
    ['jeremiah', 'Jeremiah'],
    ['lamentations', 'Lamentations'],
    ['ezekiel', 'Ezekiel'],
    ['daniel', 'Daniel'],
    ['hosea', 'Hosea'],
    ['joel', 'Joel'],
    ['amos', 'Amos'],
    ['obadiah', 'Obadiah'],
    ['jonah', 'Jonah'],
    ['micah', 'Micah'],
    ['nahum', 'Nahum'],
    ['habakkuk', 'Habakkuk'],
    ['zephaniah', 'Zephaniah'],
    ['haggai', 'Haggai'],
    ['zechariah', 'Zechariah'],
    ['malachi', 'Malachi'],
    ['matthew', 'Matthew'],
    ['mark', 'Mark'],
    ['luke', 'Luke'],
    ['john', 'John'],
    ['acts', 'Acts'],
    ['romans', 'Romans'],
    ['galatians', 'Galatians'],
    ['ephesians', 'Ephesians'],
    ['philippians', 'Philippians'],
    ['colossians', 'Colossians'],
    ['titus', 'Titus'],
    ['philemon', 'Philemon'],
    ['hebrews', 'Hebrews'],
    ['james', 'James'],
    ['jude', 'Jude'],
    ['revelation', 'Revelation'],
    ['revelations', 'Revelation'],
];

function parseSpokenNumber(tokens) {
    if (!tokens.length) {
        return null;
    }
    let total = 0;
    let current = 0;
    for (const t of tokens) {
        const w = String(t).toLowerCase();
        if (/^\d+$/.test(w)) {
            if (current !== 0) {
                total += current;
                current = 0;
            }
            total += Number(w);
            continue;
        }
        if (!(w in WORDS)) {
            return null;
        }
        const n = WORDS[w];
        if (n >= 20 && n % 10 === 0) {
            current += n;
        } else {
            current += n;
            total += current;
            current = 0;
        }
    }
    total += current;
    return total > 0 ? total : null;
}

/**
 * @param {string} transcript
 * @returns {string|null} e.g. "John 3:16"
 */
export function parseSpokenReference(transcript) {
    let text = ` ${String(transcript).toLowerCase()} `;
    // STT often returns "John 3:16" — split chapter:verse before tokenization.
    text = text.replace(/[.:;,\-–—/\\!?…]/g, ' ');
    text = text
        .replace(/\bturn with me to\b/g, ' ')
        .replace(/\bplease open\b/g, ' ')
        .replace(/\bopen your bibles? to\b/g, ' ')
        .replace(/\bin the book of\b/g, ' ')
        .replace(/\bchapters?\b/g, ' ')
        .replace(/\bverses?\b/g, ' ')
        .replace(/\band\b/g, ' ')
        .replace(/\bthrough\b/g, ' ')
        .replace(/\bto\b/g, ' ');

    text = text.replace(
        /\b(1|2|3|i|ii|iii|one|two|three|first|second|third)\s+(john|peter|corinthians|thessalonians|timothy|samuel|kings|chronicles)\b/g,
        (_, ordRaw, book) => {
            const map = {
                1: 'first', i: 'first', one: 'first', first: 'first',
                2: 'second', ii: 'second', two: 'second', second: 'second',
                3: 'third', iii: 'third', three: 'third', third: 'third',
            };
            return ` ${map[ordRaw] || ordRaw} ${book} `;
        },
    );
    text = text.replace(/\s+/g, ' ');

    let book = null;
    let rest = text;
    for (const [spoken, canonical] of BOOK_SPOKEN) {
        const idx = text.indexOf(` ${spoken} `);
        if (idx === -1) {
            continue;
        }
        book = canonical;
        rest = text.slice(idx + spoken.length + 2);
        break;
    }
    if (!book) {
        return null;
    }

    const tokens = rest.trim().split(/\s+/).filter(Boolean);
    if (!tokens.length) {
        return null;
    }

    const nums = [];
    let i = 0;
    while (i < tokens.length && nums.length < 3) {
        if (/^\d+$/.test(tokens[i])) {
            nums.push(Number(tokens[i]));
            i += 1;
            continue;
        }
        const one = parseSpokenNumber([tokens[i]]);
        if (one == null) {
            i += 1;
            continue;
        }
        if (i + 1 < tokens.length && tokens[i + 1] in WORDS) {
            const tens = WORDS[tokens[i]];
            if (tens != null && tens >= 20) {
                const two = parseSpokenNumber([tokens[i], tokens[i + 1]]);
                if (two != null) {
                    nums.push(two);
                    i += 2;
                    continue;
                }
            }
        }
        nums.push(one);
        i += 1;
    }

    if (nums.length < 2) {
        return null;
    }
    const chapter = nums[0];
    const verse = nums[1];
    if (nums.length >= 3 && nums[2] !== verse) {
        return `${book} ${chapter}:${verse}-${nums[2]}`;
    }
    return `${book} ${chapter}:${verse}`;
}

export function bindScriptureStudio(root) {
    if (!root || root.dataset.scriptureEnabled !== '1') {
        return;
    }

    const panel = document.getElementById('studio-scripture');
    if (!panel) {
        return;
    }

    const input = document.getElementById('scripture-search');
    const suggestEl = document.getElementById('scripture-suggestions');
    const statusEl = document.getElementById('scripture-status');
    const btnShow = document.getElementById('btn-scripture-show');
    const btnClear = document.getElementById('btn-scripture-clear');
    const btnListen = document.getElementById('btn-scripture-listen');
    const liveEl = document.getElementById('scripture-live');
    const liveLabelEl = liveEl?.querySelector('.scripture-live-label') || null;
    const liveTextEl = document.getElementById('scripture-live-text');
    const confirmEl = document.getElementById('scripture-confirm');
    const confirmRefEl = document.getElementById('scripture-confirm-ref');
    const btnConfirmYes = document.getElementById('btn-scripture-confirm');
    const btnConfirmNo = document.getElementById('btn-scripture-dismiss');

    const storeUrl = root.dataset.scriptureStoreUrl || '';
    const destroyUrl = root.dataset.scriptureDestroyUrl || '';
    const suggestUrl = root.dataset.scriptureSuggestUrl || '';
    const showUrl = root.dataset.scriptureShowUrl || '';

    let selectedRef = '';
    let recognition = null;
    let listening = false;
    let confirmTimer = null;
    let pendingRef = '';
    /** Finalized phrases kept across Chrome's silent restart cycle. */
    let sessionFinals = '';
    /** Last non-empty display line (interim or final) — survive no-speech restarts. */
    let lastHeardLine = '';
    let lastHeardWasFinal = false;
    let gotResultOnce = false;
    let noSpeechStreak = 0;
    let watchdogTimer = null;
    let audioStarted = false;

    function setStatus(msg) {
        if (statusEl) {
            statusEl.textContent = msg;
        }
    }

    function clearLiveTranscript() {
        sessionFinals = '';
        lastHeardLine = '';
        lastHeardWasFinal = false;
        gotResultOnce = false;
        noSpeechStreak = 0;
        audioStarted = false;
        if (watchdogTimer) {
            window.clearTimeout(watchdogTimer);
            watchdogTimer = null;
        }
        if (liveEl) {
            liveEl.setAttribute('hidden', '');
        }
        if (liveLabelEl) {
            liveLabelEl.textContent = 'Live transcript';
        }
        if (liveTextEl) {
            liveTextEl.textContent = 'Listening…';
            liveTextEl.classList.remove('scripture-live-text--interim');
            liveTextEl.classList.add('scripture-live-text--idle');
        }
    }

    function showLiveIdle() {
        if (!liveEl || !liveTextEl) {
            return;
        }
        // Prefer keeping the last heard line across silent restarts.
        if (lastHeardLine) {
            setLiveTranscript(lastHeardLine, { final: lastHeardWasFinal });
            return;
        }
        liveEl.removeAttribute('hidden');
        if (liveLabelEl) {
            liveLabelEl.textContent = 'Live transcript';
        }
        liveTextEl.textContent = 'Listening…';
        liveTextEl.classList.remove('scripture-live-text--interim');
        liveTextEl.classList.add('scripture-live-text--idle');
    }

    /**
     * @param {string} text
     * @param {{ final?: boolean }} [opts]
     */
    function setLiveTranscript(text, opts = {}) {
        if (!liveEl || !liveTextEl) {
            return;
        }
        const trimmed = String(text || '').trim();
        liveEl.removeAttribute('hidden');
        if (!trimmed) {
            if (!lastHeardLine) {
                liveEl.removeAttribute('hidden');
                if (liveLabelEl) {
                    liveLabelEl.textContent = 'Live transcript';
                }
                liveTextEl.textContent = 'Listening…';
                liveTextEl.classList.remove('scripture-live-text--interim');
                liveTextEl.classList.add('scripture-live-text--idle');
            }
            return;
        }
        lastHeardLine = trimmed;
        lastHeardWasFinal = Boolean(opts.final);
        if (liveLabelEl) {
            liveLabelEl.textContent = 'Hearing…';
        }
        liveTextEl.textContent = trimmed;
        liveTextEl.classList.remove('scripture-live-text--idle');
        if (opts.final) {
            liveTextEl.classList.remove('scripture-live-text--interim');
        } else {
            liveTextEl.classList.add('scripture-live-text--interim');
        }
    }

    function armWatchdog() {
        if (watchdogTimer) {
            window.clearTimeout(watchdogTimer);
        }
        watchdogTimer = window.setTimeout(() => {
            watchdogTimer = null;
            if (!listening || gotResultOnce) {
                return;
            }
            const micBusyHint = document.getElementById('btn-enable-mic')
                ? ' If the Studio mic is already open for broadcast, Chrome may not share it with speech recognition — try typing a reference, or briefly stop the mic.'
                : '';
            setStatus(
                (audioStarted
                    ? 'Speech engine heard sound but returned no words.'
                    : 'Speech recognition is not returning any words.') +
                    ' Check the mic isn’t muted, use Chrome, and allow microphone access.' +
                    micBusyHint,
            );
            if (liveTextEl && !lastHeardLine) {
                liveEl?.removeAttribute('hidden');
                if (liveLabelEl) {
                    liveLabelEl.textContent = 'Live transcript';
                }
                liveTextEl.textContent = 'No speech detected yet…';
                liveTextEl.classList.remove('scripture-live-text--interim');
                liveTextEl.classList.add('scripture-live-text--idle');
            }
        }, 12000);
    }

    function hideConfirm() {
        if (confirmTimer) {
            window.clearTimeout(confirmTimer);
            confirmTimer = null;
        }
        pendingRef = '';
        confirmEl?.setAttribute('hidden', '');
    }

    function showConfirm(ref) {
        // Same ref already pending — do not reset the auto-cue timer (STT often re-emits).
        if (ref === pendingRef && confirmTimer) {
            if (confirmRefEl) {
                confirmRefEl.textContent = ref;
            }
            confirmEl?.removeAttribute('hidden');
            return;
        }
        pendingRef = ref;
        if (confirmRefEl) {
            confirmRefEl.textContent = ref;
        }
        confirmEl?.removeAttribute('hidden');
        if (confirmTimer) {
            window.clearTimeout(confirmTimer);
        }
        // Brief dismiss window; keep short so listen clients see the cue quickly.
        confirmTimer = window.setTimeout(() => {
            void cue(ref);
            hideConfirm();
        }, 1200);
    }

    async function cue(ref) {
        if (!storeUrl || !ref) {
            return;
        }
        btnShow && (btnShow.disabled = true);
        try {
            const res = await fetch(storeUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': root.dataset.csrf || '',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ ref }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                setStatus(data.message || 'Could not show that verse.');
                return;
            }
            selectedRef = data.scripture?.ref || ref;
            if (input) {
                input.value = selectedRef;
            }
            setStatus(`Showing ${selectedRef} on listen`);
        } catch {
            setStatus('Could not show that verse.');
        } finally {
            if (btnShow) {
                btnShow.disabled = false;
            }
        }
    }

    async function clear() {
        if (!destroyUrl) {
            return;
        }
        try {
            await fetch(destroyUrl, {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': root.dataset.csrf || '',
                },
                credentials: 'same-origin',
            });
            selectedRef = '';
            setStatus('Scripture cleared from listen');
        } catch {
            setStatus('Could not clear scripture.');
        }
    }

    let suggestTimer = null;
    let activeSuggestIndex = -1;

    function suggestItems() {
        return suggestEl
            ? Array.from(suggestEl.querySelectorAll('.scripture-suggest-item'))
            : [];
    }

    function setActiveSuggest(index) {
        const items = suggestItems();
        activeSuggestIndex = index;
        items.forEach((el, i) => {
            el.classList.toggle('is-active', i === index);
            if (i === index) {
                el.setAttribute('aria-selected', 'true');
            } else {
                el.removeAttribute('aria-selected');
            }
        });
        if (index >= 0 && items[index]) {
            items[index].scrollIntoView({ block: 'nearest' });
        }
    }

    function clearSuggestions() {
        if (suggestEl) {
            suggestEl.innerHTML = '';
        }
        activeSuggestIndex = -1;
    }

    function applySuggestion(ref, { cueNow = true } = {}) {
        if (!ref) {
            return;
        }
        if (input) {
            input.value = ref;
        }
        selectedRef = ref;
        clearSuggestions();
        if (cueNow) {
            void cue(ref);
        }
    }

    async function runSuggest(q) {
        if (!suggestUrl || !suggestEl) {
            return;
        }
        if (!q.trim()) {
            clearSuggestions();
            return;
        }
        try {
            const url = new URL(suggestUrl, window.location.origin);
            url.searchParams.set('q', q);
            const res = await fetch(url.toString(), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            const data = await res.json();
            const items = data.suggestions || [];
            activeSuggestIndex = -1;
            suggestEl.innerHTML = items
                .map(
                    (s, i) =>
                        `<button type="button" class="scripture-suggest-item" role="option" id="scripture-suggest-${i}" data-ref="${s.ref.replace(/"/g, '&quot;')}">` +
                        `<strong>${s.ref}</strong><span>${s.preview || ''}</span></button>`,
                )
                .join('');
        } catch {
            /* ignore */
        }
    }

    input?.addEventListener('input', () => {
        selectedRef = input.value.trim();
        if (suggestTimer) {
            window.clearTimeout(suggestTimer);
        }
        suggestTimer = window.setTimeout(() => void runSuggest(input.value), 250);
    });

    input?.addEventListener('keydown', (ev) => {
        const items = suggestItems();
        if (ev.key === 'ArrowDown' && items.length) {
            ev.preventDefault();
            const next = activeSuggestIndex < items.length - 1 ? activeSuggestIndex + 1 : 0;
            setActiveSuggest(next);
            return;
        }
        if (ev.key === 'ArrowUp' && items.length) {
            ev.preventDefault();
            const next = activeSuggestIndex > 0 ? activeSuggestIndex - 1 : items.length - 1;
            setActiveSuggest(next);
            return;
        }
        if (ev.key === 'Escape') {
            if (items.length) {
                ev.preventDefault();
                clearSuggestions();
            }
            return;
        }
        if (ev.key === 'Enter') {
            ev.preventDefault();
            if (activeSuggestIndex >= 0 && items[activeSuggestIndex]) {
                const ref = items[activeSuggestIndex].dataset.ref || '';
                applySuggestion(ref, { cueNow: true });
                return;
            }
            void cue(input.value.trim());
        }
        if (ev.key === 'Tab' && activeSuggestIndex >= 0 && items[activeSuggestIndex]) {
            ev.preventDefault();
            const ref = items[activeSuggestIndex].dataset.ref || '';
            applySuggestion(ref, { cueNow: false });
        }
    });

    suggestEl?.addEventListener('click', (ev) => {
        const btn = ev.target.closest('[data-ref]');
        if (!btn || !(btn instanceof HTMLElement)) {
            return;
        }
        applySuggestion(btn.dataset.ref || '', { cueNow: true });
    });

    document.addEventListener('click', (ev) => {
        if (!suggestEl || !input) {
            return;
        }
        const target = ev.target;
        if (!(target instanceof Node)) {
            return;
        }
        if (suggestEl.contains(target) || input.contains(target)) {
            return;
        }
        clearSuggestions();
    });

    btnShow?.addEventListener('click', () => void cue((input?.value || selectedRef).trim()));
    btnClear?.addEventListener('click', () => void clear());
    btnConfirmYes?.addEventListener('click', () => {
        const ref = pendingRef;
        hideConfirm();
        if (ref) {
            void cue(ref);
        }
    });
    btnConfirmNo?.addEventListener('click', () => hideConfirm());

    let restartTimer = null;

    function stopListening() {
        listening = false;
        if (restartTimer) {
            window.clearTimeout(restartTimer);
            restartTimer = null;
        }
        if (watchdogTimer) {
            window.clearTimeout(watchdogTimer);
            watchdogTimer = null;
        }
        try {
            recognition?.stop();
        } catch {
            /* ignore */
        }
        recognition = null;
        btnListen?.classList.remove('is-on');
        if (btnListen) {
            btnListen.textContent = 'Listen for scripture';
        }
        clearLiveTranscript();
        setStatus(selectedRef ? `Showing ${selectedRef} on listen` : 'Speech recognition off');
    }

    function createRecognition() {
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (!SpeechRecognition) {
            return null;
        }
        const rec = new SpeechRecognition();
        rec.lang = 'en-US';
        // Chrome's continuous mode often stalls without delivering interim text.
        // Short non-continuous sessions + onend restart is more reliable.
        rec.continuous = false;
        rec.interimResults = true;
        rec.maxAlternatives = 1;

        rec.onaudiostart = () => {
            audioStarted = true;
        };
        rec.onspeechstart = () => {
            if (!lastHeardLine && liveLabelEl) {
                liveEl?.removeAttribute('hidden');
                liveLabelEl.textContent = 'Hearing…';
            }
        };
        rec.onresult = (event) => {
            let newFinals = '';
            let interim = '';
            // Walk the full result list so we never miss interim updates when
            // resultIndex jumps (Chrome quirks after silent restarts).
            for (let i = 0; i < event.results.length; i++) {
                const result = event.results[i];
                const piece = result?.[0]?.transcript || '';
                if (!piece) {
                    continue;
                }
                if (result.isFinal) {
                    if (i >= event.resultIndex) {
                        newFinals += piece;
                    }
                } else {
                    interim += piece;
                }
            }
            if (newFinals) {
                sessionFinals = `${sessionFinals} ${newFinals}`.replace(/\s+/g, ' ').trim();
            }
            const text = `${sessionFinals} ${interim}`.replace(/\s+/g, ' ').trim();
            if (text) {
                gotResultOnce = true;
                noSpeechStreak = 0;
                if (watchdogTimer) {
                    window.clearTimeout(watchdogTimer);
                    watchdogTimer = null;
                }
                setLiveTranscript(text, { final: Boolean(sessionFinals) && !interim.trim() });
            }
            if (!text) {
                return;
            }
            // Prefer the newest clause for scripture parse (rolling window).
            const parseTarget = `${newFinals} ${interim}`.trim() || text;
            const ref = parseSpokenReference(parseTarget) || parseSpokenReference(text);
            if (!ref) {
                return;
            }
            if (!newFinals.trim() && parseTarget.length < 6) {
                return;
            }
            setStatus(`Heard: ${ref}`);
            showConfirm(ref);
        };
        rec.onerror = (event) => {
            if (!listening) {
                return;
            }
            const err = event?.error || '';
            // Permanent / user-action errors — stop and explain.
            if (err === 'not-allowed' || err === 'service-not-allowed') {
                stopListening();
                setStatus('Microphone permission denied for speech. Allow mic access, or type a reference.');
                return;
            }
            if (err === 'audio-capture') {
                stopListening();
                setStatus('Could not capture audio for speech (mic may be in use by Studio or another app). Type a reference instead.');
                return;
            }
            // Transient: no-speech / aborted / network — keep listening; keep last transcript.
            if (err === 'no-speech' || err === 'aborted' || err === 'network') {
                if (err === 'no-speech') {
                    noSpeechStreak += 1;
                    // Soft-reset rolling finals after a long quiet stretch so the box doesn't grow forever.
                    if (noSpeechStreak >= 3) {
                        sessionFinals = '';
                    }
                    if (!gotResultOnce && noSpeechStreak >= 4) {
                        setStatus(
                            'Still listening, but no words yet — check the mic isn’t muted, use Chrome, or type a reference. Studio’s broadcast mic can block speech recognition on some browsers.',
                        );
                    } else if (!pendingRef) {
                        setStatus('Listening for scripture references…');
                    }
                    showLiveIdle();
                } else if (!pendingRef) {
                    setStatus('Listening for scripture references…');
                }
                return;
            }
            if (!pendingRef) {
                setStatus('Listening for scripture references…');
            }
        };
        rec.onend = () => {
            if (listening) {
                scheduleRestart();
            }
        };
        return rec;
    }

    function tryStartRecognition() {
        if (!listening) {
            return false;
        }
        try {
            // Fresh instance each cycle — reusing after onend is flaky in Chrome.
            recognition = createRecognition();
            if (!recognition) {
                return false;
            }
            recognition.start();
            return true;
        } catch {
            return false;
        }
    }

    function scheduleRestart() {
        if (!listening || restartTimer) {
            return;
        }
        restartTimer = window.setTimeout(() => {
            restartTimer = null;
            if (!listening) {
                return;
            }
            if (tryStartRecognition()) {
                return;
            }
            // Already started, or hard failure — try once more shortly.
            restartTimer = window.setTimeout(() => {
                restartTimer = null;
                if (!listening) {
                    return;
                }
                if (!tryStartRecognition()) {
                    stopListening();
                    setStatus('Speech recognition stopped. Click Listen again, or type a reference.');
                }
            }, 600);
        }, 350);
    }

    function startListening() {
        if (!(window.SpeechRecognition || window.webkitSpeechRecognition)) {
            setStatus('Speech recognition is not supported in this browser. Use Chrome, or type a reference.');
            return;
        }
        listening = true;
        sessionFinals = '';
        gotResultOnce = false;
        noSpeechStreak = 0;
        audioStarted = false;
        lastHeardLine = '';
        lastHeardWasFinal = false;
        btnListen?.classList.add('is-on');
        if (btnListen) {
            btnListen.textContent = 'Stop listening';
        }
        showLiveIdle();
        setStatus('Listening for scripture references…');
        armWatchdog();
        if (!tryStartRecognition()) {
            listening = false;
            clearLiveTranscript();
            btnListen?.classList.remove('is-on');
            if (btnListen) {
                btnListen.textContent = 'Listen for scripture';
            }
            setStatus('Could not start speech recognition. Type a reference instead.');
        }
    }

    btnListen?.addEventListener('click', () => {
        if (listening) {
            stopListening();
        } else {
            startListening();
        }
    });

    // Hydrate current cue.
    if (showUrl) {
        void fetch(showUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then((r) => r.json())
            .then((data) => {
                if (data.scripture?.ref) {
                    selectedRef = data.scripture.ref;
                    if (input) {
                        input.value = selectedRef;
                    }
                    setStatus(`Showing ${selectedRef} on listen`);
                }
            })
            .catch(() => {});
    }
}
