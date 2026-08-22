import './bootstrap';
import { bindStagePlayer } from './player';
import { bindInitialGalleryFromDom, refreshGalleryFromUrl } from './gallery-ui';
import { bindScriptureListen } from './scripture-listen';

const root = document.getElementById('listen-root');
const audio = document.getElementById('stream-audio');
const statusEl = document.getElementById('stream-status');

const STATUS_POLL_MS = 5000;
/** Hold through brief ICE/NAT blips before any recovery work. */
const DISCONNECT_GRACE_MS = 20_000;
/** Extra hold after a soft ICE restart before tearing down. */
const ICE_RESTART_GRACE_MS = 12_000;
const RETRY_BASE_MS = 2000;
const RETRY_MAX_MS = 20_000;
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
let retryAttempt = 0;
/** Once WHEP works, keep using it for this page session when not preferring HLS. */
let whepSucceeded = false;
/** Once HLS works (CDN / AAC), keep preferring it for this page session. */
let hlsSucceeded = false;
/** @type {ReturnType<typeof bindStagePlayer> | null} */
let stagePlayer = null;

function preferHls() {
    return root?.dataset.preferHls === '1' || root?.dataset.preferHls === 'true';
}

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
    if (root?.dataset.streamStatus === 'paused') {
        return 'Broadcast paused — the host may resume this same event shortly.';
    }
    return isMarkedLive()
        ? 'Stream interrupted — reconnecting…'
        : 'Waiting for the broadcast to start. This page will keep trying.';
}

