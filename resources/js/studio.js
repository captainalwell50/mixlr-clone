import './bootstrap';

const root = document.getElementById('studio-root');
const whipUrl = root?.dataset.whipUrl;
const audioSelect = document.getElementById('audio-input');
const auxSelect = document.getElementById('aux-input');
const outputSelect = document.getElementById('audio-output');
const btnStart = document.getElementById('btn-start');
const btnStop = document.getElementById('btn-stop');
const btnAddFile = document.getElementById('btn-add-file');
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
const airLabel = document.getElementById('studio-air-label');
const heroHint = document.getElementById('studio-hero-hint');
const timerEl = document.getElementById('studio-timer');
const copyBtn = document.getElementById('btn-copy-listen');
const listenUrlEl = document.getElementById('listen-url');
const audioLayoutSelect = document.getElementById('audio-layout');
const cueAudio = document.getElementById('cue-audio');
const playlistCountEl = document.getElementById('playlist-count');

const micFaderEl = document.getElementById('mic-fader');
const auxFaderEl = document.getElementById('aux-fader');
const playlistFaderEl = document.getElementById('playlist-fader');
const micCueBtn = document.getElementById('mic-cue');
const auxCueBtn = document.getElementById('aux-cue');
const playlistCueBtn = document.getElementById('playlist-cue');
const micMuteBtn = document.getElementById('mic-mute');
const auxMuteBtn = document.getElementById('aux-mute');
const playlistMuteBtn = document.getElementById('playlist-mute');

/** Opus fullband max (bits/sec). */
const OPUS_MAX_BITRATE = 510_000;
const LAYOUT_STORAGE_KEY = 'studio-audio-layout';

/** Raw interface capture — no browser AGC / NS / EC. */
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
    stageEl?.classList.toggle('is-on-air', live);
    if (modeEl) {
        modeEl.textContent = live ? 'Live now' : 'Off air';
    }
    if (airLabel) {
        airLabel.textContent = live ? 'LIVE NOW' : 'OFF AIR';
    }
    if (heroHint) {
        heroHint.textContent = live
            ? 'You’re on air — cue stays off unless you enable headphones'
            : 'Click Start to go live';
    }
    if (btnStart) {
        btnStart.textContent = live ? 'On air' : 'Start';
        btnStart.disabled = live;
    }
    if (live) {
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
    if (name === 'NotAllowedError' || /permission|denied/i.test(msg)) {
        return 'Microphone permission denied. Allow the mic in your browser settings and try again.';
    }
    if (name === 'NotFoundError' || /requested device not found/i.test(msg)) {
        return 'Could not open that microphone. Pick another input, or reload and allow mic access.';
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
        setStatus('Could not start cue monitor — click Start/cue again after interacting with the page.');
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
            setStatus('Output layout changed — Stop and Start again to apply.');
        }
    });
}

copyBtn?.addEventListener('click', async () => {
    const text = listenUrlEl?.textContent?.trim();
    if (!text) {
        return;
    }
    try {
        await navigator.clipboard.writeText(text);
        setStatus('Listen link copied.');
    } catch {
        setStatus('Could not copy listen link.');
    }
});

