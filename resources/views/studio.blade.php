@extends('layouts.stream')

@section('title', 'Studio · '.$stream->title)

@section('vite')
    @vite(['resources/js/studio.js'])
@endsection

@php
    $theme = $organization->themeColor();
    $artwork = $organization->artworkUrl();
@endphp

@section('content')
    <div id="studio-stage" class="mixer" style="--mixer-accent: {{ $theme }};">
        <header class="mixer-topbar">
            <a href="{{ url('/') }}" class="mixer-brand">{{ config('app.name', 'Live Mix Audio') }}</a>
            <div class="mixer-topbar-right">
                <span class="mixer-user">{{ $organization->name }}</span>
                <a href="{{ route('dashboard') }}" class="mixer-top-link">Dashboard</a>
            </div>
        </header>

        <section class="mixer-hero">
            <div class="mixer-hero-art" style="background-image: url('{{ $artwork ?: asset('images/listen-stage-bg.jpg') }}')"></div>
            <div class="mixer-hero-copy">
                <p class="mixer-hero-kicker" id="studio-mode">Off air</p>
                <h1 class="mixer-hero-title">{{ $stream->title }}</h1>
                <p class="mixer-hero-sub" id="studio-hero-hint">Click Start to go live</p>
            </div>
            <div class="mixer-hero-actions">
                <a class="mixer-icon-btn" href="{{ route('dashboard') }}" title="Back to dashboard" aria-label="Back to dashboard">←</a>
                <button type="button" class="mixer-icon-btn" id="btn-copy-listen" title="Copy listen link" aria-label="Copy listen link">↗</button>
            </div>
        </section>

        <section class="mixer-board">
            <div class="mixer-strips" id="mixer-strips">
                {{-- MIC --}}
                <div class="mixer-strip" data-strip="mic" id="mic-channel">
                    <div class="mixer-strip-meter" aria-hidden="true">
                        <div class="mixer-strip-meter-fill" id="mic-meter"></div>
                    </div>
                    <input
                        id="mic-fader"
                        class="mixer-fader"
                        type="range"
                        min="0"
                        max="150"
                        value="100"
                        step="1"
                        orient="vertical"
                        aria-label="Mic level"
                    >
                    <div class="mixer-strip-toggles">
                        <button type="button" class="mixer-cue" id="mic-cue" aria-pressed="false" title="Cue in headphones (off by default)">
                            <span class="sr-only">Cue mic</span>
                            <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M12 3a4 4 0 0 0-4 4v4a4 4 0 0 0 8 0V7a4 4 0 0 0-4-4Zm-7 8a1 1 0 0 1 1 1 6 6 0 0 0 12 0 1 1 0 1 1 2 0 8 8 0 0 1-7 7.93V22h3a1 1 0 1 1 0 2H8a1 1 0 1 1 0-2h3v-2.07A8 8 0 0 1 4 12a1 1 0 0 1 1-1Z"/></svg>
                        </button>
                        <button type="button" class="mixer-mute" id="mic-mute" aria-pressed="false" title="Mute mic">
                            <span class="sr-only">Mute mic</span>
                            <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M3.28 2.22 2.22 3.28l4.1 4.1A6.96 6.96 0 0 0 5 12a1 1 0 1 0 2 0 5 5 0 0 1 .4-1.96l1.6 1.6A4 4 0 0 0 12 17a3.98 3.98 0 0 0 2.36-.77l1.5 1.5A5.97 5.97 0 0 1 12 19a6 6 0 0 1-6-6H4a8 8 0 0 0 7 7.93V22H8a1 1 0 1 0 0 2h8a1 1 0 1 0 0-2h-3v-1.07a7.96 7.96 0 0 0 3.78-1.5l4.94 4.95 1.06-1.06L3.28 2.22ZM16.8 14.66l1.48 1.48A7.9 7.9 0 0 0 20 12a1 1 0 1 0-2 0c0 .96-.2 1.87-.56 2.66ZM9.17 6.99l5.7 5.7A4 4 0 0 0 16 11V7a4 4 0 0 0-6.83-.01Z"/></svg>
                        </button>
                    </div>
                    <p class="mixer-strip-label">MIC</p>
                    <label class="sr-only" for="audio-input">Microphone</label>
                    <select id="audio-input" class="mixer-source"></select>
                </div>

                {{-- ANY INPUT --}}
                <div class="mixer-strip" data-strip="aux" id="aux-channel">
                    <div class="mixer-strip-meter" aria-hidden="true">
                        <div class="mixer-strip-meter-fill" id="aux-meter"></div>
                    </div>
                    <input
                        id="aux-fader"
                        class="mixer-fader"
                        type="range"
                        min="0"
                        max="150"
                        value="100"
                        step="1"
                        orient="vertical"
                        aria-label="Any input level"
                    >
                    <div class="mixer-strip-toggles">
                        <button type="button" class="mixer-cue" id="aux-cue" aria-pressed="false" title="Cue in headphones (off by default)">
                            <span class="sr-only">Cue input</span>
                            <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M12 3a4 4 0 0 0-4 4v4a4 4 0 0 0 8 0V7a4 4 0 0 0-4-4Zm-7 8a1 1 0 0 1 1 1 6 6 0 0 0 12 0 1 1 0 1 1 2 0 8 8 0 0 1-7 7.93V22h3a1 1 0 1 1 0 2H8a1 1 0 1 1 0-2h3v-2.07A8 8 0 0 1 4 12a1 1 0 0 1 1-1Z"/></svg>
                        </button>
                        <button type="button" class="mixer-mute is-active" id="aux-mute" aria-pressed="true" title="Mute input">
                            <span class="sr-only">Mute input</span>
                            <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M3.28 2.22 2.22 3.28l4.1 4.1A6.96 6.96 0 0 0 5 12a1 1 0 1 0 2 0 5 5 0 0 1 .4-1.96l1.6 1.6A4 4 0 0 0 12 17a3.98 3.98 0 0 0 2.36-.77l1.5 1.5A5.97 5.97 0 0 1 12 19a6 6 0 0 1-6-6H4a8 8 0 0 0 7 7.93V22H8a1 1 0 1 0 0 2h8a1 1 0 1 0 0-2h-3v-1.07a7.96 7.96 0 0 0 3.78-1.5l4.94 4.95 1.06-1.06L3.28 2.22ZM16.8 14.66l1.48 1.48A7.9 7.9 0 0 0 20 12a1 1 0 1 0-2 0c0 .96-.2 1.87-.56 2.66ZM9.17 6.99l5.7 5.7A4 4 0 0 0 16 11V7a4 4 0 0 0-6.83-.01Z"/></svg>
                        </button>
                    </div>
                    <p class="mixer-strip-label">ANY INPUT</p>
                    <label class="sr-only" for="aux-input">Any input</label>
                    <select id="aux-input" class="mixer-source">
                        <option value="">Select source</option>
                    </select>
                </div>

                {{-- PLAYLIST --}}
                <div class="mixer-strip" data-strip="playlist" id="playlist-channel">
                    <div class="mixer-strip-meter" aria-hidden="true">
                        <div class="mixer-strip-meter-fill" id="playlist-meter"></div>
                    </div>
                    <input
                        id="playlist-fader"
                        class="mixer-fader"
                        type="range"
                        min="0"
                        max="150"
                        value="100"
                        step="1"
                        orient="vertical"
                        aria-label="Playlist level"
                    >
                    <div class="mixer-strip-toggles">
                        <button type="button" class="mixer-cue" id="playlist-cue" aria-pressed="false" title="Cue playlist (off by default)">
                            <span class="sr-only">Cue playlist</span>
                            <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M12 3a4 4 0 0 0-4 4v4a4 4 0 0 0 8 0V7a4 4 0 0 0-4-4Zm-7 8a1 1 0 0 1 1 1 6 6 0 0 0 12 0 1 1 0 1 1 2 0 8 8 0 0 1-7 7.93V22h3a1 1 0 1 1 0 2H8a1 1 0 1 1 0-2h3v-2.07A8 8 0 0 1 4 12a1 1 0 0 1 1-1Z"/></svg>
                        </button>
                        <button type="button" class="mixer-mute" id="playlist-mute" aria-pressed="false" title="Mute playlist">
                            <span class="sr-only">Mute playlist</span>
                            <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M3.28 2.22 2.22 3.28l4.1 4.1A6.96 6.96 0 0 0 5 12a1 1 0 1 0 2 0 5 5 0 0 1 .4-1.96l1.6 1.6A4 4 0 0 0 12 17a3.98 3.98 0 0 0 2.36-.77l1.5 1.5A5.97 5.97 0 0 1 12 19a6 6 0 0 1-6-6H4a8 8 0 0 0 7 7.93V22H8a1 1 0 1 0 0 2h8a1 1 0 1 0 0-2h-3v-1.07a7.96 7.96 0 0 0 3.78-1.5l4.94 4.95 1.06-1.06L3.28 2.22ZM16.8 14.66l1.48 1.48A7.9 7.9 0 0 0 20 12a1 1 0 1 0-2 0c0 .96-.2 1.87-.56 2.66ZM9.17 6.99l5.7 5.7A4 4 0 0 0 16 11V7a4 4 0 0 0-6.83-.01Z"/></svg>
                        </button>
                    </div>
                    <p class="mixer-strip-label">PLAYLIST</p>
                    <p class="mixer-source mixer-source--static" id="playlist-count">No sounds</p>
                </div>

                {{-- OUT --}}
                <div class="mixer-strip mixer-strip--out" data-strip="out">
                    <div class="mixer-strip-meter mixer-strip-meter--out" aria-hidden="true">
                        <div class="mixer-strip-meter-fill" id="level-meter"></div>
                    </div>
                    <div class="mixer-out-tools">
                        <label class="mixer-sq" title="Output layout">
                            <span>SQ</span>
                            <select id="audio-layout" aria-label="Output layout">
                                <option value="mono" selected>Mono</option>
                                <option value="stereo">Stereo</option>
                            </select>
                        </label>
                        <span class="mixer-rec" id="meter-label" title="Mix level">—</span>
                    </div>
                    <p class="mixer-strip-label">OUT</p>
                    <label class="sr-only" for="audio-output">Monitor output</label>
                    <select id="audio-output" class="mixer-source">
                        <option value="">Select output</option>
                    </select>
                </div>
            </div>

            <div class="mixer-playlist">
                <div class="mixer-playlist-head">
                    <h2>Add sounds to your playlist</h2>
                    <time id="playlist-duration" datetime="PT0S">00:00:00</time>
                </div>
                <div class="mixer-playlist-list" id="audio-channels"></div>
                <div class="mixer-playlist-actions">
                    <button type="button" id="btn-add-file" class="mixer-add-sounds">+ Add sounds</button>
                    <input id="file-input" type="file" accept="audio/*,.mp3,.wav,.m4a,.aac,.ogg,.flac" class="hidden" multiple>
                </div>
                <p class="mixer-hint">
                    Cue (headphones) is off by default — Studio stays silent. Turn cue on only with headphones, or monitor on the listen link.
                </p>
                <p id="studio-status" class="mixer-status" role="status">Allow microphone access when prompted.</p>
                <div class="mixer-listen-row">
                    <code id="listen-url" title="{{ $listenUrl }}">{{ $listenUrl }}</code>
                </div>
            </div>
        </section>

        <footer class="mixer-bar">
            <p class="mixer-air" id="studio-on-air-pill" aria-live="polite">
                <span class="mixer-air-dot"></span>
                <span id="studio-air-label">OFF AIR</span>
            </p>
            <p class="mixer-timer" id="studio-timer">00:00:00</p>
            <div class="mixer-bar-actions">
                <button type="button" id="btn-stop" class="mixer-btn-stop" disabled>Stop</button>
                <button type="button" id="btn-start" class="mixer-btn-start">Start</button>
            </div>
        </footer>

        {{-- Local cue monitor only — never used for publish --}}
        <audio id="cue-audio" playsinline class="hidden"></audio>

        <div
            id="studio-root"
            data-whip-url="{{ $whipUrl }}"
            class="hidden"
        ></div>
    </div>
@endsection
