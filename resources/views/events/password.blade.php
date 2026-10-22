@extends('layouts.stream')

@section('title', 'Private Event · '.$event->organization->name)

@php
    $theme = $event->organization->themeColor();
    $artwork = $event->artworkUrl() ?: $event->organization->artworkUrl();
@endphp

@section('content')
    <div class="stage stage-cinema portal stage-private-gate" style="--stage-accent: {{ $theme }};">
        <div
            class="stage-atmosphere {{ $artwork ? 'has-art' : '' }} is-fullscreen"
            @if ($artwork) style="--stage-art: url('{{ $artwork }}')" @endif
            aria-hidden="true"
        ></div>

        <div class="stage-shell">
            <header class="portal-bar stage-rise">
                <div class="portal-brand">
                    <a href="{{ $event->organization->channelUrl() }}" class="portal-channel-link">
                        @if ($artwork)
                            <img src="{{ $artwork }}" alt="" class="portal-avatar">
                        @else
                            <span class="portal-avatar portal-avatar--fallback" aria-hidden="true">{{ strtoupper(substr($event->organization->name, 0, 1)) }}</span>
                        @endif
                        <span class="portal-channel-name">{{ $event->organization->name }}</span>
                    </a>
                </div>
                <nav class="portal-bar-links">
                    <a href="{{ route('discover') }}" class="stage-top-link">Discover</a>
                </nav>
            </header>

            <div class="portal-lock-container">
                <div class="portal-lock-card stage-rise">
                    <div class="portal-lock-icon-wrap" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                    </div>

                    <div class="portal-badges justify-center">
                        <span class="stage-status is-idle portal-badge portal-badge--locked">
                            <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" class="inline mr-1" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                            Private
                        </span>
                        <span class="portal-badge portal-badge--soft">Protected Event</span>
                    </div>

                    <h1 class="stage-channel portal-lock-channel stage-rise-delay">{{ $event->organization->name }}</h1>
                    <p class="stage-title portal-lock-title stage-rise-delay-2">{{ $event->title }}</p>
                    <p class="stage-meta portal-lock-sub">Enter the password to listen.</p>

                    <form method="POST" action="{{ route('events.unlock', $event) }}" class="stage-desk portal-lock-form stage-rise-delay-2">
                        @csrf
                        <div class="portal-lock-field">
                            <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-[var(--stage-muted)]">Event Access Password</label>
                            <input id="password" type="password" name="password" required autofocus
                                placeholder="Enter passcode"
                                class="mt-2 w-full rounded-xl border border-white/15 bg-black/40 px-3.5 py-2.5 text-sm font-mono text-[var(--stage-cream)] outline-none transition focus:border-[var(--stage-accent)] focus:ring-1 focus:ring-[var(--stage-accent)]">
                            @error('password')
                                <p class="mt-1.5 text-xs font-medium text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <button type="submit" class="btn-go portal-lock-submit w-full">Unlock Broadcast</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