micFaderEl?.addEventListener('input', applyMicGain);
auxFaderEl?.addEventListener('input', applyAuxGain);
playlistFaderEl?.addEventListener('input', applyPlaylistGain);

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
            el.style.height = '0%';
        }
    }
    if (meterLabel) {
        meterLabel.textContent = '—';
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
    el.style.height = `${pct}%`;
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
            meterLabel.textContent = masterRms > 0.02 ? 'SIGNAL' : '—';
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

    const fmtpValue = `minptime=10;useinbandfec=1;stereo=1;sprop-stereo=1;maxaveragebitrate=${OPUS_MAX_BITRATE};maxplaybackrate=48000`;

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
    if (audioCtx && mixDest && micGain && playlistGain && cueDest) {
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

    // Publish path only — never audioCtx.destination.
    micGain.connect(mixDest);
    auxGain.connect(mixDest);
    playlistGain.connect(mixDest);
    micGain.connect(masterAnalyser);
    auxGain.connect(masterAnalyser);
    playlistGain.connect(masterAnalyser);

    // Cue taps (gain 0 until headphones buttons are enabled).
    micGain.connect(micCueGain);
    auxGain.connect(auxCueGain);
    playlistGain.connect(playlistCueGain);
    micCueGain.connect(cueDest);
    auxCueGain.connect(cueDest);
    playlistCueGain.connect(cueDest);

    micGain.connect(micAnalyser);
    auxGain.connect(auxAnalyser);
    playlistGain.connect(playlistAnalyser);

    applyMicGain();
    applyAuxGain();
    applyPlaylistGain();
    startMeters();
}

/**
 * @param {string} [deviceId]
 */
async function openDevice(deviceId = '') {
    const base = { ...CLEAN_AUDIO };
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
    return navigator.mediaDevices.getUserMedia({ audio: base, video: false });
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
        const inputs = devices.filter((d) => d.kind === 'audioinput');
        const outputs = devices.filter((d) => d.kind === 'audiooutput');

        if (audioSelect) {
            const previous = audioSelect.value;
            audioSelect.innerHTML = '';
            for (const d of inputs) {
                const opt = document.createElement('option');
                opt.value = d.deviceId;
                opt.textContent = d.label || `Input ${audioSelect.length + 1}`;
                audioSelect.appendChild(opt);
            }
            if (previous) {
                audioSelect.value = previous;
            }
        }

        if (auxSelect) {
            const previous = auxSelect.value;
            auxSelect.innerHTML = '';
            const none = document.createElement('option');
            none.value = '';
            none.textContent = 'Select source';
            auxSelect.appendChild(none);
            for (const d of inputs) {
                const opt = document.createElement('option');
                opt.value = d.deviceId;
                opt.textContent = d.label || `Input ${auxSelect.length}`;
                auxSelect.appendChild(opt);
            }
            if (previous) {
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
            for (const d of outputs) {
                const opt = document.createElement('option');
                opt.value = d.deviceId;
                opt.textContent = d.label || `Output ${outputSelect.length}`;
                outputSelect.appendChild(opt);
            }
            if (previous) {
                outputSelect.value = previous;
            }
        }
    } catch {
        setStatus('Could not list audio devices.');
    }
}

audioSelect?.addEventListener('change', async () => {
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

auxSelect?.addEventListener('change', async () => {
    if (!isLive) {
        return;
    }
    try {
        await attachAuxToMixer();
        setStatus(auxSelect.value ? 'Any Input armed — still on air.' : 'Any Input cleared.');
    } catch (e) {
        setStatus(friendlyError(e));
    }
});

function wireFileChannelAudio(channel) {
    if (!audioCtx || !playlistGain || channel.source) {
        return;
    }
    channel.source = audioCtx.createMediaElementSource(channel.audio);
    channel.gain = audioCtx.createGain();
    channel.gain.gain.value = 1;
    channel.source.connect(channel.gain);
    channel.gain.connect(playlistGain);
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
    URL.revokeObjectURL(channel.objectUrl);
    channel.card.remove();
    fileChannels.delete(id);
    updatePlaylistMeta();
}

function addFileChannel(file) {
    const id = `file-${++fileChannelSeq}`;
    const objectUrl = URL.createObjectURL(file);
    const audio = new Audio(objectUrl);
    audio.loop = false;
    audio.preload = 'auto';
    audio.muted = true;
    audio.volume = 0;

    const card = document.createElement('div');
    card.className = 'mixer-track';
    card.dataset.id = id;
    card.innerHTML = `
        <div class="mixer-track-head">
            <span class="mixer-track-name" title=""></span>
            <button type="button" class="mixer-track-remove" data-remove>Remove</button>
        </div>
        <div class="mixer-track-controls">
            <button type="button" data-play>Play</button>
            <button type="button" data-pause>Pause</button>
            <button type="button" data-restart>Restart</button>
        </div>
    `;
    const nameEl = card.querySelector('.mixer-track-name');
    if (nameEl) {
        nameEl.textContent = file.name;
        nameEl.title = file.name;
    }

    channelsEl?.appendChild(card);

    const channel = {
        id,
        name: file.name,
        objectUrl,
        audio,
        source: null,
        gain: null,
        card,
        duration: 0,
    };
    fileChannels.set(id, channel);
    updatePlaylistMeta();

    audio.addEventListener('loadedmetadata', () => {
        channel.duration = audio.duration || 0;
        updatePlaylistMeta();
    });

    if (audioCtx && playlistGain) {
        wireFileChannelAudio(channel);
    }

    card.querySelector('[data-remove]')?.addEventListener('click', () => {
        removeFileChannel(id);
    });

    card.querySelector('[data-play]')?.addEventListener('click', async () => {
        try {
            await ensureMixer();
            wireFileChannelAudio(channel);
            await channel.audio.play();
        } catch (e) {
            setStatus(e instanceof Error ? e.message : 'Could not play file.');
        }
    });

    card.querySelector('[data-pause]')?.addEventListener('click', () => {
        channel.audio.pause();
    });

    card.querySelector('[data-restart]')?.addEventListener('click', async () => {
        try {
            await ensureMixer();
            wireFileChannelAudio(channel);
            channel.audio.currentTime = 0;
            await channel.audio.play();
        } catch (e) {
            setStatus(e instanceof Error ? e.message : 'Could not restart file.');
        }
    });
}

btnAddFile?.addEventListener('click', () => {
    fileInput?.click();
});

fileInput?.addEventListener('change', () => {
    const files = Array.from(fileInput.files || []);
    for (const file of files) {
        if (!file.type.startsWith('audio/') && !/\.(mp3|wav|m4a|aac|ogg|flac|webm)$/i.test(file.name)) {
            setStatus(`Skipped non-audio file: ${file.name}`);
            continue;
        }
        addFileChannel(file);
    }
    fileInput.value = '';
    if (files.length) {
        setStatus(
            isLive
                ? 'Sound added. Press Play to include it in the live mix.'
                : 'Sound ready. Press Play when you want it in the mix, then Start.',
        );
    }
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

    try {
        await track.applyConstraints(CLEAN_AUDIO);
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
        throw new Error('Server answered without Opus (phone codec). Stop and Start again after refresh.');
    }
    await pc.setRemoteDescription({ type: 'answer', sdp: answerSdp });
    silenceRemoteAudio(pc);
    await applyMaxAudioBitrate(pc);
}

async function primeMicrophone() {
    if (!window.isSecureContext) {
        setStatus(`Studio needs HTTPS for the microphone. Open ${httpsStudioUrl()}`);
        if (btnStart) {
            btnStart.disabled = true;
        }
        return;
    }
    if (!navigator.mediaDevices?.getUserMedia) {
        setStatus('This browser does not support microphone capture.');
        return;
    }
    try {
        const priming = await navigator.mediaDevices.getUserMedia({
            audio: CLEAN_AUDIO,
            video: false,
        });
        for (const t of priming.getTracks()) {
            t.stop();
        }
        await loadDevices();
        setToggle(auxMuteBtn, auxMuted);
        setStatus('Ready. Cue is off — Studio stays silent. Start to go live; use the listen link (or cue + headphones) to monitor.');
    } catch (e) {
        setStatus(friendlyError(e));
    }
}

await primeMicrophone();

if (navigator.mediaDevices && 'addEventListener' in navigator.mediaDevices) {
    navigator.mediaDevices.addEventListener('devicechange', loadDevices);
}

btnStart?.addEventListener('click', async () => {
    if (!window.isSecureContext) {
        setStatus(`Studio needs HTTPS for the microphone. Open ${httpsStudioUrl()}`);
        return;
    }
    if (!whipUrl) {
        setStatus('WHIP URL is not configured.');
        return;
    }
    btnStart.disabled = true;
    setStatus('Starting…');

    try {
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
                ? 'You’re on air. Cue is on — use headphones to avoid feedback.'
                : 'You’re on air. Studio is silent (cue off). Monitor on the listen link or enable cue with headphones.',
        );
        setOnAir(true);
        btnStop.disabled = false;
    } catch (e) {
        console.error(e);
        setStatus(friendlyError(e));
        btnStart.disabled = false;
        setOnAir(false);
        publishMode = null;
        await teardownLive();
    }
});

btnStop?.addEventListener('click', async () => {
    btnStop.disabled = true;
    setStatus('Stopping…');
    await teardownLive();
    setStatus('Stopped. Ready when you are.');
    btnStart.disabled = false;
});

async function teardownLive() {
    setOnAir(false);
    publishMode = null;
    stopMeter();

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
}

const btnAddGallery = document.getElementById('btn-add-gallery');
const galleryInput = document.getElementById('gallery-input');
const studioGalleryList = document.getElementById('studio-gallery-list');
const galleryUploadUrl = root?.dataset.galleryUploadUrl;
const galleryCsrf = root?.dataset.csrf || document.querySelector('meta[name="csrf-token"]')?.content;

btnAddGallery?.addEventListener('click', () => {
    galleryInput?.click();
});

galleryInput?.addEventListener('change', async () => {
    const files = Array.from(galleryInput.files || []);
    if (!galleryUploadUrl || files.length === 0) {
        return;
    }
    for (const file of files) {
        const body = new FormData();
        body.append('image', file);
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
                setStatus('Could not upload photo. Try again.');
                continue;
            }
            const data = await res.json();
            const image = data.image;
            if (image && studioGalleryList) {
                const figure = document.createElement('figure');
                figure.className = 'mixer-gallery-thumb';
                figure.dataset.id = String(image.id);
                figure.innerHTML = `<img src="${image.url}" alt="${image.caption || 'Gallery photo'}">`;
                studioGalleryList.prepend(figure);
            }
            setStatus('Photo posted to the listener gallery.');
        } catch {
            setStatus('Could not upload photo.');
        }
    }
    galleryInput.value = '';
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
        micGain = null;
        auxGain = null;
        playlistGain = null;
    }
});
