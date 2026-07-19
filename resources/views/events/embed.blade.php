<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $event->title }} · {{ config('app.name', 'Live Mix Audio') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=fraunces:600|source-sans-3:400,600" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/listen.js'])
</head>
@php
    $theme = $event->organization->themeColor();
    $artwork = $event->artworkUrl();
@endphp
<body class="embed-body">
    <div class="embed-shell" style="--stage-accent: {{ $theme }};">
        <div
            class="embed-art {{ $artwork ? 'has-art' : '' }}"
            @if ($artwork) style="--tile-art: url('{{ $artwork }}')" @endif
            aria-hidden="true"
        ></div>
        <div class="embed-main">
            <div class="embed-top">
                <div class="min-w-0">
                    <h1 class="embed-title">{{ $event->title }}</h1>
                    <p class="embed-channel">{{ $event->organization->name }}</p>
                </div>
                <span id="broadcast-badge" class="embed-badge {{ $isLive ? 'is-live' : '' }}">
                    @if ($isLive)
                        <span class="live-dot inline-block h-1.5 w-1.5 rounded-full bg-current"></span>
                    @endif
                    {{ $isLive ? 'Live' : 'Offline' }}
                </span>
            </div>

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
            @else
                <p class="embed-offline">Broadcast not live.</p>
            @endif

            <p class="embed-brand">{{ config('app.name', 'Live Mix Audio') }}</p>
        </div>
    </div>
</body>
</html>
