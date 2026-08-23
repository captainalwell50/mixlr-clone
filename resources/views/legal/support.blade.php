<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Support — {{ config('app.name', 'Sound Mix Live') }}</title>
    <meta name="description" content="Contact Sound Mix Live support for account, streaming, and app help.">
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

    <section class="mkt-page-hero" aria-label="Support">
        <div class="mkt-page-hero-inner">
            <p class="site-section-label">Help</p>
            <h1 class="mkt-page-title">Support</h1>
            <p class="mkt-section-lede">
                Need help with listening, Studio, billing, or your account? Reach the Sound Mix Live team directly.
            </p>
        </div>
    </section>

    <section class="mkt-section">
        <div class="mkt-section-inner mkt-legal">
            <h2>Email</h2>
            <p>
                <a class="mkt-support-email" href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>
            </p>
            <p>
                Include your account email, device/app version if relevant, and a short description of the issue.
                For account deletion requests you can also use the in-app or website Account deletion flow.
            </p>

            <h2>Self-serve links</h2>
            <ul>
                <li><a href="{{ route('how-it-works') }}">How it works</a></li>
                <li><a href="{{ route('downloads') }}">Downloads</a></li>
                <li><a href="{{ route('legal.privacy') }}">Privacy Policy</a></li>
                <li><a href="{{ route('legal.terms') }}">Terms of Service</a></li>
                @auth
                    <li><a href="{{ route('account.edit') }}">Your account</a> (including delete account)</li>
                @else
                    <li><a href="{{ route('login') }}">Log in</a> to manage or delete your account</li>
                @endauth
            </ul>

            <h2>Developer / store contact</h2>
            <p>
                This same address is the developer contact for Google Play and Apple App Store listings:
                <strong>{{ $supportEmail }}</strong>.
            </p>
        </div>
    </section>

    @include('partials.marketing-footer')
</body>
</html>
