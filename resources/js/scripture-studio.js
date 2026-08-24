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
    ['ecclesiastics', 'Ecclesiastes'],
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

const CANON_BOOKS = [
    'Genesis', 'Exodus', 'Leviticus', 'Numbers', 'Deuteronomy',
    'Joshua', 'Judges', 'Ruth', '1 Samuel', '2 Samuel',
    '1 Kings', '2 Kings', '1 Chronicles', '2 Chronicles',
    'Ezra', 'Nehemiah', 'Esther', 'Job', 'Psalms', 'Proverbs',
    'Ecclesiastes', 'Song of Solomon', 'Isaiah', 'Jeremiah',
    'Lamentations', 'Ezekiel', 'Daniel', 'Hosea', 'Joel', 'Amos',
    'Obadiah', 'Jonah', 'Micah', 'Nahum', 'Habakkuk', 'Zephaniah',
    'Haggai', 'Zechariah', 'Malachi',
    'Matthew', 'Mark', 'Luke', 'John', 'Acts', 'Romans',
    '1 Corinthians', '2 Corinthians', 'Galatians', 'Ephesians',
    'Philippians', 'Colossians', '1 Thessalonians', '2 Thessalonians',
    '1 Timothy', '2 Timothy', 'Titus', 'Philemon', 'Hebrews',
    'James', '1 Peter', '2 Peter', '1 John', '2 John', '3 John',
    'Jude', 'Revelation',
];

