import './bootstrap';
import { bindStagePlayer, listenMeterPeak } from './player';
import { bindInitialGalleryFromDom, refreshGalleryFromUrl } from './gallery-ui';
import { bindScriptureListen } from './scripture-listen';
import {
    ensureAudioSession,
    setupMediaSession,
    updateMediaSessionMetadata,
    syncMediaSessionPlaybackState,
    handleVisibilityChange,
    classifyAudioPause,
} from './media-session';

const root = document.getElementById('listen-root');
const audio = document.getElementById('stream-audio');
const statusEl = document.getElementById('stream-status');

const STATUS_POLL_MS = 5000;
/** Hold through brief ICE/NAT blips before any recovery work. */
const DISCONNECT_GRACE_MS = 20_000;
/** Extra hold after a failed recovery before tearing down. */
const ICE_RESTART_GRACE_MS = 12_000;
const RETRY_BASE_MS = 2000;
const RETRY_MAX_MS = 20_000;
const OFFLINE_CONFIRM_POLLS = 2;
const MAX_SOFT_ICE_RESTARTS = 2;
/** Receive jitter buffer — hide small gaps. Publisher bursts still need a healthy ingest. */
const PLAYOUT_DELAY_HINT_S = 0.45;
const JITTER_BUFFER_TARGET_MS = 450;

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
/** One-shot switch from AAC remux onto Opus WHEP after admin prefer_hls flips. */
let whepPreferUpgradeTried = false;
/** @type {ReturnType<typeof bindStagePlayer> | null} */
let stagePlayer = null;
/** Listener clicked Pause — do not auto-resume via poll/retry/WHEP. */
let userPaused = false;
/** Interrupted by external OS event (call, audio focus loss, etc.) vs intentional user pause. */
let interrupted = false;
/** Current broadcast metadata for MediaSession. */
let currentMetadata = {
    title: '',
    artist: '',
    artworkUrl: '',
};
/** True while we are tearing down media (ignore the resulting pause event). */
let tearingDown = false;

