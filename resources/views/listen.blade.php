@extends('layouts.stream')

@section('title', $stream->title.' · '.$organization->name)

@section('vite')
    @vite(['resources/js/listen.js', 'resources/js/chat.js'])
@endsection

@php
    $isLive = $stream->status === \App\Enums\StreamStatus::Live;
    $theme = $organization->themeColor();
    $artwork = $organization->artworkUrl();
@endphp

@section('content')
    <div class="stage" style="--stage-accent: {{ $theme }};">
        <div
            class="stage-atmosphere {{ $artwork ? 'has-art' : '' }}"
            @if ($artwork) style="--stage-art: url('{{ $artwork }}')" @endif
        ></div>

        <div class="stage-content">
            <header class="stage-top stage-rise">
                <p class="stage-platform">{{ config('app.name', 'Live Mix Audio') }}</p>
                <a href="{{ route('discover') }}" class="stage-top-link">Discover</a>
            </header>

            <p class="stage-status stage-rise-delay {{ $isLive ? '' : 'is-idle' }}">
                @if ($isLive)
                    <span class="live-dot inline-block h-1.5 w-1.5 rounded-full bg-current"></span>
                @endif
                {{ $isLive ? 'Live' : 'Offline' }}
            </p>

            <h1 class="stage-channel stage-rise-delay">
                <a href="{{ route('channels.show', $organization) }}">{{ $organization->name }}</a>
            </h1>
            <p class="stage-title stage-rise-delay-2">{{ $stream->title }}</p>
            @if ($stream->description)
                <p class="stage-meta">{{ $stream->description }}</p>
            @endif

            @include('partials.stage-player', [
                'status' => $isLive ? 'Connecting…' : 'Waiting for the broadcast to start. This page will keep trying.',
                'disabled' => ! $isLive,
            ])

            <div
                id="listen-root"
                data-hls-url="{{ $hlsUrl }}"
                data-stream-status="{{ $stream->status->value }}"
                class="hidden"
            ></div>

            @if ($stream->chat_enabled)
                <section class="stage-chat">
                    <div class="stage-chat-panel">
                        <h2 class="stage-chat-label">Chat</h2>
                        <div id="chat-messages" class="stage-chat-messages"></div>
                        <form id="chat-form" class="mt-3 flex flex-col gap-2">
                            @guest
                                <input id="chat-name" type="text" maxlength="80" placeholder="Your name" required
                                    class="rounded-[0.65rem] border border-white/15 bg-black/40 px-3 py-2 text-sm text-[var(--stage-cream)]">
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
                    </div>
                </section>
            @endif
        </div>
    </div>
@endsection
