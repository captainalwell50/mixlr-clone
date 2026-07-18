import './bootstrap';

const root = document.getElementById('listen-root');
const audio = document.getElementById('stream-audio');
const statusEl = document.getElementById('stream-status');

/** @type {import('hls.js').default | null} */
let hls = null;
let retryTimer = null;

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

    const hlsUrl = root.dataset.hlsUrl;
    if (!hlsUrl) {
        setStatus('Playback URL missing.');
        return;
    }

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
                setStatus(isMarkedLive() ? '' : 'Playing');
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
            setStatus('');
        },
        { once: true },
    );
}

void startPlayback();
