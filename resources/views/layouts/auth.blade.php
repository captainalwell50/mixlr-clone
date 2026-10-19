<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#3d9b7a">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=fraunces:500,600|source-sans-3:400,500,600|open-sans:600,700" rel="stylesheet" />
    <title>@yield('title', 'Authentication') — {{ config('app.name', 'Sound Mix Live') }}</title>
    @include('partials.theme-head')
    @vite(['resources/css/app.css', 'resources/js/pwa.js'])
    @yield('vite')
</head>
<body class="auth-shell min-h-screen bg-[var(--app-bg)] text-[var(--app-text)] font-sans antialiased flex flex-col justify-between selection:bg-emerald-500/20 selection:text-emerald-700 dark:selection:text-emerald-300">
    {{-- Minimal Top Header --}}
    <header class="auth-header">
        <a href="{{ url('/') }}" class="auth-header-brand" aria-label="{{ config('app.name', 'Sound Mix Live') }} home">
            <img
                src="{{ asset('brand/soundmix-mark.png') }}?v=20260723d"
                alt=""
                width="34"
                height="34"
                class="auth-header-logo"
                decoding="async"
                aria-hidden="true"
            >
            <span class="auth-header-name font-display">
                <span>Sound Mix</span>
                <span class="auth-header-dot" aria-hidden="true"></span>
                <span class="auth-header-live">Live</span>
            </span>
        </a>

        <div class="auth-header-actions">
            @include('partials.theme-selector', ['mode' => 'compact'])
        </div>
    </header>

    {{-- Main Auth Section --}}
    <main class="auth-main">
        <div class="auth-container">
            <div class="auth-layout">
                {{-- Left Context / Brand Panel (Desktop >= 1024px) --}}
                <aside class="auth-brand-panel" aria-label="About SoundMix Live">
                    <div>
                        <div class="auth-brand-kicker">
                            <span class="inline-block h-2 w-2 rounded-full bg-emerald-500 animate-pulse" aria-hidden="true"></span>
                            <span>SoundMix Live Audio</span>
                        </div>

                        <h2 class="auth-brand-heading">
                            Broadcast live.<br>
                            Publish content.<br>
                            Engage your audience.
                        </h2>

                        <p class="auth-brand-copy">
                            Live audio, events, audience engagement, and digital content platform for creators, broadcasters, and organisations.
                        </p>

                        <div class="auth-brand-features">
                            <div class="auth-brand-feature-item">
                                <span class="auth-brand-feature-icon" aria-hidden="true">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                </span>
                                <span>Live audio broadcasting and web studio</span>
                            </div>

                            <div class="auth-brand-feature-item">
                                <span class="auth-brand-feature-icon" aria-hidden="true">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                </span>
                                <span>Private events and controlled access</span>
                            </div>

                            <div class="auth-brand-feature-item">
                                <span class="auth-brand-feature-icon" aria-hidden="true">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                </span>
                                <span>On-demand content archives and podcasts</span>
                            </div>

                            <div class="auth-brand-feature-item">
                                <span class="auth-brand-feature-icon" aria-hidden="true">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                </span>
                                <span>Real-time audience interaction and engagement</span>
                            </div>
                        </div>
                    </div>

                    <div class="auth-brand-trust">
                        <div class="h-8 w-8 rounded-full bg-emerald-500/10 text-[var(--app-accent)] flex items-center justify-center font-bold text-xs shrink-0 border border-emerald-500/20" aria-hidden="true">
                            SM
                        </div>
                        <div class="text-xs">
                            <p class="font-semibold text-[var(--app-text)]">Built for live audio and digital content</p>
                            <p class="text-[11px] text-[var(--app-text-muted)]">Reliable streaming infrastructure for global reach</p>
                        </div>
                    </div>
                </aside>

                {{-- Right Auth Form Card --}}
                <div class="auth-card">
                    {{-- Mobile Brand Lockup (< 1024px) --}}
                    <div class="lg:hidden mb-6 flex flex-col items-center text-center">
                        <a href="{{ url('/') }}" class="inline-flex items-center gap-2 mb-2 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 rounded-lg" aria-label="{{ config('app.name', 'Sound Mix Live') }} home">
                            <img
                                src="{{ asset('brand/soundmix-mark.png') }}?v=20260723d"
                                alt=""
                                width="36"
                                height="36"
                                class="h-9 w-9 rounded-xl shadow-sm shrink-0"
                                decoding="async"
                                aria-hidden="true"
                            >
                            <span class="inline-flex items-baseline gap-1 text-lg font-bold tracking-tight text-[var(--app-text)] font-display">
                                <span>Sound Mix</span>
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 mx-0.5 self-center" aria-hidden="true"></span>
                                <span class="font-normal text-[var(--app-text-muted)]">Live</span>
                            </span>
                        </a>
                    </div>

                    @yield('content')
                </div>
            </div>
        </div>
    </main>

    {{-- Minimal Footer --}}
    <footer class="auth-footer">
        <p>&copy; {{ date('Y') }} Sound Mix Live. All rights reserved.</p>
        <div class="auth-footer-links">
            <a href="{{ route('legal.terms') }}" class="auth-footer-link">Terms of Service</a>
            <span class="text-[var(--app-border-strong)]" aria-hidden="true">&middot;</span>
            <a href="{{ route('legal.privacy') }}" class="auth-footer-link">Privacy Policy</a>
            <span class="text-[var(--app-border-strong)]" aria-hidden="true">&middot;</span>
            <a href="{{ route('legal.support') }}" class="auth-footer-link">Support</a>
        </div>
    </footer>

    {{-- Floating WhatsApp Support Widget --}}
    <x-whatsapp-support :context="'public'" :pageLabel="trim($__env->yieldContent('title', ''))" />

    {{-- Accessible Password Visibility Toggle Helper --}}
    <script>
        function toggleAuthPassword(btn) {
            if (!btn) return;
            var wrap = btn.closest('.auth-input-password-wrap');
            if (!wrap) return;
            var input = wrap.querySelector('input');
            if (!input) return;

            var isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';

            var newLabel = isPassword ? 'Hide password' : 'Show password';
            btn.setAttribute('aria-label', newLabel);
            btn.setAttribute('title', newLabel);

            var eyeOpen = btn.querySelector('.auth-eye-open');
            var eyeClosed = btn.querySelector('.auth-eye-closed');
            if (eyeOpen && eyeClosed) {
                if (isPassword) {
                    eyeOpen.classList.add('hidden');
                    eyeClosed.classList.remove('hidden');
                } else {
                    eyeOpen.classList.remove('hidden');
                    eyeClosed.classList.add('hidden');
                }
            }
        }
    </script>
</body>
</html>
