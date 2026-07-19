@extends('layouts.stream')

@section('title', $stream->title.' · '.$organization->name)

@section('vite')
    @vite(['resources/js/listen.js', 'resources/js/chat.js'])
@endsection

@php
    $isLive = $stream->status === \App\Enums\StreamStatus::Live;
    $theme = $organization->themeColor();
    $artwork = $organization->artworkUrl();
    $stageArt = $artwork ?: asset('images/listen-stage-bg.jpg');
@endphp

@section('content')
    <div class="stage stage-cinema" style="--stage-accent: {{ $theme }}; --stage-art: url('{{ $stageArt }}')">
        <div class="stage-atmosphere has-art" aria-hidden="true"></div>

        <div class="stage-shell">
            <header class="stage-bar stage-rise">
                <a href="{{ url('/') }}" class="stage-platform">{{ config('app.name', 'Live Mix Audio') }}</a>
                <nav class="stage-bar-links">
                    <a href="{{ route('channels.show', $organization) }}" class="stage-top-link">Channel</a>
                    <a href="{{ route('discover') }}" class="stage-top-link">Discover</a>
                </nav>
            </header>

            <div class="stage-layout {{ $stream->chat_enabled ? 'has-chat' : '' }}">
                <main class="stage-main">
                    <p id="broadcast-badge" class="stage-status stage-rise-delay {{ $isLive ? '' : 'is-idle' }}">
                        @if ($isLive)
                            <span class="live-dot inline-block h-1.5 w-1.5 rounded-full bg-current"></span>
                        @endif
                        {{ $isLive ? 'Live' : 'Offline' }}
                    </p>

                    <h1 class="stage-channel stage-rise-delay">
                        <a href="{{ route('channels.show', $organization) }}">{{ $organization->name }}</a>
                    </h1>
                    <p class="stage-title stage-rise-delay-2">{{ $stream->title }}</p>

                    @include('partials.stage-player', [
                        'status' => $isLive ? 'Connecting…' : 'Waiting for the broadcast to start. This page will keep trying.',
                        'disabled' => ! $isLive,
                    ])

                    <div
                        id="listen-root"
                        data-hls-url="{{ $hlsUrl }}"
                        data-whep-url="{{ $whepUrl }}"
                        data-stream-status="{{ $stream->status->value }}"
                        data-status-url="{{ route('listen.status', $stream) }}"
                        class="hidden"
                    ></div>
                </main>

                @if ($stream->chat_enabled)
                    <aside class="stage-rail stage-rise-delay-2" aria-label="Live chat">
                        <h2 class="stage-chat-label">Chat</h2>
                        <div id="chat-messages" class="stage-chat-messages"></div>
                        <form id="chat-form" class="stage-chat-form">
                            @guest
                                <input id="chat-name" type="text" maxlength="80" placeholder="Your name" required
                                    class="stage-chat-name">
                            @endguest
                            <div class="stage-chat-input">
                                <input id="chat-body" type="text" maxlength="500" placeholder="Say something…" required>
                                <button type="submit">Send</button>
                            </div>
                        </form>
                        <div
                            id="chat-root"
                            class="hidden"
                            data-poll-url="{{ route('chat.index', $stream) }}"
                            data-post-url="{{ route('chat.store', $stream) }}"
                        ></div>
                    </aside>
                @endif
            </div>
        </div>
    </div>
@endsection
