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
<body class="min-h-screen bg-[#0c1210] font-sans text-[#e8ebe4] antialiased">
    <nav class="border-b border-white/10 bg-[#141c18]/90 backdrop-blur">
        <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-4 px-4 py-3">
            <a href="{{ url('/') }}" class="font-display text-base font-semibold text-[#e8ebe4]">{{ config('app.name', 'Live Mix Audio') }}</a>
            <div class="flex flex-wrap items-center gap-3 text-sm">
                <a href="{{ route('discover') }}" class="text-zinc-400 hover:text-white">Discover</a>
                <a href="{{ route('archive.index') }}" class="text-zinc-400 hover:text-white">Archive</a>
                @auth
                    @if(auth()->user()->is_admin || auth()->user()->manageableOrganizations()->exists())
                        <a href="{{ route('admin.events.index') }}" class="text-zinc-400 hover:text-white">Events</a>
                        <a href="{{ route('admin.analytics.index') }}" class="text-zinc-400 hover:text-white">Analytics</a>
                        <a href="{{ route('admin.organizations.index') }}" class="text-zinc-400 hover:text-white">Channels</a>
                        <a href="{{ route('admin.streams.index') }}" class="text-zinc-400 hover:text-white">Streams</a>
                    @endif
                    <span class="text-zinc-500">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="text-amber-400 hover:text-amber-300">Log out</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="text-zinc-400 hover:text-white">Log in</a>
                    @if (config('app.registration_enabled') && Route::has('register'))
                        <a href="{{ route('register') }}" class="text-emerald-400 hover:text-emerald-300">Register</a>
                    @endif
                @endauth
            </div>
        </div>
    </nav>

    <main class="mx-auto max-w-5xl px-4 py-8">
        @if (session('status'))
            <div class="mb-6 rounded-lg border border-emerald-800 bg-emerald-950/50 px-4 py-3 text-sm text-emerald-200">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 rounded-lg border border-red-900 bg-red-950/40 px-4 py-3 text-sm text-red-200">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>
</body>
</html>
