import './bootstrap';
import { bindScriptureStudio } from './scripture-studio';

const root = document.getElementById('studio-root');
const whipUrl = root?.dataset.whipUrl;
const broadcastAllowed = root?.dataset.broadcastAllowed !== '0';
const billingUrl = root?.dataset.billingUrl || '/billing';
const audioSelect = document.getElementById('audio-input');
const auxSelect = document.getElementById('aux-input');
const outputSelect = document.getElementById('audio-output');
const btnStart = document.getElementById('btn-start');
const btnStop = document.getElementById('btn-stop'); // legacy
const btnPause = document.getElementById('btn-pause');
const btnEnd = document.getElementById('btn-end');
const btnCreateEvent = document.getElementById('btn-create-event');
const btnAddFile = document.getElementById('btn-add-file');
const eventTitleEl = document.getElementById('studio-event-title');
const eventUrlEl = document.getElementById('event-url');
const sessionGoLiveUrl = root?.dataset.sessionGoLiveUrl;
const sessionPauseUrl = root?.dataset.sessionPauseUrl;
const sessionResumeUrl = root?.dataset.sessionResumeUrl;
const sessionEndUrl = root?.dataset.sessionEndUrl;
const sessionCreateEventUrl = root?.dataset.sessionCreateEventUrl;
const sessionRenameEventUrl = root?.dataset.sessionRenameEventUrl;
const streamTitleDefault = root?.dataset.streamTitle || 'Sound Mix Live';
const eventTitleInput = document.getElementById('event-title-input');
const eventRenameWrap = document.getElementById('studio-event-rename');
const btnSaveEventTitle = document.getElementById('btn-save-event-title');
const localRecordingTitleInput = document.getElementById('local-recording-title');
/** @type {{ id:number, uuid:string, title:string, status:string, url:string }|null} */
let currentEvent = root?.dataset.openEventId
    ? {
        id: Number(root.dataset.openEventId),
        uuid: '',
        title: root.dataset.openEventTitle || streamTitleDefault,
        status: root.dataset.openEventStatus || 'scheduled',
        url: root.dataset.openEventUrl || '',
    }
    : null;
let sessionPaused = currentEvent?.status === 'paused';
const fileInput = document.getElementById('file-input');
const channelsEl = document.getElementById('audio-channels');
const statusEl = document.getElementById('studio-status');
const meterEl = document.getElementById('level-meter');
const meterLabel = document.getElementById('meter-label');
const micMeterEl = document.getElementById('mic-meter');
const auxMeterEl = document.getElementById('aux-meter');
const playlistMeterEl = document.getElementById('playlist-meter');
const stageEl = document.getElementById('studio-stage');
const modeEl = document.getElementById('studio-mode');
const modeMobileEl = document.getElementById('studio-mode-mobile');
const airLabel = document.getElementById('studio-air-label');
const heroHint = document.getElementById('studio-hero-hint');
const timerEl = document.getElementById('studio-timer');
const shareChannelBtn = document.getElementById('btn-share-channel');
const shareChannelMainBtn = document.getElementById('btn-share-channel-main');
const copyChannelBtn = document.getElementById('btn-copy-channel');
const channelUrlEl = document.getElementById('channel-url');
const listenUrlEl = document.getElementById('listen-url');
const channelShareUrl = root?.dataset.channelUrl || channelUrlEl?.textContent?.trim() || '';
const channelShareName = root?.dataset.channelName || 'Sound Mix Live';

function activeShareUrl() {
    return currentEvent?.url || eventUrlEl?.textContent?.trim() || channelShareUrl;
}
const audioLayoutSelect = document.getElementById('audio-layout');
const cueAudio = document.getElementById('cue-audio');
const playlistCountEl = document.getElementById('playlist-count');

const micFaderEl = document.getElementById('mic-fader');
const auxFaderEl = document.getElementById('aux-fader');
const playlistFaderEl = document.getElementById('playlist-fader');
const masterFaderEl = document.getElementById('master-fader');
const micCueBtn = document.getElementById('mic-cue');
const auxCueBtn = document.getElementById('aux-cue');
const playlistCueBtn = document.getElementById('playlist-cue');
const micMuteBtn = document.getElementById('mic-mute');
const auxMuteBtn = document.getElementById('aux-mute');
const playlistMuteBtn = document.getElementById('playlist-mute');

/** Opus fullband max (bits/sec). */
const OPUS_MAX_BITRATE = 510_000;
const LAYOUT_STORAGE_KEY = 'studio-audio-layout';

/** Raw interface capture — no browser AGC / NS / EC (desktop / USB interfaces). */
const CLEAN_AUDIO = {
    channelCount: { ideal: 2 },
    sampleRate: { ideal: 48000 },
    sampleSize: { ideal: 16 },
    echoCancellation: false,
    noiseSuppression: false,
    autoGainControl: false,
    googEchoCancellation: false,
    googNoiseSuppression: false,
    googAutoGainControl: false,
    googHighpassFilter: false,
    googTypingNoiseDetection: false,
};

/** Simpler constraints for phones — strict CLEAN_AUDIO often fails on Android. */
const MOBILE_AUDIO = {
    echoCancellation: true,
    noiseSuppression: true,
    autoGainControl: true,
};

let micPrimed = false;

function isMobileUa() {
    return /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent || '');
}

function audioCaptureConstraints() {
    return isMobileUa() ? { ...MOBILE_AUDIO } : { ...CLEAN_AUDIO };
}

let pc = null;
/** @type {string|null} */
let whipResourceUrl = null;
/** @type {AudioContext|null} */
let audioCtx = null;
/** @type {MediaStreamAudioDestinationNode|null} */
let mixDest = null;
/** Local cue bus — played only via #cue-audio when a cue button is on. */
/** @type {MediaStreamAudioDestinationNode|null} */
let cueDest = null;
/** @type {GainNode|null} */
let masterGain = null;
/** Post-fader cue bus — follows Master so headphones match broadcast level. */
/** @type {GainNode|null} */
let cueMasterGain = null;
/** @type {GainNode|null} */
let micGain = null;
/** @type {GainNode|null} */
let micCueGain = null;
/** @type {MediaStreamAudioSourceNode|null} */
let micSource = null;
/** @type {ChannelSplitterNode|null} */
let micSplitter = null;
/** @type {GainNode|null} */
let micMonoSum = null;
/** @type {ChannelMergerNode|null} */
let micMerger = null;
/** @type {MediaStream|null} */
let micStream = null;
/** @type {GainNode|null} */
let auxGain = null;
/** @type {GainNode|null} */
let auxCueGain = null;
/** @type {MediaStreamAudioSourceNode|null} */
let auxSource = null;
/** @type {ChannelSplitterNode|null} */
let auxSplitter = null;
/** @type {GainNode|null} */
let auxMonoSum = null;
/** @type {ChannelMergerNode|null} */
let auxMerger = null;
/** @type {MediaStream|null} */
let auxStream = null;
/** @type {GainNode|null} */
let playlistGain = null;
/** @type {GainNode|null} */
let playlistCueGain = null;
/** @type {AnalyserNode|null} */
let masterAnalyser = null;
/** @type {AnalyserNode|null} */
let micAnalyser = null;
/** @type {AnalyserNode|null} */
let auxAnalyser = null;
/** @type {AnalyserNode|null} */
let playlistAnalyser = null;
let meterRaf = 0;
let fileChannelSeq = 0;
let isLive = false;
let liveStartedAt = 0;
let timerInterval = 0;
/** @type {'mixer' | null} */
let publishMode = null;
/** Auto-republish WHIP after publisher network drops while still on air. */
let publisherReconnectTimer = null;
let publisherDisconnectGraceTimer = null;
let publisherReconnectAttempt = 0;
let publisherRepublishing = false;

/** @type {MediaRecorder|null} */
let localRecorder = null;
/** @type {Blob[]} */
let localRecordChunks = [];
/** @type {Blob|null} */
let pendingLocalRecording = null;
let pendingLocalDurationSec = 0;
let pendingLocalMime = 'audio/webm';
let localRecordStartedAt = 0;

let micMuted = false;
let auxMuted = true;
let playlistMuted = false;
let micCueOn = false;
let auxCueOn = false;
let playlistCueOn = false;

/** @type {Map<string, {
 *   id: string,
 *   name: string,
 *   objectUrl: string,
 *   audio: HTMLAudioElement,
 *   source: MediaElementAudioSourceNode|null,
 *   gain: GainNode|null,
 *   card: HTMLElement,
 *   duration: number,
 * }>} */
const fileChannels = new Map();

function setStatus(message) {
    if (statusEl) {
        statusEl.textContent = message;
    }
}

function formatTimer(ms) {
    const total = Math.max(0, Math.floor(ms / 1000));
    const h = String(Math.floor(total / 3600)).padStart(2, '0');
    const m = String(Math.floor((total % 3600) / 60)).padStart(2, '0');
    const s = String(total % 60).padStart(2, '0');
    return `${h}:${m}:${s}`;
}

function updateTimer() {
    if (!timerEl) {
        return;
    }
    timerEl.textContent = isLive ? formatTimer(Date.now() - liveStartedAt) : '00:00:00';
}

function setOnAir(live) {
    isLive = live;
    if (!live) {
        clearPublisherReconnect();
    }
    if (heroHint) {
        if (live) {
            heroHint.textContent = 'You’re broadcasting — Pause keeps this event; End live closes it.';
        } else if (sessionPaused) {
            heroHint.textContent = 'Event paused — Resume to continue, or End live to close this event.';
        } else {
            heroHint.textContent = 'Create an event, or go live to start one automatically';
        }
    }
    if (live) {
        sessionPaused = false;
        liveStartedAt = Date.now();
        updateTimer();
        if (timerInterval) {
            clearInterval(timerInterval);
        }
        timerInterval = window.setInterval(updateTimer, 1000);
    } else {
        if (timerInterval) {
            clearInterval(timerInterval);
            timerInterval = 0;
        }
        updateTimer();
    }
    refreshSessionButtons();
}

function httpsStudioUrl() {
    const u = new URL(window.location.href);
    u.protocol = 'https:';
    return u.href;
}

