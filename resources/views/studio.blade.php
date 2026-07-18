@extends('layouts.stream')

@section('title', 'Studio · '.$stream->title)

@section('vite')
    @vite(['resources/js/studio.js'])
@endsection

@php
    $theme = $organization->themeColor();
    $artwork = $organization->artworkUrl();
@endphp

@section('content')
    <div id="studio-stage" class="stage" style="--stage-accent: {{ $theme }};">
        <div
            class="stage-atmosphere {{ $artwork ? 'has-art' : '' }}"
            @if ($artwork) style="--stage-art: url('{{ $artwork }}')" @endif
        ></div>

        <div class="stage-content">
            <header class="stage-top stage-rise">
                <p class="stage-platform">{{ config('app.name', 'Live Mix Audio') }}</p>
                <a href="{{ route('dashboard') }}" class="stage-top-link">Dashboard</a>
            </header>

            <p id="studio-mode" class="stage-status stage-rise-delay stage-broadcaster">
                Broadcaster
            </p>

            <h1 class="stage-channel stage-rise-delay">{{ $organization->name }}</h1>
            <p class="stage-title stage-rise-delay-2">{{ $stream->title }}</p>
            <p class="stage-meta">You’re at the desk. Go live, then share the listener link. Keep this tab open.</p>

            <p class="stage-on-air-pill" id="studio-on-air-pill" aria-live="polite">
                <span class="live-dot inline-block h-1.5 w-1.5 rounded-full bg-current"></span>
                You’re on air
            </p>

            <div class="stage-desk stage-rise-delay-2">
                <div>
                    <label for="audio-input">Microphone / interface</label>
                    <select id="audio-input" class="mt-2"></select>
                </div>

                <div>
                    <div class="mb-1 flex items-center justify-between text-xs" style="color: var(--stage-muted)">
                        <span>Input level</span>
                        <span id="meter-label">—</span>
                    </div>
                    <div class="stage-meter-track">
                        <div id="level-meter" class="stage-meter-fill"></div>
                    </div>
                </div>

                <div class="stage-desk-actions">
                    <button type="button" id="btn-start" class="btn-go">Go live</button>
                    <button type="button" id="btn-stop" class="btn-stop" disabled>Stop</button>
                </div>

                <p id="studio-status" class="stage-status-line" style="text-align: left">
                    Allow microphone access when prompted, then press Go live.
                </p>

                <div class="stage-copy-row">
                    <code id="listen-url" title="{{ $listenUrl }}">{{ $listenUrl }}</code>
                    <button type="button" id="btn-copy-listen">Copy link</button>
                </div>
            </div>

            <div
                id="studio-root"
                data-whip-url="{{ $whipUrl }}"
                class="hidden"
            ></div>
        </div>
    </div>
@endsection
