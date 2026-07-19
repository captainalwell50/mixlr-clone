@extends('layouts.stream')

@section('title', $event->title.' · '.$event->organization->name)

@section('vite')
    @vite(['resources/js/listen.js', 'resources/js/chat.js', 'resources/js/event-engage.js'])
@endsection

@php
    $theme = $event->organization->themeColor();
    $isLive = $event->isLive();
    $artwork = $event->artworkUrl();
    $stageArt = $artwork ?: asset('images/listen-stage-bg.jpg');
    $statusLabel = match ($event->status->value) {
        'live' => 'Live',
        'ended' => 'Ended',
        default => 'Scheduled',
    };
@endphp

@section('content')
    <div class="stage stage-cinema" style="--stage-accent: {{ $theme }}; --stage-art: url('{{ $stageArt }}')">
        <div class="stage-atmosphere has-art" aria-hidden="true"></div>

        <div class="stage-shell">
            <header class="stage-bar stage-rise">
                <a href="{{ url('/') }}" class="stage-platform">{{ config('app.name', 'Live Mix Audio') }}</a>
                <nav class="stage-bar-links">
                    <a href="{{ route('channels.show', $event->organization) }}" class="stage-top-link">Channel</a>
                    <a href="{{ route('discover') }}" class="stage-top-link">Discover</a>
                </nav>
            </header>

            <div class="stage-layout {{ $event->chat_enabled ? 'has-chat' : '' }}">
                <main class="stage-main">
                    <p id="broadcast-badge" class="stage-status stage-rise-delay {{ $isLive ? '' : 'is-idle' }}">
                        @if ($isLive)
                            <span class="live-dot inline-block h-1.5 w-1.5 rounded-full bg-current"></span>
                        @endif
                        {{ $statusLabel }}
                    </p>

                    <h1 class="stage-channel stage-rise-delay">
                        <a href="{{ route('channels.show', $event->organization) }}">{{ $event->organization->name }}</a>
                    </h1>
                    <p class="stage-title stage-rise-delay-2">{{ $event->title }}</p>

                    @if ($event->scheduled_at && ! $isLive && $event->status->value !== 'ended')
                        <p class="stage-meta">{{ $event->scheduled_at->timezone(config('app.timezone'))->format('D, M j · g:i A T') }}</p>
                    @endif

                    @if ($isLive && ($whepUrl || $hlsUrl))
                        @include('partials.stage-player', ['status' => 'Connecting…'])
                        <div
                            id="listen-root"
                            data-hls-url="{{ $hlsUrl }}"
                            data-whep-url="{{ $whepUrl }}"
                            data-stream-status="live"
                            data-status-url="{{ route('events.status', $event) }}"
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

                    <div class="stage-presence stage-rise-delay-2">
                        @if ($event->show_listener_count)
                            <span><span id="listener-count">{{ $listenerCount ?? 0 }}</span> listening</span>
                        @endif
                        <span><span id="heart-count">{{ $heartCount }}</span> hearts</span>
                        @auth
                            <button type="button" id="btn-heart"
                                class="stage-heart {{ $userHearted ? 'is-on' : '' }}"
                                data-hearted="{{ $userHearted ? '1' : '0' }}">
                                {{ $userHearted ? '♥ Hearted' : '♡ Heart' }}
                            </button>
                        @else
                            <a href="{{ route('login') }}" class="stage-top-link">Log in to heart</a>
                        @endauth
                    </div>
                </main>

                @if ($event->chat_enabled)
                    <aside class="stage-rail stage-rise-delay-2" aria-label="Live chat">
                        <h2 class="stage-chat-label">Chat</h2>
                        <div id="chat-messages" class="stage-chat-messages"></div>
                        @auth
                            <form id="chat-form" class="stage-chat-input">
                                <input id="chat-body" type="text" maxlength="500" placeholder="Say something…" required>
                                <button type="submit">Send</button>
                            </form>
                        @else
                            <p class="stage-meta mt-3"><a href="{{ route('login') }}" style="color: var(--stage-accent)">Log in</a> to join the chat.</p>
                        @endauth
                        <div id="chat-root" class="hidden"
                            data-poll-url="{{ route('events.chat.index', $event) }}"
                            data-post-url="{{ route('events.chat.store', $event) }}"></div>
                    </aside>
                @endif
            </div>

            <div id="engage-root" class="hidden"
                data-presence-url="{{ route('events.presence', $event) }}"
                data-heart-url="{{ route('events.heart', $event) }}"
                data-csrf="{{ csrf_token() }}"></div>
        </div>
    </div>
@endsection
