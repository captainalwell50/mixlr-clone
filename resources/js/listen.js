import './bootstrap';
import { bindStagePlayer } from './player';

const root = document.getElementById('listen-root');
const audio = document.getElementById('stream-audio');
const statusEl = document.getElementById('stream-status');

/** @type {import('hls.js').default | null} */
let hls = null;
/** @type {RTCPeerConnection|null} */
let pc = null;
/** @type {string|null} */
let whepResourceUrl = null;
let retryTimer = null;
/** @type {ReturnType<typeof bindStagePlayer> | null} */
let stagePlayer = null;

function setStatus(message) {
    if (statusEl) {
        statusEl.textContent = message;
    }
}

function isMarkedLive() {
    return root?.dataset.streamStatus === 'live';
}

function waitingMessage() {
    return isMarkedLive()
        ? 'Stream interrupted — reconnecting…'
        : 'Waiting for the broadcast to start. This page will keep trying.';
}

function scheduleRetry(reason) {
    if (retryTimer) {
        return;
    }
    setStatus(reason);
    retryTimer = window.setTimeout(() => {
        retryTimer = null;
        void startPlayback();
    }, 4000);
}

async function waitForIce(peer) {
    if (peer.iceGatheringState === 'complete') {
        return;
    }
    await new Promise((resolve) => {
        const done = () => {
            if (peer.iceGatheringState === 'complete') {
                peer.removeEventListener('icegatheringstatechange', done);
                resolve();
            }
        };
        peer.addEventListener('icegatheringstatechange', done);
        window.setTimeout(resolve, 2000);
    });
}

async function teardown() {
    if (hls) {
        hls.destroy();
        hls = null;
    }
    if (whepResourceUrl) {
        try {
            await fetch(whepResourceUrl, { method: 'DELETE' });
        } catch {
            /* ignore */
        }
        whepResourceUrl = null;
    }
    if (pc) {
        pc.ontrack = null;
        pc.onconnectionstatechange = null;
        pc.close();
        pc = null;
    }
    if (audio) {
        audio.srcObject = null;
        audio.removeAttribute('src');
        try {
            audio.load();
        } catch {
            /* ignore */
        }
    }
}

/**
 * Opus from Studio WHIP is not reliably playable via HLS in Chrome — use WHEP.
 * @param {string} whepUrl
 */
async function startWhep(whepUrl) {
    pc = new RTCPeerConnection({
        iceServers: [{ urls: 'stun:stun.l.google.com:19302' }],
    });

    pc.addTransceiver('audio', { direction: 'recvonly' });

    pc.ontrack = (event) => {
        if (!audio) {
            return;
        }
        const stream = event.streams[0] ?? new MediaStream([event.track]);
        audio.srcObject = stream;
        audio.play().catch(() => {
            setStatus('Press play when you are ready.');
        });
    };

    pc.onconnectionstatechange = () => {
        if (!pc) {
            return;
        }
        if (pc.connectionState === 'connected') {
            setStatus(isMarkedLive() ? 'On air' : 'Playing');
            return;
        }
        if (pc.connectionState === 'failed' || pc.connectionState === 'disconnected') {
            scheduleRetry(waitingMessage());
        }
    };

    const offer = await pc.createOffer();
    await pc.setLocalDescription(offer);
    await waitForIce(pc);

    const res = await fetch(whepUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/sdp',
            Accept: 'application/sdp',
        },
        body: pc.localDescription?.sdp ?? '',
    });

    if (!res.ok) {
        const text = await res.text();
        throw new Error(text || `WHEP failed (${res.status})`);
    }

    const location = res.headers.get('Location');
    if (location) {
        whepResourceUrl = new URL(location, whepUrl).toString();
    }

    const answer = await res.text();
    await pc.setRemoteDescription({ type: 'answer', sdp: answer });
}

async function startHls(hlsUrl) {
    if (!audio) {
        return;
    }

    if (audio.canPlayType('application/vnd.apple.mpegurl')) {
        audio.src = hlsUrl;
        audio.addEventListener(
            'error',
            () => {
                scheduleRetry(waitingMessage());
            },
            { once: true },
        );
        audio.addEventListener(
            'playing',
            () => {
                setStatus(isMarkedLive() ? 'On air' : 'Playing');
            },
            { once: true },
        );
        try {
            await audio.play();
        } catch {
            setStatus('Press play when you are ready.');
        }
        return;
    }

    const { default: Hls } = await import('hls.js');

    if (!Hls.isSupported()) {
        setStatus('This browser cannot play the stream.');
        stagePlayer?.disable();
        return;
    }

    hls = new Hls({
        lowLatencyMode: true,
        backBufferLength: 30,
    });
    hls.loadSource(hlsUrl);
    hls.attachMedia(audio);
    hls.on(Hls.Events.MANIFEST_PARSED, () => {
        audio.play().catch(() => {
            setStatus('Press play when you are ready.');
        });
    });
    hls.on(Hls.Events.ERROR, (_, data) => {
        if (!data.fatal) {
            return;
        }
        if (data.type === Hls.ErrorTypes.NETWORK_ERROR) {
            hls?.startLoad();
            scheduleRetry(waitingMessage());
            return;
        }
        setStatus('Playback error.');
        void teardown().then(() => scheduleRetry('Trying again…'));
    });
    audio.addEventListener(
        'playing',
        () => {
            setStatus('On air');
        },
        { once: true },
    );
}

async function startPlayback() {
    if (!root || !audio) {
        return;
    }

    if (!stagePlayer) {
        stagePlayer = bindStagePlayer(audio);
    }

    const whepUrl = root.dataset.whepUrl || '';
    const hlsUrl = root.dataset.hlsUrl || '';

    if (!whepUrl && !hlsUrl) {
        setStatus('Playback URL missing.');
        stagePlayer.disable();
        return;
    }

    stagePlayer.enable();
    setStatus(isMarkedLive() ? 'Connecting…' : 'Waiting for the broadcast to start. This page will keep trying.');

    await teardown();

    if (whepUrl) {
        try {
            await startWhep(whepUrl);
            return;
        } catch {
            // Fall back to HLS (e.g. AAC from OBS/RTMP).
        }
    }

    if (!hlsUrl) {
        scheduleRetry(waitingMessage());
        return;
    }

    try {
        await startHls(hlsUrl);
    } catch {
        scheduleRetry(waitingMessage());
    }
}

window.addEventListener('beforeunload', () => {
    void teardown();
});

void startPlayback();