function applyPlaybackUrls(data) {
    if (!root || !data || typeof data !== 'object') {
        return;
    }
    if (typeof data.hls_url === 'string' && data.hls_url) {
        root.dataset.hlsUrl = data.hls_url;
    }
    if (typeof data.whep_url === 'string' && data.whep_url) {
        root.dataset.whepUrl = data.whep_url;
    }
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

function peerIsTerminal() {
    return Boolean(pc && (pc.connectionState === 'failed' || pc.connectionState === 'closed'));
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
 * Whether we should skip starting a new session.
 * Never trust a stale MediaStream after the peer has failed/closed — that left
 * listeners permanently silent after publisher network gaps.
 */
function playbackLooksHealthy() {
    if (peerIsTerminal()) {
        return false;
    }
    if (isPeerHealthy()) {
        return true;
    }
    // HLS (no peer): element still advancing.
    if (!pc && (hls || (audio?.src && !audio.srcObject))) {
        return audioStillPlaying();
    }
    // WHEP blip (disconnected): only hold while frames are still arriving.
    if (pc && pc.connectionState === 'disconnected') {
        return audioStillPlaying();
    }
    return false;
}

function nextRetryDelayMs() {
    const delay = Math.min(RETRY_MAX_MS, RETRY_BASE_MS * 2 ** retryAttempt);
    retryAttempt = Math.min(retryAttempt + 1, 6);
    return delay;
}

function resetRetryBackoff() {
    retryAttempt = 0;
}

/** Bust CDN/proxy playlist cache after a publisher gap. */
function hlsUrlWithCacheBust(hlsUrl) {
    try {
        const url = new URL(hlsUrl, window.location.href);
        url.searchParams.set('_sm', String(Date.now()));
        return url.toString();
    } catch {
        const join = hlsUrl.includes('?') ? '&' : '?';
        return `${hlsUrl}${join}_sm=${Date.now()}`;
    }
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
    if (playbackLooksHealthy()) {
        return;
    }
    setStatus(reason);
    const delay = nextRetryDelayMs();
    retryTimer = window.setTimeout(() => {
        retryTimer = null;
        if (playbackLooksHealthy()) {
            resetRetryBackoff();
            setStatus(isMarkedLive() ? 'On air' : 'Playing');
            return;
        }
        void startPlayback({ force: true });
    }, delay);
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
        if (audioStillPlaying() && !peerIsTerminal()) {
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
                if (playbackLooksHealthy()) {
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
        resetRetryBackoff();
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
                if (playbackLooksHealthy()) {
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
    resetRetryBackoff();
    whepSucceeded = false;
    hlsSucceeded = false;
    if (root) {
        root.dataset.streamStatus = 'offline';
    }
    updateBroadcastBadge(false);
    await teardown();
    stagePlayer?.disable();
    stagePlayer?.setPlayingVisual(false);
    setStatus('Offline');
}

async function applyLive({ force = false } = {}) {
    offlinePollStreak = 0;
    if (root) {
        root.dataset.streamStatus = 'live';
    }
    updateBroadcastBadge(true);
    if (!force && playbackLooksHealthy()) {
        return;
    }
    resetRetryBackoff();
    void startPlayback({ force: true });
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
        applyPlaybackUrls(data);
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
            // Offline → live: always open a fresh WHEP/HLS session.
            await applyLive({ force: true });
        } else if (live) {
            offlinePollStreak = 0;
            if (root) {
                root.dataset.streamStatus = 'live';
            }
            // Stayed live but listener session died (publisher path replace /
            // MediaMTX destroy) — resubscribe without waiting for offline→live.
            if (!playbackLooksHealthy() && !startingPlayback && !retryTimer) {
                scheduleRetry(waitingMessage());
            }
        } else if (root) {
            root.dataset.streamStatus = data.status === 'paused' ? 'paused' : 'offline';
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

/**
 * Start HLS and resolve once audio is playing. Rejects on timeout / fatal error
 * so callers can fall back to WHEP.
 * @param {string} hlsUrl
 * @param {{ allowFallback?: boolean }} [opts]
 */
async function startHls(hlsUrl, opts = {}) {
    const allowFallback = Boolean(opts.allowFallback);
    if (!audio) {
        throw new Error('Missing audio element');
    }

    const CONNECT_MS = allowFallback ? 5000 : 15000;

    if (audio.canPlayType('application/vnd.apple.mpegurl')) {
        await new Promise((resolve, reject) => {
            let settled = false;
            const timer = window.setTimeout(() => {
                if (!settled) {
                    settled = true;
                    reject(new Error('HLS connect timeout'));
                }
            }, CONNECT_MS);
            const onPlaying = () => {
                if (settled) {
                    return;
                }
                settled = true;
                window.clearTimeout(timer);
                setStatus(isMarkedLive() ? 'On air' : 'Playing');
                hlsSucceeded = true;
                resolve();
            };
            const onError = () => {
                if (settled) {
                    return;
                }
                settled = true;
                window.clearTimeout(timer);
                reject(new Error('Native HLS error'));
            };
            audio.addEventListener('playing', onPlaying, { once: true });
            audio.addEventListener('error', onError, { once: true });
            audio.src = hlsUrlWithCacheBust(hlsUrl);
            audio.play().catch(() => {
                // Autoplay blocked — still treat as connected if metadata arrives.
                if (!settled) {
                    settled = true;
                    window.clearTimeout(timer);
                    setStatus('Press play when you are ready.');
                    hlsSucceeded = true;
                    resolve();
                }
            });
        });
        return;
    }

    const { default: Hls } = await import('hls.js');

    if (!Hls.isSupported()) {
        throw new Error('HLS.js unsupported');
    }

    await new Promise((resolve, reject) => {
        let settled = false;
        const finish = (err) => {
            if (settled) {
                return;
            }
            settled = true;
            window.clearTimeout(timer);
            if (err) {
                reject(err);
            } else {
                hlsSucceeded = true;
                resolve();
            }
        };
        const timer = window.setTimeout(() => finish(new Error('HLS connect timeout')), CONNECT_MS);

        hls = new Hls({
            lowLatencyMode: false,
            backBufferLength: 60,
            maxBufferLength: 60,
            maxMaxBufferLength: 90,
            liveSyncDurationCount: 4,
            liveMaxLatencyDurationCount: 12,
            liveDurationInfinity: true,
            maxLiveSyncPlaybackRate: 1.05,
            // After publisher gaps, refuse stale CDN playlists.
            manifestLoadingMaxRetry: 6,
            levelLoadingMaxRetry: 6,
            fragLoadingMaxRetry: 6,
        });
        hls.loadSource(hlsUrlWithCacheBust(hlsUrl));
        hls.attachMedia(audio);
        hls.on(Hls.Events.MANIFEST_PARSED, () => {
            audio.play().catch(() => {
                setStatus('Press play when you are ready.');
                finish(null);
            });
        });
        hls.on(Hls.Events.ERROR, (_, data) => {
            if (!data.fatal) {
                return;
            }
            if (!settled && allowFallback) {
                finish(new Error(`HLS fatal: ${data.type}`));
                return;
            }
            if (data.type === Hls.ErrorTypes.NETWORK_ERROR) {
                // After publisher gaps, startLoad alone can loop on a stale CDN playlist.
                // Prefer a full session rebuild with cache-bust.
                setStatus('Rebuffering…');
                if (!settled) {
                    finish(new Error('HLS network error'));
                    return;
                }
                void teardown().then(() => scheduleRetry('Trying again…'));
                return;
            }
            if (data.type === Hls.ErrorTypes.MEDIA_ERROR) {
                try {
                    hls?.recoverMediaError();
                    setStatus('Recovering playback…');
                } catch {
                    finish(new Error('HLS media recovery failed'));
                    void teardown().then(() => scheduleRetry('Trying again…'));
                }
                return;
            }
            setStatus('Playback error.');
            finish(new Error('HLS playback error'));
            void teardown().then(() => scheduleRetry('Trying again…'));
        });
        audio.addEventListener(
            'playing',
            () => {
                setStatus('On air');
                finish(null);
            },
            { once: true },
        );
    });
}

async function startPlayback({ force = false } = {}) {
    if (!root || !audio || startingPlayback) {
        return;
    }

    // Already listening — do not tear down a healthy session.
    if (!force && playbackLooksHealthy()) {
        resetRetryBackoff();
        setStatus(isMarkedLive() ? 'On air' : 'Playing');
        return;
    }

    if (!stagePlayer) {
        stagePlayer = bindStagePlayer(audio);
    }

    if (hasStatusUrl() && !isMarkedLive()) {
        stagePlayer.disable();
        setStatus(waitingMessage());
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

        const tryHlsFirst = preferHls() || hlsSucceeded;
        // After a successful WHEP session, stay on WHEP unless HLS is preferred (CDN/AAC).
        const stickToWhep = whepSucceeded && !tryHlsFirst;

        if (tryHlsFirst && hlsUrl && !stickToWhep) {
            try {
                await startHls(hlsUrl, { allowFallback: Boolean(whepUrl) });
                resetRetryBackoff();
                return;
            } catch {
                // Fall through to WHEP (Studio Opus / sidecar not ready yet).
            }
        }

        if (whepUrl) {
            try {
                await startWhep(whepUrl);
                resetRetryBackoff();
                return;
            } catch {
                if (whepSucceeded && !preferHls()) {
                    scheduleRetry(waitingMessage());
                    return;
                }
                // First WHEP failure — try HLS for AAC/RTMP when not already tried.
            }
        }

        if (hlsUrl && !tryHlsFirst) {
            try {
                await startHls(hlsUrl, { allowFallback: false });
                resetRetryBackoff();
                return;
            } catch {
                /* retry below */
            }
        }

        scheduleRetry(waitingMessage());
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
