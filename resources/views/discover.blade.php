@extends('layouts.app')

@section('title', 'Discover · '.config('app.name', 'Live Mix Audio'))
@section('main_class', 'w-full')

@section('content')
    <div class="discover-page site-page">
        <div class="discover-head stage-rise">
            <div>
                <p class="site-section-label is-live">Live Mix Audio</p>
                <h1 class="mt-2">Discover</h1>
                <p class="mt-2 max-w-md text-sm text-[var(--stage-muted)]">
                    What’s on air now — and what’s coming up.
                </p>
            </div>
            <form method="GET" action="{{ route('discover') }}" class="discover-search">
                <input type="search" name="q" value="{{ $q }}" placeholder="Search events or channels" aria-label="Search">
                <button type="submit">Search</button>
            </form>
        </div>

        <section class="mt-10 stage-rise-delay">
            <h2 class="site-section-label is-live">Live now</h2>

            @if ($live->isEmpty())
                <p class="site-empty">No one is live right now{{ $q ? ' matching that search' : '' }}.</p>
            @else
                <div class="live-rail">
                    @foreach ($live as $event)
                        @php
                            $accent = $event->organization->themeColor();
                            $art = $event->artworkUrl();
                        @endphp
                        <a href="{{ route('events.show', $event) }}"
                            class="live-card"
                            style="--tile-accent: {{ $accent }};">
                            <div
                                class="live-card-art {{ $art ? 'has-art' : '' }}"
                                @if ($art) style="--tile-art: url('{{ $art }}')" @endif
                            >
                                <div class="live-card-copy">
                                    <span class="live-badge">
                                        <span class="live-dot inline-block h-1.5 w-1.5 rounded-full bg-current"></span>
                                        Live
                                    </span>
                                    <h3>{{ $event->title }}</h3>
                                    <p>{{ $event->organization->name }}</p>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="mt-14 stage-rise-delay-2">
            <h2 class="site-section-label">Upcoming</h2>
            @if ($upcoming->isEmpty())
                <p class="site-empty">No upcoming public events.</p>
            @else
                <div class="art-grid">
                    @foreach ($upcoming as $event)
                        @include('partials.art-tile', [
                            'href' => route('events.show', $event),
                            'title' => $event->title,
                            'subtitle' => $event->organization->name
                                .' · '.($event->scheduled_at?->format('M j, H:i') ?: 'TBA'),
                            'artwork' => $event->artworkUrl(),
                            'accent' => $event->organization->themeColor(),
                        ])
                    @endforeach
                </div>
            @endif
        </section>
    </div>
@endsection
