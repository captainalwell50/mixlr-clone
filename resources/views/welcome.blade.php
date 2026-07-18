<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=fraunces:500,600|source-sans-3:400,500,600" rel="stylesheet" />
    @vite(['resources/css/app.css'])
    <style>
        :root {
            --welcome-bg: #0c0f12;
            --welcome-ink: #f4f1ea;
            --welcome-muted: #9aa3ad;
            --welcome-accent: #3d9b7a;
        }
        .welcome-shell {
            font-family: 'Source Sans 3', ui-sans-serif, system-ui, sans-serif;
            background:
                radial-gradient(ellipse 80% 60% at 20% 10%, rgba(61, 155, 122, 0.18), transparent 55%),
                radial-gradient(ellipse 70% 50% at 90% 80%, rgba(40, 60, 80, 0.45), transparent 50%),
                linear-gradient(165deg, #10161c 0%, var(--welcome-bg) 45%, #0a0d10 100%);
            color: var(--welcome-ink);
        }
        .welcome-brand {
            font-family: Fraunces, ui-serif, Georgia, serif;
            letter-spacing: -0.02em;
        }
        @keyframes welcome-rise {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes welcome-pulse {
            0%, 100% { opacity: 0.55; }
            50% { opacity: 1; }
        }
        .welcome-rise { animation: welcome-rise 0.7s ease-out both; }
        .welcome-rise-delay { animation: welcome-rise 0.7s ease-out 0.12s both; }
        .welcome-rise-delay-2 { animation: welcome-rise 0.7s ease-out 0.24s both; }
        .welcome-live-dot { animation: welcome-pulse 2.2s ease-in-out infinite; }
    </style>
</head>
<body class="welcome-shell min-h-screen antialiased">
    <div class="mx-auto flex min-h-screen max-w-3xl flex-col px-6 py-8 sm:px-10">
        <header class="flex items-center justify-between gap-4">
            <p class="welcome-brand text-xl font-semibold sm:text-2xl">{{ config('app.name') }}</p>
            <nav class="flex items-center gap-4 text-sm text-[var(--welcome-muted)]">
                <a href="{{ route('discover') }}" class="hover:text-white">Discover</a>
                <a href="{{ route('archive.index') }}" class="hover:text-white">Archive</a>
                @auth
                    <a href="{{ url('/dashboard') }}" class="hover:text-white">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="hover:text-white">Log in</a>
                @endauth
            </nav>
        </header>

        <main class="flex flex-1 flex-col justify-center py-16 sm:py-24">
            <p class="welcome-rise flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.2em] text-[var(--welcome-accent)]">
                <span class="welcome-live-dot inline-block h-1.5 w-1.5 rounded-full bg-[var(--welcome-accent)]"></span>
                Live audio for Sunday
            </p>
            <h1 class="welcome-brand welcome-rise-delay mt-4 max-w-xl text-4xl font-semibold leading-tight sm:text-5xl">
                {{ config('app.name') }}
            </h1>
            <p class="welcome-rise-delay-2 mt-5 max-w-md text-base leading-relaxed text-[var(--welcome-muted)] sm:text-lg">
                Channels, scheduled events, live chat, and installable web apps — share one event link that becomes your live page.
            </p>
            <div class="welcome-rise-delay-2 mt-10 flex flex-wrap gap-3">
                <a href="{{ route('discover') }}"
                    class="inline-flex rounded-lg bg-[var(--welcome-accent)] px-5 py-2.5 text-sm font-semibold text-white hover:brightness-110">
                    Discover live
                </a>
                @auth
                    @if(auth()->user()->is_admin || auth()->user()->manageableOrganizations()->exists())
                        <a href="{{ route('admin.streams.index') }}"
                            class="inline-flex rounded-lg border border-white/15 px-5 py-2.5 text-sm font-semibold text-white hover:bg-white/5">
                            Open streams
                        </a>
                    @endif
                @else
                    <a href="{{ route('login') }}"
                        class="inline-flex rounded-lg border border-white/15 px-5 py-2.5 text-sm font-semibold text-white hover:bg-white/5">
                        Log in
                    </a>
                @endauth
            </div>
        </main>

        <footer class="pb-4 text-xs text-[var(--welcome-muted)]">
            Listen links are shared per stream — ask your church admin for today’s URL.
        </footer>
    </div>
</body>
</html>
