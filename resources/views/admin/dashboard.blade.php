@extends('layouts.admin')

@section('title', 'Platform Operations & Business Dashboard')

@section('content')
<div class="space-y-8 max-w-7xl mx-auto">
    <!-- PAGE HEADER -->
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-zinc-200 dark:border-zinc-800 pb-5">
        <div>
            <div class="flex items-center gap-2">
                <span class="inline-flex h-2 w-2 rounded-full bg-emerald-500 dark:bg-emerald-400"></span>
                <span class="text-xs font-semibold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">Operations Console</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white mt-1">Platform Dashboard</h1>
            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">Real-time broadcast operations, platform growth, creator health, and commercial telemetry.</p>
        </div>
        <div class="flex items-center gap-3">
            <span class="text-[11px] text-zinc-400 dark:text-zinc-500 font-mono hidden sm:inline-block">Snapshot: {{ now()->timezone(\App\Support\LocalTime::tz())->format('M j, Y · H:i:s T') }}</span>
            <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-xs font-medium text-zinc-700 dark:text-zinc-200 hover:bg-zinc-50 dark:hover:bg-zinc-700 hover:text-zinc-900 dark:hover:text-white transition shadow-xs">
                <svg class="h-3.5 w-3.5 text-zinc-400 dark:text-zinc-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                </svg>
                <span>Refresh</span>
            </a>
        </div>
    </div>

    <!-- 1. PLATFORM PULSE (Operational "Right Now" Metrics) -->
    <section id="platform-pulse" class="scroll-mt-20">
        <div class="flex flex-wrap items-center justify-between gap-y-1.5 gap-x-2 mb-3">
            <div class="flex flex-wrap items-center gap-2">
                <span class="inline-flex h-2 w-2 rounded-full bg-emerald-500 dark:bg-emerald-400 animate-pulse"></span>
                <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-800 dark:text-zinc-200">Platform Pulse <span class="hidden sm:inline text-xs font-normal text-zinc-500 dark:text-zinc-400">· Real-Time Platform State</span></h2>
            </div>
            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-medium bg-emerald-50 dark:bg-emerald-950/70 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/60">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                Live Telemetry
            </span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3">
            <!-- 1. Live Broadcasts -->
            <a href="{{ route('admin.streams.index', ['status' => 'live']) }}" class="group rounded-xl border border-zinc-200 dark:border-zinc-800/80 bg-white dark:bg-zinc-900/50 p-3.5 shadow-xs hover:border-emerald-500/60 dark:hover:border-emerald-700/80 hover:shadow-sm transition flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400">
                        <span class="uppercase tracking-wider font-semibold text-[11px]">Live Broadcasts</span>
                        <div class="h-6 w-6 rounded-md bg-zinc-100 dark:bg-zinc-800/80 text-emerald-600 dark:text-emerald-400 border border-zinc-200/80 dark:border-zinc-700/60 flex items-center justify-center shrink-0">
                            <span class="h-2 w-2 rounded-full {{ $metrics['live_now'] > 0 ? 'bg-emerald-500 dark:bg-emerald-400 animate-ping' : 'bg-zinc-400 dark:bg-zinc-600' }}"></span>
                        </div>
                    </div>
                    <div class="mt-1.5 text-2xl font-bold font-mono tracking-tight text-zinc-900 dark:text-white group-hover:text-emerald-600 dark:group-hover:text-emerald-300 transition">
                        {{ number_format($metrics['live_now']) }}
                    </div>
                </div>
                <div class="mt-2 text-[10px] text-zinc-500 dark:text-zinc-400 border-t border-zinc-100 dark:border-zinc-800/80 pt-1.5 flex items-center justify-between">
                    <span>{{ $metrics['live_now'] > 0 ? 'Streams active now' : 'No active streams' }}</span>
                    <span class="text-emerald-600 dark:text-emerald-400 group-hover:translate-x-0.5 transition-transform">&rarr;</span>
                </div>
            </a>

            <!-- 2. Current Listeners -->
            <a href="{{ route('admin.streams.index') }}" class="group rounded-xl border border-zinc-200 dark:border-zinc-800/80 bg-white dark:bg-zinc-900/50 p-3.5 shadow-xs hover:border-emerald-500/60 dark:hover:border-emerald-700/80 hover:shadow-sm transition flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400">
                        <span class="uppercase tracking-wider font-semibold text-[11px]">Current Listeners</span>
                        <div class="h-6 w-6 rounded-md bg-zinc-100 dark:bg-zinc-800/80 text-zinc-500 dark:text-zinc-400 border border-zinc-200/80 dark:border-zinc-700/60 flex items-center justify-center shrink-0">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.114 5.636a9 9 0 0 1 0 12.728M16.463 8.288a5.25 5.25 0 0 1 0 7.424M6.75 8.25l4.72-4.72a.75.75 0 0 1 1.28.53v15.88a.75.75 0 0 1-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.009 9.009 0 0 1 2.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75Z" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-1.5 text-2xl font-bold font-mono tracking-tight text-zinc-900 dark:text-white group-hover:text-emerald-600 dark:group-hover:text-emerald-300 transition">
                        {{ number_format($metrics['current_listeners']) }}
                    </div>
                </div>
                <div class="mt-2 text-[10px] text-zinc-500 dark:text-zinc-400 border-t border-zinc-100 dark:border-zinc-800/80 pt-1.5 flex items-center justify-between">
                    <span>Active audience</span>
                    <span class="text-emerald-600 dark:text-emerald-400 group-hover:translate-x-0.5 transition-transform">&rarr;</span>
                </div>
            </a>

            <!-- 3. Broadcasts Today -->
            <a href="{{ route('admin.events.index') }}" class="group rounded-xl border border-zinc-200 dark:border-zinc-800/80 bg-white dark:bg-zinc-900/50 p-3.5 shadow-xs hover:border-emerald-500/60 dark:hover:border-emerald-700/80 hover:shadow-sm transition flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400">
                        <span class="uppercase tracking-wider font-semibold text-[11px]">Broadcasts Today</span>
                        <div class="h-6 w-6 rounded-md bg-zinc-100 dark:bg-zinc-800/80 text-zinc-500 dark:text-zinc-400 border border-zinc-200/80 dark:border-zinc-700/60 flex items-center justify-center shrink-0">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 9v7.5" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-1.5 text-2xl font-bold font-mono tracking-tight text-zinc-900 dark:text-white group-hover:text-emerald-600 dark:group-hover:text-emerald-300 transition">
                        {{ number_format($metrics['broadcasts_today']) }}
                    </div>
                </div>
                <div class="mt-2 text-[10px] text-zinc-500 dark:text-zinc-400 border-t border-zinc-100 dark:border-zinc-800/80 pt-1.5 flex items-center justify-between">
                    <span>Since 00:00 local</span>
                    <span class="text-emerald-600 dark:text-emerald-400 group-hover:translate-x-0.5 transition-transform">&rarr;</span>
                </div>
            </a>

            <!-- 4. Active Organisations -->
            <a href="{{ route('admin.organizations.index') }}" class="group rounded-xl border border-zinc-200 dark:border-zinc-800/80 bg-white dark:bg-zinc-900/50 p-3.5 shadow-xs hover:border-emerald-500/60 dark:hover:border-emerald-700/80 hover:shadow-sm transition flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400">
                        <span class="uppercase tracking-wider font-semibold text-[11px]">Active Organisations</span>
                        <div class="h-6 w-6 rounded-md bg-zinc-100 dark:bg-zinc-800/80 text-zinc-500 dark:text-zinc-400 border border-zinc-200/80 dark:border-zinc-700/60 flex items-center justify-center shrink-0">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.333A2.25 2.25 0 0 0 18 8.083h-3a2.25 2.25 0 0 0-2.25 2.25V21" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-1.5 text-2xl font-bold font-mono tracking-tight text-zinc-900 dark:text-white group-hover:text-emerald-600 dark:group-hover:text-emerald-300 transition">
                        {{ number_format($metrics['active_organizations']) }}
                    </div>
                </div>
                <div class="mt-2 text-[10px] text-zinc-500 dark:text-zinc-400 border-t border-zinc-100 dark:border-zinc-800/80 pt-1.5 flex items-center justify-between">
                    <span>Active organisations: {{ number_format($metrics['active_organizations']) }}</span>
                    <span class="text-emerald-600 dark:text-emerald-400 group-hover:translate-x-0.5 transition-transform">&rarr;</span>
                </div>
            </a>

            <!-- 5. Signups Today -->
            <a href="{{ route('admin.users.index') }}" class="group rounded-xl border border-zinc-200 dark:border-zinc-800/80 bg-white dark:bg-zinc-900/50 p-3.5 shadow-xs hover:border-indigo-500/60 dark:hover:border-indigo-700/80 hover:shadow-sm transition flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400">
                        <span class="uppercase tracking-wider font-semibold text-[11px]">Signups Today</span>
                        <span class="text-[10px] font-mono {{ $growthMetrics['signups_today_change'] >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                            {{ $growthMetrics['signups_today_change'] >= 0 ? '+' : '' }}{{ $growthMetrics['signups_today_percent'] }}%
                        </span>
                    </div>
                    <div class="mt-1.5 text-2xl font-bold font-mono tracking-tight text-zinc-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-300 transition">
                        {{ number_format($growthMetrics['signups_today']) }}
                    </div>
                </div>
                <div class="mt-2 text-[10px] text-zinc-500 dark:text-zinc-400 border-t border-zinc-100 dark:border-zinc-800/80 pt-1.5 flex items-center justify-between">
                    <span>Yesterday: {{ number_format($growthMetrics['signups_yesterday']) }}</span>
                    <span class="text-indigo-600 dark:text-indigo-400 group-hover:translate-x-0.5 transition-transform">&rarr;</span>
                </div>
            </a>

            <!-- 6. Attention Items -->
            <a href="#needs-attention" class="group rounded-xl border {{ count($attentionItems) > 0 ? 'border-amber-300 dark:border-amber-800/60 bg-amber-50/40 dark:bg-amber-950/20 hover:border-amber-400 dark:hover:border-amber-700' : 'border-zinc-200 dark:border-zinc-800/80 bg-white dark:bg-zinc-900/50 hover:border-emerald-500/60 dark:hover:border-emerald-700/80' }} p-3.5 shadow-xs hover:shadow-sm transition flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400">
                        <span class="uppercase tracking-wider font-semibold text-[11px]">Attention Items</span>
                        @if (count($attentionItems) > 0)
                            <span class="text-[10px] font-mono font-medium text-amber-700 dark:text-amber-300 bg-amber-100 dark:bg-amber-950/80 px-1.5 py-0.2 rounded border border-amber-300 dark:border-amber-800">
                                {{ count($attentionItems) }} queue{{ count($attentionItems) === 1 ? '' : 's' }}
                            </span>
                        @else
                            <span class="text-[10px] font-mono font-medium text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/60 px-1.5 py-0.2 rounded border border-emerald-200 dark:border-emerald-800">
                                Normal
                            </span>
                        @endif
                    </div>
                    <div class="mt-1.5 text-2xl font-bold font-mono tracking-tight {{ count($attentionItems) > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-zinc-900 dark:text-white' }} transition">
                        {{ array_sum(array_column($attentionItems, 'count')) }}
                    </div>
                </div>
                <div class="mt-2 text-[10px] text-zinc-500 dark:text-zinc-400 border-t border-zinc-100 dark:border-zinc-800/80 pt-1.5 flex items-center justify-between">
                    <span>{{ count($attentionItems) > 0 ? 'Action required' : 'All systems clear' }}</span>
                    <span class="{{ count($attentionItems) > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400' }} group-hover:translate-x-0.5 transition-transform">&rarr;</span>
                </div>
            </a>
        </div>
    </section>

    <!-- 2. GROWTH & ACQUISITION -->
    <section id="growth-acquisition" class="scroll-mt-20">
        <div class="flex flex-wrap items-center justify-between gap-y-1.5 gap-x-2 mb-3">
            <div class="flex flex-wrap items-center gap-2">
                <span class="inline-flex h-2 w-2 rounded-full bg-indigo-500 dark:bg-indigo-400"></span>
                <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-800 dark:text-zinc-200">Growth &amp; Acquisition</h2>
                <span class="text-[10px] font-mono bg-indigo-50 dark:bg-indigo-950/70 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800/60 px-1.5 py-0.5 rounded">Conversion Funnel</span>
            </div>
            <span class="text-xs text-zinc-500 dark:text-zinc-400 hidden sm:inline-block">Authoritative signups &amp; first-time paid conversions</span>
        </div>

        <!-- 4 CONSOLIDATED COMPACT KPI CARDS -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-2 xl:grid-cols-4 gap-3">
            <!-- 1. Signups Today & MoM Growth -->
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-800/80 bg-white dark:bg-zinc-900/50 p-3.5 shadow-xs flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400">
                        <span class="uppercase tracking-wider font-semibold text-[11px]">Signups Today</span>
                        <span class="text-[10px] font-mono {{ $growthMetrics['signups_today_change'] >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                            {{ $growthMetrics['signups_today_change'] >= 0 ? '+' : '' }}{{ $growthMetrics['signups_today_percent'] }}% vs yest
                        </span>
                    </div>
                    <div class="mt-1.5 flex items-baseline justify-between">
                        <span class="text-2xl font-bold font-mono tracking-tight text-zinc-900 dark:text-white">
                            {{ number_format($growthMetrics['signups_today']) }}
                        </span>
                        <span class="text-[11px] font-mono font-medium {{ $growthMetrics['signups_mom_percent'] >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                            {{ $growthMetrics['signups_mom_percent'] >= 0 ? '+' : '' }}{{ $growthMetrics['signups_mom_percent'] }}% MoM
                        </span>
                    </div>
                </div>
                <div class="mt-2 text-[10px] text-zinc-500 dark:text-zinc-400 border-t border-zinc-100 dark:border-zinc-800/80 pt-1.5 flex items-center justify-between">
                    <span>Yesterday: {{ number_format($growthMetrics['signups_yesterday']) }}</span>
                    <span class="text-zinc-500 dark:text-zinc-400 font-mono">MoM Signup Growth</span>
                </div>
            </div>

            <!-- 2. Signups (Month) -->
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-800/80 bg-white dark:bg-zinc-900/50 p-3.5 shadow-xs flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400">
                        <span class="uppercase tracking-wider font-semibold text-[11px]">Signups (Month)</span>
                        <span class="text-[10px] font-mono {{ $growthMetrics['signups_mom_change'] >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                            {{ $growthMetrics['signups_mom_change'] >= 0 ? '+' : '' }}{{ $growthMetrics['signups_mom_percent'] }}%
                        </span>
                    </div>
                    <div class="mt-1.5 text-2xl font-bold font-mono tracking-tight text-zinc-900 dark:text-white">
                        {{ number_format($growthMetrics['signups_this_month']) }}
                    </div>
                </div>
                <div class="mt-2 text-[10px] text-zinc-500 dark:text-zinc-400 border-t border-zinc-100 dark:border-zinc-800/80 pt-1.5 flex items-center justify-between">
                    <span>Prev: {{ number_format($growthMetrics['signups_last_month']) }}</span>
                    <span class="font-mono {{ $growthMetrics['signups_mom_change'] >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                        {{ $growthMetrics['signups_mom_change'] >= 0 ? '+' : '' }}{{ $growthMetrics['signups_mom_change'] }} users
                    </span>
                </div>
            </div>

            <!-- 3. New Organisations This Month -->
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-800/80 bg-white dark:bg-zinc-900/50 p-3.5 shadow-xs flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400">
                        <span class="uppercase tracking-wider font-semibold text-[11px]">New Orgs (Mo)</span>
                        <span class="text-[10px] font-mono {{ $growthMetrics['new_organizations_mom_change'] >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                            {{ $growthMetrics['new_organizations_mom_change'] >= 0 ? '+' : '' }}{{ $growthMetrics['new_organizations_mom_percent'] }}%
                        </span>
                    </div>
                    <div class="mt-1.5 text-2xl font-bold font-mono tracking-tight text-zinc-900 dark:text-white">
                        {{ number_format($growthMetrics['new_organizations_this_month']) }}
                    </div>
                </div>
                <div class="mt-2 text-[10px] text-zinc-500 dark:text-zinc-400 border-t border-zinc-100 dark:border-zinc-800/80 pt-1.5 flex items-center justify-between">
                    <span>Prev: {{ number_format($growthMetrics['new_organizations_last_month']) }}</span>
                    <span class="font-mono text-zinc-500 dark:text-zinc-400">Total: {{ number_format($metrics['total_organizations']) }}</span>
                </div>
            </div>

            <!-- 4. New Paid Creators & Conversion -->
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-800/80 bg-white dark:bg-zinc-900/50 p-3.5 shadow-xs flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400">
                        <span class="uppercase tracking-wider font-semibold text-[11px]">New Paid Creators</span>
                        <span class="text-[10px] font-mono text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800/60 px-1.5 py-0.2 rounded font-medium">
                            Paid Conversion: {{ $growthMetrics['signup_to_paid_conversion_rate'] }}%
                        </span>
                    </div>
                    <div class="mt-1.5 text-2xl font-bold font-mono tracking-tight text-emerald-600 dark:text-emerald-400">
                        {{ number_format($growthMetrics['new_paid_creators_this_month']) }}
                    </div>
                </div>
                <div class="mt-2 text-[10px] text-zinc-500 dark:text-zinc-400 border-t border-zinc-100 dark:border-zinc-800/80 pt-1.5 flex items-center justify-between">
                    <span>This month: {{ $growthMetrics['signup_to_paid_this_month_rate'] }}% conv</span>
                    <span class="font-mono text-emerald-600 dark:text-emerald-400 font-semibold">{{ $growthMetrics['funnel']['paid_creators']['count'] }}/{{ $growthMetrics['funnel']['users']['count'] }} all-time</span>
                </div>
            </div>
        </div>

        <!-- GROWTH CURVE & CONVERSION FUNNEL -->
        <div class="mt-4 grid grid-cols-1 lg:grid-cols-3 gap-4 min-w-0">
            <!-- Growth Curve Chart (2 cols) -->
            <div class="lg:col-span-2 rounded-xl border border-zinc-200 dark:border-zinc-800/80 bg-white dark:bg-zinc-900/40 p-4 sm:p-5 shadow-xs flex flex-col justify-between min-w-0">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-zinc-200 dark:border-zinc-800 pb-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm font-semibold uppercase tracking-wider text-zinc-800 dark:text-zinc-200">Signup Growth</h3>
                            <span class="text-[10px] text-zinc-400 dark:text-zinc-500 font-mono">{{ $growthCurve['range'] === '12m' ? 'Monthly buckets' : 'Daily buckets' }}</span>
                        </div>
                        <div class="mt-1.5 flex flex-wrap items-center gap-4 text-[11px]">
                            <span class="flex items-center gap-1.5 text-zinc-700 dark:text-zinc-300">
                                <span class="h-2 w-2 rounded-full bg-indigo-500 dark:bg-indigo-400"></span>
                                User Signups ({{ number_format($growthCurve['totals']['user_signups']) }})
                            </span>
                            <span class="flex items-center gap-1.5 text-zinc-700 dark:text-zinc-300">
                                <span class="h-2 w-2 rounded-full bg-emerald-500 dark:bg-emerald-400"></span>
                                Organisations ({{ number_format($growthCurve['totals']['organizations_created']) }})
                            </span>
                            <span class="flex items-center gap-1.5 text-zinc-700 dark:text-zinc-300">
                                <span class="h-2 w-2 rounded-full bg-amber-500 dark:bg-amber-400"></span>
                                First Paid Creators ({{ number_format($growthCurve['totals']['first_paid_conversions']) }})
                            </span>
                        </div>
                    </div>

                    <!-- Range Selector -->
                    <div class="inline-flex rounded-lg border border-zinc-200 dark:border-zinc-800 bg-zinc-100 dark:bg-zinc-950 p-0.5 text-xs self-start sm:self-auto">
                        @foreach (['7d' => '7 days', '30d' => '30 days', '90d' => '90 days', '12m' => '12 months'] as $rKey => $rLabel)
                            <a href="{{ request()->fullUrlWithQuery(['growth_range' => $rKey]) }}"
                               class="rounded-md px-2.5 py-1 font-medium transition {{ $growthRange === $rKey ? 'bg-white dark:bg-zinc-800 text-zinc-900 dark:text-white font-semibold shadow-xs border border-zinc-200/80 dark:border-transparent' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-200' }}">
                                {{ $rLabel }}
                            </a>
                        @endforeach
                    </div>
                </div>

                @php
                    $maxVal = max(1,
                        ...$growthCurve['series']['user_signups'],
                        ...$growthCurve['series']['organizations_created'],
                        ...$growthCurve['series']['first_paid_conversions']
                    );
                @endphp
                <div class="mt-4 overflow-x-auto min-w-0">
                    <div class="h-32 min-w-[360px] sm:min-w-0 flex items-end gap-1 sm:gap-1.5 pt-3 pb-1 border-b border-zinc-200 dark:border-zinc-800">
                        @foreach ($growthCurve['labels'] as $idx => $label)
                            @php
                                $uCount = $growthCurve['series']['user_signups'][$idx] ?? 0;
                                $oCount = $growthCurve['series']['organizations_created'][$idx] ?? 0;
                                $pCount = $growthCurve['series']['first_paid_conversions'][$idx] ?? 0;
                                $uPct = max(4, round(($uCount / $maxVal) * 100));
                                $oPct = max(4, round(($oCount / $maxVal) * 100));
                                $pPct = max(4, round(($pCount / $maxVal) * 100));
                            @endphp
                            <div class="flex-1 flex items-end justify-center gap-0.5 h-full group relative" title="{{ $label }} — Users: {{ $uCount }}, Orgs: {{ $oCount }}, First Paid: {{ $pCount }}">
                                <!-- User signups bar -->
                                <div class="w-full max-w-[8px] rounded-t transition-all {{ $uCount > 0 ? 'bg-indigo-500 hover:bg-indigo-400' : 'bg-zinc-200/70 dark:bg-zinc-800/40' }}" style="height: {{ $uPct }}%;"></div>
                                <!-- Organisation signups bar -->
                                <div class="w-full max-w-[8px] rounded-t transition-all {{ $oCount > 0 ? 'bg-emerald-500 hover:bg-emerald-400' : 'bg-zinc-200/70 dark:bg-zinc-800/40' }}" style="height: {{ $oPct }}%;"></div>
                                <!-- First paid conversions bar -->
                                <div class="w-full max-w-[8px] rounded-t transition-all {{ $pCount > 0 ? 'bg-amber-500 hover:bg-amber-400' : 'bg-zinc-200/70 dark:bg-zinc-800/40' }}" style="height: {{ $pPct }}%;"></div>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-2 min-w-[360px] sm:min-w-0 flex justify-between text-[10px] font-mono text-zinc-500 dark:text-zinc-400">
                        <span>{{ $growthCurve['labels'][0] ?? '' }}</span>
                        <span>{{ end($growthCurve['labels']) ?: '' }}</span>
                    </div>
                </div>
            </div>

            <!-- Conversion Funnel (1 col) -->
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-800/80 bg-white dark:bg-zinc-900/40 p-4 sm:p-5 shadow-xs flex flex-col justify-between min-w-0">
                <div>
                    <div class="flex items-center justify-between border-b border-zinc-200 dark:border-zinc-800 pb-3">
                        <h3 class="text-sm font-semibold uppercase tracking-wider text-zinc-800 dark:text-zinc-200">Acquisition Funnel</h3>
                        <span class="text-[10px] text-zinc-400 dark:text-zinc-500 font-mono">Conversion Stages</span>
                    </div>

                    <div class="mt-4 space-y-3">
                        <!-- Step 1: Users -->
                        <div class="rounded-lg bg-zinc-50 dark:bg-zinc-950/60 border border-zinc-200/80 dark:border-zinc-800 p-3">
                            <div class="flex items-center justify-between text-xs">
                                <div class="flex items-center gap-2">
                                    <span class="flex h-5 w-5 items-center justify-center rounded-full bg-indigo-100 dark:bg-indigo-500/20 text-[10px] font-bold text-indigo-700 dark:text-indigo-400">1</span>
                                    <span class="font-semibold text-zinc-800 dark:text-zinc-200">Registered Users</span>
                                </div>
                                <span class="font-mono font-bold text-zinc-900 dark:text-white text-sm">{{ number_format($growthMetrics['funnel']['users']['count']) }}</span>
                            </div>
                            <div class="mt-2 flex items-center justify-between text-[11px] text-zinc-500 dark:text-zinc-400">
                                <span>Signups this month: {{ number_format($growthMetrics['funnel']['users']['this_month']) }}</span>
                                <span class="font-mono text-indigo-600 dark:text-indigo-400 font-semibold">100% Base</span>
                            </div>
                        </div>

                        <!-- Down arrow -->
                        <div class="flex justify-center text-zinc-400 dark:text-zinc-600 -my-1">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                            </svg>
                        </div>

                        <!-- Step 2: Organisations -->
                        <div class="rounded-lg bg-zinc-50 dark:bg-zinc-950/60 border border-zinc-200/80 dark:border-zinc-800 p-3">
                            <div class="flex items-center justify-between text-xs">
                                <div class="flex items-center gap-2">
                                    <span class="flex h-5 w-5 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-500/20 text-[10px] font-bold text-emerald-700 dark:text-emerald-400">2</span>
                                    <span class="font-semibold text-zinc-800 dark:text-zinc-200">Organisations Created</span>
                                </div>
                                <span class="font-mono font-bold text-zinc-900 dark:text-white text-sm">{{ number_format($growthMetrics['funnel']['organizations']['count']) }}</span>
                            </div>
                            <div class="mt-2 flex items-center justify-between text-[11px] text-zinc-500 dark:text-zinc-400">
                                <span>New this month: {{ number_format($growthMetrics['funnel']['organizations']['this_month']) }}</span>
                                <span class="font-mono text-emerald-600 dark:text-emerald-400 font-semibold">{{ $growthMetrics['funnel']['organizations']['conversion_rate'] }}% of users</span>
                            </div>
                        </div>

                        <!-- Down arrow -->
                        <div class="flex justify-center text-zinc-400 dark:text-zinc-600 -my-1">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                            </svg>
                        </div>

                        <!-- Step 3: Paid Creator Subscriptions -->
                        <div class="rounded-lg bg-amber-50/50 dark:bg-zinc-950/60 border border-amber-200 dark:border-amber-900/40 p-3">
                            <div class="flex items-center justify-between text-xs">
                                <div class="flex items-center gap-2">
                                    <span class="flex h-5 w-5 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-500/20 text-[10px] font-bold text-amber-700 dark:text-amber-400">3</span>
                                    <span class="font-semibold text-amber-900 dark:text-amber-200">Paid Creator Subscriptions</span>
                                </div>
                                <span class="font-mono font-bold text-amber-700 dark:text-amber-400 text-sm">{{ number_format($growthMetrics['funnel']['paid_creators']['count']) }}</span>
                            </div>
                            <div class="mt-2 flex items-center justify-between text-[11px] text-zinc-500 dark:text-zinc-400">
                                <span>New this month: {{ number_format($growthMetrics['funnel']['paid_creators']['this_month']) }}</span>
                                <span class="font-mono text-amber-600 dark:text-amber-400 font-semibold">{{ $growthMetrics['funnel']['paid_creators']['conversion_rate'] }}% of users</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-zinc-200 dark:border-zinc-800 text-[11px] text-zinc-500 dark:text-zinc-400 flex items-center justify-between">
                    <span>Org-to-Paid Conversion</span>
                    <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400">{{ $growthMetrics['funnel']['paid_creators']['org_conversion_rate'] }}%</span>
                </div>
            </div>
        </div>
    </section>

    <!-- 3. LIVE OPERATIONS -->
    <section id="live-activity" class="scroll-mt-20">
        <div class="flex flex-wrap items-center justify-between gap-y-1.5 gap-x-2 mb-3">
            <div class="flex flex-wrap items-center gap-2">
                <span class="inline-flex h-2 w-2 rounded-full bg-emerald-500 dark:bg-emerald-400"></span>
                <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-800 dark:text-zinc-200">Live Operations <span class="text-xs font-normal text-zinc-500 dark:text-zinc-400">· Live Platform Activity</span></h2>
                @if ($metrics['live_now'] > 0)
                    <span class="rounded-full bg-emerald-50 dark:bg-emerald-500/20 px-2 py-0.5 text-xs font-bold text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-700/50 flex items-center gap-1.5">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 dark:bg-emerald-400 animate-ping"></span>
                        {{ $metrics['live_now'] }} Live Now
                    </span>
                @endif
            </div>
            <a href="{{ route('admin.streams.index') }}" class="text-xs text-zinc-500 dark:text-zinc-400 hover:text-emerald-600 dark:hover:text-emerald-400 transition font-medium">View all streams &rarr;</a>
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
                    <div class="rounded-xl border border-emerald-200 dark:border-emerald-900/50 bg-gradient-to-br from-emerald-50/50 via-white to-zinc-50 dark:from-emerald-950/30 dark:via-zinc-900/60 dark:to-zinc-900/60 p-4 shadow-xs flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 dark:bg-emerald-500/20 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-700/60">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 dark:bg-emerald-400 animate-pulse"></span>
                                    LIVE
                                </span>
                                <span class="text-[11px] font-mono text-zinc-500 dark:text-zinc-400">{{ $duration }}</span>
                            </div>
                            <h3 class="text-base font-semibold text-zinc-900 dark:text-white truncate" title="{{ $displayTitle }}">{{ $displayTitle }}</h3>
                            <p class="text-xs text-emerald-700 dark:text-emerald-300/80 truncate mt-0.5">
                                <a href="{{ route('admin.organizations.edit', $stream->organization) }}" class="hover:underline font-medium">{{ $stream->organization->name }}</a>
                            </p>

                            <div class="mt-4 flex items-center justify-between border-t border-zinc-100 dark:border-zinc-800/80 pt-3 text-xs">
                                <div>
                                    <span class="text-zinc-500 dark:text-zinc-400 block text-[10px] uppercase tracking-wider">Audience</span>
                                    <span class="font-semibold text-zinc-800 dark:text-zinc-200">{{ number_format($listeners) }} current listener{{ $listeners === 1 ? '' : 's' }}</span>
                                </div>
                                <div class="text-right">
                                    <span class="text-zinc-500 dark:text-zinc-400 block text-[10px] uppercase tracking-wider">Ingest</span>
                                    <span class="font-mono text-[11px] text-zinc-600 dark:text-zinc-400">{{ $stream->playbackMode() === 'hls' ? 'HLS + WHEP' : 'WHEP direct' }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 pt-3 border-t border-zinc-100 dark:border-zinc-800/80 flex items-center justify-between gap-2">
                            <a href="{{ route('admin.streams.edit', $stream) }}" class="flex-1 text-center rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-2.5 py-1.5 text-xs font-medium text-zinc-700 dark:text-zinc-200 hover:bg-zinc-50 dark:hover:bg-zinc-700 hover:text-zinc-900 dark:hover:text-white shadow-xs transition">
                                Inspect
                            </a>
                            <a href="{{ route('listen.stream', $stream) }}" target="_blank" rel="noopener noreferrer" class="rounded-lg border border-emerald-300 dark:border-emerald-800/60 bg-emerald-50 dark:bg-emerald-950/40 px-2.5 py-1.5 text-xs font-medium text-emerald-700 dark:text-emerald-300 hover:bg-emerald-100 dark:hover:bg-emerald-900/60 shadow-xs transition">
                                Listen
                            </a>
                            <a href="{{ route('admin.streams.studio', $stream) }}" target="_blank" rel="noopener noreferrer" class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-2.5 py-1.5 text-xs font-medium text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-700 shadow-xs transition">
                                Studio
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <!-- PUNCHY LIVE STREAMING EMPTY STATE -->
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-800/80 bg-white dark:bg-zinc-900/40 p-6 shadow-xs">
                <div class="flex flex-col sm:flex-row items-center sm:items-start justify-between gap-4">
                    <div class="flex items-start gap-4 text-center sm:text-left">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/60 shadow-xs">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 0 0 6-6v-1.5m-6 7.5a6 6 0 0 1-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 0 1-3-3V4.5a3 3 0 1 1 6 0v8.25a3 3 0 0 1-3 3Z" />
                            </svg>
                        </div>
                        <div>
                            <div class="flex items-center justify-center sm:justify-start gap-2">
                                <h3 class="text-sm font-semibold text-zinc-900 dark:text-white">No live broadcasts currently streaming</h3>
                                <span class="inline-flex items-center gap-1 rounded-full bg-zinc-100 dark:bg-zinc-800 px-2 py-0.5 text-[10px] font-mono text-zinc-600 dark:text-zinc-400">Nodes Standby</span>
                            </div>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1 max-w-xl">
                                Platform WHIP ingest and WHEP/HLS distribution nodes are active and standing by for incoming church and creator streams.
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <a href="{{ route('admin.streams.index') }}" class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-xs font-medium text-zinc-700 dark:text-zinc-200 hover:bg-zinc-50 dark:hover:bg-zinc-700 shadow-xs transition">
                            View All Streams
                        </a>
                        <a href="{{ route('admin.events.index') }}" class="rounded-lg border border-emerald-300 dark:border-emerald-800/60 bg-emerald-50 dark:bg-emerald-950/40 px-3 py-1.5 text-xs font-medium text-emerald-700 dark:text-emerald-300 hover:bg-emerald-100 dark:hover:bg-emerald-900/60 shadow-xs transition">
                            Schedule Event &rarr;
                        </a>
                    </div>
                </div>
            </div>
        @endif
    </section>

    <!-- 4. NEEDS ATTENTION -->
    <section id="needs-attention" class="scroll-mt-20">
        <div class="flex flex-wrap items-center justify-between gap-y-1.5 gap-x-2 mb-3">
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-800 dark:text-zinc-200">Needs Attention</h2>
                @if (count($attentionItems) > 0)
                    <span class="rounded-full bg-amber-50 dark:bg-amber-500/20 px-2 py-0.5 text-xs font-bold text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-600/40">
                        {{ array_sum(array_column($attentionItems, 'count')) }} action item{{ array_sum(array_column($attentionItems, 'count')) === 1 ? '' : 's' }}
                    </span>
                @endif
            </div>
            <span class="text-xs text-zinc-500 dark:text-zinc-400 hidden sm:inline-block">Actionable operational &amp; financial exceptions</span>
        </div>

        @if (count($attentionItems) > 0)
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach ($attentionItems as $item)
                    <div class="flex items-center justify-between rounded-xl border p-3.5 transition {{ $item['severity'] === 'critical' ? 'border-rose-200 dark:border-rose-900/60 bg-rose-50/70 dark:bg-rose-950/20 hover:bg-rose-100/60 dark:hover:bg-rose-950/30' : 'border-amber-200 dark:border-amber-900/60 bg-amber-50/70 dark:bg-amber-950/20 hover:bg-amber-100/60 dark:hover:bg-amber-950/30' }}">
                        <div class="flex items-start gap-3">
                            <span class="mt-0.5 flex h-2 w-2 shrink-0 rounded-full {{ $item['severity'] === 'critical' ? 'bg-rose-500 dark:bg-rose-400' : 'bg-amber-500 dark:bg-amber-400' }}"></span>
                            <div>
                                <p class="text-xs font-semibold text-zinc-900 dark:text-zinc-100">{{ $item['label'] }}</p>
                                <p class="text-[11px] text-zinc-500 dark:text-zinc-400 mt-0.5 capitalize">Priority: {{ $item['severity'] }}</p>
                            </div>
                        </div>
                        <a href="{{ $item['url'] }}" class="shrink-0 rounded-lg border px-2.5 py-1 text-xs font-medium transition shadow-xs {{ $item['severity'] === 'critical' ? 'border-rose-300 dark:border-rose-700 bg-white dark:bg-rose-900/50 text-rose-700 dark:text-rose-200 hover:bg-rose-50 dark:hover:bg-rose-800' : 'border-amber-300 dark:border-amber-700 bg-white dark:bg-amber-900/50 text-amber-700 dark:text-amber-200 hover:bg-amber-50 dark:hover:bg-amber-800' }}">
                            {{ $item['action_label'] }} &rarr;
                        </a>
                    </div>
                @endforeach
            </div>
        @else
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-800/80 bg-white dark:bg-zinc-900/40 p-4 text-center sm:text-left sm:flex sm:items-center sm:justify-between shadow-xs">
                <div class="flex items-center gap-3">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-50 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400 ring-1 ring-emerald-200 dark:ring-emerald-800">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                    </span>
                    <div>
                        <p class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">No items currently need attention</p>
                        <p class="text-[11px] text-zinc-500 dark:text-zinc-400">All creator queues, subscriptions, review queues, and background jobs are clear.</p>
                    </div>
                </div>
                <span class="hidden sm:inline-block text-[11px] font-mono font-medium text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/40 px-2 py-1 rounded border border-emerald-200 dark:border-emerald-900/60">Systems Normal</span>
            </div>
        @endif
    </section>

    <!-- 5. CREATOR & CUSTOMER HEALTH -->
    <section id="creator-health" class="scroll-mt-20">
        <div class="flex flex-wrap items-center justify-between gap-y-1.5 gap-x-2 mb-3">
            <div class="flex flex-wrap items-center gap-2">
                <span class="inline-flex h-2 w-2 rounded-full bg-blue-500 dark:bg-blue-400"></span>
                <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-800 dark:text-zinc-200">Creator &amp; Customer Health <span class="text-xs font-normal text-zinc-500 dark:text-zinc-400">· Subscription Performance</span></h2>
            </div>
            <a href="{{ route('admin.finance.subscriptions') }}" class="text-xs text-blue-600 dark:text-blue-400 hover:underline flex items-center gap-1 font-medium">
                Subscription Matrix &rarr;
            </a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 min-w-0">
            <!-- Subscription Performance 4-Pack (2 cols) -->
            <div class="lg:col-span-2 rounded-xl border border-zinc-200 dark:border-zinc-800/80 bg-white dark:bg-zinc-900/40 p-4 shadow-xs flex flex-col justify-between min-w-0">
                <div>
                    <div class="flex items-center justify-between mb-3 border-b border-zinc-100 dark:border-zinc-800/80 pb-2.5">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-zinc-800 dark:text-zinc-200">Subscription Performance</h3>
                        <span class="text-[10px] text-zinc-500 dark:text-zinc-400 font-mono">Current Month Lifecycle</span>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 text-xs">
                        <div class="p-3 rounded-lg bg-zinc-50 dark:bg-zinc-950/50 border border-zinc-200/80 dark:border-zinc-800/80">
                            <span class="text-zinc-500 dark:text-zinc-400 block text-[10px] font-semibold uppercase tracking-wider">New Paid</span>
                            <span class="text-xl font-bold text-emerald-600 dark:text-emerald-400 font-mono">{{ number_format($subscriptionPerformance['new_this_month']) }}</span>
                            <span class="text-[10px] text-zinc-400 dark:text-zinc-500 block mt-0.5">New creator subs</span>
                        </div>
                        <div class="p-3 rounded-lg bg-zinc-50 dark:bg-zinc-950/50 border border-zinc-200/80 dark:border-zinc-800/80">
                            <span class="text-zinc-500 dark:text-zinc-400 block text-[10px] font-semibold uppercase tracking-wider">Renewals</span>
                            <span class="text-xl font-bold text-zinc-900 dark:text-white font-mono">{{ number_format($subscriptionPerformance['renewals']) }}</span>
                            <span class="text-[10px] text-zinc-400 dark:text-zinc-500 block mt-0.5">Retained renewals</span>
                        </div>
                        <div class="p-3 rounded-lg bg-zinc-50 dark:bg-zinc-950/50 border border-zinc-200/80 dark:border-zinc-800/80">
                            <span class="text-zinc-500 dark:text-zinc-400 block text-[10px] font-semibold uppercase tracking-wider">Cancellations</span>
                            <span class="text-xl font-bold text-zinc-600 dark:text-zinc-300 font-mono">{{ number_format($subscriptionPerformance['cancelled_this_month']) }}</span>
                            <span class="text-[10px] text-zinc-400 dark:text-zinc-500 block mt-0.5">Voluntary churn</span>
                        </div>
                        <div class="p-3 rounded-lg {{ $financeOverview['failed_checkouts_count'] > 0 ? 'bg-rose-50/70 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-900/60' : 'bg-zinc-50 dark:bg-zinc-950/50 border border-zinc-200/80 dark:border-zinc-800/80' }}">
                            <span class="text-zinc-500 dark:text-zinc-400 block text-[10px] font-semibold uppercase tracking-wider">Failed Renewals</span>
                            <span class="text-xl font-bold {{ $financeOverview['failed_checkouts_count'] > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-zinc-600 dark:text-zinc-300' }} font-mono">{{ number_format($financeOverview['failed_checkouts_count']) }}</span>
                            <span class="text-[10px] text-zinc-400 dark:text-zinc-500 block mt-0.5">Billing failures</span>
                        </div>
                    </div>
                </div>

                <div class="mt-3 pt-2.5 border-t border-zinc-100 dark:border-zinc-800/80 flex items-center justify-between text-xs">
                    <span class="text-[11px] text-zinc-500 dark:text-zinc-400">Total subscribed orgs: <span class="font-mono font-semibold text-zinc-700 dark:text-zinc-300">{{ number_format($subscriptionPerformance['total']) }}</span></span>
                    <span class="text-[10px] font-mono text-zinc-400 dark:text-zinc-500">Daily payment checkouts active</span>
                </div>
            </div>

            <!-- Customer Account State (1 col) -->
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-800/80 bg-white dark:bg-zinc-900/40 p-4 shadow-xs flex flex-col justify-between min-w-0">
                <div>
                    <div class="flex items-center justify-between mb-3 border-b border-zinc-100 dark:border-zinc-800/80 pb-2.5">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-zinc-800 dark:text-zinc-200">Customer Base Health</h3>
                        <span class="text-[10px] text-zinc-500 dark:text-zinc-400 font-mono">Overview</span>
                    </div>

                    <div class="space-y-2.5">
                        <div class="flex items-center justify-between p-2.5 rounded-lg bg-zinc-50 dark:bg-zinc-950/50 border border-zinc-200/80 dark:border-zinc-800/80 text-xs">
                            <span class="text-zinc-600 dark:text-zinc-400">Active Paid Organisations</span>
                            <span class="font-mono font-bold text-zinc-900 dark:text-white">{{ number_format($financeOverview['active_paid_subscriptions_count']) }}</span>
                        </div>
                        <div class="flex items-center justify-between p-2.5 rounded-lg {{ $financeOverview['past_due_subscriptions_count'] > 0 ? 'bg-rose-50/70 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-900/60' : 'bg-zinc-50 dark:bg-zinc-950/50 border border-zinc-200/80 dark:border-zinc-800/80' }} text-xs">
                            <span class="{{ $financeOverview['past_due_subscriptions_count'] > 0 ? 'text-rose-700 dark:text-rose-300 font-medium' : 'text-zinc-600 dark:text-zinc-400' }}">Past Due Subscriptions</span>
                            <span class="font-mono font-bold {{ $financeOverview['past_due_subscriptions_count'] > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-zinc-900 dark:text-white' }}">{{ number_format($financeOverview['past_due_subscriptions_count']) }}</span>
                        </div>
                        <div class="flex items-center justify-between p-2.5 rounded-lg bg-zinc-50 dark:bg-zinc-950/50 border border-zinc-200/80 dark:border-zinc-800/80 text-xs">
                            <span class="text-zinc-600 dark:text-zinc-400">Total Registered Accounts</span>
                            <span class="font-mono font-bold text-zinc-900 dark:text-white">{{ number_format($metrics['total_users']) }}</span>
                        </div>
                    </div>
                </div>

                <div class="mt-3 pt-2.5 border-t border-zinc-100 dark:border-zinc-800/80 flex items-center justify-between text-xs">
                    <a href="{{ route('admin.organizations.index') }}" class="text-blue-600 dark:text-blue-400 hover:underline text-[11px] font-medium">Manage organisations &rarr;</a>
                    <a href="{{ route('admin.users.index') }}" class="text-zinc-500 dark:text-zinc-400 hover:underline text-[11px]">Manage users &rarr;</a>
                </div>
            </div>
        </div>
    </section>

    <!-- 6. BUSINESS SNAPSHOT (Platform Business Overview) -->
    <section id="business-snapshot" class="scroll-mt-20">
        <div class="flex flex-wrap items-center justify-between gap-y-1.5 gap-x-2 mb-3">
            <div class="flex flex-wrap items-center gap-2">
                <span class="inline-flex h-2 w-2 rounded-full bg-emerald-500 dark:bg-emerald-400"></span>
                <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-800 dark:text-zinc-200">Business Snapshot <span class="text-xs font-normal text-zinc-500 dark:text-zinc-400">· Platform Business Overview</span></h2>
                <span class="text-[10px] font-mono bg-emerald-50 dark:bg-emerald-950/70 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/60 px-1.5 py-0.5 rounded">NGN Ledger</span>
            </div>
            <a href="{{ route('admin.finance.index') }}" class="text-xs text-emerald-600 dark:text-emerald-400 hover:underline flex items-center gap-1 font-medium">
                View Finance Hub &rarr;
            </a>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-2 xl:grid-cols-4 gap-3">
            <!-- 1. MRR -->
            <a href="{{ route('admin.finance.subscriptions') }}" class="group rounded-xl border border-zinc-200 dark:border-zinc-800/80 bg-white dark:bg-zinc-900/50 p-3.5 shadow-xs hover:border-emerald-500/60 dark:hover:border-emerald-700/80 hover:shadow-sm transition flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400">
                        <span class="uppercase tracking-wider font-semibold text-[11px]">MRR (NGN)</span>
                        <div class="h-6 w-6 rounded-md bg-zinc-100 dark:bg-zinc-800/80 text-emerald-600 dark:text-emerald-400 border border-zinc-200/80 dark:border-zinc-700/60 flex items-center justify-center shrink-0">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-1.5 text-xl font-bold font-mono tracking-tight text-emerald-600 dark:text-emerald-400 group-hover:text-emerald-700 dark:group-hover:text-emerald-300 transition">
                        &#8358;{{ number_format($financeOverview['current_mrr_minor'] / 100, 2) }}
                    </div>
                </div>
                <div class="mt-2 text-[10px] text-zinc-500 dark:text-zinc-400 border-t border-zinc-100 dark:border-zinc-800/80 pt-1.5 flex items-center justify-between">
                    <span>Active base</span>
                    <span class="text-emerald-600 dark:text-emerald-400 group-hover:translate-x-0.5 transition-transform">&rarr;</span>
                </div>
            </a>

            <!-- 2. Revenue This Month -->
            <a href="{{ route('admin.finance.payments') }}" class="group rounded-xl border border-zinc-200 dark:border-zinc-800/80 bg-white dark:bg-zinc-900/50 p-3.5 shadow-xs hover:border-emerald-500/60 dark:hover:border-emerald-700/80 hover:shadow-sm transition flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400">
                        <span class="uppercase tracking-wider font-semibold text-[11px]">Revenue (Mo)</span>
                        <span class="text-[10px] font-mono {{ $financeOverview['revenue_mom_change_minor'] >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                            {{ $financeOverview['revenue_mom_change_minor'] >= 0 ? '+' : '' }}{{ $financeOverview['revenue_mom_percent'] }}%
                        </span>
                    </div>
                    <div class="mt-1.5 text-xl font-bold font-mono tracking-tight text-zinc-900 dark:text-white group-hover:text-emerald-600 dark:group-hover:text-emerald-300 transition">
                        &#8358;{{ number_format($financeOverview['revenue_this_month_minor'] / 100, 2) }}
                    </div>
                </div>
                <div class="mt-2 text-[10px] text-zinc-500 dark:text-zinc-400 border-t border-zinc-100 dark:border-zinc-800/80 pt-1.5 flex items-center justify-between">
                    <span>Prev: &#8358;{{ number_format($financeOverview['revenue_last_month_minor'] / 100, 0) }}</span>
                    <span class="text-emerald-600 dark:text-emerald-400 group-hover:translate-x-0.5 transition-transform">&rarr;</span>
                </div>
            </a>

            <!-- 3. Outstanding Invoices / Collection Risk -->
            <a href="{{ route('admin.finance.invoices', ['status' => 'past_due']) }}" class="group rounded-xl border border-zinc-200 dark:border-zinc-800/80 bg-white dark:bg-zinc-900/50 p-3.5 shadow-xs hover:border-emerald-500/60 dark:hover:border-emerald-700/80 hover:shadow-sm transition flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400">
                        <span class="uppercase tracking-wider font-semibold text-[11px]">Invoices Due</span>
                        <span class="text-[10px] font-mono bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700 px-1 py-0.2 rounded">{{ $financeOverview['outstanding_invoices_count'] }}</span>
                    </div>
                    <div class="mt-1.5 text-xl font-bold font-mono tracking-tight {{ $financeOverview['outstanding_invoices_minor'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-zinc-900 dark:text-white' }} transition">
                        &#8358;{{ number_format($financeOverview['outstanding_invoices_minor'] / 100, 2) }}
                    </div>
                </div>
                <div class="mt-2 text-[10px] text-zinc-500 dark:text-zinc-400 border-t border-zinc-100 dark:border-zinc-800/80 pt-1.5 flex items-center justify-between">
                    <span>Collection risk</span>
                    <span class="text-emerald-600 dark:text-emerald-400 group-hover:translate-x-0.5 transition-transform">&rarr;</span>
                </div>
            </a>

            <!-- 4. Affiliate Payable (Liability) -->
            <a href="{{ route('admin.finance.affiliate-liabilities') }}" class="group rounded-xl border border-zinc-200 dark:border-zinc-800/80 bg-white dark:bg-zinc-900/50 p-3.5 shadow-xs hover:border-amber-500/60 dark:hover:border-amber-700/80 hover:shadow-sm transition flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400">
                        <span class="uppercase tracking-wider font-semibold text-[11px]">Affiliate Payable</span>
                        <span class="text-[10px] font-mono font-medium text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-950/50 border border-amber-200 dark:border-amber-800/60 px-1.5 py-0.2 rounded">Liability</span>
                    </div>
                    <div class="mt-1.5 text-xl font-bold font-mono tracking-tight text-amber-600 dark:text-amber-400 group-hover:text-amber-700 dark:group-hover:text-amber-300 transition">
                        &#8358;{{ number_format($financeOverview['affiliate_payable_minor'] / 100, 2) }}
                    </div>
                </div>
                <div class="mt-2 text-[10px] text-zinc-500 dark:text-zinc-400 border-t border-zinc-100 dark:border-zinc-800/80 pt-1.5 flex items-center justify-between">
                    <span>Due for payout</span>
                    <span class="text-amber-600 dark:text-amber-400 group-hover:translate-x-0.5 transition-transform">&rarr;</span>
                </div>
            </a>
        </div>
    </section>

    <!-- 7. CONTENT & COMMERCE -->
    <div id="content-commerce" class="space-y-6">
        <!-- Marketplace · Digital Library -->
        <section id="marketplace-activity" class="scroll-mt-20">
            <div class="flex flex-wrap items-center justify-between gap-y-1.5 gap-x-2 mb-3">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-800 dark:text-zinc-200">Content &amp; Commerce <span class="text-xs font-normal text-zinc-500 dark:text-zinc-400">· Marketplace &middot; Digital Library</span></h2>
                </div>
                <a href="{{ route('admin.digital-library.overview') }}" class="text-xs text-emerald-600 dark:text-emerald-400 hover:underline flex items-center gap-1 font-medium">Marketplace Hub &rarr;</a>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <!-- GMV -->
                <a href="{{ route('admin.digital-library.purchases.index') }}" class="group rounded-xl border border-zinc-200 dark:border-zinc-800/80 bg-white dark:bg-zinc-900/50 p-3.5 shadow-xs hover:border-emerald-500/60 dark:hover:border-emerald-700/80 hover:shadow-sm transition flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400">
                            <span class="uppercase tracking-wider font-semibold text-[11px]">Digital Library GMV (NGN)</span>
                            <span class="text-[10px] font-mono bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700 px-1.5 py-0.5 rounded">Gross NGN</span>
                        </div>
                        <div class="mt-1.5 text-2xl font-bold font-mono tracking-tight text-emerald-600 dark:text-emerald-400 group-hover:text-emerald-700 dark:group-hover:text-emerald-300 transition">
                            &#8358;{{ number_format($metrics['dl_gross_collected_minor'] / 100, 2) }}
                        </div>
                    </div>
                    <div class="mt-2 text-[11px] text-zinc-500 dark:text-zinc-400 border-t border-zinc-100 dark:border-zinc-800/80 pt-1.5 flex items-center justify-between">
                        <span>Net after refunds: &#8358;{{ number_format($metrics['dl_net_collected_minor'] / 100, 2) }}</span>
                        <span class="text-emerald-600 dark:text-emerald-400 group-hover:translate-x-0.5 transition-transform">&rarr;</span>
                    </div>
                </a>

                <!-- Library Purchases -->
                <a href="{{ route('admin.digital-library.purchases.index') }}" class="group rounded-xl border border-zinc-200 dark:border-zinc-800/80 bg-white dark:bg-zinc-900/50 p-3.5 shadow-xs hover:border-emerald-500/60 dark:hover:border-emerald-700/80 hover:shadow-sm transition flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400">
                            <span class="uppercase tracking-wider font-semibold text-[11px]">Library Purchases</span>
                            <div class="h-6 w-6 rounded-md bg-zinc-100 dark:bg-zinc-800/80 text-zinc-500 dark:text-zinc-400 flex items-center justify-center shrink-0 border border-zinc-200/80 dark:border-zinc-700/60">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                                </svg>
                            </div>
                        </div>
                        <div class="mt-1.5 text-2xl font-bold font-mono tracking-tight text-zinc-900 dark:text-white group-hover:text-emerald-600 dark:group-hover:text-emerald-300 transition">
                            {{ number_format($metrics['dl_purchases']) }}
                        </div>
                    </div>
                    <div class="mt-2 text-[11px] text-zinc-500 dark:text-zinc-400 border-t border-zinc-100 dark:border-zinc-800/80 pt-1.5 flex items-center justify-between">
                        <span>Paid transactions</span>
                        <span class="text-emerald-600 dark:text-emerald-400 group-hover:translate-x-0.5 transition-transform">&rarr;</span>
                    </div>
                </a>

                <!-- Sellers -->
                <a href="{{ route('admin.digital-library.sellers.index') }}" class="group rounded-xl border border-zinc-200 dark:border-zinc-800/80 bg-white dark:bg-zinc-900/50 p-3.5 shadow-xs hover:border-emerald-500/60 dark:hover:border-emerald-700/80 hover:shadow-sm transition flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400">
                            <span class="uppercase tracking-wider font-semibold text-[11px]">Library Sellers</span>
                            @if ($metrics['pending_sellers'] > 0)
                                <span class="rounded bg-amber-50 dark:bg-amber-500/20 px-1.5 py-0.2 text-[10px] font-bold text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-700/50">{{ $metrics['pending_sellers'] }} pending</span>
                            @endif
                        </div>
                        <div class="mt-1.5 text-2xl font-bold font-mono tracking-tight text-zinc-900 dark:text-white group-hover:text-emerald-600 dark:group-hover:text-emerald-300 transition">
                            {{ number_format($metrics['approved_sellers']) }}
                        </div>
                    </div>
                    <div class="mt-2 text-[11px] text-zinc-500 dark:text-zinc-400 border-t border-zinc-100 dark:border-zinc-800/80 pt-1.5 flex items-center justify-between">
                        <span>{{ $metrics['pending_sellers'] }} application{{ $metrics['pending_sellers'] === 1 ? '' : 's' }} pending</span>
                        <span class="text-emerald-600 dark:text-emerald-400 group-hover:translate-x-0.5 transition-transform">&rarr;</span>
                    </div>
                </a>
            </div>
        </section>

        <!-- Affiliate Programme & Liabilities -->
        <section id="affiliate-activity" class="scroll-mt-20">
            <div class="flex flex-wrap items-center justify-between gap-y-1.5 gap-x-2 mb-3">
                <h3 class="text-sm font-semibold uppercase tracking-wider text-zinc-800 dark:text-zinc-200">Affiliate Programme &amp; Liabilities</h3>
                <a href="{{ route('admin.affiliate-programme.overview') }}" class="text-xs text-emerald-600 dark:text-emerald-400 hover:underline flex items-center gap-1 font-medium">Affiliate Hub &rarr;</a>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Active Affiliates -->
                <a href="{{ route('admin.affiliates.index') }}" class="group rounded-xl border border-zinc-200 dark:border-zinc-800/80 bg-white dark:bg-zinc-900/50 p-3.5 shadow-xs hover:border-emerald-500/60 dark:hover:border-emerald-700/80 hover:shadow-sm transition flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400">
                            <span class="uppercase tracking-wider font-semibold text-[11px]">Active Affiliates</span>
                            @if ($metrics['pending_affiliates'] > 0)
                                <span class="rounded bg-amber-50 dark:bg-amber-500/20 px-1.5 py-0.2 text-[10px] font-bold text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-700/50">{{ $metrics['pending_affiliates'] }} pending</span>
                            @endif
                        </div>
                        <div class="mt-1.5 text-2xl font-bold font-mono tracking-tight text-zinc-900 dark:text-white group-hover:text-emerald-600 dark:group-hover:text-emerald-300 transition">
                            {{ number_format($metrics['approved_affiliates']) }}
                        </div>
                    </div>
                    <div class="mt-2 text-[11px] text-zinc-500 dark:text-zinc-400 border-t border-zinc-100 dark:border-zinc-800/80 pt-1.5 flex items-center justify-between">
                        <span>{{ $metrics['pending_affiliates'] }} pending review</span>
                        <span class="text-emerald-600 dark:text-emerald-400 group-hover:translate-x-0.5 transition-transform">&rarr;</span>
                    </div>
                </a>

                <!-- Held Commissions -->
                <a href="{{ route('admin.finance.affiliate-liabilities') }}" class="group rounded-xl border border-zinc-200 dark:border-zinc-800/80 bg-white dark:bg-zinc-900/50 p-3.5 shadow-xs hover:border-emerald-500/60 dark:hover:border-emerald-700/80 hover:shadow-sm transition flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400">
                            <span class="uppercase tracking-wider font-semibold text-[11px]">Held Commissions</span>
                            <span class="text-[10px] font-mono text-zinc-600 dark:text-zinc-400 bg-zinc-100 dark:bg-zinc-800 px-1.5 py-0.2 rounded border border-zinc-200 dark:border-zinc-700">Unvested</span>
                        </div>
                        <div class="mt-1.5 text-2xl font-bold font-mono tracking-tight text-zinc-900 dark:text-white group-hover:text-emerald-600 dark:group-hover:text-emerald-300 transition">
                            &#8358;{{ number_format($financeOverview['affiliate_held_minor'] / 100, 2) }}
                        </div>
                    </div>
                    <div class="mt-2 text-[11px] text-zinc-500 dark:text-zinc-400 border-t border-zinc-100 dark:border-zinc-800/80 pt-1.5 flex items-center justify-between">
                        <span>In holding window</span>
                        <span class="text-emerald-600 dark:text-emerald-400 group-hover:translate-x-0.5 transition-transform">&rarr;</span>
                    </div>
                </a>

                <!-- Payable Commissions -->
                <a href="{{ route('admin.finance.affiliate-liabilities') }}" class="group rounded-xl border border-zinc-200 dark:border-zinc-800/80 bg-white dark:bg-zinc-900/50 p-3.5 shadow-xs hover:border-amber-500/60 dark:hover:border-amber-700/80 hover:shadow-sm transition flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400">
                            <span class="uppercase tracking-wider font-semibold text-[11px]">Payable Commissions</span>
                            <span class="text-[10px] font-mono font-medium text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-950/50 border border-amber-200 dark:border-amber-800/60 px-1.5 py-0.2 rounded">Liability</span>
                        </div>
                        <div class="mt-1.5 text-2xl font-bold font-mono tracking-tight text-amber-600 dark:text-amber-400 group-hover:text-amber-700 dark:group-hover:text-amber-300 transition">
                            &#8358;{{ number_format($financeOverview['affiliate_payable_minor'] / 100, 2) }}
                        </div>
                    </div>
                    <div class="mt-2 text-[11px] text-zinc-500 dark:text-zinc-400 border-t border-zinc-100 dark:border-zinc-800/80 pt-1.5 flex items-center justify-between">
                        <span>Due for payout</span>
                        <span class="text-amber-600 dark:text-amber-400 group-hover:translate-x-0.5 transition-transform">&rarr;</span>
                    </div>
                </a>

                <!-- Paid This Month -->
                <a href="{{ route('admin.affiliate-payouts.index') }}" class="group rounded-xl border border-zinc-200 dark:border-zinc-800/80 bg-white dark:bg-zinc-900/50 p-3.5 shadow-xs hover:border-emerald-500/60 dark:hover:border-emerald-700/80 hover:shadow-sm transition flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400">
                            <span class="uppercase tracking-wider font-semibold text-[11px]">Paid This Month</span>
                            <span class="text-[10px] font-mono font-medium text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800/60 px-1.5 py-0.2 rounded">Disbursed</span>
                        </div>
                        <div class="mt-1.5 text-2xl font-bold font-mono tracking-tight text-zinc-900 dark:text-white group-hover:text-emerald-600 dark:group-hover:text-emerald-300 transition">
                            &#8358;{{ number_format($financeOverview['affiliate_paid_this_month_minor'] / 100, 2) }}
                        </div>
                    </div>
                    <div class="mt-2 text-[11px] text-zinc-500 dark:text-zinc-400 border-t border-zinc-100 dark:border-zinc-800/80 pt-1.5 flex items-center justify-between">
                        <span>Settled payouts</span>
                        <span class="text-emerald-600 dark:text-emerald-400 group-hover:translate-x-0.5 transition-transform">&rarr;</span>
                    </div>
                </a>
            </div>
        </section>
    </div>

    <!-- 8. PLATFORM HEALTH & INGEST DELIVERY -->
    <section id="platform-health" class="scroll-mt-20">
        <div class="flex flex-wrap items-center justify-between gap-y-1.5 gap-x-2 mb-3">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-800 dark:text-zinc-200">Platform Health &amp; Ingest Delivery</h2>
            <a href="{{ route('admin.settings.edit') }}" class="text-xs text-emerald-600 dark:text-emerald-400 hover:underline flex items-center gap-1 font-medium">Platform settings &rarr;</a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Media Delivery Pipeline -->
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-800/80 bg-white dark:bg-zinc-900/40 p-4 sm:p-5 shadow-xs">
                <div class="flex items-center justify-between text-xs">
                    <span class="font-semibold text-zinc-700 dark:text-zinc-300 uppercase tracking-wider text-[11px]">Playback Delivery</span>
                    <span class="inline-flex items-center gap-1 rounded bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 px-2 py-0.5 font-mono text-[10px] text-zinc-700 dark:text-zinc-300">
                        {{ $listenPlaybackStatus['effective_mode'] }}
                    </span>
                </div>
                <div class="mt-3 flex items-baseline gap-2">
                    <span class="text-lg font-bold font-mono tracking-tight text-zinc-900 dark:text-white">{{ $listenPlaybackStatus['effective_label'] }}</span>
                </div>
                <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400 leading-relaxed">
                    Source: <span class="text-zinc-700 dark:text-zinc-200 font-medium">{{ $listenPlaybackStatus['policy_source_label'] }}</span>
                </p>
                <div class="mt-3 text-[11px] text-zinc-500 dark:text-zinc-400 border-t border-zinc-100 dark:border-zinc-800/80 pt-2 flex items-center justify-between">
                    <span>CDN: {{ $listenPlaybackStatus['cdn_host'] ?: 'Local Ingest Node' }}</span>
                    <span>AAC Sidecar: {{ $listenPlaybackStatus['aac_sidecar'] ? 'On' : 'Off' }}</span>
                </div>
            </div>

            <!-- Host Infrastructure Capacity State -->
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-800/80 bg-white dark:bg-zinc-900/40 p-4 sm:p-5 shadow-xs">
                <div class="flex items-center justify-between text-xs">
                    <span class="font-semibold text-zinc-700 dark:text-zinc-300 uppercase tracking-wider text-[11px]">Infrastructure Capacity</span>
                    <span class="inline-flex items-center gap-1.5 rounded border border-zinc-200 dark:border-zinc-700 bg-zinc-100 dark:bg-zinc-800 px-2 py-0.5 text-[10px] {{ $vmSnapshot['desired'] === 'peak' ? 'text-amber-700 dark:text-amber-400 font-bold' : 'text-zinc-600 dark:text-zinc-400' }}">
                        {{ strtoupper($vmSnapshot['desired']) }}
                    </span>
                </div>
                <div class="mt-3 flex items-baseline gap-2">
                    <span class="text-lg font-bold font-mono tracking-tight text-zinc-900 dark:text-white">{{ $vmSnapshot['desired_sku'] }}</span>
                    <span class="text-xs text-zinc-500 font-mono">({{ $vmSnapshot['reason'] }})</span>
                </div>
                <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400 leading-relaxed">
                    Live streams: <span class="text-zinc-700 dark:text-zinc-200 font-mono font-medium">{{ $vmSnapshot['live_streams'] }}</span> &middot; Upcoming (30m): <span class="text-zinc-700 dark:text-zinc-200 font-mono font-medium">{{ $vmSnapshot['upcoming_events'] }}</span>
                </p>
                <div class="mt-3 text-[11px] text-zinc-500 dark:text-zinc-400 border-t border-zinc-100 dark:border-zinc-800/80 pt-2">
                    Hold peak: {{ !empty($vmSnapshot['hold_peak_until']) ? 'Held until ' . \App\Support\LocalTime::format(\Illuminate\Support\Carbon::parse($vmSnapshot['hold_peak_until']), 'H:i') : 'Auto (dynamic)' }}
                </div>
            </div>

            <!-- Payment Gateway Connectivity -->
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-800/80 bg-white dark:bg-zinc-900/40 p-4 sm:p-5 shadow-xs">
                <div class="flex items-center justify-between text-xs">
                    <span class="font-semibold text-zinc-700 dark:text-zinc-300 uppercase tracking-wider text-[11px]">Payment Gateway</span>
                    <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[10px] {{ $paystackEnabled ? 'bg-emerald-50 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 border border-zinc-200 dark:border-zinc-700' }}">
                        {{ $paystackEnabled ? 'Active' : 'Disabled' }}
                    </span>
                </div>
                <div class="mt-3 flex items-baseline gap-2">
                    <span class="text-lg font-bold font-mono tracking-tight text-zinc-900 dark:text-white">Payment Gateway API</span>
                </div>
                <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400 leading-relaxed">
                    Webhook endpoint: <code class="font-mono text-[10px] text-zinc-700 dark:text-zinc-300 bg-zinc-100 dark:bg-zinc-800 px-1 py-0.5 rounded">/webhooks/paystack</code>
                </p>
                <div class="mt-3 text-[11px] text-zinc-500 dark:text-zinc-400 border-t border-zinc-100 dark:border-zinc-800/80 pt-2">
                    Currency: <span class="text-zinc-800 dark:text-zinc-300 font-mono font-medium">{{ config('services.paystack.currency', 'NGN') }}</span>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
