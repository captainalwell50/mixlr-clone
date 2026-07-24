@extends('layouts.app')

@section('title', 'Podcasts · '.config('app.name', 'Sound Mix Live'))
@section('main_class', 'w-full')

@section('content')
    <div class="archive-page site-page">
        <div class="archive-head stage-rise">
            <div>
                <p class="site-section-label">Catch up</p>
                <h1 class="mt-2">Podcasts</h1>
                <p class="mt-2 max-w-lg text-sm text-[var(--stage-muted)]">
                    Browse channels, then open their recorded lives and podcasts.
                </p>
            </div>
            <a href="{{ route('discover') }}" class="archive-head-link">Discover live</a>
        </div>

        @if ($channels->isEmpty())
            <div class="archive-empty stage-rise-delay">
                <p class="archive-empty-title">No podcasts yet</p>
                <p>Channels appear here after studios upload a broadcast.</p>
            </div>
        @else
            <ul class="archive-channel-grid stage-rise-delay">
                @foreach ($channels as $channel)
                    @php
                        $art = $channel->artworkUrl();
                        $accent = $channel->themeColor();
                        $count = (int) $channel->podcasts_count;
                    @endphp
                    <li>
                        <a
                            href="{{ route('archive.channel', $channel) }}"
                            class="archive-channel-card"
                            style="--tile-accent: {{ $accent }};"
                        >
                            <div
                                class="archive-art {{ $art ? 'has-art' : '' }}"
                                @if ($art) style="--tile-art: url('{{ $art }}')" @endif
                                aria-hidden="true"
                            >
                                @unless ($art)
                                    <span>{{ strtoupper(substr($channel->name, 0, 1)) }}</span>
                                @endunless
                            </div>
                            <div class="archive-copy">
                                <h2>{{ $channel->name }}</h2>
                                <p class="archive-channel">
                                    {{ $count }} {{ \Illuminate\Support\Str::plural('podcast', $count) }}
                                </p>
                            </div>
                            <span class="archive-play" aria-hidden="true">Open</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
