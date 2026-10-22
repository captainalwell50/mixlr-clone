<div id="listener-auth-sheet" class="listener-auth-sheet fixed inset-0 z-50 hidden transition-opacity duration-200" role="dialog" aria-modal="true" aria-labelledby="listener-sheet-title">
    {{-- Non-blocking backdrop (allows audio to continue uninterrupted) --}}
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" data-auth-dismiss aria-hidden="true"></div>

    {{-- Sheet Container: bottom sheet on mobile, centered modal on sm+ --}}
    <div class="fixed inset-x-0 bottom-0 sm:inset-0 sm:flex sm:items-center sm:justify-center p-0 sm:p-4 pointer-events-none">
        <div class="listener-auth-panel pointer-events-auto w-full sm:max-w-md bg-zinc-900 border border-zinc-800 text-zinc-100 rounded-t-2xl sm:rounded-2xl shadow-2xl p-6 sm:p-7 space-y-5">
            {{-- Header --}}
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 id="listener-sheet-title" class="text-base sm:text-lg font-bold text-white tracking-tight">
                        <span id="auth-title-default">Join the conversation</span>
                        <span id="auth-title-comment" class="hidden">Sign in to comment</span>
                    </h3>
                    <p id="listener-sheet-subtitle" class="text-xs text-zinc-400 mt-1 leading-relaxed">
                        <span id="auth-subtitle-default">Sign in to chat, heart, and follow.</span>
                        <span id="auth-subtitle-comment" class="hidden">Join the broadcast room to share your message.</span>
                    </p>
                </div>
                <button type="button" class="text-zinc-400 hover:text-white p-1 rounded-lg transition-colors" data-auth-dismiss aria-label="Close">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- State A: Known Identity Preview (from localStorage - visual convenience only) --}}
            <div id="auth-known-identity" class="hidden space-y-3">
                <a id="auth-known-google-btn" href="{{ route('auth.google', ['intent' => 'listener']) }}" class="flex items-center gap-3 w-full p-3 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-white font-medium transition-all group">
                    <img id="auth-known-avatar" src="" alt="" class="h-9 w-9 rounded-full object-cover border border-emerald-500/30 hidden">
                    <div id="auth-known-avatar-placeholder" class="h-9 w-9 rounded-full bg-emerald-500/20 text-emerald-400 font-bold flex items-center justify-center text-xs border border-emerald-500/30">?</div>
                    <div class="flex-1 text-left min-w-0">
                        <p class="text-xs font-semibold text-white truncate">
                            Continue as <span id="auth-known-name"></span>
                        </p>
                        <p id="auth-known-email" class="text-[11px] text-zinc-400 truncate"></p>
                    </div>
                    <svg class="h-4 w-4 text-zinc-400 group-hover:text-white transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>

                <div class="relative flex items-center justify-center">
                    <div class="border-t border-zinc-800 w-full"></div>
                    <span class="bg-zinc-900 px-3 text-[10px] uppercase tracking-wider text-zinc-500">or</span>
                </div>
            </div>

            {{-- Action Buttons --}}
            <div class="space-y-2.5">
                {{-- Continue with Google --}}
                <a id="btn-auth-google" href="{{ route('auth.google', ['intent' => 'listener']) }}" class="flex items-center justify-center gap-3 w-full py-2.5 px-4 rounded-xl bg-white hover:bg-zinc-100 text-zinc-900 font-semibold text-xs transition-colors shadow-sm">
                    <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" aria-hidden="true">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                    </svg>
                    <span>Continue with Google</span>
                </a>

                {{-- Continue with email --}}
                <a id="btn-auth-email" href="{{ route('login') }}" class="flex items-center justify-center gap-2.5 w-full py-2.5 px-4 rounded-xl bg-zinc-800 hover:bg-zinc-700/80 text-zinc-200 font-semibold text-xs border border-zinc-700 transition-colors">
                    <svg class="h-4 w-4 shrink-0 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/>
                    </svg>
                    <span>Continue with email</span>
                </a>
            </div>

            {{-- Guest option: simply closes sheet, keeps audio playing --}}
            <div class="pt-1 text-center">
                <button type="button" class="text-xs text-zinc-400 hover:text-white transition-colors" data-auth-dismiss>
                    Listen as guest
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        const sheet = document.getElementById('listener-auth-sheet');
        if (!sheet) return;

        const baseGoogleUrl = "{{ route('auth.google', ['intent' => 'listener']) }}";
        const baseLoginUrl = "{{ route('login') }}";

        const titleDefault = document.getElementById('auth-title-default');
        const titleComment = document.getElementById('auth-title-comment');
        const subtitleDefault = document.getElementById('auth-subtitle-default');
        const subtitleComment = document.getElementById('auth-subtitle-comment');

        const knownBlock = document.getElementById('auth-known-identity');
        const knownBtn = document.getElementById('auth-known-google-btn');
        const knownName = document.getElementById('auth-known-name');
        const knownEmail = document.getElementById('auth-known-email');
        const knownAvatar = document.getElementById('auth-known-avatar');
        const knownPlaceholder = document.getElementById('auth-known-avatar-placeholder');

        const googleBtn = document.getElementById('btn-auth-google');
        const emailBtn = document.getElementById('btn-auth-email');

        // Store known identity preview when user is authenticated
        @auth
            try {
                localStorage.setItem('soundmix_known_identity', JSON.stringify({
                    name: @json(auth()->user()->name),
                    email: @json(auth()->user()->email),
                    avatar: @json(auth()->user()->avatarUrl()),
                }));
            } catch (e) {}
        @endauth

        window.SoundmixAuth = {
            open: function (opts) {
                opts = opts || {};
                const reason = opts.reason || 'generic';
                const currentPath = window.location.pathname + window.location.search;
                const intended = opts.intended || currentPath;

                // Update Google OAuth target with intended path
                const googleTarget = baseGoogleUrl + '&intended=' + encodeURIComponent(intended);
                const emailTarget = baseLoginUrl + '?intended=' + encodeURIComponent(intended);

                if (googleBtn) googleBtn.href = googleTarget;
                if (emailBtn) emailBtn.href = emailTarget;
                if (knownBtn) knownBtn.href = googleTarget;

                // Check for client-side known identity preview (visual convenience only)
                let known = null;
                try {
                    const raw = localStorage.getItem('soundmix_known_identity');
                    if (raw) known = JSON.parse(raw);
                } catch (e) {}

                if (known && known.name) {
                    if (knownName) knownName.textContent = known.name;
                    if (knownEmail) knownEmail.textContent = known.email || '';
                    if (known.avatar && knownAvatar) {
                        knownAvatar.src = known.avatar;
                        knownAvatar.classList.remove('hidden');
                        if (knownPlaceholder) knownPlaceholder.classList.add('hidden');
                    } else if (knownPlaceholder) {
                        knownPlaceholder.textContent = (known.name.slice(0, 1) || '?').toUpperCase();
                        knownPlaceholder.classList.remove('hidden');
                        if (knownAvatar) knownAvatar.classList.add('hidden');
                    }
                    if (knownBlock) knownBlock.classList.remove('hidden');

                    if (reason === 'chat') {
                        if (titleDefault) titleDefault.classList.add('hidden');
                        if (titleComment) {
                            titleComment.textContent = 'Comment as ' + known.name;
                            titleComment.classList.remove('hidden');
                        }
                    }
                } else {
                    if (knownBlock) knownBlock.classList.add('hidden');
                    if (reason === 'chat') {
                        if (titleDefault) titleDefault.classList.add('hidden');
                        if (titleComment) {
                            titleComment.textContent = 'Sign in to comment';
                            titleComment.classList.remove('hidden');
                        }
                    }
                }

                if (reason === 'chat') {
                    if (subtitleDefault) subtitleDefault.classList.add('hidden');
                    if (subtitleComment) subtitleComment.classList.remove('hidden');
                } else {
                    if (titleDefault) titleDefault.classList.remove('hidden');
                    if (titleComment) titleComment.classList.add('hidden');
                    if (subtitleDefault) subtitleDefault.classList.remove('hidden');
                    if (subtitleComment) subtitleComment.classList.add('hidden');
                }

                sheet.classList.remove('hidden');
            },

            close: function () {
                sheet.classList.add('hidden');
            }
        };

        // Dismiss handlers
        sheet.querySelectorAll('[data-auth-dismiss]').forEach(function (el) {
            el.addEventListener('click', function () {
                window.SoundmixAuth.close();
            });
        });

        // Restore pending draft comment after returning from OAuth
        @auth
            try {
                const pendingRaw = sessionStorage.getItem('soundmix_pending_action');
                if (pendingRaw) {
                    const pending = JSON.parse(pendingRaw);
                    if (pending && pending.type === 'chat' && pending.body) {
                        const input = document.getElementById('chat-body');
                        if (input) {
                            input.value = pending.body;
                            input.focus();
                        }
                    }
                    sessionStorage.removeItem('soundmix_pending_action');
                }
            } catch (e) {}
        @endauth
    })();
</script>