/** lowercase alias → canonical book (mirrors KjvBibleService). */
const BOOK_ALIASES = {
    gen: 'Genesis', gn: 'Genesis', ge: 'Genesis', genesis: 'Genesis',
    ex: 'Exodus', exo: 'Exodus', exod: 'Exodus', exodus: 'Exodus',
    lev: 'Leviticus', le: 'Leviticus', lv: 'Leviticus', leviticus: 'Leviticus',
    num: 'Numbers', nu: 'Numbers', nm: 'Numbers', numbers: 'Numbers',
    deut: 'Deuteronomy', dt: 'Deuteronomy', de: 'Deuteronomy', deuteronomy: 'Deuteronomy',
    josh: 'Joshua', jos: 'Joshua', joshua: 'Joshua',
    judg: 'Judges', jdg: 'Judges', jg: 'Judges', judges: 'Judges',
    ruth: 'Ruth', ru: 'Ruth',
    '1sam': '1 Samuel', '1 samuel': '1 Samuel', '1sa': '1 Samuel', 'i samuel': '1 Samuel',
    '2sam': '2 Samuel', '2 samuel': '2 Samuel', '2sa': '2 Samuel', 'ii samuel': '2 Samuel',
    '1kgs': '1 Kings', '1 kings': '1 Kings', '1ki': '1 Kings', 'i kings': '1 Kings',
    '2kgs': '2 Kings', '2 kings': '2 Kings', '2ki': '2 Kings', 'ii kings': '2 Kings',
    '1chr': '1 Chronicles', '1 chronicles': '1 Chronicles', '1ch': '1 Chronicles', 'i chronicles': '1 Chronicles',
    '2chr': '2 Chronicles', '2 chronicles': '2 Chronicles', '2ch': '2 Chronicles', 'ii chronicles': '2 Chronicles',
    ezra: 'Ezra', ezr: 'Ezra',
    neh: 'Nehemiah', ne: 'Nehemiah', nehemiah: 'Nehemiah',
    esth: 'Esther', est: 'Esther', esther: 'Esther',
    job: 'Job',
    ps: 'Psalms', psa: 'Psalms', psalm: 'Psalms', psalms: 'Psalms',
    prov: 'Proverbs', pr: 'Proverbs', prv: 'Proverbs', proverbs: 'Proverbs',
    eccl: 'Ecclesiastes', ecc: 'Ecclesiastes', ec: 'Ecclesiastes', ecclesiastes: 'Ecclesiastes',
    song: 'Song of Solomon', sos: 'Song of Solomon', ss: 'Song of Solomon',
    'song of solomon': 'Song of Solomon', 'song of songs': 'Song of Solomon',
    isa: 'Isaiah', is: 'Isaiah', isaiah: 'Isaiah',
    jer: 'Jeremiah', je: 'Jeremiah', jeremiah: 'Jeremiah',
    lam: 'Lamentations', la: 'Lamentations', lamentations: 'Lamentations',
    ezek: 'Ezekiel', eze: 'Ezekiel', ezk: 'Ezekiel', ezekiel: 'Ezekiel',
    dan: 'Daniel', da: 'Daniel', dn: 'Daniel', daniel: 'Daniel',
    hos: 'Hosea', ho: 'Hosea', hosea: 'Hosea',
    joel: 'Joel', jl: 'Joel',
    amos: 'Amos', am: 'Amos',
    obad: 'Obadiah', ob: 'Obadiah', obadiah: 'Obadiah',
    jonah: 'Jonah', jon: 'Jonah', jnh: 'Jonah',
    mic: 'Micah', mi: 'Micah', micah: 'Micah',
    nah: 'Nahum', na: 'Nahum', nahum: 'Nahum',
    hab: 'Habakkuk', habakkuk: 'Habakkuk',
    zeph: 'Zephaniah', zep: 'Zephaniah', zephaniah: 'Zephaniah',
    hag: 'Haggai', haggai: 'Haggai',
    zech: 'Zechariah', zec: 'Zechariah', zechariah: 'Zechariah',
    mal: 'Malachi', malachi: 'Malachi',
    matt: 'Matthew', mt: 'Matthew', mat: 'Matthew', matthew: 'Matthew',
    mark: 'Mark', mk: 'Mark', mr: 'Mark',
    luke: 'Luke', lk: 'Luke', lu: 'Luke',
    john: 'John', jn: 'John', joh: 'John',
    acts: 'Acts', ac: 'Acts',
    rom: 'Romans', ro: 'Romans', romans: 'Romans',
    '1cor': '1 Corinthians', '1 corinthians': '1 Corinthians', '1co': '1 Corinthians', 'i corinthians': '1 Corinthians',
    '2cor': '2 Corinthians', '2 corinthians': '2 Corinthians', '2co': '2 Corinthians', 'ii corinthians': '2 Corinthians',
    gal: 'Galatians', ga: 'Galatians', galatians: 'Galatians',
    eph: 'Ephesians', ephesians: 'Ephesians',
    phil: 'Philippians', php: 'Philippians', philippians: 'Philippians',
    col: 'Colossians', colossians: 'Colossians',
    '1thess': '1 Thessalonians', '1 thessalonians': '1 Thessalonians', '1th': '1 Thessalonians',
    '2thess': '2 Thessalonians', '2 thessalonians': '2 Thessalonians', '2th': '2 Thessalonians',
    '1tim': '1 Timothy', '1 timothy': '1 Timothy', '1ti': '1 Timothy',
    '2tim': '2 Timothy', '2 timothy': '2 Timothy', '2ti': '2 Timothy',
    tit: 'Titus', ti: 'Titus', titus: 'Titus',
    phlm: 'Philemon', phm: 'Philemon', philemon: 'Philemon',
    heb: 'Hebrews', hebrews: 'Hebrews',
    jas: 'James', jm: 'James', james: 'James',
    '1pet': '1 Peter', '1 peter': '1 Peter', '1pe': '1 Peter',
    '2pet': '2 Peter', '2 peter': '2 Peter', '2pe': '2 Peter',
    '1john': '1 John', '1 john': '1 John', '1jn': '1 John',
    '2john': '2 John', '2 john': '2 John', '2jn': '2 John',
    '3john': '3 John', '3 john': '3 John', '3jn': '3 John',
    jude: 'Jude',
    rev: 'Revelation', re: 'Revelation', revelation: 'Revelation', revelations: 'Revelation',
};

/**
 * @param {string} raw
 * @returns {{ bookToken: string, separator: string, rest: string|null }}
 */
export function splitScriptureQuery(raw) {
    const value = String(raw ?? '').replace(/^\s+/, '');
    const withRest = value.match(/^(.+?)(\s+)(\d.*)$/);
    if (withRest) {
        return { bookToken: withRest[1], separator: withRest[2], rest: withRest[3] };
    }
    const trailing = value.match(/^(.*\S)(\s+)$/);
    if (trailing) {
        return { bookToken: trailing[1], separator: trailing[2], rest: '' };
    }
    return { bookToken: value, separator: '', rest: null };
}

function normalizeBookToken(token) {
    let key = String(token || '').toLowerCase().trim().replace(/\./g, '');
    key = key.replace(/\s+/g, ' ');
    key = key.replace(/^iii\s+/, '3 ').replace(/^ii\s+/, '2 ').replace(/^i\s+/, '1 ');
    key = key.replace(/^3rd\s+/, '3 ').replace(/^2nd\s+/, '2 ').replace(/^1st\s+/, '1 ');
    return key;
}

/**
 * @param {string} token
 * @returns {string[]}
 */
