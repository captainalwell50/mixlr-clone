@extends('layouts.app')

@section('title', 'Discover · '.config('app.name'))

@section('content')
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-white">Discover</h1>
            <p class="mt-1 text-sm text-zinc-400">Live and upcoming public events.</p>
        </div>
        <form method="GET" action="{{ route('discover') }}" class="flex w-full gap-2 sm:w-auto">
            <input type="search" name="q" value="{{ $q }}" placeholder="Search events or channels"
                class="w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-sm text-white sm:w-64 focus:border-emerald-600 focus:outline-none">
            <button type="submit" class="rounded-lg bg-zinc-800 px-4 py-2 text-sm text-white hover:bg-zinc-700">Search</button>
        </form>
    </div>

    <section class="mt-10">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-emerald-400/90">Live now</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            @forelse ($live as $event)
                <a href="{{ route('events.show', $event) }}"
                    class="block rounded-xl border border-emerald-900/40 bg-emerald-950/20 p-4 transition hover:border-emerald-700/60">
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-400">Live</p>
                    <h3 class="mt-1 text-lg font-semibold text-white">{{ $event->title }}</h3>
                    <p class="mt-1 text-sm text-zinc-400">{{ $event->organization->name }}</p>
                </a>
            @empty
                <p class="col-span-full text-sm text-zinc-500">No one is live right now{{ $q ? ' matching that search' : '' }}.</p>
            @endforelse
        </div>
    </section>

    <section class="mt-12">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500">Upcoming</h2>
        <div class="mt-4 grid gap-3 sm:grid-cols-2">
            @forelse ($upcoming as $event)
                <a href="{{ route('events.show', $event) }}"
                    class="block rounded-xl border border-zinc-800 bg-zinc-900/40 p-4 hover:border-zinc-700">
                    <h3 class="font-medium text-white">{{ $event->title }}</h3>
                    <p class="mt-1 text-sm text-zinc-500">{{ $event->organization->name }}</p>
                    <p class="mt-1 text-xs text-zinc-600">{{ $event->scheduled_at?->format('M j, H:i') ?: 'TBA' }}</p>
                </a>
            @empty
                <p class="col-span-full text-sm text-zinc-500">No upcoming public events.</p>
            @endforelse
        </div>
    </section>
@endsection
