<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $stream->title }}</title>
    @vite(['resources/css/app.css', 'resources/js/listen.js'])
</head>
@php
    $isLive = $stream->status === \App\Enums\StreamStatus::Live;
    $org = $stream->organization;
    $accent = data_get($org?->branding_config, 'accent');
@endphp
<body class="m-0 bg-zinc-950 p-2 text-zinc-100">
    <div class="mb-1 flex items-center justify-between gap-2 px-0.5 text-xs">
        <span class="truncate font-medium text-white">{{ $stream->title }}</span>
        <span class="shrink-0 font-semibold uppercase tracking-wide {{ $isLive ? 'text-emerald-400' : 'text-zinc-500' }}"
            @if ($accent && $isLive) style="color: {{ $accent }}" @endif>
            {{ $isLive ? 'Live' : 'Offline' }}
        </span>
    </div>
    <audio id="stream-audio" class="w-full" controls playsinline></audio>
    <p id="stream-status" class="mt-1 px-0.5 text-[11px] text-zinc-500">
        @unless ($isLive)
            Waiting for broadcast…
        @endunless
    </p>
    <div
        id="listen-root"
        data-hls-url="{{ $hlsUrl }}"
        data-stream-status="{{ $stream->status->value }}"
        class="hidden"
    ></div>
</body>
</html>
