/**
 * Headless Web Audio mixer for Sound Mix Live Studio Desktop.
 * Flutter drives this via window.SoundMixDesktop + SoundMixHost.postMessage.
 */
import './bootstrap';

/** Stereo Opus send cap. The mixer page supplies the admin rate. */
const OPUS_SEND_RATES = new Set([128000, 160000, 192000, 256000, 320000, 510000]);

function readOpusSendBitrate(value) {
    const rate = Number(value);
    return OPUS_SEND_RATES.has(rate) ? rate : 320000;
}

let opusSendBitrate = readOpusSendBitrate(document.body?.dataset.opusBitrate);
const CLEAN_AUDIO = {
    sampleRate: { ideal: 48000 },
    echoCancellation: false,
    noiseSuppression: false,
    autoGainControl: false,
    voiceIsolation: false,
};

/** @type {1 | 2} */
let receivedChannels = 1;
/** @type {{ splitter: ChannelSplitterNode, left: AnalyserNode, right: AnalyserNode, timer: ReturnType<typeof setTimeout>|null } | null} */
let layoutProbe = null;

function applyReceivedMixLayout() {
    const n = receivedChannels >= 2 ? 2 : 1;
    try {
        if (mixDest) {
            mixDest.channelCount = n;
            mixDest.channelCountMode = 'explicit';
        }
        if (cueDest) {
            cueDest.channelCount = n;
        }
    } catch {
        /* optional */
    }
}

/**
 * @param {Float32Array} left
 * @param {Float32Array} right
 * @returns {0 | 1 | 2}
 */
function decideReceivedLayout(left, right) {
    const n = Math.min(left.length, right.length);
    if (n < 32) {
        return 0;
    }
    let sumL2 = 0;
    let sumR2 = 0;
    let sumLR = 0;
    let peakL = 0;
    let peakR = 0;
    for (let i = 0; i < n; i += 1) {
        const l = left[i];
        const r = right[i];
        sumL2 += l * l;
        sumR2 += r * r;
        sumLR += l * r;
        const al = Math.abs(l);
        const ar = Math.abs(r);
        if (al > peakL) peakL = al;
        if (ar > peakR) peakR = ar;
    }
    const rmsL = Math.sqrt(sumL2 / n);
    const rmsR = Math.sqrt(sumR2 / n);
    const loud = Math.max(rmsL, rmsR, peakL * 0.5, peakR * 0.5);
    if (loud < 0.004) {
        return 0;
    }
    if (Math.min(rmsL, rmsR) < loud * 0.12) {
        return 1;
    }
    const denom = Math.sqrt(sumL2 * sumR2);
    const corr = denom > 0 ? sumLR / denom : 1;
    return corr > 0.995 ? 1 : 2;
}

function stopLayoutProbe() {
    if (!layoutProbe) return;
    if (layoutProbe.timer) clearTimeout(layoutProbe.timer);
    try { layoutProbe.splitter.disconnect(); } catch { /* ignore */ }
    try { layoutProbe.left.disconnect(); } catch { /* ignore */ }
    try { layoutProbe.right.disconnect(); } catch { /* ignore */ }
    layoutProbe = null;
}

function startLayoutProbe(sourceNode) {
    stopLayoutProbe();
    if (!audioCtx || !sourceNode) return;
    if ((sourceNode.channelCount || 1) < 2) {
        receivedChannels = 1;
        applyReceivedMixLayout();
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
    const probe = { splitter, left, right, timer: null };
    layoutProbe = probe;
    let attempts = 0;
    let stereoHits = 0;
    const tick = () => {
        if (layoutProbe !== probe) return;
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
                receivedChannels = 2;
                applyReceivedMixLayout();
                stopLayoutProbe();
                return;
            }
        } else if (decision === 1) {
            stereoHits = 0;
            receivedChannels = 1;
            applyReceivedMixLayout();
            if (attempts >= 8) {
                stopLayoutProbe();
                return;
            }
        }
        if (attempts >= 24) {
            stopLayoutProbe();
            return;
        }
        probe.timer = setTimeout(tick, 180);
    };
    probe.timer = setTimeout(tick, 120);
}

