/**
 * Stage player: play/pause control + reactive waveform bars.
 * Expects #stream-audio, #btn-play, #stage-wave, optional #stream-status.
 */

const BAR_COUNT = 24;

/** @type {AudioContext|null} */
let audioCtx = null;
/** @type {AnalyserNode|null} */
let analyser = null;
/** @type {MediaElementAudioSourceNode|MediaStreamAudioSourceNode|null} */
let sourceNode = null;
/** @type {string|null} */
let sourceMode = null;
let raf = 0;
let wired = false;

function iconPlay() {
    return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5.14v13.72a1 1 0 0 0 1.5.86l11-6.86a1 1 0 0 0 0-1.72l-11-6.86a1 1 0 0 0-1.5.86z"/></svg>';
}

function iconPause() {
    return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 5h3.5v14H7V5zm6.5 0H17v14h-3.5V5z"/></svg>';
}

function ensureBars(wave) {
    if (!wave || wave.children.length) {
        return;
    }
    const frag = document.createDocumentFragment();
    for (let i = 0; i < BAR_COUNT; i++) {
        const span = document.createElement('span');
        span.style.height = '4px';
        frag.appendChild(span);
    }
    wave.appendChild(frag);
}

function stopMeter() {
    if (raf) {
        cancelAnimationFrame(raf);
        raf = 0;
    }
}

function tick(wave) {
    if (!analyser || !wave) {
        return;
    }
    const data = new Uint8Array(analyser.frequencyBinCount);
    analyser.getByteFrequencyData(data);
    const bars = wave.children;
    const step = Math.floor(data.length / bars.length) || 1;
    for (let i = 0; i < bars.length; i++) {
        const v = data[i * step] / 255;
        const h = Math.max(4, Math.round(4 + v * 44));
        bars[i].style.height = `${h}px`;
        bars[i].style.opacity = String(0.35 + v * 0.65);
    }
    raf = requestAnimationFrame(() => tick(wave));
}

function connectAnalyser(audio) {
    const stream = audio.srcObject instanceof MediaStream ? audio.srcObject : null;
    const mode = stream ? 'stream' : 'element';
    if (sourceNode && sourceMode === mode) {
        return;
    }
    try {
        audioCtx = audioCtx || new AudioContext();
        analyser = audioCtx.createAnalyser();
        analyser.fftSize = 128;
        if (stream) {
            // WebRTC/WHEP: MediaElementSource is flaky with srcObject — tap the stream instead.
            sourceNode = audioCtx.createMediaStreamSource(stream);
            sourceNode.connect(analyser);
            // Keep element output for audible playback; analyser is meter-only.
        } else {
            sourceNode = audioCtx.createMediaElementSource(audio);
            sourceNode.connect(analyser);
            analyser.connect(audioCtx.destination);
        }
        sourceMode = mode;
    } catch {
        /* autoplay / already connected */
    }
}

function syncButton(audio, btn) {
    if (!btn) {
        return;
    }
    const playing = !audio.paused && !audio.ended;
    btn.innerHTML = playing ? iconPause() : iconPlay();
    btn.setAttribute('aria-label', playing ? 'Pause' : 'Play');
    btn.classList.toggle('is-live', playing);
}

/**
 * @param {HTMLAudioElement} audio
 */
export function bindStagePlayer(audio) {
    const btn = document.getElementById('btn-play');
    const wave = document.getElementById('stage-wave');
    const playShell = document.getElementById('stage-play-shell');
    const volumeBtn = document.getElementById('btn-volume');
    const waveToggle = document.getElementById('btn-wave-toggle');

    ensureBars(wave);

    if (!wired && btn) {
        wired = true;
        btn.addEventListener('click', async () => {
            if (audio.paused) {
                try {
                    if (audioCtx?.state === 'suspended') {
                        await audioCtx.resume();
                    }
                    await audio.play();
                } catch {
                    /* listen.js sets status */
                }
            } else {
                audio.pause();
            }
        });

        volumeBtn?.addEventListener('click', () => {
            audio.muted = !audio.muted;
            volumeBtn.classList.toggle('is-muted', audio.muted);
            volumeBtn.setAttribute('aria-label', audio.muted ? 'Unmute' : 'Mute');
            const vol = volumeBtn.querySelector('.icon-volume');
            const muted = volumeBtn.querySelector('.icon-muted');
            vol?.classList.toggle('hidden', audio.muted);
            muted?.classList.toggle('hidden', !audio.muted);
        });

        waveToggle?.addEventListener('click', () => {
            const on = waveToggle.getAttribute('aria-pressed') !== 'true';
            waveToggle.setAttribute('aria-pressed', on ? 'true' : 'false');
            waveToggle.classList.toggle('is-on', on);
            wave?.classList.toggle('is-hidden', !on);
        });
    }

    const onPlay = () => {
        connectAnalyser(audio);
        void audioCtx?.resume();
        wave?.classList.add('is-active');
        playShell?.classList.add('is-live');
        stopMeter();
        tick(wave);
        syncButton(audio, btn);
    };

    const onPause = () => {
        stopMeter();
        wave?.classList.remove('is-active');
        playShell?.classList.remove('is-live');
        if (wave) {
            for (const bar of wave.children) {
                bar.style.height = '4px';
                bar.style.opacity = '0.35';
            }
        }
        syncButton(audio, btn);
    };

    audio.addEventListener('play', onPlay);
    audio.addEventListener('playing', onPlay);
    audio.addEventListener('pause', onPause);
    audio.addEventListener('ended', onPause);

    syncButton(audio, btn);

    return {
        enable() {
            if (btn) {
                btn.disabled = false;
            }
        },
        disable() {
            if (btn) {
                btn.disabled = true;
            }
        },
        setPlayingVisual(playing) {
            if (playing) {
                onPlay();
            } else {
                onPause();
            }
        },
    };
}
