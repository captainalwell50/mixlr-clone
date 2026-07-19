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
const micGainInput = document.getElementById('mic-gain');
const micGainLabel = document.getElementById('mic-gain-label');
const audioLayoutSelect = document.getElementById('audio-layout');
const fxNoise = document.getElementById('fx-noise');
const fxEcho = document.getElementById('fx-echo');
const fxAgc = document.getElementById('fx-agc');
const fxVoice = document.getElementById('fx-voice');
const fxHighpass = document.getElementById('fx-highpass');
const fxCompress = document.getElementById('fx-compress');
const fxLimit = document.getElementById('fx-limit');

/** Opus fullband max (bits/sec). */
const OPUS_MAX_BITRATE = 510_000;
const LAYOUT_STORAGE_KEY = 'studio-audio-layout';
const FX_STORAGE_KEY = 'studio-audio-fx';

let pc = null;
/** @type {string|null} */
let whipResourceUrl = null;
/** @type {AudioContext|null} */
let audioCtx = null;
/** @type {MediaStreamAudioDestinationNode|null} */
let mixDest = null;
/** @type {GainNode|null} */
let micGain = null;
/** @type {BiquadFilterNode|null} */
let fxHighpassNode = null;
/** @type {DynamicsCompressorNode|null} */
let fxCompressNode = null;
/** @type {DynamicsCompressorNode|null} */
let fxLimitNode = null;
/** @type {MediaStreamAudioSourceNode|null} */
let micSource = null;
/** @type {ChannelSplitterNode|null} */
let micSplitter = null;
/** @type {GainNode|null} */
let micMonoSum = null;
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

function micGainPercent() {
    return Number(micGainInput?.value ?? 100);
}

/** @returns {'mono' | 'stereo'} */
function audioLayout() {
    const v = audioLayoutSelect?.value;
    return v === 'stereo' ? 'stereo' : 'mono';
}

function isMono() {
    return audioLayout() === 'mono';
}

function captureChannelCount() {
    return isMono() ? 1 : 2;
}

function fxChecked(el) {
    return Boolean(el?.checked);
}

function needsGraphFx() {
    return fxChecked(fxHighpass) || fxChecked(fxCompress) || fxChecked(fxLimit);
}

/** Mixer when mono, files, level ≠ 100%, or Web Audio enhance FX. */
function needsMixer() {
    return isMono() || fileChannels.size > 0 || micGainPercent() !== 100 || needsGraphFx();
}

function captureConstraints() {
    return {
        sampleRate: { ideal: 48000 },
        sampleSize: { ideal: 16 },
        channelCount: { ideal: 2 },
        echoCancellation: fxChecked(fxEcho),
        noiseSuppression: fxChecked(fxNoise),
        autoGainControl: fxChecked(fxAgc),
        voiceIsolation: fxChecked(fxVoice),
    };
}

function loadFxPrefs() {
    try {
        const raw = localStorage.getItem(FX_STORAGE_KEY);
        if (!raw) {
            return;
        }
        const prefs = JSON.parse(raw);
        const map = [
            [fxNoise, 'noise'],
            [fxEcho, 'echo'],
            [fxAgc, 'agc'],
            [fxVoice, 'voice'],
            [fxHighpass, 'highpass'],
            [fxCompress, 'compress'],
            [fxLimit, 'limit'],
        ];
        for (const [el, key] of map) {
            if (el && typeof prefs[key] === 'boolean') {
                el.checked = prefs[key];
            }
        }
    } catch {
        /* ignore */
    }
}

function saveFxPrefs() {
    const prefs = {
        noise: fxChecked(fxNoise),
        echo: fxChecked(fxEcho),
        agc: fxChecked(fxAgc),
        voice: fxChecked(fxVoice),
        highpass: fxChecked(fxHighpass),
        compress: fxChecked(fxCompress),
        limit: fxChecked(fxLimit),
    };
    localStorage.setItem(FX_STORAGE_KEY, JSON.stringify(prefs));
}

loadFxPrefs();

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