function friendlyError(err) {
    if (!(err instanceof Error)) {
        return 'Could not go live.';
    }
    const name = err.name || '';
    const msg = err.message || '';
    if (!window.isSecureContext || /secure origin|insecure/i.test(msg)) {
        return `Studio needs HTTPS for the microphone. Open ${httpsStudioUrl()}`;
    }
    if (
        name === 'NotReadableError'
        || name === 'AbortError'
        || /Could not start|Device in use|in use|busy|TrackStartError/i.test(msg)
    ) {
        return isMobileUa()
            ? 'Microphone is busy. Close WhatsApp (or Phone / Zoom / Meet), dismiss any chat bubbles or overlays, then tap Allow microphone again.'
            : 'Microphone is busy in another app. Close that app and try again.';
    }
    if (name === 'NotAllowedError' || /permission|denied|dismissed/i.test(msg)) {
        return isMobileUa()
            ? 'Mic permission blocked. Close WhatsApp and any bubbles/overlays, then tap Allow microphone. If it still fails, open Chrome site settings → Microphone → Allow for this site.'
            : 'Microphone permission denied. Allow the mic in your browser settings and try again.';
    }
    if (name === 'NotFoundError' || /requested device not found/i.test(msg)) {
        return 'Could not open that microphone. Pick another input, or reload and allow mic access.';
    }
    if (name === 'OverconstrainedError' || /constraint/i.test(msg)) {
        return 'This device rejected the mic settings. Tap Allow microphone again, or try another browser.';
    }
    if (/WHIP|403|401|forbidden|publish secret|Not Found|<!DOCTYPE/i.test(msg)) {
        return 'Publish rejected or media proxy misconfigured. Confirm /rtc reaches MediaMTX (not Laravel).';
    }
    if (/ICE|icegather|DTLS|Could not establish|peerconnection/i.test(msg)) {
        return 'Could not reach the media server (ICE/network). Confirm UDP 8189 and webrtcAdditionalHosts.';
    }
    if (/Failed to fetch|NetworkError|Load failed/i.test(msg)) {
        return 'Could not reach the WHIP endpoint. Check HTTPS, Caddy /rtc proxy, and MediaMTX.';
    }
    return msg || 'Could not go live.';
}

function setMicEnableVisible(show) {
    const wrap = document.getElementById('mic-enable-wrap');
    if (wrap) {
        wrap.hidden = !show;
    }
}

/** @returns {'mono' | 'stereo'} */
function audioLayout() {
    const v = audioLayoutSelect?.value;
    return v === 'stereo' ? 'stereo' : 'mono';
}

function isMono() {
    return audioLayout() === 'mono';
}

function faderGain(el) {
    return Number(el?.value ?? 100) / 100;
}

function applyMicGain() {
    if (micGain) {
        micGain.gain.value = micMuted ? 0 : faderGain(micFaderEl);
    }
}

function applyAuxGain() {
    if (auxGain) {
        auxGain.gain.value = auxMuted || !auxSelect?.value ? 0 : faderGain(auxFaderEl);
    }
}

function applyPlaylistGain() {
    if (playlistGain) {
        playlistGain.gain.value = playlistMuted ? 0 : faderGain(playlistFaderEl);
    }
}

function applyMasterGain() {
    const g = faderGain(masterFaderEl);
    if (masterGain) {
        masterGain.gain.value = g;
    }
    if (cueMasterGain) {
        cueMasterGain.gain.value = g;
    }
}

function anyCueOn() {
    return micCueOn || auxCueOn || playlistCueOn;
}

async function syncCuePlayback() {
    if (!cueAudio || !cueDest) {
        return;
    }
    if (micCueGain) {
        micCueGain.gain.value = micCueOn ? 1 : 0;
    }
    if (auxCueGain) {
        auxCueGain.gain.value = auxCueOn ? 1 : 0;
    }
    if (playlistCueGain) {
        playlistCueGain.gain.value = playlistCueOn ? 1 : 0;
    }

    if (!anyCueOn()) {
        cueAudio.pause();
        cueAudio.srcObject = null;
        return;
    }

    if (cueAudio.srcObject !== cueDest.stream) {
        cueAudio.srcObject = cueDest.stream;
    }

    const sinkId = outputSelect?.value || '';
    if (sinkId && typeof cueAudio.setSinkId === 'function') {
        try {
            await cueAudio.setSinkId(sinkId);
        } catch {
            /* output routing optional */
        }
    }

    try {
        await cueAudio.play();
    } catch {
        setStatus('Could not start cue monitor — click Go on air or cue again after interacting with the page.');
    }
}

function setToggle(btn, on) {
    if (!btn) {
        return;
    }
    btn.classList.toggle('is-active', on);
    btn.setAttribute('aria-pressed', on ? 'true' : 'false');
}

function updatePlaylistMeta() {
    const n = fileChannels.size;
    if (playlistCountEl) {
        playlistCountEl.textContent = n === 0 ? 'No sounds' : `${n} sound${n === 1 ? '' : 's'}`;
    }
    let total = 0;
    for (const ch of fileChannels.values()) {
        total += Number.isFinite(ch.duration) ? ch.duration : 0;
    }
    const durationEl = document.getElementById('playlist-duration');
    if (durationEl) {
        durationEl.textContent = formatTimer(total * 1000);
    }
}

try {
    localStorage.removeItem('studio-audio-fx');
} catch {
    /* ignore */
}

if (audioLayoutSelect) {
    const saved = localStorage.getItem(LAYOUT_STORAGE_KEY);
    if (saved === 'mono' || saved === 'stereo') {
        audioLayoutSelect.value = saved;
    }
    audioLayoutSelect.addEventListener('change', () => {
        localStorage.setItem(LAYOUT_STORAGE_KEY, audioLayout());
        if (isLive) {
            setStatus('Output layout changed — End broadcast, then Go on air again to apply.');
        }
    });
}

async function copyChannelLink() {
    const text = activeShareUrl();
    if (!text) {
        return;
    }
    try {
        await navigator.clipboard.writeText(text);
        setStatus(currentEvent?.url ? 'Event link copied.' : 'Channel link copied.');
    } catch {
        setStatus('Could not copy link.');
    }
}

async function shareChannelLink() {
    const text = activeShareUrl();
    if (!text) {
        return;
    }
    const title = currentEvent?.title || channelShareName;
    if (navigator.share) {
        try {
            await navigator.share({
                title,
                text: `Listen live: ${title}`,
                url: text,
            });
            setStatus('Link shared.');
            return;
        } catch (e) {
            if (e?.name === 'AbortError') {
                return;
            }
        }
    }
    await copyChannelLink();
}

function applyEventToUi(event) {
    const previousGalleryEventId = galleryScopedEventId();
    currentEvent = event || null;
    sessionPaused = event?.status === 'paused';
    if (eventTitleEl) {
        eventTitleEl.textContent = event?.title || streamTitleDefault;
    }
    if (eventTitleInput) {
        eventTitleInput.value = event?.title || '';
    }
    if (eventRenameWrap) {
        eventRenameWrap.hidden = !event;
    }
    if (eventUrlEl) {
        if (event?.url) {
            eventUrlEl.textContent = event.url;
            eventUrlEl.title = event.url;
        } else if (!event && channelShareUrl) {
            eventUrlEl.textContent = channelShareUrl;
            eventUrlEl.title = channelShareUrl;
        }
    }
    refreshSessionButtons();
    const nextGalleryEventId = galleryScopedEventId();
    if (previousGalleryEventId !== nextGalleryEventId) {
        void refreshStudioGallery();
    }
}

