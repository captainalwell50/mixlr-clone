@extends(auth()->user()?->isAdmin() ? 'layouts.admin' : 'layouts.creator')

@section('title', 'Audience Analytics')

@section('content')
<div class="space-y-6 max-w-7xl mx-auto">
    <!-- PAGE HEADER & RANGE SELECTOR -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-zinc-800 pb-5">
        <div>
            <div class="flex items-center gap-2">
                <span class="inline-flex h-2 w-2 rounded-full bg-emerald-400"></span>
                <span class="text-xs font-semibold uppercase tracking-wider text-emerald-400">
                    {{ auth()->user()?->isAdmin() ? 'Platform Analytics' : 'Audience Analytics' }}
                </span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-white mt-1">Audience Analytics</h1>
            <p class="text-xs text-zinc-400 mt-0.5">Listener reach, audience reactions, and community engagement across recent broadcasts.</p>
        </div>

        <!-- Range Selector Pills (7d, 30d, 90d) -->
        <div class="flex items-center gap-1.5 rounded-xl border border-zinc-800 bg-zinc-900/80 p-1 self-start sm:self-auto">
            @foreach ([7 => 'Last 7 days', 30 => 'Last 30 days', 90 => 'Last 90 days'] as $d => $label)
                <a
                    href="{{ route('admin.analytics.index', ['days' => $d]) }}"
                    @if ($days === $d) aria-current="page" @endif
                    class="rounded-lg px-3 py-1.5 text-xs font-semibold transition {{ $days === $d ? 'bg-emerald-600 text-white shadow-sm' : 'text-zinc-400 hover:text-white hover:bg-zinc-800' }}"
                    title="{{ $label }}"
                >
                    {{ $d }}d
                </a>
            @endforeach
        </div>
    </div>

    <!-- KPI STRIP -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <!-- Unique Listeners -->
        <div class="rounded-xl border border-zinc-800 bg-zinc-900/60 p-5 relative overflow-hidden">
            <div class="flex items-center justify-between text-xs text-zinc-400">
                <span class="font-semibold uppercase tracking-wider">Unique Listeners</span>
                <div class="h-8 w-8 rounded-lg bg-emerald-950/80 text-emerald-400 flex items-center justify-center ring-1 ring-emerald-700/50">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                    </svg>
                </div>
            </div>
            <div class="mt-2 text-3xl font-extrabold text-white tracking-tight">{{ number_format($uniqueListeners) }}</div>
            <div class="mt-1 text-[11px] text-zinc-500">Distinct sessions across past {{ $days }} days</div>
        </div>

        <!-- Hearts & Reactions -->
        <div class="rounded-xl border border-zinc-800 bg-zinc-900/60 p-5 relative overflow-hidden">
            <div class="flex items-center justify-between text-xs text-zinc-400">
                <span class="font-semibold uppercase tracking-wider">Audience Hearts</span>
                <div class="h-8 w-8 rounded-lg bg-rose-950/80 text-rose-400 flex items-center justify-center ring-1 ring-rose-700/50">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z" />
                    </svg>
                </div>
            </div>
            <div class="mt-2 text-3xl font-extrabold text-white tracking-tight">{{ number_format($hearts) }}</div>
            <div class="mt-1 text-[11px] text-zinc-500">Real-time live reactions sent by listeners</div>
        </div>

        <!-- Chat Messages -->
        <div class="rounded-xl border border-zinc-800 bg-zinc-900/60 p-5 relative overflow-hidden">
            <div class="flex items-center justify-between text-xs text-zinc-400">
                <span class="font-semibold uppercase tracking-wider">Chat Messages</span>
                <div class="h-8 w-8 rounded-lg bg-sky-950/80 text-sky-400 flex items-center justify-center ring-1 ring-sky-700/50">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a.75.75 0 0 1-.774-.962 5.064 5.064 0 0 0 .832-2.185C3.893 16.326 3 14.28 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
                    </svg>
                </div>
            </div>
            <div class="mt-2 text-3xl font-extrabold text-white tracking-tight">{{ number_format($chats) }}</div>
            <div class="mt-1 text-[11px] text-zinc-500">Community dialogue & prayer submissions</div>
        </div>
    </div>

    <!-- EVENTS PERFORMANCE BREAKDOWN -->
    <div class="space-y-3">
        <div class="flex items-center justify-between">
            <h2 class="text-xs font-bold uppercase tracking-wider text-zinc-400">Broadcast Performance (Past {{ $days }} Days)</h2>
            <span class="text-xs text-zinc-500">{{ $events->count() }} {{ \Illuminate\Support\Str::plural('broadcast', $events->count()) }}</span>
        </div>

        <div class="rounded-xl border border-zinc-800 bg-zinc-900/40 overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-800 text-left text-xs">
                    <thead>
                        <tr class="bg-zinc-950/60 text-zinc-400 uppercase font-semibold tracking-wider text-[11px]">
                            <th scope="col" class="py-3 px-4">Event Title</th>
                            <th scope="col" class="py-3 px-4">Channel</th>
                            <th scope="col" class="py-3 px-4">Status</th>
                            <th scope="col" class="py-3 px-4">Aired / Scheduled</th>
                            <th scope="col" class="py-3 px-4 text-right">Unique Listeners</th>
                            <th scope="col" class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800/60">
                        @forelse ($events as $event)
                            <tr class="hover:bg-zinc-800/30 transition">
                                <td class="py-3 px-4">
                                    <div class="font-semibold text-white text-sm">{{ $event->title }}</div>
                                    @if ($event->description)
                                        <div class="text-[11px] text-zinc-400 truncate max-w-xs mt-0.5">{{ $event->description }}</div>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-zinc-300">
                                    {{ $event->organization->name }}
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap">
                                    @if ($event->status->value === 'live')
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-red-950 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-red-300 border border-red-700/60">
                                            <span class="h-1.5 w-1.5 rounded-full bg-red-400 animate-pulse"></span>
                                            <span>Live</span>
                                        </span>
                                    @elseif ($event->status->value === 'scheduled')
                                        <span class="inline-flex items-center rounded-full bg-emerald-950/70 px-2.5 py-0.5 text-[10px] font-semibold text-emerald-300 border border-emerald-800/60 uppercase">
                                            Scheduled
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-zinc-800 px-2.5 py-0.5 text-[10px] font-semibold text-zinc-400 uppercase">
                                            {{ $event->status->value }}
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-zinc-400 whitespace-nowrap font-mono text-[11px]">
                                    @php
                                        $displayDate = $event->started_at ?? $event->scheduled_at ?? $event->created_at;
                                    @endphp
                                    @if ($displayDate)
                                        <div>{{ \App\Support\LocalTime::format($displayDate, 'M j, Y · H:i') }}</div>
                                    @else
                                        <span>—</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-right whitespace-nowrap">
                                    <span class="font-bold text-sm text-white">{{ number_format($perEvent[$event->id] ?? 0) }}</span>
                                </td>
                                <td class="py-3 px-4 text-right whitespace-nowrap space-x-2">
                                    <a href="{{ route('admin.events.edit', $event) }}" class="text-emerald-400 hover:text-emerald-300 font-semibold">
                                        Edit
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-12 text-center text-zinc-400">
                                    <svg class="h-8 w-8 text-zinc-600 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                                    </svg>
                                    <p class="text-xs font-medium text-zinc-300">No broadcast activity recorded in the past {{ $days }} days.</p>
                                    <p class="text-[11px] text-zinc-500 mt-0.5">As you go live and listeners connect, real-time statistics will populate here.</p>
                                    <a href="{{ route('admin.events.create') }}" class="mt-3 inline-block text-xs font-semibold text-emerald-400 hover:text-emerald-300">
                                        Schedule your next service →
                                    </a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
