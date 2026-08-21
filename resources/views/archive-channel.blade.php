@extends('layouts.app')

@section('title', $organization->name.' · Podcasts')
@section('main_class', 'w-full')

@section('content')
    @php
        $art = $organization->artworkUrl();
        $accent = $organization->themeColor();
    @endphp
    <div class="archive-page site-page">
        <div class="archive-head stage-rise">
            <div>
                <h1>{{ $organization->name }}</h1>
            </div>
            <a href="{{ $organization->channelUrl() }}" class="archive-head-link">Channel page</a>
        </div>

        @if ($recordings->isEmpty())
            <div class="archive-empty stage-rise-delay">
                <p class="archive-empty-title">No podcasts yet</p>
                <p>This channel hasn’t uploaded podcasts.</p>
            </div>
        @else
            <ul class="archive-list stage-rise-delay">
                @foreach ($recordings as $recording)
                    <li class="archive-row" style="--tile-accent: {{ $accent }};">
                        <div
                            class="archive-art {{ $art ? 'has-art' : '' }}"
                            @if ($art) style="--tile-art: url('{{ $art }}')" @endif
                            aria-hidden="true"
                        >
                            @unless ($art)
                                <span>{{ strtoupper(substr($organization->name, 0, 1)) }}</span>
                            @endunless
                        </div>

                        <div class="archive-copy">
                            <h2>{{ $recording->displayTitle() }}</h2>
                            <p class="archive-channel">
                                @if ($recording->event)
                                    Event recording
                                @else
                                    Podcast
                                @endif
                            </p>
                            <div class="archive-meta">
                                <span>{{ $recording->completed_at->timezone(config('app.timezone'))->format('M j, Y · g:i A') }}</span>
                                <span>{{ $recording->durationLabel() }}</span>
                                <span>{{ $recording->sizeLabel() }}</span>
                            </div>
                        </div>

                        <div class="archive-row-actions">
                            <a
                                href="{{ route('archive.play', $recording) }}"
                                class="archive-play"
                                target="_blank"
                                rel="noopener"
                            >
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M8 5.14v13.72a1 1 0 0 0 1.5.86l11-6.86a1 1 0 0 0 0-1.72l-11-6.86a1 1 0 0 0-1.5.86z"/></svg>
                                Play
                            </a>
                            @if ($canManage)
                                <form
                                    method="POST"
                                    action="{{ URL::temporarySignedRoute('recordings.destroy', now()->addHours(12), ['stream' => $recording->stream, 'recording' => $recording]) }}"
                                    class="archive-delete-form"
                                    onsubmit="return confirm('Delete this podcast permanently?');"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="archive-delete">Delete</button>
                                </form>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>

            <div class="archive-pagination">
                {{ $recordings->links() }}
            </div>
        @endif
    </div>
@endsection
