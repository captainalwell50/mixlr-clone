<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0c1210">
    <title>@yield('title', $creatorOrg?->name ?? 'Creator Console') · {{ config('app.name', 'Sound Mix Live') }}</title>
    @include('partials.theme-head')
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=source-sans-3:400,500,600,700|jet-brains-mono:400,500" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/pwa.js'])
    @yield('vite')
    <style>
        .creator-sidebar-scroll {
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.12) transparent;
        }
        .creator-sidebar-scroll::-webkit-scrollbar {
            width: 4px;
        }
        .creator-sidebar-scroll::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.12);
            border-radius: 4px;
        }
    </style>
</head>
<body class="h-full bg-zinc-950 font-sans text-zinc-100 antialiased selection:bg-emerald-500 selection:text-white creator-shell app-shell">
    <!-- Mobile sidebar backdrop -->
    <div
        id="mobile-creator-backdrop"
        class="fixed inset-0 z-40 bg-black/80 backdrop-blur-sm transition-opacity duration-200 lg:hidden hidden"
        aria-hidden="true"
        onclick="closeCreatorSidebar()"
    ></div>

    <div class="flex min-h-screen flex-col">
        <!-- TOP BAR -->
        <header class="sticky top-0 z-30 flex h-16 shrink-0 items-center justify-between border-b border-zinc-800/80 bg-zinc-900/95 px-4 backdrop-blur sm:px-6 lg:px-8">
            <div class="flex items-center gap-3 lg:gap-4">
                <!-- Mobile toggle button -->
                <button
                    type="button"
                    id="mobile-creator-sidebar-toggle"
                    onclick="toggleCreatorSidebar()"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-zinc-700 bg-zinc-800 text-zinc-300 hover:bg-zinc-700 hover:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500 lg:hidden"
                    aria-label="Toggle navigation menu"
                    aria-expanded="false"
                    aria-controls="creator-sidebar"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>

                <!-- Channel Brand & Org Switcher Lockup -->
                @if ($creatorOrg)
                    @if ($userOrganizations && $userOrganizations->count() > 1)
                        <details class="relative group">
                            <summary class="list-none cursor-pointer flex items-center gap-2.5 rounded-lg border border-zinc-800 bg-zinc-900/80 px-2.5 py-1.5 transition hover:border-zinc-700 hover:bg-zinc-800/80 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                <div class="relative flex h-7 w-7 shrink-0 items-center justify-center rounded-md overflow-hidden bg-emerald-950 text-emerald-400 font-bold text-xs ring-1 ring-emerald-700/60">
                                    @if ($creatorOrg->logoUrl())
                                        <img src="{{ $creatorOrg->logoUrl() }}" alt="" class="h-full w-full object-cover">
                                    @else
                                        {{ strtoupper(substr($creatorOrg->name, 0, 1)) }}
                                    @endif
                                </div>
                                <div class="flex flex-col text-left min-w-0">
                                    <div class="flex items-center gap-1 min-w-0">
                                        <span class="text-xs font-semibold text-white tracking-tight truncate max-w-[120px] sm:max-w-[200px]">
                                            {{ $creatorOrg->name }}
                                        </span>
                                        <svg class="h-3 w-3 text-zinc-400 shrink-0 transition-transform group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                        </svg>
                                    </div>
                                    <span class="text-[10px] text-zinc-400 capitalize truncate max-w-[120px] sm:max-w-[200px]">
                                        {{ $creatorRole?->value ?? 'Member' }}
                                    </span>
                                </div>
                            </summary>

                            <div class="absolute left-0 mt-2 w-64 origin-top-left rounded-xl border border-zinc-800 bg-zinc-900 py-1.5 shadow-xl ring-1 ring-black/50 z-50">
                                <div class="px-3 py-1.5 border-b border-zinc-800 text-[10px] font-semibold uppercase tracking-wider text-zinc-400">
                                    Switch Organization
                                </div>
                                <div class="max-h-60 overflow-y-auto creator-sidebar-scroll py-0.5">
                                    @foreach ($userOrganizations as $org)
                                        <a
                                            href="{{ route('creator.home', ['org' => $org->slug]) }}"
                                            class="flex items-center justify-between px-3 py-2 text-xs transition {{ $org->id === $creatorOrg->id ? 'bg-emerald-950/50 text-emerald-300 font-medium' : 'text-zinc-300 hover:bg-zinc-800 hover:text-white' }}"
                                        >
                                            <div class="flex items-center gap-2 truncate">
                                                <div class="h-5 w-5 rounded bg-zinc-800 flex items-center justify-center text-[10px] text-zinc-300 font-bold shrink-0">
                                                    @if ($org->logoUrl())
                                                        <img src="{{ $org->logoUrl() }}" alt="" class="h-full w-full object-cover rounded">
                                                    @else
                                                        {{ strtoupper(substr($org->name, 0, 1)) }}
                                                    @endif
                                                </div>
                                                <span class="truncate">{{ $org->name }}</span>
                                            </div>
                                            @if ($org->id === $creatorOrg->id)
                                                <span class="text-emerald-400 text-xs">✓</span>
                                            @endif
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        </details>
                    @else
                        <div class="flex items-center gap-2.5">
                            <div class="relative flex h-8 w-8 shrink-0 items-center justify-center rounded-lg overflow-hidden bg-emerald-950 text-emerald-400 font-bold text-xs ring-1 ring-emerald-700/60 shadow-inner">
                                @if ($creatorOrg->logoUrl())
                                    <img src="{{ $creatorOrg->logoUrl() }}" alt="" class="h-full w-full object-cover">
                                @else
                                    {{ strtoupper(substr($creatorOrg->name, 0, 1)) }}
                                @endif
                            </div>
                            <div class="flex flex-col min-w-0">
                                <span class="text-sm font-semibold tracking-tight text-white truncate max-w-[130px] sm:max-w-[220px]">
                                    {{ $creatorOrg->name }}
                                </span>
                                <span class="text-[10px] text-zinc-400 leading-tight truncate max-w-[130px] sm:max-w-[220px]">
                                    {{ $creatorOrg->city ?: $creatorOrg->slug }} · {{ ucfirst($creatorRole?->value ?? 'Creator') }}
                                </span>
                            </div>
                        </div>
                    @endif
                @else
                    <a href="{{ route('creator.home') }}" class="flex items-center gap-2 text-sm font-semibold text-white">
                        <span>Creator Console</span>
                    </a>
                @endif
            </div>

            <!-- Middle: Live status indicator -->
            <div class="hidden sm:flex items-center">
                @if ($creatorIsLive)
                    <a
                        href="{{ $creatorStudioUrl ?? ($creatorOrg ? $creatorOrg->channelUrl() : '#') }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-2 rounded-full border border-red-500/40 bg-red-950/60 px-3 py-1 text-xs font-semibold text-red-300 hover:bg-red-900/60 transition shadow-sm"
                        title="Broadcast is currently LIVE — Click to open Studio"
                    >
                        <span class="relative flex h-2 w-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-red-500"></span>
                        </span>
                        <span>ON AIR</span>
                        <span class="text-zinc-600">·</span>
                        <span class="text-red-200 font-mono">{{ $creatorActiveListeners }} {{ \Illuminate\Support\Str::plural('listener', $creatorActiveListeners) }}</span>
                    </a>
                @else
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-zinc-800 bg-zinc-900/60 px-2.5 py-1 text-xs text-zinc-400">
                        <span class="h-1.5 w-1.5 rounded-full bg-zinc-600"></span>
                        <span>Offline</span>
                    </span>
                @endif
            </div>

            <!-- Right Controls: Studio action & User Account menu -->
            <div class="flex items-center gap-3">
                @if ($creatorStudioUrl)
                    @if ($creatorCanBroadcast)
                        <a
                            href="{{ $creatorStudioUrl }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="hidden sm:inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3.5 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-emerald-500 transition focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 0 0 6-6v-1.5m-6 7.5a6 6 0 0 1-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 0 1-3-3V4.5a3 3 0 1 1 6 0v8.25a3 3 0 0 1-3 3Z" />
                            </svg>
                            <span>Open Studio</span>
                        </a>
                    @elseif ($creatorIsOwner)
                        <a
                            href="{{ route('billing.plans') }}"
                            class="hidden sm:inline-flex items-center gap-1.5 rounded-lg bg-amber-600 px-3.5 py-1.5 text-xs font-semibold text-white hover:bg-amber-500 transition"
                        >
                            <span>Upgrade</span>
                        </a>
                    @endif
                @endif

                <!-- User account menu -->
                <details class="relative">
                    <summary class="list-none cursor-pointer flex items-center gap-2 rounded-lg border border-zinc-800 bg-zinc-900/80 p-1.5 transition hover:bg-zinc-800 text-zinc-300 hover:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        <div class="h-7 w-7 rounded-md bg-zinc-800 flex items-center justify-center text-xs font-semibold text-zinc-300">
                            {{ strtoupper(substr(auth()->user()?->name ?? 'U', 0, 1)) }}
                        </div>
                        <span class="hidden md:block text-xs font-medium max-w-[120px] truncate">
                            {{ auth()->user()?->name }}
                        </span>
                        <svg class="h-3.5 w-3.5 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                        </svg>
                    </summary>

                    <div class="absolute right-0 mt-2 w-56 origin-top-right rounded-xl border border-zinc-800 bg-zinc-900 py-1.5 shadow-xl ring-1 ring-black/50 z-50">
                        <div class="px-3 py-2 border-b border-zinc-800">
                            <p class="text-xs font-semibold text-white truncate">{{ auth()->user()?->name }}</p>
                            <p class="text-[11px] text-zinc-400 truncate">{{ auth()->user()?->email }}</p>
                        </div>
                        <div class="px-3 py-2 border-b border-zinc-800 flex items-center justify-between">
                            <span class="text-[11px] font-medium text-zinc-400">Theme</span>
                            @include('partials.theme-selector', ['mode' => 'compact'])
                        </div>
                        <div class="py-1">
                            @if ($creatorOrg)
                                <a href="{{ $creatorOrg->channelUrl() }}" target="_blank" class="flex items-center justify-between px-3 py-1.5 text-xs text-zinc-300 hover:bg-zinc-800 hover:text-white">
                                    <span>Public Channel</span>
                                    <span class="text-zinc-500 text-[10px]">↗</span>
                                </a>
                            @endif
                            <a href="{{ route('my-library') }}" class="flex items-center px-3 py-1.5 text-xs text-zinc-300 hover:bg-zinc-800 hover:text-white">
                                My Library
                            </a>
                            <a href="{{ route('account.edit') }}" class="flex items-center px-3 py-1.5 text-xs text-zinc-300 hover:bg-zinc-800 hover:text-white">
                                Account Profile
                            </a>
                            @if (auth()->user()?->isAdmin())
                                <a href="{{ route('admin.dashboard') }}" class="flex items-center px-3 py-1.5 text-xs text-emerald-400 hover:bg-zinc-800">
                                    Super Admin Console
                                </a>
                            @endif
                        </div>
                        <div class="border-t border-zinc-800 pt-1">
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="w-full text-left px-3 py-1.5 text-xs text-red-400 hover:bg-zinc-800 hover:text-red-300">
                                    Sign out
                                </button>
                            </form>
                        </div>
                    </div>
                </details>
            </div>
        </header>

        <div class="flex flex-1">
            <!-- SIDEBAR -->
            <aside
                id="creator-sidebar"
                class="fixed inset-y-0 left-0 z-40 w-64 -translate-x-full border-r border-zinc-800/80 bg-zinc-950 p-4 transition-transform duration-200 ease-in-out lg:static lg:translate-x-0 lg:flex lg:flex-col lg:justify-between shrink-0"
                aria-label="Creator Navigation"
            >
                <!-- Navigation Items -->
                <nav class="space-y-6 creator-sidebar-scroll overflow-y-auto pr-1">
                    <!-- OVERVIEW -->
                    <div>
                        <div class="px-2 mb-2 text-[10px] font-bold uppercase tracking-wider text-zinc-500">
                            Overview
                        </div>
                        <div class="space-y-0.5">
                            <a
                                href="{{ route('creator.home') }}"
                                class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-xs font-medium transition {{ request()->routeIs('creator.home') ? 'bg-emerald-950/60 text-emerald-300 border-l-2 border-emerald-500 font-semibold' : 'text-zinc-400 hover:bg-zinc-900/80 hover:text-zinc-200' }}"
                            >
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                                </svg>
                                <span>Dashboard</span>
                            </a>
                        </div>
                    </div>

                    <!-- BROADCAST -->
                    <div>
                        <div class="px-2 mb-2 text-[10px] font-bold uppercase tracking-wider text-zinc-500">
                            Broadcast
                        </div>
                        <div class="space-y-0.5">
                            @if ($creatorStudioUrl)
                                <a
                                    href="{{ $creatorStudioUrl }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-xs font-medium transition text-zinc-400 hover:bg-zinc-900/80 hover:text-zinc-200"
                                >
                                    <svg class="h-4 w-4 shrink-0 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 0 0 6-6v-1.5m-6 7.5a6 6 0 0 1-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 0 1-3-3V4.5a3 3 0 1 1 6 0v8.25a3 3 0 0 1-3 3Z" />
                                    </svg>
                                    <span class="flex-1">Web Studio</span>
                                    <svg class="h-3 w-3 text-zinc-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                    </svg>
                                    @if ($creatorIsLive)
                                        <span class="h-2 w-2 rounded-full bg-red-500 animate-pulse"></span>
                                    @endif
                                </a>
                            @endif

                            <a
                                href="{{ route('admin.events.index') }}"
                                class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-xs font-medium transition {{ request()->routeIs('admin.events.*') ? 'bg-emerald-950/60 text-emerald-300 border-l-2 border-emerald-500 font-semibold' : 'text-zinc-400 hover:bg-zinc-900/80 hover:text-zinc-200' }}"
                            >
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 9v7.5" />
                                </svg>
                                <span>Events & Schedule</span>
                            </a>

                            <a
                                href="{{ route('admin.streams.index') }}"
                                class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-xs font-medium transition {{ request()->routeIs('admin.streams.*') ? 'bg-emerald-950/60 text-emerald-300 border-l-2 border-emerald-500 font-semibold' : 'text-zinc-400 hover:bg-zinc-900/80 hover:text-zinc-200' }}"
                            >
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.348 14.652a3.75 3.75 0 0 1 0-5.304m5.304 0a3.75 3.75 0 0 1 0 5.304m-7.425 2.121a6.75 6.75 0 0 1 0-9.546m9.546 0a6.75 6.75 0 0 1 0 9.546M5.106 18.894c-3.808-3.807-3.808-9.98 0-13.788m13.788 0c3.808 3.807 3.808 9.98 0 13.788M12 12h.008v.008H12V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                </svg>
                                <span>Streams</span>
                            </a>
                        </div>
                    </div>

                    <!-- AUDIENCE -->
                    <div>
                        <div class="px-2 mb-2 text-[10px] font-bold uppercase tracking-wider text-zinc-500">
                            Audience
                        </div>
                        <div class="space-y-0.5">
                            <a
                                href="{{ route('admin.analytics.index') }}"
                                class="flex items-center justify-between rounded-lg px-2.5 py-2 text-xs font-medium transition {{ request()->routeIs('admin.analytics.*') ? 'bg-emerald-950/60 text-emerald-300 border-l-2 border-emerald-500 font-semibold' : 'text-zinc-400 hover:bg-zinc-900/80 hover:text-zinc-200' }}"
                            >
                                <div class="flex items-center gap-2.5">
                                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.325 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                                    </svg>
                                    <span>Analytics</span>
                                </div>
                                @if ($creatorOrg && ! $creatorOrg->hasFeature('listener_analytics'))
                                    <span class="rounded bg-zinc-800 px-1.5 py-0.5 text-[10px] font-semibold text-zinc-400 uppercase">Pro</span>
                                @endif
                            </a>
                        </div>
                    </div>

                    <!-- CONTENT -->
                    <div>
                        <div class="px-2 mb-2 text-[10px] font-bold uppercase tracking-wider text-zinc-500">
                            Content
                        </div>
                        <div class="space-y-0.5">
                            <a
                                href="{{ route('archive.index') }}"
                                class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-xs font-medium transition {{ request()->routeIs('archive.*') ? 'bg-emerald-950/60 text-emerald-300 border-l-2 border-emerald-500 font-semibold' : 'text-zinc-400 hover:bg-zinc-900/80 hover:text-zinc-200' }}"
                            >
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m9 9 10.5-3m0 6.553v3.75a2.25 2.25 0 0 1-1.632 2.163l-1.32.377a1.803 1.803 0 1 1-.99-3.467l2.31-.66a.75.75 0 0 0 .582-.726V9.66a.75.75 0 0 0-.54-.723L9.75 6.375A.75.75 0 0 0 9 7.098v9.704a2.25 2.25 0 0 1-1.632 2.163l-1.32.377a1.803 1.803 0 1 1-.99-3.467l2.31-.66a.75.75 0 0 0 .582-.726V9Z" />
                                </svg>
                                <span>Podcasts & Archive</span>
                            </a>

                            @if ($creatorOrg && ($creatorCanManage || $creatorOrg->digitalLibrarySeller))
                                <a
                                    href="{{ route('digital-library.sell.show', $creatorOrg) }}"
                                    class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-xs font-medium transition {{ request()->routeIs('digital-library.sell.*') ? 'bg-emerald-950/60 text-emerald-300 border-l-2 border-emerald-500 font-semibold' : 'text-zinc-400 hover:bg-zinc-900/80 hover:text-zinc-200' }}"
                                >
                                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                                    </svg>
                                    <span>Digital Library</span>
                                </a>
                            @endif
                        </div>
                    </div>

                    <!-- ORGANISATION -->
                    @if ($creatorOrg && $creatorCanManage)
                        <div>
                            <div class="px-2 mb-2 text-[10px] font-bold uppercase tracking-wider text-zinc-500">
                                Organisation
                            </div>
                            <div class="space-y-0.5">
                                <a
                                    href="{{ route('admin.organizations.customise', $creatorOrg) }}"
                                    class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-xs font-medium transition {{ request()->routeIs('admin.organizations.customise') ? 'bg-emerald-950/60 text-emerald-300 border-l-2 border-emerald-500 font-semibold' : 'text-zinc-400 hover:bg-zinc-900/80 hover:text-zinc-200' }}"
                                >
                                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.53 16.122a3 3 0 0 0-5.78 1.128 2.25 2.25 0 0 1-2.4 2.245 4.5 4.5 0 0 0 8.4-2.245c0-.399-.078-.78-.22-1.128Zm0 0a15.998 15.998 0 0 0 3.388-1.62m-5.043-.025a15.994 15.994 0 0 1 1.622-3.39m3.42 3.42a15.995 15.995 0 0 0 4.764-4.648l3.876-5.814a1.151 1.151 0 0 0-1.597-1.597L14.146 6.32a15.996 15.996 0 0 0-4.649 4.763m3.42 3.42a6.776 6.776 0 0 0-3.42-3.42" />
                                    </svg>
                                    <span>Branding & Channel</span>
                                </a>

                                <a
                                    href="{{ route('admin.organizations.members', $creatorOrg) }}"
                                    class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-xs font-medium transition {{ request()->routeIs('admin.organizations.members') ? 'bg-emerald-950/60 text-emerald-300 border-l-2 border-emerald-500 font-semibold' : 'text-zinc-400 hover:bg-zinc-900/80 hover:text-zinc-200' }}"
                                >
                                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                                    </svg>
                                    <span>Team & Members</span>
                                </a>
                            </div>
                        </div>
                    @endif

                    <!-- ACCOUNT & BILLING -->
                    <div>
                        <div class="px-2 mb-2 text-[10px] font-bold uppercase tracking-wider text-zinc-500">
                            Account
                        </div>
                        <div class="space-y-0.5">
                            @if ($creatorIsOwner)
                                <a
                                    href="{{ route('billing.plans') }}"
                                    class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-xs font-medium transition {{ request()->routeIs('billing.*') ? 'bg-emerald-950/60 text-emerald-300 border-l-2 border-emerald-500 font-semibold' : 'text-zinc-400 hover:bg-zinc-900/80 hover:text-zinc-200' }}"
                                >
                                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
                                    </svg>
                                    <span>Subscription & Plan</span>
                                </a>
                            @endif

                            <a
                                href="{{ route('account.edit') }}"
                                class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-xs font-medium transition {{ request()->routeIs('account.*') ? 'bg-emerald-950/60 text-emerald-300 border-l-2 border-emerald-500 font-semibold' : 'text-zinc-400 hover:bg-zinc-900/80 hover:text-zinc-200' }}"
                            >
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                                <span>Profile & Security</span>
                            </a>
                        </div>
                    </div>
                </nav>

                <!-- Sidebar Footer Links -->
                <div class="mt-6 pt-4 border-t border-zinc-800/80 space-y-2">
                    @if ($creatorOrg)
                        <a
                            href="{{ $creatorOrg->channelUrl() }}"
                            target="_blank"
                            class="flex items-center justify-between rounded-lg px-2.5 py-1.5 text-xs text-zinc-400 hover:bg-zinc-900 hover:text-zinc-200 transition"
                        >
                            <span>Public Channel</span>
                            <span class="text-[10px] text-zinc-500">↗</span>
                        </a>
                    @endif
                    <a
                        href="{{ route('discover') }}"
                        class="flex items-center justify-between rounded-lg px-2.5 py-1.5 text-xs text-zinc-400 hover:bg-zinc-900 hover:text-zinc-200 transition"
                    >
                        <span>Discover Channels</span>
                        <span class="text-[10px] text-zinc-500">↗</span>
                    </a>
                </div>
            </aside>

            <!-- MAIN CONTENT -->
            <main class="flex-1 min-w-0 bg-zinc-950 p-4 sm:p-6 lg:p-8 @yield('main_class')">
                <!-- Flash Status -->
                @if (session('status'))
                    <div class="mb-6 rounded-xl border border-emerald-500/40 bg-emerald-950/20 px-4 py-3 text-sm text-emerald-200 flex items-center gap-2">
                        <svg class="h-4 w-4 shrink-0 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                        <span>{{ session('status') }}</span>
                    </div>
                @endif

                <!-- Flash Error -->
                @if (session('error'))
                    <div class="mb-6 rounded-xl border border-red-500/40 bg-red-950/20 px-4 py-3 text-sm text-red-200 flex items-center gap-2">
                        <svg class="h-4 w-4 shrink-0 text-red-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                        </svg>
                        <span>{{ session('error') }}</span>
                    </div>
                @endif

                <!-- Validation Errors -->
                @if ($errors->any())
                    <div class="mb-6 rounded-xl border border-red-500/40 bg-red-950/30 px-4 py-3 text-sm text-red-200">
                        <p class="font-semibold text-xs uppercase tracking-wide text-red-300 mb-1">Please fix the following issues:</p>
                        <ul class="list-disc list-inside space-y-1 text-xs">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    <!-- Mobile Drawer Script -->
    <script>
        function toggleCreatorSidebar() {
            const sidebar = document.getElementById('creator-sidebar');
            const backdrop = document.getElementById('mobile-creator-backdrop');
            const toggle = document.getElementById('mobile-creator-sidebar-toggle');
            const isOpen = !sidebar.classList.contains('-translate-x-full');

            if (isOpen) {
                closeCreatorSidebar();
            } else {
                sidebar.classList.remove('-translate-x-full');
                backdrop.classList.remove('hidden');
                if (toggle) toggle.setAttribute('aria-expanded', 'true');
                document.body.style.overflow = 'hidden';
            }
        }

        function closeCreatorSidebar() {
            const sidebar = document.getElementById('creator-sidebar');
            const backdrop = document.getElementById('mobile-creator-backdrop');
            const toggle = document.getElementById('mobile-creator-sidebar-toggle');

            sidebar.classList.add('-translate-x-full');
            backdrop.classList.add('hidden');
            if (toggle) toggle.setAttribute('aria-expanded', 'false');
            document.body.style.overflow = '';
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeCreatorSidebar();
            }
        });
    </script>

    @hasSection('whatsapp_widget')
        @yield('whatsapp_widget')
    @else
        <x-whatsapp-support
            context="creator"
            :organisation="$creatorOrg?->name"
            :pageLabel="trim($__env->yieldContent('title', 'Creator Dashboard'))"
        />
    @endif
</body>
</html>
