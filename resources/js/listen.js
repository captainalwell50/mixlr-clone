import './bootstrap';
import { bindStagePlayer } from './player';
import { bindInitialGalleryFromDom, refreshGalleryFromUrl } from './gallery-ui';
import { bindScriptureListen } from './scripture-listen';

const root = document.getElementById('listen-root');
const audio = document.getElementById('stream-audio');
const statusEl = document.getElementById('stream-status');

const STATUS_POLL_MS = 8000;
/** Hold through brief ICE/NAT blips before any recovery work. */
const DISCONNECT_GRACE_MS = 20_000;
/** Extra hold after a soft ICE restart before tearing down. */
const ICE_RESTART_GRACE_MS = 12_000;
const RETRY_MS = 3500;
const OFFLINE_CONFIRM_POLLS = 2;
const MAX_SOFT_ICE_RESTARTS = 2;
/** Receive jitter buffer target (seconds / ms) — absorbs micro-gaps without cutting audio. */
const PLAYOUT_DELAY_HINT_S = 0.25;
const JITTER_BUFFER_TARGET_MS = 250;

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
let softIceRestarts = 0;
/** Once WHEP works, keep using it — Opus HLS in Chrome is unreliable. */
let whepSucceeded = false;
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
    }
    retryTimer = null;
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

/** True when the element is still producing audio — prefer holding over teardown. */
function audioStillPlaying() {
    return Boolean(
        audio &&
        !audio.paused &&
        !audio.ended &&
        (audio.srcObject || audio.src) &&
        audio.readyState >= HTMLMediaElement.HAVE_CURRENT_DATA,
    );
}

/**
 * Prefer a larger receive jitter buffer so brief packet gaps don't mute the speaker.
 * @param {RTCRtpReceiver | undefined} receiver
 * @param {MediaStreamTrack | undefined} track
 */
function tuneReceiveAudio(receiver, track) {
    try {
        if (track && 'contentHint' in track) {
            track.contentHint = 'music';
        }
    } catch {
        /* ignore */
    }
    if (!receiver) {
        return;
    }
    try {
        if ('playoutDelayHint' in receiver) {
            receiver.playoutDelayHint = PLAYOUT_DELAY_HINT_S;
        }
    } catch {
        /* ignore */
    }
    try {
        if ('jitterBufferTarget' in receiver) {
            receiver.jitterBufferTarget = JITTER_BUFFER_TARGET_MS;
        }
    } catch {
        /* ignore */
    }
}

