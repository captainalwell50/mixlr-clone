@php
    $current = $current ?? null;
@endphp
<nav class="mkt-nav" aria-label="Primary">
    <a href="{{ route('how-it-works') }}" @if($current === 'how-it-works') aria-current="page" @endif>How it works</a>
    <a href="{{ route('discover') }}" @if($current === 'discover') aria-current="page" @endif>Discover</a>
    <a href="{{ route('archive.index') }}" @if($current === 'archive') aria-current="page" @endif>Podcasts</a>
    <a href="{{ route('downloads') }}" @if($current === 'downloads') aria-current="page" @endif>Download</a>
    @auth
        <a href="{{ url('/dashboard') }}">Dashboard</a>
    @else
        <a href="{{ route('login') }}">Log in</a>
        @if (config('app.registration_enabled') && Route::has('register'))
            <a href="{{ route('register') }}">Register</a>
        @endif
    @endauth
    <a href="{{ route('downloads') }}" class="mkt-nav-cta">Get the apps</a>
</nav>
