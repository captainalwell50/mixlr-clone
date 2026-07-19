import './bootstrap';

const root = document.getElementById('studio-root');
const whipUrl = root?.dataset.whipUrl;
const audioSelect = document.getElementById('audio-input');
const btnStart = document.getElementById('btn-start');
const btnStop = document.getElementById('btn-stop');
const btnAddFile = document.getElementById('btn-add-file');
const fileInput = document.getElementById('file-input');
const channelsEl = document.getElementById('audio-channels');
const statusEl = document.getElementById('studio-status');
const meterEl = document.getElementById('level-meter');
const meterLabel = document.getElementById('meter-label');
const stageEl = document.getElementById('studio-stage');
const modeEl = document.getElementById('studio-mode');
const copyBtn = document.getElementById('btn-copy-listen');
const listenUrlEl = document.getElementById('listen-url');
const audioLayoutSelect = document.getElementById('audio-layout');

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
    // Chromium legacy keys (ignored when unsupported).
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
/** @type {GainNode|null} */
let micGain = null;
/** @type {MediaStreamAudioSourceNode|null} */
let micSource = null;
/** @type {MediaStream|null} */
let micStream = null;
/** @type {AnalyserNode|null} */
let analyser = null;
/** @type {MediaStreamAudioSourceNode|null} */
let meterSource = null;
let meterRaf = 0;
let fileChannelSeq = 0;
let isLive = false;
/** @type {'direct' | 'mixer' | null} */
let publishMode = null;

/** @type {Map<string, {
 *   id: string,
 *   name: string,
 *   objectUrl: string,
 *   audio: HTMLAudioElement,
 *   source: MediaElementAudioSourceNode|null,
 *   gain: GainNode|null,
 *   card: HTMLElement,
 * }>} */
const fileChannels = new Map();

function setStatus(message) {
    if (statusEl) {
        statusEl.textContent = message;
    }
}

function setOnAir(live) {
    isLive = live;
    stageEl?.classList.toggle('is-on-air', live);
    if (modeEl) {
        modeEl.textContent = live ? 'On air' : 'Broadcaster';
        modeEl.classList.toggle('stage-broadcaster', !live);
    }
    if (btnStart) {
        btnStart.textContent = live ? 'On air' : 'Go live';
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

/**
 * Mixer only when audio files are added.
 * Mono no longer remuxes through Web Audio (that muffled the Scarlett) —
 * both-ears mono is Opus stereo=0 on the direct mic track.
 */
function needsMixer() {
    return fileChannels.size > 0;
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
            setStatus('Output layout changed — stop and Go live again to apply.');
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
        copyBtn.textContent = 'Copied';
        window.setTimeout(() => {
            copyBtn.textContent = 'Copy link';
        }, 1600);
    } catch {
        copyBtn.textContent = 'Copy failed';
    }
});

function stopMeter() {
    if (meterRaf) {
        cancelAnimationFrame(meterRaf);
        meterRaf = 0;
    }
    try {
        meterSource?.disconnect();
        analyser?.disconnect();
    } catch {
        /* ignore */
    }
    meterSource = null;
    analyser = null;
    if (meterEl) {
        meterEl.style.width = '0%';
    }
    if (meterLabel) {
        meterLabel.textContent = '—';
    }
}

/**
 * Meter-only tap (never used as the published track).
 * @param {MediaStream} stream
 */
async function startMeterFromStream(stream) {
    stopMeter();
    try {
        if (!audioCtx) {
            audioCtx = new AudioContext({ sampleRate: 48000, latencyHint: 'playback' });
        }
        if (audioCtx.state === 'suspended') {
            await audioCtx.resume();
        }
        meterSource = audioCtx.createMediaStreamSource(stream);
        analyser = audioCtx.createAnalyser();
        analyser.fftSize = 256;
        meterSource.connect(analyser);
        const data = new Uint8Array(analyser.frequencyBinCount);

        const tick = () => {
            if (!analyser) {
                return;
            }
            analyser.getByteTimeDomainData(data);
            let sum = 0;
            for (let i = 0; i < data.length; i++) {
                const v = (data[i] - 128) / 128;
                sum += v * v;
            }
            const rms = Math.sqrt(sum / data.length);
            const pct = Math.min(100, Math.round(rms * 220));
            if (meterEl) {
                meterEl.style.width = `${pct}%`;
                meterEl.style.background = pct > 75
                    ? '#d4a24c'
                    : 'var(--stage-accent, #3d9b7a)';
            }
            if (meterLabel) {
                meterLabel.textContent = pct > 2 ? 'Signal' : 'Silence';
            }
            meterRaf = requestAnimationFrame(tick);
        };
        meterRaf = requestAnimationFrame(tick);
    } catch {
        /* meter is optional */
    }
}

