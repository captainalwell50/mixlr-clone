@extends('layouts.stream')

@section('title', $stream->title.' · Archive')

@section('vite')
    @vite(['resources/js/archive-player.js'])
@endsection

@php
    $organization = $stream->organization;
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
                <a href="{{ route('archive.index') }}" class="stage-top-link">Archive</a>
            </header>

            <p class="stage-status stage-rise-delay is-idle">Recording</p>
            <h1 class="stage-channel stage-rise-delay">{{ $organization->name }}</h1>
            <p class="stage-title stage-rise-delay-2">{{ $stream->title }}</p>
            <p class="stage-meta">
                {{ $recording->completed_at->timezone(config('app.timezone'))->format('M j, Y · g:i A') }}
            </p>

            @include('partials.stage-player', ['status' => 'Press play to listen'])
            <div id="archive-root" data-src="{{ $fileUrl }}" class="hidden"></div>
        </div>
    </div>
@endsection