async function sessionPatch(url, body = {}) {
    if (!url) {
        throw new Error('Session link missing — reopen Studio from your dashboard.');
    }
    const res = await fetch(url, {
        method: 'PATCH',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': root?.dataset.csrf || document.querySelector('meta[name="csrf-token"]')?.content || '',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(body),
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        throw new Error(data.message || Object.values(data.errors || {})[0]?.[0] || 'Could not save');
    }
    return data;
}

btnSaveEventTitle?.addEventListener('click', async () => {
    const title = (eventTitleInput?.value || '').trim();
    if (!title) {
        setStatus('Enter an event name first.');
        return;
    }
    if (!currentEvent?.id && !sessionRenameEventUrl) {
        setStatus('Go live or create an event first, then save the name.');
        return;
    }
    btnSaveEventTitle.disabled = true;
    try {
        const data = await sessionPatch(sessionRenameEventUrl, {
            title,
            event_id: currentEvent?.id || undefined,
        });
        applyEventToUi(data.event);
        setStatus(`Event name saved: ${data.event.title}`);
    } catch (e) {
        setStatus(e instanceof Error ? e.message : 'Could not save event name.');
    } finally {
        btnSaveEventTitle.disabled = false;
    }
});

eventTitleInput?.addEventListener('keydown', (ev) => {
    if (ev.key === 'Enter') {
        ev.preventDefault();
        btnSaveEventTitle?.click();
    }
});

function refreshSessionButtons() {
    const live = isLive;
    const paused = sessionPaused && !live;
    // Server still has a live event after refresh / WHIP drop — reconnect before grace ends.
    const needsReconnect = !live && !paused && currentEvent?.status === 'live';
    if (btnCreateEvent) {
        btnCreateEvent.hidden = live || paused || needsReconnect;
        btnCreateEvent.disabled = !broadcastAllowed || live || paused || needsReconnect;
    }
    if (btnPause) {
        btnPause.hidden = !live;
        btnPause.disabled = !live;
    }
    if (btnEnd) {
        btnEnd.hidden = !(live || paused || needsReconnect);
        btnEnd.disabled = !(live || paused || needsReconnect);
    }
    if (btnStart) {
        btnStart.hidden = false;
        if (live) {
            btnStart.textContent = 'On air';
            btnStart.disabled = true;
        } else if (paused) {
            btnStart.textContent = 'Resume';
            btnStart.disabled = !broadcastAllowed;
        } else if (needsReconnect) {
            btnStart.textContent = 'Reconnect';
            btnStart.disabled = !broadcastAllowed;
        } else {
            btnStart.textContent = 'Go live';
            btnStart.disabled = !broadcastAllowed;
        }
    }
    if (airLabel) {
        airLabel.textContent = live ? 'ON THE AIR' : paused ? 'PAUSED' : needsReconnect ? 'RECONNECT' : 'STANDBY';
    }
    if (modeEl) {
        modeEl.textContent = live ? 'On the air' : paused ? 'Paused' : needsReconnect ? 'Reconnect' : 'Standby';
    }
    if (modeMobileEl) {
        modeMobileEl.textContent = live ? 'ON AIR' : paused ? 'PAUSED' : needsReconnect ? 'RECONNECT' : 'STANDBY';
    }
    stageEl?.classList.toggle('is-on-air', live);
    stageEl?.classList.toggle('is-paused', paused);
}

async function sessionPost(url, body = {}) {
    if (!url) {
        throw new Error('Session link missing — reopen Studio from your dashboard.');
    }
    const res = await fetch(url, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': root?.dataset.csrf || document.querySelector('meta[name="csrf-token"]')?.content || '',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(body),
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        throw new Error(data.message || Object.values(data.errors || {})[0]?.[0] || 'Session request failed');
    }
    return data;
}

shareChannelBtn?.addEventListener('click', () => {
    void shareChannelLink();
});
shareChannelMainBtn?.addEventListener('click', () => {
    void shareChannelLink();
});
copyChannelBtn?.addEventListener('click', () => {
    void copyChannelLink();
});

document.getElementById('btn-refresh-studio')?.addEventListener('click', () => {
    setStatus('Refreshing Studio…');
    window.location.reload();
});

micFaderEl?.addEventListener('input', applyMicGain);
auxFaderEl?.addEventListener('input', applyAuxGain);
playlistFaderEl?.addEventListener('input', applyPlaylistGain);
masterFaderEl?.addEventListener('input', applyMasterGain);

micMuteBtn?.addEventListener('click', () => {
    micMuted = !micMuted;
    setToggle(micMuteBtn, micMuted);
    applyMicGain();
});
auxMuteBtn?.addEventListener('click', () => {
    auxMuted = !auxMuted;
    setToggle(auxMuteBtn, auxMuted);
    applyAuxGain();
});
playlistMuteBtn?.addEventListener('click', () => {
    playlistMuted = !playlistMuted;
    setToggle(playlistMuteBtn, playlistMuted);
    applyPlaylistGain();
});

micCueBtn?.addEventListener('click', async () => {
    micCueOn = !micCueOn;
    setToggle(micCueBtn, micCueOn);
    if (micCueOn) {
        setStatus('Mic cue on — use headphones or you will get feedback.');
    }
    await syncCuePlayback();
});
auxCueBtn?.addEventListener('click', async () => {
    auxCueOn = !auxCueOn;
    setToggle(auxCueBtn, auxCueOn);
    if (auxCueOn) {
        setStatus('Input cue on — use headphones or you will get feedback.');
    }
    await syncCuePlayback();
});
playlistCueBtn?.addEventListener('click', async () => {
    playlistCueOn = !playlistCueOn;
    setToggle(playlistCueBtn, playlistCueOn);
    if (playlistCueOn) {
        setStatus('Playlist cue on — use headphones or you will get feedback.');
    }
    await syncCuePlayback();
});

outputSelect?.addEventListener('change', () => {
    void syncCuePlayback();
});

function stopMeter() {
    if (meterRaf) {
        cancelAnimationFrame(meterRaf);
        meterRaf = 0;
    }
    for (const el of [meterEl, micMeterEl, auxMeterEl, playlistMeterEl]) {
        if (el) {
            el.style.width = '0%';
            el.style.height = '100%';
        }
    }
    if (meterLabel) {
        meterLabel.textContent = '—';
        meterLabel.classList.remove('is-hot');
    }
}

/**
 * Publish/mix graph must never use the default speaker sink.
 * @param {AudioContextOptions} [options]
 */
function createSilentAudioContext(options = {}) {
    try {
        return new AudioContext({ ...options, sinkId: { type: 'none' } });
    } catch {
        return new AudioContext(options);
    }
}

function fillMeter(el, rms) {
    if (!el) {
        return;
    }
    const pct = Math.min(100, Math.round(rms * 220));
    el.style.width = `${pct}%`;
    el.style.height = '100%';
}

function rmsFromAnalyser(node) {
    if (!node) {
        return 0;
    }
    const data = new Uint8Array(node.frequencyBinCount);
    node.getByteTimeDomainData(data);
    let sum = 0;
    for (let i = 0; i < data.length; i++) {
        const v = (data[i] - 128) / 128;
        sum += v * v;
    }
    return Math.sqrt(sum / data.length);
}

function startMeters() {
    stopMeter();
    const tick = () => {
        const micRms = rmsFromAnalyser(micAnalyser);
        const auxRms = rmsFromAnalyser(auxAnalyser);
        const plRms = rmsFromAnalyser(playlistAnalyser);
        const masterRms = rmsFromAnalyser(masterAnalyser);
        fillMeter(micMeterEl, micRms);
        fillMeter(auxMeterEl, auxRms);
        fillMeter(playlistMeterEl, plRms);
        fillMeter(meterEl, masterRms);
        if (meterLabel) {
            const hot = masterRms > 0.02;
            meterLabel.textContent = hot ? 'SIGNAL' : '—';
            meterLabel.classList.toggle('is-hot', hot);
        }
        meterRaf = requestAnimationFrame(tick);
    };
    meterRaf = requestAnimationFrame(tick);
}

/**
 * @param {RTCRtpTransceiver} transceiver
 */
function forceOpusCodec(transceiver) {
    const caps = RTCRtpSender.getCapabilities?.('audio');
    if (!caps?.codecs?.length || !transceiver?.setCodecPreferences) {
        return;
    }
    const opus = caps.codecs.filter((c) => c.mimeType.toLowerCase() === 'audio/opus');
    if (opus.length === 0) {
        return;
    }
    try {
        transceiver.setCodecPreferences(opus);
    } catch (e) {
        console.warn('Could not set Opus codec preferences', e);
    }
}

/**
 * @param {string} sdp
 */
function stripNonOpusAudioCodecs(sdp) {
    const opusPts = new Set();
    for (const match of sdp.matchAll(/^a=rtpmap:(\d+) opus\/48000(?:\/\d+)?/gim)) {
        opusPts.add(match[1]);
    }
    if (opusPts.size === 0) {
        return sdp;
    }

    const lines = sdp.split(/\r?\n/);
    /** @type {string[]} */
    const out = [];
    let inAudio = false;
    for (const line of lines) {
        if (line.startsWith('m=audio ')) {
            inAudio = true;
            const parts = line.split(' ');
            const kept = parts.slice(0, 3).concat(parts.slice(3).filter((pt) => opusPts.has(pt)));
            out.push(kept.join(' '));
            continue;
        }
        if (line.startsWith('m=')) {
            inAudio = false;
        }
        if (inAudio) {
            const ptMatch = line.match(/^a=(?:rtpmap|fmtp|rtcp-fb):(\d+)\b/);
            if (ptMatch && !opusPts.has(ptMatch[1])) {
                continue;
            }
        }
        out.push(line);
    }
    return out.join('\r\n');
}

/**
 * @param {string} sdp
 */
function preferHighQualityOpus(sdp) {
    let out = stripNonOpusAudioCodecs(sdp);
    const opusPts = new Set();
    for (const match of out.matchAll(/^a=rtpmap:(\d+) opus\/48000(?:\/\d+)?/gim)) {
        opusPts.add(match[1]);
    }
    if (opusPts.size === 0) {
        return out;
    }

    // minptime=20 + FEC + no DTX: fewer packet-edge gaps that listeners hear as stutters.
    const fmtpValue = `minptime=20;useinbandfec=1;usedtx=0;stereo=1;sprop-stereo=1;maxaveragebitrate=${OPUS_MAX_BITRATE};maxplaybackrate=48000`;

    for (const pt of opusPts) {
        const fmtpRe = new RegExp(`^a=fmtp:${pt} .*`, 'im');
        if (fmtpRe.test(out)) {
            out = out.replace(fmtpRe, `a=fmtp:${pt} ${fmtpValue}`);
        } else {
            const rtpmapRe = new RegExp(`^(a=rtpmap:${pt} opus\\/48000(?:\\/\\d+)?)`, 'im');
            out = out.replace(rtpmapRe, `$1\r\na=fmtp:${pt} ${fmtpValue}`);
        }
    }

    return out;
}

/**
 * @param {RTCPeerConnection} peer
 */
async function applyMaxAudioBitrate(peer) {
    for (const sender of peer.getSenders()) {
        if (sender.track?.kind !== 'audio') {
            continue;
        }
        try {
            const params = sender.getParameters();
            if (!params.encodings || params.encodings.length === 0) {
                params.encodings = [{}];
            }
            for (const encoding of params.encodings) {
                encoding.maxBitrate = OPUS_MAX_BITRATE;
                encoding.priority = 'high';
                encoding.networkPriority = 'high';
            }
            await sender.setParameters(params);
        } catch (e) {
            console.warn('Could not set audio maxBitrate', e);
        }
    }
}

async function ensureMixer() {
    if (audioCtx && mixDest && micGain && playlistGain && masterGain && cueDest) {
        // Migrate older graphs that tapped cue pre-master.
        if (!cueMasterGain && micCueGain && auxCueGain && playlistCueGain) {
            cueMasterGain = audioCtx.createGain();
            try {
                micCueGain.disconnect();
                auxCueGain.disconnect();
                playlistCueGain.disconnect();
            } catch {
                /* ignore */
            }
            micCueGain.connect(cueMasterGain);
            auxCueGain.connect(cueMasterGain);
            playlistCueGain.connect(cueMasterGain);
            cueMasterGain.connect(cueDest);
            applyMasterGain();
        }
        if (audioCtx.state === 'suspended') {
            await audioCtx.resume();
        }
        if (!meterRaf) {
            startMeters();
        }
        return;
    }
    if (!audioCtx) {
        audioCtx = createSilentAudioContext({ sampleRate: 48000, latencyHint: 'interactive' });
    } else if (audioCtx.state === 'suspended') {
        await audioCtx.resume();
    }

    mixDest = audioCtx.createMediaStreamDestination();
    cueDest = audioCtx.createMediaStreamDestination();
    try {
        mixDest.channelCount = 2;
        mixDest.channelCountMode = 'explicit';
        mixDest.channelInterpretation = 'discrete';
        cueDest.channelCount = 2;
    } catch {
        /* optional */
    }

    masterGain = audioCtx.createGain();
    cueMasterGain = audioCtx.createGain();
    micGain = audioCtx.createGain();
    auxGain = audioCtx.createGain();
    playlistGain = audioCtx.createGain();
    micCueGain = audioCtx.createGain();
    auxCueGain = audioCtx.createGain();
    playlistCueGain = audioCtx.createGain();
    micCueGain.gain.value = 0;
    auxCueGain.gain.value = 0;
    playlistCueGain.gain.value = 0;

    masterAnalyser = audioCtx.createAnalyser();
    masterAnalyser.fftSize = 256;
    micAnalyser = audioCtx.createAnalyser();
    micAnalyser.fftSize = 256;
    auxAnalyser = audioCtx.createAnalyser();
    auxAnalyser.fftSize = 256;
    playlistAnalyser = audioCtx.createAnalyser();
    playlistAnalyser.fftSize = 256;

    // Channel → master bus → publish + meters.
    micGain.connect(masterGain);
    auxGain.connect(masterGain);
    playlistGain.connect(masterGain);
    masterGain.connect(mixDest);
    masterGain.connect(masterAnalyser);

    // Per-channel cue selects → Master → headphones (same level as broadcast bus).
    micGain.connect(micCueGain);
    auxGain.connect(auxCueGain);
    playlistGain.connect(playlistCueGain);
    micCueGain.connect(cueMasterGain);
    auxCueGain.connect(cueMasterGain);
    playlistCueGain.connect(cueMasterGain);
    cueMasterGain.connect(cueDest);

    micGain.connect(micAnalyser);
    auxGain.connect(auxAnalyser);
    playlistGain.connect(playlistAnalyser);

    applyMicGain();
    applyAuxGain();
    applyPlaylistGain();
    applyMasterGain();
    startMeters();
}

/**
 * @param {string} [deviceId]
 */
async function openDevice(deviceId = '') {
    const base = audioCaptureConstraints();
    if (deviceId) {
        try {
            return await navigator.mediaDevices.getUserMedia({
                audio: { ...base, deviceId: { exact: deviceId } },
                video: false,
            });
        } catch {
            try {
                return await navigator.mediaDevices.getUserMedia({
                    audio: { ...base, deviceId: { ideal: deviceId } },
                    video: false,
                });
            } catch {
                /* fall through */
            }
        }
    }
    try {
        return await navigator.mediaDevices.getUserMedia({ audio: base, video: false });
    } catch (first) {
        // Last resort on phones: bare audio track.
        if (isMobileUa()) {
            return navigator.mediaDevices.getUserMedia({ audio: true, video: false });
        }
        throw first;
    }
}

function disconnectMicGraph() {
    try {
        micSource?.disconnect();
        micSplitter?.disconnect();
        micMonoSum?.disconnect();
        micMerger?.disconnect();
    } catch {
        /* ignore */
    }
    micSource = null;
    micSplitter = null;
    micMonoSum = null;
    micMerger = null;
}

function disconnectAuxGraph() {
    try {
        auxSource?.disconnect();
        auxSplitter?.disconnect();
        auxMonoSum?.disconnect();
        auxMerger?.disconnect();
    } catch {
        /* ignore */
    }
    auxSource = null;
    auxSplitter = null;
    auxMonoSum = null;
    auxMerger = null;
}

function stopMicTracks() {
    if (micStream) {
        for (const t of micStream.getTracks()) {
            t.stop();
        }
        micStream = null;
    }
}

function stopAuxTracks() {
    if (auxStream) {
        for (const t of auxStream.getTracks()) {
            t.stop();
        }
        auxStream = null;
    }
}

/**
 * @param {MediaStreamAudioSourceNode} source
 * @param {GainNode} destGain
 * @param {'mic' | 'aux'} which
 */
function routeInputToGain(source, destGain, which) {
    if (!isMono()) {
        source.connect(destGain);
        return;
    }
    const splitter = audioCtx.createChannelSplitter(2);
    const monoSum = audioCtx.createGain();
    monoSum.gain.value = 0.707;
    const merger = audioCtx.createChannelMerger(2);
    source.connect(splitter);
    splitter.connect(monoSum, 0);
    splitter.connect(monoSum, 1);
    monoSum.connect(merger, 0, 0);
    monoSum.connect(merger, 0, 1);
    merger.connect(destGain);
    if (which === 'mic') {
        micSplitter = splitter;
        micMonoSum = monoSum;
        micMerger = merger;
    } else {
        auxSplitter = splitter;
        auxMonoSum = monoSum;
        auxMerger = merger;
    }
}

async function attachMicrophoneToMixer() {
    await ensureMixer();
    disconnectMicGraph();
    stopMicTracks();
    micStream = await openDevice(audioSelect?.value || '');
    micSource = audioCtx.createMediaStreamSource(micStream);
    routeInputToGain(micSource, micGain, 'mic');
    applyMicGain();
}

async function attachAuxToMixer() {
    await ensureMixer();
    disconnectAuxGraph();
    stopAuxTracks();
    const id = auxSelect?.value || '';
    if (!id) {
        applyAuxGain();
        return;
    }
    auxStream = await openDevice(id);
    auxSource = audioCtx.createMediaStreamSource(auxStream);
    routeInputToGain(auxSource, auxGain, 'aux');
    applyAuxGain();
}

/** Drop any remote audio — Studio must never play the live stream locally. */
function silenceRemoteAudio(peer) {
    peer.ontrack = (ev) => {
        try {
            ev.track.enabled = false;
            ev.track.stop();
        } catch {
            /* ignore */
        }
        for (const stream of ev.streams || []) {
            for (const t of stream.getTracks()) {
                try {
                    t.enabled = false;
                    t.stop();
                } catch {
                    /* ignore */
                }
            }
        }
    };
    for (const receiver of peer.getReceivers()) {
        if (receiver.track) {
            try {
                receiver.track.enabled = false;
                receiver.track.stop();
            } catch {
                /* ignore */
            }
        }
    }
}

async function loadDevices() {
    try {
        const devices = await navigator.mediaDevices.enumerateDevices();
        // Browsers hide real deviceIds/labels until getUserMedia has been granted.
        const inputs = devices.filter((d) => d.kind === 'audioinput' && d.deviceId);
        const outputs = devices.filter((d) => d.kind === 'audiooutput' && d.deviceId);
        const listed = inputs.length > 0;

        if (audioSelect) {
            const previous = audioSelect.value;
            audioSelect.innerHTML = '';
            if (!listed) {
                const placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = micPrimed ? 'No microphone found' : 'Allow microphone to list mics';
                audioSelect.appendChild(placeholder);
            } else {
                for (const [i, d] of inputs.entries()) {
                    const opt = document.createElement('option');
                    opt.value = d.deviceId;
                    opt.textContent = d.label || `Microphone ${i + 1}`;
                    audioSelect.appendChild(opt);
                }
                if (previous && [...audioSelect.options].some((o) => o.value === previous)) {
                    audioSelect.value = previous;
                }
            }
        }

        if (auxSelect) {
            const previous = auxSelect.value;
            auxSelect.innerHTML = '';
            const none = document.createElement('option');
            none.value = '';
            none.textContent = listed ? 'Select source' : (micPrimed ? 'No microphone found' : 'Allow microphone to list mics');
            auxSelect.appendChild(none);
            for (const [i, d] of inputs.entries()) {
                const opt = document.createElement('option');
                opt.value = d.deviceId;
                opt.textContent = d.label || `Microphone ${i + 1}`;
                auxSelect.appendChild(opt);
            }
            if (previous && [...auxSelect.options].some((o) => o.value === previous)) {
                auxSelect.value = previous;
            }
        }

        if (outputSelect) {
            const previous = outputSelect.value;
            outputSelect.innerHTML = '';
            const def = document.createElement('option');
            def.value = '';
            def.textContent = 'Default output';
            outputSelect.appendChild(def);
            for (const [i, d] of outputs.entries()) {
                const opt = document.createElement('option');
                opt.value = d.deviceId;
                opt.textContent = d.label || `Output ${i + 1}`;
                outputSelect.appendChild(opt);
            }
            if (previous && [...outputSelect.options].some((o) => o.value === previous)) {
                outputSelect.value = previous;
            }
        }

        setMicEnableVisible(!listed);
    } catch {
        setStatus('Could not list audio devices.');
        setMicEnableVisible(true);
    }
}

async function ensureMicListed() {
    if (micPrimed) {
        await loadDevices();
        return micPrimed;
    }
    return primeMicrophone({ interactive: true });
}

audioSelect?.addEventListener('pointerdown', () => {
    if (!micPrimed) {
        void ensureMicListed();
    }
});
audioSelect?.addEventListener('focus', () => {
    if (!micPrimed) {
        void ensureMicListed();
    }
});
audioSelect?.addEventListener('change', async () => {
    if (!audioSelect.value && !micPrimed) {
        await ensureMicListed();
        return;
    }
    if (!isLive || !pc) {
        return;
    }
    try {
        await attachMicrophoneToMixer();
        setStatus('Switched microphone — still on air.');
    } catch (e) {
        setStatus(friendlyError(e));
    }
});

auxSelect?.addEventListener('pointerdown', () => {
    if (!micPrimed) {
        void ensureMicListed();
    }
});
auxSelect?.addEventListener('focus', () => {
    if (!micPrimed) {
        void ensureMicListed();
    }
});
auxSelect?.addEventListener('change', async () => {
    if (!auxSelect.value && !micPrimed) {
        await ensureMicListed();
        return;
    }
    if (!isLive) {
        return;
    }
    try {
        await attachAuxToMixer();
        setStatus(auxSelect.value ? 'Input 2 armed — still on air.' : 'Input 2 cleared.');
    } catch (e) {
        setStatus(friendlyError(e));
    }
});

function wireFileChannelAudio(channel) {
    if (!audioCtx || !playlistGain || channel.source) {
        return;
    }
    // MediaElementSource takes over element output — do not mute the element
    // (muted/volume=0 silences the Web Audio graph in Chromium).
    channel.audio.muted = false;
    channel.audio.volume = 1;
    channel.source = audioCtx.createMediaElementSource(channel.audio);
    channel.gain = audioCtx.createGain();
    channel.gain.gain.value = 1;
    channel.source.connect(channel.gain);
    channel.gain.connect(playlistGain);
}

function setFileChannelReady(channel, ready, detail = '') {
    channel.ready = ready;
    const statusEl = channel.card.querySelector('[data-ready-status]');
    const playBtn = channel.card.querySelector('[data-play]');
    const restartBtn = channel.card.querySelector('[data-restart]');
    if (statusEl) {
        statusEl.textContent = ready ? (detail || 'Ready') : (detail || 'Loading…');
        statusEl.classList.toggle('is-ready', ready);
        statusEl.classList.toggle('is-loading', !ready);
    }
    if (playBtn) {
        playBtn.disabled = !ready;
    }
    if (restartBtn) {
        restartBtn.disabled = !ready;
    }
}

function formatBytes(bytes) {
    const n = Number(bytes) || 0;
    if (n >= 1024 * 1024) {
        return `${(n / (1024 * 1024)).toFixed(1)} MB`;
    }
    return `${Math.max(1, Math.round(n / 1024))} KB`;
}

function formatTrackDuration(seconds) {
    const s = Number(seconds);
    if (!Number.isFinite(s) || s <= 0) {
        return '';
    }
    return `${Math.floor(s / 60)}:${String(Math.floor(s % 60)).padStart(2, '0')}`;
}

function removeFileChannel(id) {
    const channel = fileChannels.get(id);
    if (!channel) {
        return;
    }
    channel.audio.pause();
    try {
        channel.source?.disconnect();
        channel.gain?.disconnect();
    } catch {
        /* ignore */
    }
    if (channel.objectUrl && String(channel.objectUrl).startsWith('blob:')) {
        URL.revokeObjectURL(channel.objectUrl);
    }
    channel.card.remove();
    fileChannels.delete(id);
    updatePlaylistMeta();
}

/**
 * @param {{ name: string, src: string, objectUrl?: string|null, sizeLabel?: string, assetId?: number|null, duration?: number }} opts
 */
function addPlaylistTrack(opts) {
    const name = opts.name || 'Audio';
    const src = opts.src;
    if (!src) {
        return null;
    }

    const id = `file-${++fileChannelSeq}`;
    const objectUrl = opts.objectUrl ?? null;
    const sizeLabel = opts.sizeLabel || '';
    const audio = new Audio();
    audio.loop = false;
    audio.preload = 'auto';
    audio.muted = false;
    audio.volume = 1;
    audio.src = src;

    const card = document.createElement('div');
    card.className = 'mixer-track is-loading';
    card.dataset.id = id;
    card.innerHTML = `
        <div class="mixer-track-head">
            <span class="mixer-track-name" title=""></span>
            <button type="button" class="mixer-track-remove" data-remove>Remove</button>
        </div>
        <p class="mixer-track-status is-loading" data-ready-status>Loading…</p>
        <p class="mixer-track-meta" data-meta>${sizeLabel}</p>
        <div class="mixer-track-controls">
            <button type="button" data-play disabled>Play</button>
            <button type="button" data-pause>Pause</button>
            <button type="button" data-restart disabled>Restart</button>
        </div>
    `;
    const nameEl = card.querySelector('.mixer-track-name');
    if (nameEl) {
        nameEl.textContent = name;
        nameEl.title = name;
    }

    channelsEl?.appendChild(card);

    const channel = {
        id,
        name,
        objectUrl,
        assetId: opts.assetId ?? null,
        audio,
        source: null,
        gain: null,
        card,
        duration: Number(opts.duration) > 0 ? Number(opts.duration) : 0,
        ready: false,
    };
    fileChannels.set(id, channel);
    updatePlaylistMeta();
    setFileChannelReady(channel, false, 'Loading…');

    const markReady = () => {
        if (channel.ready) {
            return;
        }
        channel.duration = Number.isFinite(audio.duration) ? audio.duration : channel.duration;
        const dur = formatTrackDuration(channel.duration);
        const metaEl = card.querySelector('[data-meta]');
        if (metaEl) {
            metaEl.textContent = [dur, sizeLabel].filter(Boolean).join(' · ') || 'Ready';
        }
        card.classList.remove('is-loading');
        card.classList.add('is-ready');
        setFileChannelReady(channel, true, 'Ready');
        updatePlaylistMeta();
        setStatus(`“${name}” ready — press Play (turn on Playlist CUE + headphones to monitor).`);
    };

    audio.addEventListener('loadedmetadata', () => {
        channel.duration = audio.duration || channel.duration;
        updatePlaylistMeta();
        if (audio.readyState >= HTMLMediaElement.HAVE_FUTURE_DATA) {
            markReady();
        } else {
            setFileChannelReady(channel, false, 'Buffering…');
        }
    });
    audio.addEventListener('canplay', markReady);
    audio.addEventListener('canplaythrough', markReady);
    audio.addEventListener('error', () => {
        setFileChannelReady(channel, false, 'Failed to load');
        card.classList.add('is-error');
        setStatus(`Could not load “${name}”. Try another file (mp3/wav/m4a).`);
    });

    void audio.load();

    if (audioCtx && playlistGain) {
        wireFileChannelAudio(channel);
    }

    card.querySelector('[data-remove]')?.addEventListener('click', () => {
        removeFileChannel(id);
    });

    card.querySelector('[data-play]')?.addEventListener('click', async () => {
        if (!channel.ready) {
            setStatus('Still loading this sound — wait until it shows Ready.');
            return;
        }
        try {
            await ensureMixer();
            wireFileChannelAudio(channel);
            await channel.audio.play();
            setStatus(
                playlistCueOn
                    ? `Playing “${name}” in the mix (cue on).`
                    : `Playing “${name}” in the mix. Enable Playlist CUE + headphones to hear it in Studio.`,
            );
        } catch (e) {
            setStatus(e instanceof Error ? e.message : 'Could not play file.');
        }
    });

    card.querySelector('[data-pause]')?.addEventListener('click', () => {
        channel.audio.pause();
    });

    card.querySelector('[data-restart]')?.addEventListener('click', async () => {
        if (!channel.ready) {
            setStatus('Still loading this sound — wait until it shows Ready.');
            return;
        }
        try {
            await ensureMixer();
            wireFileChannelAudio(channel);
            channel.audio.currentTime = 0;
            await channel.audio.play();
        } catch (e) {
            setStatus(e instanceof Error ? e.message : 'Could not restart file.');
        }
    });

    return channel;
}

function queueLibraryAsset(asset) {
    if (!asset?.url) {
        return;
    }
    addPlaylistTrack({
        name: asset.title || asset.original_filename || 'Audio',
        src: asset.url,
        objectUrl: null,
        sizeLabel: formatBytes(asset.size_bytes),
        assetId: asset.id,
        duration: asset.duration_seconds || 0,
    });
    setStatus(`Queued “${asset.title || asset.original_filename}” in the session playlist.`);
}

/**
 * @param {File} file
 * @returns {Promise<number|null>}
 */
function probeAudioDuration(file) {
    return new Promise((resolve) => {
        const url = URL.createObjectURL(file);
        const audio = new Audio();
        let settled = false;
        const finish = (value) => {
            if (settled) {
                return;
            }
            settled = true;
            URL.revokeObjectURL(url);
            resolve(value);
        };
        audio.addEventListener('loadedmetadata', () => {
            finish(Number.isFinite(audio.duration) ? audio.duration : null);
        });
        audio.addEventListener('error', () => finish(null));
        audio.preload = 'metadata';
        audio.src = url;
        setTimeout(() => finish(null), 5000);
    });
}

btnAddFile?.addEventListener('click', () => {
    fileInput?.click();
});

document.getElementById('btn-upload-library')?.addEventListener('click', () => {
    fileInput?.click();
});

fileInput?.addEventListener('change', () => {
    const files = Array.from(fileInput.files || []);
    fileInput.value = '';
    void uploadFilesToLibrary(files, true);
});

/**
 * @param {MediaStream} stream
 */
async function publishWhip(stream) {
    const track = stream.getAudioTracks()[0];
    if (!track) {
        throw new Error('No audio track. Check the microphone.');
    }

    pc = new RTCPeerConnection({
        iceServers: [{ urls: 'stun:stun.l.google.com:19302' }],
    });
    silenceRemoteAudio(pc);
    bindPublisherPeerWatch(pc);

    try {
        await track.applyConstraints(audioCaptureConstraints());
    } catch {
        /* optional */
    }

    const transceiver = pc.addTransceiver(track, {
        direction: 'sendonly',
        streams: [stream],
        sendEncodings: [{ maxBitrate: OPUS_MAX_BITRATE, priority: 'high', networkPriority: 'high' }],
    });

    if (!transceiver.sender) {
        pc.addTrack(track, stream);
    }

    forceOpusCodec(transceiver);
    await applyMaxAudioBitrate(pc);

    const offer = await pc.createOffer();
    const highQualitySdp = preferHighQualityOpus(offer.sdp || '');
    if (!/opus\/48000/i.test(highQualitySdp)) {
        throw new Error('Browser did not offer Opus. Try Chrome/Edge, or reload Studio.');
    }
    await pc.setLocalDescription({ type: 'offer', sdp: highQualitySdp });
    await applyMaxAudioBitrate(pc);

    await new Promise((resolve) => {
        if (!pc || pc.iceGatheringState === 'complete') {
            resolve();
            return;
        }
        const done = () => {
            if (pc?.iceGatheringState === 'complete') {
                pc.removeEventListener('icegatheringstatechange', done);
                resolve();
            }
        };
        pc.addEventListener('icegatheringstatechange', done);
        setTimeout(resolve, 2000);
    });

    const res = await fetch(whipUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/sdp' },
        body: pc.localDescription?.sdp ?? '',
    });

    if (!res.ok) {
        const text = await res.text();
        throw new Error(text || `WHIP failed (${res.status})`);
    }

    const loc = res.headers.get('Location');
    whipResourceUrl = loc ? new URL(loc, whipUrl).href : null;

    const answerSdp = await res.text();
    if (/G722|PCMU|PCMA/i.test(answerSdp) && !/opus\/48000/i.test(answerSdp)) {
        throw new Error('Server answered without Opus (phone codec). End broadcast and Go on air again after refresh.');
    }
    await pc.setRemoteDescription({ type: 'answer', sdp: answerSdp });
    silenceRemoteAudio(pc);
    await applyMaxAudioBitrate(pc);
}

