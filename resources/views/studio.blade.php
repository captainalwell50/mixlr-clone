@extends('layouts.stream')

@php
    $org = $organization ?? $stream->organization;
    $channelTitle = $org?->name ?? $stream->title;
    $artUrl = $org?->artworkUrl() ?: $stream->listenBackgroundUrl();
    $subscription = $org?->subscription;
    $planName = $activePlanName ?? $org?->plan()?->name ?? 'Free';
    $freeDaysLeft = $freeDaysRemaining ?? $subscription?->freeDaysRemaining();
    $broadcastAllowed = $broadcastAllowed ?? ($org ? $org->allowsBroadcast() : true);
    $needsUpgrade = $needsUpgrade ?? ($org ? $org->needsUpgradeCta() : false);
    $billingUrl = $billingUrl ?? ($org ? route('billing.plans') : null);
    $channelUrl = $channelUrl ?? ($org ? $org->channelUrl() : route('listen.stream', $stream));
    $studioReloadAt = $studioReloadAt ?? \App\Models\SiteSetting::studioReloadAt($stream->uuid);
    $channelInitial = mb_strtoupper(mb_substr(trim((string) $channelTitle), 0, 1)) ?: 'S';
    $activeEventUrl = $openEvent ? route('events.show', $openEvent) : '';
@endphp

@section('title', 'Studio · '.$channelTitle)

@section('vite')
    <script>
        (function () {
            var key = 'soundmix-studio-theme';
            var stored = null;
            try {
                stored = localStorage.getItem(key);
            } catch (e) {}
            var theme = stored === 'dark' ? 'dark' : 'light';
            document.documentElement.setAttribute('data-studio-theme', theme);
            document.documentElement.setAttribute('data-theme', theme);
            document.documentElement.style.colorScheme = theme;
        })();
    </script>
    @vite(['resources/js/studio.js'])
@endsection