export function matchBooks(token) {
    const key = normalizeBookToken(token);
    if (!key) {
        return [];
    }
    const compact = key.replace(/ /g, '');
    const found = [];
    const seen = new Set();
    const add = (book) => {
        if (book && !seen.has(book)) {
            seen.add(book);
            found.push(book);
        }
    };

    const exact = BOOK_ALIASES[key] || BOOK_ALIASES[compact];
    if (exact) {
        add(exact);
    }

    for (const book of CANON_BOOKS) {
        const lower = book.toLowerCase();
        const bookCompact = lower.replace(/ /g, '');
        if (lower === key || bookCompact === compact || lower.startsWith(key) || bookCompact.startsWith(compact)) {
            add(book);
        }
    }

    return found;
}

/**
 * Best book completion for the current query (EasyWorship / address-bar).
 *
 * @param {string} raw
 * @returns {{ book: string, unique: boolean, ghostSuffix: string, expanded: string }|null}
 */
export function inferBookCompletion(raw) {
    const { bookToken, separator, rest } = splitScriptureQuery(raw);
    if (!bookToken) {
        return null;
    }
    const matches = matchBooks(bookToken);
    if (!matches.length) {
        return null;
    }
    const book = matches[0];
    const unique = matches.length === 1;
    const expanded = rest !== null ? `${book}${separator}${rest}` : `${book} `;
    let ghostSuffix = '';
    if (book.toLowerCase().startsWith(bookToken.toLowerCase())) {
        ghostSuffix = book.slice(bookToken.length);
    }
    return { book, unique, ghostSuffix, expanded };
}

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

function isTensWord(n) {
    return n != null && n >= 20 && n % 10 === 0;
}

function isOnesWord(n) {
    return n != null && n >= 1 && n <= 9;
}

/** How many numeric values remain from `from` (tens+ones count as one). */
function countNumericValuesAhead(tokens, from) {
    let count = 0;
    let i = from;
    while (i < tokens.length) {
        if (/^\d+$/.test(tokens[i])) {
            count += 1;
            i += 1;
            continue;
        }
        const n = WORDS[tokens[i]];
        if (n == null) {
            i += 1;
            continue;
        }
        if (isTensWord(n) && i + 1 < tokens.length && isOnesWord(WORDS[tokens[i + 1]])) {
            count += 1;
            i += 2;
            continue;
        }
        count += 1;
        i += 1;
    }
    return count;
}

/**
 * @param {string} transcript
 * @returns {string|null} e.g. "John 3:16"
 */
