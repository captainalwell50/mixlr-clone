import './bootstrap';
import { bindStagePlayer } from './player';

const root = document.getElementById('archive-root');
const audio = document.getElementById('stream-audio');
const statusEl = document.getElementById('stream-status');
const seekEl = document.getElementById('archive-seek');
const timeCurrentEl = document.getElementById('archive-time-current');
const timeDurationEl = document.getElementById('archive-time-duration');
const skipBackBtn = document.getElementById('btn-skip-back');
const skipForwardBtn = document.getElementById('btn-skip-forward');

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
    if (seekEl && !seekEl.matches(':active')) {
        seekEl.max = String(duration || 0);
        seekEl.value = String(current || 0);
        const pct = duration > 0 ? (current / duration) * 100 : 0;
        seekEl.style.setProperty('--seek-pct', `${pct}%`);
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

    if (!src) {
        if (statusEl) {
            statusEl.textContent = 'Recording file missing.';
        }
        player.disable();
        skipBackBtn && (skipBackBtn.disabled = true);
        skipForwardBtn && (skipForwardBtn.disabled = true);
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

        seekEl?.addEventListener('input', () => {
            const value = Number(seekEl.value);
            if (!Number.isFinite(value)) {
                return;
            }
            const duration = Number.isFinite(audio.duration) ? audio.duration : 0;
            const pct = duration > 0 ? (value / duration) * 100 : 0;
            seekEl.style.setProperty('--seek-pct', `${pct}%`);
            if (timeCurrentEl) {
                timeCurrentEl.textContent = formatTime(value);
            }
        });
        seekEl?.addEventListener('change', () => {
            const value = Number(seekEl.value);
            if (Number.isFinite(value)) {
                audio.currentTime = value;
            }
            syncTimes();
        });

        skipBackBtn?.addEventListener('click', () => seekBy(-15));
        skipForwardBtn?.addEventListener('click', () => seekBy(15));
    }
}
