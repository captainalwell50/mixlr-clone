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
    <div id="studio-stage" class="stage stage--cinema" style="--stage-accent: {{ $theme }};">
        <div
            class="stage-atmosphere has-art"
            style="--stage-art: url('{{ $artwork ?: asset('images/listen-stage-bg.jpg') }}')"
        ></div>

        <div class="stage-frame stage-frame--desk">
            <header class="stage-top stage-rise">
                <a href="{{ url('/') }}" class="stage-platform">{{ config('app.name', 'Live Mix Audio') }}</a>
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
                <div class="stage-channels" id="audio-channels">
                    <div class="stage-channel-card" data-channel="mic" id="mic-channel">
                        <div class="stage-channel-head">
                            <span class="stage-channel-badge">Mic</span>
                            <span class="stage-channel-name">Microphone / interface</span>
                        </div>
                        <label class="sr-only" for="audio-input">Microphone / interface</label>
                        <select id="audio-input" class="mt-2"></select>
                        <div class="stage-channel-gain">
                            <label for="mic-gain">Level</label>
                            <input id="mic-gain" type="range" min="0" max="150" value="100" step="1">
                            <span id="mic-gain-label" class="stage-channel-gain-value">100%</span>
                        </div>
                    </div>
                </div>

                <div class="stage-channel-actions">
                    <button type="button" id="btn-add-file" class="btn-channel-add">+ Add audio file</button>
                    <input id="file-input" type="file" accept="audio/*,.mp3,.wav,.m4a,.aac,.ogg,.flac" class="hidden" multiple>
                </div>

                <div>
                    <label for="audio-layout">Output</label>
                    <select id="audio-layout" class="mt-2">
                        <option value="mono" selected>Mono — both ears (recommended for one mic)</option>
                        <option value="stereo">Stereo — left / right</option>
                    </select>
                    <p class="mt-1 text-xs" style="color: var(--stage-muted)">
                        Mic is captured clean (no browser processing). Mono encodes for both ears; stereo keeps left/right. Leave Level at 100% for highest quality.
                    </p>
                </div>

                <div>
                    <div class="mb-1 flex items-center justify-between text-xs" style="color: var(--stage-muted)">
                        <span>Mix level</span>
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
                    Allow microphone access when prompted. Leave mic level at 100% for best quality (direct path). Only add audio files if you need them in the same broadcast.
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
