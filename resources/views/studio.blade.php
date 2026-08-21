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
            @include('partials.brand-logo', ['compact' => true, 'onDark' => true])
            <p class="mixer-topbar-status">
                <span id="studio-mode-mobile">STANDBY</span>
                <span class="mixer-topbar-dot" aria-hidden="true">·</span>
                <span class="mixer-topbar-stream">{{ $stream->title }}</span>
            </p>
            <div class="mixer-topbar-right">
                <span class="mixer-user">{{ $organization->name }}</span>
                <a href="{{ route('dashboard') }}" class="mixer-top-link">Dashboard</a>
                <button type="button" class="mixer-top-link mixer-top-copy" id="btn-share-channel" title="Share your channel page">Share channel</button>
                <button type="button" class="mixer-btn-refresh" id="btn-refresh-studio" title="Reload Studio">Refresh</button>
            </div>
        </header>

        <section class="mixer-hero">
            <div class="mixer-hero-art" style="background-image: url('{{ $artwork ?: asset('images/listen-stage-bg.jpg') }}')"></div>
            <div class="mixer-hero-copy">
                <p class="mixer-hero-kicker" id="studio-mode">Standby</p>
                <h1 class="mixer-hero-title" id="studio-event-title">{{ $openEvent?->title ?: $stream->title }}</h1>
                <p class="mixer-hero-sub" id="studio-hero-hint">Create an event, or go live to start one automatically</p>
                <div class="mixer-event-rename" id="studio-event-rename" @unless($openEvent) hidden @endunless>
                    <label class="mixer-hint" for="event-title-input">Event name</label>
                    <div class="mixer-event-rename-row">
                        <input
                            id="event-title-input"
                            type="text"
                            maxlength="255"
                            value="{{ $openEvent?->title }}"
                            placeholder="Name this event"
                            autocomplete="off"
                        >
                        <button type="button" class="mixer-add-sounds" id="btn-save-event-title">Save</button>
                    </div>
                </div>
            </div>
            <div class="mixer-hero-actions">
                <a class="mixer-icon-btn" href="{{ route('dashboard') }}" title="Back to dashboard" aria-label="Back to dashboard">←</a>
            </div>
        </section>

        <nav class="mixer-mobile-nav" aria-label="Studio sections">
            <button type="button" class="mixer-mobile-tab is-active" data-mobile-pane="mix">Mix</button>
            <button type="button" class="mixer-mobile-tab" data-mobile-pane="sounds">Sounds</button>
            <button type="button" class="mixer-mobile-tab" data-mobile-pane="more">More</button>
        </nav>

        <section class="mixer-board">
            <div class="mixer-deck" data-mobile-pane="mix" aria-label="Mixing console">
                <div id="mic-enable-wrap" class="mixer-mic-enable" hidden>
                    <button type="button" id="btn-enable-mic" class="mixer-add-sounds">Allow microphone</button>
                    <p class="mixer-hint">Needed to list Input 1 / Input 2 devices. On Android, close WhatsApp and any bubbles/overlays first.</p>
                </div>
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
                                <div class="mixer-fader-slot">
                                    <input id="mic-fader" class="mixer-fader" type="range" min="0" max="150" value="100" step="1" aria-label="Input 1 level">
                                </div>
                            </div>
                            <label class="mixer-field mixer-field--source" for="audio-input">
                                <span class="mixer-field-label">Source</span>
                                <select id="audio-input" class="mixer-field-control"></select>
                            </label>
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
                                <div class="mixer-fader-slot">
                                    <input id="aux-fader" class="mixer-fader" type="range" min="0" max="150" value="100" step="1" aria-label="Input 2 level">
                                </div>
                            </div>
                            <label class="mixer-field mixer-field--source" for="aux-input">
                                <span class="mixer-field-label">Source</span>
                                <select id="aux-input" class="mixer-field-control">
                                    <option value="">Select source</option>
                                </select>
                            </label>
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
                                <div class="mixer-fader-slot">
                                    <input id="playlist-fader" class="mixer-fader" type="range" min="0" max="150" value="100" step="1" aria-label="Playlist level">
                                </div>
                            </div>
                            <div class="mixer-field mixer-field--source">
                                <span class="mixer-field-label">Queue</span>
                                <p class="mixer-field-static" id="playlist-count">No sounds</p>
                            </div>
                        </div>

                        {{-- MASTER — mix volume + cue headphone output --}}
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
                                <span class="mixer-rec" id="meter-label" title="Mix level" data-idle>—</span>
                            </div>
                            <div class="mixer-fader-well mixer-fader-well--master">
                                <div class="mixer-fader-scale" aria-hidden="true">
                                    <span>+6</span><span>0</span><span>-10</span><span>-20</span><span>-∞</span>
                                </div>
                                <div class="mixer-fader-slot">
                                    <input id="master-fader" class="mixer-fader" type="range" min="0" max="150" value="100" step="1" aria-label="Master level">
                                </div>
                            </div>
                            <div class="mixer-master-fields">
                                <label class="mixer-field" for="audio-layout">
                                    <span class="mixer-field-label">Layout</span>
                                    <select id="audio-layout" class="mixer-field-control" title="Broadcast layout">
                                        <option value="mono" selected>Mono</option>
                                        <option value="stereo">Stereo</option>
                                    </select>
                                </label>
                                <label class="mixer-field" for="audio-output">
                                    <span class="mixer-field-label">Cue HP</span>
                                    <select id="audio-output" class="mixer-field-control" title="Cue headphones">
                                        <option value="">Default output</option>
                                    </select>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mixer-rail">
            <div class="mixer-playlist" data-mobile-pane="sounds">
                <div class="mixer-playlist-head">
                    <h2>Audio library</h2>
                </div>
                <div class="mixer-library-toolbar">
                    <label class="sr-only" for="library-search">Search library</label>
                    <input id="library-search" class="mixer-library-search" type="search" placeholder="Search songs…" autocomplete="off">
                    <label class="sr-only" for="library-destination">Save to</label>
                    <select id="library-destination" class="mixer-library-search" title="Where to save uploads" style="max-width: 9.5rem">
                        <option value="platform">Platform</option>
                        <option value="drive">Google Drive</option>
                        <option value="local">Server disk</option>
                    </select>
                    <button type="button" id="btn-upload-library" class="mixer-add-sounds">+ Upload</button>
                    <button type="button" id="btn-drive-connect" class="mixer-add-sounds" hidden>Connect Drive</button>
                    <button type="button" id="btn-drive-browse" class="mixer-add-sounds" hidden>Import Drive</button>
                    <input id="file-input" type="file" accept="audio/*,.mp3,.wav,.m4a,.aac,.ogg,.flac" class="hidden" multiple>
                </div>
                <div class="mixer-library-list" id="library-list" role="list"></div>

                <div class="mixer-playlist-head" style="margin-top: 0.75rem">
                    <h2>Session playlist</h2>
                    <time id="playlist-duration" datetime="PT0S">00:00:00</time>
                </div>
                <p class="mixer-hint mixer-hint--desktop">Queued for this Studio session. Queue from the library above, then Play.</p>
                <div class="mixer-playlist-list" id="audio-channels"></div>
                <div class="mixer-playlist-actions">
                    <button type="button" id="btn-add-file" class="mixer-add-sounds">+ Upload &amp; queue</button>
                </div>
            </div>

            <div class="mixer-more" data-mobile-pane="more">
                <div class="mixer-gallery">
                    <p class="mixer-hint" style="margin-bottom: 0.75rem">
                        <a href="{{ route('admin.organizations.customise', $organization) }}" class="mixer-top-link">Customise channel</a>
                    </p>

                    <div class="mixer-playlist-head">
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

                    @if ($scriptureEnabled ?? false)
                        <div class="mixer-playlist-head" style="margin-top: 1rem">
                            <h2>Scripture</h2>
                        </div>
                        <div id="studio-scripture" class="studio-scripture">
                            <p class="mixer-hint">Show KJV verses on the listen page left panel (EasyWorship-style). Type a reference or let Studio listen for spoken scripture.</p>
                            <label class="sr-only" for="scripture-search">Scripture reference</label>
                            <input id="scripture-search" class="mixer-library-search" type="search" placeholder="e.g. John 3:16" autocomplete="off">
                            <div id="scripture-suggestions" class="scripture-suggestions" role="listbox"></div>
                            <div class="mixer-gallery-actions">
                                <button type="button" id="btn-scripture-show" class="mixer-add-sounds">Show on listen</button>
                                <button type="button" id="btn-scripture-clear" class="mixer-add-sounds">Clear</button>
                                <button type="button" id="btn-scripture-listen" class="mixer-add-sounds">Listen for scripture</button>
                            </div>
                            <div id="scripture-live" class="scripture-live" hidden aria-live="polite">
                                <span class="scripture-live-label">Live transcript</span>
                                <p id="scripture-live-text" class="scripture-live-text scripture-live-text--idle">Listening…</p>
                            </div>
                            <div id="scripture-confirm" class="scripture-confirm" hidden>
                                <p>Show <strong id="scripture-confirm-ref"></strong> on listen?</p>
                                <div class="mixer-gallery-actions">
                                    <button type="button" id="btn-scripture-confirm" class="mixer-btn-start">Show</button>
                                    <button type="button" id="btn-scripture-dismiss" class="mixer-btn-secondary">Dismiss</button>
                                </div>
                            </div>
                            <p id="scripture-status" class="mixer-hint" role="status">No scripture on listen</p>
                        </div>
                    @endif

                    <div class="mixer-local-recording" id="studio-local-recording" hidden style="margin-top: 1rem">
                        <p class="mixer-recording-title" id="studio-local-recording-title">Local session ready</p>
                        <p class="mixer-recording-meta" id="studio-local-recording-meta"></p>
                        <label class="mixer-hint" for="local-recording-title">Recording title (optional)</label>
                        <input id="local-recording-title" type="text" maxlength="255" placeholder="e.g. Sunday Morning Service" autocomplete="off">
                        <div class="mixer-recording-actions">
                            <button type="button" class="mixer-recording-upload" id="btn-upload-recording">Upload</button>
                            <button type="button" class="mixer-recording-link" id="btn-download-recording">Save locally</button>
                            <button type="button" class="mixer-recording-delete" id="btn-discard-recording">Discard</button>
                        </div>
                    </div>
                    <p class="mixer-hint" id="studio-local-recording-live" hidden>Recording locally… network drops won’t cut this file.</p>
                </div>
                <p id="studio-status" class="mixer-status" role="status">Allow microphone access when prompted.</p>
                <div class="mixer-listen-row">
                    <p class="mixer-hint">Event / channel link</p>
                    <code id="event-url" title="{{ $openEvent ? route('events.show', $openEvent) : $channelUrl }}">{{ $openEvent ? route('events.show', $openEvent) : $channelUrl }}</code>
                    <code id="channel-url" title="{{ $channelUrl }}">{{ $channelUrl }}</code>
                    <div class="mixer-share-actions">
                        <button type="button" class="mixer-add-sounds" id="btn-share-channel-main">Share</button>
                        <button type="button" class="mixer-add-sounds" id="btn-copy-channel">Copy</button>
                    </div>
                </div>
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
                <button type="button" id="btn-create-event" class="mixer-btn-secondary" @disabled(! ($broadcastAllowed ?? true))>Create event</button>
                <button type="button" id="btn-pause" class="mixer-btn-stop" hidden disabled>Pause</button>
                <button type="button" id="btn-end" class="mixer-btn-stop" hidden disabled>End live</button>
                <button type="button" id="btn-start" class="mixer-btn-start" @disabled(! ($broadcastAllowed ?? true))>Go live</button>
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
            data-gallery-list-url="{{ $galleryListUrl }}"
            data-library-list-url="{{ $libraryListUrl }}"
            data-library-upload-url="{{ $libraryUploadUrl }}"
            data-library-import-drive-url="{{ $libraryImportDriveUrl }}"
            data-recording-upload-url="{{ $recordingUploadUrl }}"
            data-session-show-url="{{ $sessionShowUrl }}"
            data-session-create-event-url="{{ $sessionCreateEventUrl }}"
            data-session-go-live-url="{{ $sessionGoLiveUrl }}"
            data-session-pause-url="{{ $sessionPauseUrl }}"
            data-session-resume-url="{{ $sessionResumeUrl }}"
            data-session-end-url="{{ $sessionEndUrl }}"
            data-session-rename-event-url="{{ $sessionRenameEventUrl }}"
            data-scripture-enabled="{{ ($scriptureEnabled ?? false) ? '1' : '0' }}"
            @if ($scriptureEnabled ?? false)
                data-scripture-show-url="{{ $scriptureShowUrl }}"
                data-scripture-store-url="{{ $scriptureStoreUrl }}"
                data-scripture-destroy-url="{{ $scriptureDestroyUrl }}"
                data-scripture-suggest-url="{{ $scriptureSuggestUrl }}"
            @endif
            data-open-event-id="{{ $openEvent?->id }}"
            data-open-event-title="{{ $openEvent?->title }}"
            data-open-event-status="{{ $openEvent?->status?->value }}"
            data-open-event-url="{{ $openEvent ? route('events.show', $openEvent) : '' }}"
            data-channel-url="{{ $channelUrl }}"
            data-channel-name="{{ $organization->name }}"
            data-stream-title="{{ $stream->title }}"
            data-csrf="{{ csrf_token() }}"
            class="hidden"
        ></div>
    </div>
@endsection