function noteReceivedChannels(stream, sourceNode) {
    const track = stream?.getAudioTracks?.()?.[0];
    const fromTrack = Number(track?.getSettings?.()?.channelCount || 0);
    if (fromTrack === 1 || sourceNode?.channelCount === 1) {
        receivedChannels = 1;
        applyReceivedMixLayout();
        return;
    }
    receivedChannels = 1;
    applyReceivedMixLayout();
    if (sourceNode) {
        startLayoutProbe(sourceNode);
    }
}

const cueAudio = document.getElementById('cue-audio');
const host = () => window.SoundMixHost;

/** @type {AudioContext|null} */
let audioCtx = null;
/** @type {MediaStreamAudioDestinationNode|null} */
let mixDest = null;
/** @type {MediaStreamAudioDestinationNode|null} */
let cueDest = null;
/** @type {GainNode|null} */
let masterGain = null;
/** @type {GainNode|null} */
let cueMasterGain = null;
/** @type {GainNode|null} */
let micGain = null;
/** @type {GainNode|null} */
let playlistGain = null;
/** @type {GainNode|null} */
let micCueGain = null;
/** @type {GainNode|null} */
let playlistCueGain = null;
/** @type {AnalyserNode|null} */
let masterAnalyser = null;
/** @type {AnalyserNode|null} */
let micAnalyser = null;
/** @type {AnalyserNode|null} */
let playlistAnalyser = null;
/** @type {MediaStream|null} */
let micStream = null;
/** @type {MediaStreamAudioSourceNode|null} */
let micSource = null;
/** @type {RTCPeerConnection|null} */
let pc = null;
/** @type {string|null} */
let whipResourceUrl = null;
let meterRaf = 0;
let trackSeq = 0;
let micMuted = false;
let playlistMuted = false;
let micCueOn = false;
let playlistCueOn = false;
let micFader = 1;
let playlistFader = 1;
let masterFader = 1;
/** @type {string} */
let selectedDeviceId = '';

/** @type {Map<string, {id:string, title:string, url:string, assetId:number|null, audio:HTMLAudioElement, source:MediaElementAudioSourceNode|null, gain:GainNode|null, ready:boolean, playing:boolean}>} */
const tracks = new Map();

function post(type, payload = {}) {
    const msg = JSON.stringify({ type, ...payload });
    try {
        if (typeof host()?.postMessage === 'function') {
            host().postMessage(msg);
            return;
        }
    } catch {
        /* flutter channel */
    }
    try {
        window.webkit?.messageHandlers?.SoundMixHost?.postMessage(msg);
    } catch {
        /* native host not ready */
    }
}

function createSilentAudioContext(options = {}) {
    try {
        return new AudioContext({ ...options, sinkId: { type: 'none' } });
    } catch {
        return new AudioContext(options);
    }
}

function rmsFromAnalyser(node) {
    if (!node) return 0;
    const data = new Uint8Array(node.frequencyBinCount);
    node.getByteTimeDomainData(data);
    let sum = 0;
    for (let i = 0; i < data.length; i++) {
        const v = (data[i] - 128) / 128;
        sum += v * v;
    }
    return Math.sqrt(sum / data.length);
}

function applyGains() {
    if (micGain) micGain.gain.value = micMuted ? 0 : micFader;
    if (playlistGain) playlistGain.gain.value = playlistMuted ? 0 : playlistFader;
    if (masterGain) masterGain.gain.value = masterFader;
    if (cueMasterGain) cueMasterGain.gain.value = masterFader;
    if (micCueGain) micCueGain.gain.value = micCueOn ? 1 : 0;
    if (playlistCueGain) playlistCueGain.gain.value = playlistCueOn ? 1 : 0;
}

async function syncCuePlayback() {
    if (!cueAudio || !cueDest) return;
    const any = micCueOn || playlistCueOn;
    if (!any) {
        cueAudio.pause();
        cueAudio.srcObject = null;
        return;
    }
    cueAudio.srcObject = cueDest.stream;
    try {
        await cueAudio.play();
    } catch {
        /* autoplay / no headphones */
    }
}

