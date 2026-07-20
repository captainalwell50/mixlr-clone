import './bootstrap';

const root = document.getElementById('engage-root');
if (root) {
    const presenceUrl = root.dataset.presenceUrl;
    const heartUrl = root.dataset.heartUrl;
    const csrf = root.dataset.csrf || document.querySelector('meta[name="csrf-token"]')?.content;
    const listenerEl = document.getElementById('listener-count');
    const heartCountEl = document.getElementById('heart-count');
    const heartBtn = document.getElementById('btn-heart');
    const shareBtn = document.getElementById('btn-share');
    const infoBtn = document.getElementById('btn-info');
    const infoPanel = document.getElementById('portal-info');
    const chatToggle = document.getElementById('btn-chat-toggle');
    const chatClose = document.getElementById('btn-chat-close');
    const layout = document.getElementById('portal-layout');

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

    heartBtn?.addEventListener('click', async (ev) => {
        if (heartBtn.tagName === 'A') {
            return;
        }
        ev.preventDefault();
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
            heartBtn.classList.add('is-on');
            if (heartCountEl && data.hearts != null) {
                heartCountEl.textContent = String(data.hearts);
            }
        } catch {
            /* ignore */
        }
    });

    shareBtn?.addEventListener('click', async () => {
        const url = shareBtn.dataset.shareUrl || window.location.href;
        const title = shareBtn.dataset.shareTitle || document.title;
        try {
            if (navigator.share) {
                await navigator.share({ title, url });
                return;
            }
            await navigator.clipboard.writeText(url);
            shareBtn.classList.add('is-on');
            window.setTimeout(() => shareBtn.classList.remove('is-on'), 1600);
        } catch {
            /* ignore cancel / clipboard errors */
        }
    });

    infoBtn?.addEventListener('click', () => {
        if (!infoPanel) {
            return;
        }
        const open = infoPanel.hasAttribute('hidden');
        if (open) {
            infoPanel.removeAttribute('hidden');
        } else {
            infoPanel.setAttribute('hidden', '');
        }
        infoBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        infoBtn.classList.toggle('is-on', open);
    });

    function setChatOpen(open) {
        layout?.classList.toggle('chat-open', open);
        chatToggle?.classList.toggle('is-on', open);
    }

    chatToggle?.addEventListener('click', () => {
        setChatOpen(!layout?.classList.contains('chat-open'));
        document.getElementById('portal-chat')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });

    chatClose?.addEventListener('click', () => {
        setChatOpen(false);
    });

    void beat();
    window.setInterval(() => void beat(), 15000);
}