function preferHls() {
    if (typeof window !== 'undefined' && window.location) {
        const params = new URLSearchParams(window.location.search);
        if (params.get('transport') === 'hls' || params.get('player') === 'hls' || params.get('prefer_hls') === '1') {
            return true;
        }
        if (params.get('transport') === 'whep' || params.get('player') === 'whep' || params.get('prefer_hls') === '0') {
            return false;
        }
    }
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

function pausedStatusMessage() {
    return isMarkedLive() ? 'Paused — press play to resume.' : 'Paused';
}

function hasAttachedMedia() {
    return Boolean(audio && (audio.srcObject || audio.src));
}

function setUserPaused(paused) {
    userPaused = paused;
    if (paused) {
        interrupted = false;
        clearRetry();
        setStatus(pausedStatusMessage());
    }
    syncMediaSessionPlaybackState({
        isPlaying: audioStillPlaying(),
        isLive: isMarkedLive(),
        userPaused,
    });
}

function playAudioElement() {
    if (!audio || userPaused) {
        return Promise.resolve();
    }
    ensureAudioSession();
    updateMediaSessionMetadata(currentMetadata);
    if (audio && currentMetadata.title) {
        audio.title = currentMetadata.title;
    }
    return audio.play().then(() => {
        interrupted = false;
        updateMediaSessionMetadata(currentMetadata);
        syncMediaSessionPlaybackState({
            isPlaying: true,
            isLive: isMarkedLive(),
            userPaused: false,
        });
    }).catch(() => {
        if (!userPaused) {
            setStatus('Press play when you are ready.');
        }
    });
}

function applyPlaybackMetadata(data) {
    if (!root || !data || typeof data !== 'object') {
        return;
    }
    if (typeof data.hls_url === 'string' && data.hls_url) {
        root.dataset.hlsUrl = data.hls_url;
    }
    if (typeof data.whep_url === 'string' && data.whep_url) {
        root.dataset.whepUrl = data.whep_url;
    }
    if (typeof data.prefer_hls === 'boolean') {
        root.dataset.preferHls = data.prefer_hls ? '1' : '0';
    }

    let metaChanged = false;
    if (typeof data.title === 'string' && data.title && data.title !== currentMetadata.title) {
        currentMetadata.title = data.title;
        metaChanged = true;
        const portalTitle = document.querySelector('.portal-title') || document.querySelector('.embed-title');
        if (portalTitle) {
            portalTitle.textContent = data.title;
        }
    }
    if (typeof data.organization_name === 'string' && data.organization_name && data.organization_name !== currentMetadata.artist) {
        currentMetadata.artist = data.organization_name;
        metaChanged = true;
    }
    if (typeof data.artwork_url === 'string' && data.artwork_url !== currentMetadata.artworkUrl) {
        currentMetadata.artworkUrl = data.artwork_url;
        metaChanged = true;
    }
    if (metaChanged) {
        updateMediaSessionMetadata(currentMetadata);
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
    // Intentional pause is not a dead session — never reconnect over it.
    if (userPaused) {
        return true;
    }
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

/**
 * Copy a private-listen capability onto an HLS request.
 * Public playlist URLs have no `media` param, so this leaves them unchanged.
 * @param {string} requestUrl
 * @param {string} playlistUrl
 */
function urlWithListenCapability(requestUrl, playlistUrl) {
    try {
        const playlist = new URL(playlistUrl, window.location.href);
        const capability = playlist.searchParams.get('media');
        if (!capability) {
            return requestUrl;
        }
        const target = new URL(requestUrl, playlist);
        if (target.searchParams.get('media') === capability) {
            return requestUrl;
        }
        target.searchParams.set('media', capability);
        return target.toString();
    } catch {
        return requestUrl;
    }
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
    // WHEP has no trickle/ICE-restart signaling to MediaMTX. restartIce() here
    // renegotiates locally and often kills a pair that was still recovering.
    return false;
}

function scheduleRetry(reason) {
    if (userPaused) {
        setStatus(pausedStatusMessage());
        return;
    }
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
        if (userPaused) {
            setStatus(pausedStatusMessage());
            return;
        }
        if (playbackLooksHealthy()) {
            resetRetryBackoff();
            setStatus(isMarkedLive() ? 'On air' : 'Playing');
            return;
        }
        void startPlayback({ force: true });
    }, delay);
}

function armDisconnectRecovery() {
    if (userPaused || disconnectTimer) {
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
        if (userPaused) {
            setStatus(pausedStatusMessage());
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
                if (userPaused || playbackLooksHealthy()) {
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
        setStatus(userPaused ? pausedStatusMessage() : (isMarkedLive() ? 'On air' : 'Playing'));
        syncMediaSessionPlaybackState({
            isPlaying: audioStillPlaying(),
            isLive: isMarkedLive(),
            userPaused,
        });
        return;
    }

    if (state === 'connecting' || state === 'new') {
        return;
    }

    // Brief ICE blips often report "disconnected" then recover — wait, then soft ICE.
    if (state === 'disconnected') {
        if (userPaused) {
            return;
        }
        armDisconnectRecovery();
        return;
    }

    if (state === 'failed' || state === 'closed') {
        syncMediaSessionPlaybackState({
            isPlaying: false,
            isLive: isMarkedLive(),
            userPaused,
        });
        if (userPaused) {
            setStatus(pausedStatusMessage());
            return;
        }
        clearDisconnectGrace();
        if (state === 'failed' && trySoftIceRestart()) {
            disconnectTimer = window.setTimeout(() => {
                disconnectTimer = null;
                if (!pc || pc.connectionState === 'connected' || pc.connectionState === 'connecting') {
                    return;
                }
                if (userPaused || playbackLooksHealthy()) {
                    return;
                }
                scheduleRetry(waitingMessage());
            }, ICE_RESTART_GRACE_MS);
            return;
        }
        scheduleRetry(waitingMessage());
    }
}

function updateBroadcastBadge(live, status = null) {
    const badge =
        document.getElementById('broadcast-badge') ||
        document.querySelector('.embed-badge');
    if (!badge) {
        return;
    }

    const currentStatus = status || (live ? 'live' : (root?.dataset.streamStatus || 'offline'));
    const isPaused = currentStatus === 'paused';

    badge.classList.toggle('is-idle', !live && !isPaused);
    badge.classList.toggle('is-live', live);
    badge.classList.toggle('is-paused', isPaused);

    if (live) {
        badge.innerHTML =
            '<span class="live-dot" aria-hidden="true"></span> Live';
    } else if (isPaused) {
        badge.innerHTML =
            '<span class="live-dot is-paused" aria-hidden="true"></span> Paused';
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
    syncMediaSessionPlaybackState({
        isPlaying: false,
        isLive: false,
        userPaused,
    });
}

async function applyLive({ force = false } = {}) {
    offlinePollStreak = 0;
    if (root) {
        root.dataset.streamStatus = 'live';
    }
    updateBroadcastBadge(true);
    syncMediaSessionPlaybackState({
        isPlaying: audioStillPlaying(),
        isLive: true,
        userPaused,
    });
    if (userPaused) {
        stagePlayer?.enable();
        setStatus(pausedStatusMessage());
        return;
    }
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
        applyPlaybackMetadata(data);
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
            if (userPaused) {
                setStatus(pausedStatusMessage());
            } else if (
                hls &&
                !preferHls() &&
                (root.dataset.whepUrl || '') &&
                !whepPreferUpgradeTried
            ) {
                // Upgrade AAC/CDN remux to Studio Opus (WHEP) without a full page reload.
                whepPreferUpgradeTried = true;
                hlsSucceeded = false;
                void startPlayback({ force: true });
            } else if (!playbackLooksHealthy() && !startingPlayback && !retryTimer) {
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
        const nextDelay = document.hidden && audioStillPlaying() ? 15000 : STATUS_POLL_MS;
        statusPollTimer = window.setTimeout(tick, nextDelay);
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
    tearingDown = true;
    try {
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
        syncMediaSessionPlaybackState({
            isPlaying: false,
            isLive: isMarkedLive(),
            userPaused,
        });
    } finally {
        tearingDown = false;
    }
}

/**
 * Opus from Studio WHIP is not reliably playable via HLS in Chrome — use WHEP.
 * @param {string} whepUrl
 */
function iceCandidateIp(line) {
    return line.trim().split(/\s+/)[4] || '';
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

function sanitizeRemoteIceSdp(sdp) {
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

    return usable.join(nl);
}

/**
 * MediaMTX often returns Location: http://127.0.0.1:8889/live/... or /live/...
 * (Caddy stripped /rtc). DELETE then hits the wrong host/path.
 * @param {string|null} loc
 * @param {string} baseUrl
 */
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
        void playAudioElement();
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
        whepResourceUrl = resolveMediaProxyUrl(location, whepUrl) || new URL(location, whepUrl).toString();
    }

    const answer = sanitizeRemoteIceSdp(await res.text());
    // Desktop/phone WHIP often answers G.722. Chrome WHEP of G.722 is silent;
    // the AAC HLS sidecar is the working listen path (what we used before WHEP-first).
    if (!/a=rtpmap:\d+ opus\/48000/i.test(answer)) {
        if (pc) {
            pc.ontrack = null;
            pc.onconnectionstatechange = null;
            pc.close();
            pc = null;
        }
        if (whepResourceUrl) {
            try {
                await fetch(whepResourceUrl, { method: 'DELETE' });
            } catch {
                /* ignore */
            }
            whepResourceUrl = null;
        }
        throw new Error('WHEP answered without Opus');
    }
    await pc.setRemoteDescription({ type: 'answer', sdp: answer });
    whepSucceeded = true;
}

/**
 * True if the listen meter heard energy, false if it stayed flat, null if
 * we could not measure (do not force a WHEP fallback).
 * @param {number} ms
 * @returns {Promise<boolean|null>}
 */
async function waitForListenEnergy(ms) {
    const deadline = Date.now() + ms;
    let sawAnalyser = false;
    while (Date.now() < deadline) {
        const peak = listenMeterPeak();
        if (peak != null) {
            sawAnalyser = true;
            if (peak >= 6) {
                return true;
            }
        }
        await new Promise((resolve) => window.setTimeout(resolve, 200));
    }
    return sawAnalyser ? false : null;
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

    const CONNECT_MS = allowFallback ? 14000 : 15000;

    if (audio.canPlayType('application/vnd.apple.mpegurl')) {
        await new Promise((resolve, reject) => {
            let settled = false;
            const timer = window.setTimeout(() => {
                if (!settled) {
                    settled = true;
                    reject(new Error('HLS connect timeout'));
                }
            }, CONNECT_MS);
            const onPlaying = async () => {
                if (settled) {
                    return;
                }
                if (allowFallback && !userPaused) {
                    const heard = await waitForListenEnergy(4000);
                    if (settled) {
                        return;
                    }
                    if (heard === false) {
                        settled = true;
                        window.clearTimeout(timer);
                        reject(new Error('HLS silent'));
                        return;
                    }
                }
                if (settled) {
                    return;
                }
                settled = true;
                window.clearTimeout(timer);
                setStatus(userPaused ? pausedStatusMessage() : (isMarkedLive() ? 'On air' : 'Playing'));
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
            if (userPaused) {
                if (!settled) {
                    settled = true;
                    window.clearTimeout(timer);
                    hlsSucceeded = true;
                    resolve();
                }
            } else {
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
            }
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
                if (hls) {
                    hls.destroy();
                    hls = null;
                }
                if (audio) {
                    audio.removeAttribute('src');
                    audio.srcObject = null;
                    audio.load();
                }
                reject(err);
            } else {
                hlsSucceeded = true;
                resolve();
            }
        };
        const timer = window.setTimeout(() => finish(new Error('HLS connect timeout')), CONNECT_MS);

        hls = new Hls({
            xhrSetup(xhr, url) {
                const next = urlWithListenCapability(url, hlsUrl);
                if (next !== url) {
                    xhr.open('GET', next, true);
                }
            },
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
        hls.on(Hls.Events.MANIFEST_PARSED, async () => {
            if (userPaused) {
                finish(null);
                return;
            }
            try {
                await audio.play();
            } catch {
                setStatus('Press play when you are ready.');
                finish(null);
                return;
            }
            if (allowFallback) {
                const heard = await waitForListenEnergy(4000);
                if (settled) {
                    return;
                }
                if (heard === false) {
                    finish(new Error('HLS silent'));
                    return;
                }
            }
            finish(null);
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
                if (userPaused) {
                    setStatus(pausedStatusMessage());
                    return;
                }
                void teardown().then(() => scheduleRetry('Trying again…'));
                return;
            }
            if (data.type === Hls.ErrorTypes.MEDIA_ERROR) {
                if (userPaused) {
                    setStatus(pausedStatusMessage());
                    return;
                }
                try {
                    hls?.recoverMediaError();
                    setStatus('Recovering playback…');
                } catch {
                    finish(new Error('HLS media recovery failed'));
                    void teardown().then(() => scheduleRetry('Trying again…'));
                }
                return;
            }
            if (userPaused) {
                setStatus(pausedStatusMessage());
                return;
            }
            setStatus('Playback error.');
            finish(new Error('HLS playback error'));
            void teardown().then(() => scheduleRetry('Trying again…'));
        });
        audio.addEventListener(
            'playing',
            () => {
                setStatus(userPaused ? pausedStatusMessage() : 'On air');
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

    if (userPaused) {
        stagePlayer?.enable();
        setStatus(pausedStatusMessage());
        return;
    }

    // Already listening — do not tear down a healthy session.
    if (!force && playbackLooksHealthy()) {
        resetRetryBackoff();
        setStatus(isMarkedLive() ? 'On air' : 'Playing');
        return;
    }

    ensureStagePlayer();

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
        if (userPaused) {
            setStatus(pausedStatusMessage());
            return;
        }

        const tryHlsFirst = preferHls() || hlsSucceeded;
        // After a successful WHEP session, stay on WHEP unless HLS is preferred (CDN/AAC).
        const stickToWhep = whepSucceeded && !tryHlsFirst;

        if (tryHlsFirst && hlsUrl && !stickToWhep) {
            try {
                await startHls(hlsUrl, { allowFallback: Boolean(whepUrl) });
                resetRetryBackoff();
                return;
            } catch {
                if (userPaused) {
                    setStatus(pausedStatusMessage());
                    return;
                }
                // Fall through to WHEP (Studio Opus / sidecar not ready yet).
            }
        }

        if (whepUrl) {
            try {
                await startWhep(whepUrl);
                resetRetryBackoff();
                return;
            } catch {
                if (userPaused) {
                    setStatus(pausedStatusMessage());
                    return;
                }
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
                if (userPaused) {
                    setStatus(pausedStatusMessage());
                    return;
                }
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

function ensureStagePlayer() {
    if (!audio || stagePlayer) {
        return;
    }
    stagePlayer = bindStagePlayer(audio, {
        onUserPause: () => setUserPaused(true),
        onUserPlay: () => {
            setUserPaused(false);
            interrupted = false;
            ensureAudioSession();
            updateMediaSessionMetadata(currentMetadata);
            if (!hasAttachedMedia() || peerIsTerminal()) {
                void startPlayback({ force: true });
                return true;
            }
            return false;
        },
    });

    audio.addEventListener('playing', () => {
        interrupted = false;
        if (audio && currentMetadata.title) {
            audio.title = currentMetadata.title;
        }
        updateMediaSessionMetadata(currentMetadata);
        syncMediaSessionPlaybackState({
            isPlaying: true,
            isLive: isMarkedLive(),
            userPaused,
        });
    });

    audio.addEventListener('pause', () => {
        if (tearingDown || startingPlayback) {
            return;
        }
        if (!hasAttachedMedia()) {
            return;
        }
        syncMediaSessionPlaybackState({
            isPlaying: false,
            isLive: isMarkedLive(),
            userPaused,
        });

        const pauseType = classifyAudioPause({ userPaused });
        if (pauseType.isInterruption) {
            // OS interruption (e.g. incoming call, audio focus loss, bluetooth disconnect)
            interrupted = true;
            clearRetry();
            setStatus('Playback paused — press play to resume.');
        }
    });

    audio.addEventListener('ended', () => {
        syncMediaSessionPlaybackState({
            isPlaying: false,
            isLive: false,
            userPaused: true,
        });
    });
}

function initMediaSession() {
    currentMetadata = {
        title: root?.dataset.title || document.title || 'Live Broadcast',
        artist: root?.dataset.artist || 'Sound Mix Live',
        artworkUrl: root?.dataset.artwork || '',
    };

    setupMediaSession({
        metadata: currentMetadata,
        onUserPlay: () => {
            setUserPaused(false);
            interrupted = false;
            ensureAudioSession();
            updateMediaSessionMetadata(currentMetadata);
            if (!hasAttachedMedia() || peerIsTerminal()) {
                void startPlayback({ force: true });
            } else {
                void playAudioElement();
            }
        },
        onUserPause: () => {
            setUserPaused(true);
            interrupted = false;
            if (audio) {
                audio.pause();
            }
        },
    });
}

document.addEventListener('visibilitychange', () => {
    handleVisibilityChange({
        isHidden: document.hidden,
        isLive: isMarkedLive(),
        userPaused,
        isPlaying: audioStillPlaying(),
        onForegroundRecovery: () => {
            if (peerIsTerminal() || !playbackLooksHealthy()) {
                void startPlayback({ force: true });
            } else if (audio && audio.paused && !interrupted) {
                void playAudioElement();
            }
        },
    });

    if (!document.hidden) {
        void refreshStreamStatus();
        const gUrl = root?.dataset.galleryUrl;
        if (gUrl) {
            void refreshGalleryFromUrl(gUrl);
        }
    }
});

function bindMobilePortalTabs() {
    const tabs = document.querySelectorAll('.portal-tab-btn');
    const cards = document.getElementById('portal-cards');
    if (!tabs.length || !cards) return;

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            const target = tab.dataset.tab;
            if (!target) return;
            cards.dataset.activeTab = target;
            tabs.forEach((t) => {
                const active = t === tab;
                t.classList.toggle('bg-white/15', active);
                t.classList.toggle('text-white', active);
                t.classList.toggle('shadow-sm', active);
                t.classList.toggle('text-white/60', !active);
                t.classList.toggle('is-active', active);
            });
            if (target === 'scripture' || target === 'board') {
                const dot = document.getElementById('scripture-tab-dot');
                if (dot) {
                    dot.classList.add('hidden');
                }
            }
        });
    });
}

function bindStickyPlayer() {
    const sticky = document.getElementById('mobile-sticky-player');
    const hero = document.getElementById('portal-hero-viewport') || document.querySelector('.portal-player-screen') || document.getElementById('stage-play-shell') || document.querySelector('.stage-player');
    if (!sticky || !hero) return;

    if (typeof IntersectionObserver !== 'undefined') {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                const shouldShow = !entry.isIntersecting;
                sticky.classList.toggle('is-visible', shouldShow);
            });
        }, { threshold: 0.1 });

        observer.observe(hero);
    }

    const stickyPlay = document.getElementById('sticky-btn-play');
    const heroPlay = document.getElementById('btn-play');
    if (stickyPlay && heroPlay) {
        stickyPlay.addEventListener('click', () => {
            heroPlay.click();
        });
    }

    const stickyVol = document.getElementById('sticky-btn-volume');
    const heroVol = document.getElementById('btn-volume');
    if (stickyVol && heroVol) {
        stickyVol.addEventListener('click', () => {
            heroVol.click();
        });
    }

    if (audio) {
        const updateStickyState = () => {
            const playing = !audio.paused && !audio.ended && audio.currentTime > 0;
            const iconPlay = stickyPlay?.querySelector('.icon-play');
            const iconPause = stickyPlay?.querySelector('.icon-pause');
            if (iconPlay && iconPause) {
                iconPlay.classList.toggle('hidden', playing);
                iconPause.classList.toggle('hidden', !playing);
            }
            const volIcon = stickyVol?.querySelector('.icon-volume');
            const mutedIcon = stickyVol?.querySelector('.icon-muted');
            if (volIcon && mutedIcon) {
                volIcon.classList.toggle('hidden', audio.muted);
                mutedIcon.classList.toggle('hidden', !audio.muted);
            }
        };

        audio.addEventListener('play', updateStickyState);
        audio.addEventListener('pause', updateStickyState);
        audio.addEventListener('volumechange', updateStickyState);
        updateStickyState();
    }
}

function bindListenAccordion() {
    const triggers = document.querySelectorAll('.listen-accordion-trigger');
    const cards = document.getElementById('portal-cards');
    if (!triggers.length) return;

    triggers.forEach((trigger) => {
        trigger.addEventListener('click', () => {
            const item = trigger.closest('.listen-accordion-item');
            if (!item) return;
            const wasOpen = item.classList.contains('is-open');
            const targetTab = trigger.dataset.accordionTab;

            // Single disclosure: close others if open
            document.querySelectorAll('.listen-accordion-item').forEach((other) => {
                other.classList.remove('is-open');
                const btn = other.querySelector('.listen-accordion-trigger');
                if (btn) btn.setAttribute('aria-expanded', 'false');
                const panel = other.querySelector('.listen-accordion-panel');
                if (panel) panel.hidden = true;
            });

            if (!wasOpen) {
                item.classList.add('is-open');
                trigger.setAttribute('aria-expanded', 'true');
                const panel = item.querySelector('.listen-accordion-panel');
                if (panel) {
                    panel.hidden = false;
                    if (targetTab === 'chat') {
                        const msgList = panel.querySelector('#chat-messages');
                        if (msgList) msgList.scrollTop = msgList.scrollHeight;
                    }
                }
                if (cards && targetTab) {
                    cards.dataset.activeTab = targetTab;
                }
                if (targetTab === 'scripture') {
                    const dot = document.getElementById('scripture-tab-dot');
                    if (dot) dot.classList.add('hidden');
                }
            } else {
                if (cards) {
                    cards.dataset.activeTab = '';
                }
            }
        });
    });
}

ensureAudioSession();
initMediaSession();
startStatusPolling();
ensureStagePlayer();
void startPlayback();
bindInitialGalleryFromDom();
bindScriptureListen(root);
bindMobilePortalTabs();
bindListenAccordion();
bindStickyPlayer();
const galleryUrl = root?.dataset.galleryUrl;
if (galleryUrl) {
    void refreshGalleryFromUrl(galleryUrl);
    window.setInterval(() => {
        if (!document.hidden) {
            void refreshGalleryFromUrl(galleryUrl);
        }
    }, 20000);
}
