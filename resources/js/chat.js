import './bootstrap';

const root = document.getElementById('chat-root');
if (root) {
    const listEl = document.getElementById('chat-messages');
    const formEl = document.getElementById('chat-form');
    const bodyEl = document.getElementById('chat-body');
    const pollUrl = root.dataset.pollUrl;
    const postUrl = root.dataset.postUrl;
    const selfName = (root.dataset.selfName || '').trim().toLowerCase();
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    let lastId = 0;

    function initials(name) {
        const parts = String(name || '?').trim().split(/\s+/).filter(Boolean);
        if (parts.length === 0) {
            return '?';
        }
        if (parts.length === 1) {
            return parts[0].slice(0, 2).toUpperCase();
        }
        return (parts[0][0] + parts[1][0]).toUpperCase();
    }

    function formatTime(iso) {
        if (!iso) {
            return '';
        }
        try {
            return new Date(iso).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
        } catch {
            return '';
        }
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;');
    }

    function appendMessage(msg) {
        if (!listEl || msg.id <= lastId) {
            return;
        }
        lastId = Math.max(lastId, msg.id);
        listEl.querySelector('.wa-empty')?.remove();
        const mine = selfName !== '' && String(msg.name || '').trim().toLowerCase() === selfName;
        const row = document.createElement('div');
        row.className = `wa-msg ${mine ? 'is-mine' : 'is-theirs'}`;
        row.innerHTML = `
            ${mine ? '' : `<span class="wa-avatar" aria-hidden="true">${escapeHtml(initials(msg.name))}</span>`}
            <div class="wa-bubble">
                ${mine ? '' : `<p class="wa-name">${escapeHtml(msg.name)}</p>`}
                <p class="wa-body"></p>
                <span class="wa-meta">
                    <time>${escapeHtml(formatTime(msg.at))}</time>
                    ${mine ? '<span class="wa-ticks" aria-hidden="true">✓✓</span>' : ''}
                </span>
            </div>
        `;
        row.querySelector('.wa-body').textContent = msg.body;
        listEl.appendChild(row);
        listEl.scrollTop = listEl.scrollHeight;
    }

    async function poll() {
        try {
            const res = await fetch(`${pollUrl}?after=${lastId}`, {
                headers: { Accept: 'application/json' },
            });
            if (!res.ok) {
                return;
            }
            const data = await res.json();
            if (data.enabled === false && listEl) {
                listEl.innerHTML = '<p class="wa-empty">Chat is off for this broadcast.</p>';
                return;
            }
            for (const msg of data.messages || []) {
                appendMessage(msg);
            }
        } catch {
            /* ignore */
        }
    }

    formEl?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const body = bodyEl?.value?.trim();
        if (!body) {
            return;
        }
        const sendBtn = formEl.querySelector('button[type="submit"]');
        if (sendBtn) {
            sendBtn.disabled = true;
        }
        try {
            const res = await fetch(postUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ body }),
            });
            if (res.status === 401) {
                window.location.href = '/login';
                return;
            }
            if (!res.ok) {
                const err = await res.json().catch(() => ({}));
                alert(err.message || 'Could not send message.');
                return;
            }
            const data = await res.json();
            if (data.message) {
                appendMessage(data.message);
            }
            if (bodyEl) {
                bodyEl.value = '';
                bodyEl.focus();
            }
        } catch {
            alert('Could not send message.');
        } finally {
            if (sendBtn) {
                sendBtn.disabled = false;
            }
        }
    });

    void poll();
    window.setInterval(() => void poll(), 2500);
}
