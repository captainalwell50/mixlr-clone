@extends('layouts.creator')

@section('title', $organization->name . ' · Operations Dashboard')

@section('content')
<div class="space-y-8 max-w-7xl mx-auto">
    <!-- PAGE HEADER -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-zinc-800 pb-5">
        <div>
            <div class="flex items-center gap-2">
                <span class="inline-flex h-2 w-2 rounded-full bg-emerald-400"></span>
                <span class="text-xs font-semibold uppercase tracking-wider text-emerald-400">Creator Operations</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-white mt-1 break-words">{{ $organization->name }}</h1>
            <p class="text-xs text-zinc-400 mt-0.5">
                Channel URL:
                <a href="{{ $organization->channelUrl() }}" target="_blank" class="text-emerald-400 hover:text-emerald-300 underline underline-offset-2">
                    {{ parse_url($organization->channelUrl(), PHP_URL_HOST) ?: '/c/'.$organization->slug }}
                </a>
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if ($isOwner)
                @if ($subscription?->needsUpgradeCta() ?? true)
                    <a href="{{ route('billing.plans') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3.5 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-emerald-500 transition">
                        <span>Upgrade</span>
                    </a>
                @endif
                <a href="{{ route('billing.plans') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-1.5 text-xs font-medium text-zinc-300 hover:bg-zinc-700 hover:text-white transition">
                    <span>Manage billing</span>
                </a>
            @endif

            <a href="{{ $organization->channelUrl() }}" target="_blank" class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-700 bg-zinc-800/80 px-3 py-1.5 text-xs font-medium text-zinc-300 hover:bg-zinc-700 hover:text-white transition">
                <span>View channel</span>
                <svg class="h-3 w-3 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 19.5 15-15m0 0H8.25m11.25 0v11.25" />
                </svg>
            </a>
        </div>
    </div>

    <!-- ATTENTION / NEEDS ACTION ALERTS -->
    @if (! empty($attentionItems))
        <div class="space-y-3">
            @foreach ($attentionItems as $item)
                <div class="rounded-xl border p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 {{ $item['level'] === 'danger' ? 'border-red-500/50 bg-red-950/30 text-red-200' : ($item['level'] === 'warning' ? 'border-amber-500/50 bg-amber-950/30 text-amber-200' : 'border-emerald-500/40 bg-emerald-950/20 text-emerald-200') }}">
                    <div class="flex items-start gap-3">
                        <div class="mt-0.5 shrink-0">
                            @if ($item['level'] === 'danger')
                                <svg class="h-5 w-5 text-red-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                                </svg>
                            @elseif ($item['level'] === 'warning')
                                <svg class="h-5 w-5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                                </svg>
                            @else
                                <svg class="h-5 w-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                                </svg>
                            @endif
                        </div>
                        <div>
                            <h2 class="text-sm font-semibold">{{ $item['title'] }}</h2>
                            <p class="text-xs opacity-90 mt-0.5">{{ $item['message'] }}</p>
                        </div>
                    </div>

                    @if (! empty($item['action_url']))
                        <a
                            href="{{ $item['action_url'] }}"
                            class="inline-flex items-center justify-center shrink-0 rounded-lg px-3 py-1.5 text-xs font-semibold shadow-sm transition {{ $item['level'] === 'danger' ? 'bg-red-600 hover:bg-red-500 text-white' : ($item['level'] === 'warning' ? 'bg-amber-600 hover:bg-amber-500 text-white' : 'bg-emerald-600 hover:bg-emerald-500 text-white') }}"
                        >
                            {{ $item['action_label'] }}
                        </a>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    <!-- HERO BROADCAST STATUS PANEL -->
    <div class="rounded-2xl border p-6 transition-all {{ $isLive ? 'border-red-500/50 bg-gradient-to-br from-red-950/40 via-zinc-900 to-zinc-950 ring-1 ring-red-500/20 shadow-lg shadow-red-950/20' : 'border-zinc-800 bg-gradient-to-br from-zinc-900/90 to-zinc-950' }}">
        @if ($isLive)
            <!-- LIVE BROADCAST ACTIVE STATE -->
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                <div class="space-y-3">
                    <div class="flex items-center gap-3">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-zinc-400">Current Broadcast Status:</span>
                        <span class="inline-flex items-center gap-2 rounded-full border border-red-500/50 bg-red-950/70 px-3 py-1 text-xs font-bold uppercase tracking-wider text-red-300">
                            <span class="relative flex h-2.5 w-2.5">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-500"></span>
                            </span>
                            <span>On Air Now</span>
                        </span>
                        <span class="text-xs text-zinc-400">
                            Started {{ $stream->started_at ? \App\Support\LocalTime::format($stream->started_at, 'H:i') : 'recently' }}
                        </span>
                    </div>

                    <div>
                        <h2 class="text-xl sm:text-2xl font-bold text-white tracking-tight">
                            {{ $openEvent?->title ?? $stream->title ?? 'Live Service' }}
                        </h2>
                        <p class="text-xs text-zinc-400 mt-1">
                            Your audio broadcast is currently being streamed to your public channel and connected listeners.
                        </p>
                    </div>

                    <div class="flex items-center gap-6 pt-1">
                        <div>
                            <span class="text-[11px] font-medium uppercase tracking-wider text-zinc-400">Current Listeners</span>
                            <div class="text-2xl font-bold font-mono text-red-300">{{ number_format($activeListeners) }}</div>
                        </div>
                        <div class="h-8 w-px bg-zinc-800"></div>
                        <div>
                            <span class="text-[11px] font-medium uppercase tracking-wider text-zinc-400">Endpoint</span>
                            <div class="text-sm font-semibold text-zinc-200">{{ $stream->title ?: 'Default Stream' }}</div>
                        </div>
                        @if ($openEvent)
                            <div class="h-8 w-px bg-zinc-800 hidden sm:block"></div>
                            <div class="hidden sm:block">
                                <span class="text-[11px] font-medium uppercase tracking-wider text-zinc-400">Access</span>
                                <div class="text-sm font-semibold text-zinc-200 capitalize">{{ $openEvent->access->value }}</div>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="flex flex-wrap lg:flex-col items-stretch gap-2.5 shrink-0">
                    @if ($studioUrl)
                        <a
                            href="{{ $studioUrl }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-emerald-950/40 hover:bg-emerald-500 transition"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 0 0 6-6v-1.5m-6 7.5a6 6 0 0 1-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 0 1-3-3V4.5a3 3 0 1 1 6 0v8.25a3 3 0 0 1-3 3Z" />
                            </svg>
                            <span>Return to Studio</span>
                        </a>
                    @endif

                    <a
                        href="{{ $organization->channelUrl() }}"
                        target="_blank"
                        class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-zinc-700 bg-zinc-800 px-4 py-2.5 text-xs font-semibold text-zinc-200 hover:bg-zinc-700 hover:text-white transition"
                    >
                        <span>Listen Live</span>
                        <svg class="h-3.5 w-3.5 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 19.5 15-15m0 0H8.25m11.25 0v11.25" />
                        </svg>
                    </a>

                    @if ($openEvent)
                        <a
                            href="{{ route('admin.events.edit', $openEvent) }}"
                            class="inline-flex items-center justify-center rounded-xl border border-zinc-800 bg-zinc-900/60 px-4 py-2 text-xs font-medium text-zinc-400 hover:text-zinc-200 transition"
                        >
                            Edit Event Details
                        </a>
                    @endif
                </div>
            </div>
        @else
            <!-- OFFLINE HERO STATE -->
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                <div class="space-y-3">
                    <div class="flex items-center gap-3">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-zinc-400">Current Broadcast Status:</span>
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-zinc-800 bg-zinc-900/80 px-2.5 py-1 text-xs font-semibold text-zinc-400">
                            <span class="h-2 w-2 rounded-full bg-zinc-600"></span>
                            <span>Studio Offline</span>
                        </span>
                        @if ($subscription)
                            <span class="text-xs text-zinc-500">
                                Plan: <span class="text-zinc-300 font-medium">{{ $subscription->plan?->name ?? 'Free Plan' }}</span>
                                @if ($subscription->isOnFreePeriod() && isset($freeDaysRemaining))
                                    · <span class="text-amber-400 font-medium">{{ $freeDaysRemaining > 0 ? $freeDaysRemaining.' DAYS LEFT' : 'ENDED' }}</span>
                                @endif
                            </span>
                        @endif
                    </div>

                    @if ($nextScheduledEvent)
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-semibold uppercase tracking-wider text-emerald-400">Next Scheduled Service</span>
                                <span class="rounded bg-zinc-800 px-1.5 py-0.2 text-[10px] text-zinc-300 uppercase font-semibold">
                                    {{ $nextScheduledEvent->access->value }}
                                </span>
                            </div>
                            <h2 class="text-xl sm:text-2xl font-bold text-white tracking-tight mt-0.5">
                                {{ $nextScheduledEvent->title }}
                            </h2>
                            <p class="text-xs text-zinc-400 mt-1 flex items-center gap-2">
                                <svg class="h-3.5 w-3.5 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                                <span>{{ \App\Support\LocalTime::format($nextScheduledEvent->scheduled_at, 'l, F j, Y · H:i') }}</span>
                                <span class="text-zinc-600">·</span>
                                <span class="text-emerald-400 font-medium">{{ $nextScheduledEvent->scheduled_at->diffForHumans() }}</span>
                            </p>
                        </div>
                    @else
                        <div>
                            <h2 class="text-xl sm:text-2xl font-bold text-white tracking-tight">
                                Ready to broadcast?
                            </h2>
                            <p class="text-xs text-zinc-400 mt-1 max-w-xl">
                                Go live directly using Web Studio with low-latency audio, or schedule your next church service with a shareable promotional link.
                            </p>
                        </div>
                    @endif
                </div>

                <div class="flex flex-wrap items-center gap-3 shrink-0">
                    @if ($studioUrl)
                        @if ($canBroadcast)
                            <a
                                href="{{ $studioUrl }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-emerald-950/40 hover:bg-emerald-500 transition focus:outline-none focus:ring-2 focus:ring-emerald-500"
                            >
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 0 0 6-6v-1.5m-6 7.5a6 6 0 0 1-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 0 1-3-3V4.5a3 3 0 1 1 6 0v8.25a3 3 0 0 1-3 3Z" />
                                </svg>
                                <span>Open Studio</span>
                            </a>
                        @else
                            <a
                                href="{{ route('billing.plans') }}"
                                class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-amber-600 px-5 py-3 text-sm font-bold text-white hover:bg-amber-500 transition"
                            >
                                <span>Upgrade to go on air</span>
                            </a>
                        @endif
                    @endif

                    <a
                        href="{{ route('admin.events.create') }}"
                        class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-zinc-700 bg-zinc-800 px-4 py-3 text-xs font-semibold text-zinc-200 hover:bg-zinc-700 hover:text-white transition"
                    >
                        <svg class="h-4 w-4 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        <span>Schedule Event</span>
                    </a>
                </div>
            </div>
        @endif
    </div>

    <!-- QUICK ACTIONS TOOLBAR -->
    <div>
        <h2 class="text-xs font-bold uppercase tracking-wider text-zinc-400 mb-3">Quick Actions</h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            @if ($studioUrl)
                <a
                    href="{{ $canBroadcast ? $studioUrl : route('billing.plans') }}"
                    @if ($canBroadcast) target="_blank" rel="noopener noreferrer" @endif
                    class="rounded-xl border border-zinc-800 bg-zinc-900/60 p-3 flex flex-col items-start gap-2 hover:border-emerald-600/60 hover:bg-zinc-900 transition group"
                >
                    <div class="h-8 w-8 rounded-lg bg-emerald-950/80 text-emerald-400 flex items-center justify-center ring-1 ring-emerald-700/50 group-hover:scale-105 transition-transform">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 0 0 6-6v-1.5m-6 7.5a6 6 0 0 1-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 0 1-3-3V4.5a3 3 0 1 1 6 0v8.25a3 3 0 0 1-3 3Z" />
                        </svg>
                    </div>
                    <div>
                        <div class="text-xs font-semibold text-white group-hover:text-emerald-300">Go Live</div>
                        <div class="text-[10px] text-zinc-500">Launch Web Studio</div>
                    </div>
                </a>
            @endif

            <a
                href="{{ route('admin.events.create') }}"
                class="rounded-xl border border-zinc-800 bg-zinc-900/60 p-3 flex flex-col items-start gap-2 hover:border-zinc-700 hover:bg-zinc-900 transition group"
            >
                <div class="h-8 w-8 rounded-lg bg-zinc-800 text-zinc-300 flex items-center justify-center ring-1 ring-zinc-700 group-hover:scale-105 transition-transform">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 9v7.5" />
                    </svg>
                </div>
                <div>
                    <div class="text-xs font-semibold text-white group-hover:text-zinc-200">Schedule</div>
                    <div class="text-[10px] text-zinc-500">New church service</div>
                </div>
            </a>

            <a
                href="{{ route('archive.index') }}"
                class="rounded-xl border border-zinc-800 bg-zinc-900/60 p-3 flex flex-col items-start gap-2 hover:border-zinc-700 hover:bg-zinc-900 transition group"
            >
                <div class="h-8 w-8 rounded-lg bg-zinc-800 text-zinc-300 flex items-center justify-center ring-1 ring-zinc-700 group-hover:scale-105 transition-transform">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m9 9 10.5-3m0 6.553v3.75a2.25 2.25 0 0 1-1.632 2.163l-1.32.377a1.803 1.803 0 1 1-.99-3.467l2.31-.66a.75.75 0 0 0 .582-.726V9.66a.75.75 0 0 0-.54-.723L9.75 6.375A.75.75 0 0 0 9 7.098v9.704a2.25 2.25 0 0 1-1.632 2.163l-1.32.377a1.803 1.803 0 1 1-.99-3.467l2.31-.66a.75.75 0 0 0 .582-.726V9Z" />
                    </svg>
                </div>
                <div>
                    <div class="text-xs font-semibold text-white group-hover:text-zinc-200">Podcasts</div>
                    <div class="text-[10px] text-zinc-500">Sermons & episodes</div>
                </div>
            </a>

            @if ($canSellLibrary)
                <a
                    href="{{ route('digital-library.sell.show', $organization) }}"
                    class="rounded-xl border border-zinc-800 bg-zinc-900/60 p-3 flex flex-col items-start gap-2 hover:border-zinc-700 hover:bg-zinc-900 transition group"
                >
                    <div class="h-8 w-8 rounded-lg bg-zinc-800 text-zinc-300 flex items-center justify-center ring-1 ring-zinc-700 group-hover:scale-105 transition-transform">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                        </svg>
                    </div>
                    <div>
                        <div class="text-xs font-semibold text-white group-hover:text-zinc-200">Digital Library</div>
                        <div class="text-[10px] text-zinc-500">Series & teachings</div>
                    </div>
                </a>
            @endif

            @if ($canManage)
                <a
                    href="{{ route('admin.organizations.customise', $organization) }}"
                    class="rounded-xl border border-zinc-800 bg-zinc-900/60 p-3 flex flex-col items-start gap-2 hover:border-zinc-700 hover:bg-zinc-900 transition group"
                >
                    <div class="h-8 w-8 rounded-lg bg-zinc-800 text-zinc-300 flex items-center justify-center ring-1 ring-zinc-700 group-hover:scale-105 transition-transform">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.53 16.122a3 3 0 0 0-5.78 1.128 2.25 2.25 0 0 1-2.4 2.245 4.5 4.5 0 0 0 8.4-2.245c0-.399-.078-.78-.22-1.128Zm0 0a15.998 15.998 0 0 0 3.388-1.62m-5.043-.025a15.994 15.994 0 0 1 1.622-3.39m3.42 3.42a15.995 15.995 0 0 0 4.764-4.648l3.876-5.814a1.151 1.151 0 0 0-1.597-1.597L14.146 6.32a15.996 15.996 0 0 0-4.649 4.763m3.42 3.42a6.776 6.776 0 0 0-3.42-3.42" />
                        </svg>
                    </div>
                    <div>
                        <div class="text-xs font-semibold text-white group-hover:text-zinc-200">Customise</div>
                        <div class="text-[10px] text-zinc-500">Logo & branding</div>
                    </div>
                </a>

                <a
                    href="{{ route('admin.organizations.members', $organization) }}"
                    class="rounded-xl border border-zinc-800 bg-zinc-900/60 p-3 flex flex-col items-start gap-2 hover:border-zinc-700 hover:bg-zinc-900 transition group"
                >
                    <div class="h-8 w-8 rounded-lg bg-zinc-800 text-zinc-300 flex items-center justify-center ring-1 ring-zinc-700 group-hover:scale-105 transition-transform">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                        </svg>
                    </div>
                    <div>
                        <div class="text-xs font-semibold text-white group-hover:text-zinc-200">Team</div>
                        <div class="text-[10px] text-zinc-500">Manage members</div>
                    </div>
                </a>
            @endif
        </div>
    </div>

    <!-- AUTHORITATIVE OPERATIONAL KPIS -->
    <div>
        <h2 class="text-xs font-bold uppercase tracking-wider text-zinc-400 mb-3">Activity & Reach</h2>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
            <!-- KPI 1: Broadcasts this month -->
            <a href="{{ route('admin.events.index') }}" class="rounded-xl border border-zinc-800 bg-zinc-900/50 p-4 transition hover:border-zinc-700 hover:bg-zinc-900/80">
                <div class="flex items-center justify-between text-xs text-zinc-400">
                    <span class="uppercase tracking-wider font-semibold">Broadcasts This Month</span>
                    <div class="h-6 w-6 rounded-md bg-emerald-950/60 text-emerald-400 flex items-center justify-center shrink-0 border border-emerald-800/40">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 0 0 6-6v-1.5m-6 7.5a6 6 0 0 1-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 0 1-3-3V4.5a3 3 0 1 1 6 0v8.25a3 3 0 0 1-3 3Z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-2 text-2xl sm:text-3xl font-bold text-white font-mono">{{ number_format($broadcastsThisMonth) }}</div>
                <div class="mt-1 text-[11px] text-zinc-400">Services on air since 1st</div>
            </a>

            <!-- KPI 2: Upcoming scheduled events -->
            <a href="{{ route('admin.events.index') }}" class="rounded-xl border border-zinc-800 bg-zinc-900/50 p-4 transition hover:border-zinc-700 hover:bg-zinc-900/80">
                <div class="flex items-center justify-between text-xs text-zinc-400">
                    <span class="uppercase tracking-wider font-semibold">Upcoming Events</span>
                    <div class="h-6 w-6 rounded-md bg-zinc-800/80 text-zinc-400 flex items-center justify-center shrink-0 border border-zinc-700/60">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 9v7.5" />
                        </svg>
                    </div>
                </div>
                <div class="mt-2 text-2xl sm:text-3xl font-bold text-white font-mono">{{ number_format($upcomingEventsCount) }}</div>
                <div class="mt-1 text-[11px] text-zinc-400">Scheduled on calendar</div>
            </a>

            <!-- KPI 3: Published podcast episodes -->
            <a href="{{ route('archive.index') }}" class="rounded-xl border border-zinc-800 bg-zinc-900/50 p-4 transition hover:border-zinc-700 hover:bg-zinc-900/80">
                <div class="flex items-center justify-between text-xs text-zinc-400">
                    <span class="uppercase tracking-wider font-semibold">Podcasts Published</span>
                    <div class="h-6 w-6 rounded-md bg-zinc-800/80 text-zinc-400 flex items-center justify-center shrink-0 border border-zinc-700/60">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m9 9 10.5-3m0 6.553v3.75a2.25 2.25 0 0 1-1.632 2.163l-1.32.377a1.803 1.803 0 1 1-.99-3.467l2.31-.66a.75.75 0 0 0 .582-.726V9.66a.75.75 0 0 0-.54-.723L9.75 6.375A.75.75 0 0 0 9 7.098v9.704a2.25 2.25 0 0 1-1.632 2.163l-1.32.377a1.803 1.803 0 1 1-.99-3.467l2.31-.66a.75.75 0 0 0 .582-.726V9Z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-2 text-2xl sm:text-3xl font-bold text-white font-mono">{{ number_format($publishedPodcastsCount) }}</div>
                <div class="mt-1 text-[11px] text-zinc-400">{{ number_format($totalRecordingsCount) }} total recordings</div>
            </a>

            <!-- KPI 4: 30-Day Unique Listeners -->
            @if ($hasAnalytics)
                <a href="{{ route('admin.analytics.index') }}" class="rounded-xl border border-zinc-800 bg-zinc-900/50 p-4 transition hover:border-zinc-700 hover:bg-zinc-900/80">
                    <div class="flex items-center justify-between text-xs text-zinc-400">
                        <span class="uppercase tracking-wider font-semibold">Listeners (30d)</span>
                        <div class="h-6 w-6 rounded-md bg-emerald-950/60 text-emerald-400 flex items-center justify-center shrink-0 border border-emerald-800/40">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-2 text-2xl sm:text-3xl font-bold text-emerald-400 font-mono">{{ number_format($uniqueListeners30d ?? 0) }}</div>
                    <div class="mt-1 text-[11px] text-zinc-400">Confirmed unique listeners</div>
                </a>
            @else
                <div class="rounded-xl border border-zinc-800/80 bg-zinc-900/30 p-4 flex flex-col justify-between">
                    <div class="flex items-center justify-between text-xs text-zinc-400">
                        <span class="uppercase tracking-wider font-semibold">Listener Analytics</span>
                        <span class="rounded bg-zinc-800 px-1.5 py-0.5 text-[10px] font-semibold text-zinc-400 uppercase">Pro</span>
                    </div>
                    <div class="my-2">
                        <div class="text-xs font-semibold text-zinc-300">Detailed Listener Reach</div>
                        <div class="text-[11px] text-zinc-500 mt-0.5">30-day distinct listeners & hearts</div>
                    </div>
                    <div>
                        <a href="{{ route('billing.plans') }}" class="text-[11px] text-emerald-400 hover:text-emerald-300 font-semibold underline underline-offset-2">
                            Upgrade to unlock →
                        </a>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- MAIN OPERATIONS SECTION: SCHEDULE & RECENT BROADCASTS -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- UPCOMING SCHEDULE PANEL -->
        <div class="rounded-xl border border-zinc-800 bg-zinc-900/40 p-5 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between border-b border-zinc-800 pb-3">
                    <div class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 9v7.5" />
                        </svg>
                        <h2 class="text-sm font-semibold text-white">Upcoming Events</h2>
                    </div>
                    <a href="{{ route('admin.events.create') }}" class="text-xs font-semibold text-emerald-400 hover:text-emerald-300">
                        + Schedule event
                    </a>
                </div>

                <div class="mt-3 divide-y divide-zinc-800/60">
                    @forelse ($upcomingEvents as $evt)
                        <div class="py-3 flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-semibold text-white truncate">{{ $evt->title }}</span>
                                    <span class="rounded bg-zinc-800 px-1.5 py-0.2 text-[10px] text-zinc-300 uppercase font-semibold shrink-0">
                                        {{ $evt->access->value }}
                                    </span>
                                </div>
                                <p class="text-[11px] text-zinc-400 mt-0.5">
                                    {{ $evt->scheduled_at ? \App\Support\LocalTime::format($evt->scheduled_at, 'D, M j · H:i') : 'Date not set' }}
                                    @if ($evt->scheduled_at && ! $evt->scheduled_at->isPast())
                                        <span class="text-emerald-400 font-medium">({{ $evt->scheduled_at->diffForHumans() }})</span>
                                    @endif
                                </p>
                            </div>

                            <div class="flex items-center gap-2 shrink-0 text-xs">
                                <a href="{{ route('events.show', $evt) }}" target="_blank" rel="noopener noreferrer" class="text-zinc-400 hover:text-zinc-200">
                                    View
                                </a>
                                <span class="text-zinc-600">·</span>
                                <a href="{{ route('admin.events.edit', $evt) }}" class="text-emerald-400 hover:text-emerald-300 font-medium">
                                    Edit
                                </a>
                            </div>
                        </div>
                    @empty
                        <div class="py-8 text-center">
                            <svg class="h-8 w-8 text-zinc-600 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 9v7.5" />
                            </svg>
                            <p class="text-xs text-zinc-400">No upcoming broadcasts scheduled.</p>
                            <a href="{{ route('admin.events.create') }}" class="mt-2 inline-block text-xs font-semibold text-emerald-400 hover:text-emerald-300">
                                Schedule your next service →
                            </a>
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="pt-3 border-t border-zinc-800/80 mt-2">
                <a href="{{ route('admin.events.index') }}" class="text-xs text-zinc-400 hover:text-zinc-200 font-medium flex items-center justify-between">
                    <span>View all events</span>
                    <span>→</span>
                </a>
            </div>
        </div>

        <!-- RECENT BROADCASTS PANEL -->
        <div class="rounded-xl border border-zinc-800 bg-zinc-900/40 p-5 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between border-b border-zinc-800 pb-3">
                    <div class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        <h2 class="text-sm font-semibold text-white">Recent Completed Broadcasts</h2>
                    </div>
                    <a href="{{ route('archive.index') }}" class="text-xs font-semibold text-zinc-400 hover:text-zinc-300">
                        View archive
                    </a>
                </div>

                <div class="mt-3 divide-y divide-zinc-800/60">
                    @forelse ($recentBroadcasts as $bcast)
                        <div class="py-3 flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-xs font-semibold text-white truncate">{{ $bcast->title }}</div>
                                <p class="text-[11px] text-zinc-400 mt-0.5">
                                    Broadcast ended {{ $bcast->ended_at ? \App\Support\LocalTime::format($bcast->ended_at, 'M j, Y · H:i') : 'recently' }}
                                </p>
                            </div>

                            <div class="flex items-center gap-2 shrink-0 text-xs">
                                <a href="{{ route('events.show', $bcast) }}" target="_blank" rel="noopener noreferrer" class="text-zinc-400 hover:text-zinc-200">
                                    View
                                </a>
                                <span class="text-zinc-600">·</span>
                                <a href="{{ route('admin.events.edit', $bcast) }}" class="text-emerald-400 hover:text-emerald-300 font-medium">
                                    Edit
                                </a>
                            </div>
                        </div>
                    @empty
                        <div class="py-8 text-center">
                            <svg class="h-8 w-8 text-zinc-600 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 0 0 6-6v-1.5m-6 7.5a6 6 0 0 1-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 0 1-3-3V4.5a3 3 0 1 1 6 0v8.25a3 3 0 0 1-3 3Z" />
                            </svg>
                            <p class="text-xs text-zinc-400">No completed broadcasts recorded yet.</p>
                            <p class="text-[11px] text-zinc-500 mt-1">When you finish a service from Studio, it will appear here.</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="pt-3 border-t border-zinc-800/80 mt-2">
                <a href="{{ route('admin.events.index') }}" class="text-xs text-zinc-400 hover:text-zinc-200 font-medium flex items-center justify-between">
                    <span>View all broadcasts</span>
                    <span>→</span>
                </a>
            </div>
        </div>
    </div>

    <!-- DIGITAL LIBRARY & PODCASTS CARDS -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- DIGITAL LIBRARY SELLER CARD -->
        <div class="rounded-xl border border-zinc-800 bg-zinc-900/40 p-5 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between border-b border-zinc-800 pb-3">
                    <div class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                        </svg>
                        <h2 class="text-sm font-semibold text-white">Digital Library · Current Sales</h2>
                    </div>

                    @if ($sellerStatus === 'approved')
                        <span class="rounded bg-emerald-950 px-2 py-0.5 text-[10px] font-semibold text-emerald-400 border border-emerald-800/60 uppercase">
                            Approved Seller
                        </span>
                    @elseif ($sellerStatus === 'pending')
                        <span class="rounded bg-amber-950 px-2 py-0.5 text-[10px] font-semibold text-amber-400 border border-amber-800/60 uppercase">
                            Under Review
                        </span>
                    @else
                        <span class="rounded bg-zinc-800 px-2 py-0.5 text-[10px] font-semibold text-zinc-400 uppercase">
                            Not Enrolled
                        </span>
                    @endif
                </div>

                <div class="mt-4">
                    @if ($sellerStatus === 'approved')
                        <p class="text-xs text-zinc-400 mb-4">
                            Publish sermon series, audio teachings, and study guides for your congregation and global listeners.
                        </p>

                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 bg-zinc-950/60 rounded-lg p-3 border border-zinc-800/80">
                            <div>
                                <span class="text-[10px] font-medium uppercase tracking-wider text-zinc-500">Series</span>
                                <div class="text-lg font-bold text-white font-mono mt-0.5">{{ $sellerSeriesCount }}</div>
                                <div class="text-[10px] text-zinc-400">{{ $sellerPublishedSeriesCount }} published</div>
                            </div>
                            <div>
                                <span class="text-[10px] font-medium uppercase tracking-wider text-zinc-500">Orders</span>
                                <div class="text-lg font-bold text-white font-mono mt-0.5">{{ number_format($sellerTotalPurchases) }}</div>
                                <div class="text-[10px] text-zinc-400">Completed purchases</div>
                            </div>
                            <div>
                                <span class="text-[10px] font-medium uppercase tracking-wider text-zinc-500">Gross Collected</span>
                                <div class="text-lg font-bold text-white font-mono mt-0.5">₦{{ number_format($sellerGrossRevenueNgnMinor / 100, 2) }}</div>
                                <div class="text-[10px] text-zinc-400">Total NGN gross</div>
                            </div>
                            <div>
                                <span class="text-[10px] font-medium uppercase tracking-wider text-zinc-500">Net Collected</span>
                                <div class="text-lg font-bold text-emerald-400 font-mono mt-0.5">₦{{ number_format($sellerNetRevenueNgnMinor / 100, 2) }}</div>
                                <div class="text-[10px] text-zinc-400">
                                    @if ($sellerRefundedNgnMinor > 0)
                                        ₦{{ number_format($sellerRefundedNgnMinor / 100, 2) }} refunded
                                    @else
                                        After refunds
                                    @endif
                                </div>
                            </div>
                        </div>
                    @elseif ($sellerStatus === 'pending')
                        <div class="rounded-lg border border-amber-500/30 bg-amber-950/20 p-4 text-xs text-amber-200">
                            <p class="font-semibold text-sm text-amber-300">Teacher & Seller Application Submitted</p>
                            <p class="mt-1">
                                Platform administrators are currently reviewing your church application. Once approved, you will be able to create series, assign teachers, and publish teachings.
                            </p>
                        </div>
                    @else
                        <p class="text-xs text-zinc-400 mb-4">
                            Expand your ministry by publishing premium digital audio series and study materials directly to the Sound Mix Digital Library.
                        </p>
                        <div class="rounded-lg border border-zinc-800 bg-zinc-950/60 p-4 text-xs text-zinc-300">
                            <ul class="list-disc list-inside space-y-1 text-zinc-400">
                                <li>Create multi-part teaching series and audio devotionals</li>
                                <li>Set custom prices in NGN with secure Paystack checkout</li>
                                <li>Reach believers beyond your Sunday live broadcasts</li>
                            </ul>
                        </div>
                    @endif
                </div>
            </div>

            <div class="pt-4 border-t border-zinc-800/80 mt-4">
                @if ($canSellLibrary)
                    <a
                        href="{{ route('digital-library.sell.show', $organization) }}"
                        class="text-xs font-semibold text-emerald-400 hover:text-emerald-300 flex items-center justify-between"
                    >
                        <span>{{ $sellerStatus === 'approved' ? 'Manage Digital Library series' : ($sellerStatus === 'pending' ? 'View application status' : 'Apply to sell on Digital Library') }}</span>
                        <span>→</span>
                    </a>
                @endif
            </div>
        </div>

        <!-- PODCASTS & ARCHIVE CARD -->
        <div class="rounded-xl border border-zinc-800 bg-zinc-900/40 p-5 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between border-b border-zinc-800 pb-3">
                    <div class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m9 9 10.5-3m0 6.553v3.75a2.25 2.25 0 0 1-1.632 2.163l-1.32.377a1.803 1.803 0 1 1-.99-3.467l2.31-.66a.75.75 0 0 0 .582-.726V9.66a.75.75 0 0 0-.54-.723L9.75 6.375A.75.75 0 0 0 9 7.098v9.704a2.25 2.25 0 0 1-1.632 2.163l-1.32.377a1.803 1.803 0 1 1-.99-3.467l2.31-.66a.75.75 0 0 0 .582-.726V9Z" />
                        </svg>
                        <h2 class="text-sm font-semibold text-white">Podcasts & Sermon Recordings</h2>
                    </div>

                    <span class="rounded bg-zinc-800 px-2 py-0.5 text-[10px] font-semibold text-zinc-300">
                        {{ number_format($publishedPodcastsCount) }} Episodes
                    </span>
                </div>

                <div class="mt-4">
                    <p class="text-xs text-zinc-400 mb-4">
                        Recordings captured from your live broadcasts can be trimmed, named, and published as public podcast episodes for on-demand listening.
                    </p>

                    <div class="grid grid-cols-2 gap-3 bg-zinc-950/60 rounded-lg p-3 border border-zinc-800/80">
                        <div>
                            <span class="text-[10px] font-medium uppercase tracking-wider text-zinc-500">Public Episodes</span>
                            <div class="text-lg font-bold text-white font-mono mt-0.5">{{ number_format($publishedPodcastsCount) }}</div>
                            <div class="text-[10px] text-zinc-400">Available on channel</div>
                        </div>
                        <div>
                            <span class="text-[10px] font-medium uppercase tracking-wider text-zinc-500">Total Captured</span>
                            <div class="text-lg font-bold text-zinc-300 font-mono mt-0.5">{{ number_format($totalRecordingsCount) }}</div>
                            <div class="text-[10px] text-zinc-400">Master recordings</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="pt-4 border-t border-zinc-800/80 mt-4 flex items-center justify-between text-xs">
                <a href="{{ route('archive.index') }}" class="font-semibold text-emerald-400 hover:text-emerald-300">
                    Manage podcasts & recordings →
                </a>
                <a href="{{ route('archive.index') }}" target="_blank" class="text-zinc-400 hover:text-zinc-200">
                    Public archive ↗
                </a>
            </div>
        </div>
    </div>

    <!-- SUBSCRIPTION HEALTH & TEAM SNAPSHOT -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- PLAN & SUBSCRIPTION CARD -->
        <div class="rounded-xl border border-zinc-800 bg-zinc-900/40 p-5 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between border-b border-zinc-800 pb-3">
                    <div class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
                        </svg>
                        <h2 class="text-sm font-semibold text-white">Subscription & Plan Health</h2>
                    </div>

                    @if ($subscription)
                        <span class="rounded-full border px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider {{ $subscription->status === \App\Enums\SubscriptionStatus::Active ? 'border-emerald-600 bg-emerald-950 text-emerald-300' : ($subscription->status === \App\Enums\SubscriptionStatus::PastDue ? 'border-red-600 bg-red-950 text-red-300' : 'border-zinc-700 bg-zinc-800 text-zinc-300') }}">
                            {{ $subscription->status->label() }}
                        </span>
                    @endif
                </div>

                <div class="mt-4 space-y-3">
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-zinc-400">Current Plan:</span>
                        <span class="font-semibold text-white">{{ $subscription?->plan?->name ?? 'Free Tier' }}</span>
                    </div>

                    @if ($subscription?->isOnFreePeriod() && isset($freeDaysRemaining))
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-zinc-400">Trial Period:</span>
                            <span class="font-semibold text-amber-400">{{ $freeDaysRemaining > 0 ? $freeDaysRemaining.' DAYS LEFT' : 'ENDED' }}</span>
                        </div>
                    @endif

                    @if ($subscription?->renews_at)
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-zinc-400">Renewal Date:</span>
                            <span class="font-mono text-zinc-300">{{ \App\Support\LocalTime::format($subscription->renews_at, 'M j, Y') }}</span>
                        </div>
                    @endif

                    <div class="flex items-center justify-between text-xs">
                        <span class="text-zinc-400">Broadcasting Permission:</span>
                        <span class="font-medium {{ $canBroadcast ? 'text-emerald-400' : 'text-red-400' }}">
                            {{ $canBroadcast ? 'Enabled' : 'Disabled (Requires plan upgrade)' }}
                        </span>
                    </div>

                    <div class="flex items-center justify-between text-xs">
                        <span class="text-zinc-400">Listener Analytics:</span>
                        <span class="font-medium {{ $hasAnalytics ? 'text-emerald-400' : 'text-zinc-500' }}">
                            {{ $hasAnalytics ? 'Included in Plan' : 'Requires Pro Plan' }}
                        </span>
                    </div>
                </div>
            </div>

            @if ($isOwner)
                <div class="pt-4 border-t border-zinc-800/80 mt-4 flex items-center justify-between text-xs">
                    <a href="{{ route('billing.plans') }}" class="font-semibold text-emerald-400 hover:text-emerald-300">
                        {{ $subscription?->needsUpgradeCta() ? 'Upgrade plan' : 'Manage billing & invoices' }} →
                    </a>
                </div>
            @endif
        </div>

        <!-- TEAM & MEMBERS CARD -->
        <div class="rounded-xl border border-zinc-800 bg-zinc-900/40 p-5 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between border-b border-zinc-800 pb-3">
                    <div class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                        </svg>
                        <h2 class="text-sm font-semibold text-white">Broadcast Team</h2>
                    </div>

                    <span class="rounded bg-zinc-800 px-2 py-0.5 text-[10px] font-semibold text-zinc-300">
                        {{ $teamCount }} {{ \Illuminate\Support\Str::plural('Member', $teamCount) }}
                    </span>
                </div>

                <div class="mt-4 space-y-3">
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-zinc-400">Account Owners:</span>
                        <span class="font-semibold text-white">{{ $ownersCount }}</span>
                    </div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-zinc-400">Broadcasters / Admins:</span>
                        <span class="font-semibold text-white">{{ $adminsCount }}</span>
                    </div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-zinc-400">Team Members:</span>
                        <span class="font-semibold text-white">{{ $membersCount }}</span>
                    </div>

                    <div class="pt-2">
                        <div class="text-[11px] text-zinc-500">
                            Admins can manage streams, schedule services, and operate Studio. Only the Owner can manage billing.
                        </div>
                    </div>
                </div>
            </div>

            @if ($canManage)
                <div class="pt-4 border-t border-zinc-800/80 mt-4">
                    <a href="{{ route('admin.organizations.members', $organization) }}" class="text-xs font-semibold text-emerald-400 hover:text-emerald-300 flex items-center justify-between">
                        <span>Invite & manage team members</span>
                        <span>→</span>
                    </a>
                </div>
            @endif
        </div>
    </div>

    <!-- CONTEXTUAL SETUP CHECKLIST -->
    @if (! $isSetupComplete)
        <div class="rounded-xl border border-zinc-800 bg-zinc-900/30 p-5">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <span class="h-2 w-2 rounded-full bg-amber-400"></span>
                    <h2 class="text-sm font-semibold text-white">Channel Setup Guide</h2>
                </div>
                <span class="text-xs font-mono text-zinc-400">
                    {{ $completedSetupSteps }} of {{ $totalSetupSteps }} completed
                </span>
            </div>

            <div class="w-full bg-zinc-800 rounded-full h-1.5 mb-4 overflow-hidden">
                <div class="bg-emerald-500 h-1.5 rounded-full transition-all duration-300" style="width: {{ ($completedSetupSteps / $totalSetupSteps) * 100 }}%"></div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
                <!-- Step 1: Branding -->
                <div class="flex items-start gap-2.5 p-2 rounded-lg {{ $setupChecklist['has_logo'] ? 'text-zinc-400' : 'bg-zinc-900 text-zinc-200 font-medium' }}">
                    <span class="shrink-0 mt-0.5 {{ $setupChecklist['has_logo'] ? 'text-emerald-400' : 'text-zinc-600' }}">
                        {{ $setupChecklist['has_logo'] ? '✓' : '○' }}
                    </span>
                    <div>
                        <div>Upload logo & artwork</div>
                        @if (! $setupChecklist['has_logo'])
                            <a href="{{ route('admin.organizations.customise', $organization) }}" class="text-emerald-400 hover:text-emerald-300 text-[11px] block mt-0.5">Customise now →</a>
                        @endif
                    </div>
                </div>

                <!-- Step 2: Stream -->
                <div class="flex items-start gap-2.5 p-2 rounded-lg {{ $setupChecklist['has_stream'] ? 'text-zinc-400' : 'bg-zinc-900 text-zinc-200 font-medium' }}">
                    <span class="shrink-0 mt-0.5 {{ $setupChecklist['has_stream'] ? 'text-emerald-400' : 'text-zinc-600' }}">
                        {{ $setupChecklist['has_stream'] ? '✓' : '○' }}
                    </span>
                    <div>
                        <div>Configure live stream</div>
                        @if (! $setupChecklist['has_stream'])
                            <a href="{{ route('admin.streams.create') }}" class="text-emerald-400 hover:text-emerald-300 text-[11px] block mt-0.5">Create stream →</a>
                        @endif
                    </div>
                </div>

                <!-- Step 3: Event -->
                <div class="flex items-start gap-2.5 p-2 rounded-lg {{ $setupChecklist['has_scheduled_event'] ? 'text-zinc-400' : 'bg-zinc-900 text-zinc-200 font-medium' }}">
                    <span class="shrink-0 mt-0.5 {{ $setupChecklist['has_scheduled_event'] ? 'text-emerald-400' : 'text-zinc-600' }}">
                        {{ $setupChecklist['has_scheduled_event'] ? '✓' : '○' }}
                    </span>
                    <div>
                        <div>Schedule first service</div>
                        @if (! $setupChecklist['has_scheduled_event'])
                            <a href="{{ route('admin.events.create') }}" class="text-emerald-400 hover:text-emerald-300 text-[11px] block mt-0.5">Schedule now →</a>
                        @endif
                    </div>
                </div>

                <!-- Step 4: Broadcast -->
                <div class="flex items-start gap-2.5 p-2 rounded-lg {{ $setupChecklist['has_broadcasted'] ? 'text-zinc-400' : 'bg-zinc-900 text-zinc-200 font-medium' }}">
                    <span class="shrink-0 mt-0.5 {{ $setupChecklist['has_broadcasted'] ? 'text-emerald-400' : 'text-zinc-600' }}">
                        {{ $setupChecklist['has_broadcasted'] ? '✓' : '○' }}
                    </span>
                    <div>
                        <div>First broadcast</div>
                        @if (! $setupChecklist['has_broadcasted'] && $studioUrl)
                            <a href="{{ $studioUrl }}" target="_blank" rel="noopener noreferrer" class="text-emerald-400 hover:text-emerald-300 text-[11px] block mt-0.5">Test studio →</a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
