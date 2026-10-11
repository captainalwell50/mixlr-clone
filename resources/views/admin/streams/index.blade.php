@extends(auth()->user()?->isAdmin() ? 'layouts.admin' : 'layouts.creator')

@section('title', 'Streams')

@section('content')
<div class="space-y-6 max-w-7xl mx-auto">
    <!-- PAGE HEADER -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-zinc-800 pb-5">
        <div>
            <div class="flex items-center gap-2">
                <span class="inline-flex h-2 w-2 rounded-full bg-emerald-400"></span>
                <span class="text-xs font-semibold uppercase tracking-wider text-emerald-400">{{ auth()->user()?->isAdmin() ? 'Platform Console' : 'Church Operations' }}</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-white mt-1">Streams</h1>
            <p class="text-xs text-zinc-400 mt-0.5">Broadcast endpoints, studio ingest, embeds, and current active status.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.streams.create') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-emerald-500 transition">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                <span>New stream</span>
            </a>
        </div>
    </div>

    <!-- OPERATIONAL KPI STRIP -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        <a href="{{ route('admin.streams.index', ['status' => 'live']) }}" class="rounded-xl border p-3.5 transition {{ request('status') === 'live' ? 'border-emerald-600 bg-emerald-950/40 text-emerald-300' : 'border-zinc-800 bg-zinc-900/50 hover:bg-zinc-900/80' }}">
            <div class="flex items-center justify-between text-xs text-zinc-400">
                <span class="uppercase tracking-wider font-semibold">Live Now</span>
                <span class="h-2 w-2 rounded-full {{ $liveCount > 0 ? 'bg-emerald-400 animate-pulse' : 'bg-zinc-600' }}"></span>
            </div>
            <div class="mt-1 text-2xl font-bold text-white">{{ number_format($liveCount) }}</div>
            <div class="mt-1 text-[11px] text-zinc-500">Currently broadcasting</div>
        </a>

        <a href="{{ route('admin.streams.index') }}" class="rounded-xl border p-3.5 transition {{ !request()->has('status') && !request()->has('q') && !request()->has('organization_id') ? 'border-emerald-700/60 bg-emerald-950/30' : 'border-zinc-800 bg-zinc-900/50 hover:bg-zinc-900/80' }}">
            <div class="flex items-center justify-between text-xs text-zinc-400">
                <span class="uppercase tracking-wider font-semibold">Total Streams</span>
                <svg class="h-4 w-4 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.348 14.652a3.75 3.75 0 0 1 0-5.304m5.304 0a3.75 3.75 0 0 1 0 5.304m-7.425 2.121a6.75 6.75 0 0 1 0-9.546m9.546 0a6.75 6.75 0 0 1 0 9.546M5.106 18.894c-3.808-3.807-3.808-9.98 0-13.788m13.788 0c3.808 3.807 3.808 9.98 0 13.788M12 12h.008v.008H12V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                </svg>
            </div>
            <div class="mt-1 text-2xl font-bold text-white">{{ number_format($totalCount) }}</div>
            <div class="mt-1 text-[11px] text-zinc-500">Configured endpoints</div>
        </a>

        <a href="{{ route('admin.events.index') }}" class="rounded-xl border border-zinc-800 bg-zinc-900/50 p-3.5 transition hover:bg-zinc-900/80">
            <div class="flex items-center justify-between text-xs text-zinc-400">
                <span class="uppercase tracking-wider font-semibold">Broadcasts Today</span>
                <svg class="h-4 w-4 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 9v7.5" />
                </svg>
            </div>
            <div class="mt-1 text-2xl font-bold text-white">{{ number_format($broadcastsToday) }}</div>
            <div class="mt-1 text-[11px] text-zinc-500">Services since 00:00</div>
        </a>

        <a href="{{ route('admin.streams.index', ['status' => 'offline']) }}" class="rounded-xl border p-3.5 transition {{ request('status') === 'offline' ? 'border-zinc-600 bg-zinc-800/60' : 'border-zinc-800 bg-zinc-900/50 hover:bg-zinc-900/80' }}">
            <div class="flex items-center justify-between text-xs text-zinc-400">
                <span class="uppercase tracking-wider font-semibold">Offline</span>
                <span class="h-2 w-2 rounded-full bg-zinc-600"></span>
            </div>
            <div class="mt-1 text-2xl font-bold text-zinc-300">{{ number_format($offlineCount) }}</div>
            <div class="mt-1 text-[11px] text-zinc-500">Standby streams</div>
        </a>
    </div>

    <!-- FILTER & SEARCH BAR -->
    <div class="rounded-xl border border-zinc-800 bg-zinc-900/60 p-4">
        <form method="GET" action="{{ route('admin.streams.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <!-- Free text search -->
            <div class="lg:col-span-2">
                <label for="filter-q" class="sr-only">Search streams</label>
                <div class="relative">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-zinc-500">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                    </div>
                    <input
                        id="filter-q"
                        type="search"
                        name="q"
                        value="{{ $q }}"
                        placeholder="Search title, channel, or UUID..."
                        class="w-full rounded-lg border border-zinc-700 bg-zinc-950 py-1.5 pl-9 pr-3 text-xs text-zinc-100 placeholder-zinc-500 focus:border-emerald-500 focus:outline-none"
                    />
                </div>
            </div>

            <!-- Status filter -->
            <div>
                <label for="filter-status" class="sr-only">Status</label>
                <select
                    id="filter-status"
                    name="status"
                    onchange="this.form.submit()"
                    class="w-full rounded-lg border border-zinc-700 bg-zinc-950 py-1.5 px-3 text-xs text-zinc-200 focus:border-emerald-500 focus:outline-none"
                >
                    <option value="">All statuses</option>
                    <option value="live" @selected($status === 'live')>● Live only</option>
                    <option value="offline" @selected($status === 'offline')>Offline only</option>
                </select>
            </div>

            <!-- Organisation filter -->
            <div>
                <label for="filter-org" class="sr-only">Organisation</label>
                <select
                    id="filter-org"
                    name="organization_id"
                    onchange="this.form.submit()"
                    class="w-full rounded-lg border border-zinc-700 bg-zinc-950 py-1.5 px-3 text-xs text-zinc-200 focus:border-emerald-500 focus:outline-none"
                >
                    <option value="">All organisations</option>
                    @foreach ($organizations as $org)
                        <option value="{{ $org->id }}" @selected((string)$organizationId === (string)$org->id)>{{ $org->name }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Sort & actions -->
            <div class="flex items-center gap-2">
                <select
                    name="sort"
                    onchange="this.form.submit()"
                    class="w-full rounded-lg border border-zinc-700 bg-zinc-950 py-1.5 px-3 text-xs text-zinc-200 focus:border-emerald-500 focus:outline-none"
                >
                    <option value="latest" @selected($sort === 'latest')>Newest first</option>
                    <option value="oldest" @selected($sort === 'oldest')>Oldest first</option>
                    <option value="title" @selected($sort === 'title')>By title</option>
                    <option value="status" @selected($sort === 'status')>Live first</option>
                </select>
                @if ($q !== '' || $status || $organizationId || $sort !== 'latest')
                    <a href="{{ route('admin.streams.index') }}" class="rounded-lg border border-zinc-700 bg-zinc-800 px-2.5 py-1.5 text-xs text-zinc-400 hover:text-white transition" title="Reset filters">
                        Clear
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- STREAMS DATA TABLE -->
    <div class="overflow-hidden rounded-xl border border-zinc-800 bg-zinc-900/40 shadow">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-800 text-left text-xs">
                <thead class="bg-zinc-900/90 text-[11px] uppercase tracking-wider text-zinc-400 font-semibold">
                    <tr>
                        <th scope="col" class="py-3.5 pl-4 pr-3 sm:pl-6">Stream / Channel</th>
                        <th scope="col" class="px-3 py-3.5">Organisation</th>
                        <th scope="col" class="px-3 py-3.5">Status</th>
                        <th scope="col" class="px-3 py-3.5">Listeners</th>
                        <th scope="col" class="px-3 py-3.5 whitespace-nowrap">Account created</th>
                        <th scope="col" class="px-3 py-3.5">Ingest / Mode</th>
                        <th scope="col" class="py-3.5 pl-3 pr-4 sm:pr-6 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800/80 bg-zinc-950/40">
                    @forelse ($streams as $stream)
                        @php
                            $isLive = $stream->status === \App\Enums\StreamStatus::Live;
                            $listeners = $isLive ? $stream->activeListenerCount() : 0;
                        @endphp
                        <tr class="transition hover:bg-zinc-900/60 {{ $isLive ? 'bg-emerald-950/10' : '' }}">
                            <!-- Stream title & description -->
                            <td class="py-3 pl-4 pr-3 sm:pl-6">
                                <div class="font-medium text-zinc-100 flex items-center gap-2">
                                    <span class="text-sm font-semibold">{{ $stream->title }}</span>
                                    @if ($stream->is_public)
                                        <span class="rounded bg-zinc-800 px-1.5 py-0.2 text-[10px] text-zinc-400" title="Listed on Discover">Public</span>
                                    @else
                                        <span class="rounded bg-zinc-900 px-1.5 py-0.2 text-[10px] text-zinc-500 border border-zinc-800" title="Unlisted">Unlisted</span>
                                    @endif
                                </div>
                                @if ($stream->description)
                                    <p class="text-[11px] text-zinc-500 truncate max-w-xs mt-0.5">{{ $stream->description }}</p>
                                @endif
                                <div class="mt-1 font-mono text-[10px] text-zinc-500">
                                    UUID: {{ \Illuminate\Support\Str::limit($stream->uuid, 12, '…') }}
                                </div>
                            </td>

                            <!-- Organisation -->
                            <td class="px-3 py-3 text-zinc-300">
                                <a href="{{ route('admin.organizations.edit', $stream->organization) }}" class="text-emerald-400/90 hover:text-emerald-300 font-medium">
                                    {{ $stream->organization->name }}
                                </a>
                            </td>

                            <!-- Status Badge -->
                            <td class="px-3 py-3 whitespace-nowrap">
                                @if ($isLive)
                                    <div class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/20 px-2.5 py-1 text-[11px] font-bold text-emerald-300 border border-emerald-700/60">
                                        <span class="h-2 w-2 rounded-full bg-emerald-400 animate-pulse"></span>
                                        <span>LIVE</span>
                                    </div>
                                @else
                                    <span class="inline-flex items-center rounded-md bg-zinc-800/80 px-2 py-0.5 text-[11px] font-medium text-zinc-400 border border-zinc-700/50">
                                        Offline
                                    </span>
                                @endif
                            </td>

                            <!-- Listeners -->
                            <td class="px-3 py-3 whitespace-nowrap">
                                @if ($isLive)
                                    <span class="font-semibold text-emerald-400">{{ number_format($listeners) }}</span>
                                    <span class="text-zinc-500 text-[10px] block">concurrent</span>
                                @else
                                    <span class="text-zinc-500">—</span>
                                @endif
                            </td>

                            <!-- Account created (Preserving account created date format) -->
                            <td class="px-3 py-3 whitespace-nowrap text-zinc-400 text-[11px]" title="{{ $stream->organization->created_at?->timezone(\App\Support\LocalTime::tz())->toIso8601String() }}">
                                {{ $stream->organization->created_at ? \App\Support\LocalTime::format($stream->organization->created_at, 'M j, Y') : '—' }}
                            </td>

                            <!-- Ingest / Delivery mode -->
                            <td class="px-3 py-3 whitespace-nowrap font-mono text-[11px] text-zinc-400">
                                <span>{{ $stream->playbackMode() === 'hls' ? 'CDN HLS' : 'WHEP' }}</span>
                                <span class="text-[10px] text-zinc-500 block">OBS / WHIP</span>
                            </td>

                            <!-- Clear differentiated Action model -->
                            <td class="py-3 pl-3 pr-4 sm:pr-6 text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-1.5">
                                    <!-- Primary: Inspect -->
                                    <a
                                        href="{{ route('admin.streams.edit', $stream) }}"
                                        class="inline-flex items-center gap-1 rounded-lg border border-zinc-700 bg-zinc-800 px-2.5 py-1 text-xs font-medium text-zinc-100 hover:bg-zinc-700 hover:text-white transition"
                                    >
                                        <span>Inspect</span>
                                    </a>

                                    <!-- Secondary: Listen -->
                                    <a
                                        href="{{ route('listen.stream', $stream) }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center rounded-lg border border-zinc-800 bg-zinc-900/80 px-2 py-1 text-xs text-zinc-300 hover:bg-zinc-800 hover:text-white transition"
                                        title="Open public listener"
                                    >
                                        Listen
                                    </a>

                                    <!-- Secondary: Open Studio -->
                                    <a
                                        href="{{ route('admin.streams.studio', $stream) }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center rounded-lg border border-zinc-800 bg-zinc-900/80 px-2 py-1 text-xs text-zinc-300 hover:bg-zinc-800 hover:text-white transition"
                                        title="Launch Studio broadcast console"
                                    >
                                        Studio
                                    </a>

                                    <!-- Secondary: Embed -->
                                    <a
                                        href="{{ route('embed.stream', $stream) }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center rounded-lg border border-zinc-800 bg-zinc-900/80 px-2 py-1 text-xs text-zinc-400 hover:bg-zinc-800 hover:text-zinc-200 transition"
                                        title="View audio embed player"
                                    >
                                        Embed
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-zinc-500">
                                <svg class="mx-auto h-8 w-8 text-zinc-600 mb-2" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.348 14.652a3.75 3.75 0 0 1 0-5.304m5.304 0a3.75 3.75 0 0 1 0 5.304m-7.425 2.121a6.75 6.75 0 0 1 0-9.546m9.546 0a6.75 6.75 0 0 1 0 9.546M5.106 18.894c-3.808-3.807-3.808-9.98 0-13.788m13.788 0c3.808 3.807 3.808 9.98 0 13.788M12 12h.008v.008H12V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                </svg>
                                <p class="text-sm font-medium text-zinc-400">No streams found matching the current filters.</p>
                                <p class="text-xs text-zinc-500 mt-1">Try clearing search or filters to see all platform streams.</p>
                                @if ($q !== '' || $status || $organizationId)
                                    <a href="{{ route('admin.streams.index') }}" class="mt-3 inline-block text-xs text-emerald-400 hover:underline">Reset filters</a>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- PAGINATION (PRESERVING QUERY STRING) -->
    <div class="mt-4">
        {{ $streams->links() }}
    </div>
</div>
@endsection
