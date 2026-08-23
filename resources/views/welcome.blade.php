<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Sound Mix Live') }} — Live audio, mixed clean</title>
    <meta name="description" content="Share one event link. It becomes your live stage — with chat, hearts, and an installable listen experience.">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=fraunces:500,600,700|source-sans-3:400,500,600,700" rel="stylesheet" />
    @vite(['resources/css/app.css'])
</head>
<body class="stage-body marketing-body">
    <header class="mkt-header">
        <div class="mkt-header-inner">
            @include('partials.brand-logo')

            @include('partials.marketing-nav')
        </div>
    </header>

    <section class="mkt-hero" aria-label="Welcome">
        <div class="mkt-hero-atmosphere" aria-hidden="true"></div>
        <div class="mkt-hero-grid">
            <div class="mkt-hero-copy">
                <p class="mkt-brand stage-rise">{{ config('app.name', 'Sound Mix Live') }}</p>
                <h1 class="mkt-headline stage-rise-delay">Effortless live audio for every gathering.</h1>
                <p class="mkt-lede stage-rise-delay-2">
                    Share one event link. Listeners get a clean stage with chat and hearts — built for churches, rooms, and real-time presence.
                </p>
                <div class="mkt-cta stage-rise-delay-2">
                    @if (config('app.registration_enabled') && Route::has('register') && auth()->guest())
                        <a href="{{ route('register') }}" class="site-btn site-btn-primary">Start broadcasting</a>
                        <a href="{{ route('discover') }}" class="site-btn site-btn-ghost">Discover live</a>
                    @else
                        <a href="{{ route('discover') }}" class="site-btn site-btn-primary">Discover live</a>
                        @auth
                            @if(auth()->user()->is_admin)
                                <a href="{{ route('admin.streams.index') }}" class="site-btn site-btn-ghost">Open streams</a>
                            @elseif(auth()->user()->organizations()->exists())
                                <a href="{{ route('creator.home') }}" class="site-btn site-btn-ghost">Creator home</a>
                            @else
                                <a href="{{ route('onboarding.show') }}" class="site-btn site-btn-ghost">Set up channel</a>
                            @endif
                        @else
                            <a href="{{ route('login') }}" class="site-btn site-btn-ghost">Log in</a>
                        @endauth
                    @endif
                </div>
                <div class="mkt-hero-stores stage-rise-delay-2" aria-label="Download mobile apps">
                    @include('partials.store-links', ['group' => 'mobile'])
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
                                <span class="mkt-mock-platform">Sound Mix Live</span>
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

    <section class="mkt-apps" aria-label="Download apps">
        <div class="mkt-apps-inner">
            <p class="site-section-label">Apps</p>
            <h2 class="mkt-apps-title">Take Sound Mix Live with you</h2>
            <p class="mkt-apps-lede">
                Google Play, App Store, Mac, and Windows — listen and go live wherever you create.
            </p>
            <div class="mkt-apps-stores">
                @include('partials.store-links')
            </div>
            <p class="mkt-apps-more">
                <a href="{{ route('downloads') }}">All download options</a>
            </p>
        </div>
    </section>

    @include('partials.marketing-footer')
</body>
</html>
