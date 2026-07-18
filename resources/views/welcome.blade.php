<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Live Mix Audio') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=fraunces:500,600|source-sans-3:400,500,600" rel="stylesheet" />
    @vite(['resources/css/app.css'])
</head>
<body class="stage-body min-h-screen">
    <div class="stage">
        <div class="stage-atmosphere"></div>
        <div class="relative z-10 mx-auto flex min-h-screen max-w-3xl flex-col px-6 py-8 sm:px-10">
        <header class="flex items-center justify-between gap-4">
            <p class="font-display text-xl font-semibold sm:text-2xl">{{ config('app.name', 'Live Mix Audio') }}</p>
            <nav class="flex items-center gap-4 text-sm text-[var(--stage-muted)]">
                <a href="{{ route('discover') }}" class="hover:text-[var(--stage-cream)]">Discover</a>
                <a href="{{ route('archive.index') }}" class="hover:text-[var(--stage-cream)]">Archive</a>
                @auth
                    <a href="{{ url('/dashboard') }}" class="hover:text-[var(--stage-cream)]">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="hover:text-[var(--stage-cream)]">Log in</a>
                @endauth
            </nav>
        </header>

        <main class="flex flex-1 flex-col justify-center py-16 sm:py-24">
            <p class="stage-rise flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.2em] text-[var(--stage-accent)]">
                <span class="live-dot inline-block h-1.5 w-1.5 rounded-full bg-current"></span>
                Live audio, mixed clean
            </p>
            <h1 class="font-display stage-rise-delay mt-4 max-w-xl text-4xl font-semibold leading-tight sm:text-5xl">
                {{ config('app.name', 'Live Mix Audio') }}
            </h1>
            <p class="stage-rise-delay-2 mt-5 max-w-md text-base leading-relaxed text-[var(--stage-muted)] sm:text-lg">
                Share one event link. It becomes your live stage — with chat, hearts, and an installable listen experience.
            </p>
            <div class="stage-rise-delay-2 mt-10 flex flex-wrap gap-3">
                <a href="{{ route('discover') }}"
                    class="inline-flex rounded-lg bg-[var(--stage-accent)] px-5 py-2.5 text-sm font-semibold text-white hover:brightness-110">
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

        <footer class="pb-4 text-xs text-[var(--stage-muted)]">
            Live Mix Audio — channels, events, and a stage made for listening.
        </footer>
        </div>
    </div>
</body>
</html>