function chapterVerseFromRest(rest, book) {
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
            const ones = WORDS[tokens[i + 1]];
            if (isTensWord(tens) && isOnesWord(ones)) {
                // Prefer chapter:verse when two word-numbers follow a book with
                // nothing after ("thirty one" → 30:1). Once chapter is set,
                // compound for the verse ("twenty three thirty one" → 23:31).
                const moreAfter = countNumericValuesAhead(tokens, i + 2);
                if (nums.length === 0 && moreAfter === 0) {
                    nums.push(tens);
                    nums.push(ones);
                    i += 2;
                    continue;
                }
                const two = parseSpokenNumber([tokens[i], tokens[i + 1]]);
                if (two != null) {
                    nums.push(two);
                    i += 2;
                    continue;
                }
            }
        }
        // Prefer "three sixteen" as 3 then 16 (not 19).
        nums.push(one);
        i += 1;
    }

    if (nums.length < 2) {
        return null;
    }
    const chapter = nums[0];
    const verse = nums[1];
    // Only real ascending verse ranges — restated "chapter 1" must not yield 1:7-1.
    if (nums.length >= 3 && nums[2] > verse) {
        return `${book} ${chapter}:${verse}-${nums[2]}`;
    }
    return `${book} ${chapter}:${verse}`;
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
        // "John 13 start from 16" / "begin at" / "beginning at" / "starting from"
        .replace(/\b(start|starting|begin|beginning)\s+(from|at)\b/g, ' ')
        .replace(/\b(start|starting|begin|beginning)\b/g, ' ')
        .replace(/\bfrom\b/g, ' ')
        .replace(/\bat\b/g, ' ')
        .replace(/\band\b/g, ' ')
        .replace(/\bthrough\b/g, ' ')
        .replace(/\bto\b/g, ' ')
        .replace(/\bby\b/g, ' ');

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

    /** @type {{ book: string, idx: number, len: number }[]} */
    const hits = [];
    for (const [spoken, canonical] of BOOK_SPOKEN) {
        const needle = ` ${spoken} `;
        let from = 0;
        while (true) {
            const idx = text.indexOf(needle, from);
            if (idx === -1) {
                break;
            }
            hits.push({ book: canonical, idx, len: needle.length });
            from = idx + 1;
        }
    }
    if (!hits.length) {
        return null;
    }

    hits.sort((a, b) => (a.idx - b.idx) || (b.len - a.len));
    /** @type {{ book: string, idx: number, len: number }[]} */
    const kept = [];
    for (const h of hits) {
        const nested = kept.some((k) => h.idx >= k.idx && h.idx < k.idx + k.len);
        if (!nested) {
            kept.push(h);
        }
    }

    let best = null;
    let bestIdx = -1;
    for (const h of kept) {
        const ref = chapterVerseFromRest(text.slice(h.idx + h.len), h.book);
        if (!ref) {
            continue;
        }
        if (h.idx >= bestIdx) {
            best = ref;
            bestIdx = h.idx;
        }
    }
    return best;
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
    const ghostEl = document.getElementById('scripture-search-ghost');
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
    /** @type {string} dismissed listen suggestion — suppress until a distinct new ref */
    let dismissedListenRef = '';
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

    function sameRef(a, b) {
        return String(a || '').trim().toLowerCase() === String(b || '').trim().toLowerCase();
    }

    function hideConfirm({ dismiss = false } = {}) {
        if (confirmTimer) {
            window.clearTimeout(confirmTimer);
            confirmTimer = null;
        }
        if (dismiss && pendingRef) {
            dismissedListenRef = pendingRef;
            const lower = String(statusEl?.textContent || '').toLowerCase();
            if (
                lower.includes('could not find') ||
                lower.includes('could not show') ||
                lower.startsWith('heard:') ||
                lower.startsWith('showing ')
            ) {
                setStatus('Listening for scripture references…');
            }
        }
        pendingRef = '';
        confirmEl?.setAttribute('hidden', '');
    }

    function showConfirm(ref) {
        if (sameRef(ref, dismissedListenRef)) {
            return;
        }
        if (sameRef(ref, selectedRef)) {
            return;
        }
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
            void cue(ref, { fromListen: true });
            hideConfirm();
        }, 1200);
    }

    async function cue(ref, { fromListen = false } = {}) {
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
                if (fromListen) {
                    dismissedListenRef = ref;
                    setStatus('Listening for scripture references…');
                } else {
                    setStatus(data.message || 'Could not show that verse.');
                }
                return;
            }
            selectedRef = data.scripture?.ref || ref;
            dismissedListenRef = '';
            writeInputValue(selectedRef);
            inlineHint = null;
            renderGhost('');
            setStatus(`Showing ${selectedRef} on listen`);
            window.dispatchEvent(new CustomEvent('live-board-changed', {
                detail: { mode: 'scripture', scripture: data.scripture || null },
            }));
        } catch {
            if (fromListen) {
                dismissedListenRef = ref;
                setStatus('Listening for scripture references…');
            } else {
                setStatus('Could not show that verse.');
            }
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
            window.dispatchEvent(new CustomEvent('live-board-changed', {
                detail: { mode: null },
            }));
        } catch {
            setStatus('Could not clear scripture.');
        }
    }

    let suggestTimer = null;
    let activeSuggestIndex = -1;
    let applyingCompletion = false;
    /** @type {{ book: string, unique: boolean, ghostSuffix: string, expanded: string }|null} */
    let inlineHint = null;

    function cursorAtEnd() {
        if (!input) {
            return false;
        }
        return input.selectionStart === input.value.length && input.selectionEnd === input.value.length;
    }

    function writeInputValue(value) {
        if (!input) {
            return;
        }
        applyingCompletion = true;
        input.value = value;
        try {
            const end = value.length;
            input.setSelectionRange(end, end);
        } catch {
            /* unfocused type=search can throw */
        }
        applyingCompletion = false;
        selectedRef = value.trim();
    }

    function syncGhostMetrics() {
        if (!ghostEl || !input) {
            return;
        }
        const cs = getComputedStyle(input);
        ghostEl.style.font = cs.font;
        ghostEl.style.letterSpacing = cs.letterSpacing;
        ghostEl.style.paddingTop = cs.paddingTop;
        ghostEl.style.paddingBottom = cs.paddingBottom;
        ghostEl.style.paddingLeft = cs.paddingLeft;
        ghostEl.style.paddingRight = cs.paddingRight;
        ghostEl.style.lineHeight = cs.lineHeight;
    }

    function renderGhost(suffix) {
        if (!ghostEl) {
            return;
        }
        if (!suffix || !input?.value) {
            ghostEl.replaceChildren();
            return;
        }
        syncGhostMetrics();
        const typed = document.createElement('span');
        typed.className = 'scripture-search-ghost-typed';
        typed.textContent = input.value;
        const rest = document.createElement('span');
        rest.className = 'scripture-search-ghost-suffix';
        rest.textContent = suffix;
        ghostEl.replaceChildren(typed, rest);
    }

    function refreshInlineHint({ expand } = { expand: false }) {
        if (!input) {
            inlineHint = null;
            renderGhost('');
            return;
        }
        inlineHint = inferBookCompletion(input.value);
        const canExpand = Boolean(
            expand
                && inlineHint?.unique
                && inlineHint.expanded !== input.value
                && cursorAtEnd(),
        );
        if (canExpand && inlineHint) {
            writeInputValue(inlineHint.expanded);
            inlineHint = inferBookCompletion(input.value);
        }
        const ghost = inlineHint
            && inlineHint.expanded !== input.value
            && inlineHint.ghostSuffix
            ? inlineHint.ghostSuffix
            : '';
        renderGhost(ghost);
    }

    function acceptInlineCompletion() {
        inlineHint = inferBookCompletion(input?.value || '');
        if (!input || !inlineHint || inlineHint.expanded === input.value) {
            return false;
        }
        writeInputValue(inlineHint.expanded);
        refreshInlineHint({ expand: false });
        scheduleSuggest(input.value);
        return true;
    }

    function scheduleSuggest(q) {
        if (suggestTimer) {
            window.clearTimeout(suggestTimer);
        }
        suggestTimer = window.setTimeout(() => void runSuggest(q), 250);
    }

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
        input?.setAttribute('aria-expanded', 'false');
    }

    function applySuggestion(ref, { cueNow = true } = {}) {
        if (!ref) {
            return;
        }
        writeInputValue(ref);
        selectedRef = ref;
        inlineHint = null;
        renderGhost('');
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
            input?.setAttribute('aria-expanded', items.length ? 'true' : 'false');
        } catch {
            /* ignore */
        }
    }

    input?.addEventListener('input', (ev) => {
        if (applyingCompletion) {
            return;
        }
        const deleting = typeof ev.inputType === 'string'
            && (ev.inputType.startsWith('delete') || ev.inputType === 'historyUndo');
        selectedRef = input.value.trim();
        refreshInlineHint({ expand: !deleting && !ev.isComposing });
        scheduleSuggest(input.value);
    });

    input?.addEventListener('compositionend', () => {
        refreshInlineHint({ expand: true });
        scheduleSuggest(input.value);
    });

    input?.addEventListener('keydown', (ev) => {
        const items = suggestItems();
        if ((ev.key === 'Tab' || ev.key === 'ArrowRight') && !ev.altKey && !ev.metaKey && !ev.ctrlKey) {
            const atEnd = ev.key === 'Tab' || cursorAtEnd();
            if (atEnd && acceptInlineCompletion()) {
                ev.preventDefault();
                return;
            }
        }
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
            if (items.length || (ghostEl && ghostEl.childNodes.length)) {
                ev.preventDefault();
                clearSuggestions();
                renderGhost('');
                inlineHint = null;
            }
            return;
        }
        if (ev.key === 'Enter') {
            ev.preventDefault();
            const pick = activeSuggestIndex >= 0 && items[activeSuggestIndex]
                ? items[activeSuggestIndex]
                : items[0];
            if (pick) {
                applySuggestion(pick.dataset.ref || '', { cueNow: true });
                return;
            }
            void cue(input.value.trim());
            return;
        }
        if (ev.key === 'Tab' && items.length) {
            ev.preventDefault();
            const pick = activeSuggestIndex >= 0 && items[activeSuggestIndex]
                ? items[activeSuggestIndex]
                : items[0];
            applySuggestion(pick.dataset.ref || '', { cueNow: false });
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
    btnConfirmNo?.addEventListener('click', () => hideConfirm({ dismiss: true }));

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

    window.addEventListener('live-board-changed', (ev) => {
        const mode = ev?.detail?.mode;
        if (mode === 'song') {
            setStatus('No scripture on listen');
        }
    });

    // Hydrate current cue.
    if (showUrl) {
        void fetch(showUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then((r) => r.json())
            .then((data) => {
                if (data.live_board === 'scripture' && data.scripture?.ref) {
                    selectedRef = data.scripture.ref;
                    writeInputValue(selectedRef);
                    inlineHint = null;
                    renderGhost('');
                    setStatus(`Showing ${selectedRef} on listen`);
                    return;
                }
                if (data.live_board === 'song') {
                    setStatus('No scripture on listen');
                }
            })
            .catch(() => {});
    }
}
