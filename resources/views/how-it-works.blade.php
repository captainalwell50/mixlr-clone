<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>How it works — {{ config('app.name', 'Live Mix Audio') }}</title>
    <meta name="description" content="Schedule an event, go live from Studio, share one link, and let listeners join with chat and hearts.">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=fraunces:500,600,700|source-sans-3:400,500,600,700" rel="stylesheet" />
    @vite(['resources/css/app.css'])
</head>
<body class="stage-body marketing-body">
    <header class="mkt-header">
        <div class="mkt-header-inner">
            <a href="{{ url('/') }}" class="mkt-logo" aria-label="{{ config('app.name', 'Live Mix Audio') }} home">
                <span class="mkt-logo-mark" aria-hidden="true">
                    <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect width="40" height="40" rx="10" fill="#0c1210"/>
                        <path d="M12 26V14c0-1.1.9-2 2-2h1.2c.4 0 .8.2 1 .6l8.6 14.8c.3.5.9.6 1.4.3.3-.2.5-.5.5-.9V14c0-1.1.9-2 2-2s2 .9 2 2v12c0 1.1-.9 2-2 2h-1.2c-.4 0-.8-.2-1-.6L15.9 12.6c-.3-.5-.9-.6-1.4-.3-.3.2-.5.5-.5.9V26c0 1.1-.9 2-2 2s-2-.9-2-2Z" fill="#3d9b7a"/>
                        <circle cx="20" cy="32.5" r="1.6" fill="#3d9b7a" opacity=".85"/>
                    </svg>
                </span>
                <span class="mkt-logo-word">
                    <span class="mkt-logo-primary">Live Mix</span>
                    <span class="mkt-logo-secondary">Audio</span>
                </span>
            </a>

            <nav class="mkt-nav" aria-label="Primary">
                <a href="{{ route('how-it-works') }}" aria-current="page">How it works</a>
                <a href="{{ route('discover') }}">Discover</a>
                <a href="{{ route('archive.index') }}">Archive</a>
                @auth
                    <a href="{{ url('/dashboard') }}">Dashboard</a>
                @else
                    <a href="{{ route('login') }}">Log in</a>
                @endauth
                <a href="{{ route('discover') }}" class="mkt-nav-cta">Discover live</a>
            </nav>
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
                    <p>People join from any phone, send hearts, and chat in real time. Later, recordings land in Archive for midweek catch-up.</p>
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
                    <a href="{{ route('archive.index') }}" class="site-btn site-btn-ghost">Open archive</a>
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
            <a href="{{ url('/') }}" class="mkt-logo mkt-logo-compact">
                <span class="mkt-logo-mark" aria-hidden="true">
                    <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect width="40" height="40" rx="10" fill="#141c18"/>
                        <path d="M12 26V14c0-1.1.9-2 2-2h1.2c.4 0 .8.2 1 .6l8.6 14.8c.3.5.9.6 1.4.3.3-.2.5-.5.5-.9V14c0-1.1.9-2 2-2s2 .9 2 2v12c0 1.1-.9 2-2 2h-1.2c-.4 0-.8-.2-1-.6L15.9 12.6c-.3-.5-.9-.6-1.4-.3-.3.2-.5.5-.5.9V26c0 1.1-.9 2-2 2s-2-.9-2-2Z" fill="#3d9b7a"/>
                        <circle cx="20" cy="32.5" r="1.6" fill="#3d9b7a" opacity=".85"/>
                    </svg>
                </span>
                <span class="mkt-logo-word">
                    <span class="mkt-logo-primary">Live Mix</span>
                    <span class="mkt-logo-secondary">Audio</span>
                </span>
            </a>
            <p>Channels, events, and a stage made for listening.</p>
            <nav aria-label="Footer">
                <a href="{{ route('how-it-works') }}">How it works</a>
                <a href="{{ route('discover') }}">Discover</a>
                <a href="{{ route('archive.index') }}">Archive</a>
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
