import './bootstrap';

const root = document.getElementById('engage-root');
if (root) {
    const presenceUrl = root.dataset.presenceUrl;
    const likeUrl = root.dataset.likeUrl || root.dataset.heartUrl;
    const csrf = root.dataset.csrf || document.querySelector('meta[name="csrf-token"]')?.content;
    const listenerEl = document.getElementById('listener-count');
    const likeCountEl = document.getElementById('like-count') || document.getElementById('heart-count');
    const likeBtn = document.getElementById('btn-like') || document.getElementById('btn-heart');
    const followBtn = document.getElementById('btn-follow');
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
            const likes = data.likes ?? data.hearts;
            if (likeCountEl && likes != null) {
                likeCountEl.textContent = String(likes);
            }
        } catch {
            /* ignore */
        }
    }

    likeBtn?.addEventListener('click', async (ev) => {
        if (likeBtn.tagName === 'A') {
            return;
        }
        ev.preventDefault();
        if (!likeUrl || likeBtn.dataset.liked === '1' || likeBtn.dataset.hearted === '1') {
            return;
        }
        try {
            const res = await fetch(likeUrl, {
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
            likeBtn.dataset.liked = '1';
            likeBtn.dataset.hearted = '1';
            likeBtn.classList.add('is-on');
            const likes = data.likes ?? data.hearts;
            if (likeCountEl && likes != null) {
                likeCountEl.textContent = String(likes);
            }
        } catch {
            /* ignore */
        }
    });

    followBtn?.addEventListener('click', async () => {
        const following = followBtn.dataset.following === '1';
        const url = following ? followBtn.dataset.unfollowUrl : followBtn.dataset.followUrl;
        if (!url) {
            return;
        }
        try {
            const res = await fetch(url, {
                method: following ? 'DELETE' : 'POST',
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
            const nowFollowing = Boolean(data.following);
            followBtn.dataset.following = nowFollowing ? '1' : '0';
            followBtn.classList.toggle('is-following', nowFollowing);
            followBtn.textContent = nowFollowing ? 'Following' : '+ Follow';
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
            /* ignore */
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
