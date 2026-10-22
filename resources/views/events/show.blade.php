@extends('layouts.stream')

@section('title', $event->title.' · '.$event->organization->name)

@section('vite')
    @vite(['resources/js/listen.js', 'resources/js/chat.js', 'resources/js/event-engage.js'])
@endsection

@php
    $theme = $event->organization->themeColor();
    $isLive = $event->isLive();
    $isPaused = $event->status->value === 'paused';
    $isEnded = $event->status->value === 'ended';
    $artwork = $event->organization->artworkUrl();
    $background = ($listenBackgroundUrl ?? null) ?: ($event->artworkUrl() ?: asset('images/listen-default-live.jpg'));
    $cardArt = $artwork ?: $background;
    $statusLabel = match ($event->status->value) {
        'live' => 'Live',
        'paused' => 'Paused',
        'ended' => 'Ended',
        default => 'Scheduled',
    };
    $shareUrl = route('events.show', $event);
    $scriptureEnabled = $event->organization->creator_type === \App\Enums\CreatorType::Church;
    $scriptureUrl = ($scriptureEnabled && $event->stream)
        ? route('scripture.show', $event->stream)
        : null;
    $liveBoard = $event->liveBoardMode();
@endphp

@section('content')
    <div class="stage stage-cinema portal" style="--stage-accent: {{ $theme }};">
        {{-- Calm subtle top ambient tint only --}}
        <div class="portal-ambient" aria-hidden="true"></div>

        <div class="stage-shell portal-reference-shell">
            {{-- APPROVED REFERENCE: the player IS the first viewport. --}}
            <section class="portal-hero-viewport portal-reference-hero" id="portal-hero-viewport" aria-label="Live audio player">
                <div class="portal-player-screen portal-reference-player stage-rise">
                    <div class="portal-player-backdrop">
                        <img src="{{ $background }}" alt="{{ $event->title }}" class="portal-backdrop-img">
                        <div class="portal-backdrop-gradient" aria-hidden="true"></div>
                    </div>

                    <div class="portal-player-content portal-reference-content">
                        <header class="portal-reference-nav" aria-label="Listen navigation">
                            <a href="{{ url('/') }}" class="portal-reference-wordmark" aria-label="SoundMix home">
                                <svg class="portal-reference-logo-mark" viewBox="0 0 32 32" aria-hidden="true">
                                    <path d="M4 16h2M8 11v10M12 7v18M16 4v24M20 8v16M24 11v10M28 15v2" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/>
                                </svg>
                                <span>Sound<span>Mix</span></span>
                            </a>

                            <nav class="portal-reference-nav-center" aria-label="Primary">
                                <a href="#portal-hero-viewport" class="is-active">Listen</a>
                                <a href="{{ route('discover') }}">Explore</a>
                                <a href="{{ url('/library') }}">My Library</a>
                            </nav>

                            <div class="portal-reference-nav-actions">
                                <a href="{{ route('discover') }}" class="portal-reference-icon-btn" aria-label="Search">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.2-3.2"></path></svg>
                                </a>
                                <a href="{{ auth()->check() ? url('/account') : route('login') }}" class="portal-reference-profile-btn" aria-label="{{ auth()->check() ? 'Account' : 'Log in' }}">
                                    @auth
                                        <span>{{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}</span>
                                    @else
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="8" r="4"></circle><path d="M4.5 21a7.5 7.5 0 0 1 15 0"></path></svg>
                                    @endauth
                                </a>
                            </div>
                        </header>

                        <div class="portal-reference-live-meta" aria-label="Broadcast status and audience">
                            <span id="broadcast-badge" class="portal-reference-live-badge {{ $isLive ? 'is-live' : ($isPaused ? 'is-paused' : 'is-idle') }}">
                                @if ($isLive || $isPaused)
                                    <span class="live-dot {{ $isPaused ? 'is-paused' : '' }}" aria-hidden="true"></span>
                                @endif
                                {{ $statusLabel }}
                            </span>
                            @if ($event->show_listener_count)
                                <span class="portal-reference-listeners">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M16 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM8 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm0 2c-3.14 0-6 1.57-6 4v2h12v-2c0-2.43-2.86-4-6-4Zm8 0c-.46 0-.9.03-1.33.1 1.42 1.04 2.33 2.35 2.33 3.9v2h5v-2c0-2.43-2.86-4-6-4Z"/></svg>
                                    <span><span id="listener-count">{{ $listenerCount ?? 0 }}</span> listening</span>
                                </span>
                            @endif
                        </div>

                        @if ($scriptureEnabled)
                            @php
                                $hasLiveScripture = ($liveBoard === 'scripture') && filled($event->scripture_ref) && filled($event->scripture_text);
                            @endphp
                            <div
                                id="live-scripture-overlay"
                                class="live-scripture-overlay portal-reference-scripture {{ $hasLiveScripture ? 'is-visible' : '' }}"
                                @if (! $hasLiveScripture) hidden @endif
                                role="region"
                                aria-label="Live scripture"
                                aria-live="polite"
                            >
                                <div class="live-scripture-card" id="live-scripture-card" role="button" tabindex="0" aria-expanded="false" title="Tap to expand or collapse">
                                    <div class="live-scripture-top">
                                        <div class="live-scripture-kicker-wrap">
                                            <svg class="live-scripture-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.1A9 9 0 0 0 6 3.8c-1 0-2 .16-3 .5v14.2A9 9 0 0 1 6 18c2.3 0 4.4.87 6 2.3m0-14.2A9 9 0 0 1 18 3.8c1 0 2 .16 3 .5v14.2A9 9 0 0 0 18 18a9 9 0 0 0-6 2.3m0-14.2v14.2"/></svg>
                                            <span class="live-scripture-ref" id="live-scripture-ref">{{ $event->scripture_ref }}</span>
                                        </div>
                                        <span class="live-scripture-version-pill">KJV</span>
                                    </div>
                                    <div class="live-scripture-body" id="live-scripture-body">
                                        <p class="live-scripture-text" id="live-scripture-text">@if ($hasLiveScripture)“{{ $event->scripture_text }}”@endif</p>
                                    </div>
                                    <div class="live-scripture-expand-hint" id="live-scripture-expand-hint" aria-hidden="true">
                                        <span class="expand-label">Tap to expand</span>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <div class="portal-reference-bottom">
                            <div class="portal-reference-event-copy">
                                <h1 class="portal-title">{{ $event->title }}</h1>
                                <div class="portal-channel-row">
                                    <div class="portal-channel-badge">
                                        @if ($event->organization->logo_url)
                                            <img src="{{ $event->organization->logo_url }}" alt="" class="portal-channel-avatar">
                                        @else
                                            <span class="portal-channel-monogram">{{ strtoupper(substr($event->organization->name, 0, 1)) }}</span>
                                        @endif
                                    </div>
                                    <a href="{{ $event->organization->channelUrl() }}" class="portal-channel-link">{{ $event->organization->name }}</a>
                                </div>
                                @if ($event->description)
                                    <p class="portal-description">{{ $event->description }}</p>
                                @endif
                            </div>

                            @if ($isEnded)
                                <div class="portal-ended-inline">
                                    <span class="portal-ended-badge">Broadcast Ended</span>
                                    <a href="{{ $event->organization->channelUrl() }}" class="portal-ended-btn">Visit Channel Archive</a>
                                </div>
                            @else
                                <div class="portal-reference-transport" aria-label="Live player controls">
                                    @auth
                                        <button
                                            type="button"
                                            id="btn-like"
                                            class="portal-reference-transport-btn portal-like {{ $userHearted ? 'is-on' : '' }}"
                                            data-liked="{{ $userHearted ? '1' : '0' }}"
                                            aria-label="Like"
                                            title="Like event"
                                        >
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                                            <span id="like-count" class="portal-reference-like-count">{{ $heartCount }}</span>
                                        </button>
                                    @else
                                        <button type="button" class="portal-reference-transport-btn portal-like" aria-label="Like event" onclick="window.SoundmixAuth?.open({ reason: 'heart' })">
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                                            <span id="like-count" class="portal-reference-like-count">{{ $heartCount }}</span>
                                        </button>
                                    @endauth

                                    <button type="button" class="portal-reference-transport-btn portal-reference-skip" aria-label="Previous item" disabled>
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M6 5h2v14H6V5Zm3 7 9 6V6l-9 6Z"/></svg>
                                    </button>

                                    <div class="portal-reference-player-core">
                                        @include('partials.stage-player', [
                                            'status' => $isLive
                                                ? 'Connecting…'
                                                : ($isPaused
                                                    ? 'Broadcast paused — the host may resume this same event shortly.'
                                                    : 'Waiting for the broadcast to start. This page will keep trying.'),
                                            'disabled' => ! $isLive,
                                        ])
                                    </div>

                                    <button type="button" class="portal-reference-transport-btn portal-reference-skip" aria-label="Next item" disabled>
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M16 5h2v14h-2V5Zm-10 1 9 6-9 6V6Z"/></svg>
                                    </button>

                                    <button type="button" class="portal-reference-transport-btn portal-reference-more" aria-label="More event actions" data-portal-more-toggle>
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="5" cy="12" r="1.8" fill="currentColor"/><circle cx="12" cy="12" r="1.8" fill="currentColor"/><circle cx="19" cy="12" r="1.8" fill="currentColor"/></svg>
                                    </button>
                                </div>

                                <div
                                    id="listen-root"
                                    data-hls-url="{{ $hlsUrl }}"
                                    data-whep-url="{{ $whepUrl }}"
                                    data-prefer-hls="{{ ! empty($preferHls) ? '1' : '0' }}"
                                    data-stream-status="{{ $isLive ? 'live' : ($isPaused ? 'paused' : 'offline') }}"
                                    data-status-url="{{ route('events.status', $event) }}"
                                    @if ($event->stream)
                                        data-gallery-url="{{ route('gallery.index', ['stream' => $event->stream, 'event_id' => $event->id]) }}"
                                    @endif
                                    data-title="{{ $event->title }}"
                                    data-artist="{{ $event->organization->name }}"
                                    data-artwork="{{ $event->artworkUrl() ?: ($artwork ?: $cardArt) }}"
                                    @if ($scriptureUrl)
                                        data-scripture-url="{{ $scriptureUrl }}"
                                    @endif
                                    class="hidden"
                                ></div>
                            @endif
                        </div>

                        <a href="#portal-secondary" class="portal-scroll-cue portal-reference-scroll-cue" aria-label="Scroll to event community and details">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
                            <span>Scroll for more</span>
                        </a>
                    </div>
                </div>
            </section>

            {{-- Secondary actions intentionally live below the player so playback remains the first-screen priority. --}}
            <div class="portal-secondary-actions" aria-label="Event actions">
                @auth
                    <button
                        type="button"
                        id="btn-follow"
                        class="portal-secondary-action {{ $isFollowing ? 'is-following' : '' }}"
                        data-follow-url="{{ route('channels.follow', $event->organization) }}"
                        data-unfollow-url="{{ route('channels.unfollow', $event->organization) }}"
                        data-following="{{ $isFollowing ? '1' : '0' }}"
                    >{{ $isFollowing ? 'Following' : '+ Follow' }}</button>
                @else
                    <button type="button" class="portal-secondary-action" onclick="window.SoundmixAuth?.open({ reason: 'follow' })">+ Follow</button>
                @endauth

                <button type="button" id="btn-share" class="portal-secondary-action" data-share-url="{{ $shareUrl }}" data-share-title="{{ $event->title }}">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><path d="m8.6 13.5 6.8 4M15.4 6.5l-6.8 4"/></svg>
                    <span>Share</span>
                </button>

                @include('partials.give-online', ['organization' => $event->organization])
            </div>

            {{-- 2. CALM SCROLL TRANSITION TO SECONDARY CONTENT BELOW THE FOLD --}}
            <div class="listen-scroll-divider" id="portal-secondary">
                <span class="listen-divider-line" aria-hidden="true"></span>
                <span class="listen-divider-pill">Event Community & Content</span>
                <span class="listen-divider-line" aria-hidden="true"></span>
            </div>

            {{-- 3. BELOW-THE-FOLD SECONDARY CONTENT: ACCORDION / DISCLOSURE PATTERN --}}
            <div class="portal-secondary-content" id="portal-secondary-content">
                <div class="listen-accordion-stack" id="portal-cards" data-active-tab="{{ $event->chat_enabled ? 'chat' : ($scriptureEnabled ? 'scripture' : 'about') }}">

                    {{-- ACCORDION ITEM 1: LIVE CHAT --}}
                    @if ($event->chat_enabled)
                        <div class="listen-accordion-item is-open" id="accordion-item-chat">
                            <button
                                type="button"
                                class="listen-accordion-trigger"
                                data-accordion-tab="chat"
                                aria-expanded="true"
                                aria-controls="panel-chat"
                            >
                                <div class="accordion-trigger-left">
                                    <svg class="accordion-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                    </svg>
                                    <span class="accordion-title">Live Chat</span>
                                </div>
                                <div class="accordion-trigger-right">
                                    <span class="accordion-badge accordion-badge--online">
                                        <span class="live-dot" aria-hidden="true"></span>
                                        Online
                                    </span>
                                    <svg class="accordion-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </div>
                            </button>
                            <div class="listen-accordion-panel" id="panel-chat" role="region" aria-label="Live chat conversation">
                                <div class="portal-card stage-rail wa-chat portal-chat-slot" id="portal-chat" aria-label="Live chat">
                                    <header class="wa-header">
                                        @if ($artwork)
                                            <img src="{{ $artwork }}" alt="" class="wa-header-avatar">
                                        @else
                                            <span class="wa-header-avatar" aria-hidden="true">{{ strtoupper(substr($event->organization->name, 0, 1)) }}</span>
                                        @endif
                                        <div class="wa-header-copy">
                                            <h2>Live Chat</h2>
                                            <p>{{ $event->organization->name }} · online now</p>
                                        </div>
                                    </header>
                                    <div id="chat-messages" class="wa-messages" aria-live="polite">
                                        <p class="wa-empty">Say hello — the room is listening.</p>
                                    </div>
                                    <form id="chat-form" class="wa-composer stage-chat-input" data-authenticated="{{ auth()->check() ? '1' : '0' }}">
                                        <input id="chat-body" type="text" maxlength="500" placeholder="{{ auth()->check() ? 'Type a message' : 'Sign in to comment...' }}" autocomplete="off" required>
                                        <button type="submit" aria-label="Send">Send</button>
                                    </form>
                                    @guest
                                        <p class="wa-login text-xs mt-2 text-zinc-400">
                                            <button type="button" class="text-emerald-400 hover:underline font-medium" onclick="window.SoundmixAuth?.open({ reason: 'chat' })">Sign in</button> to join the conversation
                                        </p>
                                    @endguest
                                    <div id="chat-root" class="hidden"
                                        data-poll-url="{{ route('events.chat.index', $event) }}"
                                        data-post-url="{{ route('events.chat.store', $event) }}"
                                        data-self-name="{{ auth()->user()?->name }}"></div>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- ACCORDION ITEM 2: SCRIPTURE (CONTEXTUAL: ONLY CHURCH CREATOR TYPE) --}}
                    @if ($scriptureEnabled)
                        <div class="listen-accordion-item {{ ! $event->chat_enabled ? 'is-open' : '' }}" id="accordion-item-scripture">
                            <button
                                type="button"
                                class="listen-accordion-trigger"
                                data-accordion-tab="scripture"
                                aria-expanded="{{ ! $event->chat_enabled ? 'true' : 'false' }}"
                                aria-controls="panel-scripture"
                            >
                                <div class="accordion-trigger-left">
                                    <svg class="accordion-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                                    </svg>
                                    <span class="accordion-title">Scripture</span>
                                    <span id="scripture-tab-dot" class="scripture-tab-dot {{ $liveBoard ? '' : 'hidden' }}" aria-label="Active scripture dot"></span>
                                </div>
                                <div class="accordion-trigger-right">
                                    <span id="scripture-accordion-badge" class="accordion-badge {{ filled($event->scripture_ref) ? '' : 'hidden' }}">
                                        {{ $event->scripture_ref }}
                                    </span>
                                    <svg class="accordion-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </div>
                            </button>
                            <div class="listen-accordion-panel" id="panel-scripture" role="region" aria-label="Scripture & Live Board" @if ($event->chat_enabled) hidden @endif>
                                <div class="portal-card portal-scripture-slot portal-art{{ $liveBoard === 'song' ? ' has-song' : ($liveBoard === 'ndi' ? ' has-ndi' : ($liveBoard === 'scripture' ? ' has-scripture' : '')) }}" role="region" aria-label="Scripture & Live Board">
                                    <div class="scripture-panel-head">
                                        <span class="scripture-panel-kicker">Scripture & Live Board</span>
                                    </div>
                                    <div id="scripture-slide" class="scripture-slide" @if ($liveBoard !== 'scripture') hidden @endif>
                                        <div class="scripture-body">
                                            <div class="scripture-body-inner">
                                                <div class="scripture-heading">
                                                    <p class="scripture-board-name">Scripture Board</p>
                                                    <p class="scripture-ref">{{ $event->scripture_ref }}<span class="scripture-version"> (KJV)</span></p>
                                                </div>
                                                <p class="scripture-text">{{ $event->scripture_text }}</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div id="song-slide" class="scripture-slide song-slide" @if ($liveBoard !== 'song') hidden @endif>
                                        <div class="scripture-body">
                                            <div class="scripture-body-inner">
                                                <div class="scripture-heading">
                                                    <p class="scripture-board-name">Live Board</p>
                                                    <p class="scripture-ref">{{ $event->song_title }}</p>
                                                    <p class="song-slide-meta">
                                                        @if (filled($event->song_title) && (int) ($event->song_slide_count ?? 1) > 1)
                                                            {{ ((int) ($event->song_slide_index ?? 0)) + 1 }} / {{ (int) $event->song_slide_count }}
                                                        @endif
                                                    </p>
                                                </div>
                                                <p class="scripture-text">{{ $event->song_text }}</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div id="ndi-slide" class="ndi-slide" @if ($liveBoard !== 'ndi') hidden @endif>
                                        @php $ndi = $event->liveNdiPayload(); @endphp
                                        <img id="ndi-slide-image" alt="Stage display" @if ($ndi) src="{{ $ndi['image_url'] }}" @endif>
                                        <p id="ndi-slide-source" class="ndi-slide-source">{{ $ndi['source'] ?? '' }}</p>
                                    </div>
                                    <div id="scripture-empty-state" class="scripture-empty-state @if ($liveBoard) hidden @endif">
                                        <svg viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="1.6" class="scripture-empty-icon" aria-hidden="true"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
                                        <p class="scripture-empty-text">No active scripture reading.</p>
                                        <p class="scripture-empty-sub">Verses and songs shared by the speaker will appear here in real time.</p>
                                    </div>
                                </div>
                                @if ($scriptureUrl)
                                    <div id="scripture-poll-root" class="hidden" data-scripture-url="{{ $scriptureUrl }}"></div>
                                @endif
                            </div>
                        </div>
                    @endif

                    {{-- ACCORDION ITEM 3: LIVE GALLERY --}}
                    @if ($event->stream)
                        <div class="listen-accordion-item" id="accordion-item-gallery">
                            <button
                                type="button"
                                class="listen-accordion-trigger"
                                data-accordion-tab="gallery"
                                aria-expanded="false"
                                aria-controls="panel-gallery"
                            >
                                <div class="accordion-trigger-left">
                                    <svg class="accordion-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    <span class="accordion-title">Live Gallery</span>
                                </div>
                                <div class="accordion-trigger-right">
                                    @if ($galleryImages && $galleryImages->count() > 0)
                                        <span class="accordion-badge">{{ $galleryImages->count() }}</span>
                                    @endif
                                    <svg class="accordion-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </div>
                            </button>
                            <div class="listen-accordion-panel" id="panel-gallery" role="region" aria-label="Event gallery" hidden>
                                <section class="portal-card portal-gallery" aria-label="Event gallery">
                                    <div class="portal-section-head portal-section-head--side">
                                        <h2>Photos & Video Reels</h2>
                                        <p>Tap any item to open full screen</p>
                                    </div>
                                    <div class="portal-gallery-grid" id="gallery-grid">
                                        @include('partials.gallery-items', ['galleryImages' => $galleryImages])
                                    </div>
                                </section>
                            </div>
                        </div>
                    @endif

                    {{-- ACCORDION ITEM 4: ABOUT --}}
                    <div class="listen-accordion-item {{ (! $event->chat_enabled && ! $scriptureEnabled) ? 'is-open' : '' }}" id="accordion-item-about">
                        <button
                            type="button"
                            class="listen-accordion-trigger"
                            data-accordion-tab="about"
                            aria-expanded="{{ (! $event->chat_enabled && ! $scriptureEnabled) ? 'true' : 'false' }}"
                            aria-controls="panel-about"
                        >
                            <div class="accordion-trigger-left">
                                <svg class="accordion-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span class="accordion-title">About</span>
                            </div>
                            <div class="accordion-trigger-right">
                                <svg class="accordion-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </button>
                        <div class="listen-accordion-panel" id="panel-about" role="region" aria-label="About broadcaster" @if ($event->chat_enabled || $scriptureEnabled) hidden @endif>
                            <div class="portal-card portal-about-slot" aria-label="About broadcaster">
                                <div class="portal-about-inner">
                                    <div class="portal-about-head">
                                        @if ($artwork)
                                            <img src="{{ $artwork }}" alt="" class="portal-about-avatar">
                                        @else
                                            <span class="portal-about-avatar portal-avatar--fallback" aria-hidden="true">{{ strtoupper(substr($event->organization->name, 0, 1)) }}</span>
                                        @endif
                                        <div>
                                            <h3 class="portal-about-name">{{ $event->organization->name }}</h3>
                                            <p class="portal-about-kicker">Live Broadcaster on SoundMix</p>
                                        </div>
                                    </div>
                                    <h4 class="portal-about-event-title">{{ $event->title }}</h4>
                                    <p class="portal-about-bio">
                                        {{ $event->description ?: ($event->organization->bio ?: 'Welcome to our live audio broadcast. Tune in to listen live from anywhere.') }}
                                    </p>
                                    @if ($event->organization->social_feed_url)
                                        <div class="portal-about-social">
                                            <a class="portal-about-social-link" href="{{ $event->organization->social_feed_url }}" target="_blank" rel="noopener noreferrer">
                                                <span>Social photo feed</span>
                                                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                                            </a>
                                        </div>
                                    @endif
                                    <div class="portal-about-actions">
                                        <a href="{{ $event->organization->channelUrl() }}" class="portal-about-btn">
                                            Visit Channel Archive
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <div id="engage-root" class="hidden"
                data-presence-url="{{ route('events.presence', $event) }}"
                data-like-url="{{ route('events.heart', $event) }}"
                data-csrf="{{ csrf_token() }}"></div>

            {{-- Mobile Sticky Player --}}
            <div id="mobile-sticky-player" class="soundmix-sticky-player" aria-label="Mobile audio player">
                <div class="sticky-player-body">
                    <div class="sticky-player-avatar">
                        @if ($artwork)
                            <img src="{{ $artwork }}" alt="" class="sticky-player-thumb">
                        @else
                            <span class="sticky-player-thumb sticky-player-thumb--fallback" aria-hidden="true">{{ strtoupper(substr($event->organization->name, 0, 1)) }}</span>
                        @endif
                    </div>
                    <div class="sticky-player-meta">
                        <p class="sticky-player-title">{{ $event->title }}</p>
                        <p class="sticky-player-status">
                            <span class="live-dot {{ $isLive ? 'is-live' : ($isPaused ? 'is-paused' : '') }}" aria-hidden="true"></span>
                            <span>{{ $statusLabel }}</span>
                            <span class="sticky-player-dot">·</span>
                            <span class="sticky-player-channel">{{ $event->organization->name }}</span>
                        </p>
                    </div>
                    <div class="sticky-player-actions">
                        <button type="button" id="sticky-btn-play" class="sticky-btn sticky-btn--play" aria-label="Play or pause">
                            <svg class="icon-play" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M8 5.14v13.72a1 1 0 0 0 1.5.86l11-6.86a1 1 0 0 0 0-1.72l-11-6.86a1 1 0 0 0-1.5.86z"/></svg>
                            <svg class="icon-pause hidden" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
                        </button>
                        <button type="button" id="sticky-btn-volume" class="sticky-btn sticky-btn--vol" aria-label="Toggle mute">
                            <svg class="icon-volume" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M3 10v4h4l5 4V6L7 10H3zm13.5 2a3.5 3.5 0 0 0-1.8-3.1v6.2A3.5 3.5 0 0 0 16.5 12zM14 4.7v2.1a5.5 5.5 0 0 1 0 10.4v2.1a7.5 7.5 0 0 0 0-14.6z"/></svg>
                            <svg class="icon-muted hidden" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M16.5 12a3.5 3.5 0 0 0-1.8-3.1v2.36l1.75 1.75c.03-.33.05-.67.05-1.01zm2.5 0c0 .94-.2 1.82-.54 2.64l1.51 1.51A8.8 8.8 0 0 0 21 12c0-3.53-2.04-6.55-5-7.97v2.21A5.5 5.5 0 0 1 19 12zM4.27 3 3 4.27 7.73 9H3v6h4l5 5v-6.73l4.25 4.25c-.67.52-1.42.93-2.25 1.18v2.14a8.94 8.94 0 0 0 3.69-1.81L19.73 21 21 19.73l-9-9L4.27 3zM12 4 9.91 6.09 12 8.18V4z"/></svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <x-listener-auth-sheet />
@endsection

@section('whatsapp_widget')
    <x-whatsapp-support
        context="listener"
        :organisation="$event->organization->name"
        :eventTitle="$event->title"
        pageLabel="Event: {{ $event->title }}"
    />
@endsection
