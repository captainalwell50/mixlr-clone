<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Store listing kit — {{ config('app.name', 'Sound Mix Live') }}</title>
    <meta name="description" content="Internal Play Store listing kit for Sound Mix Live — copy and assets.">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=fraunces:500,600,700|source-sans-3:400,500,600,700" rel="stylesheet" />
    @vite(['resources/css/app.css'])
</head>
<body class="stage-body marketing-body">
    <header class="mkt-header">
        <div class="mkt-header-inner">
            @include('partials.brand-logo')
            {{-- Intentionally no marketing-nav: URL-only listing kit --}}
        </div>
    </header>

    <main class="listing-page">
        <header class="listing-hero">
            <p class="site-section-label">Unlisted</p>
            <h1 class="mkt-page-title">Play Store listing kit</h1>
            <p class="mkt-section-lede">
                Copy and image assets for the Sound Mix Live Google Play listing.
                This page is not linked from the public site menu.
            </p>
            <p class="listing-meta">
                App version <strong>{{ $appVersion }}</strong>
                · Package <code>com.livemixaudio.live_mix</code>
            </p>
        </header>

        <section class="listing-section" aria-labelledby="listing-copy-heading">
            <h2 id="listing-copy-heading" class="listing-section-title">Store copy</h2>

            <div class="listing-field">
                <div class="listing-field-head">
                    <h3>App name / title</h3>
                    <span class="listing-char">{{ strlen($title) }} / 30</span>
                </div>
                <pre class="listing-copy" id="listing-title">{{ $title }}</pre>
                <button type="button" class="site-btn site-btn-ghost listing-copy-btn" data-copy="listing-title">Copy</button>
            </div>

            <div class="listing-field">
                <div class="listing-field-head">
                    <h3>Short description</h3>
                    <span class="listing-char {{ strlen($shortDescription) > 80 ? 'is-over' : '' }}">{{ strlen($shortDescription) }} / 80</span>
                </div>
                <pre class="listing-copy" id="listing-short">{{ $shortDescription }}</pre>
                <button type="button" class="site-btn site-btn-ghost listing-copy-btn" data-copy="listing-short">Copy</button>
            </div>

            <div class="listing-field">
                <div class="listing-field-head">
                    <h3>Full description</h3>
                    <span class="listing-char">{{ strlen($fullDescription) }} chars</span>
                </div>
                <pre class="listing-copy listing-copy-full" id="listing-full">{{ $fullDescription }}</pre>
                <button type="button" class="site-btn site-btn-ghost listing-copy-btn" data-copy="listing-full">Copy</button>
            </div>

            <dl class="listing-meta-grid">
                <div>
                    <dt>Category</dt>
                    <dd>Music &amp; Audio</dd>
                </div>
                <div>
                    <dt>Contact</dt>
                    <dd><a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a></dd>
                </div>
                <div>
                    <dt>Privacy</dt>
                    <dd><a href="{{ route('legal.privacy') }}">{{ url('/privacy') }}</a></dd>
                </div>
                <div>
                    <dt>Terms</dt>
                    <dd><a href="{{ route('legal.terms') }}">{{ url('/terms') }}</a></dd>
                </div>
                <div>
                    <dt>Support</dt>
                    <dd><a href="{{ route('legal.support') }}">{{ url('/support') }}</a></dd>
                </div>
            </dl>
        </section>

        <section class="listing-section" aria-labelledby="listing-assets-heading">
            <h2 id="listing-assets-heading" class="listing-section-title">Play Store images</h2>
            <p class="listing-section-lede">
                Final pixel sizes ready for Play Console upload. Download each file as needed.
            </p>

            <div class="listing-assets">
                @foreach ($assets as $asset)
                    <figure class="listing-asset">
                        <a class="listing-asset-preview" href="{{ $asset['url'] }}" target="_blank" rel="noopener">
                            <img
                                src="{{ $asset['url'] }}"
                                alt="{{ $asset['label'] }}"
                                width="{{ $asset['width'] }}"
                                height="{{ $asset['height'] }}"
                                loading="lazy"
                                class="{{ $asset['tall'] ?? false ? 'is-tall' : '' }}"
                            >
                        </a>
                        <figcaption>
                            <strong>{{ $asset['label'] }}</strong>
                            <span>{{ $asset['width'] }}×{{ $asset['height'] }} · {{ $asset['note'] }}</span>
                            <a class="site-btn site-btn-primary listing-dl" href="{{ $asset['url'] }}" download="{{ $asset['filename'] }}">Download</a>
                        </figcaption>
                    </figure>
                @endforeach
            </div>
        </section>

        <section class="listing-section" aria-labelledby="listing-downloads-heading">
            <h2 id="listing-downloads-heading" class="listing-section-title">Binary / downloads</h2>
            <p class="listing-section-lede">
                Use the public downloads page for store badges and desktop builds.
                APK is available when <code>DOWNLOAD_ANDROID_APK_URL</code> is set.
            </p>
            <div class="listing-actions">
                <a href="{{ route('downloads') }}" class="site-btn site-btn-primary">All download options</a>
                @if (! empty($apkUrl))
                    <a href="{{ $apkUrl }}" class="site-btn site-btn-ghost">Download Android APK</a>
                @else
                    <span class="listing-soon">Android APK URL not configured</span>
                @endif
            </div>
        </section>
    </main>

    <footer class="mkt-footer">
        <div class="mkt-footer-inner">
            @include('partials.brand-logo', ['compact' => true, 'onDark' => true])
            <p>Store listing prep — not linked from public navigation.</p>
            <p class="mkt-footer-support">
                Support:
                <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>
            </p>
        </div>
    </footer>

    <script>
        document.querySelectorAll('[data-copy]').forEach(function (btn) {
            btn.addEventListener('click', async function () {
                var el = document.getElementById(btn.getAttribute('data-copy'));
                if (!el) return;
                try {
                    await navigator.clipboard.writeText(el.textContent);
                    var prev = btn.textContent;
                    btn.textContent = 'Copied';
                    setTimeout(function () { btn.textContent = prev; }, 1500);
                } catch (e) {
                    btn.textContent = 'Select & copy';
                }
            });
        });
    </script>
</body>
</html>
