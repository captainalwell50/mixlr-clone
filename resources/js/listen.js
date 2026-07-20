import './bootstrap';
import { bindStagePlayer } from './player';
import { bindInitialGalleryFromDom, refreshGalleryFromUrl } from './gallery-ui';

const root = document.getElementById('listen-root');
const audio = document.getElementById('stream-audio');
const statusEl = document.getElementById('stream-status');

const STATUS_POLL_MS = 5000;
const DISCONNECT_GRACE_MS = 8000;
const OFFLINE_CONFIRM_POLLS = 2;

/** @type {import('hls.js').default | null} */
let hls = null;
/** @type {RTCPeerConnection|null} */
let pc = null;
/** @type {string|null} */
let whepResourceUrl = null;
let retryTimer = null;
let disconnectTimer = null;
let statusPollTimer = null;
let startingPlayback = false;
let offlinePollStreak = 0;
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

function clearDisconnectGrace() {
    if (disconnectTimer) {
        window.clearTimeout(disconnectTimer);
        disconnectTimer = null;
    }
}

function isPeerHealthy() {
    return Boolean(
        pc &&
        (pc.connectionState === 'connected' || pc.connectionState === 'connecting') &&
        audio?.srcObject,
    );
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
    // Don't tear down a connection that already recovered.
    if (isPeerHealthy() || (audio && !audio.paused && !audio.ended && (audio.srcObject || audio.src))) {
        return;
    }
    setStatus(reason);
    retryTimer = window.setTimeout(() => {
        retryTimer = null;
        if (isPeerHealthy()) {
            setStatus(isMarkedLive() ? 'On air' : 'Playing');
            return;
        }
        void startPlayback();
    }, 4000);
}

function handlePeerConnectionChange() {
    if (!pc) {
        return;
    }

    const state = pc.connectionState;
    if (state === 'connected') {
        clearDisconnectGrace();
        clearRetry();
        setStatus(isMarkedLive() ? 'On air' : 'Playing');
        return;
    }

    if (state === 'connecting' || state === 'new') {
        return;
    }

    // Brief ICE blips often report "disconnected" then recover — wait before reconnecting.
    if (state === 'disconnected') {
        if (disconnectTimer) {
            return;
        }
        setStatus('Connection unstable — holding…');
        disconnectTimer = window.setTimeout(() => {
            disconnectTimer = null;
            if (!pc || pc.connectionState === 'connected' || pc.connectionState === 'connecting') {
                return;
            }
            scheduleRetry(waitingMessage());
        }, DISCONNECT_GRACE_MS);
        return;
    }

    if (state === 'failed' || state === 'closed') {
        clearDisconnectGrace();
        scheduleRetry(waitingMessage());
    }
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
            '<span class="live-dot" aria-hidden="true"></span> Live';
    } else {
        badge.textContent = badge.classList.contains('portal-badge') ? 'Offline' : 'Offline';
    }
}

async function applyOffline() {
    clearRetry();
    clearDisconnectGrace();
    offlinePollStreak = 0;
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
    offlinePollStreak = 0;
    if (root) {
        root.dataset.streamStatus = 'live';
    }
    updateBroadcastBadge(true);
    if (isPeerHealthy()) {
        return;
    }
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
            // Require consecutive offline polls so a blip does not kill audio.
            offlinePollStreak += 1;
            if (offlinePollStreak < OFFLINE_CONFIRM_POLLS) {
                return true;
            }
            await applyOffline();
        } else if (!wasLive && live) {
            offlinePollStreak = 0;
            await applyLive();
        } else if (root) {
            offlinePollStreak = live ? 0 : offlinePollStreak;
            root.dataset.streamStatus = live ? 'live' : 'offline';
        }

        return live || (wasLive && offlinePollStreak > 0 && offlinePollStreak < OFFLINE_CONFIRM_POLLS);
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

    pc.onconnectionstatechange = () => handlePeerConnectionChange();

    const offer = await pc.createOffer();
    // Prefer stereo Opus on the receive side when the browser offers it.
    let sdp = offer.sdp || '';
    sdp = sdp.replace(
        /^a=fmtp:(\d+) (.*)$/gim,
        (line, pt, params) => {
            if (!new RegExp(`a=rtpmap:${pt} opus/48000`, 'i').test(sdp)) {
                return line;
            }
            if (/stereo=1/i.test(params) && /maxaveragebitrate=/i.test(params)) {
                return line;
            }
            return `a=fmtp:${pt} minptime=10;useinbandfec=1;stereo=1;sprop-stereo=1;maxaveragebitrate=510000;maxplaybackrate=48000`;
        },
    );
    await pc.setLocalDescription({ type: 'offer', sdp });
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
        lowLatencyMode: false,
        backBufferLength: 30,
        maxBufferLength: 30,
        liveSyncDurationCount: 3,
        liveMaxLatencyDurationCount: 6,
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

    // Already listening — do not tear down a healthy session.
    if (isPeerHealthy()) {
        setStatus(isMarkedLive() ? 'On air' : 'Playing');
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
    clearDisconnectGrace();
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
    clearDisconnectGrace();
    if (statusPollTimer) {
        window.clearTimeout(statusPollTimer);
        statusPollTimer = null;
    }
    void teardown();
});

startStatusPolling();
void startPlayback();
bindInitialGalleryFromDom();
const galleryUrl = root?.dataset.galleryUrl;
if (galleryUrl) {
    void refreshGalleryFromUrl(galleryUrl);
    window.setInterval(() => void refreshGalleryFromUrl(galleryUrl), 20000);
}
