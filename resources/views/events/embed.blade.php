<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $event->title }}</title>
    @vite(['resources/css/app.css', 'resources/js/listen.js'])
</head>
<body class="m-0 bg-zinc-950 p-2 text-zinc-100">
    <div class="mb-1 flex items-center justify-between gap-2 px-0.5 text-xs">
        <span class="truncate font-medium text-white">{{ $event->title }}</span>
        <span class="shrink-0 font-semibold uppercase tracking-wide {{ $isLive ? 'text-emerald-400' : 'text-zinc-500' }}">
            {{ $isLive ? 'Live' : 'Offline' }}
        </span>
    </div>
    @if ($isLive && $hlsUrl)
        <audio id="stream-audio" class="w-full" controls playsinline></audio>
        <div id="listen-root" data-hls-url="{{ $hlsUrl }}" data-stream-status="live" class="hidden"></div>
    @else
        <p class="px-0.5 text-[11px] text-zinc-500">Broadcast not live.</p>
    @endif
</body>
</html>
