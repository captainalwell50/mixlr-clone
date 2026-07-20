@extends('layouts.stream')

@section('title', $stream->title.' · Recorded Audio')

@section('vite')
    @vite(['resources/js/archive-player.js'])
@endsection

@php
    $organization = $stream->organization;
    $theme = $organization->themeColor();
    $artwork = $organization->artworkUrl();
@endphp

@section('content')
    <div class="stage" style="--stage-accent: {{ $theme }};">
        <div
            class="stage-atmosphere {{ $artwork ? 'has-art' : '' }}"
            @if ($artwork) style="--stage-art: url('{{ $artwork }}')" @endif
        ></div>

        <div class="stage-content">
            <header class="stage-top stage-rise">
                <p class="stage-platform">{{ config('app.name', 'Live Mix Audio') }}</p>
                <a href="{{ route('archive.index') }}" class="stage-top-link">Recorded Audio</a>
            </header>

            <p class="stage-status stage-rise-delay is-idle">Recording</p>
            <h1 class="stage-channel stage-rise-delay">{{ $organization->name }}</h1>
            <p class="stage-title stage-rise-delay-2">{{ $stream->title }}</p>
            <p class="stage-meta">
                {{ $recording->completed_at->timezone(config('app.timezone'))->format('M j, Y · g:i A') }}
            </p>

            <div class="stage-player stage-rise-delay-2 archive-player">
                <div id="stage-wave" class="stage-wave" aria-hidden="true"></div>
                <div class="stage-transport">
                    <button type="button" id="btn-volume" class="stage-transport-btn" aria-label="Mute" title="Volume">
                        <svg class="icon-volume" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M3 10v4h4l5 4V6L7 10H3zm13.5 2a3.5 3.5 0 0 0-1.8-3.1v6.2A3.5 3.5 0 0 0 16.5 12zM14 4.7v2.1a5.5 5.5 0 0 1 0 10.4v2.1a7.5 7.5 0 0 0 0-14.6z"/></svg>
                        <svg class="icon-muted hidden" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M16.5 12a3.5 3.5 0 0 0-1.8-3.1v2.36l1.75 1.75c.03-.33.05-.67.05-1.01zm2.5 0c0 .94-.2 1.82-.54 2.64l1.51 1.51A8.8 8.8 0 0 0 21 12c0-3.53-2.04-6.55-5-7.97v2.21A5.5 5.5 0 0 1 19 12zM4.27 3 3 4.27 7.73 9H3v6h4l5 5v-6.73l4.25 4.25c-.67.52-1.42.93-2.25 1.18v2.14a8.94 8.94 0 0 0 3.69-1.81L19.73 21 21 19.73l-9-9L4.27 3zM12 4 9.91 6.09 12 8.18V4z"/></svg>
                    </button>
                    <button type="button" id="btn-skip-back" class="stage-transport-btn" aria-label="Back 15 seconds" title="−15s">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M11.99 5V1l-5 5 5 5V7c3.31 0 6 2.69 6 6s-2.69 6-6 6-6-2.69-6-6h-2c0 4.42 3.58 8 8 8s8-3.58 8-8-3.58-8-8-8zm-1.06 8.63 2.53 1.53-.75 1.3-3.19-1.92V8.89h1.5v4.74z"/></svg>
                    </button>
                    <div id="stage-play-shell" class="player-breath rounded-full">
                        <button type="button" id="btn-play" class="stage-play" aria-label="Play">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5.14v13.72a1 1 0 0 0 1.5.86l11-6.86a1 1 0 0 0 0-1.72l-11-6.86a1 1 0 0 0-1.5.86z"/></svg>
                        </button>
                    </div>
                    <button type="button" id="btn-skip-forward" class="stage-transport-btn" aria-label="Forward 15 seconds" title="+15s">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 5V1l5 5-5 5V7c-3.31 0-6 2.69-6 6s2.69 6 6 6 6-2.69 6-6h2c0 4.42-3.58 8-8 8s-8-3.58-8-8 3.58-8 8-8zm1.09 8.63V8.89h-1.5v4.65l-3.19 1.92.75 1.3 2.94-1.78z"/></svg>
                    </button>
                    <button type="button" id="btn-wave-toggle" class="stage-transport-btn is-on" aria-label="Toggle visualizer" aria-pressed="true" title="Visualizer">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M4 10h2v8H4v-8zm5-6h2v14H9V4zm5 3h2v11h-2V7zm5 4h2v7h-2v-7z"/></svg>
                    </button>
                </div>

                <div class="archive-scrub" aria-label="Playback progress">
                    <span id="archive-time-current" class="archive-time">0:00</span>
                    <input
                        id="archive-seek"
                        class="archive-seek"
                        type="range"
                        min="0"
                        max="0"
                        value="0"
                        step="0.1"
                        aria-label="Seek"
                        disabled
                    >
                    <span id="archive-time-duration" class="archive-time">0:00</span>
                </div>

                <audio id="stream-audio" class="sr-only" playsinline preload="metadata"></audio>
                <p id="stream-status" class="stage-status-line">Press play to listen</p>
            </div>
            <div id="archive-root" data-src="{{ $fileUrl }}" class="hidden"></div>
        </div>
    </div>
@endsection
