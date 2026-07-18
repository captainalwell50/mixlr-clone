import './bootstrap';
import { bindStagePlayer } from './player';

const root = document.getElementById('listen-root');
const audio = document.getElementById('stream-audio');
const statusEl = document.getElementById('stream-status');

/** @type {import('hls.js').default | null} */
let hls = null;
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

async function startPlayback() {
    if (!root || !audio) {
        return;
    }

    if (!stagePlayer) {
        stagePlayer = bindStagePlayer(audio);
    }

    const hlsUrl = root.dataset.hlsUrl;
    if (!hlsUrl) {
        setStatus('Playback URL missing.');
        stagePlayer.disable();
        return;
    }

    stagePlayer.enable();

    if (!isMarkedLive()) {
        setStatus('Waiting for the broadcast to start. This page will keep trying.');
    } else {
        setStatus('Connecting…');
    }

    if (audio.canPlayType('application/vnd.apple.mpegurl')) {
        audio.src = hlsUrl;
        audio.addEventListener(
            'error',
            () => {
                scheduleRetry(
                    isMarkedLive()
                        ? 'Stream interrupted — reconnecting…'
                        : 'Waiting for the broadcast to start. This page will keep trying.',
                );
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
        setStatus('This browser cannot play HLS.');
        stagePlayer.disable();
        return;
    }

    if (hls) {
        hls.destroy();
        hls = null;
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
            scheduleRetry(
                isMarkedLive()
                    ? 'Stream interrupted — reconnecting…'
                    : 'Waiting for the broadcast to start. This page will keep trying.',
            );
            return;
        }
        setStatus('Playback error.');
        hls?.destroy();
        hls = null;
        scheduleRetry('Trying again…');
    });
    audio.addEventListener(
        'playing',
        () => {
            setStatus('On air');
        },
        { once: true },
    );
}

void startPlayback();
