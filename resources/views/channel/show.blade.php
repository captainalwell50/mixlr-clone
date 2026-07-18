@extends('layouts.app')

@section('title', $organization->name.' · '.config('app.name'))

@section('content')
    @php $theme = $organization->themeColor(); @endphp
    <style>
        .channel-accent { color: {{ $theme }}; }
        .channel-accent-bg { background-color: {{ $theme }}; }
        .channel-accent-border { border-color: {{ $theme }}; }
    </style>

    <div class="overflow-hidden rounded-2xl border border-zinc-800 bg-zinc-900/40">
        <div class="h-28 w-full channel-accent-bg opacity-80" style="background: linear-gradient(135deg, {{ $theme }}, #0c0f12);"></div>
        <div class="px-6 pb-6 -mt-8">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-3xl font-semibold text-white">{{ $organization->name }}</h1>
                    @if ($organization->tagline)
                        <p class="mt-1 text-sm text-zinc-400">{{ $organization->tagline }}</p>
                    @endif
                </div>
                <div class="flex flex-wrap gap-2">
                    @if ($organization->support_url)
                        <a href="{{ $organization->support_url }}" target="_blank" rel="noopener"
                            class="rounded-lg border border-zinc-600 px-4 py-2 text-sm text-zinc-200 hover:bg-zinc-800">Support</a>
                    @endif
                    @auth
                        @if ($following)
                            <form method="POST" action="{{ route('channels.unfollow', $organization) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded-lg border border-zinc-600 px-4 py-2 text-sm text-zinc-300 hover:bg-zinc-800">Following</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('channels.follow', $organization) }}">
                                @csrf
                                <button type="submit" class="rounded-lg channel-accent-bg px-4 py-2 text-sm font-semibold text-white">Follow</button>
                            </form>
                        @endif
                    @else
                        <a href="{{ route('login') }}" class="rounded-lg channel-accent-bg px-4 py-2 text-sm font-semibold text-white">Log in to follow</a>
                    @endauth
                </div>
            </div>
        </div>
    </div>

    @if ($live)
        <section class="mt-8 rounded-xl border channel-accent-border bg-zinc-900/50 p-5">
            <p class="text-xs font-semibold uppercase tracking-wide channel-accent">Live now</p>
            <h2 class="mt-1 text-xl font-semibold text-white">{{ $live->title }}</h2>
            <a href="{{ route('events.show', $live) }}" class="mt-4 inline-flex rounded-lg channel-accent-bg px-4 py-2 text-sm font-semibold text-white">Listen</a>
        </section>
    @endif

    <section class="mt-10">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500">Upcoming</h2>
        <div class="mt-3 grid gap-3 sm:grid-cols-2">
            @forelse ($upcoming as $event)
                <a href="{{ route('events.show', $event) }}" class="rounded-xl border border-zinc-800 p-4 hover:border-zinc-700">
                    <h3 class="font-medium text-white">{{ $event->title }}</h3>
                    <p class="mt-1 text-xs text-zinc-500">
                        {{ $event->scheduled_at?->timezone(config('app.timezone'))->format('M j, Y H:i') ?: 'TBA' }}
                    </p>
                </a>
            @empty
                <p class="text-sm text-zinc-500">No upcoming events.</p>
            @endforelse
        </div>
    </section>

    <section class="mt-10">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500">Recordings</h2>
        <div class="mt-3 space-y-2">
            @forelse ($recordings as $recording)
                <a href="{{ route('archive.play', $recording) }}" class="flex justify-between rounded-lg border border-zinc-800 px-4 py-3 text-sm hover:border-zinc-700">
                    <span class="text-white">{{ $recording->stream->title }}</span>
                    <span class="text-zinc-500">{{ $recording->completed_at->format('M j, Y') }}</span>
                </a>
            @empty
                <p class="text-sm text-zinc-500">No recordings yet.</p>
            @endforelse
        </div>
    </section>
@endsection
