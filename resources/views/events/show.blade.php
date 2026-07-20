@extends('layouts.stream')

@section('title', $event->title.' · '.$event->organization->name)

@section('vite')
    @vite(['resources/js/listen.js', 'resources/js/chat.js', 'resources/js/event-engage.js'])
@endsection

@php
    $theme = $event->organization->themeColor();
    $isLive = $event->isLive();
    $artwork = $event->organization->artworkUrl();
    $background = ($listenBackgroundUrl ?? null) ?: ($event->artworkUrl() ?: asset('images/listen-stage-bg.jpg'));
    $cardArt = $artwork ?: $background;
    $statusLabel = match ($event->status->value) {
        'live' => 'Live',
        'ended' => 'Ended',
        default => 'Scheduled',
    };
    $shareUrl = route('events.show', $event);
@endphp

@section('content')
    <div class="stage stage-cinema portal" style="--stage-accent: {{ $theme }}; --stage-art: url('{{ $background }}')">
        <div class="stage-atmosphere has-art is-fullscreen" aria-hidden="true"></div>

        <div class="stage-shell">
            <header class="portal-bar stage-rise">
                <div class="portal-brand">
                    <a href="{{ route('channels.show', $event->organization) }}" class="portal-channel-link">
                        @if ($event->organization->artworkUrl())
                            <img src="{{ $event->organization->artworkUrl() }}" alt="" class="portal-avatar">
                        @else
                            <span class="portal-avatar portal-avatar--fallback" aria-hidden="true">{{ strtoupper(substr($event->organization->name, 0, 1)) }}</span>
                        @endif
                        <span class="portal-channel-name">{{ $event->organization->name }}</span>
                    </a>
                </div>
                <nav class="portal-bar-links">
                    <a href="{{ route('discover') }}" class="stage-top-link">Discover</a>
                    @auth
                        <a href="{{ route('dashboard') }}" class="stage-top-link">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="stage-top-link">Log in</a>
                    @endauth
                </nav>
            </header>

            <div class="portal-layout" id="portal-layout">
                <div class="portal-cards stage-rise-delay">
                    <div class="portal-card portal-art" style="background-image: url('{{ $cardArt }}')" role="img" aria-label="Channel artwork"></div>

                    @if ($event->chat_enabled)
                        <div class="portal-card stage-rail wa-chat portal-chat-slot" id="portal-chat" aria-label="Live chat">
                            <header class="wa-header">
                                @if ($artwork)
                                    <img src="{{ $artwork }}" alt="" class="wa-header-avatar">
                                @else
                                    <span class="wa-header-avatar" aria-hidden="true">{{ strtoupper(substr($event->organization->name, 0, 1)) }}</span>
                                @endif
                                <div class="wa-header-copy">
                                    <h2>{{ $event->organization->name }}</h2>
                                    <p>Live chat · online now</p>
                                </div>
                            </header>
                            <div id="chat-messages" class="wa-messages" aria-live="polite">
                                <p class="wa-empty">Say hello — the room is listening.</p>
                            </div>
                            @auth
                                <form id="chat-form" class="wa-composer stage-chat-input">
                                    <input id="chat-body" type="text" maxlength="500" placeholder="Type a message" autocomplete="off" required>
                                    <button type="submit" aria-label="Send">Send</button>
                                </form>
                            @else
                                <p class="wa-login">
                                    <a href="{{ route('login') }}">Log in</a> to join the conversation
                                </p>
                            @endauth
                            <div id="chat-root" class="hidden"
                                data-poll-url="{{ route('events.chat.index', $event) }}"
                                data-post-url="{{ route('events.chat.store', $event) }}"
                                data-self-name="{{ auth()->user()?->name }}"></div>
                        </div>
                    @else
                        <div class="portal-card portal-chat-off" aria-label="Chat unavailable">
                            <p>Chat is off for this broadcast.</p>
                        </div>
                    @endif

                    <section class="portal-card portal-gallery" aria-label="Service gallery">
                        <div class="portal-section-head portal-section-head--side">
                            <h2>Live Gallery - Happening Now</h2>
                            <p>Photos & video reels · tap to open</p>
                        </div>
                        <div class="portal-gallery-grid" id="gallery-grid">
                            @include('partials.gallery-items', ['galleryImages' => $galleryImages])
                        </div>
                    </section>
                </div>

                <div class="portal-below">
                    <h1 class="portal-title stage-rise-delay">{{ $event->title }}</h1>

                    <div class="portal-badges stage-rise-delay-2">
                        <span id="broadcast-badge" class="portal-badge {{ $isLive ? 'is-live' : 'is-idle' }}">
                            @if ($isLive)
                                <span class="live-dot" aria-hidden="true"></span>
                            @endif
                            {{ $statusLabel }}
                        </span>
                        <span class="portal-badge portal-badge--soft">Event</span>
                    </div>

                    @if ($event->scheduled_at && ! $isLive && $event->status->value !== 'ended')
                        <p class="portal-schedule">{{ $event->scheduled_at->timezone(config('app.timezone'))->format('D, M j · g:i A T') }}</p>
                    @endif

                    <div class="portal-stats stage-rise-delay-2">
                        @if ($event->show_listener_count)
                            <p class="portal-listeners">
                                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M16 11c1.66 0 3-1.34 3-3S17.66 5 16 5s-3 1.34-3 3 1.34 3 3 3zm-8 0c1.66 0 3-1.34 3-3S9.66 5 8 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5C15 14.17 10.33 13 8 13zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                                <span><span id="listener-count">{{ $listenerCount ?? 0 }}</span> Listeners</span>
                            </p>
                        @endif
                    </div>

                    <div class="portal-engage stage-rise-delay-2">
                        @auth
                            <button
                                type="button"
                                id="btn-like"
                                class="portal-like {{ $userHearted ? 'is-on' : '' }}"
                                data-liked="{{ $userHearted ? '1' : '0' }}"
                                aria-label="Like"
                            >
                                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                                <span id="like-count">{{ $heartCount }}</span>
                            </button>
                            <button
                                type="button"
                                id="btn-follow"
                                class="portal-follow {{ $isFollowing ? 'is-following' : '' }}"
                                data-follow-url="{{ route('channels.follow', $event->organization) }}"
                                data-unfollow-url="{{ route('channels.unfollow', $event->organization) }}"
                                data-following="{{ $isFollowing ? '1' : '0' }}"
                            >
                                {{ $isFollowing ? 'Following' : '+ Follow' }}
                            </button>
                        @else
                            <a href="{{ route('login') }}" class="portal-like" aria-label="Log in to like">
                                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                                <span id="like-count">{{ $heartCount }}</span>
                            </a>
                            <a href="{{ route('login') }}" class="portal-follow">+ Follow</a>
                        @endauth

                        <button type="button" id="btn-share" class="portal-icon-btn" aria-label="Share" title="Share" data-share-url="{{ $shareUrl }}" data-share-title="{{ $event->title }}">
                            <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7a3.27 3.27 0 0 0 0-1.39l7.02-4.11A2.99 2.99 0 1 0 14 5c0 .17.02.34.05.5L7.04 9.61a3 3 0 1 0 0 4.78l7.12 4.16c-.03.14-.05.29-.05.45a3 3 0 1 0 3-3z"/></svg>
                        </button>

                        <button type="button" id="btn-info" class="portal-icon-btn" aria-label="Event info" title="Info" aria-expanded="false" aria-controls="portal-info">
                            <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M11 7h2v2h-2V7zm0 4h2v6h-2v-6zm1-9C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/></svg>
                        </button>
                    </div>

                    <div id="portal-info" class="portal-info" hidden>
                        <p>{{ $event->description ?: 'Live audio event on '.$event->organization->name.'.' }}</p>
                        <p class="portal-info-meta">
                            <a href="{{ route('channels.show', $event->organization) }}">{{ $event->organization->name }}</a>
                            · {{ $shareUrl }}
                        </p>
                    </div>

                    @if ($isLive && ($whepUrl || $hlsUrl))
                        @include('partials.stage-player', ['status' => 'Connecting…'])
                        <div
                            id="listen-root"
                            data-hls-url="{{ $hlsUrl }}"
                            data-whep-url="{{ $whepUrl }}"
                            data-stream-status="live"
                            data-status-url="{{ route('events.status', $event) }}"
                            @if ($event->stream)
                                data-gallery-url="{{ route('gallery.index', $event->stream) }}"
                            @endif
                            class="hidden"
                        ></div>
                    @elseif ($event->status->value === 'ended')
                        <p class="stage-waiting stage-rise-delay-2">
                            This event has ended.
                            <a href="{{ route('channels.show', $event->organization) }}">Browse the channel</a>
                            for recordings.
                        </p>
                    @else
                        <p class="stage-waiting stage-rise-delay-2">
                            Waiting for the broadcast to start.
                        </p>
                        <meta http-equiv="refresh" content="30">
                    @endif

                    @if ($event->organization->social_feed_url)
                        <section class="portal-social stage-rise-delay-2">
                            <div class="portal-section-head">
                                <h2>Social photos</h2>
                                <p>Posts from this service</p>
                            </div>
                            <a class="portal-social-card" href="{{ $event->organization->social_feed_url }}" target="_blank" rel="noopener noreferrer">
                                <span>Open photo feed</span>
                                <span class="portal-social-url">{{ $event->organization->social_feed_url }}</span>
                            </a>
                        </section>
                    @endif
                </div>
            </div>

            <div id="engage-root" class="hidden"
                data-presence-url="{{ route('events.presence', $event) }}"
                data-like-url="{{ route('events.heart', $event) }}"
                data-csrf="{{ csrf_token() }}"></div>
        </div>
    </div>
@endsection
