@extends(auth()->user()?->isAdmin() ? 'layouts.admin' : 'layouts.creator')

@section('title', 'Events & Schedule')

@section('content')
<div class="space-y-6 max-w-7xl mx-auto">
    <!-- PAGE HEADER -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-zinc-800 pb-5">
        <div>
            <div class="flex items-center gap-2">
                <span class="inline-flex h-2 w-2 rounded-full bg-emerald-400"></span>
                <span class="text-xs font-semibold uppercase tracking-wider text-emerald-400">
                    {{ auth()->user()?->isAdmin() ? 'Platform Console' : 'Church Operations' }}
                </span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-white mt-1">Events & Schedule</h1>
            <p class="text-xs text-zinc-400 mt-0.5">Schedule church services, create shareable links, and prepare for broadcast.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.events.create') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3.5 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-emerald-500 transition">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                <span>Schedule event</span>
            </a>
        </div>
    </div>

    <!-- FILTER TABS -->
    <div class="flex items-center gap-2 border-b border-zinc-800/80 pb-3">
        <a
            href="{{ route('admin.events.index') }}"
            class="rounded-lg px-3 py-1.5 text-xs font-semibold transition {{ !request()->has('status') ? 'bg-zinc-800 text-white ring-1 ring-zinc-700' : 'text-zinc-400 hover:text-white hover:bg-zinc-900' }}"
        >
            All Events
        </a>
        <a
            href="{{ route('admin.events.index', ['status' => 'live']) }}"
            class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition {{ request('status') === 'live' ? 'bg-red-950/80 text-red-300 ring-1 ring-red-700/60' : 'text-zinc-400 hover:text-white hover:bg-zinc-900' }}"
        >
            <span class="h-1.5 w-1.5 rounded-full bg-red-400 animate-pulse"></span>
            <span>Live Now</span>
        </a>
        <a
            href="{{ route('admin.events.index', ['status' => 'scheduled']) }}"
            class="rounded-lg px-3 py-1.5 text-xs font-semibold transition {{ request('status') === 'scheduled' ? 'bg-emerald-950/80 text-emerald-300 ring-1 ring-emerald-700/60' : 'text-zinc-400 hover:text-white hover:bg-zinc-900' }}"
        >
            Scheduled
        </a>
        <a
            href="{{ route('admin.events.index', ['status' => 'ended']) }}"
            class="rounded-lg px-3 py-1.5 text-xs font-semibold transition {{ request('status') === 'ended' ? 'bg-zinc-800 text-white ring-1 ring-zinc-700' : 'text-zinc-400 hover:text-white hover:bg-zinc-900' }}"
        >
            Completed
        </a>
    </div>

    <!-- EVENTS LIST / TABLE -->
    <div class="rounded-xl border border-zinc-800 bg-zinc-900/40 overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-800 text-left text-xs">
                <thead>
                    <tr class="bg-zinc-950/60 text-zinc-400 uppercase font-semibold tracking-wider text-[11px]">
                        <th scope="col" class="py-3 px-4">Event Title</th>
                        <th scope="col" class="py-3 px-4">Channel</th>
                        <th scope="col" class="py-3 px-4">Status</th>
                        <th scope="col" class="py-3 px-4">Access</th>
                        <th scope="col" class="py-3 px-4">Scheduled / Timing</th>
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
                                @elseif ($event->status->value === 'paused')
                                    <span class="inline-flex items-center rounded-full bg-amber-950 px-2.5 py-0.5 text-[10px] font-semibold text-amber-300 border border-amber-800/60 uppercase">
                                        Paused
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-zinc-800 px-2.5 py-0.5 text-[10px] font-semibold text-zinc-400 uppercase">
                                        {{ $event->status->value }}
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap">
                                <span class="rounded bg-zinc-800/80 px-2 py-0.5 text-[10px] font-medium text-zinc-300 capitalize border border-zinc-700/60">
                                    {{ $event->access->value }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-zinc-400 whitespace-nowrap font-mono text-[11px]">
                                @if ($event->scheduled_at)
                                    <div>{{ \App\Support\LocalTime::format($event->scheduled_at, 'M j, Y · H:i') }}</div>
                                    @if (! $event->scheduled_at->isPast())
                                        <div class="text-[10px] text-emerald-400 font-sans font-medium">{{ $event->scheduled_at->diffForHumans() }}</div>
                                    @endif
                                @else
                                    <span>—</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right whitespace-nowrap space-x-2">
                                @if ($event->status->value === 'live')
                                    <a href="{{ route('events.show', $event) }}" target="_blank" rel="noopener noreferrer" class="text-emerald-400 hover:text-emerald-300 font-semibold inline-flex items-center gap-1">
                                        <span>Listen</span>
                                        <span class="sr-only">(opens live page in new tab)</span>
                                    </a>
                                @else
                                    <a href="{{ route('events.show', $event) }}" target="_blank" rel="noopener noreferrer" class="text-zinc-400 hover:text-zinc-200 inline-flex items-center gap-1">
                                        <span>View</span>
                                        <span class="sr-only">(opens event page in new tab)</span>
                                    </a>
                                @endif
                                <span class="text-zinc-600">·</span>
                                <a href="{{ route('admin.events.edit', $event) }}" class="text-emerald-400 hover:text-emerald-300 font-semibold">
                                    Edit
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12 text-center text-zinc-400">
                                <svg class="h-8 w-8 text-zinc-600 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 9v7.5" />
                                </svg>
                                <p class="text-xs font-medium">No events found matching this filter.</p>
                                <a href="{{ route('admin.events.create') }}" class="mt-2 inline-block text-xs font-semibold text-emerald-400 hover:text-emerald-300">
                                    Schedule a new event →
                                </a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($events->hasPages())
        <div class="mt-6">{{ $events->links() }}</div>
    @endif
</div>
@endsection