function clearPublisherReconnect() {
    if (publisherReconnectTimer) {
        window.clearTimeout(publisherReconnectTimer);
        publisherReconnectTimer = null;
    }
    if (publisherDisconnectGraceTimer) {
        window.clearTimeout(publisherDisconnectGraceTimer);
        publisherDisconnectGraceTimer = null;
    }
    publisherReconnectAttempt = 0;
    publisherRepublishing = false;
}

function bindPublisherPeerWatch(peer) {
    peer.onconnectionstatechange = () => {
        if (peer !== pc || !isLive || publishMode !== 'mixer') {
            return;
        }
        const state = peer.connectionState;
        if (state === 'connected') {
            if (publisherDisconnectGraceTimer) {
                window.clearTimeout(publisherDisconnectGraceTimer);
                publisherDisconnectGraceTimer = null;
            }
            publisherReconnectAttempt = 0;
            return;
        }
        if (state === 'failed') {
            schedulePublisherReconnect('Broadcast link failed — reconnecting…');
            return;
        }
        if (state === 'disconnected') {
            // Brief ICE blips often recover — wait before tearing down WHIP.
            if (publisherDisconnectGraceTimer) {
                return;
            }
            publisherDisconnectGraceTimer = window.setTimeout(() => {
                publisherDisconnectGraceTimer = null;
                if (peer !== pc || !isLive) {
                    return;
                }
                if (pc?.connectionState === 'connected' || pc?.connectionState === 'connecting') {
                    return;
                }
                schedulePublisherReconnect('Network dropped — republishing…');
            }, 12_000);
        }
    };
}

