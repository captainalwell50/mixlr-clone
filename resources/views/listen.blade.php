@extends('layouts.stream')

@section('title', $stream->title.' · '.$organization->name)

@section('vite')
    @vite(['resources/js/listen.js', 'resources/js/chat.js'])
@endsection

@php
    $isLive = $stream->status === \App\Enums\StreamStatus::Live;
    $accent = data_get($organization->branding_config, 'accent');
@endphp

@section('content')
    @if ($accent)
        <style>:root { --stream-accent: {{ $accent }}; }</style>
    @endif

    <div>
        <p class="text-xs font-medium uppercase tracking-wide {{ $isLive ? 'text-emerald-400/90' : 'text-zinc-500' }}"
            @if ($accent && $isLive) style="color: var(--stream-accent)" @endif>
            @if ($isLive)
                <span class="mr-1.5 inline-block h-1.5 w-1.5 rounded-full bg-emerald-400 align-middle"></span>
                Live
            @else
                Offline
            @endif
        </p>
        <h1 class="mt-1 text-2xl font-semibold text-white">{{ $stream->title }}</h1>
        <p class="mt-1 text-sm text-zinc-400">{{ $organization->name }}</p>
        @if ($stream->description)
            <p class="mt-2 text-sm text-zinc-500">{{ $stream->description }}</p>
        @endif
    </div>

    <audio id="stream-audio" class="w-full rounded-lg" controls playsinline></audio>
    <p id="stream-status" class="text-sm text-zinc-500">
        @if ($isLive)
            Connecting…
        @else
            Waiting for the broadcast to start. This page will keep trying.
        @endif
    </p>

    <div
        id="listen-root"
        data-hls-url="{{ $hlsUrl }}"
        data-stream-status="{{ $stream->status->value }}"
        class="hidden"
    ></div>

    @if ($stream->chat_enabled)
        <section class="rounded-xl border border-zinc-800 bg-zinc-900/40 p-4">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-400">Chat</h2>
            <div id="chat-messages" class="mt-3 flex max-h-56 flex-col gap-2 overflow-y-auto"></div>
            <form id="chat-form" class="mt-3 flex flex-col gap-2">
                @guest
                    <input id="chat-name" type="text" maxlength="80" placeholder="Your name" required
                        class="rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-white">
                @endguest
                <div class="flex gap-2">
                    <input id="chat-body" type="text" maxlength="500" placeholder="Say something…" required
                        class="min-w-0 flex-1 rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-white">
                    <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Send</button>
                </div>
            </form>
            <div
                id="chat-root"
                class="hidden"
                data-poll-url="{{ route('chat.index', $stream) }}"
                data-post-url="{{ route('chat.store', $stream) }}"
            ></div>
        </section>
    @endif
@endsection
