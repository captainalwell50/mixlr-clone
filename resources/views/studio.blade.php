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
            <a href="{{ url('/') }}" class="mixer-brand">{{ config('app.name', 'Live Mix Audio') }}</a>
            <div class="mixer-topbar-right">
                <span class="mixer-user">{{ $organization->name }}</span>
                <a href="{{ route('dashboard') }}" class="mixer-top-link">Dashboard</a>
            </div>
        </header>

        <section class="mixer-hero">
            <div class="mixer-hero-art" style="background-image: url('{{ $artwork ?: asset('images/listen-stage-bg.jpg') }}')"></div>
            <div class="mixer-hero-copy">
                <p class="mixer-hero-kicker" id="studio-mode">Standby</p>
                <h1 class="mixer-hero-title">{{ $stream->title }}</h1>
                <p class="mixer-hero-sub" id="studio-hero-hint">Hit Go on air when you’re ready</p>
            </div>
            <div class="mixer-hero-actions">
                <a class="mixer-icon-btn" href="{{ route('dashboard') }}" title="Back to dashboard" aria-label="Back to dashboard">←</a>
                <button type="button" class="mixer-icon-btn" id="btn-copy-listen" title="Copy listen link" aria-label="Copy listen link">↗</button>
            </div>
        </section>

        <section class="mixer-board">
            <div class="mixer-deck" aria-label="Mixing console">
                <div class="mixer-chassis">
                    <div class="mixer-chassis-rail">
                        <span class="mixer-chassis-title">Console</span>
                        <span class="mixer-chassis-meta">Input 1 · Input 2 · Playlist · Master</span>
                    </div>

                    <div class="mixer-strips" id="mixer-strips">
                        {{-- INPUT 1 --}}
                        <div class="mixer-strip" data-strip="mic" id="mic-channel">
                            <div class="mixer-scribble">
                                <span class="mixer-ch-num">1</span>
                                <span class="mixer-strip-label">INPUT 1</span>
                            </div>
                            <div class="mixer-meterbridge" aria-hidden="true">
                                <div class="mixer-strip-meter">
                                    <div class="mixer-strip-meter-fill" id="mic-meter"></div>
                                </div>
                            </div>
                            <div class="mixer-strip-toggles">
                                <button type="button" class="mixer-cue" id="mic-cue" aria-pressed="false" title="Cue in headphones">CUE</button>
                                <button type="button" class="mixer-mute" id="mic-mute" aria-pressed="false" title="Mute input 1">M</button>
                            </div>
                            <div class="mixer-fader-well">
                                <div class="mixer-fader-scale" aria-hidden="true">
                                    <span>+6</span><span>0</span><span>-10</span><span>-20</span><span>-∞</span>
                                </div>
                                <input id="mic-fader" class="mixer-fader" type="range" min="0" max="150" value="100" step="1" orient="vertical" aria-label="Input 1 level">
                            </div>
                            <label class="sr-only" for="audio-input">Input 1 source</label>
                            <select id="audio-input" class="mixer-source"></select>
                        </div>

                        {{-- INPUT 2 --}}
                        <div class="mixer-strip" data-strip="aux" id="aux-channel">
                            <div class="mixer-scribble">
                                <span class="mixer-ch-num">2</span>
                                <span class="mixer-strip-label">INPUT 2</span>
                            </div>
                            <div class="mixer-meterbridge" aria-hidden="true">
                                <div class="mixer-strip-meter">
                                    <div class="mixer-strip-meter-fill" id="aux-meter"></div>
                                </div>
                            </div>
                            <div class="mixer-strip-toggles">
                                <button type="button" class="mixer-cue" id="aux-cue" aria-pressed="false" title="Cue in headphones">CUE</button>
                                <button type="button" class="mixer-mute is-active" id="aux-mute" aria-pressed="true" title="Mute input 2">M</button>
                            </div>
                            <div class="mixer-fader-well">
                                <div class="mixer-fader-scale" aria-hidden="true">
                                    <span>+6</span><span>0</span><span>-10</span><span>-20</span><span>-∞</span>
                                </div>
                                <input id="aux-fader" class="mixer-fader" type="range" min="0" max="150" value="100" step="1" orient="vertical" aria-label="Input 2 level">
                            </div>
                            <label class="sr-only" for="aux-input">Input 2 source</label>
                            <select id="aux-input" class="mixer-source">
                                <option value="">Select source</option>
                            </select>
                        </div>

                        {{-- PLAYLIST --}}
                        <div class="mixer-strip" data-strip="playlist" id="playlist-channel">
                            <div class="mixer-scribble">
                                <span class="mixer-ch-num">3</span>
                                <span class="mixer-strip-label">PLAYLIST</span>
                            </div>
                            <div class="mixer-meterbridge" aria-hidden="true">
                                <div class="mixer-strip-meter">
                                    <div class="mixer-strip-meter-fill" id="playlist-meter"></div>
                                </div>
                            </div>
                            <div class="mixer-strip-toggles">
                                <button type="button" class="mixer-cue" id="playlist-cue" aria-pressed="false" title="Cue playlist">CUE</button>
                                <button type="button" class="mixer-mute" id="playlist-mute" aria-pressed="false" title="Mute playlist">M</button>
                            </div>
                            <div class="mixer-fader-well">
                                <div class="mixer-fader-scale" aria-hidden="true">
                                    <span>+6</span><span>0</span><span>-10</span><span>-20</span><span>-∞</span>
                                </div>
                                <input id="playlist-fader" class="mixer-fader" type="range" min="0" max="150" value="100" step="1" orient="vertical" aria-label="Playlist level">
                            </div>
                            <p class="mixer-source mixer-source--static" id="playlist-count">No sounds</p>
                        </div>

                        {{-- MASTER — mix bus volume only (Input 1 + 2 + Playlist) --}}
                        <div class="mixer-strip mixer-strip--out" data-strip="out">
                            <div class="mixer-scribble">
                                <span class="mixer-ch-num">M</span>
                                <span class="mixer-strip-label">MASTER</span>
                            </div>
                            <div class="mixer-meterbridge" aria-hidden="true">
                                <div class="mixer-strip-meter mixer-strip-meter--out">
                                    <div class="mixer-strip-meter-fill" id="level-meter"></div>
                                </div>
                            </div>
                            <div class="mixer-strip-toggles mixer-strip-toggles--master">
                                <span class="mixer-rec" id="meter-label" title="Mix level">—</span>
                            </div>
                            <div class="mixer-fader-well">
                                <div class="mixer-fader-scale" aria-hidden="true">
                                    <span>+6</span><span>0</span><span>-10</span><span>-20</span><span>-∞</span>
                                </div>
                                <input id="master-fader" class="mixer-fader" type="range" min="0" max="150" value="100" step="1" orient="vertical" aria-label="Master level">
                            </div>
                            <p class="mixer-source mixer-source--static">Mix bus</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mixer-playlist">
                <div class="mixer-playlist-head">
                    <h2>Audio library</h2>
                </div>
                <p class="mixer-hint">Saved on this stream — search and queue into the session playlist. Uploads survive refresh.</p>
                <div class="mixer-library-toolbar">
                    <label class="sr-only" for="library-search">Search library</label>
                    <input id="library-search" class="mixer-library-search" type="search" placeholder="Search songs…" autocomplete="off">
                    <button type="button" id="btn-upload-library" class="mixer-add-sounds">+ Upload</button>
                    <input id="file-input" type="file" accept="audio/*,.mp3,.wav,.m4a,.aac,.ogg,.flac" class="hidden" multiple>
                </div>
                <div class="mixer-library-list" id="library-list" role="list"></div>

                <div class="mixer-playlist-head" style="margin-top: 0.75rem">
                    <h2>Session playlist</h2>
                    <time id="playlist-duration" datetime="PT0S">00:00:00</time>
                </div>
                <p class="mixer-hint">Queued for this Studio session. Queue from the library above, then Play.</p>
                <div class="mixer-playlist-list" id="audio-channels"></div>
                <div class="mixer-playlist-actions">
                    <button type="button" id="btn-add-file" class="mixer-add-sounds">+ Upload &amp; queue</button>
                </div>
                <div class="mixer-gallery">
                    <div class="mixer-playlist-head">
                        <h2>Listen background</h2>
                    </div>
                    <p class="mixer-hint">Full-screen image behind the listener page (replaces the default).</p>
                    <div class="mixer-gallery-actions">
                        <button type="button" id="btn-add-background" class="mixer-add-sounds">Set background</button>
                        <input id="background-input" type="file" accept="image/*" class="hidden">
                    </div>
                    @if (! empty($listenBackgroundUrl))
                        <div class="mixer-bg-preview" id="studio-bg-preview" style="background-image: url('{{ $listenBackgroundUrl }}')"></div>
                    @else
                        <div class="mixer-bg-preview is-empty" id="studio-bg-preview">Default background</div>
                    @endif

                    <div class="mixer-playlist-head" style="margin-top: 1rem">
                        <h2>Service gallery</h2>
                    </div>
                    <p class="mixer-hint">Post photos or short video reels (30s–60s) for listeners.</p>
                    <div class="mixer-gallery-actions">
                        <button type="button" id="btn-add-gallery" class="mixer-add-sounds">+ Add photo</button>
                        <button type="button" id="btn-add-reel" class="mixer-add-sounds">+ Video reel</button>
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

                    <div class="mixer-playlist-head" style="margin-top: 1rem">
                        <h2>Recorded audio</h2>
                    </div>
                    <p class="mixer-hint">Delete past recordings from this stream when you no longer need them.</p>
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
                            <p class="mixer-hint" id="studio-recordings-empty">No recordings yet for this stream.</p>
                        @endforelse
                    </div>
                </div>
                <div class="mixer-monitor-prefs">
                    <label class="mixer-sq">
                        <span>Broadcast layout</span>
                        <select id="audio-layout" aria-label="Broadcast layout">
                            <option value="mono" selected>Mono</option>
                            <option value="stereo">Stereo</option>
                        </select>
                    </label>
                    <label class="mixer-sq">
                        <span>Cue headphones</span>
                        <select id="audio-output" aria-label="Cue headphones output">
                            <option value="">Default output</option>
                        </select>
                    </label>
                </div>
                <p class="mixer-hint">
                    Cue (headphones) is off by default — Studio stays silent. Turn cue on only with headphones, or monitor on the listen link. Master only sets overall mix volume for Input 1, Input 2, and Playlist.
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
                <span id="studio-air-label">STANDBY</span>
            </p>
            <p class="mixer-timer" id="studio-timer">00:00:00</p>
            <div class="mixer-bar-actions">
                <button type="button" id="btn-stop" class="mixer-btn-stop" disabled>End broadcast</button>
                <button type="button" id="btn-start" class="mixer-btn-start" @disabled(! ($broadcastAllowed ?? true))>Go on air</button>
            </div>
        </footer>

        {{-- Local cue monitor only — never used for publish --}}
        <audio id="cue-audio" playsinline class="hidden"></audio>

        <div
            id="studio-root"
            data-whip-url="{{ $whipUrl }}"
            data-broadcast-allowed="{{ ($broadcastAllowed ?? true) ? '1' : '0' }}"
            data-billing-url="{{ $billingUrl ?? route('billing.plans') }}"
            data-gallery-upload-url="{{ $galleryUploadUrl }}"
            data-background-upload-url="{{ $backgroundUploadUrl }}"
            data-gallery-list-url="{{ $galleryListUrl }}"
            data-library-list-url="{{ $libraryListUrl }}"
            data-library-upload-url="{{ $libraryUploadUrl }}"
            data-csrf="{{ csrf_token() }}"
            class="hidden"
        ></div>
    </div>
@endsection
