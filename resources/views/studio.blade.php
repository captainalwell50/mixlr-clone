@extends('layouts.stream')

@section('title', 'Studio · '.$stream->title)

@section('vite')
    @vite(['resources/js/studio.js'])
@endsection

@php
    use Illuminate\Support\Facades\URL;
    $theme = $organization->themeColor();
    $artwork = $organization->artworkUrl();
@endphp

@section('content')
    <div id="studio-stage" class="mixer" style="--mixer-accent: {{ $theme }};">
        <header class="mixer-topbar">
            <div class="mixer-topbar-left">
                <a href="{{ url('/') }}" class="mixer-brand">{{ config('app.name', 'Live Mix Audio') }}</a>
                <span class="mixer-top-sep" aria-hidden="true"></span>
                <span class="mixer-user">{{ $organization->name }}</span>
            </div>
            <div class="mixer-topbar-right">
                <button type="button" class="mixer-chip" id="btn-copy-listen" title="Copy listen link">
                    <span>Copy listen link</span>
                </button>
                <a href="{{ route('dashboard') }}" class="mixer-top-link">Dashboard</a>
            </div>
        </header>

        <section class="mixer-hero">
            <div class="mixer-hero-art" style="background-image: url('{{ $artwork ?: asset('images/listen-stage-bg.jpg') }}')"></div>
            <div class="mixer-hero-copy">
                <p class="mixer-hero-kicker" id="studio-mode">Standby</p>
                <h1 class="mixer-hero-title">{{ $stream->title }}</h1>
                <p class="mixer-hero-sub" id="studio-hero-hint">Cue off · Studio silent until you go on air</p>
            </div>
            <div class="mixer-hero-status">
                <p class="mixer-air mixer-air--hero" id="studio-on-air-pill" aria-live="polite">
                    <span class="mixer-air-dot"></span>
                    <span id="studio-air-label">STANDBY</span>
                </p>
            </div>
        </section>

        <section class="mixer-board">
            <div class="mixer-console">
                <div class="mixer-console-label">
                    <span>Mixer</span>
                    <span class="mixer-console-hint">Levels · cue · mute</span>
                </div>
                <div class="mixer-strips" id="mixer-strips">
                    {{-- MIC --}}
                    <div class="mixer-strip" data-strip="mic" id="mic-channel">
                        <p class="mixer-strip-label">MIC</p>
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
                        <label class="sr-only" for="audio-input">Microphone</label>
                        <select id="audio-input" class="mixer-source"></select>
                    </div>

                    {{-- ANY INPUT --}}
                    <div class="mixer-strip" data-strip="aux" id="aux-channel">
                        <p class="mixer-strip-label">INPUT</p>
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
                        <label class="sr-only" for="aux-input">Any input</label>
                        <select id="aux-input" class="mixer-source">
                            <option value="">Select source</option>
                        </select>
                    </div>

                    {{-- PLAYLIST --}}
                    <div class="mixer-strip" data-strip="playlist" id="playlist-channel">
                        <p class="mixer-strip-label">PLAYLIST</p>
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
                        <p class="mixer-source mixer-source--static" id="playlist-count">No sounds</p>
                    </div>

                    {{-- OUT --}}
                    <div class="mixer-strip mixer-strip--out" data-strip="out">
                        <p class="mixer-strip-label">OUT</p>
                        <div class="mixer-strip-meter mixer-strip-meter--out" aria-hidden="true">
                            <div class="mixer-strip-meter-fill" id="level-meter"></div>
                        </div>
                        <div class="mixer-out-tools">
                            <label class="mixer-sq" title="Output layout">
                                <span>Layout</span>
                                <select id="audio-layout" aria-label="Output layout">
                                    <option value="mono" selected>Mono</option>
                                    <option value="stereo">Stereo</option>
                                </select>
                            </label>
                            <span class="mixer-rec" id="meter-label" title="Mix level">—</span>
                        </div>
                        <label class="sr-only" for="audio-output">Monitor output</label>
                        <select id="audio-output" class="mixer-source">
                            <option value="">Default output</option>
                        </select>
                    </div>
                </div>
            </div>

            <aside class="mixer-side">
                <section class="mixer-panel">
                    <div class="mixer-panel-head">
                        <h2>Playlist</h2>
                        <time id="playlist-duration" datetime="PT0S">00:00:00</time>
                    </div>
                    <div class="mixer-playlist-list" id="audio-channels"></div>
                    <button type="button" id="btn-add-file" class="mixer-btn mixer-btn-secondary">+ Add sounds</button>
                    <input id="file-input" type="file" accept="audio/*,.mp3,.wav,.m4a,.aac,.ogg,.flac" class="hidden" multiple>
                </section>

                <section class="mixer-panel">
                    <div class="mixer-panel-head">
                        <h2>Listen background</h2>
                    </div>
                    <p class="mixer-hint">Image behind the listener page.</p>
                    @if (! empty($listenBackgroundUrl))
                        <div class="mixer-bg-preview" id="studio-bg-preview" style="background-image: url('{{ $listenBackgroundUrl }}')"></div>
                    @else
                        <div class="mixer-bg-preview is-empty" id="studio-bg-preview">Default background</div>
                    @endif
                    <button type="button" id="btn-add-background" class="mixer-btn mixer-btn-secondary">Set background</button>
                    <input id="background-input" type="file" accept="image/*" class="hidden">
                </section>

                <section class="mixer-panel">
                    <div class="mixer-panel-head">
                        <h2>Gallery</h2>
                    </div>
                    <p class="mixer-hint">Photos and short reels for listeners.</p>
                    <div class="mixer-gallery-actions">
                        <button type="button" id="btn-add-gallery" class="mixer-btn mixer-btn-secondary">+ Photo</button>
                        <button type="button" id="btn-add-reel" class="mixer-btn mixer-btn-ghost">+ Reel</button>
                        <input id="gallery-input" type="file" accept="image/*" class="hidden" multiple>
                        <input id="reel-input" type="file" accept="video/mp4,video/webm,video/quicktime,.mp4,.webm,.mov" class="hidden">
                    </div>
                    <div class="mixer-gallery-list" id="studio-gallery-list">
                        @foreach ($galleryImages as $image)
                            <figure class="mixer-gallery-thumb {{ $image->isVideo() ? 'is-video' : '' }}" data-id="{{ $image->id }}">
                                @if ($image->isVideo())
                                    <video src="{{ $image->url() }}" muted playsinline preload="metadata"></video>
                                    <span class="mixer-reel-badge">Reel</span>
                                @else
                                    <img src="{{ $image->url() }}" alt="{{ $image->caption ?: 'Gallery photo' }}">
                                @endif
                            </figure>
                        @endforeach
                    </div>
                </section>

                <section class="mixer-panel">
                    <div class="mixer-panel-head">
                        <h2>Recordings</h2>
                    </div>
                    <div class="mixer-recordings" id="studio-recordings">
                        @forelse ($recordings as $recording)
                            <div class="mixer-recording-row" data-recording-id="{{ $recording->id }}">
                                <div class="mixer-recording-copy">
                                    <p class="mixer-recording-title">{{ $recording->completed_at->timezone(config('app.timezone'))->format('M j, Y · g:i A') }}</p>
                                    <p class="mixer-recording-meta">{{ $recording->durationLabel() }} · {{ $recording->sizeLabel() }}</p>
                                </div>
                                <div class="mixer-recording-actions">
                                    <a href="{{ route('archive.play', $recording) }}" target="_blank" rel="noopener" class="mixer-recording-link">Play</a>
                                    <button
                                        type="button"
                                        class="mixer-recording-delete"
                                        data-delete-url="{{ URL::temporarySignedRoute('recordings.destroy', now()->addHours(12), ['stream' => $stream, 'recording' => $recording]) }}"
                                    >Delete</button>
                                </div>
                            </div>
                        @empty
                            <p class="mixer-hint" id="studio-recordings-empty">No recordings yet.</p>
                        @endforelse
                    </div>
                </section>

                <p id="studio-status" class="mixer-status" role="status">Allow microphone access when prompted.</p>
                <div class="mixer-listen-row">
                    <code id="listen-url" title="{{ $listenUrl }}">{{ $listenUrl }}</code>
                </div>
            </aside>
        </section>

        <footer class="mixer-bar">
            <p class="mixer-timer" id="studio-timer">00:00:00</p>
            <div class="mixer-bar-actions">
                <button type="button" id="btn-stop" class="mixer-btn-stop" disabled>End broadcast</button>
                <button type="button" id="btn-start" class="mixer-btn-start" @disabled(! ($broadcastAllowed ?? true))>Go on air</button>
            </div>
        </footer>

        <audio id="cue-audio" playsinline class="hidden"></audio>

        <div
            id="studio-root"
            data-whip-url="{{ $whipUrl }}"
            data-broadcast-allowed="{{ ($broadcastAllowed ?? true) ? '1' : '0' }}"
            data-billing-url="{{ $billingUrl ?? route('billing.plans') }}"
            data-gallery-upload-url="{{ $galleryUploadUrl }}"
            data-background-upload-url="{{ $backgroundUploadUrl }}"
            data-gallery-list-url="{{ $galleryListUrl }}"
            data-csrf="{{ csrf_token() }}"
            class="hidden"
        ></div>
    </div>
@endsection