function schedulePublisherReconnect(message) {
    if (!isLive || publishMode !== 'mixer' || publisherRepublishing) {
        return;
    }
    if (publisherReconnectTimer) {
        return;
    }
    setStatus(message);
    const delay = Math.min(30_000, 1500 * 2 ** publisherReconnectAttempt);
    publisherReconnectAttempt = Math.min(publisherReconnectAttempt + 1, 6);
    publisherReconnectTimer = window.setTimeout(() => {
        publisherReconnectTimer = null;
        void republishWhipWhileLive();
    }, delay);
}

async function republishWhipWhileLive() {
    if (!isLive || publishMode !== 'mixer' || publisherRepublishing) {
        return;
    }
    if (!mixDest?.stream) {
        schedulePublisherReconnect('Mixer not ready — retrying publish…');
        return;
    }
    publisherRepublishing = true;
    setStatus('Reconnecting broadcast…');
    try {
        if (whipResourceUrl) {
            try {
                await fetch(whipResourceUrl, { method: 'DELETE' });
            } catch {
                /* ignore */
            }
            whipResourceUrl = null;
        }
        if (pc) {
            pc.onconnectionstatechange = null;
            pc.close();
            pc = null;
        }
        await publishWhip(mixDest.stream);
        publisherReconnectAttempt = 0;
        setStatus(
            anyCueOn()
                ? 'You’re broadcasting again. Cue is on — use headphones to avoid feedback.'
                : 'You’re broadcasting again.',
        );
    } catch (e) {
        console.error(e);
        setStatus(friendlyError(e));
        schedulePublisherReconnect('Could not republish — retrying…');
    } finally {
        publisherRepublishing = false;
    }
}