function startMeters() {
    cancelAnimationFrame(meterRaf);
    const tick = () => {
        post('levels', {
            master: rmsFromAnalyser(masterAnalyser),
            mic: rmsFromAnalyser(micAnalyser),
            playlist: rmsFromAnalyser(playlistAnalyser),
        });
        meterRaf = requestAnimationFrame(tick);
    };
    meterRaf = requestAnimationFrame(tick);
}

async function resumeAudioContext(ctx) {
    if (!ctx || ctx.state !== 'suspended') return;
    try {
        await withTimeout(ctx.resume(), 4000, 'AudioContext.resume');
    } catch {
        // WKWebView often keeps the context suspended until a later gesture;
        // capture / graph setup can still proceed.
    }
}

async function ensureMixer() {
    if (audioCtx && mixDest && micGain && playlistGain && masterGain && cueDest) {
        await resumeAudioContext(audioCtx);
        if (!meterRaf) startMeters();
        return;
    }

    audioCtx = createSilentAudioContext({ sampleRate: 48000, latencyHint: 'playback' });
    await resumeAudioContext(audioCtx);

    mixDest = audioCtx.createMediaStreamDestination();
    cueDest = audioCtx.createMediaStreamDestination();
    applyReceivedMixLayout();
    masterGain = audioCtx.createGain();
    cueMasterGain = audioCtx.createGain();
    micGain = audioCtx.createGain();
    playlistGain = audioCtx.createGain();
    micCueGain = audioCtx.createGain();
    playlistCueGain = audioCtx.createGain();
    micCueGain.gain.value = 0;
    playlistCueGain.gain.value = 0;

    masterAnalyser = audioCtx.createAnalyser();
    masterAnalyser.fftSize = 256;
    micAnalyser = audioCtx.createAnalyser();
    micAnalyser.fftSize = 256;
    playlistAnalyser = audioCtx.createAnalyser();
    playlistAnalyser.fftSize = 256;

    micGain.connect(masterGain);
    playlistGain.connect(masterGain);
    masterGain.connect(mixDest);
    masterGain.connect(masterAnalyser);

    micGain.connect(micCueGain);
    playlistGain.connect(playlistCueGain);
    micCueGain.connect(cueMasterGain);
    playlistCueGain.connect(cueMasterGain);
    cueMasterGain.connect(cueDest);

    micGain.connect(micAnalyser);
    playlistGain.connect(playlistAnalyser);

    applyGains();
    startMeters();
}

function wireTrack(track) {
    if (!audioCtx || !playlistGain || track.source) return;
    track.audio.muted = false;
    track.audio.volume = 1;
    track.source = audioCtx.createMediaElementSource(track.audio);
    track.gain = audioCtx.createGain();
    track.gain.gain.value = 1;
    track.source.connect(track.gain);
    track.gain.connect(playlistGain);
}

function trackSnapshot(track) {
    return {
        id: track.id,
        title: track.title,
        assetId: track.assetId,
        ready: track.ready,
        playing: !track.audio.paused && !track.audio.ended,
        currentTime: track.audio.currentTime || 0,
        duration: Number.isFinite(track.audio.duration) ? track.audio.duration : 0,
    };
}

function emitTracks() {
    post('tracks', { tracks: [...tracks.values()].map(trackSnapshot) });
}

function forceOpusCodec(transceiver) {
    const caps = RTCRtpSender.getCapabilities?.('audio');
    if (!caps?.codecs?.length || !transceiver?.setCodecPreferences) return;
    const opus = caps.codecs.filter((c) => c.mimeType.toLowerCase() === 'audio/opus');
    if (!opus.length) return;
    try {
        transceiver.setCodecPreferences(opus);
    } catch {
        /* optional */
    }
}

