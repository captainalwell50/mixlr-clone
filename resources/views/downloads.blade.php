<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Download apps — {{ config('app.name', 'Sound Mix Live') }}</title>
    <meta name="description" content="Get Sound Mix Live on Google Play, the App Store, Mac, and Windows.">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=fraunces:500,600,700|source-sans-3:400,500,600,700" rel="stylesheet" />
    @vite(['resources/css/app.css'])
</head>
<body class="stage-body marketing-body">
    <header class="mkt-header">
        <div class="mkt-header-inner">
            @include('partials.brand-logo')
            @include('partials.marketing-nav', ['current' => 'downloads'])
        </div>
    </header>

    <main class="dl-page">
        <header class="dl-hero">
            <p class="site-section-label">Apps</p>
            <h1 class="mkt-page-title">Get Sound Mix Live</h1>
            <p class="mkt-section-lede">
                Listen and go live from your phone or desktop. Download from the stores below.
            </p>
        </header>

        <section class="dl-group" aria-labelledby="dl-mobile-heading">
            <h2 id="dl-mobile-heading" class="dl-group-title">Mobile</h2>
            <p class="dl-group-lede">
                Install the Android APK to test now. Store listings go live when published.
            </p>
            @include('partials.store-links', ['group' => 'mobile'])
        </section>

        <section class="dl-group" aria-labelledby="dl-desktop-heading">
            <h2 id="dl-desktop-heading" class="dl-group-title">Desktop</h2>
            <p class="dl-group-lede">
                Sound Mix Live Studio for Mac — universal (Apple Silicon + Intel). Windows builds publish when available.
                You can also use
                <a href="{{ route('login') }}">Studio in your browser</a>.
            </p>
            @include('partials.store-links', ['group' => 'desktop'])
            <p class="dl-note" style="margin-top:1rem">
                <strong>Mac download blocked?</strong>
                That’s Gatekeeper (the build isn’t Apple-notarized yet), not a wrong chip.
                Open <strong>System Settings → Privacy &amp; Security</strong>, scroll to the message about
                Sound Mix Live Studio, and click <strong>Open Anyway</strong>.
                Or right-click the app → <strong>Open</strong> → <strong>Open</strong>.
            </p>
        </section>

        <p class="dl-note">
            Prefer the browser?
            <a href="{{ route('discover') }}">Discover live</a>
            or open
            <a href="{{ route('login') }}">Studio on the web</a>.
        </p>
    </main>

    <footer class="mkt-footer">
        <div class="mkt-footer-inner">
            @include('partials.brand-logo', ['compact' => true, 'onDark' => true])
            <p>Channels, events, and a stage made for listening.</p>
            <nav aria-label="Footer">
                <a href="{{ route('how-it-works') }}">How it works</a>
                <a href="{{ route('discover') }}">Discover</a>
                <a href="{{ route('downloads') }}">Download</a>
                <a href="{{ route('archive.index') }}">Podcasts</a>
                @auth
                    <a href="{{ url('/dashboard') }}">Dashboard</a>
                @else
                    <a href="{{ route('login') }}">Log in</a>
                @endauth
            </nav>
        </div>
    </footer>
</body>
</html>