async function primeMicrophone({ interactive = false } = {}) {
    if (!window.isSecureContext) {
        setStatus(`Studio needs HTTPS for the microphone. Open ${httpsStudioUrl()}`);
        setMicEnableVisible(false);
        if (btnStart) {
            btnStart.disabled = true;
        }
        return false;
    }
    if (!navigator.mediaDevices?.getUserMedia) {
        setStatus('This browser does not support microphone capture.');
        setMicEnableVisible(false);
        return false;
    }

    // Mobile: wait for a tap (Allow microphone / source picker / Go on air).
    // Auto getUserMedia on load is blocked by overlays and leaves empty device lists.
    if (isMobileUa() && !interactive && !micPrimed) {
        await loadDevices();
        setMicEnableVisible(true);
        setStatus('Tap Allow microphone (on Mix) to list your mics. Close WhatsApp/bubbles if Android blocks the prompt.');
        return false;
    }

    try {
        setStatus('Requesting microphone…');
        const priming = await openDevice('');
        for (const t of priming.getTracks()) {
            t.stop();
        }
        micPrimed = true;
        await loadDevices();
        setMicEnableVisible(false);
        setToggle(auxMuteBtn, auxMuted);
        const micCount = audioSelect?.options?.length || 0;
        setStatus(
            isMobileUa()
                ? `Microphone ready (${micCount} input${micCount === 1 ? '' : 's'}). Pick Input 1, then Go on air.`
                : 'Ready. Cue is off — Studio stays silent. Go on air when ready; use the listen link (or cue + headphones) to monitor.',
        );
        return true;
    } catch (e) {
        micPrimed = false;
        await loadDevices();
        setMicEnableVisible(true);
        setStatus(friendlyError(e));
        return false;
    }
}

document.getElementById('btn-enable-mic')?.addEventListener('click', async () => {
    await primeMicrophone({ interactive: true });
});

const mobileTabs = document.querySelectorAll('.mixer-mobile-tab');
const mobilePanes = document.querySelectorAll('[data-mobile-pane]');

function setMobilePane(pane) {
    mobileTabs.forEach((tab) => {
        tab.classList.toggle('is-active', tab.dataset.mobilePane === pane);
    });
    mobilePanes.forEach((el) => {
        const match = el.dataset.mobilePane === pane;
        el.classList.toggle('is-mobile-active', match);
    });
    stageEl?.setAttribute('data-mobile-pane', pane);
}

mobileTabs.forEach((tab) => {
    tab.addEventListener('click', () => {
        setMobilePane(tab.dataset.mobilePane || 'mix');
    });
});
setMobilePane('mix');

void primeMicrophone({ interactive: false });

if (navigator.mediaDevices && 'addEventListener' in navigator.mediaDevices) {
    navigator.mediaDevices.addEventListener('devicechange', loadDevices);
}

btnCreateEvent?.addEventListener('click', async () => {
    if (!broadcastAllowed) {
        setStatus('An active subscription is required to create an event.');
        return;
    }
    const suggestedTitle = currentEvent?.title
        || `Live from ${channelShareName} · Sound Mix Live`;
    const title = window.prompt('Event title', suggestedTitle);
    if (title === null) {
        return;
    }
    const trimmed = title.trim();
    if (!trimmed) {
        setStatus('Event title is required.');
        return;
    }
    btnCreateEvent.disabled = true;
    setStatus('Creating event…');
    try {
        const data = await sessionPost(sessionCreateEventUrl, { title: trimmed });
        applyEventToUi(data.event);
        setStatus(`Event ready: ${data.event.title}. Hit Go live when you’re ready.`);
    } catch (e) {
        setStatus(e instanceof Error ? e.message : 'Could not create event.');
    } finally {
        refreshSessionButtons();
    }
});

btnStart?.addEventListener('click', async () => {
    if (!broadcastAllowed) {
        setStatus('An active subscription is required to go on air.');
        if (billingUrl) {
            window.location.href = billingUrl;
        }
        return;
    }
    if (!window.isSecureContext) {
        setStatus(`Studio needs HTTPS for the microphone. Open ${httpsStudioUrl()}`);
        return;
    }
    if (!whipUrl) {
        setStatus('WHIP URL is not configured.');
        return;
    }
    btnStart.disabled = true;
    const resuming = sessionPaused;
    const reconnecting = !resuming && currentEvent?.status === 'live';
    setStatus(
        resuming
            ? 'Resuming event…'
            : reconnecting
                ? 'Reconnecting broadcast…'
                : 'Starting event & going live…',
    );

    try {
        const sessionData = resuming
            ? await sessionPost(sessionResumeUrl)
            : await sessionPost(sessionGoLiveUrl, {
                title: currentEvent?.title || undefined,
                event_id: currentEvent?.id || undefined,
            });
        if (sessionData.event) {
            applyEventToUi(sessionData.event);
        }

        if (!micPrimed) {
            const ok = await primeMicrophone({ interactive: true });
            if (!ok) {
                refreshSessionButtons();
                return;
            }
        }
        publishMode = 'mixer';
        await ensureMixer();
        await attachMicrophoneToMixer();
        await attachAuxToMixer();
        for (const channel of fileChannels.values()) {
            wireFileChannelAudio(channel);
        }
        await syncCuePlayback();
        await publishWhip(mixDest.stream);

        setStatus(
            anyCueOn()
                ? 'You’re broadcasting. Cue is on — use headphones to avoid feedback.'
                : 'You’re broadcasting. Share the event link — Pause keeps this event open.',
        );
        setOnAir(true);
        startLocalRecording();
    } catch (e) {
        console.error(e);
        setStatus(friendlyError(e));
        setOnAir(false);
        publishMode = null;
        await teardownPublisher({ keepEvent: true });
    }
});

btnPause?.addEventListener('click', async () => {
    btnPause.disabled = true;
    setStatus('Pausing — same event stays open…');
    try {
        const data = await sessionPost(sessionPauseUrl);
        if (data.event) {
            applyEventToUi(data.event);
        }
        sessionPaused = true;
        await teardownPublisher({ keepEvent: true });
        setOnAir(false);
        setStatus('Paused. Resume to continue this event, or End live to close it.');
    } catch (e) {
        setStatus(e instanceof Error ? e.message : 'Could not pause.');
        refreshSessionButtons();
    }
});

btnEnd?.addEventListener('click', async () => {
    if (!window.confirm('End this live event? The next Go live will create a new event.')) {
        return;
    }
    btnEnd.disabled = true;
    setStatus('Ending event…');
    try {
        const data = await sessionPost(sessionEndUrl);
        const ended = data.event || currentEvent;
        await teardownPublisher({ keepEvent: false });
        sessionPaused = false;
        currentEvent = null;
        applyEventToUi(null);
        setOnAir(false);
        if (ended?.id) {
            // Keep id so auto-upload still attaches to the ended event.
            currentEvent = { ...ended, status: 'ended' };
        }
        if (pendingLocalRecording) {
            setStatus('Event ended — uploading recording…');
            const uploaded = await uploadLocalRecording();
            if (!uploaded) {
                setStatus(
                    ended?.url
                        ? `Event ended. Upload failed — retry Upload, or open ${ended.url}`
                        : 'Event ended. Upload failed — retry Upload when ready.',
                );
            }
        } else {
            setStatus(
                ended?.url
                    ? `Event ended — ${ended.url}`
                    : 'Event ended.',
            );
        }
    } catch (e) {
        setStatus(e instanceof Error ? e.message : 'Could not end event.');
        refreshSessionButtons();
    }
});

btnStop?.addEventListener('click', async () => {
    btnEnd?.click();
});

async function teardownPublisher({ keepEvent }) {
    publishMode = null;
    clearPublisherReconnect();
    stopMeter();
    await stopLocalRecording();

    micCueOn = false;
    auxCueOn = false;
    playlistCueOn = false;
    setToggle(micCueBtn, false);
    setToggle(auxCueBtn, false);
    setToggle(playlistCueBtn, false);
    await syncCuePlayback();

    if (whipResourceUrl) {
        try {
            await fetch(whipResourceUrl, { method: 'DELETE' });
        } catch {
            /* ignore */
        }
        whipResourceUrl = null;
    }
    if (pc) {
        pc.close();
        pc = null;
    }

    disconnectMicGraph();
    disconnectAuxGraph();
    stopMicTracks();
    stopAuxTracks();

    for (const channel of fileChannels.values()) {
        channel.audio.pause();
    }

    if (!keepEvent) {
        sessionPaused = false;
    }
}

/** @deprecated use teardownPublisher */
async function teardownLive() {
    await teardownPublisher({ keepEvent: true });
    setOnAir(false);
}

const libraryListEl = document.getElementById('library-list');
const librarySearchEl = document.getElementById('library-search');
const libraryDestinationEl = document.getElementById('library-destination');
const libraryStorageMeterEl = document.getElementById('library-storage-meter');
const libraryDriveStatusEl = document.getElementById('library-drive-status');
const btnDriveConnect = document.getElementById('btn-drive-connect');
const btnDriveBrowse = document.getElementById('btn-drive-browse');
const libraryListUrl = root?.dataset.libraryListUrl;
const libraryUploadUrl = root?.dataset.libraryUploadUrl;
const libraryImportDriveUrl = root?.dataset.libraryImportDriveUrl;
/** @type {Array<Record<string, any>>} */
let libraryAssets = [];
let librarySearchTimer = 0;
let libraryDrive = { connected: false, connect_url: null, email: null };