/**
 * Force Opus only — G722/PCMU sound like phone audio and break HLS.
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
 * Drop telephony codecs from the audio m-line so negotiation cannot pick G722.
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

    const stereoFlag = isMono() ? '0' : '1';
    const fmtpValue = `minptime=10;useinbandfec=1;stereo=${stereoFlag};sprop-stereo=${stereoFlag};maxaveragebitrate=${OPUS_MAX_BITRATE};maxplaybackrate=48000`;

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
    if (audioCtx && mixDest && micGain) {
        if (audioCtx.state === 'suspended') {
            await audioCtx.resume();
        }
        try {
            mixDest.channelCount = 2;
        } catch {
            /* optional */
        }
        return;
    }
    if (!audioCtx) {
        audioCtx = new AudioContext({ sampleRate: 48000, latencyHint: 'interactive' });
    } else if (audioCtx.state === 'suspended') {
        await audioCtx.resume();
    }
    mixDest = audioCtx.createMediaStreamDestination();
    try {
        mixDest.channelCount = 2;
        mixDest.channelCountMode = 'explicit';
        mixDest.channelInterpretation = 'speakers';
    } catch {
        /* optional */
    }
    micGain = audioCtx.createGain();
    micGain.gain.value = 1;
    micGain.connect(mixDest);
}

