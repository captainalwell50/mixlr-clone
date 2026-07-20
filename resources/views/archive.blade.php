@extends('layouts.app')

@section('title', 'Recorded Audio · '.config('app.name', 'Live Mix Audio'))
@section('main_class', 'w-full')

@section('content')
    <div class="archive-page site-page">
        <div class="archive-head stage-rise">
            <div>
                <p class="site-section-label">Past broadcasts</p>
                <h1 class="mt-2">Recorded Audio</h1>
                <p class="mt-2 max-w-lg text-sm text-[var(--stage-muted)]">
                    Catch up on services you missed — open any recording to listen with full playback controls.
                </p>
            </div>
            <a href="{{ route('discover') }}" class="archive-head-link">Discover live</a>
        </div>

        @if ($recordings->isEmpty())
            <div class="archive-empty stage-rise-delay">
                <p class="archive-empty-title">No recordings yet</p>
                <p>Past broadcasts will appear here after a live service ends.</p>
            </div>
        @else
            <ul class="archive-list stage-rise-delay">
                @foreach ($recordings as $recording)
                    @php
                        $org = $recording->stream->organization;
                        $art = $org->artworkUrl();
                        $accent = $org->themeColor();
                    @endphp
                    <li class="archive-row" style="--tile-accent: {{ $accent }};">
                        <div
                            class="archive-art {{ $art ? 'has-art' : '' }}"
                            @if ($art) style="--tile-art: url('{{ $art }}')" @endif
                            aria-hidden="true"
                        >
                            @unless ($art)
                                <span>{{ strtoupper(substr($org->name, 0, 1)) }}</span>
                            @endunless
                        </div>

                        <div class="archive-copy">
                            <h2>{{ $recording->stream->title }}</h2>
                            <p class="archive-channel">{{ $org->name }}</p>
                            <div class="archive-meta">
                                <span>{{ $recording->completed_at->timezone(config('app.timezone'))->format('M j, Y · g:i A') }}</span>
                                <span>{{ $recording->durationLabel() }}</span>
                                <span>{{ $recording->sizeLabel() }}</span>
                            </div>
                        </div>

                        <a
                            href="{{ route('archive.play', $recording) }}"
                            class="archive-play"
                            target="_blank"
                            rel="noopener"
                        >
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M8 5.14v13.72a1 1 0 0 0 1.5.86l11-6.86a1 1 0 0 0 0-1.72l-11-6.86a1 1 0 0 0-1.5.86z"/></svg>
                            Play
                        </a>
                    </li>
                @endforeach
            </ul>

            <div class="archive-pagination">
                {{ $recordings->links() }}
            </div>
        @endif
    </div>
@endsection
