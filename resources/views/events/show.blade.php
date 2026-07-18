@extends('layouts.stream')

@section('title', $event->title.' · '.$event->organization->name)

@section('vite')
    @vite(['resources/js/listen.js', 'resources/js/chat.js', 'resources/js/event-engage.js'])
@endsection

@php
    $theme = $event->organization->themeColor();
    $isLive = $event->isLive();
@endphp

@section('content')
    <style>
        .ev-accent { color: {{ $theme }}; }
        .ev-accent-bg { background-color: {{ $theme }}; }
    </style>

    <div>
        <p class="text-xs font-medium uppercase tracking-wide {{ $isLive ? 'ev-accent' : 'text-zinc-500' }}">
            @if ($isLive)
                <span class="mr-1.5 inline-block h-1.5 w-1.5 rounded-full bg-emerald-400 align-middle"></span>
                Live
            @elseif ($event->status->value === 'ended')
                Ended
            @else
                Scheduled
            @endif
        </p>
        <h1 class="mt-1 text-2xl font-semibold text-white">{{ $event->title }}</h1>
        <p class="mt-1 text-sm text-zinc-400">
            <a href="{{ route('channels.show', $event->organization) }}" class="hover:text-white">{{ $event->organization->name }}</a>
        </p>
        @if ($event->description)
            <p class="mt-2 text-sm text-zinc-500">{{ $event->description }}</p>
        @endif
        @if ($event->scheduled_at && ! $isLive)
            <p class="mt-2 text-sm text-zinc-400">{{ $event->scheduled_at->timezone(config('app.timezone'))->format('D, M j · g:i A T') }}</p>
        @endif
    </div>

    @if ($isLive && $hlsUrl)
        <audio id="stream-audio" class="w-full rounded-lg" controls playsinline></audio>
        <p id="stream-status" class="text-sm text-zinc-500">Connecting…</p>
        <div id="listen-root" data-hls-url="{{ $hlsUrl }}" data-stream-status="live" class="hidden"></div>
    @elseif ($event->status->value === 'ended')
        <p class="rounded-lg border border-zinc-800 bg-zinc-900/50 px-4 py-3 text-sm text-zinc-400">
            This event has ended.
            <a href="{{ route('channels.show', $event->organization) }}" class="text-emerald-400 hover:text-emerald-300">Browse the channel</a>
            for recordings.
        </p>
    @else
        <p class="rounded-lg border border-zinc-800 bg-zinc-900/50 px-4 py-3 text-sm text-zinc-400">
            Waiting for the broadcast to start. Keep this page open — it will become the live player.
        </p>
        <meta http-equiv="refresh" content="30">
    @endif

    <div class="flex flex-wrap items-center gap-4 text-sm">
        @if ($event->show_listener_count)
            <span class="text-zinc-400"><span id="listener-count">{{ $listenerCount ?? 0 }}</span> listening</span>
        @endif
        <span class="text-zinc-400"><span id="heart-count">{{ $heartCount }}</span> hearts</span>
        @auth
            <button type="button" id="btn-heart"
                class="rounded-lg border border-zinc-700 px-3 py-1.5 text-sm {{ $userHearted ? 'text-rose-300' : 'text-zinc-300' }} hover:bg-zinc-800"
                data-hearted="{{ $userHearted ? '1' : '0' }}">
                {{ $userHearted ? '♥ Hearted' : '♡ Heart' }}
            </button>
        @else
            <a href="{{ route('login') }}" class="text-zinc-400 hover:text-white">Log in to heart</a>
        @endauth
    </div>

    @if ($event->chat_enabled)
        <section class="rounded-xl border border-zinc-800 bg-zinc-900/40 p-4">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-400">Chat</h2>
            <div id="chat-messages" class="mt-3 flex max-h-56 flex-col gap-2 overflow-y-auto"></div>
            @auth
                <form id="chat-form" class="mt-3 flex gap-2">
                    <input id="chat-body" type="text" maxlength="500" placeholder="Say something…" required
                        class="min-w-0 flex-1 rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-white">
                    <button type="submit" class="rounded-lg ev-accent-bg px-4 py-2 text-sm font-semibold text-white">Send</button>
                </form>
            @else
                <p class="mt-3 text-sm text-zinc-500"><a href="{{ route('login') }}" class="text-emerald-400">Log in</a> to join the chat.</p>
            @endauth
            <div id="chat-root" class="hidden"
                data-poll-url="{{ route('events.chat.index', $event) }}"
                data-post-url="{{ route('events.chat.store', $event) }}"></div>
        </section>
    @endif

    <div id="engage-root" class="hidden"
        data-presence-url="{{ route('events.presence', $event) }}"
        data-heart-url="{{ route('events.heart', $event) }}"
        data-csrf="{{ csrf_token() }}"></div>
@endsection