const btnAddGallery = document.getElementById('btn-add-gallery');
const galleryInput = document.getElementById('gallery-input');
const btnAddReel = document.getElementById('btn-add-reel');
const reelInput = document.getElementById('reel-input');
const studioGalleryList = document.getElementById('studio-gallery-list');
const galleryUploadUrl = root?.dataset.galleryUploadUrl;
const galleryListUrl = root?.dataset.galleryListUrl;
const galleryCsrf = root?.dataset.csrf || document.querySelector('meta[name="csrf-token"]')?.content;

/** Open service event only — ended events must not keep prior photos in Studio. */
function galleryScopedEventId() {
    if (!currentEvent?.id) {
        return null;
    }
    if (currentEvent.status === 'ended') {
        return null;
    }
    return Number(currentEvent.id);
}

function renderStudioGallery(images) {
    if (!studioGalleryList) {
        return;
    }
    studioGalleryList.innerHTML = '';
    // API returns newest first; prepend in reverse so the left edge stays newest.
    for (const image of [...images].reverse()) {
        appendGalleryThumb(image);
    }
}

async function refreshStudioGallery() {
    if (!studioGalleryList) {
        return;
    }
    const eventId = galleryScopedEventId();
    if (!eventId || !galleryListUrl) {
        studioGalleryList.innerHTML = '';
        return;
    }
    try {
        const url = new URL(galleryListUrl, window.location.origin);
        url.searchParams.set('event_id', String(eventId));
        const res = await fetch(url.toString(), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            cache: 'no-store',
        });
        if (!res.ok) {
            studioGalleryList.innerHTML = '';
            return;
        }
        const data = await res.json();
        renderStudioGallery(Array.isArray(data.images) ? data.images : []);
    } catch {
        studioGalleryList.innerHTML = '';
    }
}

function filteredLibraryAssets() {
    const q = (librarySearchEl?.value || '').trim().toLowerCase();
    if (!q) {
        return libraryAssets;
    }
    return libraryAssets.filter((a) => {
        const title = String(a.title || '').toLowerCase();
        const name = String(a.original_filename || '').toLowerCase();
        return title.includes(q) || name.includes(q);
    });
}

function renderLibraryList(assets = filteredLibraryAssets()) {
    if (!libraryListEl) {
        return;
    }
    libraryListEl.innerHTML = '';
    if (!assets.length) {
        const empty = document.createElement('p');
        empty.className = 'mixer-hint';
        empty.textContent = librarySearchEl?.value?.trim()
            ? 'No matching songs in the library.'
            : 'Library is empty — upload sounds to keep them after refresh.';
        libraryListEl.appendChild(empty);
        return;
    }

    for (const asset of assets) {
        const row = document.createElement('div');
        row.className = 'mixer-library-row';
        row.setAttribute('role', 'listitem');
        row.dataset.id = String(asset.id);

        const dur = formatTrackDuration(asset.duration_seconds);
        const meta = [dur, formatBytes(asset.size_bytes)].filter(Boolean).join(' · ');

        row.innerHTML = `
            <div class="mixer-library-copy">
                <p class="mixer-library-title"></p>
                <p class="mixer-library-meta"></p>
            </div>
            <div class="mixer-library-actions">
                <button type="button" data-queue>Queue</button>
                <button type="button" data-delete>Delete</button>
            </div>
        `;
        row.querySelector('.mixer-library-title').textContent = asset.title || asset.original_filename || 'Audio';
        const provider =
            asset.storage_provider === 'drive'
                ? 'Drive'
                : asset.storage_provider === 'platform'
                  ? 'Platform'
                  : 'Server';
        row.querySelector('.mixer-library-meta').textContent = `${meta} · ${provider}`;

        row.querySelector('[data-queue]')?.addEventListener('click', () => {
            queueLibraryAsset(asset);
        });
        row.querySelector('[data-delete]')?.addEventListener('click', async () => {
            if (!asset.delete_url) {
                setStatus('Could not delete — missing permission link.');
                return;
            }
            if (!window.confirm(`Delete “${asset.title || asset.original_filename}” from the library?`)) {
                return;
            }
            try {
                const res = await fetch(asset.delete_url, {
                    method: 'DELETE',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': galleryCsrf || '',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                if (!res.ok) {
                    throw new Error('delete failed');
                }
                libraryAssets = libraryAssets.filter((a) => a.id !== asset.id);
                renderLibraryList();
                setStatus('Removed from audio library.');
            } catch {
                setStatus('Could not delete from library.');
            }
        });

        libraryListEl.appendChild(row);
    }
}

async function refreshLibrary() {
    if (!libraryListUrl) {
        return;
    }
    try {
        // Keep the signed URL query string intact (do not append search params).
        const res = await fetch(libraryListUrl, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        if (!res.ok) {
            throw new Error('list failed');
        }
        const data = await res.json();
        libraryAssets = Array.isArray(data.assets) ? data.assets : [];
        if (data.storage && libraryStorageMeterEl) {
            libraryStorageMeterEl.textContent = `Platform storage: ${data.storage.used_label} of ${data.storage.limit_label}`;
        }
        if (data.drive) {
            libraryDrive = data.drive;
            updateDriveUi();
        }
        renderLibraryList();
    } catch {
        if (libraryListEl) {
            libraryListEl.innerHTML = '<p class="mixer-hint">Could not load audio library.</p>';
        }
    }
}

function updateDriveUi() {
    if (libraryDriveStatusEl) {
        libraryDriveStatusEl.textContent = libraryDrive.connected
            ? `Google Drive: connected${libraryDrive.email ? ` (${libraryDrive.email})` : ''}`
            : 'Google Drive: not connected — uploads to Drive need Connect Drive (login).';
    }
    if (btnDriveConnect) {
        btnDriveConnect.hidden = !libraryDrive.connect_url || libraryDrive.connected;
        btnDriveConnect.onclick = () => {
            if (libraryDrive.connect_url) {
                window.location.href = libraryDrive.connect_url;
            }
        };
    }
    if (btnDriveBrowse) {
        btnDriveBrowse.hidden = !libraryDrive.connected;
        btnDriveBrowse.onclick = () => {
            void importDriveFilePrompt();
        };
    }
    if (libraryDestinationEl && libraryDrive.connected === false && libraryDestinationEl.value === 'drive') {
        libraryDestinationEl.value = 'platform';
    }
}

async function importDriveFilePrompt() {
    if (!libraryImportDriveUrl || !libraryDrive.connected) {
        setStatus('Connect Google Drive first.');
        return;
    }
    const fileId = window.prompt('Paste a Google Drive file ID from your “Sound Mix Live” folder:');
    if (!fileId || !fileId.trim()) {
        return;
    }
    try {
        const res = await fetch(libraryImportDriveUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': galleryCsrf || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ file_id: fileId.trim() }),
        });
        if (!res.ok) {
            const err = await res.json().catch(() => ({}));
            throw new Error(err.message || 'Import failed');
        }
        const data = await res.json();
        if (data.asset) {
            libraryAssets = [data.asset, ...libraryAssets.filter((a) => a.id !== data.asset.id)];
            renderLibraryList();
            setStatus('Imported from Google Drive.');
        }
    } catch (e) {
        setStatus(e instanceof Error ? e.message : 'Could not import from Drive.');
    }
}

/**
 * @param {File[]} files
 * @param {boolean} queueAfterUpload
 */
async function uploadFilesToLibrary(files, queueAfterUpload) {
    if (!libraryUploadUrl) {
        setStatus('Audio library upload is not available on this Studio link.');
        return;
    }

    const audioFiles = files.filter(
        (file) => file.type.startsWith('audio/') || /\.(mp3|wav|m4a|aac|ogg|flac|webm|mp4)$/i.test(file.name),
    );
    if (!audioFiles.length) {
        setStatus('Choose an audio file (mp3, wav, m4a, …).');
        return;
    }

    setStatus(`Saving ${audioFiles.length} sound${audioFiles.length === 1 ? '' : 's'} to the library…`);

    for (const file of audioFiles) {
        try {
            const duration = await probeAudioDuration(file);
            const body = new FormData();
            body.append('audio', file);
            body.append('title', file.name.replace(/\.[^.]+$/, '') || file.name);
            body.append('destination', libraryDestinationEl?.value || 'platform');
            if (duration != null) {
                body.append('duration_seconds', String(Math.round(duration)));
            }

            const res = await fetch(libraryUploadUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': galleryCsrf || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body,
            });
            if (!res.ok) {
                const raw = await res.text();
                let msg = `Upload failed (${res.status})`;
                try {
                    const err = JSON.parse(raw);
                    msg =
                        err?.message ||
                        err?.errors?.storage?.[0] ||
                        err?.errors?.audio?.[0] ||
                        msg;
                } catch {
                    if (/POST data is too large|Content Too Large|413/i.test(raw) || res.status === 413) {
                        msg = 'File is too large for the server (max about 50 MB). Try a smaller file or compress it.';
                    } else if (raw.trim()) {
                        msg = raw.trim().slice(0, 160);
                    }
                }
                throw new Error(msg);
            }
            const data = await res.json();
            const asset = data.asset;
            if (asset) {
                libraryAssets = [asset, ...libraryAssets.filter((a) => a.id !== asset.id)];
                if (queueAfterUpload) {
                    queueLibraryAsset(asset);
                }
            }
        } catch (e) {
            setStatus(e instanceof Error ? e.message : `Could not upload “${file.name}”.`);
            return;
        }
    }

    renderLibraryList();
    setStatus(
        queueAfterUpload
            ? 'Saved to library and queued. Wait for Ready, then Play.'
            : 'Saved to audio library.',
    );
}

librarySearchEl?.addEventListener('input', () => {
    window.clearTimeout(librarySearchTimer);
    librarySearchTimer = window.setTimeout(() => {
        renderLibraryList();
    }, 150);
});

void refreshLibrary();

const REEL_MAX_SECONDS = 60;

function readVideoDuration(file) {
    return new Promise((resolve, reject) => {
        const url = URL.createObjectURL(file);
        const video = document.createElement('video');
        video.preload = 'metadata';
        video.onloadedmetadata = () => {
            const duration = video.duration;
            URL.revokeObjectURL(url);
            resolve(duration);
        };
        video.onerror = () => {
            URL.revokeObjectURL(url);
            reject(new Error('Could not read video'));
        };
        video.src = url;
    });
}

