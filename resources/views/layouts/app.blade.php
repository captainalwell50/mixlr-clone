<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#3d9b7a">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=fraunces:500,600|source-sans-3:400,500,600" rel="stylesheet" />
    <title>@yield('title', config('app.name', 'Live Mix Audio'))</title>
    @vite(['resources/css/app.css', 'resources/js/pwa.js'])
    @yield('vite')
</head>
<body class="min-h-screen bg-[var(--color-ink)] font-sans text-[var(--stage-cream)] antialiased">
    <nav class="app-nav">
        <div class="app-nav-inner">
            <a href="{{ url('/') }}" class="font-display text-base font-semibold text-[var(--stage-cream)]">
                {{ config('app.name', 'Live Mix Audio') }}
            </a>
            <div class="app-nav-links">
                <a href="{{ route('how-it-works') }}">How it works</a>
                <a href="{{ route('discover') }}">Discover</a>
                <a href="{{ route('archive.index') }}">Archive</a>
                @auth
                    @if(auth()->user()->is_admin || auth()->user()->manageableOrganizations()->exists())
                        <a href="{{ route('admin.events.index') }}">Events</a>
                        <a href="{{ route('admin.analytics.index') }}">Analytics</a>
                        <a href="{{ route('admin.organizations.index') }}">Channels</a>
                        <a href="{{ route('admin.streams.index') }}">Streams</a>
                    @endif
                    <span class="text-[var(--stage-muted)]">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="nav-warn">Log out</button>
                    </form>
                @else
                    <a href="{{ route('login') }}">Log in</a>
                    @if (config('app.registration_enabled') && Route::has('register'))
                        <a href="{{ route('register') }}" class="nav-accent">Register</a>
                    @endif
                @endauth
            </div>
        </div>
    </nav>

    <main class="@yield('main_class', 'console mx-auto max-w-5xl px-4 py-8')">
        @if (session('status'))
            <div class="mx-auto mb-6 max-w-5xl px-4 pt-2">
                <div class="rounded-lg border px-4 py-3 text-sm text-[var(--stage-cream)]"
                    style="border-color: color-mix(in srgb, var(--color-accent) 35%, transparent); background: color-mix(in srgb, var(--color-accent) 12%, transparent);">
                    {{ session('status') }}
                </div>
            </div>
        @endif

        @if ($errors->any())
            <div class="mx-auto mb-6 max-w-5xl px-4 pt-2">
                <div class="rounded-lg border border-red-900/60 bg-red-950/40 px-4 py-3 text-sm text-red-200">
                    <ul class="list-inside list-disc space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        @yield('content')
    </main>
</body>
</html>
