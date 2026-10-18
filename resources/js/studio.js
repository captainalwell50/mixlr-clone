import './bootstrap';
import { bindScriptureStudio } from './scripture-studio';
import { bindSongStudio } from './song-studio';
import { bindNdiStudio } from './ndi-studio';
import { decidePublisherReconnect } from './publisher-reconnect';
import { mediaPlaylistState, nextPlaylistPlayback } from './playlist-transport';
import {
    decideReceivedLayout,
    detectedSourceChannels,
    effectiveProgramLayout,
    programChannelCount,
} from './studio-program-layout';
import { bindStudioThemeToggle } from './studio-theme';

bindStudioThemeToggle();

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
const btnPublishRecording = document.getElementById('btn-publish-recording');
const btnEditRecording = document.getElementById('btn-edit-recording');
const btnCreateEvent = document.getElementById('btn-create-event');
const btnUpgradeLive = document.getElementById('btn-upgrade-live');
const btnAddFile = document.getElementById('btn-add-file');
const eventTitleEl = document.getElementById('studio-event-title');
const eventUrlEl = document.getElementById('event-url');
const sessionShowUrl = root?.dataset.sessionShowUrl;
const sessionGoLiveUrl = root?.dataset.sessionGoLiveUrl;
const sessionPauseUrl = root?.dataset.sessionPauseUrl;
const sessionResumeUrl = root?.dataset.sessionResumeUrl;
const sessionEndUrl = root?.dataset.sessionEndUrl;
const sessionRecordingUrl = root?.dataset.sessionRecordingUrl;
const sessionPublisherClaimUrl = root?.dataset.sessionPublisherClaimUrl;
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
const copyEventBtn = document.getElementById('btn-copy-event');
const shareEventBtn = document.getElementById('btn-share-event');
const shareEventBlock = document.getElementById('share-event-block');
const shareEventNote = document.getElementById('share-event-note');
const channelUrlEl = document.getElementById('channel-url');
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
const micSourceSummaryEl = document.getElementById('mic-source-summary');
const auxSourceSummaryEl = document.getElementById('aux-source-summary');
const playlistSourceSummaryEl = document.getElementById('playlist-source-summary');
const masterSourceSummaryEl = document.getElementById('master-source-summary');

/** Windows Chromium often leaves 100cqh at ~0 in flex wells; measure throw from the slot. */
function syncMixerFaderTravel() {
    document.querySelectorAll('.mixer-fader-slot').forEach((slot) => {
        const fader = slot.querySelector('.mixer-fader');
        if (!fader) {
            return;
        }
        const travel = Math.max(72, Math.round(slot.clientHeight));
        slot.style.setProperty('--fader-travel', `${travel}px`);
        slot.closest('.mixer-fader-well')?.style.setProperty('--fader-travel', `${travel}px`);
        fader.style.width = `${travel}px`;
    });
}

function bindMixerFaderLayout() {
    syncMixerFaderTravel();
    requestAnimationFrame(() => requestAnimationFrame(syncMixerFaderTravel));
    const wells = document.querySelectorAll('.mixer-fader-well');
    if (typeof ResizeObserver !== 'undefined') {
        const observer = new ResizeObserver(() => syncMixerFaderTravel());
        wells.forEach((well) => observer.observe(well));
    }
    window.addEventListener('resize', syncMixerFaderTravel);
}

bindMixerFaderLayout();

/** Stereo Opus send cap. Admin platform setting; 320 kbps until the page says otherwise. */
const OPUS_SEND_RATES = new Set([128000, 160000, 192000, 256000, 320000, 510000]);

function readOpusSendBitrate(value) {
    const rate = Number(value);
    return OPUS_SEND_RATES.has(rate) ? rate : 320000;
}

let opusSendBitrate = readOpusSendBitrate(root?.dataset.opusBitrate);
const LAYOUT_STORAGE_KEY = 'studio-audio-layout';

/** Raw interface capture — no browser AGC / NS / EC (desktop / USB interfaces). */
const CLEAN_AUDIO = {
    sampleRate: { ideal: 48000 },
    sampleSize: { ideal: 16 },
    echoCancellation: false,
    noiseSuppression: false,
    autoGainControl: false,
    voiceIsolation: false,
    googEchoCancellation: false,
    googNoiseSuppression: false,
    googAutoGainControl: false,
    googHighpassFilter: false,
    googTypingNoiseDetection: false,
};

