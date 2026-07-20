@extends('layouts.stream')

@section('title', $stream->title.' · '.$organization->name)

@section('vite')
    @vite(['resources/js/listen.js', 'resources/js/chat.js', 'resources/js/event-engage.js'])
@endsection

@php
    $isLive = $stream->status === \App\Enums\StreamStatus::Live;
    $theme = $organization->themeColor();
    $artwork = $organization->artworkUrl();
    $background = $listenBackgroundUrl ?: asset('images/listen-stage-bg.jpg');
    $shareUrl = route('listen.stream', $stream);
@endphp

@section('content')
    <div class="stage stage-cinema portal" style="--stage-accent: {{ $theme }}; --stage-art: url('{{ $background }}')">
        <div class="stage-atmosphere has-art is-fullscreen" aria-hidden="true"></div>

        <div class="stage-shell">
            <header class="portal-bar stage-rise">
                <div class="portal-brand">
                    <a href="{{ route('channels.show', $organization) }}" class="portal-channel-link">
                        @if ($artwork)
                            <img src="{{ $artwork }}" alt="" class="portal-avatar">
                        @else
                            <span class="portal-avatar portal-avatar--fallback" aria-hidden="true">{{ strtoupper(substr($organization->name, 0, 1)) }}</span>
                        @endif
                        <span class="portal-channel-name">{{ $organization->name }}</span>
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

            <div class="portal-layout has-side {{ $stream->chat_enabled ? 'has-chat' : '' }}" id="portal-layout">
                <main class="portal-main">
                    <h1 class="portal-title stage-rise-delay">{{ $stream->title }}</h1>

                    <div class="portal-badges stage-rise-delay-2">
                        <span id="broadcast-badge" class="portal-badge {{ $isLive ? 'is-live' : 'is-idle' }}">
                            @if ($isLive)
                                <span class="live-dot" aria-hidden="true"></span>
                            @endif
                            {{ $isLive ? 'Live' : 'Offline' }}
                        </span>
                        <span class="portal-badge portal-badge--soft">Broadcast</span>
                    </div>

                    <div class="portal-stats stage-rise-delay-2">
                        <p class="portal-listeners">
                            <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M16 11c1.66 0 3-1.34 3-3S17.66 5 16 5s-3 1.34-3 3 1.34 3 3 3zm-8 0c1.66 0 3-1.34 3-3S9.66 5 8 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5C15 14.17 10.33 13 8 13zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                            <span><span id="listener-count">{{ $listenerCount }}</span> Listeners</span>
                        </p>
                    </div>

                    <div class="portal-engage stage-rise-delay-2">
                        @auth
                            <button
                                type="button"
                                id="btn-like"
                                class="portal-like {{ $userLiked ? 'is-on' : '' }}"
                                data-liked="{{ $userLiked ? '1' : '0' }}"
                                aria-label="Like"
                            >
                                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                                <span id="like-count">{{ $likeCount }}</span>
                            </button>
                            <button
                                type="button"
                                id="btn-follow"
                                class="portal-follow {{ $isFollowing ? 'is-following' : '' }}"
                                data-follow-url="{{ route('channels.follow', $organization) }}"
                                data-unfollow-url="{{ route('channels.unfollow', $organization) }}"
                                data-following="{{ $isFollowing ? '1' : '0' }}"
                            >
                                {{ $isFollowing ? 'Following' : '+ Follow' }}
                            </button>
                        @else
                            <a href="{{ route('login') }}" class="portal-like" aria-label="Log in to like">
                                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                                <span id="like-count">{{ $likeCount }}</span>
                            </a>
                            <a href="{{ route('login') }}" class="portal-follow">+ Follow</a>
                        @endauth

                        @if ($stream->chat_enabled)
                            <button type="button" id="btn-chat-toggle" class="portal-icon-btn" aria-label="Open chat" title="Chat">
                                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H5.17L4 17.17V4h16v12z"/></svg>
                            </button>
                        @endif

                        <button type="button" id="btn-share" class="portal-icon-btn" aria-label="Share" title="Share" data-share-url="{{ $shareUrl }}" data-share-title="{{ $stream->title }}">
                            <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7a3.27 3.27 0 0 0 0-1.39l7.02-4.11A2.99 2.99 0 1 0 14 5c0 .17.02.34.05.5L7.04 9.61a3 3 0 1 0 0 4.78l7.12 4.16c-.03.14-.05.29-.05.45a3 3 0 1 0 3-3z"/></svg>
                        </button>

                        <button type="button" id="btn-info" class="portal-icon-btn" aria-label="Info" title="Info" aria-expanded="false" aria-controls="portal-info">
                            <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M11 7h2v2h-2V7zm0 4h2v6h-2v-6zm1-9C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/></svg>
                        </button>
                    </div>

                    <div id="portal-info" class="portal-info" hidden>
                        <p>{{ $stream->description ?: 'Live audio from '.$organization->name.'.' }}</p>
                        <p class="portal-info-meta">
                            Follow the channel to get an email when they go on air.
                        </p>
                    </div>

                    @include('partials.stage-player', [
                        'status' => $isLive ? 'Connecting…' : 'Waiting for the broadcast to start. This page will keep trying.',
                        'disabled' => ! $isLive,
                    ])

                    @if ($organization->social_feed_url)
                        <section class="portal-social stage-rise-delay-2">
                            <div class="portal-section-head">
                                <h2>Social photos</h2>
                                <p>Posts from this service</p>
                            </div>
                            <a class="portal-social-card" href="{{ $organization->social_feed_url }}" target="_blank" rel="noopener noreferrer">
                                <span>Open photo feed</span>
                                <span class="portal-social-url">{{ $organization->social_feed_url }}</span>
                            </a>
                        </section>
                    @endif

                    <div
                        id="listen-root"
                        data-hls-url="{{ $hlsUrl }}"
                        data-whep-url="{{ $whepUrl }}"
                        data-stream-status="{{ $stream->status->value }}"
                        data-status-url="{{ route('listen.status', $stream) }}"
                        data-gallery-url="{{ route('gallery.index', $stream) }}"
                        class="hidden"
                    ></div>
                </main>

                <aside class="portal-side stage-rise-delay-2" aria-label="Gallery and chat">
                    <section class="portal-gallery" aria-label="Service gallery">
                        <div class="portal-section-head portal-section-head--side">
                            <h2>Live Gallery - Happening Now</h2>
                            <p>Tap a photo to open</p>
                        </div>
                        <div class="portal-gallery-grid" id="gallery-grid">
                            @forelse ($galleryImages as $image)
                                <button
                                    type="button"
                                    class="portal-gallery-item"
                                    data-id="{{ $image->id }}"
                                    data-url="{{ $image->url() }}"
                                    data-caption="{{ $image->caption }}"
                                    aria-label="Open gallery photo"
                                >
                                    <img src="{{ $image->url() }}" alt="{{ $image->caption ?: 'Service photo' }}" loading="lazy">
                                    @if ($image->caption)
                                        <span class="portal-gallery-caption">{{ $image->caption }}</span>
                                    @endif
                                </button>
                            @empty
                                <p class="portal-empty" id="gallery-empty">No photos yet — they’ll appear here when the studio posts them.</p>
                            @endforelse
                        </div>
                    </section>

                    @if ($stream->chat_enabled)
                        <div class="stage-rail portal-rail" id="portal-chat" aria-label="Live chat">
                            <div class="portal-rail-head">
                                <h2 class="stage-chat-label">Chat</h2>
                                <button type="button" id="btn-chat-close" class="portal-rail-close" aria-label="Close chat">✕</button>
                            </div>
                            <div id="chat-messages" class="stage-chat-messages"></div>
                            @auth
                                <form id="chat-form" class="stage-chat-input">
                                    <input id="chat-body" type="text" maxlength="500" placeholder="Chat as {{ auth()->user()->name }}…" required>
                                    <button type="submit">Post</button>
                                </form>
                            @else
                                <p class="stage-meta mt-3">
                                    <a href="{{ route('login') }}" style="color: var(--stage-accent)">Log in</a> to join the chat.
                                </p>
                            @endauth
                            <div
                                id="chat-root"
                                class="hidden"
                                data-poll-url="{{ route('chat.index', $stream) }}"
                                data-post-url="{{ route('chat.store', $stream) }}"
                            ></div>
                        </div>
                    @endif
                </aside>
            </div>

            <div id="engage-root" class="hidden"
                data-presence-url="{{ route('listen.presence', $stream) }}"
                data-like-url="{{ route('listen.like', $stream) }}"
                data-csrf="{{ csrf_token() }}"></div>
        </div>
    </div>
@endsection
