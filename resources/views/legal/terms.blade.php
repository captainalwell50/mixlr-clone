<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Terms of Service — {{ config('app.name', 'Sound Mix Live') }}</title>
    <meta name="description" content="Terms of Service for Sound Mix Live website, apps, and Studio.">
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

    <section class="mkt-page-hero" aria-label="Terms of Service">
        <div class="mkt-page-hero-inner">
            <p class="site-section-label">Legal</p>
            <h1 class="mkt-page-title">Terms of Service</h1>
            <p class="mkt-section-lede">
                Last updated {{ $updated }}. By using Sound Mix Live you agree to these terms.
            </p>
        </div>
    </section>

    <section class="mkt-section">
        <div class="mkt-section-inner mkt-legal">
            <h2>The service</h2>
            <p>
                Sound Mix Live lets creators broadcast live audio and lets listeners join channels and events.
                Features may include Studio publishing, chat, engagement signals, gallery media, scripture aids,
                recordings/archives, and desktop/mobile apps. We may change or discontinue features with reasonable notice when practical.
            </p>

            <h2>Accounts</h2>
            <p>
                You must provide accurate account information and keep your password confidential.
                You are responsible for activity under your account. Public registration may be disabled on some deployments;
                invited or admin-created accounts remain subject to these terms.
            </p>

            <h2>Acceptable use</h2>
            <p>You agree not to:</p>
            <ul>
                <li>Broadcast unlawful, harassing, or infringing content.</li>
                <li>Attempt to disrupt media infrastructure, abuse APIs, or bypass access controls.</li>
                <li>Upload malware or scrape the service in a way that harms availability.</li>
                <li>Impersonate others or misrepresent affiliation with a church, organization, or brand.</li>
            </ul>
            <p>We may suspend or terminate accounts that violate these rules.</p>

            <h2>Content you provide</h2>
            <p>
                You retain rights to your audio and media. You grant us a limited license to host, transmit, and display
                that content as needed to operate Sound Mix Live (including CDN/media relay delivery to listeners).
                You confirm you have the rights needed to broadcast and upload what you share.
            </p>

            <h2>Subscriptions and billing</h2>
            <p>
                Paid plans, when offered, are billed through our payment provider under the package you select.
                Trials, renewals, and cancellations follow the plan terms shown at checkout and in your billing UI.
            </p>

            <h2>Apps and permissions</h2>
            <p>
                Mobile and desktop apps may request microphone access for Studio, notification permission for background listen on Android,
                and network access for streaming. Declining a permission may disable the related feature without affecting other parts of the app.
            </p>

            <h2>Disclaimer</h2>
            <p>
                The service is provided “as is.” Live streaming depends on networks and devices outside our control.
                To the fullest extent permitted by law, we disclaim warranties of uninterrupted availability and fitness for a particular purpose.
            </p>

            <h2>Limitation of liability</h2>
            <p>
                To the fullest extent permitted by law, Sound Mix Live and its operators are not liable for indirect,
                incidental, or consequential damages arising from use of the service. Aggregate liability for claims
                relating to the service is limited to the amounts you paid us for the service in the three months before the claim, if any.
            </p>

            <h2>Account deletion</h2>
            <p>
                You may delete your account from the website Account page or in-app Studio controls.
                See our <a href="{{ route('legal.privacy') }}">Privacy Policy</a> for what deletion covers.
            </p>

            <h2>Contact</h2>
            <p>
                Questions about these terms: <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>
                or visit <a href="{{ route('legal.support') }}">Support</a>.
            </p>
        </div>
    </section>

    @include('partials.marketing-footer')
</body>
</html>
