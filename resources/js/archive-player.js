import './bootstrap';
import { bindStagePlayer } from './player';

const root = document.getElementById('archive-root');
const audio = document.getElementById('stream-audio');
const statusEl = document.getElementById('stream-status');

if (root && audio) {
    const src = root.dataset.src;
    const player = bindStagePlayer(audio);

    if (!src) {
        if (statusEl) {
            statusEl.textContent = 'Recording file missing.';
        }
        player.disable();
    } else {
        audio.src = src;
        player.enable();
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
        });
    }
}