async function openMicrophone() {
    const preferredId = audioSelect?.value || '';
    const base = { ...CLEAN_AUDIO };
    if (preferredId) {
        try {
            return await navigator.mediaDevices.getUserMedia({
                audio: { ...base, deviceId: { exact: preferredId } },
                video: false,
            });
        } catch {
            try {
                return await navigator.mediaDevices.getUserMedia({
                    audio: { ...base, deviceId: { ideal: preferredId } },
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
    } catch {
        /* ignore */
    }
    micSource = null;
}

function stopMicTracks() {
    if (micStream) {
        for (const t of micStream.getTracks()) {
            t.stop();
        }
        micStream = null;
    }
}

async function openMicOnly() {
    disconnectMicGraph();
    stopMicTracks();
    micStream = await openMicrophone();
    await startMeterFromStream(micStream);
    return micStream;
}

async function attachMicrophoneToMixer() {
    await ensureMixer();
    disconnectMicGraph();
    stopMicTracks();
    micStream = await openMicrophone();
    micSource = audioCtx.createMediaStreamSource(micStream);
    // File mix keeps a clean stereo graph; Opus mono flag still handles both-ears encode.
    micSource.connect(micGain);
    await startMeterFromStream(mixDest.stream);
}

async function loadDevices() {
    if (!audioSelect) {
        return;
    }
    try {
        const devices = await navigator.mediaDevices.enumerateDevices();
        const inputs = devices.filter((d) => d.kind === 'audioinput');
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
    } catch {
        setStatus('Could not list audio devices.');
    }
}

audioSelect?.addEventListener('change', async () => {
    if (!isLive || !pc) {
        return;
    }
    try {
        if (publishMode === 'direct') {
            const stream = await openMicOnly();
            const track = stream.getAudioTracks()[0];
            const sender = pc.getSenders().find((s) => s.track?.kind === 'audio' || s.track == null);
            if (sender && track) {
                await sender.replaceTrack(track);
                await applyMaxAudioBitrate(pc);
            }
            setStatus('Switched microphone — still on air (direct).');
            return;
        }
        await attachMicrophoneToMixer();
        setStatus('Switched microphone — still on air (mixer).');
    } catch (e) {
        setStatus(friendlyError(e));
    }
});

function wireFileChannelAudio(channel) {
    if (!audioCtx || !mixDest || channel.source) {
        return;
    }
    channel.source = audioCtx.createMediaElementSource(channel.audio);
    channel.gain = audioCtx.createGain();
    const gainInput = channel.card.querySelector('[data-gain]');
    channel.gain.gain.value = Number(gainInput?.value ?? 100) / 100;
    channel.source.connect(channel.gain);
    channel.gain.connect(mixDest);
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
}

function addFileChannel(file) {
    const id = `file-${++fileChannelSeq}`;
    const objectUrl = URL.createObjectURL(file);
    const audio = new Audio(objectUrl);
    audio.loop = false;
    audio.preload = 'auto';

    const card = document.createElement('div');
    card.className = 'stage-channel-card';
    card.dataset.channel = 'file';
    card.dataset.id = id;
    card.innerHTML = `
        <div class="stage-channel-head">
            <span class="stage-channel-badge">File</span>
            <span class="stage-channel-name" title=""></span>
            <button type="button" class="stage-channel-remove" data-remove>Remove</button>
        </div>
        <div class="stage-channel-gain">
            <label>Level</label>
            <input data-gain type="range" min="0" max="150" value="100" step="1">
            <span class="stage-channel-gain-value" data-gain-label>100%</span>
        </div>
        <div class="stage-channel-file-controls">
            <button type="button" data-play>Play</button>
            <button type="button" data-pause>Pause</button>
            <button type="button" data-restart>Restart</button>
        </div>
    `;
    const nameEl = card.querySelector('.stage-channel-name');
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
    };
    fileChannels.set(id, channel);

    if (audioCtx && mixDest) {
        wireFileChannelAudio(channel);
    }

    card.querySelector('[data-remove]')?.addEventListener('click', () => {
        removeFileChannel(id);
    });

    const gainInput = card.querySelector('[data-gain]');
    const gainLabel = card.querySelector('[data-gain-label]');
    gainInput?.addEventListener('input', () => {
        const pct = Number(gainInput.value);
        if (gainLabel) {
            gainLabel.textContent = `${pct}%`;
        }
        if (channel.gain) {
            channel.gain.gain.value = pct / 100;
        }
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
            isLive && publishMode === 'direct'
                ? 'File added — stop and Go live again so the mixer can include it.'
                : isLive
                    ? 'File channel added. Press Play to include it in the live mix.'
                    : 'File channel ready. Press Play when you want it in the mix, then Go live.',
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

    // Fallback if sendEncodings unsupported at construction time.
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
        throw new Error('Server answered without Opus (phone codec). Stop and Go live again after refresh.');
    }
    await pc.setRemoteDescription({ type: 'answer', sdp: answerSdp });
    await applyMaxAudioBitrate(pc);

    const sender = transceiver.sender || pc.getSenders().find((s) => s.track?.kind === 'audio');
    const params = sender?.getParameters?.();
    const codec = params?.codecs?.[0]?.mimeType || '';
    if (codec && !/opus/i.test(codec)) {
        console.warn('Unexpected audio codec after negotiate:', codec);
    }
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
        setStatus('Microphone ready. Mono (both ears) is selected by default. Pick your Scarlett, then Go live.');
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
        let stream;
        if (needsMixer()) {
            publishMode = 'mixer';
            setStatus('Starting file mix…');
            await attachMicrophoneToMixer();
            for (const channel of fileChannels.values()) {
                wireFileChannelAudio(channel);
            }
            stream = mixDest.stream;
        } else {
            // Scarlett track → WebRTC Opus (no Web Audio remux).
            publishMode = 'direct';
            setStatus(
                isMono()
                    ? 'Starting clean Scarlett → Opus mono (both ears)…'
                    : 'Starting clean Scarlett → Opus stereo…',
            );
            stream = await openMicOnly();
        }

        await publishWhip(stream);

        const layoutLabel = isMono() ? 'Opus mono (both ears)' : 'Opus stereo';
        setStatus(
            publishMode === 'direct'
                ? `You’re on air — direct ${layoutLabel}. Keep this tab open.`
                : `You’re on air — file mix / ${layoutLabel}. Keep this tab open.`,
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
    stopMicTracks();

    for (const channel of fileChannels.values()) {
        channel.audio.pause();
    }
}

window.addEventListener('pagehide', () => {
    for (const id of [...fileChannels.keys()]) {
        removeFileChannel(id);
    }
    stopMeter();
    if (audioCtx) {
        void audioCtx.close().catch(() => {});
        audioCtx = null;
        mixDest = null;
        micGain = null;
    }
});
