import './bootstrap';

const root = document.getElementById('chat-root');
if (root) {
    const listEl = document.getElementById('chat-messages');
    const formEl = document.getElementById('chat-form');
    const bodyEl = document.getElementById('chat-body');
    const pollUrl = root.dataset.pollUrl;
    const postUrl = root.dataset.postUrl;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    let lastId = 0;

    function appendMessage(msg) {
        if (!listEl || msg.id <= lastId) {
            return;
        }
        lastId = Math.max(lastId, msg.id);
        const row = document.createElement('div');
        row.className = 'text-sm';
        row.innerHTML = `<span class="font-medium text-emerald-300/90"></span><span class="text-zinc-500"> · </span><span class="text-zinc-200"></span>`;
        row.children[0].textContent = msg.name;
        row.children[2].textContent = msg.body;
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
                listEl.innerHTML = '<p class="text-sm text-zinc-500">Chat is off for this event.</p>';
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
            }
        } catch {
            alert('Could not send message.');
        }
    });

    void poll();
    window.setInterval(() => void poll(), 2500);
}
