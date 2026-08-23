<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Privacy Policy — {{ config('app.name', 'Sound Mix Live') }}</title>
    <meta name="description" content="How Sound Mix Live collects, uses, and protects account, audio, microphone, gallery, and related data.">
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

    <section class="mkt-page-hero" aria-label="Privacy Policy">
        <div class="mkt-page-hero-inner">
            <p class="site-section-label">Legal</p>
            <h1 class="mkt-page-title">Privacy Policy</h1>
            <p class="mkt-section-lede">
                Last updated {{ $updated }}. This policy explains what Sound Mix Live collects and why —
                for the website, Android/iOS apps, and Studio clients.
            </p>
        </div>
    </section>

    <section class="mkt-section">
        <div class="mkt-section-inner mkt-legal">
            <h2>Who we are</h2>
            <p>
                Sound Mix Live (“we”, “us”) provides live audio streaming, listening, and creator Studio tools
                at <a href="{{ url('/') }}">soundmix.live</a> and in our mobile/desktop apps.
                Contact: <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>.
            </p>

            <h2>Account data</h2>
            <p>
                When you create or are invited to an account, we store your name, email address, password hash,
                organization/channel memberships, and authentication tokens (for example Sanctum API tokens used by the apps).
                We use this to sign you in, manage channels, and provide creator features.
            </p>

            <h2>Audio streaming</h2>
            <p>
                Live audio is transported through our media infrastructure (including MediaMTX and related hosting)
                so listeners can hear broadcasts in real time (for example via WHEP/WebRTC or HLS) and so creators can publish.
                Stream metadata (title, status, timing), presence/engagement signals, chat messages (on the website
                listen/event pages when chat is enabled), and similar session data may be processed to operate the service.
                The mobile app’s Listen tab does not include in-app chat; chat remains a web feature where enabled.
            </p>

            <h2>Microphone (Studio)</h2>
            <p>
                Studio features request microphone access on your device so you can broadcast live audio.
                Microphone audio is used for live publishing; we do not use the mic for advertising profiling.
                Permission is controlled by your device OS settings.
            </p>

            <h2>Photos, gallery, and profile photo</h2>
            <p>
                Creators may upload images or short media for a stream gallery or stage background.
                Those files are stored on our servers (or configured object storage) and shown to listeners of that stream.
                The mobile listen gallery primarily displays content already published for a channel.
            </p>
            <p>
                Signed-in users may optionally upload a profile photo (avatar) from the device photo picker.
                The selected image is stored with your account and may be shown next to your name in Studio and listen experiences.
                The Android app uses the system Photo Picker and does not request broad photo-library access.
            </p>

            <h2>Speech recognition (scripture / Studio aids)</h2>
            <p>
                Some Studio experiences (for example desktop scripture helpers) may use on-device or OS speech recognition
                to suggest scripture references. When enabled, audio may be processed by the OS speech APIs on that device.
                We do not sell speech data. If a feature is not available on your platform, that processing does not occur there.
            </p>

            <h2>Location</h2>
            <p>
                Sound Mix Live does not request precise device location for core listen or Studio features.
                Approximate location may appear indirectly in server logs (for example IP-based geography used for security and abuse prevention).
            </p>

            <h2>Notifications and background audio</h2>
            <p>
                On Android, background listening may use a foreground media-playback service and notification permission
                so audio can continue when the app is not in the foreground. Notification content is limited to playback status.
            </p>

            <h2>Analytics and diagnostics</h2>
            <p>
                We may collect operational metrics (for example stream health, error rates, presence counts) to keep the service reliable.
                We do not use third-party advertising SDKs in the mobile apps for personalized ads.
                We do not sell personal information.
            </p>

            <h2>Third parties and processors</h2>
            <p>
                We rely on infrastructure providers to host the website, API, media relay (MediaMTX), and optional object storage.
                Payment processors (for example Paystack, when billing is enabled) receive billing data needed to charge subscriptions.
                Optional integrations you connect (for example Google Drive for Studio library import) are governed by those providers’ policies
                and only used for the feature you enable.
            </p>

            <h2>Retention and account deletion</h2>
            <p>
                Account credentials and profile data are kept while your account is active.
                You may delete your account from the Account page on the website
                (<a href="{{ url('/account') }}">soundmix.live/account</a>, sign-in required)
                or in the mobile app under <strong>Profile → Delete account</strong>
                (password confirmation required). Deletion removes your user record and API tokens and detaches channel memberships.
                Content associated with channels you managed (streams, recordings, gallery files) may remain for other organizers
                or until an administrator removes the channel — contact support if you need channel content purged.
            </p>

            <h2>Children</h2>
            <p>
                The service is not directed at children under 13 (or the minimum age required in your country).
                Do not create an account if you are below that age.
            </p>

            <h2>Your choices</h2>
            <ul>
                <li>Update your name/password on the Account page.</li>
                <li>Revoke microphone, notification, or related permissions in system settings.</li>
                <li>Delete your account as described above.</li>
                <li>Email <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a> for privacy questions.</li>
            </ul>

            <h2>Changes</h2>
            <p>
                We may update this policy as the product evolves. The “Last updated” date at the top will change when we do.
                Continued use of Sound Mix Live after an update means you accept the revised policy.
            </p>
        </div>
    </section>

    @include('partials.marketing-footer')
</body>
</html>
