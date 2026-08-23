<footer class="mkt-footer">
    <div class="mkt-footer-inner">
        @include('partials.brand-logo', ['compact' => true, 'onDark' => true])
        <p>Channels, events, and a stage made for listening.</p>
        <nav aria-label="Footer">
            <a href="{{ route('how-it-works') }}">How it works</a>
            <a href="{{ route('discover') }}">Discover</a>
            <a href="{{ route('downloads') }}">Download</a>
            <a href="{{ route('archive.index') }}">Podcasts</a>
            <a href="{{ route('legal.privacy') }}">Privacy</a>
            <a href="{{ route('legal.terms') }}">Terms</a>
            <a href="{{ route('legal.support') }}">Support</a>
            @auth
                <a href="{{ url('/dashboard') }}">Dashboard</a>
            @else
                <a href="{{ route('login') }}">Log in</a>
            @endauth
        </nav>
        <p class="mkt-footer-support">
            Support:
            <a href="mailto:{{ config('app.support_email') }}">{{ config('app.support_email') }}</a>
        </p>
    </div>
</footer>