function trySoftIceRestart() {
    if (!pc || typeof pc.restartIce !== 'function') {
        return false;
    }
    if (softIceRestarts >= MAX_SOFT_ICE_RESTARTS) {
        return false;
    }
    try {
        softIceRestarts += 1;
        pc.restartIce();
        setStatus('Smoothing connection…');
        return true;
    } catch {
        return false;
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
    // Don't tear down a connection that already recovered or is still audible.
    if (isPeerHealthy() || audioStillPlaying()) {
        return;
    }
    setStatus(reason);
    retryTimer = window.setTimeout(() => {
        retryTimer = null;
        if (isPeerHealthy() || audioStillPlaying()) {
            setStatus(isMarkedLive() ? 'On air' : 'Playing');
            return;
        }
        void startPlayback();
    }, RETRY_MS);
}

function armDisconnectRecovery() {
    if (disconnectTimer) {
        return;
    }
    setStatus('Connection unstable — holding…');
    disconnectTimer = window.setTimeout(() => {
        disconnectTimer = null;
        if (!pc) {
            return;
        }
        const state = pc.connectionState;
        if (state === 'connected' || state === 'connecting') {
            return;
        }
        // Still getting frames — hold quietly; soft-refresh ICE in the background.
        if (audioStillPlaying()) {
            trySoftIceRestart();
            armDisconnectRecovery();
            return;
        }
        if (trySoftIceRestart()) {
            disconnectTimer = window.setTimeout(() => {
                disconnectTimer = null;
                if (!pc || pc.connectionState === 'connected' || pc.connectionState === 'connecting') {
                    return;
                }
                if (audioStillPlaying()) {
                    return;
                }
                scheduleRetry(waitingMessage());
            }, ICE_RESTART_GRACE_MS);
            return;
        }
        scheduleRetry(waitingMessage());
    }, DISCONNECT_GRACE_MS);
}

function handlePeerConnectionChange() {
    if (!pc) {
        return;
    }

    const state = pc.connectionState;
    if (state === 'connected') {
        softIceRestarts = 0;
        clearDisconnectGrace();
        clearRetry();
        setStatus(isMarkedLive() ? 'On air' : 'Playing');
        return;
    }

    if (state === 'connecting' || state === 'new') {
        return;
    }

    // Brief ICE blips often report "disconnected" then recover — wait, then soft ICE.
    if (state === 'disconnected') {
        armDisconnectRecovery();
        return;
    }

    if (state === 'failed' || state === 'closed') {
        clearDisconnectGrace();
        if (state === 'failed' && trySoftIceRestart()) {
            disconnectTimer = window.setTimeout(() => {
                disconnectTimer = null;
                if (!pc || pc.connectionState === 'connected' || pc.connectionState === 'connecting') {
                    return;
                }
                if (audioStillPlaying()) {
                    return;
                }
                scheduleRetry(waitingMessage());
            }, ICE_RESTART_GRACE_MS);
            return;
        }
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
    softIceRestarts = 0;
    whepSucceeded = false;
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
    if (isPeerHealthy() || audioStillPlaying()) {
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
    softIceRestarts = 0;
    pc = new RTCPeerConnection({
        iceServers: [{ urls: 'stun:stun.l.google.com:19302' }],
    });

    pc.addTransceiver('audio', { direction: 'recvonly' });

    pc.ontrack = (event) => {
        if (!audio) {
            return;
        }
        tuneReceiveAudio(event.receiver, event.track);
        const stream = event.streams[0] ?? new MediaStream([event.track]);
        audio.srcObject = stream;
        audio.play().catch(() => {
            setStatus('Press play when you are ready.');
        });
    };

    pc.onconnectionstatechange = () => handlePeerConnectionChange();

    const offer = await pc.createOffer();
    // Prefer stereo Opus with FEC; disable DTX so quiet passages don't drop the stream.
    let sdp = offer.sdp || '';
    sdp = sdp.replace(
        /^a=fmtp:(\d+) (.*)$/gim,
        (line, pt, params) => {
            if (!new RegExp(`a=rtpmap:${pt} opus/48000`, 'i').test(sdp)) {
                return line;
            }
            if (/stereo=1/i.test(params) && /maxaveragebitrate=/i.test(params) && /usedtx=0/i.test(params)) {
                return line;
            }
            return `a=fmtp:${pt} minptime=20;useinbandfec=1;usedtx=0;stereo=1;sprop-stereo=1;maxaveragebitrate=510000;maxplaybackrate=48000`;
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
    whepSucceeded = true;
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
        backBufferLength: 60,
        maxBufferLength: 60,
        maxMaxBufferLength: 90,
        liveSyncDurationCount: 4,
        liveMaxLatencyDurationCount: 12,
        // Prefer smooth playback over aggressive catch-up jumps.
        liveDurationInfinity: true,
        maxLiveSyncPlaybackRate: 1.05,
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
            // Recover in-place — do not also schedule a full teardown retry.
            hls?.startLoad();
            setStatus('Rebuffering…');
            return;
        }
        if (data.type === Hls.ErrorTypes.MEDIA_ERROR) {
            try {
                hls?.recoverMediaError();
                setStatus('Recovering playback…');
            } catch {
                void teardown().then(() => scheduleRetry('Trying again…'));
            }
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
    if (isPeerHealthy() || audioStillPlaying()) {
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
                // Opus Studio publishes should stay on WHEP — HLS Opus in Chrome glitches.
                if (whepSucceeded) {
                    scheduleRetry(waitingMessage());
                    return;
                }
                // First connect failed — allow HLS for AAC/RTMP publishers.
            }
        }

        if (!hlsUrl || whepSucceeded) {
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
bindScriptureListen(root);
const galleryUrl = root?.dataset.galleryUrl;
if (galleryUrl) {
    void refreshGalleryFromUrl(galleryUrl);
    window.setInterval(() => void refreshGalleryFromUrl(galleryUrl), 20000);
}
