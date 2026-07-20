import './bootstrap';
import { bindStagePlayer } from './player';
import { bindInitialGalleryFromDom, refreshGalleryFromUrl } from './gallery-ui';

const root = document.getElementById('archive-root');
const audio = document.getElementById('stream-audio');
const statusEl = document.getElementById('stream-status');
const seekEl = document.getElementById('archive-seek');
const timeCurrentEl = document.getElementById('archive-time-current');
const timeDurationEl = document.getElementById('archive-time-duration');
const skipBackBtn = document.getElementById('btn-skip-back');
const skipForwardBtn = document.getElementById('btn-skip-forward');
const volumeSlider = document.getElementById('archive-volume');
const volumeBtn = document.getElementById('btn-volume');

let seeking = false;
let lastVolume = 1;

function formatTime(seconds) {
    if (!Number.isFinite(seconds) || seconds < 0) {
        return '0:00';
    }
    const total = Math.floor(seconds);
    const h = Math.floor(total / 3600);
    const m = Math.floor((total % 3600) / 60);
    const s = total % 60;
    if (h > 0) {
        return `${h}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
    }
    return `${m}:${String(s).padStart(2, '0')}`;
}

function setRangeFill(el, pct) {
    el?.style.setProperty('--seek-pct', `${Math.min(100, Math.max(0, pct))}%`);
}

function syncVolumeUi() {
    if (!audio || !volumeSlider) {
        return;
    }
    const level = audio.muted ? 0 : audio.volume;
    if (!volumeSlider.matches(':active')) {
        volumeSlider.value = String(level);
    }
    setRangeFill(volumeSlider, level * 100);
    volumeBtn?.classList.toggle('is-muted', audio.muted || audio.volume === 0);
    volumeBtn?.setAttribute('aria-label', audio.muted || audio.volume === 0 ? 'Unmute' : 'Mute');
    const vol = volumeBtn?.querySelector('.icon-volume');
    const muted = volumeBtn?.querySelector('.icon-muted');
    vol?.classList.toggle('hidden', audio.muted || audio.volume === 0);
    muted?.classList.toggle('hidden', !(audio.muted || audio.volume === 0));
}

function syncTimes() {
    if (!audio) {
        return;
    }
    const duration = Number.isFinite(audio.duration) ? audio.duration : 0;
    const current = Number.isFinite(audio.currentTime) ? audio.currentTime : 0;
    if (timeCurrentEl) {
        timeCurrentEl.textContent = formatTime(current);
    }
    if (timeDurationEl) {
        timeDurationEl.textContent = formatTime(duration);
    }
    if (seekEl && !seeking) {
        seekEl.max = String(duration || 0);
        seekEl.value = String(current || 0);
        setRangeFill(seekEl, duration > 0 ? (current / duration) * 100 : 0);
    }
}

function seekBy(delta) {
    if (!audio || !Number.isFinite(audio.duration)) {
        return;
    }
    audio.currentTime = Math.min(Math.max(0, audio.currentTime + delta), audio.duration || 0);
    syncTimes();
}

if (root && audio) {
    const src = root.dataset.src;
    const player = bindStagePlayer(audio);

    audio.volume = 1;
    syncVolumeUi();

    if (!src) {
        if (statusEl) {
            statusEl.textContent = 'Recording file missing.';
        }
        player.disable();
        if (skipBackBtn) {
            skipBackBtn.disabled = true;
        }
        if (skipForwardBtn) {
            skipForwardBtn.disabled = true;
        }
        if (seekEl) {
            seekEl.disabled = true;
        }
        if (volumeSlider) {
            volumeSlider.disabled = true;
        }
    } else {
        audio.src = src;
        player.enable();

        audio.addEventListener('loadedmetadata', () => {
            if (seekEl) {
                seekEl.disabled = false;
                seekEl.max = String(audio.duration || 0);
            }
            syncTimes();
        });
        audio.addEventListener('durationchange', syncTimes);
        audio.addEventListener('timeupdate', syncTimes);
        audio.addEventListener('volumechange', syncVolumeUi);
        audio.addEventListener('playing', () => {
            if (statusEl) {
                statusEl.textContent = 'Playing';
            }
        });
        audio.addEventListener('pause', () => {
            if (statusEl && !audio.ended) {
                statusEl.textContent = 'Paused';
            }
        });
        audio.addEventListener('ended', () => {
            if (statusEl) {
                statusEl.textContent = 'Ended';
            }
            syncTimes();
        });

        seekEl?.addEventListener('pointerdown', () => {
            seeking = true;
        });
        seekEl?.addEventListener('pointerup', () => {
            seeking = false;
        });
        seekEl?.addEventListener('input', () => {
            seeking = true;
            const value = Number(seekEl.value);
            if (!Number.isFinite(value)) {
                return;
            }
            const duration = Number.isFinite(audio.duration) ? audio.duration : 0;
            setRangeFill(seekEl, duration > 0 ? (value / duration) * 100 : 0);
            if (timeCurrentEl) {
                timeCurrentEl.textContent = formatTime(value);
            }
        });
        seekEl?.addEventListener('change', () => {
            const value = Number(seekEl.value);
            if (Number.isFinite(value)) {
                audio.currentTime = value;
            }
            seeking = false;
            syncTimes();
        });

        skipBackBtn?.addEventListener('click', () => seekBy(-15));
        skipForwardBtn?.addEventListener('click', () => seekBy(15));

        volumeSlider?.addEventListener('input', () => {
            const value = Number(volumeSlider.value);
            if (!Number.isFinite(value)) {
                return;
            }
            audio.muted = value === 0;
            audio.volume = value;
            if (value > 0) {
                lastVolume = value;
            }
            syncVolumeUi();
        });

        // Mute button is also wired by bindStagePlayer; keep volume slider in sync after toggle.
        volumeBtn?.addEventListener('click', () => {
            window.setTimeout(() => {
                if (audio.muted) {
                    lastVolume = audio.volume > 0 ? audio.volume : lastVolume || 1;
                } else if (audio.volume === 0) {
                    audio.volume = lastVolume || 1;
                }
                syncVolumeUi();
            }, 0);
        });
    }
}

bindInitialGalleryFromDom();
const galleryUrl = root?.dataset.galleryUrl;
if (galleryUrl) {
    void refreshGalleryFromUrl(galleryUrl);
    window.setInterval(() => void refreshGalleryFromUrl(galleryUrl), 20000);
}