for (const el of [fxNoise, fxEcho, fxAgc, fxVoice, fxHighpass, fxCompress, fxLimit]) {
    el?.addEventListener('change', () => {
        saveFxPrefs();
        if (isLive) {
            setStatus('Advanced audio changed — stop and Go live again to apply.');
        } else {
            rebuildFxChain();
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
 * @param {string} sdp
 */
function preferHighQualityOpus(sdp) {
    const opusPts = new Set();
    for (const match of sdp.matchAll(/^a=rtpmap:(\d+) opus\/48000(?:\/\d+)?/gim)) {
        opusPts.add(match[1]);
    }
    if (opusPts.size === 0) {
        return sdp;
    }

    const stereoFlag = isMono() ? '0' : '1';
    const fmtpValue = `minptime=10;useinbandfec=1;stereo=${stereoFlag};sprop-stereo=${stereoFlag};maxaveragebitrate=${OPUS_MAX_BITRATE};maxplaybackrate=48000`;
    let out = sdp;

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

function ensureFxNodes() {
    if (!audioCtx) {
        return;
    }
    if (!fxHighpassNode) {
        fxHighpassNode = audioCtx.createBiquadFilter();
        fxHighpassNode.type = 'highpass';
        fxHighpassNode.frequency.value = 80;
        fxHighpassNode.Q.value = 0.7;
    }
    if (!fxCompressNode) {
        fxCompressNode = audioCtx.createDynamicsCompressor();
        fxCompressNode.threshold.value = -24;
        fxCompressNode.knee.value = 18;
        fxCompressNode.ratio.value = 3;
        fxCompressNode.attack.value = 0.01;
        fxCompressNode.release.value = 0.25;
    }
    if (!fxLimitNode) {
        fxLimitNode = audioCtx.createDynamicsCompressor();
        fxLimitNode.threshold.value = -3;
        fxLimitNode.knee.value = 2;
        fxLimitNode.ratio.value = 20;
        fxLimitNode.attack.value = 0.002;
        fxLimitNode.release.value = 0.12;
    }
}

/** Wire micGain → optional FX → mixDest. */
function rebuildFxChain() {
    if (!micGain || !mixDest) {
        return;
    }
    ensureFxNodes();
    try {
        micGain.disconnect();
        fxHighpassNode?.disconnect();
        fxCompressNode?.disconnect();
        fxLimitNode?.disconnect();
    } catch {
        /* ignore */
    }

    /** @type {AudioNode} */
    let node = micGain;
    if (fxChecked(fxHighpass) && fxHighpassNode) {
        node.connect(fxHighpassNode);
        node = fxHighpassNode;
    }
    if (fxChecked(fxCompress) && fxCompressNode) {
        node.connect(fxCompressNode);
        node = fxCompressNode;
    }
    if (fxChecked(fxLimit) && fxLimitNode) {
        node.connect(fxLimitNode);
        node = fxLimitNode;
    }
    node.connect(mixDest);
}

async function ensureMixer() {
    if (audioCtx && mixDest && micGain) {
        if (audioCtx.state === 'suspended') {
            await audioCtx.resume();
        }
        try {
            mixDest.channelCount = captureChannelCount();
        } catch {
            /* optional */
        }
        rebuildFxChain();
        return;
    }
    if (!audioCtx) {
        audioCtx = new AudioContext({ sampleRate: 48000, latencyHint: 'playback' });
    } else if (audioCtx.state === 'suspended') {
        await audioCtx.resume();
    }
    mixDest = audioCtx.createMediaStreamDestination();
    try {
        mixDest.channelCount = captureChannelCount();
        mixDest.channelCountMode = 'explicit';
        mixDest.channelInterpretation = 'speakers';
    } catch {
        /* optional */
    }
    micGain = audioCtx.createGain();
    micGain.gain.value = micGainPercent() / 100;
    ensureFxNodes();
    rebuildFxChain();
}

async function openMicrophone() {
    const preferredId = audioSelect?.value || '';
    const base = captureConstraints();
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
        micSplitter?.disconnect();
        micMonoSum?.disconnect();
    } catch {
        /* ignore */
    }
    micSource = null;
    micSplitter = null;
    micMonoSum = null;
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
    try {
        mixDest.channelCount = captureChannelCount();
    } catch {
        /* optional */
    }
    disconnectMicGraph();
    stopMicTracks();
    micStream = await openMicrophone();
    micSource = audioCtx.createMediaStreamSource(micStream);

    if (isMono()) {
        // Sum left + right so a left-only interface still plays in both ears.
        micSplitter = audioCtx.createChannelSplitter(2);
        micMonoSum = audioCtx.createGain();
        micMonoSum.gain.value = 0.707;
        micSource.connect(micSplitter);
        micSplitter.connect(micMonoSum, 0);
        micSplitter.connect(micMonoSum, 1);
        micMonoSum.connect(micGain);
    } else {
        micSource.connect(micGain);
    }
    rebuildFxChain();
    await startMeterFromStream(mixDest.stream);
}

micGainInput?.addEventListener('input', () => {
    const pct = micGainPercent();
    if (micGainLabel) {
        micGainLabel.textContent = `${pct}%`;
    }
    if (micGain) {
        micGain.gain.value = pct / 100;
    }
    if (isLive && publishMode === 'direct' && pct !== 100) {
        setStatus('Mic level changed — stop and Go live again to apply level in the mix (or leave at 100% for best quality).');
    }
});

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
        await track.applyConstraints({
            channelCount: captureChannelCount(),
            sampleRate: 48000,
            echoCancellation: false,
            noiseSuppression: false,
            autoGainControl: false,
        });
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

    await applyMaxAudioBitrate(pc);

    const offer = await pc.createOffer();
    const highQualitySdp = preferHighQualityOpus(offer.sdp || '');
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
    await pc.setRemoteDescription({ type: 'answer', sdp: answerSdp });
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
        // Request with HQ constraints so device labels + permission match Go live.
        const priming = await navigator.mediaDevices.getUserMedia({
            audio: captureConstraints(),
            video: false,
        });
        for (const t of priming.getTracks()) {
            t.stop();
        }
        await loadDevices();
        setStatus('Microphone ready. Mono by default. Open Advanced audio only if you need noise reduction (laptop mic).');
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
            setStatus('Starting mix (files / mic level)…');
            await attachMicrophoneToMixer();
            for (const channel of fileChannels.values()) {
                wireFileChannelAudio(channel);
            }
            stream = mixDest.stream;
        } else {
            // Highest quality: mic track → WebRTC with no Web Audio remux.
            publishMode = 'direct';
            setStatus('Starting direct mic (highest quality)…');
            stream = await openMicOnly();
        }

        await publishWhip(stream);

        const layoutLabel = isMono() ? 'mono (both ears)' : 'stereo';
        setStatus(
            publishMode === 'direct'
                ? `You’re on air — direct ${layoutLabel}. Keep this tab open.`
                : `You’re on air — ${layoutLabel} mix. Keep this tab open.`,
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
    disconnectMicGraph();
    stopMicTracks();
    if (audioCtx) {
        void audioCtx.close().catch(() => {});
        audioCtx = null;
        mixDest = null;
        micGain = null;
        fxHighpassNode = null;
        fxCompressNode = null;
        fxLimitNode = null;
    }
});
