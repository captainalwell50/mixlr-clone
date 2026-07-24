<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>How it works — {{ config('app.name', 'Sound Mix Live') }}</title>
    <meta name="description" content="Schedule an event, go live from Studio, share one link, and let listeners join with chat and hearts.">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=fraunces:500,600,700|source-sans-3:400,500,600,700" rel="stylesheet" />
    @vite(['resources/css/app.css'])
</head>
<body class="stage-body marketing-body">
    <header class="mkt-header">
        <div class="mkt-header-inner">
            @include('partials.brand-logo')

            @include('partials.marketing-nav', ['current' => 'how-it-works'])
        </div>
    </header>

    <section class="mkt-page-hero" aria-label="How it works">
        <div class="mkt-page-hero-inner">
            <p class="site-section-label">How it works</p>
            <h1 class="mkt-page-title">From schedule to shared listening.</h1>
            <p class="mkt-section-lede">
                No video stack. No clutter. Four beats from planning the gathering to people listening with chat and hearts.
            </p>
        </div>
    </section>

    <section class="mkt-section" id="steps">
        <div class="mkt-section-inner">
            <ol class="mkt-steps mkt-steps-four">
                <li>
                    <span class="mkt-step-num">01</span>
                    <h2>Schedule the event</h2>
                    <p>Set a title, artwork, and go-live time. One shareable URL becomes your stage door for the gathering.</p>
                </li>
                <li>
                    <span class="mkt-step-num">02</span>
                    <h2>Go live from Studio</h2>
                    <p>Open Studio on the day, hit live, and stream clean audio. Listeners hear you in a dedicated listen UI — not a muted video player.</p>
                </li>
                <li>
                    <span class="mkt-step-num">03</span>
                    <h2>Share the link</h2>
                    <p>Send the event link once. It stays the stage for the whole service — pews, kitchen radios, and phones at home.</p>
                </li>
                <li>
                    <span class="mkt-step-num">04</span>
                    <h2>Listen with chat &amp; hearts</h2>
                    <p>People join from any phone, send hearts, and chat in real time. Later, recordings land in Podcasts for midweek catch-up.</p>
                </li>
            </ol>
            <div class="mkt-cta mkt-page-cta">
                @auth
                    @if(auth()->user()->is_admin || auth()->user()->manageableOrganizations()->exists())
                        <a href="{{ route('admin.events.create') }}" class="site-btn site-btn-primary">Schedule event</a>
                    @else
                        <a href="{{ url('/dashboard') }}" class="site-btn site-btn-primary">Dashboard</a>
                    @endif
                @else
                    <a href="{{ route('login') }}" class="site-btn site-btn-primary">Log in to schedule</a>
                @endauth
                <a href="{{ route('discover') }}" class="site-btn site-btn-ghost">Discover live</a>
            </div>
        </div>
    </section>

    <section class="mkt-section mkt-section-alt" id="for-churches">
        <div class="mkt-section-inner mkt-split">
            <div>
                <p class="site-section-label">For churches</p>
                <h2 class="mkt-section-title">Built for rooms that gather — online and in the pews.</h2>
                <p class="mkt-section-lede">
                    Channels for each campus or ministry, event pages your congregation can bookmark, and a listen experience that feels like presence — chat, hearts, and an installable home-screen app.
                </p>
                <div class="mkt-cta">
                    <a href="{{ route('discover') }}" class="site-btn site-btn-primary">Browse channels</a>
                    <a href="{{ route('archive.index') }}" class="site-btn site-btn-ghost">Podcasts</a>
                </div>
            </div>
            <ul class="mkt-points">
                <li>
                    <strong>One link per gathering</strong>
                    <span>Share Sunday once. It stays the stage for chat and audio.</span>
                </li>
                <li>
                    <strong>Presence without video pressure</strong>
                    <span>Hearts and chat keep remote members connected without a camera feed.</span>
                </li>
                <li>
                    <strong>Branded listen pages</strong>
                    <span>Accent color and artwork follow your channel — not a generic player skin.</span>
                </li>
            </ul>
        </div>
    </section>

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
