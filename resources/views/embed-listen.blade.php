<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $stream->title }} · {{ config('app.name', 'Live Mix Audio') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=fraunces:600|source-sans-3:400,600" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/listen.js'])
</head>
@php
    $isLive = $stream->status === \App\Enums\StreamStatus::Live;
    $organization = $stream->organization;
    $theme = $organization?->themeColor() ?? '#3d9b7a';
    $artwork = $organization?->artworkUrl();
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
                    <h1 class="embed-title">{{ $stream->title }}</h1>
                    <p class="embed-channel">{{ $organization?->name }}</p>
                </div>
                <span class="embed-badge {{ $isLive ? 'is-live' : '' }}">
                    @if ($isLive)
                        <span class="live-dot inline-block h-1.5 w-1.5 rounded-full bg-current"></span>
                    @endif
                    {{ $isLive ? 'Live' : 'Offline' }}
                </span>
            </div>

            @include('partials.stage-player', [
                'status' => $isLive ? 'Connecting…' : 'Waiting for broadcast…',
                'disabled' => ! $isLive,
            ])

            <div
                id="listen-root"
                data-hls-url="{{ $hlsUrl }}"
                data-whep-url="{{ $whepUrl }}"
                data-stream-status="{{ $stream->status->value }}"
                class="hidden"
            ></div>

            <p class="embed-brand">{{ config('app.name', 'Live Mix Audio') }}</p>
        </div>
    </div>
</body>
</html>