function stripNonOpusAudioCodecs(sdp) {
    const opusPts = new Set();
    for (const match of sdp.matchAll(/^a=rtpmap:(\d+) opus\/48000(?:\/\d+)?/gim)) {
        opusPts.add(match[1]);
    }
    if (opusPts.size === 0) return sdp;
    const lines = sdp.split(/\r?\n/);
    const out = [];
    let inAudio = false;
    for (const line of lines) {
        if (line.startsWith('m=audio ')) {
            inAudio = true;
            const parts = line.split(' ');
            out.push(parts.slice(0, 3).concat(parts.slice(3).filter((pt) => opusPts.has(pt))).join(' '));
            continue;
        }
        if (line.startsWith('m=')) inAudio = false;
        if (inAudio) {
            const ptMatch = line.match(/^a=(?:rtpmap|fmtp|rtcp-fb):(\d+)\b/);
            if (ptMatch && !opusPts.has(ptMatch[1])) continue;
        }
        out.push(line);
    }
    return out.join('\r\n');
}

function preferHighQualityOpus(sdp) {
    let out = stripNonOpusAudioCodecs(sdp);
    const opusPts = new Set();
    for (const match of out.matchAll(/^a=rtpmap:(\d+) opus\/48000(?:\/\d+)?/gim)) {
        opusPts.add(match[1]);
    }
    if (opusPts.size === 0) return out;
    const stereo = receivedChannels >= 2 ? 1 : 0;
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

function whipAudioSdp(sdp) {
    const out = preferHighQualityOpus(sdp || '');
    if (!/opus\/48000/i.test(out)) {
        throw new Error('Browser did not offer Opus.');
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

async function applyMaxAudioBitrate(peer) {
    for (const sender of peer.getSenders()) {
        if (sender.track?.kind !== 'audio') continue;
        try {
            const params = sender.getParameters();
            if (!params.encodings || params.encodings.length === 0) params.encodings = [{}];
            for (const encoding of params.encodings) {
                encoding.maxBitrate = opusSendBitrate;
                encoding.priority = 'high';
                encoding.networkPriority = 'high';
            }
            await sender.setParameters(params);
        } catch {
            /* optional */
        }
    }
}

function silenceRemoteAudio(peer) {
    peer.ontrack = (ev) => {
        for (const t of ev.streams?.[0]?.getTracks?.() ?? []) t.stop();
    };
}

async function publishWhip(whipUrl, stream) {
    const track = stream.getAudioTracks()[0];
    if (!track) throw new Error('No audio track. Check the microphone.');
    if ('contentHint' in track) track.contentHint = 'music';

    pc = new RTCPeerConnection({
        iceServers: [{ urls: 'stun:stun.l.google.com:19302' }],
    });
    silenceRemoteAudio(pc);
    pc.oniceconnectionstatechange = () => {
        post('ice', { state: pc?.iceConnectionState || 'closed' });
    };

    const transceiver = pc.addTransceiver(track, {
        direction: 'sendonly',
        streams: [stream],
        sendEncodings: [{ maxBitrate: opusSendBitrate, priority: 'high', networkPriority: 'high' }],
    });
    if (!transceiver.sender) pc.addTrack(track, stream);
    forceOpusCodec(transceiver);
    await applyMaxAudioBitrate(pc);

    const offer = await pc.createOffer();
    const sdp = whipAudioSdp(offer.sdp || '');
    await pc.setLocalDescription({ type: 'offer', sdp });
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
        body: whipAudioSdp(pc.localDescription?.sdp || sdp),
    });
    if (!res.ok) {
        const text = await res.text();
        throw new Error(text || `WHIP failed (${res.status})`);
    }
    const loc = res.headers.get('Location');
    whipResourceUrl = resolveMediaProxyUrl(loc, whipUrl);
    await pc.setRemoteDescription({
        type: 'answer',
        sdp: sanitizeRemoteIceSdp(stripNonOpusAudioCodecs(await res.text())),
    });
    await applyMaxAudioBitrate(pc);
}

async function stopPublish() {
    if (whipResourceUrl) {
        try {
            await fetch(whipResourceUrl, { method: 'DELETE' });
        } catch {
            /* ignore */
        }
        whipResourceUrl = null;
    }
    try {
        pc?.close();
    } catch {
        /* ignore */
    }
    pc = null;
    post('ice', { state: 'closed' });
    post('publish', { state: 'idle' });
}

function withTimeout(promise, ms, label) {
    return Promise.race([
        promise,
        new Promise((_, reject) => {
            setTimeout(() => reject(new Error(`${label} timed out after ${ms}ms`)), ms);
        }),
    ]);
}

async function openMicStream(deviceId = '') {
    if (!navigator.mediaDevices?.getUserMedia) {
        throw new Error('Microphone capture is not available in this engine.');
    }
    const base = { ...CLEAN_AUDIO };
    const tryGet = (audio) =>
        withTimeout(
            navigator.mediaDevices.getUserMedia({ audio, video: false }),
            8000,
            'getUserMedia',
        );

    // Prefer loose constraints first — WKWebView often hangs on strict Scarlett constraints.
    const attempts = [];
    if (deviceId) {
        attempts.push({ ...base, deviceId: { ideal: deviceId } });
        attempts.push({ deviceId: { ideal: deviceId } });
    }
    attempts.push(true);
    attempts.push(base);
    attempts.push({ echoCancellation: false, noiseSuppression: false, autoGainControl: false });

    let lastError = null;
    for (const audio of attempts) {
        try {
            const stream = await tryGet(audio);
            for (const track of stream.getAudioTracks()) {
                if ('contentHint' in track) {
                    track.contentHint = 'music';
                }
            }
            return stream;
        } catch (e) {
            lastError = e;
        }
    }
    throw lastError instanceof Error
        ? lastError
        : new Error('Microphone permission or device access failed.');
}

function attachMicStream(stream) {
    if (!audioCtx || !micGain) return;
    stopLayoutProbe();
    micStream = stream;
    micSource = audioCtx.createMediaStreamSource(micStream);
    noteReceivedChannels(stream, micSource);
    micSource.connect(micGain);
    applyGains();
}

async function releaseMic() {
    stopLayoutProbe();
    try {
        micSource?.disconnect();
    } catch {
        /* ignore */
    }
    micSource = null;
    for (const track of micStream?.getTracks?.() ?? []) {
        track.stop();
    }
    micStream = null;
}

async function listInputDevices() {
    const devices = await navigator.mediaDevices.enumerateDevices();
    return devices
        .filter((d) => d.kind === 'audioinput' && d.deviceId)
        .map((d, i) => ({
            deviceId: d.deviceId,
            label: d.label || `Microphone ${i + 1}`,
        }));
}

const api = {
    async armMic(opts = {}) {
        post('status', { message: 'Requesting microphone…' });
        return withTimeout(
            (async () => {
                await ensureMixer();
                if (typeof opts.deviceId === 'string') {
                    selectedDeviceId = opts.deviceId;
                }
                if (micStream) {
                    const inputs = await listInputDevices();
                    post('devices', { inputs, selected: selectedDeviceId });
                    post('status', { message: 'Mic armed' });
                    return { ok: true, selected: selectedDeviceId, inputs };
                }
                try {
                    attachMicStream(await openMicStream(selectedDeviceId));
                } catch (e) {
                    const message = e instanceof Error ? e.message : String(e);
                    post('status', { message: `Mic failed: ${message}` });
                    throw new Error(message);
                }
                const inputs = await listInputDevices();
                if (!selectedDeviceId && inputs[0]?.deviceId) {
                    selectedDeviceId = inputs[0].deviceId;
                }
                post('devices', { inputs, selected: selectedDeviceId });
                post('status', { message: 'Mic armed — mixer ready' });
                return { ok: true, selected: selectedDeviceId, inputs };
            })(),
            18000,
            'armMic',
        );
    },

    async listDevices() {
        await ensureMixer();
        if (!micStream) {
            // Permission unlocks device labels.
            attachMicStream(await openMicStream(selectedDeviceId));
        }
        const inputs = await listInputDevices();
        post('devices', { inputs, selected: selectedDeviceId });
        return { inputs, selected: selectedDeviceId };
    },

    async setInputDevice(deviceId) {
        selectedDeviceId = typeof deviceId === 'string' ? deviceId : '';
        await ensureMixer();
        await releaseMic();
        attachMicStream(await openMicStream(selectedDeviceId));
        const inputs = await listInputDevices();
        const label = inputs.find((d) => d.deviceId === selectedDeviceId)?.label || 'Default input';
        post('devices', { inputs, selected: selectedDeviceId });
        post('status', { message: `Input: ${label}` });
        return { ok: true, selected: selectedDeviceId, inputs };
    },

    setGains({ mic, playlist, master } = {}) {
        if (typeof mic === 'number') micFader = Math.max(0, Math.min(1.5, mic));
        if (typeof playlist === 'number') playlistFader = Math.max(0, Math.min(1.5, playlist));
        if (typeof master === 'number') masterFader = Math.max(0, Math.min(1.5, master));
        applyGains();
        return { ok: true };
    },

    setMutes({ mic, playlist } = {}) {
        if (typeof mic === 'boolean') micMuted = mic;
        if (typeof playlist === 'boolean') playlistMuted = playlist;
        applyGains();
        return { ok: true };
    },

    async setCues({ mic, playlist } = {}) {
        if (typeof mic === 'boolean') micCueOn = mic;
        if (typeof playlist === 'boolean') playlistCueOn = playlist;
        applyGains();
        await syncCuePlayback();
        return { ok: true, micCueOn, playlistCueOn };
    },

    async queueTrack({ id, title, url, assetId } = {}) {
        if (!url) throw new Error('Missing track url');
        await ensureMixer();
        const trackId = id || `file-${++trackSeq}`;
        if (tracks.has(trackId)) {
            return trackSnapshot(tracks.get(trackId));
        }
        const audio = new Audio();
        audio.crossOrigin = 'anonymous';
        audio.preload = 'auto';
        audio.loop = false;
        audio.src = url;
        const track = {
            id: trackId,
            title: title || 'Audio',
            url,
            assetId: assetId ?? null,
            audio,
            source: null,
            gain: null,
            ready: false,
            playing: false,
        };
        tracks.set(trackId, track);
        audio.addEventListener('canplay', () => {
            track.ready = true;
            emitTracks();
        });
        audio.addEventListener('ended', () => {
            track.playing = false;
            emitTracks();
        });
        audio.addEventListener('error', () => {
            post('status', { message: `Failed to load “${track.title}”` });
            emitTracks();
        });
        void audio.load();
        wireTrack(track);
        emitTracks();
        return trackSnapshot(track);
    },

    async play(id) {
        const track = tracks.get(id);
        if (!track) throw new Error('Track not found');
        await ensureMixer();
        wireTrack(track);
        await track.audio.play();
        track.playing = true;
        emitTracks();
        post('status', { message: `Playing “${track.title}”` });
        return trackSnapshot(track);
    },

    pause(id) {
        const track = tracks.get(id);
        if (!track) throw new Error('Track not found');
        track.audio.pause();
        track.playing = false;
        emitTracks();
        return trackSnapshot(track);
    },

    async restart(id) {
        const track = tracks.get(id);
        if (!track) throw new Error('Track not found');
        track.audio.currentTime = 0;
        return api.play(id);
    },

    remove(id) {
        const track = tracks.get(id);
        if (!track) return { ok: true };
        track.audio.pause();
        try {
            track.source?.disconnect();
            track.gain?.disconnect();
        } catch {
            /* ignore */
        }
        tracks.delete(id);
        emitTracks();
        return { ok: true };
    },

    async goLive(whipUrl) {
        if (!whipUrl) throw new Error('Missing WHIP URL');
        await api.armMic();
        await ensureMixer();
        post('publish', { state: 'connecting' });
        await publishWhip(whipUrl, mixDest.stream);
        post('publish', { state: 'connected' });
        post('status', { message: 'On air — mixer publishing' });
        return { ok: true };
    },

    async stopPublish() {
        await stopPublish();
        post('status', { message: 'Publish stopped' });
        return { ok: true };
    },

    listTracks() {
        return [...tracks.values()].map(trackSnapshot);
    },
};

window.SoundMixDesktop = api;
post('ready', { ok: true });