function appendGalleryThumb(payload) {
    if (!studioGalleryList || !payload?.url) {
        return;
    }
    const figure = document.createElement('figure');
    figure.className = `mixer-gallery-thumb ${payload.type === 'video' ? 'is-video' : ''}`;
    figure.dataset.id = String(payload.id || '');
    if (payload.type === 'video') {
        figure.innerHTML = `<video src="${payload.url}" muted playsinline preload="metadata"></video><span class="mixer-reel-badge">Reel</span>`;
    } else {
        figure.innerHTML = `<img src="${payload.url}" alt="${payload.caption || 'Gallery photo'}">`;
    }
    studioGalleryList.prepend(figure);
}

btnAddGallery?.addEventListener('click', () => {
    galleryInput?.click();
});

btnAddReel?.addEventListener('click', () => {
    reelInput?.click();
});

galleryInput?.addEventListener('change', async () => {
    const files = Array.from(galleryInput.files || []);
    if (!galleryUploadUrl || files.length === 0) {
        return;
    }
    const eventId = galleryScopedEventId();
    if (!eventId) {
        setStatus('Create an event or go live before posting to the service gallery.');
        galleryInput.value = '';
        return;
    }
    for (const file of files) {
        const body = new FormData();
        body.append('image', file);
        body.append('event_id', String(eventId));
        try {
            const res = await fetch(galleryUploadUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': galleryCsrf || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body,
            });
            if (!res.ok) {
                const err = await res.json().catch(() => ({}));
                setStatus(err.message || err.errors?.event_id?.[0] || 'Could not upload photo. Try again.');
                continue;
            }
            const data = await res.json();
            appendGalleryThumb(data.image);
            setStatus('Photo posted to the listener gallery.');
        } catch {
            setStatus('Could not upload photo.');
        }
    }
    galleryInput.value = '';
});

reelInput?.addEventListener('change', async () => {
    const file = reelInput.files?.[0];
    if (!galleryUploadUrl || !file) {
        return;
    }
    const eventId = galleryScopedEventId();
    if (!eventId) {
        setStatus('Create an event or go live before posting a video reel.');
        reelInput.value = '';
        return;
    }
    try {
        const duration = await readVideoDuration(file);
        if (!Number.isFinite(duration) || duration > REEL_MAX_SECONDS + 0.5) {
            setStatus('Video reels must be 60 seconds or shorter (30s or 1 min).');
            reelInput.value = '';
            return;
        }
        if (duration < 1) {
            setStatus('That video is too short for a reel.');
            reelInput.value = '';
            return;
        }
        setStatus('Uploading video reel…');
        const body = new FormData();
        body.append('video', file);
        body.append('duration_seconds', String(Math.round(duration)));
        body.append('event_id', String(eventId));
        const res = await fetch(galleryUploadUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': galleryCsrf || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body,
        });
        if (!res.ok) {
            const err = await res.json().catch(() => ({}));
            setStatus(err.message || err.errors?.video?.[0] || err.errors?.event_id?.[0] || 'Could not upload video reel.');
            return;
        }
        const data = await res.json();
        appendGalleryThumb(data.image);
        setStatus('Video reel posted to the listener gallery.');
    } catch {
        setStatus('Could not upload video reel.');
    } finally {
        reelInput.value = '';
    }
});

const recordingUploadUrl = root?.dataset.recordingUploadUrl;
const localRecordingPanel = document.getElementById('studio-local-recording');
const localRecordingLiveEl = document.getElementById('studio-local-recording-live');
const localRecordingMetaEl = document.getElementById('studio-local-recording-meta');
const btnUploadRecording = document.getElementById('btn-upload-recording');
const btnDownloadRecording = document.getElementById('btn-download-recording');
const btnDiscardRecording = document.getElementById('btn-discard-recording');

function pickRecorderMime() {
    const candidates = [
        'audio/webm;codecs=opus',
        'audio/webm',
        'audio/mp4',
        'video/webm;codecs=opus',
    ];
    for (const type of candidates) {
        if (window.MediaRecorder?.isTypeSupported?.(type)) {
            return type;
        }
    }
    return '';
}

function formatDurationLabel(totalSec) {
    const total = Math.max(0, Math.round(totalSec));
    const h = Math.floor(total / 3600);
    const m = Math.floor((total % 3600) / 60);
    const s = total % 60;
    if (h > 0) {
        return `${h}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
    }
    return `${m}:${String(s).padStart(2, '0')}`;
}

function refreshLocalRecordingUi() {
    const recording = localRecorder?.state === 'recording';
    if (localRecordingLiveEl) {
        localRecordingLiveEl.hidden = !recording;
    }
    if (!localRecordingPanel) {
        return;
    }
    if (!pendingLocalRecording) {
        localRecordingPanel.hidden = true;
        return;
    }
    localRecordingPanel.hidden = false;
    if (localRecordingMetaEl) {
        localRecordingMetaEl.textContent = `${formatDurationLabel(pendingLocalDurationSec)} · ${formatBytes(pendingLocalRecording.size)} · on this device only`;
    }
}

function startLocalRecording() {
    if (!mixDest?.stream || typeof MediaRecorder === 'undefined') {
        setStatus('Live, but this browser can’t record locally. Use Chrome/Edge for archive uploads.');
        return;
    }
    if (localRecorder && localRecorder.state !== 'inactive') {
        return;
    }
    if (pendingLocalRecording) {
        setStatus('Broadcast is live. Upload or discard your saved local recording to capture the next session.');
        refreshLocalRecordingUi();
        return;
    }

    const mime = pickRecorderMime();
    try {
        localRecordChunks = [];
        localRecorder = mime
            ? new MediaRecorder(mixDest.stream, { mimeType: mime, audioBitsPerSecond: 96_000 })
            : new MediaRecorder(mixDest.stream, { audioBitsPerSecond: 96_000 });
        pendingLocalMime = localRecorder.mimeType || mime || 'audio/webm';
        localRecordStartedAt = Date.now();
        localRecorder.ondataavailable = (ev) => {
            if (ev.data && ev.data.size > 0) {
                localRecordChunks.push(ev.data);
            }
        };
        localRecorder.onerror = () => {
            setStatus('Local recording error — broadcast continues, but this session may not be uploadable.');
        };
        localRecorder.start(2000);
        refreshLocalRecordingUi();
    } catch (e) {
        console.error(e);
        localRecorder = null;
        setStatus('Could not start local recording. Broadcast is still live.');
    }
}

function stopLocalRecording() {
    return new Promise((resolve) => {
        const rec = localRecorder;
        if (!rec || rec.state === 'inactive') {
            localRecorder = null;
            refreshLocalRecordingUi();
            resolve();
            return;
        }
        rec.onstop = () => {
            const durationSec = Math.max(1, (Date.now() - localRecordStartedAt) / 1000);
            const blob = new Blob(localRecordChunks, { type: pendingLocalMime || 'audio/webm' });
            localRecordChunks = [];
            localRecorder = null;
            if (blob.size > 0) {
                pendingLocalRecording = blob;
                pendingLocalDurationSec = durationSec;
                setStatus('Local recording ready — Upload, Save locally, or Discard.');
            }
            refreshLocalRecordingUi();
            resolve();
        };
        try {
            rec.stop();
        } catch {
            localRecorder = null;
            refreshLocalRecordingUi();
            resolve();
        }
    });
}

function discardLocalRecording() {
    pendingLocalRecording = null;
    pendingLocalDurationSec = 0;
    refreshLocalRecordingUi();
    setStatus('Local recording discarded.');
}

function downloadLocalRecording() {
    if (!pendingLocalRecording) {
        return;
    }
    const ext = pendingLocalMime.includes('mp4') ? 'm4a' : 'webm';
    const url = URL.createObjectURL(pendingLocalRecording);
    const a = document.createElement('a');
    a.href = url;
    a.download = `live-mix-${new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-')}.${ext}`;
    a.click();
    URL.revokeObjectURL(url);
    setStatus('Saved a copy on this device.');
}

/**
 * @returns {Promise<boolean>}
 */
async function uploadLocalRecording() {
    if (!pendingLocalRecording) {
        return false;
    }
    if (!recordingUploadUrl) {
        setStatus('Upload link missing — reopen Studio from your dashboard.');
        return false;
    }
    if (btnUploadRecording) {
        btnUploadRecording.disabled = true;
    }
    setStatus('Uploading recording…');
    try {
        const ext = pendingLocalMime.includes('mp4') ? 'm4a' : 'webm';
        const body = new FormData();
        body.append('audio', pendingLocalRecording, `session.${ext}`);
        body.append('duration_seconds', String(Math.round(pendingLocalDurationSec)));
        if (currentEvent?.id) {
            body.append('event_id', String(currentEvent.id));
        }
        const recordingTitle = (localRecordingTitleInput?.value || currentEvent?.title || '').trim();
        if (recordingTitle) {
            body.append('title', recordingTitle);
        }
        const res = await fetch(recordingUploadUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': galleryCsrf || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body,
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            throw new Error(data.message || data.errors?.audio?.[0] || data.errors?.storage?.[0] || 'Upload failed');
        }
        pendingLocalRecording = null;
        pendingLocalDurationSec = 0;
        if (localRecordingTitleInput) {
            localRecordingTitleInput.value = '';
        }
        refreshLocalRecordingUi();
        setStatus(
            currentEvent?.url
                ? 'Recording uploaded to this event.'
                : 'Recording uploaded.',
        );
        return true;
    } catch (e) {
        setStatus(e instanceof Error ? e.message : 'Could not upload recording.');
        return false;
    } finally {
        if (btnUploadRecording) {
            btnUploadRecording.disabled = false;
        }
    }
}

btnUploadRecording?.addEventListener('click', async () => {
    await uploadLocalRecording();
});

refreshSessionButtons();
bindScriptureStudio(root);
if (currentEvent?.status === 'live' && !isLive) {
    setStatus('Page refreshed — hit Reconnect to keep this live event on air.');
} else if (sessionPaused) {
    setStatus('Paused. Resume to continue this event, or End live to close it.');
}

btnDownloadRecording?.addEventListener('click', () => {
    downloadLocalRecording();
});

btnDiscardRecording?.addEventListener('click', () => {
    if (!pendingLocalRecording) {
        return;
    }
    if (!window.confirm('Discard this local recording? It will not be uploaded.')) {
        return;
    }
    discardLocalRecording();
});

window.addEventListener('pagehide', () => {
    for (const id of [...fileChannels.keys()]) {
        removeFileChannel(id);
    }
    stopMeter();
    micCueOn = false;
    auxCueOn = false;
    playlistCueOn = false;
    if (cueAudio) {
        cueAudio.pause();
        cueAudio.srcObject = null;
    }
    if (audioCtx) {
        void audioCtx.close().catch(() => {});
        audioCtx = null;
        mixDest = null;
        cueDest = null;
        masterGain = null;
        cueMasterGain = null;
        micGain = null;
        auxGain = null;
        playlistGain = null;
    }
});