/** Stronger than CLEAN_AUDIO booleans — Chrome often ignores false and keeps APM on. */
const STRICT_CLEAN_AUDIO = {
    sampleRate: { ideal: 48000 },
    echoCancellation: { exact: false },
    noiseSuppression: { exact: false },
    autoGainControl: { exact: false },
    voiceIsolation: false,
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
/** idle | connecting | publishing | recovering | replaced | stopped */
let publisherPhase = 'idle';
/** Claim id for the WHIP session this tab last published. */
let publisherClaim = null;
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

function updateStudioWhatsAppHref(live) {
    const btn = document.getElementById('btn-studio-whatsapp');
    if (!btn) return;
    const base = btn.dataset.waBase;
    if (!base) return;
    const org = btn.dataset.orgName || '';
    const currentTitleEl = document.getElementById('studio-event-title');
    const eventTitle = currentTitleEl ? currentTitleEl.textContent.trim() : (btn.dataset.eventTitle || '');
    const status = live ? 'On Air' : (sessionPaused ? 'Paused' : 'Standby');

    let text = "Hello SoundMix Support,\nI need help with Web Studio.";
    const details = [];
    if (org) details.push('Organisation: ' + org);
    details.push('Broadcast status: ' + status);
    if (eventTitle && eventTitle !== 'No event selected') details.push('Event: ' + eventTitle);
    if (details.length > 0) {
        text += "\n\n" + details.join("\n");
    }
    btn.href = base + '?text=' + encodeURIComponent(text);
}

function setOnAir(live) {
    isLive = live;
    if (live) {
        publisherPhase = 'publishing';
    } else if (publisherPhase !== 'replaced') {
        publisherPhase = 'stopped';
    }
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
    updateStudioWhatsAppHref(live);
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

/** @type {1 | 2} */
let detectedChannels = 1;
/** @type {1 | 2} */
let micReceivedChannels = 1;
/** @type {1 | 2} */
let auxReceivedChannels = 1;
/** @type {Record<'mic' | 'aux', { splitter: ChannelSplitterNode, left: AnalyserNode, right: AnalyserNode, timer: ReturnType<typeof setTimeout>|null } | null>} */
const layoutProbes = { mic: null, aux: null };

/** @returns {'auto' | 'mono' | 'stereo'} */
function audioLayoutChoice() {
    const v = audioLayoutSelect?.value;
    if (v === 'mono' || v === 'stereo' || v === 'auto') {
        return v;
    }

    return 'auto';
}

/** @returns {'mono' | 'stereo'} */
function effectiveLayout() {
    return effectiveProgramLayout(audioLayoutChoice(), detectedChannels);
}

function isMono() {
    return effectiveLayout() === 'mono';
}

function mixChannelCount() {
    return programChannelCount(audioLayoutChoice(), detectedChannels);
}

/**
 * Native channel count of a captured track (not capability max).
 * @param {MediaStreamTrack | undefined} track
 */
function trackReceivedChannels(track) {
    if (!track) {
        return 0;
    }
    const n = Number(track.getSettings?.()?.channelCount || 0);
    if (n >= 2) {
        return 2;
    }
    if (n === 1) {
        return 1;
    }

    return 0;
}

/**
 * @param {'mic' | 'aux'} which
 * @param {1 | 2} n
 */
function setSourceChannels(which, n) {
    if (which === 'aux') {
        auxReceivedChannels = n;
    } else {
        micReceivedChannels = n;
    }
    const next = detectedSourceChannels(micReceivedChannels, auxReceivedChannels);
    if (next === detectedChannels) {
        return;
    }
    detectedChannels = next;
    applyMixLayout();
}

/**
 * @param {'mic' | 'aux'} which
 */
function stopLayoutProbe(which) {
    const probe = layoutProbes[which];
    if (!probe) {
        return;
    }
    if (probe.timer) {
        clearTimeout(probe.timer);
    }
    try {
        probe.splitter.disconnect();
    } catch {
        /* ignore */
    }
    try {
        probe.left.disconnect();
    } catch {
        /* ignore */
    }
    try {
        probe.right.disconnect();
    } catch {
        /* ignore */
    }
    layoutProbes[which] = null;
}

/**
 * Listen to actual L/R PCM. Device channelCount often says 2 for a 2-in interface
 * with only one mic plugged in.
 * @param {MediaStreamAudioSourceNode} sourceNode
 * @param {'mic' | 'aux'} which
 */
function startLayoutProbe(sourceNode, which) {
    stopLayoutProbe(which);
    if (!audioCtx || audioLayoutChoice() !== 'auto') {
        return;
    }
    if ((sourceNode.channelCount || 1) < 2) {
        setSourceChannels(which, 1);
        return;
    }
    const splitter = audioCtx.createChannelSplitter(2);
    const left = audioCtx.createAnalyser();
    const right = audioCtx.createAnalyser();
    left.fftSize = 2048;
    right.fftSize = 2048;
    left.smoothingTimeConstant = 0;
    right.smoothingTimeConstant = 0;
    sourceNode.connect(splitter);
    splitter.connect(left, 0);
    splitter.connect(right, 1);
    const leftTd = new Float32Array(left.fftSize);
    const rightTd = new Float32Array(right.fftSize);
    /** @type {{ splitter: ChannelSplitterNode, left: AnalyserNode, right: AnalyserNode, timer: ReturnType<typeof setTimeout>|null }} */
    const probe = { splitter, left, right, timer: null };
    layoutProbes[which] = probe;
    let attempts = 0;
    let stereoHits = 0;
    const tick = () => {
        if (layoutProbes[which] !== probe) {
            return;
        }
        attempts += 1;
        try {
            left.getFloatTimeDomainData(leftTd);
            right.getFloatTimeDomainData(rightTd);
        } catch {
            return;
        }
        const decision = decideReceivedLayout(leftTd, rightTd);
        if (decision === 2) {
            stereoHits += 1;
            if (stereoHits >= 2) {
                setSourceChannels(which, 2);
                stopLayoutProbe(which);
                return;
            }
        } else if (decision === 1) {
            stereoHits = 0;
            setSourceChannels(which, 1);
            if (attempts >= 8) {
                stopLayoutProbe(which);
                return;
            }
        }
        if (attempts >= 24) {
            stopLayoutProbe(which);
            return;
        }
        probe.timer = setTimeout(tick, 180);
    };
    probe.timer = setTimeout(tick, 120);
}

/**
 * @param {MediaStream | null | undefined} stream
 * @param {MediaStreamAudioSourceNode | null | undefined} sourceNode
 * @param {'mic' | 'aux'} [which]
 */
function noteReceivedChannels(stream, sourceNode, which = 'mic') {
    const fromTrack = trackReceivedChannels(stream?.getAudioTracks?.()?.[0]);
    if (fromTrack === 1 || (sourceNode?.channelCount || 0) === 1) {
        setSourceChannels(which, 1);
        return;
    }
    if (audioLayoutChoice() === 'auto' && sourceNode) {
        setSourceChannels(which, 1);
        startLayoutProbe(sourceNode, which);
        return;
    }
    if (fromTrack === 2) {
        setSourceChannels(which, 2);
    }
}

function applyMixLayout() {
    const count = mixChannelCount();
    try {
        if (mixDest) {
            mixDest.channelCount = count;
            mixDest.channelCountMode = 'explicit';
        }
        if (cueDest) {
            cueDest.channelCount = count;
        }
    } catch {
        /* optional */
    }
}

function withCaptureChannels(base) {
    const choice = audioLayoutChoice();
    if (choice === 'auto') {
        const next = { ...base };
        delete next.channelCount;

        return next;
    }

    return { ...base, channelCount: { ideal: choice === 'mono' ? 1 : 2 } };
}

function isCommunicationsLabel(label) {
    return /communications|hands-?free|headset earphone/i.test(label || '');
}

/**
 * Windows lists "Communications" as default — that path is 16 kHz AGC/NS (phone audio).
 * @param {MediaDeviceInfo[]} inputs
 * @param {string} previous
 */
function preferredAudioInputId(inputs, previous) {
    if (previous && inputs.some((d) => d.deviceId === previous)) {
        return previous;
    }
    const music = inputs.find((d) => d.label && !isCommunicationsLabel(d.label));

    return (music || inputs[0])?.deviceId || '';
}

/**
 * Tell Chrome this is music, not voice — otherwise it picks G.722 / SILK and crushes highs.
 * @param {MediaStreamTrack | null | undefined} track
 */
function markMusicTrack(track) {
    if (track && 'contentHint' in track) {
        track.contentHint = 'music';
    }
}

/**
 * @param {MediaStream} stream
 */
function markMusicTracks(stream) {
    for (const track of stream.getAudioTracks()) {
        markMusicTrack(track);
    }
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

function updateStripSummaries() {
    if (micSourceSummaryEl) {
        const opt = audioSelect?.selectedOptions?.[0];
        const text = opt?.textContent?.trim() || '';
        const value = audioSelect?.value || '';
        const textOut = value && text ? text : 'Default microphone';
        micSourceSummaryEl.textContent = textOut;
        micSourceSummaryEl.title = textOut;
    }
    if (auxSourceSummaryEl) {
        const opt = auxSelect?.selectedOptions?.[0];
        const text = opt?.textContent?.trim() || '';
        const value = auxSelect?.value || '';
        const textOut = value && text ? text : 'No source selected';
        auxSourceSummaryEl.textContent = textOut;
        auxSourceSummaryEl.title = textOut;
    }
    if (playlistSourceSummaryEl) {
        const n = fileChannels.size;
        playlistSourceSummaryEl.textContent = n === 0 ? 'Queue empty' : `${n} track${n === 1 ? '' : 's'} queued`;
    }
    if (masterSourceSummaryEl) {
        const choice = audioLayoutChoice();
        const layoutLabel = choice === 'mono' ? 'Mono' : choice === 'stereo' ? 'Stereo' : 'Auto';
        const outOpt = outputSelect?.selectedOptions?.[0];
        const outLabel = (outputSelect?.value && outOpt?.textContent?.trim()) ? outOpt.textContent.trim() : 'Default output';
        const textOut = `${layoutLabel} · ${outLabel}`;
        masterSourceSummaryEl.textContent = textOut;
        masterSourceSummaryEl.title = textOut;
    }
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
    if (channelsEl) {
        let emptyEl = document.getElementById('playlist-empty-state');
        if (n === 0) {
            if (!emptyEl) {
                emptyEl = document.createElement('div');
                emptyEl.id = 'playlist-empty-state';
                emptyEl.className = 'mixer-hint studio-queue-empty';
                emptyEl.innerHTML = `
                    <span class="studio-queue-empty-icon" aria-hidden="true">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 18V5l12-2v13"></path>
                            <circle cx="6" cy="18" r="3"></circle>
                            <circle cx="18" cy="16" r="3"></circle>
                        </svg>
                    </span>
                    <div class="studio-queue-empty-copy">
                        <p class="studio-queue-empty-title">Your session queue is empty.</p>
                        <p class="studio-queue-empty-sub">Add tracks from your library to play them during the broadcast.</p>
                    </div>
                `;
                channelsEl.appendChild(emptyEl);
            }
            emptyEl.hidden = false;
        } else if (emptyEl) {
            emptyEl.hidden = true;
        }
    }
    updateStripSummaries();
}

try {
    localStorage.removeItem('studio-audio-fx');
} catch {
    /* ignore */
}

if (audioLayoutSelect) {
    const saved = localStorage.getItem(LAYOUT_STORAGE_KEY);
    if (saved === 'auto' || saved === 'mono' || saved === 'stereo') {
        audioLayoutSelect.value = saved;
    }
    audioLayoutSelect.addEventListener('change', () => {
        localStorage.setItem(LAYOUT_STORAGE_KEY, audioLayoutChoice());
        applyMixLayout();
        updateStripSummaries();
        if (isLive) {
            setStatus('Output layout changed — End broadcast, then Go on air again to apply.');
        } else {
            if (micStream) {
                void attachMicrophoneToMixer();
            }
            if (auxStream) {
                void attachAuxToMixer();
            }
        }
    });
}

async function copyChannelLink(targetUrl, label = '') {
    const text = targetUrl || activeShareUrl();
    if (!text) {
        return;
    }
    try {
        await navigator.clipboard.writeText(text);
        if (label) {
            setStatus(label);
        } else {
            setStatus(currentEvent?.url ? 'Event link copied.' : 'Channel link copied.');
        }
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
    const nextTitle = event?.title || 'No event selected';
    if (eventTitleEl) {
        eventTitleEl.textContent = nextTitle;
        eventTitleEl.title = nextTitle;
    }
    if (eventTitleInput) {
        eventTitleInput.value = event?.title || '';
    }
    if (eventRenameWrap) {
        eventRenameWrap.hidden = !event;
    }
    const hasActiveEventUrl = Boolean(event?.url);
    if (eventUrlEl) {
        const nextUrl = event?.url || channelShareUrl || '';
        eventUrlEl.textContent = nextUrl;
        eventUrlEl.title = nextUrl;
    }
    if (shareEventBlock) {
        shareEventBlock.hidden = !hasActiveEventUrl;
    }
    if (shareChannelMainBtn) {
        shareChannelMainBtn.hidden = hasActiveEventUrl;
    }
    if (shareEventNote) {
        shareEventNote.hidden = hasActiveEventUrl;
    }
    if (copyChannelBtn) {
        copyChannelBtn.classList.toggle('studio-btn--primary', !hasActiveEventUrl);
        copyChannelBtn.classList.toggle('studio-btn--secondary', hasActiveEventUrl);
    }
    refreshSessionButtons();
    updateStudioWhatsAppHref(isLive);
    const nextGalleryEventId = galleryScopedEventId();
    if (previousGalleryEventId !== nextGalleryEventId) {
        void refreshStudioGallery();
    }
}

function currentCsrf() {
    return root?.dataset.csrf || document.querySelector('meta[name="csrf-token"]')?.content || '';
}

function applyCsrf(token) {
    if (!token || typeof token !== 'string') {
        return;
    }
    if (root) {
        root.dataset.csrf = token;
    }
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) {
        meta.setAttribute('content', token);
    }
}

async function refreshStudioCsrf() {
    if (!sessionShowUrl) {
        return;
    }
    try {
        const res = await fetch(sessionShowUrl, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });
        const data = await res.json().catch(() => ({}));
        if (data.csrf) {
            applyCsrf(data.csrf);
        }
        if (data.opus_send_bitrate != null) {
            opusSendBitrate = readOpusSendBitrate(data.opus_send_bitrate);
        }
        const at = Number(data.reload_at || 0);
        const loadedAt = Number(root?.dataset.studioReloadAt || 0);
        if (at > loadedAt) {
            const mode = (modeEl?.textContent || '').trim();
            if (/ON AIR|RECONNECT/i.test(mode) || isLive) {
                sessionStorage.setItem('sml-studio-resume', '1');
            }
            window.location.reload();
        }
    } catch {
        // Keep the page token; End may still succeed if the session is valid.
    }
}

if (sessionShowUrl) {
    void refreshStudioCsrf();
    window.setInterval(refreshStudioCsrf, 10 * 60 * 1000);
}

function maybeResumeAfterRemoteReload() {
    if (sessionStorage.getItem('sml-studio-resume') !== '1') {
        return;
    }
    sessionStorage.removeItem('sml-studio-resume');
    if (!broadcastAllowed || !btnStart) {
        return;
    }
    setStatus('Studio was refreshed from Sound Mix — reconnecting…');
    window.setTimeout(() => {
        if (!isLive) {
            btnStart.click();
        }
    }, 900);
}

async function sessionPatch(url, body = {}) {
    if (!url) {
        throw new Error('Session link missing — reopen Studio from your dashboard.');
    }
    await refreshStudioCsrf();
    const res = await fetch(url, {
        method: 'PATCH',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': currentCsrf(),
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

function setStartButtonLabel(label) {
    if (!btnStart) {
        return;
    }
    const labelSpan = btnStart.querySelector('.mixer-btn-start-label');
    if (labelSpan) {
        labelSpan.textContent = label;
    } else {
        btnStart.textContent = label;
    }
}

function refreshSessionButtons() {
    const live = isLive;
    const paused = sessionPaused && !live;
    const replaced = publisherPhase === 'replaced';
    // Server still has a live event after refresh / WHIP drop — reconnect before grace ends.
    const needsReconnect = !live && !paused && !replaced && currentEvent?.status === 'live';
    if (btnCreateEvent) {
        btnCreateEvent.hidden = live || paused || needsReconnect || replaced;
        btnCreateEvent.disabled = !broadcastAllowed || live || paused || needsReconnect || replaced;
    }
    if (btnPause) {
        btnPause.hidden = !live;
        btnPause.disabled = !live;
    }
    if (btnEnd) {
        btnEnd.hidden = replaced || !(live || paused || needsReconnect);
        btnEnd.disabled = replaced || !(live || paused || needsReconnect);
    }
    const expired = !broadcastAllowed;
    if (btnUpgradeLive) {
        btnUpgradeLive.hidden = !expired || live;
    }
    if (btnStart) {
        if (expired && !live) {
            btnStart.hidden = true;
            btnStart.disabled = true;
        } else {
            btnStart.hidden = false;
            if (live) {
                setStartButtonLabel('On air');
                btnStart.disabled = true;
            } else if (replaced) {
                setStartButtonLabel('Take Over Broadcast');
                btnStart.disabled = !broadcastAllowed;
            } else if (paused) {
                setStartButtonLabel('Resume');
                btnStart.disabled = !broadcastAllowed;
            } else if (needsReconnect) {
                setStartButtonLabel('Reconnect');
                btnStart.disabled = !broadcastAllowed;
            } else {
                setStartButtonLabel('Go live');
                btnStart.disabled = !broadcastAllowed;
            }
        }
    }
    if (airLabel) {
        airLabel.textContent = live ? 'ON THE AIR' : replaced ? 'REPLACED' : paused ? 'PAUSED' : needsReconnect ? 'RECONNECT' : 'STANDBY';
    }
    if (modeEl) {
        modeEl.textContent = live ? 'On the air' : replaced ? 'Replaced' : paused ? 'Paused' : needsReconnect ? 'Reconnect' : 'Standby';
    }
    if (modeMobileEl) {
        modeMobileEl.textContent = live ? 'ON AIR' : replaced ? 'REPLACED' : paused ? 'PAUSED' : needsReconnect ? 'RECONNECT' : 'STANDBY';
    }
    stageEl?.classList.toggle('is-on-air', live);
    stageEl?.classList.toggle('is-paused', paused);
}

async function sessionPost(url, body = {}) {
    if (!url) {
        throw new Error('Session link missing — reopen Studio from your dashboard.');
    }
    await refreshStudioCsrf();
    const res = await fetch(url, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': currentCsrf(),
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
shareEventBtn?.addEventListener('click', () => {
    void shareChannelLink();
});
copyEventBtn?.addEventListener('click', () => {
    void copyChannelLink(currentEvent?.url || eventUrlEl?.textContent?.trim(), 'Event link copied.');
});
copyChannelBtn?.addEventListener('click', () => {
    void copyChannelLink(channelShareUrl || activeShareUrl(), 'Channel link copied.');
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
    updateStripSummaries();
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
            el.parentElement?.classList.remove('is-clipping');
            const wellFill = el.closest('.mixer-strip')?.querySelector('.mixer-well-meter-fill');
            if (wellFill) {
                wellFill.style.height = '0%';
            }
        }
    }
    if (meterLabel) {
        meterLabel.textContent = 'READY';
        meterLabel.classList.remove('is-hot', 'is-clipping');
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
    const container = el.parentElement;
    if (container) {
        container.classList.toggle('is-clipping', pct >= 98);
    }
    const wellFill = el.closest('.mixer-strip')?.querySelector('.mixer-well-meter-fill');
    if (wellFill) {
        wellFill.style.height = `${pct}%`;
    }
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
            const clip = masterRms * 220 >= 98;
            const hot = masterRms > 0.02;
            meterLabel.textContent = clip ? 'CLIP' : hot ? 'SIGNAL' : 'READY';
            meterLabel.classList.toggle('is-clipping', clip);
            meterLabel.classList.toggle('is-hot', hot && !clip);
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
    const stereo = isMono() ? 0 : 1;
    const fmtpValue = `minptime=20;useinbandfec=1;usedtx=0;stereo=${stereo};sprop-stereo=${stereo};maxaveragebitrate=${opusSendBitrate};maxplaybackrate=48000`;

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
 * ICE gathering can put G.722/PCMU back on the m-line after we munge the offer.
 * Always strip again immediately before WHIP POST / setRemoteDescription.
 * @param {string} sdp
 */
function whipAudioSdp(sdp, kind = 'offer') {
    const out = preferHighQualityOpus(sdp || '');
    const fail = kind === 'answer'
        ? 'Server answered without Opus (phone codec). End broadcast, refresh Studio, then Go on air.'
        : 'Browser did not offer Opus. Try Chrome/Edge, or reload Studio.';
    if (!/opus\/48000/i.test(out)) {
        throw new Error(fail);
    }
    const mLine = out.match(/^m=audio .+$/im)?.[0] || '';
    const pts = mLine.split(/\s+/).slice(3);
    const opusPts = new Set(
        [...out.matchAll(/^a=rtpmap:(\d+) opus\/48000/gim)].map((match) => match[1]),
    );
    const phone = pts.find((pt) => {
        const line = out.match(new RegExp(`^a=rtpmap:${pt} (\\S+)`, 'im'));
        return line && /^(G722|PCMU|PCMA)\b/i.test(line[1]);
    });
    if (phone) {
        throw new Error(fail);
    }
    if (pts.length > 0 && pts.every((pt) => !opusPts.has(pt))) {
        throw new Error(fail);
    }

    return out;
}

function iceCandidateIp(line) {
    return line.trim().split(/\s+/)[4] || '';
}

function iceCandidateProtocol(line) {
    return (line.trim().split(/\s+/)[2] || '').toLowerCase();
}

function isUnusableIceIp(ip) {
    if (!ip) {
        return true;
    }
    if (
        ip === '20.120.113.129'
        || ip === '127.0.0.1'
        || ip === '0.0.0.0'
        || ip === '::1'
        || ip === '::'
    ) {
        return true;
    }
    if (/^(10\.|192\.168\.|169\.254\.)/.test(ip)) {
        return true;
    }
    if (/^172\.(1[6-9]|2\d|3[01])\./.test(ip)) {
        return true;
    }

    return false;
}

/**
 * Drop loopback/docker/stale-VM ICE hosts so media hits the current Azure IP.
 * @param {string} sdp
 * @param {{ preferTcp?: boolean }} [opts]
 */
function sanitizeRemoteIceSdp(sdp, opts = {}) {
    const preferTcp = Boolean(opts.preferTcp);
    const nl = sdp.includes('\r\n') ? '\r\n' : '\n';
    const lines = sdp.split(/\r?\n/);
    const usable = lines.filter((line) => {
        if (!/^a=candidate:/i.test(line)) {
            return true;
        }
        return !isUnusableIceIp(iceCandidateIp(line));
    });
    if (!usable.some((line) => /^a=candidate:/i.test(line))) {
        return sdp;
    }
    if (preferTcp && usable.some((line) => /^a=candidate:/i.test(line) && iceCandidateProtocol(line) === 'tcp')) {
        return usable
            .filter((line) => !/^a=candidate:/i.test(line) || iceCandidateProtocol(line) === 'tcp')
            .join(nl);
    }

    return usable.join(nl);
}

function whipAnswerSdp(sdp) {
    const stripped = stripNonOpusAudioCodecs(sdp || '');
    if (!/opus\/48000/i.test(stripped)) {
        throw new Error('Server answered without Opus (phone codec). End broadcast, refresh Studio, then Go on air.');
    }

    return sanitizeRemoteIceSdp(stripped);
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
                encoding.maxBitrate = opusSendBitrate;
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
        applyMixLayout();
        if (!meterRaf) {
            startMeters();
        }
        return;
    }
    if (!audioCtx) {
        audioCtx = createSilentAudioContext({ sampleRate: 48000, latencyHint: 'playback' });
    } else if (audioCtx.state === 'suspended') {
        await audioCtx.resume();
    }

    mixDest = audioCtx.createMediaStreamDestination();
    cueDest = audioCtx.createMediaStreamDestination();
    applyMixLayout();

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
 * @param {MediaTrackConstraints | boolean} audio
 * @param {string} [deviceId]
 * @returns {MediaTrackConstraints | boolean}
 */
function withCaptureDevice(audio, deviceId = '') {
    if (!deviceId || audio === true) {
        return audio;
    }

    return { ...audio, deviceId: { exact: deviceId } };
}

/**
 * @param {string} [deviceId]
 */
async function openDevice(deviceId = '') {
    /** @type {Array<MediaTrackConstraints | boolean>} */
    const attempts = [];
    if (isMobileUa()) {
        attempts.push(withCaptureDevice(audioCaptureConstraints(), deviceId));
        if (deviceId) {
            attempts.push({ ...MOBILE_AUDIO, deviceId: { ideal: deviceId } });
        }
        attempts.push(true);
    } else {
        attempts.push(withCaptureDevice(withCaptureChannels(STRICT_CLEAN_AUDIO), deviceId));
        attempts.push(withCaptureDevice(withCaptureChannels(CLEAN_AUDIO), deviceId));
        if (deviceId) {
            attempts.push({ ...withCaptureChannels(CLEAN_AUDIO), deviceId: { ideal: deviceId } });
        }
        attempts.push(withCaptureChannels(CLEAN_AUDIO));
    }

    let lastError = null;
    for (const audio of attempts) {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio, video: false });
            markMusicTracks(stream);

            return stream;
        } catch (e) {
            lastError = e;
        }
    }
    if (isMobileUa()) {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true, video: false });
        markMusicTracks(stream);

        return stream;
    }
    throw lastError instanceof Error
        ? lastError
        : new Error('Microphone permission or device access failed.');
}

function disconnectMicGraph() {
    stopLayoutProbe('mic');
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
    stopLayoutProbe('aux');
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
    const channels = source.channelCount || 1;
    if (audioLayoutChoice() !== 'mono' || channels < 2) {
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
    noteReceivedChannels(micStream, micSource, 'mic');
    routeInputToGain(micSource, micGain, 'mic');
    applyMicGain();
}

async function attachAuxToMixer() {
    await ensureMixer();
    disconnectAuxGraph();
    stopAuxTracks();
    const id = auxSelect?.value || '';
    if (!id) {
        setSourceChannels('aux', 1);
        applyAuxGain();
        return;
    }
    auxStream = await openDevice(id);
    auxSource = audioCtx.createMediaStreamSource(auxStream);
    noteReceivedChannels(auxStream, auxSource, 'aux');
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
                const preferred = preferredAudioInputId(inputs, previous);
                if (preferred && [...audioSelect.options].some((o) => o.value === preferred)) {
                    audioSelect.value = preferred;
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
        updateStripSummaries();
    } catch {
        setStatus('Could not list audio devices.');
        setMicEnableVisible(true);
        updateStripSummaries();
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
    updateStripSummaries();
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
    updateStripSummaries();
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

function playlistPlayerSnapshot(channel) {
    const audio = channel.audio;

    return {
        error: Boolean(audio.error),
        ended: audio.ended,
        paused: audio.paused,
        currentTime: audio.currentTime || 0,
        readyState: audio.readyState,
        src: audio.currentSrc || audio.src || '',
        wasPlayingBeforeBroadcastPause: channel.wasPlayingBeforeBroadcastPause === true,
    };
}

function formatPlaylistClock(seconds) {
    const s = Number(seconds);
    const safe = Number.isFinite(s) && s > 0 ? s : 0;

    return `${Math.floor(safe / 60)}:${String(Math.floor(safe % 60)).padStart(2, '0')}`;
}

const playlistStateLabel = {
    idle: 'Ready',
    loading: 'Loading…',
    playing: 'PLAYING',
    paused: 'Paused',
    ended: 'Ended',
    error: 'Failed to load',
};

function renderPlaylistTransport(channel) {
    const state = mediaPlaylistState(playlistPlayerSnapshot(channel));
    channel.playbackState = state;
    const statusEl = channel.card.querySelector('[data-ready-status]');
    const playBtn = channel.card.querySelector('[data-play]');
    const pauseBtn = channel.card.querySelector('[data-pause]');
    const restartBtn = channel.card.querySelector('[data-restart]');
    const metaEl = channel.card.querySelector('[data-meta]');
    if (statusEl) {
        statusEl.textContent = playlistStateLabel[state] || 'Ready';
        statusEl.classList.toggle('is-loading', state === 'loading');
        statusEl.classList.toggle('is-ready', state === 'idle' || state === 'paused');
        statusEl.classList.toggle('is-playing', state === 'playing');
        statusEl.classList.toggle('is-ended', state === 'ended');
    }
    channel.card.classList.toggle('is-playing', state === 'playing');
    channel.card.classList.toggle('is-error', state === 'error');
    const blocked = state === 'loading' || state === 'error' || !channel.ready;
    if (playBtn) {
        playBtn.disabled = blocked;
        playBtn.classList.toggle('is-active', state === 'playing');
        playBtn.setAttribute('aria-pressed', state === 'playing' ? 'true' : 'false');
    }
    if (pauseBtn) {
        pauseBtn.classList.toggle('is-active', state === 'paused');
    }
    if (restartBtn) {
        restartBtn.disabled = blocked;
    }
    if (metaEl) {
        const total = formatPlaylistClock(channel.duration || channel.audio.duration);
        const elapsed = formatPlaylistClock(channel.audio.currentTime);
        const clock = channel.duration > 0 || channel.audio.duration > 0
            ? `${elapsed} / ${total}`
            : '';
        metaEl.textContent = [clock, channel.sizeLabel].filter(Boolean).join(' · ');
    }
}

function setFileChannelReady(channel, ready, detail = '') {
    channel.ready = ready;
    if (!ready) {
        const statusEl = channel.card.querySelector('[data-ready-status]');
        const playBtn = channel.card.querySelector('[data-play]');
        const restartBtn = channel.card.querySelector('[data-restart]');
        if (statusEl) {
            statusEl.textContent = detail || 'Loading…';
            statusEl.classList.add('is-loading');
            statusEl.classList.remove('is-ready', 'is-playing', 'is-ended');
        }
        if (playBtn) {
            playBtn.disabled = true;
        }
        if (restartBtn) {
            restartBtn.disabled = true;
        }
        channel.playbackState = detail === 'Failed to load' ? 'error' : 'loading';

        return;
    }
    renderPlaylistTransport(channel);
}

/**
 * Broadcast pause remembers who was actually playing. Resume starts only those.
 * Go Live and WHIP reconnect do not come through here.
 */
async function applyPlaylistBroadcast(event) {
    for (const channel of fileChannels.values()) {
        const decision = nextPlaylistPlayback(event, playlistPlayerSnapshot(channel));
        if (event === 'broadcast-pause') {
            channel.wasPlayingBeforeBroadcastPause = decision.wasPlayingBeforeBroadcastPause === true;
        }
        if (decision.action === 'pause') {
            channel.audio.pause();
        } else if (decision.action === 'play') {
            channel.wasPlayingBeforeBroadcastPause = false;
            try {
                await channel.audio.play();
            } catch (e) {
                setStatus(e instanceof Error ? e.message : 'Could not resume playlist.');
            }
        } else if (event === 'broadcast-resume') {
            channel.wasPlayingBeforeBroadcastPause = false;
        }
        renderPlaylistTransport(channel);
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
    if (!objectUrl) {
        audio.crossOrigin = 'anonymous';
    }
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
        sizeLabel,
        ready: false,
        playbackState: 'loading',
        wasPlayingBeforeBroadcastPause: false,
    };
    fileChannels.set(id, channel);
    updatePlaylistMeta();
    setFileChannelReady(channel, false, 'Loading…');

    const markReady = () => {
        if (channel.ready) {
            return;
        }
        channel.duration = Number.isFinite(audio.duration) ? audio.duration : channel.duration;
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
    audio.addEventListener('play', () => {
        renderPlaylistTransport(channel);
    });
    audio.addEventListener('playing', () => {
        renderPlaylistTransport(channel);
    });
    audio.addEventListener('pause', () => {
        renderPlaylistTransport(channel);
    });
    audio.addEventListener('ended', () => {
        const endedPlayback = nextPlaylistPlayback('ended', playlistPlayerSnapshot(channel));
        renderPlaylistTransport(channel);
        if (endedPlayback.action === 'ended') {
            setStatus(`“${name}” ended.`);
        }
    });
    audio.addEventListener('timeupdate', () => {
        if (channel.playbackState === 'playing' || channel.playbackState === 'paused' || channel.playbackState === 'ended') {
            renderPlaylistTransport(channel);
        }
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
            if (channel.audio.ended) {
                channel.audio.currentTime = 0;
            }
            await channel.audio.play();
            renderPlaylistTransport(channel);
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
        channel.wasPlayingBeforeBroadcastPause = false;
        renderPlaylistTransport(channel);
        setStatus(`“${name}” paused.`);
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
        setLibraryStatus('Could not queue — this file has no playback link. Try Upload again.');
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
    setLibraryStatus(`Queued “${asset.title || asset.original_filename}”. Wait for Ready, then Play.`);
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
    openLibraryFilePicker();
});

document.getElementById('btn-upload-library')?.addEventListener('click', () => {
    openLibraryFilePicker();
});

fileInput?.addEventListener('change', () => {
    const files = Array.from(fileInput.files || []);
    fileInput.value = '';
    void uploadFilesToLibrary(files, true);
});

/**
 * MediaMTX often returns Location: http://127.0.0.1:8889/live/... or /live/...
 * (Caddy stripped /rtc). Reconnect DELETE then fails with "Failed to fetch".
 * @param {string|null} loc
 * @param {string} baseUrl
 */
function resolveMediaProxyUrl(loc, baseUrl) {
    if (!loc) {
        return null;
    }
    const base = new URL(baseUrl);
    let abs;
    try {
        abs = new URL(loc, base);
    } catch {
        return loc;
    }
    const privateHost = /^(127\.|10\.|172\.(1[6-9]|2\d|3[01])\.|192\.168\.|localhost$|0\.0\.0\.0$)/i.test(abs.hostname);
    const mtxPort = abs.port === '8889' || abs.port === '8888';
    if (privateHost || mtxPort) {
        const path = `${abs.pathname}${abs.search}${abs.hash}`;
        const prefixed = path.startsWith('/rtc/') || path.startsWith('/hls/')
            ? path
            : (path.startsWith('/live/') ? `/rtc${path}` : path);

        return `${base.origin}${prefixed}`;
    }
    if (
        abs.origin === base.origin
        && abs.pathname.startsWith('/live/')
        && /\/rtc(\/|$)/.test(base.pathname)
    ) {
        abs.pathname = `/rtc${abs.pathname}`;
    }

    return abs.href;
}

/**
 * @param {MediaStream} stream
 */
async function publishWhip(stream) {
    await refreshStudioCsrf();
    const track = stream.getAudioTracks()[0];
    if (!track) {
        throw new Error('No audio track. Check the microphone.');
    }
    markMusicTrack(track);

    // Record ownership before the WHIP POST. MediaMTX closes the previous
    // publisher as soon as this POST succeeds, and that client checks the
    // claim when its ICE connection fails. Claiming afterwards loses the race
    // to not_ready and the two clients replace each other.
    await claimPublisherOwnership();
    try {
        await publishWhipOffer(stream, track);
    } catch (error) {
        await releasePublisherClaim();
        throw error;
    }
}

/**
 * @param {MediaStream} stream
 * @param {MediaStreamTrack} track
 */
async function publishWhipOffer(stream, track) {

    pc = new RTCPeerConnection({
        iceServers: [{ urls: 'stun:stun.l.google.com:19302' }],
    });
    silenceRemoteAudio(pc);
    bindPublisherPeerWatch(pc);

    const transceiver = pc.addTransceiver(track, {
        direction: 'sendonly',
        streams: [stream],
        sendEncodings: [{ maxBitrate: opusSendBitrate, priority: 'high', networkPriority: 'high' }],
    });

    if (!transceiver.sender) {
        pc.addTrack(track, stream);
    }

    forceOpusCodec(transceiver);
    await applyMaxAudioBitrate(pc);

    const offer = await pc.createOffer();
    const highQualitySdp = whipAudioSdp(offer.sdp || '');
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

    // ICE candidates rewrite SDP and can restore G.722 as the preferred audio codec.
    const offerSdp = whipAudioSdp(pc.localDescription?.sdp || highQualitySdp);

    const res = await fetch(whipUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/sdp' },
        body: offerSdp,
    });

    if (!res.ok) {
        const text = await res.text();
        throw new Error(text || `WHIP failed (${res.status})`);
    }

    const loc = res.headers.get('Location');
    whipResourceUrl = resolveMediaProxyUrl(loc, whipUrl);

    const answerSdp = whipAnswerSdp(await res.text());
    await pc.setRemoteDescription({ type: 'answer', sdp: answerSdp });
    silenceRemoteAudio(pc);
    await applyMaxAudioBitrate(pc);
    scheduleOutboundCodecWatch(pc);
}

/**
 * @param {RTCPeerConnection} peer
 */
function scheduleOutboundCodecWatch(peer) {
    window.setTimeout(async () => {
        if (peer !== pc || !isLive) {
            return;
        }
        try {
            const stats = await peer.getStats();
            const codecIds = new Set();
            for (const report of stats.values()) {
                if (
                    report.type === 'outbound-rtp'
                    && (report.kind === 'audio' || report.mediaType === 'audio')
                    && report.codecId
                ) {
                    codecIds.add(report.codecId);
                }
            }
            let mime = '';
            for (const report of stats.values()) {
                if (report.type === 'codec' && codecIds.has(report.id)) {
                    mime = String(report.mimeType || '');
                }
            }
            if (mime && /G722|PCMU|PCMA/i.test(mime)) {
                setStatus('Broadcast negotiated a phone codec. Reconnecting as Opus…');
                schedulePublisherReconnect('Phone codec detected — republishing as Opus…');
            }
        } catch {
            /* stats not ready */
        }
    }, 1500);
}

const replacedNotice = 'This stream is now being published from another device or session.';

async function claimPublisherOwnership() {
    if (!sessionPublisherClaimUrl || !crypto.randomUUID) {
        return;
    }
    publisherClaim = crypto.randomUUID();
    publisherPhase = 'publishing';
    try {
        await sessionPost(sessionPublisherClaimUrl, {
            claim: publisherClaim,
            intent: 'claim',
        });
    } catch {
        /* A failed claim does not prove someone else owns the stream. */
    }
}

async function releasePublisherClaim() {
    if (!sessionPublisherClaimUrl || !publisherClaim) {
        return;
    }
    const claim = publisherClaim;
    publisherClaim = null;
    try {
        await sessionPost(sessionPublisherClaimUrl, {
            claim,
            intent: 'release',
        });
    } catch {
        /* The previous publisher can still recover if this claim never stuck. */
    }
}

async function publisherWasReplaced() {
    if (!sessionPublisherClaimUrl || !publisherClaim) {
        return false;
    }
    try {
        const data = await sessionPost(sessionPublisherClaimUrl, {
            claim: publisherClaim,
            intent: 'check',
        });
        return decidePublisherReconnect({
            owns: data.owns === true,
            replaced: data.replaced === true,
        }) === 'replaced';
    } catch {
        return decidePublisherReconnect({ checkFailed: true }) === 'replaced';
    }
}

function markPublisherReplaced() {
    publisherPhase = 'replaced';
    publishMode = null;
    clearPublisherReconnect();
    if (pc) {
        pc.onconnectionstatechange = null;
        try {
            pc.close();
        } catch {
            /* already closed */
        }
        pc = null;
    }
    whipResourceUrl = null;
    setOnAir(false);
    if (heroHint) {
        heroHint.textContent = replacedNotice;
    }
    setStatus(replacedNotice);
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
    if (publisherPhase === 'replaced' || publisherPhase === 'stopped') {
        return;
    }
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
    if (publisherPhase === 'replaced' || publisherPhase === 'stopped') {
        return;
    }
    if (!isLive || publishMode !== 'mixer' || publisherRepublishing) {
        return;
    }
    if (!mixDest?.stream) {
        schedulePublisherReconnect('Mixer not ready — retrying publish…');
        return;
    }
    publisherRepublishing = true;
    publisherPhase = 'recovering';
    setStatus('Reconnecting broadcast…');
    try {
        if (await publisherWasReplaced()) {
            markPublisherReplaced();
            return;
        }
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
        setStatus('');
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

const dockTabs = document.querySelectorAll('[data-dock-tab]');
const dockPanels = document.querySelectorAll('[data-dock-panel]');
let activeDockTab = 'library';

function setDockTab(tabName) {
    activeDockTab = tabName || 'library';
    dockTabs.forEach((btn) => {
        const active = btn.dataset.dockTab === activeDockTab;
        btn.classList.toggle('is-active', active);
        btn.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    dockPanels.forEach((panel) => {
        const active = panel.dataset.dockPanel === activeDockTab;
        panel.classList.toggle('is-active', active);
        panel.hidden = !active;
    });
}

dockTabs.forEach((btn) => {
    btn.addEventListener('click', () => {
        setDockTab(btn.dataset.dockTab || 'library');
    });
});

const mobileTabs = document.querySelectorAll('.mixer-mobile-tab');
const mobilePanes = document.querySelectorAll('[data-mobile-pane]');

function setMobilePane(pane) {
    mobileTabs.forEach((tab) => {
        tab.classList.toggle('is-active', tab.dataset.mobilePane === pane);
    });
    if (pane === 'library') {
        setDockTab('library');
    } else if (pane === 'tools' && activeDockTab === 'library') {
        setDockTab('board');
    }
    mobilePanes.forEach((el) => {
        const match = el.dataset.mobilePane === pane
            || (pane === 'library' && el.classList.contains('studio-dock'))
            || (pane === 'tools' && el.classList.contains('studio-dock'));
        el.classList.toggle('is-mobile-active', match);
    });
    stageEl?.setAttribute('data-mobile-pane', pane);
    if (pane === 'mix') {
        requestAnimationFrame(() => syncMixerFaderTravel());
    }
}

mobileTabs.forEach((tab) => {
    tab.addEventListener('click', () => {
        setMobilePane(tab.dataset.mobilePane || 'mix');
    });
});
setDockTab('library');
setMobilePane('mix');
updatePlaylistMeta();

void primeMicrophone({ interactive: false });
maybeResumeAfterRemoteReload();

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
        setStatus('Free access has ended. Upgrade to go live.');
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
    const takingOver = publisherPhase === 'replaced';
    if (takingOver) {
        publisherPhase = 'connecting';
    }
    const resuming = sessionPaused;
    const reconnecting = !takingOver && !resuming && currentEvent?.status === 'live';
    setStatus(
        takingOver
            ? 'Taking over the broadcast…'
            : resuming
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
        if (resuming) {
            await applyPlaylistBroadcast('broadcast-resume');
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
        await applyPlaylistBroadcast('broadcast-pause');
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
    showRecordingActions(null);
    setStatus('Ending event…');
    setOnAir(false);
    try {
        // Drop the publisher first so listeners go offline immediately.
        await teardownPublisher({ keepEvent: false });
        const data = await sessionPost(sessionEndUrl);
        const ended = data.event || currentEvent;
        sessionPaused = false;
        currentEvent = null;
        applyEventToUi(null);
        setOnAir(false);
        if (ended?.id) {
            // Keep id so auto-upload still attaches to the ended event.
            currentEvent = { ...ended, status: 'ended' };
        }
        let saved = null;
        if (pendingLocalRecording) {
            setStatus('Event ended — saving the full recording…');
            saved = await uploadLocalRecording();
        } else if (ended?.id) {
            setStatus('Event ended — saving the full recording…');
            saved = await waitForSavedRecording(ended.id);
        }
        showRecordingActions(saved);
        if (saved) {
            setStatus('Recording saved. Publish it, or edit it first to trim what you don’t want.');
        } else {
            setStatus('Event ended. The recording is still saving — Publish and Edit will light up when it is ready.');
        }
    } catch (e) {
        setStatus(e instanceof Error ? e.message : 'Could not end event.');
        refreshSessionButtons();
    }
});

function showRecordingActions(recording) {
    if (!btnPublishRecording || !btnEditRecording) {
        return;
    }
    btnPublishRecording.hidden = false;
    btnEditRecording.hidden = false;
    if (!recording) {
        btnPublishRecording.disabled = true;
        btnPublishRecording.textContent = 'Saving…';
        btnEditRecording.removeAttribute('href');
        btnEditRecording.setAttribute('aria-disabled', 'true');
        return;
    }
    btnPublishRecording.disabled = false;
    btnPublishRecording.textContent = recording.published ? 'Published' : 'Publish podcast';
    btnPublishRecording.dataset.publishUrl = recording.publish_url || '';
    if (recording.edit_url) {
        btnEditRecording.href = recording.edit_url;
        btnEditRecording.removeAttribute('aria-disabled');
    }
}

async function waitForSavedRecording(eventId) {
    if (!sessionRecordingUrl) {
        return null;
    }
    for (let attempt = 0; attempt < 40; attempt += 1) {
        try {
            const res = await fetch(sessionRecordingUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': currentCsrf(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ event_id: eventId }),
            });
            const data = await res.json().catch(() => ({}));
            if (res.ok && data.recording) {
                return data.recording;
            }
        } catch {
            /* keep waiting while the recording is assembled */
        }
        await new Promise((resolve) => setTimeout(resolve, 3000));
    }
    return null;
}

btnPublishRecording?.addEventListener('click', async () => {
    const url = btnPublishRecording.dataset.publishUrl;
    if (!url || btnPublishRecording.disabled) {
        return;
    }
    btnPublishRecording.disabled = true;
    try {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': currentCsrf(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: new URLSearchParams({ published: '1' }),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            throw new Error(data.message || 'Could not publish.');
        }
        btnPublishRecording.textContent = 'Published';
        setStatus('Published to the podcast list.');
    } catch (e) {
        btnPublishRecording.disabled = false;
        setStatus(e instanceof Error ? e.message : 'Could not publish.');
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
const libraryDestinationHintEl = document.getElementById('library-destination-hint');
const libraryStatusEl = document.getElementById('library-status');
const btnDriveConnect = document.getElementById('btn-drive-connect');
const btnDriveBrowse = document.getElementById('btn-drive-browse');
const libraryListUrl = root?.dataset.libraryListUrl;
const libraryUploadUrl = root?.dataset.libraryUploadUrl;
const libraryImportDriveUrl = root?.dataset.libraryImportDriveUrl;
const objectStorageEnabled = root?.dataset.objectStorageEnabled === '1';
/** @type {Array<Record<string, any>>} */
let libraryAssets = [];
let librarySearchTimer = 0;
let libraryDrive = { connected: false, connect_url: null, email: null };

function defaultLibraryDestination() {
    return objectStorageEnabled ? 'platform' : 'local';
}

function setLibraryStatus(message, { upgrade = false } = {}) {
    if (libraryStatusEl) {
        libraryStatusEl.textContent = message;
        if (upgrade && billingUrl) {
            libraryStatusEl.append(' ');
            const link = document.createElement('a');
            link.href = billingUrl;
            link.className = 'mixer-library-upgrade';
            link.textContent = 'Upgrade';
            libraryStatusEl.append(link);
        }
    }
    setStatus(message);
}

function libraryDestinationHint() {
    const dest = libraryDestinationEl?.value || defaultLibraryDestination();
    if (dest === 'drive') {
        return libraryDrive.connected
            ? 'Google Drive — saved to your Drive (does not use plan storage).'
            : 'Google Drive — connect Drive first, or choose Server disk / Platform.';
    }
    if (dest === 'platform') {
        return objectStorageEnabled
            ? 'Platform — Sound Mix cloud storage. Uses your plan storage quota.'
            : 'Platform cloud is not enabled yet — this upload will be saved on the server disk.';
    }
    return 'Server disk — stored on this broadcast server. Uses your plan storage quota.';
}

function updateLibraryDestinationHint() {
    if (libraryDestinationHintEl) {
        libraryDestinationHintEl.textContent = libraryDestinationHint();
    }
}

function ensureLibraryDestinationReady() {
    const dest = libraryDestinationEl?.value || defaultLibraryDestination();
    if (dest === 'drive' && !libraryDrive.connected) {
        setLibraryStatus('Connect Google Drive first, or choose Server disk / Platform.');
        if (btnDriveConnect && libraryDrive.connect_url) {
            btnDriveConnect.hidden = false;
        }
        return false;
    }
    return true;
}

function openLibraryFilePicker() {
    if (!ensureLibraryDestinationReady()) {
        return;
    }
    fileInput?.click();
}

const btnAddGallery = document.getElementById('btn-add-gallery');
const galleryInput = document.getElementById('gallery-input');
const btnAddReel = document.getElementById('btn-add-reel');
const reelInput = document.getElementById('reel-input');
const studioGalleryList = document.getElementById('studio-gallery-list');
const galleryUploadUrl = root?.dataset.galleryUploadUrl;
const galleryDestroyUrl = root?.dataset.galleryDestroyUrl;
const galleryListUrl = root?.dataset.galleryListUrl;

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

function cleanLibraryDisplayTitle(raw) {
    const str = String(raw || '').trim();
    if (!str) {
        return 'Audio';
    }
    return str
        .replace(/\.(mp3|wav|m4a|aac|ogg|flac|webm|mp4)$/i, '')
        .replace(/[_]+/g, ' ')
        .trim() || str;
}

document.addEventListener('click', (ev) => {
    const target = ev.target instanceof Element ? ev.target : null;
    document.querySelectorAll('details.studio-overflow-menu[open]').forEach((menu) => {
        if (!target || !menu.contains(target)) {
            menu.removeAttribute('open');
        }
    });
});

// Library artwork is intentionally neutral when tracks do not provide real artwork.
// Keep the placeholder deterministic and product-branded rather than inventing album art.

function updateLibraryStorageSummary() {
    const summaryEl = document.getElementById('library-storage-summary');
    if (!summaryEl) {
        return;
    }
    if (!libraryAssets.length) {
        summaryEl.textContent = '';
        summaryEl.hidden = true;
        return;
    }
    let totalBytes = 0;
    for (const a of libraryAssets) {
        totalBytes += Number(a.size_bytes) || 0;
    }
    const count = libraryAssets.length;
    const countLabel = `${count} saved track${count === 1 ? '' : 's'}`;
    summaryEl.textContent = totalBytes > 0 ? `${countLabel} · ${formatBytes(totalBytes)} used` : countLabel;
    summaryEl.hidden = false;
}

function renderLibraryList(assets = filteredLibraryAssets()) {
    updateLibraryStorageSummary();
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

    for (const [idx, asset] of assets.entries()) {
        const row = document.createElement('div');
        row.className = 'mixer-library-row';
        row.setAttribute('role', 'listitem');
        row.dataset.id = String(asset.id);

        const rawTitle = asset.title || asset.original_filename || 'Audio';
        const displayTitle = cleanLibraryDisplayTitle(rawTitle);
        const dur = formatTrackDuration(asset.duration_seconds);
        const meta = [dur, formatBytes(asset.size_bytes)].filter(Boolean).join(' · ');

        row.innerHTML = `
            <span class="mixer-library-thumb" aria-hidden="true">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 18V5l12-2v13"></path>
                    <circle cx="6" cy="18" r="3"></circle>
                    <circle cx="18" cy="16" r="3"></circle>
                </svg>
            </span>
            <div class="mixer-library-copy">
                <p class="mixer-library-title"></p>
                <p class="mixer-library-meta"></p>
            </div>
            <div class="mixer-library-actions">
                <button type="button" class="studio-library-play-btn" data-preview-queue aria-label="Queue track" title="Add to session queue">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <polygon points="6 4 20 12 6 20 6 4"></polygon>
                    </svg>
                </button>
                <button type="button" class="studio-btn studio-btn--queue" data-queue>+ Queue</button>
                <details class="studio-overflow-menu">
                    <summary class="studio-overflow-trigger" aria-label="More track actions">•••</summary>
                    <div class="studio-overflow-popover">
                        <button type="button" class="studio-overflow-item is-danger" data-delete>Delete</button>
                    </div>
                </details>
            </div>
        `;
        const titleEl = row.querySelector('.mixer-library-title');
        if (titleEl) {
            titleEl.textContent = displayTitle;
            titleEl.title = asset.original_filename && asset.original_filename !== displayTitle
                ? `${displayTitle} (${asset.original_filename})`
                : rawTitle;
        }
        const provider =
            asset.storage_provider === 'drive'
                ? 'Drive'
                : asset.storage_provider === 'platform'
                  ? 'Platform'
                  : 'Server';
        row.querySelector('.mixer-library-meta').textContent = `${meta} · ${provider}`;

        row.querySelector('[data-preview-queue]')?.addEventListener('click', () => {
            queueLibraryAsset(asset);
        });
        row.querySelector('[data-queue]')?.addEventListener('click', () => {
            queueLibraryAsset(asset);
        });
        row.querySelector('[data-delete]')?.addEventListener('click', async (ev) => {
            const details = ev.currentTarget instanceof Element ? ev.currentTarget.closest('details') : null;
            details?.removeAttribute('open');
            if (!asset.delete_url) {
                setLibraryStatus('Could not delete — missing permission link.');
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
                        'X-CSRF-TOKEN': currentCsrf(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                if (!res.ok) {
                    throw new Error('delete failed');
                }
                libraryAssets = libraryAssets.filter((a) => a.id !== asset.id);
                renderLibraryList();
                setLibraryStatus('Removed from audio library.');
            } catch {
                setLibraryStatus('Could not delete from library.');
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
        libraryDestinationEl.value = defaultLibraryDestination();
    }
    updateLibraryDestinationHint();
}

async function importDriveFilePrompt() {
    if (!libraryImportDriveUrl || !libraryDrive.connected) {
        setLibraryStatus('Connect Google Drive first.');
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
                'X-CSRF-TOKEN': currentCsrf(),
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
            setLibraryStatus('Imported from Google Drive.');
        }
    } catch (e) {
                setLibraryStatus(e instanceof Error ? e.message : 'Could not import from Drive.');
    }
}

/**
 * @param {File[]} files
 * @param {boolean} queueAfterUpload
 */
async function uploadFilesToLibrary(files, queueAfterUpload) {
    if (!libraryUploadUrl) {
        setLibraryStatus('Audio library upload is not available on this Studio link.');
        return;
    }
    if (!ensureLibraryDestinationReady()) {
        return;
    }

    const audioFiles = files.filter(
        (file) => file.type.startsWith('audio/') || /\.(mp3|wav|m4a|aac|ogg|flac|webm|mp4)$/i.test(file.name),
    );
    if (!audioFiles.length) {
        setLibraryStatus('Choose an audio file (mp3, wav, m4a, …).');
        return;
    }

    setLibraryStatus(`Saving ${audioFiles.length} sound${audioFiles.length === 1 ? '' : 's'} to the library…`);

    for (const file of audioFiles) {
        const title = file.name.replace(/\.[^.]+$/, '') || file.name;
        const objectUrl = queueAfterUpload ? URL.createObjectURL(file) : null;
        let queuedChannel = null;
        if (queueAfterUpload && objectUrl) {
            queuedChannel = addPlaylistTrack({
                name: title,
                src: objectUrl,
                objectUrl,
                sizeLabel: formatBytes(file.size),
            });
            setLibraryStatus(`Queued “${title}” — saving to library…`);
        }
        try {
            const duration = await probeAudioDuration(file);
            const body = new FormData();
            body.append('audio', file);
            body.append('title', title);
            body.append('destination', libraryDestinationEl?.value || defaultLibraryDestination());
            if (duration != null) {
                body.append('duration_seconds', String(Math.round(duration)));
            }

            const res = await fetch(libraryUploadUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': currentCsrf(),
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
                        err?.errors?.destination?.[0] ||
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
                if (queuedChannel) {
                    queuedChannel.assetId = asset.id;
                } else if (queueAfterUpload) {
                    queueLibraryAsset(asset);
                }
            }
            if (data.notice) {
                setLibraryStatus(data.notice);
            }
        } catch (e) {
            const msg = e instanceof Error ? e.message : `Could not upload “${file.name}”.`;
            setLibraryStatus(msg, {
                upgrade: /upgrade to a higher plan/i.test(msg),
            });
            return;
        }
    }

    renderLibraryList();
    setLibraryStatus(
        queueAfterUpload
            ? 'Saved to library and queued. Wait for Ready, then Play.'
            : 'Saved to audio library.',
    );
}

libraryDestinationEl?.addEventListener('change', updateLibraryDestinationHint);
updateLibraryDestinationHint();

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

function galleryDeleteButton(isVideo) {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'mixer-gallery-delete';
    btn.setAttribute('aria-label', isVideo ? 'Remove reel from gallery' : 'Remove photo from gallery');
    btn.title = 'Remove';
    btn.textContent = '×';
    return btn;
}

function appendGalleryThumb(payload) {
    if (!studioGalleryList || !payload?.url) {
        return;
    }
    const isVideo = payload.type === 'video';
    const figure = document.createElement('figure');
    figure.className = `mixer-gallery-thumb ${isVideo ? 'is-video' : ''}`;
    figure.dataset.id = String(payload.id || '');
    if (isVideo) {
        const video = document.createElement('video');
        video.src = payload.url;
        video.muted = true;
        video.playsInline = true;
        video.preload = 'metadata';
        figure.appendChild(video);
        const badge = document.createElement('span');
        badge.className = 'mixer-reel-badge';
        badge.textContent = 'Reel';
        figure.appendChild(badge);
    } else {
        const img = document.createElement('img');
        img.src = payload.url;
        img.alt = payload.caption || 'Gallery photo';
        figure.appendChild(img);
    }
    figure.appendChild(galleryDeleteButton(isVideo));
    studioGalleryList.prepend(figure);
}

async function deleteStudioGalleryItem(figure) {
    const id = Number(figure?.dataset.id || 0);
    if (!id || !galleryDestroyUrl) {
        setStatus('Could not remove this gallery item.');
        return;
    }
    const isVideo = figure.classList.contains('is-video');
    const label = isVideo ? 'reel' : 'photo';
    if (!window.confirm(`Remove this ${label} from the live gallery?`)) {
        return;
    }
    const btn = figure.querySelector('.mixer-gallery-delete');
    if (btn) {
        btn.disabled = true;
    }
    try {
        const res = await fetch(galleryDestroyUrl, {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': currentCsrf(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ image_id: id }),
        });
        if (!res.ok) {
            const err = await res.json().catch(() => ({}));
            throw new Error(err.message || err.errors?.image_id?.[0] || 'Could not remove from gallery.');
        }
        figure.remove();
        setStatus(isVideo ? 'Reel removed from the live gallery.' : 'Photo removed from the live gallery.');
    } catch (e) {
        if (btn) {
            btn.disabled = false;
        }
        setStatus(e instanceof Error ? e.message : 'Could not remove from gallery.');
    }
}

studioGalleryList?.addEventListener('click', (ev) => {
    const btn = ev.target instanceof Element ? ev.target.closest('.mixer-gallery-delete') : null;
    if (!btn || !studioGalleryList.contains(btn)) {
        return;
    }
    ev.preventDefault();
    ev.stopPropagation();
    const figure = btn.closest('.mixer-gallery-thumb');
    if (figure) {
        void deleteStudioGalleryItem(figure);
    }
});

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
                    'X-CSRF-TOKEN': currentCsrf(),
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
                'X-CSRF-TOKEN': currentCsrf(),
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
 * @returns {Promise<object|null>}
 */
async function uploadLocalRecording() {
    if (!pendingLocalRecording) {
        return null;
    }
    if (!recordingUploadUrl) {
        setStatus('Upload link missing — reopen Studio from your dashboard.');
        return null;
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
                'X-CSRF-TOKEN': currentCsrf(),
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
        setStatus('Recording saved. It stays off the podcast list until you publish it.');
        return data.recording || null;
    } catch (e) {
        setStatus(e instanceof Error ? e.message : 'Could not upload recording.');
        return null;
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
bindSongStudio(root);
bindNdiStudio(root);
if (!broadcastAllowed && !isLive) {
    setStatus('Free access has ended. Upgrade to go live.');
} else if (currentEvent?.status === 'live' && !isLive) {
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
