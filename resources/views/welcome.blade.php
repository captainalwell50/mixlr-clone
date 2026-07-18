<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Live Mix Audio') }} — Live audio, mixed clean</title>
    <meta name="description" content="Share one event link. It becomes your live stage — with chat, hearts, and an installable listen experience.">
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

    <section class="mkt-hero" aria-label="Welcome">
        <div class="mkt-hero-atmosphere" aria-hidden="true"></div>
        <div class="mkt-hero-grid">
            <div class="mkt-hero-copy">
                <p class="mkt-brand stage-rise">{{ config('app.name', 'Live Mix Audio') }}</p>
                <h1 class="mkt-headline stage-rise-delay">Effortless live audio for every gathering.</h1>
                <p class="mkt-lede stage-rise-delay-2">
                    Share one event link. Listeners get a clean stage with chat and hearts — built for churches, rooms, and real-time presence.
                </p>
                <div class="mkt-cta stage-rise-delay-2">
                    <a href="{{ route('discover') }}" class="site-btn site-btn-primary">Discover live</a>
                    @auth
                        @if(auth()->user()->is_admin || auth()->user()->manageableOrganizations()->exists())
                            <a href="{{ route('admin.streams.index') }}" class="site-btn site-btn-ghost">Open streams</a>
                        @else
                            <a href="{{ url('/dashboard') }}" class="site-btn site-btn-ghost">Dashboard</a>
                        @endif
                    @else
                        <a href="{{ route('login') }}" class="site-btn site-btn-ghost">Log in</a>
                    @endauth
                </div>
            </div>

            <div class="mkt-hero-visual stage-rise-delay-2" aria-hidden="true">
                <div class="mkt-stage-glow"></div>

                {{-- Phone: Listen experience --}}
                <div class="mkt-device mkt-phone">
                    <div class="mkt-phone-bezel">
                        <div class="mkt-phone-notch"></div>
                        <div class="mkt-phone-screen">
                            <div class="mkt-mock-top">
                                <span class="mkt-mock-platform">Live Mix</span>
                                <span class="mkt-mock-link">Discover</span>
                            </div>
                            <p class="mkt-mock-live">
                                <span class="live-dot"></span> Live
                            </p>
                            <p class="mkt-mock-channel">Sunday Gathering</p>
                            <p class="mkt-mock-title">Morning worship · Main hall</p>
                            <div class="mkt-mock-player">
                                <div class="mkt-mock-wave is-active">
                                    @for ($i = 0; $i < 14; $i++)
                                        <span style="--h: {{ 18 + (($i * 17) % 40) }}%; --d: {{ $i * 0.08 }}s"></span>
                                    @endfor
                                </div>
                                <div class="mkt-mock-play">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7L8 5z"/></svg>
                                </div>
                                <p class="mkt-mock-status">Listening · 128 kbps</p>
                            </div>
                            <div class="mkt-mock-chat">
                                <p class="mkt-mock-chat-label">Chat</p>
                                <p><strong>Ada</strong> Amen — beautiful mix</p>
                                <p><strong>Pastor Ken</strong> Welcome online family</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Desktop: Studio chrome --}}
                <div class="mkt-device mkt-studio">
                    <div class="mkt-studio-chrome">
                        <div class="mkt-studio-bar">
                            <span class="mkt-studio-dots"><i></i><i></i><i></i></span>
                            <span class="mkt-studio-title">Studio · Sunday Gathering</span>
                        </div>
                        <div class="mkt-studio-body">
                            <div class="mkt-studio-meter">
                                <span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span>
                            </div>
                            <div class="mkt-studio-meta">
                                <p class="mkt-mock-live"><span class="live-dot"></span> On air</p>
                                <p class="mkt-studio-listeners">84 listening</p>
                            </div>
                            <div class="mkt-studio-controls">
                                <span class="is-live">Go live</span>
                                <span>Mute</span>
                                <span>End</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="mkt-section" id="how-it-works">
        <div class="mkt-section-inner">
            <p class="site-section-label">How it works</p>
            <h2 class="mkt-section-title">From link to live stage in three beats.</h2>
            <p class="mkt-section-lede">No video stack. No clutter. Just audio people can join from any phone.</p>
            <ol class="mkt-steps">
                <li>
                    <span class="mkt-step-num">01</span>
                    <h3>Create the event</h3>
                    <p>Set a title, artwork, and go-live time. One shareable URL is your stage door.</p>
                </li>
                <li>
                    <span class="mkt-step-num">02</span>
                    <h3>Broadcast clean</h3>
                    <p>Open Studio, hit live, and stream. Listeners hear you with a dedicated listen UI — not a video player with the camera off.</p>
                </li>
                <li>
                    <span class="mkt-step-num">03</span>
                    <h3>Keep the archive</h3>
                    <p>Recordings land in Archive so latecomers and midweek listeners can catch up.</p>
                </li>
            </ol>
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
