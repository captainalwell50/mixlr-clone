<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-zinc-950">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0c1210">
    <title>@yield('title', 'Super Admin') · {{ config('app.name', 'Sound Mix Live') }} Console</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=source-sans-3:400,500,600,700|jet-brains-mono:400,500" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/pwa.js'])
    @yield('vite')
    <style>
        /* Admin shell specific styling enhancements */
        .admin-sidebar-scroll {
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.12) transparent;
        }
        .admin-sidebar-scroll::-webkit-scrollbar {
            width: 4px;
        }
        .admin-sidebar-scroll::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.12);
            border-radius: 4px;
        }
    </style>
</head>
<body class="h-full bg-zinc-950 font-sans text-zinc-100 antialiased selection:bg-emerald-500 selection:text-white" x-data="{ sidebarOpen: false }">
    @php
        $attentionService = app(\App\Services\AdminAttentionService::class);
        $attentionCount = $attentionService->totalAttentionCount();
        $user = auth()->user();
    @endphp

    <!-- Mobile sidebar backdrop -->
    <div
        id="mobile-backdrop"
        class="fixed inset-0 z-40 bg-black/80 backdrop-blur-sm transition-opacity duration-200 lg:hidden hidden"
        aria-hidden="true"
        onclick="closeAdminSidebar()"
    ></div>

    <div class="flex min-h-screen flex-col">
        <!-- TOP BAR -->
        <header class="sticky top-0 z-30 flex h-16 shrink-0 items-center justify-between border-b border-zinc-800/80 bg-zinc-900/95 px-4 backdrop-blur sm:px-6 lg:px-8">
            <div class="flex items-center gap-3 lg:gap-4">
                <!-- Mobile toggle button -->
                <button
                    type="button"
                    id="mobile-sidebar-toggle"
                    onclick="toggleAdminSidebar()"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-zinc-700 bg-zinc-800 text-zinc-300 hover:bg-zinc-700 hover:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500 lg:hidden"
                    aria-label="Toggle navigation menu"
                    aria-expanded="false"
                    aria-controls="admin-sidebar"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>

                <!-- Admin Brand Lockup -->
                @include('admin.partials.brand')
            </div>

            <!-- Top bar central: Global Admin Search -->
            <div class="hidden md:flex flex-1 max-w-md mx-6">
                <form action="{{ route('admin.search') }}" method="GET" class="w-full relative" role="search">
                    <label for="admin-search-input" class="sr-only">Search console</label>
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-zinc-500">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                    </div>
                    <input
                        id="admin-search-input"
                        type="search"
                        name="q"
                        value="{{ request('q') }}"
                        placeholder="Search streams, channels, users, library..."
                        class="w-full rounded-lg border border-zinc-700/80 bg-zinc-950/70 py-1.5 pl-9 pr-3 text-xs text-zinc-100 placeholder-zinc-500 transition focus:border-emerald-500 focus:bg-zinc-900 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                    />
                </form>
            </div>

            <!-- Top bar right controls -->
            <div class="flex items-center gap-3">
                <!-- Attention Center Pill -->
                <a
                    href="{{ route('admin.dashboard') }}#needs-attention"
                    class="relative inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1 text-xs font-medium transition {{ $attentionCount > 0 ? 'border-amber-700/60 bg-amber-950/40 text-amber-300 hover:bg-amber-900/40' : 'border-zinc-800 bg-zinc-900/60 text-zinc-400 hover:text-zinc-300' }}"
                    title="{{ $attentionCount > 0 ? $attentionCount . ' item(s) need attention' : 'No items need attention' }}"
                >
                    <span class="relative flex h-2 w-2">
                        @if ($attentionCount > 0)
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                        @endif
                        <span class="relative inline-flex rounded-full h-2 w-2 {{ $attentionCount > 0 ? 'bg-amber-400' : 'bg-emerald-500' }}"></span>
                    </span>
                    <span class="hidden sm:inline">Attention</span>
                    @if ($attentionCount > 0)
                        <span class="ml-0.5 rounded-full bg-amber-500/20 px-1.5 py-0.2 text-[10px] font-bold text-amber-200">{{ $attentionCount }}</span>
                    @endif
                </a>

                <!-- Public site link -->
                <a
                    href="{{ route('how-it-works') }}"
                    target="_blank"
                    class="hidden sm:inline-flex items-center gap-1 text-xs text-zinc-400 hover:text-emerald-400 transition"
                    title="View public website"
                >
                    <span>View site</span>
                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                    </svg>
                </a>

                <!-- Admin Account Menu -->
                <div class="relative flex items-center gap-2 pl-2 border-l border-zinc-800">
                    <div class="flex flex-col text-right hidden sm:block">
                        <span class="text-xs font-medium text-zinc-200">{{ $user->name }}</span>
                        <span class="text-[10px] text-zinc-400">Platform Admin</span>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button
                            type="submit"
                            class="rounded-lg border border-zinc-700/80 bg-zinc-800/80 px-2.5 py-1 text-xs font-medium text-rose-300 hover:bg-rose-950/50 hover:border-rose-800 transition"
                        >
                            Log out
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <!-- SHELL BODY: SIDEBAR + MAIN -->
        <div class="flex flex-1 overflow-hidden">
            <!-- SIDEBAR -->
            <aside
                id="admin-sidebar"
                class="fixed inset-y-0 left-0 z-50 w-64 -translate-x-full border-r border-zinc-800 bg-zinc-900/95 transition-transform duration-200 ease-in-out lg:static lg:w-64 lg:translate-x-0 lg:bg-zinc-900/50 admin-sidebar-scroll flex flex-col justify-between"
                aria-label="Admin Navigation"
            >
                <div class="flex flex-col overflow-y-auto px-4 py-5 gap-6">
                    <!-- Mobile close button inside drawer -->
                    <div class="flex items-center justify-between pb-3 border-b border-zinc-800 lg:hidden">
                        <span class="text-xs font-semibold uppercase tracking-wider text-zinc-400">Navigation</span>
                        <button
                            type="button"
                            onclick="closeAdminSidebar()"
                            class="rounded-md p-1.5 text-zinc-400 hover:bg-zinc-800 hover:text-white"
                            aria-label="Close navigation menu"
                        >
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Mobile search bar -->
                    <div class="md:hidden">
                        <form action="{{ route('admin.search') }}" method="GET" class="w-full relative" role="search">
                            <input
                                type="search"
                                name="q"
                                value="{{ request('q') }}"
                                placeholder="Search console..."
                                class="w-full rounded-lg border border-zinc-700 bg-zinc-950/70 py-1.5 pl-3 pr-3 text-xs text-zinc-100 placeholder-zinc-500 focus:border-emerald-500 focus:outline-none"
                            />
                        </form>
                    </div>

                    <!-- GROUP 1: OVERVIEW -->
                    <div>
                        <div class="px-2 pb-1.5 text-[11px] font-bold uppercase tracking-wider text-zinc-400">Overview</div>
                        <nav class="space-y-0.5 text-xs font-medium">
                            <a
                                href="{{ route('admin.dashboard') }}"
                                class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 transition {{ request()->routeIs('admin.dashboard') && !request()->has('status') ? 'bg-emerald-950/70 text-emerald-300 font-semibold border border-emerald-800/60' : 'text-zinc-300 hover:bg-zinc-800 hover:text-white' }}"
                            >
                                <svg class="h-4 w-4 opacity-75 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5m.75-9 3-3 2.148 2.148A12.061 12.061 0 0 1 16.5 7.605" />
                                </svg>
                                <span>Dashboard</span>
                            </a>
                            <a
                                href="{{ route('admin.streams.index', ['status' => 'live']) }}"
                                class="flex items-center justify-between rounded-lg px-2.5 py-2 transition {{ request()->routeIs('admin.streams.*') && request('status') === 'live' ? 'bg-emerald-950/70 text-emerald-300 font-semibold border border-emerald-800/60' : 'text-zinc-300 hover:bg-zinc-800 hover:text-white' }}"
                            >
                                <div class="flex items-center gap-2.5">
                                    <svg class="h-4 w-4 text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z" />
                                    </svg>
                                    <span>Live Activity</span>
                                </div>
                                <span class="h-2 w-2 rounded-full bg-emerald-400 animate-pulse"></span>
                            </a>
                        </nav>
                    </div>

                    <!-- GROUP 2: PLATFORM -->
                    <div>
                        <div class="px-2 pb-1.5 text-[11px] font-bold uppercase tracking-wider text-zinc-400">Platform</div>
                        <nav class="space-y-0.5 text-xs font-medium">
                            <a
                                href="{{ route('admin.organizations.index') }}"
                                class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 transition {{ request()->routeIs('admin.organizations.*') ? 'bg-emerald-950/70 text-emerald-300 font-semibold border border-emerald-800/60' : 'text-zinc-300 hover:bg-zinc-800 hover:text-white' }}"
                            >
                                <svg class="h-4 w-4 opacity-75 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.333A2.25 2.25 0 0 0 18 8.083h-3a2.25 2.25 0 0 0-2.25 2.25V21" />
                                </svg>
                                <span>Organisations</span>
                            </a>
                            <a
                                href="{{ route('admin.users.index') }}"
                                class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 transition {{ request()->routeIs('admin.users.*') ? 'bg-emerald-950/70 text-emerald-300 font-semibold border border-emerald-800/60' : 'text-zinc-300 hover:bg-zinc-800 hover:text-white' }}"
                            >
                                <svg class="h-4 w-4 opacity-75 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                                </svg>
                                <span>Users</span>
                            </a>
                            <a
                                href="{{ route('admin.streams.index') }}"
                                class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 transition {{ request()->routeIs('admin.streams.*') && request('status') !== 'live' ? 'bg-emerald-950/70 text-emerald-300 font-semibold border border-emerald-800/60' : 'text-zinc-300 hover:bg-zinc-800 hover:text-white' }}"
                            >
                                <svg class="h-4 w-4 opacity-75 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.348 14.652a3.75 3.75 0 0 1 0-5.304m5.304 0a3.75 3.75 0 0 1 0 5.304m-7.425 2.121a6.75 6.75 0 0 1 0-9.546m9.546 0a6.75 6.75 0 0 1 0 9.546M5.106 18.894c-3.808-3.807-3.808-9.98 0-13.788m13.788 0c3.808 3.807 3.808 9.98 0 13.788M12 12h.008v.008H12V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                </svg>
                                <span>Streams</span>
                            </a>
                            <a
                                href="{{ route('admin.events.index') }}"
                                class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 transition {{ request()->routeIs('admin.events.*') ? 'bg-emerald-950/70 text-emerald-300 font-semibold border border-emerald-800/60' : 'text-zinc-300 hover:bg-zinc-800 hover:text-white' }}"
                            >
                                <svg class="h-4 w-4 opacity-75 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 9v7.5" />
                                </svg>
                                <span>Events</span>
                            </a>
                            <a
                                href="{{ route('admin.analytics.index') }}"
                                class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 transition {{ request()->routeIs('admin.analytics.*') ? 'bg-emerald-950/70 text-emerald-300 font-semibold border border-emerald-800/60' : 'text-zinc-300 hover:bg-zinc-800 hover:text-white' }}"
                            >
                                <svg class="h-4 w-4 opacity-75 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                                </svg>
                                <span>Analytics</span>
                            </a>
                        </nav>
                    </div>

                    <!-- GROUP 3: CONTENT -->
                    <div>
                        <div class="px-2 pb-1.5 text-[11px] font-bold uppercase tracking-wider text-zinc-400">Content</div>
                        <nav class="space-y-0.5 text-xs font-medium">
                            <!-- Single Top-Level Digital Library entry with Sub-navigation -->
                            <div>
                                <a
                                    href="{{ route('admin.digital-library.overview') }}"
                                    class="flex items-center justify-between rounded-lg px-2.5 py-2 transition {{ request()->routeIs('admin.digital-library.*') ? 'bg-emerald-950/70 text-emerald-300 font-semibold border border-emerald-800/60' : 'text-zinc-300 hover:bg-zinc-800 hover:text-white' }}"
                                >
                                    <div class="flex items-center gap-2.5">
                                        <svg class="h-4 w-4 opacity-75 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                                        </svg>
                                        <span>Digital Library</span>
                                    </div>
                                    <span class="text-[10px] text-zinc-400 font-normal">Hub</span>
                                </a>

                                <!-- Digital Library Subsections -->
                                <div class="ml-4 mt-1 space-y-0.5 border-l border-zinc-800 pl-2">
                                    <a href="{{ route('admin.digital-library.overview') }}" class="block rounded px-2 py-1 text-[11px] {{ request()->routeIs('admin.digital-library.overview') ? 'text-emerald-400 font-semibold bg-zinc-800/60' : 'text-zinc-400 hover:text-zinc-200' }}">Overview</a>
                                    <a href="{{ route('admin.digital-library.sellers.index') }}" class="block rounded px-2 py-1 text-[11px] {{ request()->routeIs('admin.digital-library.sellers.*') ? 'text-emerald-400 font-semibold bg-zinc-800/60' : 'text-zinc-400 hover:text-zinc-200' }}">Sellers</a>
                                    <a href="{{ route('admin.digital-library.teachers.index') }}" class="block rounded px-2 py-1 text-[11px] {{ request()->routeIs('admin.digital-library.teachers.*') ? 'text-emerald-400 font-semibold bg-zinc-800/60' : 'text-zinc-400 hover:text-zinc-200' }}">Teachers</a>
                                    <a href="{{ route('admin.digital-library.series.index') }}" class="block rounded px-2 py-1 text-[11px] {{ request()->routeIs('admin.digital-library.series.*') ? 'text-emerald-400 font-semibold bg-zinc-800/60' : 'text-zinc-400 hover:text-zinc-200' }}">Series</a>
                                    <a href="{{ route('admin.digital-library.categories.index') }}" class="block rounded px-2 py-1 text-[11px] {{ request()->routeIs('admin.digital-library.categories.*') ? 'text-emerald-400 font-semibold bg-zinc-800/60' : 'text-zinc-400 hover:text-zinc-200' }}">Categories</a>
                                    <a href="{{ route('admin.digital-library.purchases.index') }}" class="block rounded px-2 py-1 text-[11px] {{ request()->routeIs('admin.digital-library.purchases.*') ? 'text-emerald-400 font-semibold bg-zinc-800/60' : 'text-zinc-400 hover:text-zinc-200' }}">Purchases</a>
                                    <a href="{{ route('admin.digital-library.settings') }}" class="block rounded px-2 py-1 text-[11px] {{ request()->routeIs('admin.digital-library.settings') ? 'text-emerald-400 font-semibold bg-zinc-800/60' : 'text-zinc-400 hover:text-zinc-200' }}">Settings</a>
                                    <a href="{{ route('admin.digital-library.audit') }}" class="block rounded px-2 py-1 text-[11px] {{ request()->routeIs('admin.digital-library.audit') ? 'text-emerald-400 font-semibold bg-zinc-800/60' : 'text-zinc-400 hover:text-zinc-200' }}">Audit Log</a>
                                </div>
                            </div>
                        </nav>
                    </div>

                    <!-- GROUP 4: COMMERCE -->
                    <div>
                        <div class="px-2 pb-1.5 text-[11px] font-bold uppercase tracking-wider text-zinc-400">Commerce</div>
                        <nav class="space-y-0.5 text-xs font-medium">
                            <a
                                href="{{ route('admin.plans.index') }}"
                                class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 transition {{ request()->routeIs('admin.plans.*') ? 'bg-emerald-950/70 text-emerald-300 font-semibold border border-emerald-800/60' : 'text-zinc-300 hover:bg-zinc-800 hover:text-white' }}"
                            >
                                <svg class="h-4 w-4 opacity-75 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
                                </svg>
                                <span>Packages & Plans</span>
                            </a>

                            <!-- Affiliate Programme with subsections -->
                            <div class="pt-1">
                                <a
                                    href="{{ route('admin.affiliate-programme.overview') }}"
                                    class="flex items-center justify-between rounded-lg px-2.5 py-2 transition {{ request()->routeIs('admin.affiliate-programme.*', 'admin.affiliates.*', 'admin.prospects.*') ? 'bg-emerald-950/70 text-emerald-300 font-semibold border border-emerald-800/60' : 'text-zinc-300 hover:bg-zinc-800 hover:text-white' }}"
                                >
                                    <div class="flex items-center gap-2.5">
                                        <svg class="h-4 w-4 opacity-75 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                                        </svg>
                                        <span>Affiliate Programme</span>
                                    </div>
                                    <span class="text-[10px] text-zinc-400 font-normal">Hub</span>
                                </a>

                                <div class="ml-4 mt-1 space-y-0.5 border-l border-zinc-800 pl-2">
                                    <a href="{{ route('admin.affiliate-programme.overview') }}" class="block rounded px-2 py-1 text-[11px] {{ request()->routeIs('admin.affiliate-programme.overview') ? 'text-emerald-400 font-semibold bg-zinc-800/60' : 'text-zinc-400 hover:text-zinc-200' }}">Overview</a>
                                    <a href="{{ route('admin.affiliates.index') }}" class="block rounded px-2 py-1 text-[11px] {{ request()->routeIs('admin.affiliates.*') ? 'text-emerald-400 font-semibold bg-zinc-800/60' : 'text-zinc-400 hover:text-zinc-200' }}">Affiliates</a>
                                    <a href="{{ route('admin.prospects.index') }}" class="block rounded px-2 py-1 text-[11px] {{ request()->routeIs('admin.prospects.*') ? 'text-emerald-400 font-semibold bg-zinc-800/60' : 'text-zinc-400 hover:text-zinc-200' }}">Prospects</a>
                                    <a href="{{ route('admin.affiliate-programme.levels') }}" class="block rounded px-2 py-1 text-[11px] {{ request()->routeIs('admin.affiliate-programme.levels') ? 'text-emerald-400 font-semibold bg-zinc-800/60' : 'text-zinc-400 hover:text-zinc-200' }}">Levels</a>
                                    <a href="{{ route('admin.affiliate-programme.settings') }}" class="block rounded px-2 py-1 text-[11px] {{ request()->routeIs('admin.affiliate-programme.settings') ? 'text-emerald-400 font-semibold bg-zinc-800/60' : 'text-zinc-400 hover:text-zinc-200' }}">Settings</a>
                                    <a href="{{ route('admin.affiliate-programme.audit') }}" class="block rounded px-2 py-1 text-[11px] {{ request()->routeIs('admin.affiliate-programme.audit') ? 'text-emerald-400 font-semibold bg-zinc-800/60' : 'text-zinc-400 hover:text-zinc-200' }}">Audit Log</a>
                                </div>
                            </div>
                        </nav>
                    </div>

                    <!-- GROUP 5: SYSTEM -->
                    <div>
                        <div class="px-2 pb-1.5 text-[11px] font-bold uppercase tracking-wider text-zinc-400">System</div>
                        <nav class="space-y-0.5 text-xs font-medium">
                            <a
                                href="{{ route('admin.settings.edit') }}"
                                class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 transition {{ request()->routeIs('admin.settings.*') ? 'bg-emerald-950/70 text-emerald-300 font-semibold border border-emerald-800/60' : 'text-zinc-300 hover:bg-zinc-800 hover:text-white' }}"
                            >
                                <svg class="h-4 w-4 opacity-75 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 0 1 0 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 0 1 0-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                                <span>Platform Settings</span>
                            </a>
                        </nav>
                    </div>
                </div>

                <!-- Sidebar footer: Operator health badge -->
                <div class="p-3 border-t border-zinc-800 bg-zinc-950/40 text-[11px] text-zinc-400 flex items-center justify-between">
                    <span class="flex items-center gap-1.5">
                        <span class="h-2 w-2 rounded-full bg-emerald-400"></span>
                        <span>Console Active</span>
                    </span>
                    <span class="font-mono text-[10px] text-zinc-400">v1.2</span>
                </div>
            </aside>

            <!-- MAIN CONTENT AREA -->
            <main class="flex-1 overflow-y-auto bg-zinc-950 px-4 py-6 sm:px-6 lg:px-8">
                <!-- Session messages -->
                @if (session('status'))
                    <div class="mb-6 rounded-lg border border-emerald-800/60 bg-emerald-950/40 px-4 py-3 text-sm text-emerald-200">
                        {{ session('status') }}
                    </div>
                @endif

                @if (session('error'))
                    <div class="mb-6 rounded-lg border border-red-900/60 bg-red-950/40 px-4 py-3 text-sm text-red-200">
                        {{ session('error') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mb-6 rounded-lg border border-red-900/60 bg-red-950/40 px-4 py-3 text-sm text-red-200">
                        <ul class="list-inside list-disc space-y-1">
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

    <!-- Mobile sidebar script -->
    <script>
        function toggleAdminSidebar() {
            const sidebar = document.getElementById('admin-sidebar');
            const backdrop = document.getElementById('mobile-backdrop');
            const toggle = document.getElementById('mobile-sidebar-toggle');
            const isOpen = !sidebar.classList.contains('-translate-x-full');

            if (isOpen) {
                closeAdminSidebar();
            } else {
                sidebar.classList.remove('-translate-x-full');
                backdrop.classList.remove('hidden');
                toggle.setAttribute('aria-expanded', 'true');
                document.body.style.overflow = 'hidden';
            }
        }

        function closeAdminSidebar() {
            const sidebar = document.getElementById('admin-sidebar');
            const backdrop = document.getElementById('mobile-backdrop');
            const toggle = document.getElementById('mobile-sidebar-toggle');

            sidebar.classList.add('-translate-x-full');
            backdrop.classList.add('hidden');
            if (toggle) toggle.setAttribute('aria-expanded', 'false');
            document.body.style.overflow = '';
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAdminSidebar();
            }
        });
    </script>
</body>
</html>
