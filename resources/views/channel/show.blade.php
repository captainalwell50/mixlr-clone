@extends('layouts.app')

@section('title', $organization->name.' · '.config('app.name', 'Live Mix Audio'))
@section('main_class', 'w-full')

@php
    $theme = $organization->themeColor();
    $artwork = $organization->artworkUrl();
    $isOnAir = $live || $liveStream;
    $listenUrl = $live
        ? route('events.show', $live)
        : ($liveStream ? route('listen.stream', $liveStream) : null);
    $thisSunday = $live ?? $upcoming->first();
    $hasPrimaryCta = $isOnAir || $thisSunday;
@endphp

@section('content')
    <div class="site-page" style="--site-accent: {{ $theme }};">
        <header
            class="site-hero stage-rise {{ $artwork ? 'has-art' : '' }}"
            @if ($artwork) style="--site-art: url('{{ $artwork }}')" @endif
        >
            <div class="site-hero-inner">
                @if ($organization->logo_path)
                    <img src="{{ $organization->logo_path }}" alt=""
                        class="mb-4 h-14 w-14 rounded-xl object-cover ring-1 ring-white/15">
                @endif

                <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em]"
                    style="color: var(--site-accent)">
                    @if ($isOnAir)
                        <span class="live-dot inline-block h-1.5 w-1.5 rounded-full bg-current"></span>
                        Live now
                    @else
                        Channel
                    @endif
                </p>

                <h1 class="site-brand stage-rise-delay">{{ $organization->name }}</h1>

                @if ($organization->tagline)
                    <p class="site-tagline stage-rise-delay">{{ $organization->tagline }}</p>
                @endif

                <div class="site-actions stage-rise-delay-2">
                    @if ($listenUrl)
                        <a href="{{ $listenUrl }}" class="site-btn site-btn-primary">Listen live</a>
                    @elseif ($thisSunday)
                        <a href="{{ route('events.show', $thisSunday) }}" class="site-btn site-btn-primary">This Sunday</a>
                    @endif

                    @auth
                        @if ($following)
                            <form method="POST" action="{{ route('channels.unfollow', $organization) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="site-btn site-btn-ghost">Following</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('channels.follow', $organization) }}">
                                @csrf
                                <button type="submit" class="site-btn {{ $hasPrimaryCta ? 'site-btn-ghost' : 'site-btn-primary' }}">Follow</button>
                            </form>
                        @endif
                    @else
                        <a href="{{ route('login') }}" class="site-btn {{ $hasPrimaryCta ? 'site-btn-ghost' : 'site-btn-primary' }}">
                            Log in to follow
                        </a>
                    @endauth

                    @if ($organization->support_url)
                        <a href="{{ $organization->support_url }}" target="_blank" rel="noopener"
                            class="site-btn site-btn-ghost">Support</a>
                    @endif
                </div>
            </div>
        </header>

        <div class="site-body">
            @if ($liveStream && ! $live)
                <section class="this-sunday stage-rise">
                    <p class="site-section-label is-live">On air</p>
                    <h2>{{ $liveStream->title }}</h2>
                    <p>Happening now — open the stage and listen in.</p>
                    <div class="site-actions">
                        <a href="{{ route('listen.stream', $liveStream) }}" class="site-btn site-btn-primary">
                            Enter stage
                        </a>
                    </div>
                </section>
            @elseif ($thisSunday)
                <section class="this-sunday stage-rise">
                    <p class="site-section-label {{ $live ? 'is-live' : '' }}">
                        {{ $live ? 'On air' : 'This Sunday' }}
                    </p>
                    <h2>{{ $thisSunday->title }}</h2>
                    <p>
                        @if ($live)
                            Happening now — open the stage and listen in.
                        @elseif ($thisSunday->scheduled_at)
                            {{ $thisSunday->scheduled_at->timezone(config('app.timezone'))->format('l, M j · g:i A T') }}
                        @else
                            Scheduled soon.
                        @endif
                    </p>
                    <div class="site-actions">
                        <a href="{{ route('events.show', $thisSunday) }}" class="site-btn site-btn-primary">
                            {{ $live ? 'Enter stage' : 'Open event' }}
                        </a>
                    </div>
                </section>
            @endif

            <section class="mt-12">
                <h2 class="site-section-label">Upcoming</h2>
                @if ($upcoming->isEmpty())
                    <p class="site-empty">No upcoming events.</p>
                @else
                    <div class="art-grid">
                        @foreach ($upcoming as $event)
                            @include('partials.art-tile', [
                                'href' => route('events.show', $event),
                                'title' => $event->title,
                                'subtitle' => $event->scheduled_at
                                    ? $event->scheduled_at->timezone(config('app.timezone'))->format('M j · g:i A')
                                    : 'TBA',
                                'artwork' => $event->artworkUrl(),
                                'accent' => $theme,
                            ])
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="mt-12">
                <h2 class="site-section-label">Recordings</h2>
                @if ($recordings->isEmpty())
                    <p class="site-empty">No recordings yet.</p>
                @else
                    <div class="art-grid">
                        @foreach ($recordings as $recording)
                            @include('partials.art-tile', [
                                'href' => route('archive.play', $recording),
                                'title' => $recording->stream->title,
                                'subtitle' => $recording->completed_at->format('M j, Y'),
                                'artwork' => $organization->artworkUrl(),
                                'accent' => $theme,
                            ])
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </div>
@endsection
