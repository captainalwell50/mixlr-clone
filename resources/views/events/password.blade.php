@extends('layouts.stream')

@section('title', 'Private event')

@php
    $theme = $event->organization->themeColor();
    $artwork = $event->artworkUrl();
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

            <p class="stage-status stage-rise-delay is-idle">Private</p>
            <h1 class="stage-channel stage-rise-delay">{{ $event->organization->name }}</h1>
            <p class="stage-title stage-rise-delay-2">{{ $event->title }}</p>
            <p class="stage-meta">Enter the password to listen.</p>

            <form method="POST" action="{{ route('events.unlock', $event) }}" class="stage-desk stage-rise-delay-2">
                @csrf
                <div>
                    <label for="password">Password</label>
                    <input id="password" type="password" name="password" required
                        class="mt-2 w-full rounded-[0.65rem] border border-white/15 bg-black/40 px-3 py-2 text-sm text-[var(--stage-cream)] outline-none focus:border-[var(--stage-accent)]">
                    @error('password')
                        <p class="mt-1 text-sm text-red-300">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit" class="btn-go w-full">Unlock</button>
            </form>
        </div>
    </div>
@endsection
