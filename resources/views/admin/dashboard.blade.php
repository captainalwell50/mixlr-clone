@extends('layouts.admin')

@section('title', 'Platform Operations Dashboard')

@section('content')
<div class="space-y-8 max-w-7xl mx-auto">
    <!-- PAGE HEADER -->
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-zinc-800 pb-5">
        <div>
            <div class="flex items-center gap-2">
                <span class="inline-flex h-2 w-2 rounded-full bg-emerald-400"></span>
                <span class="text-xs font-semibold uppercase tracking-wider text-emerald-400">Operations Console</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-white mt-1">Platform Dashboard</h1>
            <p class="text-xs text-zinc-400 mt-0.5">Platform telemetry, attention queues, and live system state.</p>
        </div>
        <div class="flex items-center gap-3">
            <span class="text-[11px] text-zinc-500 font-mono hidden sm:inline-block">Snapshot: {{ now()->timezone(\App\Support\LocalTime::tz())->format('M j, Y · H:i:s T') }}</span>
            <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-1.5 text-xs font-medium text-zinc-200 hover:bg-zinc-700 hover:text-white transition">
                <svg class="h-3.5 w-3.5 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                </svg>
                <span>Refresh</span>
            </a>
        </div>
    </div>

    <!-- ATTENTION CENTRE (NEEDS ATTENTION) -->
    <section id="needs-attention" class="scroll-mt-20">
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-300">Needs Attention</h2>
                @if (count($attentionItems) > 0)
                    <span class="rounded-full bg-amber-500/20 px-2 py-0.5 text-xs font-bold text-amber-300 border border-amber-600/40">
                        {{ array_sum(array_column($attentionItems, 'count')) }} action item{{ array_sum(array_column($attentionItems, 'count')) === 1 ? '' : 's' }}
                    </span>
                @endif
            </div>
            <span class="text-xs text-zinc-500">Real exception queue</span>
        </div>

        @if (count($attentionItems) > 0)
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach ($attentionItems as $item)
                    <div class="flex items-center justify-between rounded-xl border p-4 transition {{ $item['severity'] === 'critical' ? 'border-rose-900/60 bg-rose-950/20 hover:bg-rose-950/30' : 'border-amber-900/60 bg-amber-950/20 hover:bg-amber-950/30' }}">
                        <div class="flex items-start gap-3">
                            <span class="mt-0.5 flex h-2 w-2 shrink-0 rounded-full {{ $item['severity'] === 'critical' ? 'bg-rose-400' : 'bg-amber-400' }}"></span>
                            <div>
                                <p class="text-xs font-semibold text-zinc-100">{{ $item['label'] }}</p>
                                <p class="text-[11px] text-zinc-400 mt-0.5 capitalize">Priority: {{ $item['severity'] }}</p>
                            </div>
                        </div>
                        <a href="{{ $item['url'] }}" class="shrink-0 rounded-lg border px-2.5 py-1 text-xs font-medium transition {{ $item['severity'] === 'critical' ? 'border-rose-700 bg-rose-900/50 text-rose-200 hover:bg-rose-800' : 'border-amber-700 bg-amber-900/50 text-amber-200 hover:bg-amber-800' }}">
                            {{ $item['action_label'] }} &rarr;
                        </a>
                    </div>
                @endforeach
            </div>
        @else
            <div class="rounded-xl border border-zinc-800 bg-zinc-900/40 p-4 text-center sm:text-left sm:flex sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-950 text-emerald-400 ring-1 ring-emerald-800">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                    </span>
                    <div>
                        <p class="text-xs font-semibold text-zinc-200">No items currently need attention</p>
                        <p class="text-[11px] text-zinc-500">All creator queues, subscriptions, review queues, and background jobs are clear.</p>
                    </div>
                </div>
                <span class="hidden sm:inline-block text-[11px] font-mono text-emerald-500 bg-emerald-950/40 px-2 py-1 rounded border border-emerald-900/60">Systems Normal</span>
            </div>
        @endif
    </section>

    <!-- LIVE ACTIVITY PANEL -->
    <section id="live-activity" class="scroll-mt-20">
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-300">Live Broadcasts</h2>
                @if ($metrics['live_now'] > 0)
                    <span class="rounded-full bg-emerald-500/20 px-2 py-0.5 text-xs font-bold text-emerald-400 border border-emerald-700/50 flex items-center gap-1.5">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-ping"></span>
                        {{ $metrics['live_now'] }} Live Now
                    </span>
                @endif
            </div>
            <a href="{{ route('admin.streams.index') }}" class="text-xs text-zinc-400 hover:text-emerald-400 transition">View all streams &rarr;</a>
        </div>

        @if ($liveStreams->isNotEmpty())
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach ($liveStreams as $stream)
                    @php
                        $listeners = $stream->activeListenerCount();
                        $serviceEvent = $stream->openServiceEvent();
                        $displayTitle = $serviceEvent?->title ?: $stream->title;
                        $startedAt = $stream->started_at ?: $serviceEvent?->started_at;
                        $duration = $startedAt ? $startedAt->diffForHumans(null, true) : 'Active';
                    @endphp
                    <div class="rounded-xl border border-emerald-900/50 bg-gradient-to-br from-emerald-950/30 to-zinc-900/60 p-5 shadow-lg shadow-black/20 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/20 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-emerald-300 border border-emerald-700/60">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                    LIVE
                                </span>
                                <span class="text-[11px] font-mono text-zinc-400">{{ $duration }}</span>
                            </div>
                            <h3 class="text-base font-semibold text-white truncate" title="{{ $displayTitle }}">{{ $displayTitle }}</h3>
                            <p class="text-xs text-emerald-300/80 truncate mt-0.5">
                                <a href="{{ route('admin.organizations.edit', $stream->organization) }}" class="hover:underline">{{ $stream->organization->name }}</a>
                            </p>

                            <div class="mt-4 flex items-center justify-between border-t border-zinc-800/80 pt-3 text-xs">
                                <div>
                                    <span class="text-zinc-500 block text-[10px] uppercase tracking-wider">Audience</span>
                                    <span class="font-semibold text-zinc-200">{{ number_format($listeners) }} current listener{{ $listeners === 1 ? '' : 's' }}</span>
                                </div>
                                <div class="text-right">
                                    <span class="text-zinc-500 block text-[10px] uppercase tracking-wider">Ingest</span>
                                    <span class="font-mono text-[11px] text-zinc-400">{{ $stream->playbackMode() === 'hls' ? 'HLS + WHEP' : 'WHEP direct' }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 pt-3 border-t border-zinc-800/80 flex items-center justify-between gap-2">
                            <a href="{{ route('admin.streams.edit', $stream) }}" class="flex-1 text-center rounded-lg border border-zinc-700 bg-zinc-800 px-2.5 py-1.5 text-xs font-medium text-zinc-200 hover:bg-zinc-700 hover:text-white transition">
                                Inspect
                            </a>
                            <a href="{{ route('listen.stream', $stream) }}" target="_blank" rel="noopener noreferrer" class="rounded-lg border border-emerald-800/60 bg-emerald-950/40 px-2.5 py-1.5 text-xs font-medium text-emerald-300 hover:bg-emerald-900/60 transition">
                                Listen
                            </a>
                            <a href="{{ route('admin.streams.studio', $stream) }}" target="_blank" rel="noopener noreferrer" class="rounded-lg border border-zinc-700 bg-zinc-800 px-2.5 py-1.5 text-xs font-medium text-zinc-300 hover:bg-zinc-700 transition">
                                Studio
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="rounded-xl border border-zinc-800 bg-zinc-900/30 p-8 text-center">
                <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-zinc-800 text-zinc-400">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.348 14.652a3.75 3.75 0 0 1 0-5.304m5.304 0a3.75 3.75 0 0 1 0 5.304m-7.425 2.121a6.75 6.75 0 0 1 0-9.546m9.546 0a6.75 6.75 0 0 1 0 9.546M5.106 18.894c-3.808-3.807-3.808-9.98 0-13.788m13.788 0c3.808 3.807 3.808 9.98 0 13.788M12 12h.008v.008H12V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                    </svg>
                </div>
                <h3 class="mt-3 text-sm font-semibold text-zinc-200">No broadcasts are live right now</h3>
                <p class="mt-1 text-xs text-zinc-500">Live channels and their current listener counts will appear here when creators publish.</p>
                <div class="mt-4 flex items-center justify-center gap-3">
                    <a href="{{ route('admin.streams.index') }}" class="rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-1.5 text-xs font-medium text-zinc-200 hover:bg-zinc-700">All streams</a>
                    <a href="{{ route('admin.events.create') }}" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-500">Schedule event</a>
                </div>
            </div>
        @endif
    </section>

    <!-- OPERATIONAL METRICS / KPI GRID -->
    <section>
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-300">Platform Performance & Activity</h2>
            <span class="text-xs text-zinc-500">Live metrics from verified platform activity</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- 1. LIVE NOW -->
            <a href="{{ route('admin.streams.index', ['status' => 'live']) }}" class="group rounded-xl border border-zinc-800 bg-zinc-900/50 p-4 transition hover:border-emerald-700/80 hover:bg-zinc-900/80">
                <div class="flex items-center justify-between text-xs text-zinc-400">
                    <span class="uppercase tracking-wider font-semibold">Live Broadcasts</span>
                    <span class="h-2 w-2 rounded-full {{ $metrics['live_now'] > 0 ? 'bg-emerald-400 animate-pulse' : 'bg-zinc-600' }}"></span>
                </div>
                <div class="mt-2 text-2xl font-bold text-white group-hover:text-emerald-300 transition">
                    {{ number_format($metrics['live_now']) }}
                </div>
                <div class="mt-2 text-[11px] text-zinc-500 flex items-center justify-between">
                    <span>Current listeners: {{ number_format($metrics['current_listeners']) }}</span>
                    <span class="text-emerald-400 group-hover:translate-x-0.5 transition-transform">&rarr;</span>
                </div>
            </a>

            <!-- 2. BROADCASTS TODAY -->
            <a href="{{ route('admin.events.index') }}" class="group rounded-xl border border-zinc-800 bg-zinc-900/50 p-4 transition hover:border-emerald-700/80 hover:bg-zinc-900/80">
                <div class="flex items-center justify-between text-xs text-zinc-400">
                    <span class="uppercase tracking-wider font-semibold">Broadcasts Today</span>
                    <div class="h-6 w-6 rounded-md bg-zinc-800/80 text-zinc-400 flex items-center justify-center shrink-0 border border-zinc-700/60">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 9v7.5" />
                        </svg>
                    </div>
                </div>
                <div class="mt-2 text-2xl font-bold text-white group-hover:text-emerald-300 transition">
                    {{ number_format($metrics['broadcasts_today']) }}
                </div>
                <div class="mt-2 text-[11px] text-zinc-500 flex items-center justify-between">
                    <span>Since 00:00 local</span>
                    <span class="text-emerald-400 group-hover:translate-x-0.5 transition-transform">&rarr;</span>
                </div>
            </a>

            <!-- 3. ORGANISATIONS -->
            <a href="{{ route('admin.organizations.index') }}" class="group rounded-xl border border-zinc-800 bg-zinc-900/50 p-4 transition hover:border-emerald-700/80 hover:bg-zinc-900/80">
                <div class="flex items-center justify-between text-xs text-zinc-400">
                    <span class="uppercase tracking-wider font-semibold">Organisations</span>
                    <div class="h-6 w-6 rounded-md bg-zinc-800/80 text-zinc-400 flex items-center justify-center shrink-0 border border-zinc-700/60">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.333A2.25 2.25 0 0 0 18 8.083h-3a2.25 2.25 0 0 0-2.25 2.25V21" />
                        </svg>
                    </div>
                </div>
                <div class="mt-2 text-2xl font-bold text-white group-hover:text-emerald-300 transition">
                    {{ number_format($metrics['total_organizations']) }}
                </div>
                <div class="mt-2 text-[11px] text-zinc-500 flex items-center justify-between">
                    <span>Active organisations: {{ number_format($metrics['active_organizations']) }}</span>
                    <span class="text-emerald-400 group-hover:translate-x-0.5 transition-transform">&rarr;</span>
                </div>
            </a>

            <!-- 4. TOTAL USERS -->
            <a href="{{ route('admin.users.index') }}" class="group rounded-xl border border-zinc-800 bg-zinc-900/50 p-4 transition hover:border-emerald-700/80 hover:bg-zinc-900/80">
                <div class="flex items-center justify-between text-xs text-zinc-400">
                    <span class="uppercase tracking-wider font-semibold">Total Accounts</span>
                    <div class="h-6 w-6 rounded-md bg-zinc-800/80 text-zinc-400 flex items-center justify-center shrink-0 border border-zinc-700/60">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-2 text-2xl font-bold text-white group-hover:text-emerald-300 transition">
                    {{ number_format($metrics['total_users']) }}
                </div>
                <div class="mt-2 text-[11px] text-zinc-500 flex items-center justify-between">
                    <span>Manage users</span>
                    <span class="text-emerald-400 group-hover:translate-x-0.5 transition-transform">&rarr;</span>
                </div>
            </a>

            <!-- 5. DIGITAL LIBRARY GROSS COLLECTED -->
            <a href="{{ route('admin.digital-library.purchases.index') }}" class="group rounded-xl border border-zinc-800 bg-zinc-900/50 p-4 transition hover:border-emerald-700/80 hover:bg-zinc-900/80">
                <div class="flex items-center justify-between text-xs text-zinc-400">
                    <span class="uppercase tracking-wider font-semibold">Digital Library GMV (NGN)</span>
                    <span class="text-[10px] font-mono bg-zinc-800 text-zinc-300 px-1.5 py-0.5 rounded">Gross NGN</span>
                </div>
                <div class="mt-2 text-2xl font-bold text-emerald-400 group-hover:text-emerald-300 transition">
                    &#8358;{{ number_format($metrics['dl_gross_collected_minor'] / 100, 2) }}
                </div>
                <div class="mt-2 text-[11px] text-zinc-500 flex items-center justify-between">
                    <span>Net after refunds: &#8358;{{ number_format($metrics['dl_net_collected_minor'] / 100, 2) }}</span>
                    <span class="text-emerald-400 group-hover:translate-x-0.5 transition-transform">&rarr;</span>
                </div>
            </a>

            <!-- 6. DIGITAL LIBRARY PURCHASES -->
            <a href="{{ route('admin.digital-library.purchases.index') }}" class="group rounded-xl border border-zinc-800 bg-zinc-900/50 p-4 transition hover:border-emerald-700/80 hover:bg-zinc-900/80">
                <div class="flex items-center justify-between text-xs text-zinc-400">
                    <span class="uppercase tracking-wider font-semibold">Library Purchases</span>
                    <div class="h-6 w-6 rounded-md bg-zinc-800/80 text-zinc-400 flex items-center justify-center shrink-0 border border-zinc-700/60">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                        </svg>
                    </div>
                </div>
                <div class="mt-2 text-2xl font-bold text-white group-hover:text-emerald-300 transition">
                    {{ number_format($metrics['dl_purchases']) }}
                </div>
                <div class="mt-2 text-[11px] text-zinc-500 flex items-center justify-between">
                    <span>Paid transactions</span>
                    <span class="text-emerald-400 group-hover:translate-x-0.5 transition-transform">&rarr;</span>
                </div>
            </a>

            <!-- 7. APPROVED SELLERS & APPLICATIONS -->
            <a href="{{ route('admin.digital-library.sellers.index') }}" class="group rounded-xl border border-zinc-800 bg-zinc-900/50 p-4 transition hover:border-emerald-700/80 hover:bg-zinc-900/80">
                <div class="flex items-center justify-between text-xs text-zinc-400">
                    <span class="uppercase tracking-wider font-semibold">Library Sellers</span>
                    @if ($metrics['pending_sellers'] > 0)
                        <span class="rounded bg-amber-500/20 px-1.5 py-0.2 text-[10px] font-bold text-amber-300">{{ $metrics['pending_sellers'] }} pending</span>
                    @endif
                </div>
                <div class="mt-2 text-2xl font-bold text-white group-hover:text-emerald-300 transition">
                    {{ number_format($metrics['approved_sellers']) }}
                </div>
                <div class="mt-2 text-[11px] text-zinc-500 flex items-center justify-between">
                    <span>{{ $metrics['pending_sellers'] }} application{{ $metrics['pending_sellers'] === 1 ? '' : 's' }} pending</span>
                    <span class="text-emerald-400 group-hover:translate-x-0.5 transition-transform">&rarr;</span>
                </div>
            </a>

            <!-- 8. AFFILIATES & APPLICATIONS -->
            <a href="{{ route('admin.affiliates.index') }}" class="group rounded-xl border border-zinc-800 bg-zinc-900/50 p-4 transition hover:border-emerald-700/80 hover:bg-zinc-900/80">
                <div class="flex items-center justify-between text-xs text-zinc-400">
                    <span class="uppercase tracking-wider font-semibold">Active Affiliates</span>
                    @if ($metrics['pending_affiliates'] > 0)
                        <span class="rounded bg-amber-500/20 px-1.5 py-0.2 text-[10px] font-bold text-amber-300">{{ $metrics['pending_affiliates'] }} pending</span>
                    @endif
                </div>
                <div class="mt-2 text-2xl font-bold text-white group-hover:text-emerald-300 transition">
                    {{ number_format($metrics['approved_affiliates']) }}
                </div>
                <div class="mt-2 text-[11px] text-zinc-500 flex items-center justify-between">
                    <span>{{ $metrics['pending_affiliates'] }} application{{ $metrics['pending_affiliates'] === 1 ? '' : 's' }} pending</span>
                    <span class="text-emerald-400 group-hover:translate-x-0.5 transition-transform">&rarr;</span>
                </div>
            </a>
        </div>
    </section>

    <!-- PLATFORM HEALTH & INFRASTRUCTURE -->
    <section id="platform-health" class="scroll-mt-20">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-300">Platform Health & Ingest Delivery</h2>
            <a href="{{ route('admin.settings.edit') }}" class="text-xs text-zinc-400 hover:text-emerald-400 transition">Platform settings &rarr;</a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Media Delivery Pipeline -->
            <div class="rounded-xl border border-zinc-800 bg-zinc-900/40 p-5">
                <div class="flex items-center justify-between text-xs">
                    <span class="font-semibold text-zinc-300 uppercase tracking-wider">Playback Delivery</span>
                    <span class="inline-flex items-center gap-1 rounded bg-zinc-800 px-2 py-0.5 font-mono text-[10px] text-zinc-300">
                        {{ $listenPlaybackStatus['effective_mode'] }}
                    </span>
                </div>
                <div class="mt-3 flex items-baseline gap-2">
                    <span class="text-lg font-bold text-white">{{ $listenPlaybackStatus['effective_label'] }}</span>
                </div>
                <p class="mt-2 text-xs text-zinc-400 leading-relaxed">
                    Source: <span class="text-zinc-200">{{ $listenPlaybackStatus['policy_source_label'] }}</span>
                </p>
                <div class="mt-3 text-[11px] text-zinc-500 border-t border-zinc-800/80 pt-2 flex items-center justify-between">
                    <span>CDN host: {{ $listenPlaybackStatus['cdn_host'] ?: 'None (Local MediaMTX)' }}</span>
                    <span>AAC: {{ $listenPlaybackStatus['aac_sidecar'] ? 'On' : 'Off' }}</span>
                </div>
            </div>

            <!-- Host Infrastructure Capacity State -->
            <div class="rounded-xl border border-zinc-800 bg-zinc-900/40 p-5">
                <div class="flex items-center justify-between text-xs">
                    <span class="font-semibold text-zinc-300 uppercase tracking-wider">Infrastructure Capacity</span>
                    <span class="inline-flex items-center gap-1.5 rounded bg-zinc-800 px-2 py-0.5 text-[10px] {{ $vmSnapshot['desired'] === 'peak' ? 'text-amber-400 font-bold' : 'text-zinc-400' }}">
                        {{ strtoupper($vmSnapshot['desired']) }}
                    </span>
                </div>
                <div class="mt-3 flex items-baseline gap-2">
                    <span class="text-lg font-bold text-white">{{ $vmSnapshot['desired_sku'] }}</span>
                    <span class="text-xs text-zinc-500 font-mono">({{ $vmSnapshot['reason'] }})</span>
                </div>
                <p class="mt-2 text-xs text-zinc-400 leading-relaxed">
                    Live streams: <span class="text-zinc-200 font-mono">{{ $vmSnapshot['live_streams'] }}</span> &middot; Upcoming (30m): <span class="text-zinc-200 font-mono">{{ $vmSnapshot['upcoming_events'] }}</span>
                </p>
                <div class="mt-3 text-[11px] text-zinc-500 border-t border-zinc-800/80 pt-2">
                    Hold peak: {{ !empty($vmSnapshot['hold_peak_until']) ? 'Held until ' . \App\Support\LocalTime::format(\Illuminate\Support\Carbon::parse($vmSnapshot['hold_peak_until']), 'H:i') : 'Auto (dynamic)' }}
                </div>
            </div>

            <!-- Payment Gateway Connectivity -->
            <div class="rounded-xl border border-zinc-800 bg-zinc-900/40 p-5">
                <div class="flex items-center justify-between text-xs">
                    <span class="font-semibold text-zinc-300 uppercase tracking-wider">Commercial Gateway</span>
                    <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[10px] {{ $paystackEnabled ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-800/60' : 'bg-zinc-800 text-zinc-400' }}">
                        {{ $paystackEnabled ? 'Paystack Ready' : 'Disabled' }}
                    </span>
                </div>
                <div class="mt-3 flex items-baseline gap-2">
                    <span class="text-lg font-bold text-white">Paystack API</span>
                </div>
                <p class="mt-2 text-xs text-zinc-400 leading-relaxed">
                    Webhook endpoint: <code class="font-mono text-[10px] text-zinc-300">/webhooks/paystack</code>
                </p>
                <div class="mt-3 text-[11px] text-zinc-500 border-t border-zinc-800/80 pt-2">
                    Currency: <span class="text-zinc-300 font-mono font-medium">{{ config('services.paystack.currency', 'NGN') }}</span>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
