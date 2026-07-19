import './bootstrap';
import { bindStagePlayer } from './player';

const root = document.getElementById('listen-root');
const audio = document.getElementById('stream-audio');
const statusEl = document.getElementById('stream-status');

const STATUS_POLL_MS = 5000;

/** @type {import('hls.js').default | null} */
let hls = null;
/** @type {RTCPeerConnection|null} */
let pc = null;
/** @type {string|null} */
let whepResourceUrl = null;
let retryTimer = null;
let statusPollTimer = null;
let startingPlayback = false;
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

function hasStatusUrl() {
    return Boolean(root?.dataset.statusUrl);
}

function waitingMessage() {
    return isMarkedLive()
        ? 'Stream interrupted — reconnecting…'
        : 'Waiting for the broadcast to start. This page will keep trying.';
}

function clearRetry() {
    if (retryTimer) {
        window.clearTimeout(retryTimer);
        retryTimer = null;
    }
}

function scheduleRetry(reason) {
    if (retryTimer) {
        return;
    }
    // When we can poll server status, stay idle while offline — poll resumes playback.
    if (hasStatusUrl() && !isMarkedLive()) {
        setStatus(reason);
        return;
    }
    setStatus(reason);
    retryTimer = window.setTimeout(() => {
        retryTimer = null;
        void startPlayback();
    }, 4000);
}

function updateBroadcastBadge(live) {
    const badge =
        document.getElementById('broadcast-badge') ||
        document.querySelector('.embed-badge');
    if (!badge) {
        return;
    }

    badge.classList.toggle('is-idle', !live);
    badge.classList.toggle('is-live', live);

    if (live) {
        badge.innerHTML =
            '<span class="live-dot inline-block h-1.5 w-1.5 rounded-full bg-current"></span> Live';
    } else {
        badge.textContent = 'Offline';
    }
}

async function applyOffline() {
    clearRetry();
    if (root) {
        root.dataset.streamStatus = 'offline';
    }
    updateBroadcastBadge(false);
    await teardown();
    stagePlayer?.disable();
    stagePlayer?.setPlayingVisual(false);
    setStatus('Offline');
}

async function applyLive() {
    if (root) {
        root.dataset.streamStatus = 'live';
    }
    updateBroadcastBadge(true);
    void startPlayback();
}

async function refreshStreamStatus() {
    const url = root?.dataset.statusUrl;
    if (!url) {
        return isMarkedLive();
    }

    try {
        const res = await fetch(url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            cache: 'no-store',
        });
        if (!res.ok) {
            return isMarkedLive();
        }

        const data = await res.json();
        const live = data.status === 'live';
        const wasLive = isMarkedLive();

        if (wasLive && !live) {
            await applyOffline();
        } else if (!wasLive && live) {
            await applyLive();
        } else if (root) {
            root.dataset.streamStatus = live ? 'live' : 'offline';
        }

        return live;
    } catch {
        return isMarkedLive();
    }
}

function startStatusPolling() {
    if (!hasStatusUrl() || statusPollTimer) {
        return;
    }

    const tick = async () => {
        await refreshStreamStatus();
        statusPollTimer = window.setTimeout(tick, STATUS_POLL_MS);
    };

    statusPollTimer = window.setTimeout(tick, STATUS_POLL_MS);
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
    if (!root || !audio || startingPlayback) {
        return;
    }

    if (!stagePlayer) {
        stagePlayer = bindStagePlayer(audio);
    }

    if (hasStatusUrl() && !isMarkedLive()) {
        stagePlayer.disable();
        setStatus('Waiting for the broadcast to start. This page will keep trying.');
        return;
    }

    const whepUrl = root.dataset.whepUrl || '';
    const hlsUrl = root.dataset.hlsUrl || '';

    if (!whepUrl && !hlsUrl) {
        setStatus('Playback URL missing.');
        stagePlayer.disable();
        return;
    }

    startingPlayback = true;
    clearRetry();
    stagePlayer.enable();
    setStatus(isMarkedLive() ? 'Connecting…' : 'Waiting for the broadcast to start. This page will keep trying.');

    try {
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
    } finally {
        startingPlayback = false;
    }
}

window.addEventListener('beforeunload', () => {
    clearRetry();
    if (statusPollTimer) {
        window.clearTimeout(statusPollTimer);
        statusPollTimer = null;
    }
    void teardown();
});

startStatusPolling();
void startPlayback();
