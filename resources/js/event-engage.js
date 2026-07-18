import './bootstrap';

const root = document.getElementById('engage-root');
if (root) {
    const presenceUrl = root.dataset.presenceUrl;
    const heartUrl = root.dataset.heartUrl;
    const csrf = root.dataset.csrf || document.querySelector('meta[name="csrf-token"]')?.content;
    const listenerEl = document.getElementById('listener-count');
    const heartCountEl = document.getElementById('heart-count');
    const heartBtn = document.getElementById('btn-heart');

    let sessionKey = localStorage.getItem('listener_sid') || '';

    async function beat() {
        if (!presenceUrl) {
            return;
        }
        try {
            const res = await fetch(presenceUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ session_key: sessionKey || undefined }),
            });
            if (!res.ok) {
                return;
            }
            const data = await res.json();
            if (data.session_key) {
                sessionKey = data.session_key;
                localStorage.setItem('listener_sid', sessionKey);
            }
            if (listenerEl && data.listeners != null) {
                listenerEl.textContent = String(data.listeners);
            }
            if (heartCountEl && data.hearts != null) {
                heartCountEl.textContent = String(data.hearts);
            }
        } catch {
            /* ignore */
        }
    }

    heartBtn?.addEventListener('click', async () => {
        if (!heartUrl || heartBtn.dataset.hearted === '1') {
            return;
        }
        try {
            const res = await fetch(heartUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (res.status === 401) {
                window.location.href = '/login';
                return;
            }
            if (!res.ok) {
                return;
            }
            const data = await res.json();
            heartBtn.dataset.hearted = '1';
            heartBtn.textContent = '♥ Hearted';
            heartBtn.classList.add('is-on', 'text-rose-300');
            if (heartCountEl && data.hearts != null) {
                heartCountEl.textContent = String(data.hearts);
            }
        } catch {
            /* ignore */
        }
    });

    void beat();
    window.setInterval(() => void beat(), 15000);
}
