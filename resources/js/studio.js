import './bootstrap';

const root = document.getElementById('studio-root');
const whipUrl = root?.dataset.whipUrl;
const audioSelect = document.getElementById('audio-input');
const btnStart = document.getElementById('btn-start');
const btnStop = document.getElementById('btn-stop');
const statusEl = document.getElementById('studio-status');
const meterEl = document.getElementById('level-meter');
const meterLabel = document.getElementById('meter-label');

let pc = null;
let mediaStream = null;
/** @type {string|null} */
let whipResourceUrl = null;
/** @type {AudioContext|null} */
let audioCtx = null;
/** @type {AnalyserNode|null} */
let analyser = null;
let meterRaf = 0;

function setStatus(message) {
    if (statusEl) {
        statusEl.textContent = message;
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
        return 'Could not reach the media server (ICE/network). On Azure, confirm UDP 8189 and webrtcAdditionalHosts (public IP).';
    }
    if (/Failed to fetch|NetworkError|Load failed/i.test(msg)) {
        return 'Could not reach the WHIP endpoint. Check HTTPS, Caddy /rtc proxy, and MediaMTX.';
    }
    return msg || 'Could not go live.';
}

async function openMicrophone() {
    const preferredId = audioSelect?.value || '';
    if (preferredId) {
        try {
            return await navigator.mediaDevices.getUserMedia({
                audio: { deviceId: { ideal: preferredId } },
                video: false,
            });
        } catch {
            /* fall through to default input */
        }
    }
    return navigator.mediaDevices.getUserMedia({ audio: true, video: false });
}

function stopMeter() {
    if (meterRaf) {
        cancelAnimationFrame(meterRaf);
        meterRaf = 0;
    }
    if (audioCtx) {
        void audioCtx.close().catch(() => {});
        audioCtx = null;
    }
    analyser = null;
    if (meterEl) {
        meterEl.style.width = '0%';
    }
    if (meterLabel) {
        meterLabel.textContent = '—';
    }
}

function startMeter(stream) {
    stopMeter();
    try {
        audioCtx = new AudioContext();
        const source = audioCtx.createMediaStreamSource(stream);
        analyser = audioCtx.createAnalyser();
        analyser.fftSize = 256;
        source.connect(analyser);
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
        const priming = await navigator.mediaDevices.getUserMedia({ audio: true, video: false });
        for (const t of priming.getTracks()) {
            t.stop();
        }
        await loadDevices();
        setStatus('Microphone ready. Choose an input and press Go live.');
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
        mediaStream = await openMicrophone();
        startMeter(mediaStream);

        pc = new RTCPeerConnection({
            iceServers: [{ urls: 'stun:stun.l.google.com:19302' }],
        });

        for (const track of mediaStream.getTracks()) {
            pc.addTrack(track, mediaStream);
        }

        const offer = await pc.createOffer();
        await pc.setLocalDescription(offer);

        await new Promise((resolve) => {
            if (pc.iceGatheringState === 'complete') {
                resolve();
                return;
            }
            const done = () => {
                if (pc.iceGatheringState === 'complete') {
                    pc.removeEventListener('icegatheringstatechange', done);
                    resolve();
                }
            };
            pc.addEventListener('icegatheringstatechange', done);
            setTimeout(resolve, 2000);
        });

        const res = await fetch(whipUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/sdp',
            },
            body: pc.localDescription?.sdp ?? '',
        });

        if (!res.ok) {
            const text = await res.text();
            throw new Error(text || `WHIP failed (${res.status})`);
        }

        const loc = res.headers.get('Location');
        whipResourceUrl = loc ? new URL(loc, whipUrl).href : null;

        const answerSdp = await res.text();
        await pc.setRemoteDescription({
            type: 'answer',
            sdp: answerSdp,
        });

        setStatus('Live — audio is publishing. Keep this tab open.');
        btnStop.disabled = false;
    } catch (e) {
        console.error(e);
        setStatus(friendlyError(e));
        btnStart.disabled = false;
        await teardown();
    }
});

btnStop?.addEventListener('click', async () => {
    btnStop.disabled = true;
    setStatus('Stopping…');
    await teardown();
    setStatus('Stopped.');
    btnStart.disabled = false;
});

async function teardown() {
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
    if (mediaStream) {
        for (const t of mediaStream.getTracks()) {
            t.stop();
        }
        mediaStream = null;
    }
}