@section('content')
    <div id="studio-stage" class="mixer {{ $stream->status === \App\Enums\StreamStatus::Live ? 'is-on-air' : ($openEvent?->status === \App\Enums\EventStatus::Paused ? 'is-paused' : '') }}" data-mobile-pane="mix" data-studio-theme="light">
        <div
            id="studio-root"
            class="sr-only"
            data-stream-uuid="{{ $stream->uuid }}"
            data-stream-title="{{ $channelTitle }}"
            data-whip-url="{{ $whipUrl }}"
            data-csrf="{{ csrf_token() }}"
            data-channel-url="{{ $channelUrl }}"
            data-channel-name="{{ $channelTitle }}"
            data-session-show-url="{{ $sessionShowUrl ?? '' }}"
            data-studio-reload-at="{{ $studioReloadAt }}"
            data-session-go-live-url="{{ $sessionGoLiveUrl ?? '' }}"
            data-session-pause-url="{{ $sessionPauseUrl ?? '' }}"
            data-session-resume-url="{{ $sessionResumeUrl ?? '' }}"
            data-session-end-url="{{ $sessionEndUrl ?? '' }}"
            data-session-recording-url="{{ $sessionRecordingUrl ?? '' }}"
            data-session-publisher-claim-url="{{ $sessionPublisherClaimUrl ?? '' }}"
            data-session-create-event-url="{{ $sessionCreateEventUrl ?? '' }}"
            data-session-rename-event-url="{{ $sessionRenameEventUrl ?? '' }}"
            data-open-event-id="{{ $openEvent?->id ?? '' }}"
            data-open-event-title="{{ $openEvent?->title ?? '' }}"
            data-open-event-status="{{ $openEvent?->status?->value ?? '' }}"
            data-open-event-url="{{ $activeEventUrl }}"
            data-gallery-list-url="{{ $galleryListUrl ?? route('gallery.index', $stream) }}"
            data-gallery-upload-url="{{ $galleryUploadUrl ?? '' }}"
            data-gallery-destroy-url="{{ $galleryDestroyUrl ?? '' }}"
            data-library-list-url="{{ $libraryListUrl ?? '' }}"
            data-library-upload-url="{{ $libraryUploadUrl ?? '' }}"
            data-library-import-drive-url="{{ $libraryImportDriveUrl ?? '' }}"
            data-object-storage-enabled="{{ ! empty($objectStorageEnabled) ? '1' : '0' }}"
            data-recording-upload-url="{{ $recordingUploadUrl ?? '' }}"
            data-scripture-enabled="{{ ! empty($scriptureEnabled) ? '1' : '0' }}"
            data-scripture-store-url="{{ $scriptureStoreUrl ?? '' }}"
            data-scripture-destroy-url="{{ $scriptureDestroyUrl ?? '' }}"
            data-scripture-suggest-url="{{ $scriptureSuggestUrl ?? '' }}"
            data-scripture-show-url="{{ $scriptureShowUrl ?? '' }}"
            data-ndi-store-url="{{ $ndiStoreUrl ?? '' }}"
            data-ndi-destroy-url="{{ $ndiDestroyUrl ?? '' }}"
            data-songs-index-url="{{ $songsIndexUrl ?? '' }}"
            data-songs-store-url="{{ $songsStoreUrl ?? '' }}"
            data-songs-cue-clear-url="{{ $songsCueClearUrl ?? '' }}"
            data-songs-cue-next-url="{{ $songsCueNextUrl ?? '' }}"
            data-songs-cue-previous-url="{{ $songsCuePreviousUrl ?? '' }}"
            data-songs-show-url="{{ $songsShowUrl ?? '' }}"
            data-broadcast-allowed="{{ $broadcastAllowed ? '1' : '0' }}"
            data-opus-bitrate="{{ (int) ($opusSendBitrate ?? 320000) }}"
            data-billing-url="{{ $billingUrl ?? '' }}"
        ></div>
        <audio id="cue-audio" class="sr-only" playsinline></audio>

        {{-- 1. Compact Studio Top Bar & Context Header --}}
        <header class="mixer-topbar">
            <div class="mixer-topbar-left">
                <a href="{{ route('discover') }}" class="mixer-brand" aria-label="Sound Mix Live">
                    <img src="{{ asset('brand/soundmix-mark.png') }}" alt="" class="mixer-brand-mark" width="28" height="28" decoding="async" aria-hidden="true">
                    <span class="mixer-brand-word">SoundMix</span>
                    <span class="mixer-brand-badge">STUDIO</span>
                </a>
                <span class="mixer-topbar-sep" aria-hidden="true"></span>

                <div class="mixer-topbar-identity">
                    <div class="mixer-topbar-channel">
                        <div
                            class="mixer-topbar-art {{ ! $artUrl ? 'is-fallback' : '' }}"
                            @if ($artUrl)
                                style="background-image: url('{{ $artUrl }}')"
                            @endif
                            aria-hidden="true"
                        >
                            @if (! $artUrl)
                                <span class="mixer-topbar-art-fallback">{{ $channelInitial }}</span>
                            @endif
                        </div>
                        <div class="mixer-topbar-copy">
                            <div class="mixer-topbar-title-row">
                                <h1 class="mixer-topbar-title" title="{{ $channelTitle }}">{{ $channelTitle }}</h1>
                                <span class="mixer-topbar-state-dot" aria-hidden="true"></span>
                                <span class="mixer-topbar-mode" id="studio-mode">{{ $openEvent?->status?->value === 'paused' ? 'Paused' : ($openEvent?->status?->value === 'live' ? 'On the air' : 'Standby') }}</span>
                            </div>
                            <p class="mixer-topbar-event-line">
                                <span class="mixer-topbar-event" id="studio-event-title" title="{{ $openEvent?->title ?? 'No event selected' }}">{{ $openEvent?->title ?? 'No event selected' }}</span>
                            </p>
                            <p class="sr-only" id="studio-hero-hint"></p>
                        </div>
                    </div>

                    <div class="mixer-topbar-rename" id="studio-event-rename" @if (! $openEvent) hidden @endif>
                        <label for="event-title-input" class="sr-only">Event name</label>
                        <input
                            type="text"
                            id="event-title-input"
                            class="mixer-library-search mixer-topbar-rename-input"
                            maxlength="255"
                            placeholder="Rename event…"
                            value="{{ $openEvent?->title ?? '' }}"
                        >
                        <button type="button" class="studio-btn studio-btn--sm studio-btn--secondary" id="btn-save-event-title">Save</button>
                    </div>
                </div>
            </div>

            <p class="mixer-topbar-status" aria-live="polite">
                <span id="studio-mode-mobile">{{ $openEvent?->status?->value === 'paused' ? 'PAUSED' : ($openEvent?->status?->value === 'live' ? 'ON AIR' : 'STANDBY') }}</span>
                · <span class="mixer-topbar-stream">{{ $channelTitle }}</span>
            </p>

            <div class="mixer-topbar-right">
                @if ($needsUpgrade)
                    <div class="mixer-hero-plan" role="status">
                        <p class="mixer-hero-days">
                            <span class="mixer-hero-plan-name">{{ $planName }}</span>
                            <span class="mixer-hero-plan-dash" aria-hidden="true">—</span>
                            @if (! $broadcastAllowed)
                                <span class="mixer-hero-days-ended">ENDED</span>
                            @elseif ($freeDaysLeft !== null)
                                <span class="mixer-hero-days-num">{{ $freeDaysLeft }}</span>
                                <span class="mixer-hero-days-unit">{{ $freeDaysLeft === 1 ? 'DAY LEFT' : 'DAYS LEFT' }}</span>
                            @endif
                        </p>
                        @if ($billingUrl)
                            <a href="{{ $billingUrl }}" class="mixer-hero-upgrade">Upgrade</a>
                        @endif
                    </div>
                @endif

                <div class="studio-theme-switch" id="studio-theme-toggle" role="group" aria-label="Studio appearance">
                    <button
                        type="button"
                        class="studio-theme-btn is-active"
                        data-studio-theme-btn="light"
                        aria-pressed="true"
                        title="Light mode"
                    >
                        <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="12" cy="12" r="4"></circle>
                            <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"></path>
                        </svg>
                        <span>Light</span>
                    </button>
                    <button
                        type="button"
                        class="studio-theme-btn"
                        data-studio-theme-btn="dark"
                        aria-pressed="false"
                        title="Dark mode"
                    >
                        <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                        </svg>
                        <span>Dark</span>
                    </button>
                </div>

                <button type="button" class="studio-top-btn mixer-top-copy" id="btn-share-channel" title="Share listener link">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="18" cy="5" r="3"></circle>
                        <circle cx="6" cy="12" r="3"></circle>
                        <circle cx="18" cy="19" r="3"></circle>
                        <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
                        <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
                    </svg>
                    <span>Share</span>
                </button>
                @auth
                    <a href="{{ route('creator.home') }}" class="studio-top-btn" title="Exit Studio to Dashboard">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="3" y="3" width="7" height="7" rx="1"></rect>
                            <rect x="14" y="3" width="7" height="7" rx="1"></rect>
                            <rect x="14" y="14" width="7" height="7" rx="1"></rect>
                            <rect x="3" y="14" width="7" height="7" rx="1"></rect>
                        </svg>
                        <span>Dashboard</span>
                    </a>
                @endauth
                <button type="button" class="studio-top-btn mixer-btn-refresh" id="btn-refresh-studio" title="Reload Studio to pick up updates">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M21 2v6h-6"></path>
                        <path d="M3 12a9 9 0 0 1 15-6.7L21 8"></path>
                        <path d="M3 22v-6h6"></path>
                        <path d="M21 12a9 9 0 0 1-15 6.7L3 16"></path>
                    </svg>
                    <span>Refresh</span>
                </button>
            </div>
        </header>

        {{-- Mobile Workspace Switcher (< 768px) --}}
        <nav class="mixer-mobile-nav" aria-label="Studio workspace view">
            <button type="button" class="mixer-mobile-tab is-active" data-mobile-pane="mix">Mix</button>
            <button type="button" class="mixer-mobile-tab" data-mobile-pane="library">Library</button>
            <button type="button" class="mixer-mobile-tab" data-mobile-pane="tools">Tools</button>
        </nav>

        {{-- 2. Main Two-Column Broadcast Workspace --}}
        <main class="mixer-board">
            {{-- Left Column: 4-Channel Mixing Console --}}
            <section class="mixer-deck is-mobile-active" data-mobile-pane="mix" aria-label="Audio mixing console">
                <div class="mixer-chassis">
                    <div class="mixer-chassis-rail">
                        <div class="mixer-chassis-rail-left">
                            <span class="mixer-chassis-icon" aria-hidden="true">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="4" y1="21" x2="4" y2="14"></line>
                                    <line x1="4" y1="10" x2="4" y2="3"></line>
                                    <line x1="12" y1="21" x2="12" y2="12"></line>
                                    <line x1="12" y1="8" x2="12" y2="3"></line>
                                    <line x1="20" y1="21" x2="20" y2="16"></line>
                                    <line x1="20" y1="12" x2="20" y2="3"></line>
                                    <line x1="1" y1="14" x2="7" y2="14"></line>
                                    <line x1="9" y1="8" x2="15" y2="8"></line>
                                    <line x1="17" y1="16" x2="23" y2="16"></line>
                                </svg>
                            </span>
                            <span class="mixer-chassis-title">Mixer Console</span>
                        </div>
                        <div class="mixer-chassis-rail-right">
                            <span class="mixer-chassis-meta">48 kHz · Opus · Post-Fader Headphone Cue</span>
                            <span class="mixer-chassis-meta-icon" aria-hidden="true" title="48 kHz Opus · Post-Fader Headphone Cue">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="3"></circle>
                                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                                </svg>
                            </span>
                        </div>
                    </div>

                    <div id="mic-enable-wrap" class="mixer-mic-enable" hidden>
                        <div class="mixer-mic-enable-copy">
                            <span class="mixer-mic-enable-icon" aria-hidden="true">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"></path>
                                    <path d="M19 10v2a7 7 0 0 1-14 0v-2"></path>
                                    <line x1="12" y1="19" x2="12" y2="22"></line>
                                </svg>
                            </span>
                            <p class="mixer-hint">Allow microphone access to list your audio inputs.</p>
                        </div>
                        <button type="button" class="studio-btn studio-btn--sm studio-btn--primary" id="btn-enable-mic">Allow microphone</button>
                    </div>

                    <div class="mixer-strips" id="mixer-strips">
                        {{-- Strip 1: INPUT 1 --}}
                        <div class="mixer-strip" data-strip="mic" id="mic-channel">
                            <div class="mixer-scribble">
                                <div class="mixer-scribble-top">
                                    <span class="mixer-strip-title-wrap">
                                        <svg class="mixer-strip-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"></path>
                                            <path d="M19 10v2a7 7 0 0 1-14 0v-2"></path>
                                            <line x1="12" y1="19" x2="12" y2="22"></line>
                                        </svg>
                                        <p class="mixer-strip-label">INPUT 1</p>
                                    </span>
                                    <span class="mixer-strip-dots" aria-hidden="true">⋮</span>
                                </div>
                                <p class="mixer-strip-source-summary" id="mic-source-summary" title="Primary microphone">Default microphone</p>
                            </div>
                            <div class="mixer-meterbridge" aria-label="Input 1 level">
                                <div class="mixer-strip-meter">
                                    <div id="mic-meter" class="mixer-strip-meter-fill"></div>
                                </div>
                            </div>
                            <div class="mixer-strip-toggles">
                                <button type="button" class="mixer-cue" id="mic-cue" aria-pressed="false" title="Monitor Input 1 in headphones">
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M3 18v-6a9 9 0 0 1 18 0v6"></path>
                                        <path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"></path>
                                    </svg>
                                    <span>CUE</span>
                                </button>
                                <button type="button" class="mixer-mute" id="mic-mute" aria-pressed="false" title="Mute Input 1">
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon>
                                        <line x1="23" y1="9" x2="17" y2="15"></line>
                                        <line x1="17" y1="9" x2="23" y2="15"></line>
                                    </svg>
                                    <span>MUTE</span>
                                </button>
                            </div>
                            <div class="mixer-fader-well">
                                <div class="mixer-fader-scale" aria-hidden="true">
                                    <span>+6</span>
                                    <span class="mixer-fader-unity">0</span>
                                    <span>-6</span>
                                    <span>-12</span>
                                    <span>-24</span>
                                    <span>-∞</span>
                                </div>
                                <div class="mixer-fader-slot">
                                    <input type="range" id="mic-fader" class="mixer-fader" min="0" max="150" value="100" step="1" aria-label="Input 1 level">
                                </div>
                                <div class="mixer-well-meter" aria-hidden="true">
                                    <div class="mixer-well-meter-fill"></div>
                                </div>
                            </div>
                            <label class="mixer-field mixer-field--source" for="audio-input">
                                <span class="mixer-field-label">SOURCE</span>
                                <select id="audio-input" class="mixer-field-control" aria-label="Input 1 source">
                                    <option value="">Allow microphone to list mics</option>
                                </select>
                            </label>
                        </div>

                        {{-- Strip 2: INPUT 2 --}}
                        <div class="mixer-strip" data-strip="aux" id="aux-channel">
                            <div class="mixer-scribble">
                                <div class="mixer-scribble-top">
                                    <span class="mixer-strip-title-wrap">
                                        <svg class="mixer-strip-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"></path>
                                            <path d="M19 10v2a7 7 0 0 1-14 0v-2"></path>
                                            <line x1="12" y1="19" x2="12" y2="22"></line>
                                        </svg>
                                        <p class="mixer-strip-label">INPUT 2</p>
                                    </span>
                                    <span class="mixer-strip-dots" aria-hidden="true">⋮</span>
                                </div>
                                <p class="mixer-strip-source-summary" id="aux-source-summary" title="Secondary input source">No source selected</p>
                            </div>
                            <div class="mixer-meterbridge" aria-label="Input 2 level">
                                <div class="mixer-strip-meter">
                                    <div id="aux-meter" class="mixer-strip-meter-fill"></div>
                                </div>
                            </div>
                            <div class="mixer-strip-toggles">
                                <button type="button" class="mixer-cue" id="aux-cue" aria-pressed="false" title="Monitor Input 2 in headphones">
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M3 18v-6a9 9 0 0 1 18 0v6"></path>
                                        <path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"></path>
                                    </svg>
                                    <span>CUE</span>
                                </button>
                                <button type="button" class="mixer-mute is-active" id="aux-mute" aria-pressed="true" title="Mute Input 2">
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon>
                                        <line x1="23" y1="9" x2="17" y2="15"></line>
                                        <line x1="17" y1="9" x2="23" y2="15"></line>
                                    </svg>
                                    <span>MUTE</span>
                                </button>
                            </div>
                            <div class="mixer-fader-well">
                                <div class="mixer-fader-scale" aria-hidden="true">
                                    <span>+6</span>
                                    <span class="mixer-fader-unity">0</span>
                                    <span>-6</span>
                                    <span>-12</span>
                                    <span>-24</span>
                                    <span>-∞</span>
                                </div>
                                <div class="mixer-fader-slot">
                                    <input type="range" id="aux-fader" class="mixer-fader" min="0" max="150" value="100" step="1" aria-label="Input 2 level">
                                </div>
                                <div class="mixer-well-meter" aria-hidden="true">
                                    <div class="mixer-well-meter-fill"></div>
                                </div>
                            </div>
                            <label class="mixer-field mixer-field--source" for="aux-input">
                                <span class="mixer-field-label">SOURCE</span>
                                <select id="aux-input" class="mixer-field-control" aria-label="Input 2 source">
                                    <option value="">Select source</option>
                                </select>
                            </label>
                        </div>

                        {{-- Strip 3: PLAYLIST --}}
                        <div class="mixer-strip" data-strip="playlist" id="playlist-channel">
                            <div class="mixer-scribble">
                                <div class="mixer-scribble-top">
                                    <span class="mixer-strip-title-wrap">
                                        <svg class="mixer-strip-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M9 18V5l12-2v13"></path>
                                            <circle cx="6" cy="18" r="3"></circle>
                                            <circle cx="18" cy="16" r="3"></circle>
                                        </svg>
                                        <p class="mixer-strip-label">PLAYLIST</p>
                                    </span>
                                    <span class="mixer-strip-dots" aria-hidden="true">⋮</span>
                                </div>
                                <p class="mixer-strip-source-summary" id="playlist-source-summary">Queue empty</p>
                            </div>
                            <div class="mixer-meterbridge" aria-label="Playlist level">
                                <div class="mixer-strip-meter">
                                    <div id="playlist-meter" class="mixer-strip-meter-fill"></div>
                                </div>
                            </div>
                            <div class="mixer-strip-toggles">
                                <button type="button" class="mixer-cue" id="playlist-cue" aria-pressed="false" title="Monitor Playlist in headphones">
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M3 18v-6a9 9 0 0 1 18 0v6"></path>
                                        <path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"></path>
                                    </svg>
                                    <span>CUE</span>
                                </button>
                                <button type="button" class="mixer-mute" id="playlist-mute" aria-pressed="false" title="Mute Playlist">
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon>
                                        <line x1="23" y1="9" x2="17" y2="15"></line>
                                        <line x1="17" y1="9" x2="23" y2="15"></line>
                                    </svg>
                                    <span>MUTE</span>
                                </button>
                            </div>
                            <div class="mixer-fader-well">
                                <div class="mixer-fader-scale" aria-hidden="true">
                                    <span>+6</span>
                                    <span class="mixer-fader-unity">0</span>
                                    <span>-6</span>
                                    <span>-12</span>
                                    <span>-24</span>
                                    <span>-∞</span>
                                </div>
                                <div class="mixer-fader-slot">
                                    <input type="range" id="playlist-fader" class="mixer-fader" min="0" max="150" value="85" step="1" aria-label="Playlist level">
                                </div>
                                <div class="mixer-well-meter" aria-hidden="true">
                                    <div class="mixer-well-meter-fill"></div>
                                </div>
                            </div>
                            <div class="mixer-field mixer-field--source">
                                <span class="mixer-field-label">QUEUE</span>
                                <p class="mixer-field-static" id="playlist-count">No sounds</p>
                            </div>
                        </div>

                        {{-- Strip 4: MASTER --}}
                        <div class="mixer-strip mixer-strip--out" data-strip="out">
                            <div class="mixer-scribble mixer-scribble--master">
                                <div class="mixer-scribble-top">
                                    <span class="mixer-strip-title-wrap">
                                        <svg class="mixer-strip-icon mixer-strip-icon--master" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M2 10v3"></path>
                                            <path d="M6 6v11"></path>
                                            <path d="M10 3v18"></path>
                                            <path d="M14 8v7"></path>
                                            <path d="M18 5v13"></path>
                                            <path d="M22 10v3"></path>
                                        </svg>
                                        <p class="mixer-strip-label">MASTER</p>
                                    </span>
                                    <span class="mixer-strip-dots" aria-hidden="true">⋮</span>
                                </div>
                                <p class="mixer-strip-source-summary" id="master-source-summary">Auto · Default output</p>
                            </div>
                            <div class="mixer-meterbridge" aria-label="Master output level">
                                <div class="mixer-strip-meter mixer-strip-meter--master">
                                    <div id="level-meter" class="mixer-strip-meter-fill"></div>
                                </div>
                            </div>
                            <div class="mixer-strip-toggles mixer-strip-toggles--master">
                                <span class="mixer-rec" id="meter-label" aria-live="polite">READY</span>
                            </div>
                            <div class="mixer-fader-well mixer-fader-well--master">
                                <div class="mixer-fader-scale" aria-hidden="true">
                                    <span>+6</span>
                                    <span class="mixer-fader-unity">0</span>
                                    <span>-6</span>
                                    <span>-12</span>
                                    <span>-24</span>
                                    <span>-∞</span>
                                </div>
                                <div class="mixer-fader-slot">
                                    <input type="range" id="master-fader" class="mixer-fader" min="0" max="150" value="100" step="1" aria-label="Master output level">
                                </div>
                                <div class="mixer-well-meter" aria-hidden="true">
                                    <div class="mixer-well-meter-fill"></div>
                                </div>
                            </div>
                            <div class="mixer-master-fields">
                                <label class="mixer-field" for="audio-layout" title="Auto sends mono for a single mic and stereo when a 2-channel feed is active.">
                                    <span class="mixer-field-label">OUTPUT</span>
                                    <select id="audio-layout" class="mixer-field-control" aria-label="Broadcast audio layout">
                                        <option value="auto" selected>Auto</option>
                                        <option value="mono">Mono</option>
                                        <option value="stereo">Stereo</option>
                                    </select>
                                </label>
                                <label class="mixer-field" for="audio-output">
                                    <span class="mixer-field-label">MONITOR</span>
                                    <select id="audio-output" class="mixer-field-control" aria-label="Cue headphone output">
                                        <option value="">Default output</option>
                                    </select>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Right Column: Persistent Tabbed Tools Dock --}}
            <aside class="studio-dock" aria-label="Studio tools dock">
                <div class="studio-dock-header">
                    <div class="studio-dock-tabs" role="tablist" aria-label="Studio workspace tools">
                        <button
                            type="button"
                            class="studio-dock-tab is-active"
                            role="tab"
                            id="dock-tab-library"
                            aria-selected="true"
                            aria-controls="dock-panel-library"
                            data-dock-tab="library"
                        >
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M9 18V5l12-2v13"></path>
                                <circle cx="6" cy="18" r="3"></circle>
                                <circle cx="18" cy="16" r="3"></circle>
                            </svg>
                            <span>Library</span>
                        </button>
                        <button
                            type="button"
                            class="studio-dock-tab"
                            role="tab"
                            id="dock-tab-board"
                            aria-selected="false"
                            aria-controls="dock-panel-board"
                            data-dock-tab="board"
                        >
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                                <line x1="3" y1="9" x2="21" y2="9"></line>
                                <line x1="9" y1="21" x2="9" y2="9"></line>
                            </svg>
                            <span>Board</span>
                        </button>
                        <button
                            type="button"
                            class="studio-dock-tab"
                            role="tab"
                            id="dock-tab-stage"
                            aria-selected="false"
                            aria-controls="dock-panel-stage"
                            data-dock-tab="stage"
                        >
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="12" cy="12" r="2"></circle>
                                <path d="M16.24 7.76a6 6 0 0 1 0 8.49m-8.48-.01a6 6 0 0 1 0-8.49m11.31-2.82a10 10 0 0 1 0 14.14m-14.14 0a10 10 0 0 1 0-14.14"></path>
                            </svg>
                            <span>Stage</span>
                        </button>
                        <button
                            type="button"
                            class="studio-dock-tab"
                            role="tab"
                            id="dock-tab-share"
                            aria-selected="false"
                            aria-controls="dock-panel-share"
                            data-dock-tab="share"
                        >
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                            </svg>
                            <span>Share</span>
                        </button>
                    </div>
                </div>

                <div class="studio-dock-body">
                    {{-- Local recording archive prompt (shown when a local session recording finishes) --}}
                    <div id="studio-local-recording" class="mixer-local-recording mixer-card" hidden>
                        <p class="mixer-recording-title" id="studio-local-recording-title">Local recording ready</p>
                        <p class="mixer-recording-meta" id="studio-local-recording-meta"></p>
                        <label for="local-recording-title" class="mixer-field-label">Recording title</label>
                        <input
                            type="text"
                            id="local-recording-title"
                            class="mixer-library-search"
                            maxlength="255"
                            placeholder="Optional — defaults to event name"
                        >
                        <div class="mixer-recording-actions">
                            <button type="button" class="studio-btn studio-btn--primary" id="btn-upload-recording">
                                Upload
                            </button>
                            <button type="button" class="studio-btn studio-btn--secondary" id="btn-download-recording">
                                Save locally
                            </button>
                            <button type="button" class="studio-btn studio-btn--danger-outline" id="btn-discard-recording">
                                Discard
                            </button>
                        </div>
                    </div>

                    {{-- TAB 1: LIBRARY & SESSION PLAYLIST --}}
                    <section
                        id="dock-panel-library"
                        class="studio-dock-panel is-active"
                        role="tabpanel"
                        aria-labelledby="dock-tab-library"
                        data-dock-panel="library"
                    >
                        <div class="mixer-card studio-dock-card">
                            <div class="mixer-playlist-head">
                                <div class="studio-section-title-group">
                                    <span class="studio-card-title-icon" aria-hidden="true">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M2 10v3"></path>
                                            <path d="M6 6v11"></path>
                                            <path d="M10 3v18"></path>
                                            <path d="M14 8v7"></path>
                                            <path d="M18 5v13"></path>
                                            <path d="M22 10v3"></path>
                                        </svg>
                                    </span>
                                    <h2>Audio Library</h2>
                                </div>
                                <span class="studio-section-sub">Saved tracks</span>
                            </div>

                            <div class="mixer-library-toolbar studio-library-toolbar">
                                <div class="studio-library-search-wrap">
                                    <div class="studio-library-search-field">
                                        <svg class="studio-library-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <circle cx="11" cy="11" r="8"></circle>
                                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                        </svg>
                                        <input
                                            type="search"
                                            id="library-search"
                                            class="mixer-library-search"
                                            placeholder="Search library..."
                                            aria-label="Search audio library"
                                        >
                                    </div>
                                    <span class="studio-library-filter-badge" aria-hidden="true" title="Saved library filter">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <line x1="4" y1="6" x2="20" y2="6"></line>
                                            <line x1="7" y1="12" x2="17" y2="12"></line>
                                            <line x1="10" y1="18" x2="14" y2="18"></line>
                                        </svg>
                                    </span>
                                </div>
                                <div class="studio-library-controls-row">
                                    <label for="library-destination" class="studio-library-dest-wrap">
                                        <span class="studio-library-save-label">Save to</span>
                                        <span class="studio-library-dest-select-shell">
                                            <svg class="studio-library-dest-folder" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                                            </svg>
                                            <select id="library-destination" class="mixer-field-control studio-library-dest-select" aria-label="Storage destination for new uploads">
                                                <option value="local" @selected(empty($objectStorageEnabled))>Server disk</option>
                                                <option value="platform" @selected(! empty($objectStorageEnabled))>Platform</option>
                                                <option value="drive">Google Drive</option>
                                            </select>
                                        </span>
                                    </label>
                                    <button type="button" class="studio-btn studio-btn--primary" id="btn-upload-library">
                                        + Upload
                                    </button>
                                    <button type="button" class="studio-btn studio-btn--secondary" id="btn-drive-connect" hidden>
                                        Connect Drive
                                    </button>
                                    <button type="button" class="studio-btn studio-btn--secondary" id="btn-drive-browse" hidden>
                                        Import Drive ID
                                    </button>
                                </div>
                            </div>

                            <input
                                type="file"
                                id="file-input"
                                accept="audio/*,.mp3,.wav,.m4a,.aac,.ogg,.flac"
                                multiple
                                class="sr-only"
                            >
                            <p class="mixer-hint mixer-library-hint" id="library-destination-hint">
                                @if (! empty($objectStorageEnabled))
                                    Platform — Sound Mix cloud storage. Uses your plan storage quota.
                                @else
                                    Server disk — stored on this broadcast server. Uses your plan storage quota.
                                @endif
                            </p>
                            <p class="mixer-hint mixer-library-hint mixer-library-storage-summary" id="library-storage-summary" aria-live="polite" hidden></p>
                            <p class="mixer-status mixer-library-status" id="library-status" aria-live="polite"></p>
                            <div id="library-list" class="mixer-library-list" role="list" aria-label="Saved audio library">
                                <p class="mixer-hint">Loading library…</p>
                            </div>
                        </div>

                        <div class="mixer-card studio-dock-card studio-dock-card--queue">
                            <div class="mixer-playlist-head">
                                <div class="studio-section-title-group">
                                    <span class="studio-card-title-icon" aria-hidden="true">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round">
                                            <line x1="8" y1="6" x2="21" y2="6"></line>
                                            <line x1="8" y1="12" x2="21" y2="12"></line>
                                            <line x1="8" y1="18" x2="21" y2="18"></line>
                                            <line x1="3" y1="6" x2="3.01" y2="6"></line>
                                            <line x1="3" y1="12" x2="3.01" y2="12"></line>
                                            <line x1="3" y1="18" x2="3.01" y2="18"></line>
                                        </svg>
                                    </span>
                                    <h2>Session Playlist</h2>
                                    <time id="playlist-duration">00:00:00</time>
                                </div>
                                <button type="button" class="studio-btn studio-btn--sm studio-btn--secondary" id="btn-add-file">
                                    + Upload &amp; queue
                                </button>
                            </div>
                            <div id="audio-channels" class="mixer-playlist-list">
                                <div id="playlist-empty-state" class="mixer-hint studio-queue-empty">
                                    <span class="studio-queue-empty-icon" aria-hidden="true">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M9 18V5l12-2v13"></path>
                                            <circle cx="6" cy="18" r="3"></circle>
                                            <circle cx="18" cy="16" r="3"></circle>
                                        </svg>
                                    </span>
                                    <div class="studio-queue-empty-copy">
                                        <p class="studio-queue-empty-title">Your session queue is empty.</p>
                                        <p class="studio-queue-empty-sub">Add tracks from your library to play them during the broadcast.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    {{-- TAB 2: BOARD (SCRIPTURE, SONGS & SLIDES, SERVICE GALLERY) --}}
                    <section
                        id="dock-panel-board"
                        class="studio-dock-panel"
                        role="tabpanel"
                        aria-labelledby="dock-tab-board"
                        data-dock-panel="board"
                        hidden
                    >
                        @if ($scriptureEnabled)
                            <div class="studio-scripture mixer-card studio-dock-card" id="studio-scripture">
                                <div class="mixer-playlist-head">
                                    <div class="studio-section-title-group">
                                        <span class="studio-card-title-icon" aria-hidden="true">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                                                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                                            </svg>
                                        </span>
                                        <h2>Scripture Cue</h2>
                                    </div>
                                    <span class="studio-section-sub">KJV · Live Board</span>
                                </div>
                                <div class="scripture-search-field">
                                    <div id="scripture-search-ghost" class="scripture-search-ghost" aria-hidden="true"></div>
                                    <input
                                        type="search"
                                        id="scripture-search"
                                        class="mixer-library-search"
                                        placeholder="Book, chapter & verse (e.g. John 3:16, Rom 8:28)…"
                                        autocomplete="off"
                                        spellcheck="false"
                                        aria-label="Scripture reference"
                                    >
                                </div>
                                <div id="scripture-suggestions" class="scripture-suggestions" role="listbox" aria-label="Verse suggestions"></div>
                                <div class="mixer-gallery-actions studio-board-actions">
                                    <button type="button" class="studio-btn studio-btn--primary" id="btn-scripture-show">Show on Board</button>
                                    <button type="button" class="studio-btn studio-btn--secondary" id="btn-scripture-clear">Clear</button>
                                    <button type="button" class="studio-btn studio-btn--ghost" id="btn-scripture-listen" aria-pressed="false">
                                        Auto-listen: Off
                                    </button>
                                </div>
                                <div id="scripture-live" class="scripture-live" hidden aria-live="polite">
                                    <span class="scripture-live-label">Live transcript</span>
                                    <p id="scripture-live-text" class="scripture-live-text">Listening…</p>
                                </div>
                                <div id="scripture-confirm" class="scripture-confirm" hidden>
                                    <p class="mixer-hint">Detected <strong id="scripture-confirm-ref"></strong></p>
                                    <div class="mixer-gallery-actions">
                                        <button type="button" class="studio-btn studio-btn--primary" id="btn-scripture-confirm">Show on Board</button>
                                        <button type="button" class="studio-btn studio-btn--secondary" id="btn-scripture-dismiss">Dismiss</button>
                                    </div>
                                </div>
                                <p class="mixer-hint" id="scripture-status" aria-live="polite">No scripture on listen</p>
                            </div>

                            <div class="studio-songs mixer-card studio-dock-card" id="studio-songs">
                                <div class="mixer-playlist-head">
                                    <div class="studio-section-title-group">
                                        <span class="studio-card-title-icon" aria-hidden="true">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round">
                                                <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                                                <line x1="3" y1="9" x2="21" y2="9"></line>
                                                <line x1="9" y1="21" x2="9" y2="9"></line>
                                            </svg>
                                        </span>
                                        <h2>Songs &amp; Slides</h2>
                                    </div>
                                    <button type="button" class="studio-btn studio-btn--sm studio-btn--primary" id="btn-song-new">+ New song</button>
                                </div>
                                <div class="mixer-gallery-actions studio-board-actions">
                                    <button type="button" class="studio-btn studio-btn--secondary" id="btn-song-prev" title="Previous slide">← Prev slide</button>
                                    <button type="button" class="studio-btn studio-btn--secondary" id="btn-song-next" title="Next slide">Next slide →</button>
                                    <button type="button" class="studio-btn studio-btn--ghost" id="btn-song-clear">Clear Board</button>
                                </div>
                                <div id="song-live" class="song-live" hidden aria-live="polite">
                                    <p class="song-live-meta" id="song-live-meta" hidden></p>
                                    <p class="song-live-preview-text" id="song-live-preview" hidden></p>
                                </div>
                                <div id="song-form" class="song-form" hidden>
                                    <input
                                        type="text"
                                        id="song-title"
                                        class="mixer-library-search"
                                        placeholder="Song or announcement title"
                                        maxlength="160"
                                        aria-label="Song or announcement title"
                                    >
                                    <textarea
                                        id="song-body"
                                        class="song-body-input"
                                        rows="5"
                                        placeholder="Paste lyrics or slide text. Separate slides with a blank line."
                                        aria-label="Slides separated by blank lines"
                                    ></textarea>
                                    <div id="song-form-preview" class="song-form-preview" hidden aria-live="polite"></div>
                                    <div class="mixer-gallery-actions">
                                        <button type="button" class="studio-btn studio-btn--primary" id="btn-song-save">Save</button>
                                        <button type="button" class="studio-btn studio-btn--secondary" id="btn-song-cancel">Cancel</button>
                                    </div>
                                </div>
                                <div id="song-list" class="song-list" role="list" aria-label="Saved songs and announcements"></div>
                                <p class="mixer-hint" id="song-status" aria-live="polite">No song on listen</p>
                            </div>
                        @endif

                        <div class="mixer-gallery mixer-card studio-dock-card">
                            <div class="mixer-playlist-head">
                                <div class="studio-section-title-group">
                                    <span class="studio-card-title-icon" aria-hidden="true">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round">
                                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                            <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                            <polyline points="21 15 16 10 5 21"></polyline>
                                        </svg>
                                    </span>
                                    <h2>Service Gallery</h2>
                                </div>
                                <span class="studio-section-sub">Event photos &amp; reels</span>
                            </div>
                            <div class="mixer-gallery-actions studio-board-actions">
                                <button type="button" class="studio-btn studio-btn--secondary" id="btn-add-gallery">
                                    + Add photo
                                </button>
                                <button type="button" class="studio-btn studio-btn--secondary" id="btn-add-reel">
                                    + Add video reel
                                </button>
                                <input type="file" id="gallery-input" accept="image/*" multiple class="sr-only">
                                <input type="file" id="reel-input" accept="video/mp4,video/webm,video/quicktime" class="sr-only">
                            </div>
                            <div id="studio-gallery-list" class="mixer-gallery-list">
                                @foreach (($galleryImages ?? collect())->take(6) as $image)
                                    @php($isVideo = $image->isVideo())
                                    <figure class="mixer-gallery-thumb {{ $isVideo ? 'is-video' : '' }}" data-id="{{ $image->id }}">
                                        @if ($isVideo)
                                            <video src="{{ $image->url() }}" muted playsinline preload="metadata"></video>
                                            <span class="mixer-reel-badge">Reel</span>
                                        @else
                                            <img src="{{ $image->url() }}" alt="{{ $image->caption ?? 'Gallery photo' }}">
                                        @endif
                                        <button
                                            type="button"
                                            class="mixer-gallery-delete"
                                            aria-label="{{ $isVideo ? 'Remove reel from gallery' : 'Remove photo from gallery' }}"
                                            title="Remove"
                                        >×</button>
                                    </figure>
                                @endforeach
                            </div>
                        </div>
                    </section>

                    {{-- TAB 3: STAGE (NDI / DESKTOP STAGE) --}}
                    <section
                        id="dock-panel-stage"
                        class="studio-dock-panel"
                        role="tabpanel"
                        aria-labelledby="dock-tab-stage"
                        data-dock-panel="stage"
                        hidden
                    >
                        @if ($scriptureEnabled)
                            <div class="studio-ndi mixer-card studio-dock-card" id="studio-ndi">
                                <div class="mixer-playlist-head">
                                    <div class="studio-section-title-group">
                                        <span class="studio-card-title-icon" aria-hidden="true">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round">
                                                <circle cx="12" cy="12" r="2"></circle>
                                                <path d="M16.24 7.76a6 6 0 0 1 0 8.49m-8.48-.01a6 6 0 0 1 0-8.49m11.31-2.82a10 10 0 0 1 0 14.14m-14.14 0a10 10 0 0 1 0-14.14"></path>
                                            </svg>
                                        </span>
                                        <h2>Stage</h2>
                                        <span class="studio-section-sub">NDI / Stage</span>
                                    </div>
                                    <span class="studio-status-pill" id="ndi-connection-badge">WAITING FOR SOURCE</span>
                                </div>

                                <div class="studio-stage-viewport">
                                    <img id="ndi-preview" class="studio-stage-preview" alt="NDI stage preview" hidden>
                                    <div id="ndi-empty-state" class="studio-stage-empty">
                                        <p class="studio-stage-empty-title">No desktop stage connected</p>
                                        <p class="studio-stage-empty-sub">Connect Desktop Studio to publish compatible stage content to listeners.</p>
                                        <div class="studio-stage-sources" aria-label="Compatible stage sources">
                                            <span class="studio-stage-source-pill">EasyWorship</span>
                                            <span class="studio-stage-source-pill">vMix</span>
                                            <span class="studio-stage-source-pill">OBS / NDI</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="mixer-gallery-actions studio-stage-footer">
                                    <span class="mixer-hint" id="ndi-source-label"></span>
                                    <button type="button" class="studio-btn studio-btn--danger-outline" id="btn-ndi-clear" hidden>Take off stage</button>
                                </div>
                                <p class="mixer-hint" id="ndi-status" aria-live="polite">No NDI on listen — connect a source in Desktop Studio</p>
                            </div>
                        @else
                            <div class="mixer-card studio-dock-card">
                                <div class="mixer-playlist-head">
                                    <div class="studio-section-title-group">
                                        <span class="studio-card-title-icon" aria-hidden="true">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round">
                                                <circle cx="12" cy="12" r="2"></circle>
                                                <path d="M16.24 7.76a6 6 0 0 1 0 8.49m-8.48-.01a6 6 0 0 1 0-8.49m11.31-2.82a10 10 0 0 1 0 14.14m-14.14 0a10 10 0 0 1 0-14.14"></path>
                                            </svg>
                                        </span>
                                        <h2>Stage</h2>
                                        <span class="studio-section-sub">NDI / Stage</span>
                                    </div>
                                    <span class="studio-status-pill">WAITING FOR SOURCE</span>
                                </div>
                                <div class="studio-stage-viewport">
                                    <div class="studio-stage-empty">
                                        <p class="studio-stage-empty-title">No desktop stage connected</p>
                                        <p class="studio-stage-empty-sub">Connect Desktop Studio to publish compatible stage content to listeners.</p>
                                        <div class="studio-stage-sources" aria-label="Compatible stage sources">
                                            <span class="studio-stage-source-pill">EasyWorship</span>
                                            <span class="studio-stage-source-pill">vMix</span>
                                            <span class="studio-stage-source-pill">OBS / NDI</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </section>

                    {{-- TAB 4: SHARE --}}
                    <section
                        id="dock-panel-share"
                        class="studio-dock-panel"
                        role="tabpanel"
                        aria-labelledby="dock-tab-share"
                        data-dock-panel="share"
                        hidden
                    >
                        <div class="mixer-card mixer-card--share studio-dock-card">
                            <div class="mixer-playlist-head">
                                <div class="studio-section-title-group">
                                    <span class="studio-card-title-icon" aria-hidden="true">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round">
                                            <circle cx="18" cy="5" r="3"></circle>
                                            <circle cx="6" cy="12" r="3"></circle>
                                            <circle cx="18" cy="19" r="3"></circle>
                                            <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
                                            <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
                                        </svg>
                                    </span>
                                    <h2>Share</h2>
                                    <span class="studio-section-sub">Listener access</span>
                                </div>
                            </div>

                            <div class="mixer-listen-row studio-share-block" id="share-event-block" @if (! $openEvent) hidden @endif>
                                <span class="mixer-field-label">ACTIVE EVENT LINK</span>
                                <div class="studio-share-field">
                                    <code id="event-url" class="studio-share-code" title="{{ $activeEventUrl ?: $channelUrl }}">{{ $activeEventUrl ?: $channelUrl }}</code>
                                    <div class="studio-share-inline-actions">
                                        <button type="button" class="studio-btn studio-btn--sm studio-btn--primary" id="btn-copy-event">Copy</button>
                                        <button type="button" class="studio-btn studio-btn--sm studio-btn--secondary" id="btn-share-event">Share</button>
                                    </div>
                                </div>
                            </div>

                            <div class="studio-share-block" id="share-channel-block">
                                <span class="mixer-field-label">PERMANENT CHANNEL LINK</span>
                                <div class="studio-share-field">
                                    <code id="channel-url" class="studio-share-code" title="{{ $channelUrl }}">{{ $channelUrl }}</code>
                                    <div class="studio-share-inline-actions">
                                        <button type="button" class="studio-btn studio-btn--sm {{ $openEvent ? 'studio-btn--secondary' : 'studio-btn--primary' }}" id="btn-copy-channel">Copy</button>
                                        <button type="button" class="studio-btn studio-btn--sm studio-btn--secondary" id="btn-share-channel-main" @if ($openEvent) hidden @endif>Share</button>
                                    </div>
                                </div>
                                <p class="mixer-hint studio-share-note" id="share-event-note" @if ($openEvent) hidden @endif>
                                    An event-specific link appears automatically when an event is created or live.
                                </p>
                            </div>
                        </div>
                    </section>
                </div>
            </aside>
        </main>

        {{-- 3. Authoritative Bottom Broadcast Transport Bar --}}
        <footer class="mixer-bar">
            <div class="mixer-bar-left">
                <p class="mixer-air" id="studio-on-air-pill">
                    <span class="mixer-air-dot"></span>
                    <span id="studio-air-label">{{ $openEvent?->status?->value === 'paused' ? 'PAUSED' : ($openEvent?->status?->value === 'live' ? 'ON THE AIR' : 'STANDBY') }}</span>
                </p>
                <p class="mixer-timer" id="studio-timer">00:00:00</p>
                <p class="mixer-bar-rec-pill" id="studio-local-recording-live" hidden>
                    <span class="mixer-bar-rec-dot" aria-hidden="true"></span>
                    <span>Recording locally</span>
                </p>
            </div>

            <div class="mixer-bar-center">
                <div class="mixer-bar-status-wrap">
                    <span class="mixer-bar-status-icon" aria-hidden="true">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"></path>
                            <path d="M19 10v2a7 7 0 0 1-14 0v-2"></path>
                            <line x1="12" y1="19" x2="12" y2="22"></line>
                        </svg>
                    </span>
                    <p id="studio-status" class="mixer-status mixer-bar-status" role="status" aria-live="polite"></p>
                </div>
            </div>

            <div class="mixer-bar-actions">
                <button type="button" class="mixer-btn-secondary" id="btn-create-event" @if (! $broadcastAllowed) disabled @endif>
                    Create event
                </button>
                <button type="button" class="mixer-btn-stop" id="btn-pause" hidden disabled>
                    Pause
                </button>
                <button type="button" class="mixer-btn-stop mixer-btn-stop--danger" id="btn-end" hidden disabled>
                    End live
                </button>
                <button type="button" class="mixer-btn-secondary" id="btn-publish-recording" hidden disabled>
                    Publish podcast
                </button>
                <a class="mixer-btn-secondary" id="btn-edit-recording" hidden aria-disabled="true">
                    Edit recording
                </a>
                @if ($billingUrl)
                    <a
                        href="{{ $billingUrl }}"
                        class="mixer-hero-upgrade mixer-bar-upgrade"
                        id="btn-upgrade-live"
                        @if ($broadcastAllowed) hidden @endif
                    >
                        Upgrade to go live
                    </a>
                @endif
                <button type="button" class="mixer-btn-start" id="btn-start" @if (! $broadcastAllowed) hidden disabled @endif>
                    <svg class="mixer-btn-start-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="2"></circle>
                        <path d="M16.24 7.76a6 6 0 0 1 0 8.49m-8.48-.01a6 6 0 0 1 0-8.49m11.31-2.82a10 10 0 0 1 0 14.14m-14.14 0a10 10 0 0 1 0-14.14"></path>
                    </svg>
                    <span class="mixer-btn-start-label">{{ $openEvent?->status?->value === 'paused' ? 'Resume' : ($openEvent?->status?->value === 'live' ? 'Reconnect' : 'Go live') }}</span>
                </button>
            </div>
        </footer>
    </div>

    <script>
        (function () {
            const root = document.getElementById('studio-root');
            const url = root?.dataset.sessionShowUrl;
            if (!root || !url) return;
            async function checkRemoteReload() {
                try {
                    const res = await fetch(url, {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                        cache: 'no-store',
                    });
                    const data = await res.json().catch(() => ({}));
                    const at = Number(data.reload_at || 0);
                    const loaded = Number(root.dataset.studioReloadAt || 0);
                    if (at > loaded) {
                        const stage = document.getElementById('studio-stage');
                        const mode = (document.getElementById('studio-mode')?.textContent || '').trim();
                        if (stage?.classList.contains('is-on-air') || /ON AIR|ON THE AIR|RECONNECT/i.test(mode)) {
                            sessionStorage.setItem('sml-studio-resume', '1');
                        }
                        window.location.reload();
                    }
                } catch {}
            }
            window.setInterval(checkRemoteReload, 20000);
        })();
    </script>
@endsection

@section('whatsapp_widget')
    <x-whatsapp-support
        context="studio"
        :organisation="$channelTitle"
        :eventTitle="$openEvent?->title"
        :status="$openEvent?->status?->value === 'live' ? 'On Air' : ($openEvent?->status?->value === 'paused' ? 'Paused' : 'Standby')"
    />
@endsection

