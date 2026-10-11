@extends('layouts.admin')

@section('title', 'Admin Search Results')

@section('content')
<div class="space-y-6 max-w-7xl mx-auto">
    <!-- Search header -->
    <div class="border-b border-zinc-800 pb-5">
        <a href="{{ route('admin.dashboard') }}" class="text-xs text-zinc-400 hover:text-emerald-400 mb-2 inline-block">&larr; Back to Dashboard</a>
        <h1 class="text-2xl font-bold tracking-tight text-white">Search Results</h1>
        <p class="text-xs text-zinc-400 mt-1">
            @if ($q !== '')
                Found {{ $totalResults }} result{{ $totalResults === 1 ? '' : 's' }} for <span class="font-mono text-zinc-200">"{{ $q }}"</span> across platform entities.
            @else
                Enter a search query in the console top bar.
            @endif
        </p>
    </div>

    @if ($totalResults === 0 && $q !== '')
        <div class="rounded-xl border border-zinc-800 bg-zinc-900/40 p-12 text-center">
            <p class="text-sm text-zinc-400">No matching records found for "{{ $q }}".</p>
            <p class="text-xs text-zinc-500 mt-1">Try searching by stream title, organisation name, user email, teacher name, or affiliate code.</p>
        </div>
    @else
        <!-- Results categories -->
        <div class="space-y-6">
            <!-- 1. Streams -->
            @if ($streams->isNotEmpty())
                <div class="rounded-xl border border-zinc-800 bg-zinc-900/40 overflow-hidden">
                    <div class="border-b border-zinc-800 bg-zinc-900/80 px-4 py-2.5 flex items-center justify-between">
                        <h2 class="text-xs font-semibold uppercase tracking-wider text-zinc-300">Streams ({{ $streams->count() }})</h2>
                        <a href="{{ route('admin.streams.index', ['q' => $q]) }}" class="text-xs text-emerald-400 hover:underline">View in streams &rarr;</a>
                    </div>
                    <div class="divide-y divide-zinc-800/80">
                        @foreach ($streams as $stream)
                            <div class="p-3 sm:px-4 flex items-center justify-between hover:bg-zinc-800/40 transition">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('admin.streams.edit', $stream) }}" class="text-sm font-medium text-white hover:text-emerald-300">{{ $stream->title }}</a>
                                        <span class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-medium uppercase {{ $stream->status->value === 'live' ? 'bg-emerald-950 text-emerald-400 border border-emerald-800' : 'bg-zinc-800 text-zinc-400' }}">
                                            {{ $stream->status->value }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-zinc-400 mt-0.5">Channel: {{ $stream->organization->name }} &middot; UUID: <span class="font-mono text-[10px]">{{ $stream->uuid }}</span></p>
                                </div>
                                <a href="{{ route('admin.streams.edit', $stream) }}" class="rounded border border-zinc-700 bg-zinc-800 px-2.5 py-1 text-xs text-zinc-300 hover:bg-zinc-700">Inspect</a>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- 2. Organisations -->
            @if ($organizations->isNotEmpty())
                <div class="rounded-xl border border-zinc-800 bg-zinc-900/40 overflow-hidden">
                    <div class="border-b border-zinc-800 bg-zinc-900/80 px-4 py-2.5 flex items-center justify-between">
                        <h2 class="text-xs font-semibold uppercase tracking-wider text-zinc-300">Organisations ({{ $organizations->count() }})</h2>
                        <a href="{{ route('admin.organizations.index') }}" class="text-xs text-emerald-400 hover:underline">View all &rarr;</a>
                    </div>
                    <div class="divide-y divide-zinc-800/80">
                        @foreach ($organizations as $org)
                            <div class="p-3 sm:px-4 flex items-center justify-between hover:bg-zinc-800/40 transition">
                                <div>
                                    <a href="{{ route('admin.organizations.edit', $org) }}" class="text-sm font-medium text-white hover:text-emerald-300">{{ $org->name }}</a>
                                    <p class="text-xs text-zinc-400 mt-0.5">Slug: <span class="font-mono text-[10px]">{{ $org->slug }}</span> &middot; Created: {{ $org->created_at?->format('M j, Y') }}</p>
                                </div>
                                <a href="{{ route('admin.organizations.edit', $org) }}" class="rounded border border-zinc-700 bg-zinc-800 px-2.5 py-1 text-xs text-zinc-300 hover:bg-zinc-700">Manage</a>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- 3. Users -->
            @if ($users->isNotEmpty())
                <div class="rounded-xl border border-zinc-800 bg-zinc-900/40 overflow-hidden">
                    <div class="border-b border-zinc-800 bg-zinc-900/80 px-4 py-2.5 flex items-center justify-between">
                        <h2 class="text-xs font-semibold uppercase tracking-wider text-zinc-300">Users ({{ $users->count() }})</h2>
                        <a href="{{ route('admin.users.index', ['q' => $q]) }}" class="text-xs text-emerald-400 hover:underline">View all &rarr;</a>
                    </div>
                    <div class="divide-y divide-zinc-800/80">
                        @foreach ($users as $u)
                            <div class="p-3 sm:px-4 flex items-center justify-between hover:bg-zinc-800/40 transition">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('admin.users.edit', $u) }}" class="text-sm font-medium text-white hover:text-emerald-300">{{ $u->name }}</a>
                                        @if ($u->is_admin)
                                            <span class="rounded bg-emerald-950 px-1.5 py-0.2 text-[10px] text-emerald-400 border border-emerald-800">Admin</span>
                                        @endif
                                    </div>
                                    <p class="text-xs text-zinc-400 mt-0.5">{{ $u->email }}</p>
                                </div>
                                <a href="{{ route('admin.users.edit', $u) }}" class="rounded border border-zinc-700 bg-zinc-800 px-2.5 py-1 text-xs text-zinc-300 hover:bg-zinc-700">Edit</a>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- 4. Digital Library Series -->
            @if ($series->isNotEmpty())
                <div class="rounded-xl border border-zinc-800 bg-zinc-900/40 overflow-hidden">
                    <div class="border-b border-zinc-800 bg-zinc-900/80 px-4 py-2.5 flex items-center justify-between">
                        <h2 class="text-xs font-semibold uppercase tracking-wider text-zinc-300">Digital Library Series ({{ $series->count() }})</h2>
                        <a href="{{ route('admin.digital-library.series.index') }}" class="text-xs text-emerald-400 hover:underline">View all &rarr;</a>
                    </div>
                    <div class="divide-y divide-zinc-800/80">
                        @foreach ($series as $s)
                            <div class="p-3 sm:px-4 flex items-center justify-between hover:bg-zinc-800/40 transition">
                                <div>
                                    <a href="{{ route('admin.digital-library.series.show', $s) }}" class="text-sm font-medium text-white hover:text-emerald-300">{{ $s->title }}</a>
                                    <p class="text-xs text-zinc-400 mt-0.5">Teacher: {{ $s->teacher?->display_name ?: 'None' }} &middot; Status: {{ $s->status->value }}</p>
                                </div>
                                <a href="{{ route('admin.digital-library.series.show', $s) }}" class="rounded border border-zinc-700 bg-zinc-800 px-2.5 py-1 text-xs text-zinc-300 hover:bg-zinc-700">View</a>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- 5. Affiliates -->
            @if ($affiliates->isNotEmpty())
                <div class="rounded-xl border border-zinc-800 bg-zinc-900/40 overflow-hidden">
                    <div class="border-b border-zinc-800 bg-zinc-900/80 px-4 py-2.5 flex items-center justify-between">
                        <h2 class="text-xs font-semibold uppercase tracking-wider text-zinc-300">Affiliate Accounts ({{ $affiliates->count() }})</h2>
                        <a href="{{ route('admin.affiliates.index') }}" class="text-xs text-emerald-400 hover:underline">View all &rarr;</a>
                    </div>
                    <div class="divide-y divide-zinc-800/80">
                        @foreach ($affiliates as $aff)
                            <div class="p-3 sm:px-4 flex items-center justify-between hover:bg-zinc-800/40 transition">
                                <div>
                                    <a href="{{ route('admin.affiliates.show', $aff) }}" class="text-sm font-medium text-white hover:text-emerald-300">{{ $aff->user?->name ?: 'Affiliate' }}</a>
                                    <p class="text-xs text-zinc-400 mt-0.5">Code: <span class="font-mono text-emerald-400">{{ $aff->referral_code }}</span> &middot; Status: {{ $aff->status->value }}</p>
                                </div>
                                <a href="{{ route('admin.affiliates.show', $aff) }}" class="rounded border border-zinc-700 bg-zinc-800 px-2.5 py-1 text-xs text-zinc-300 hover:bg-zinc-700">View</a>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>
@endsection
