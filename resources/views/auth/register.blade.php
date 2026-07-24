@extends('layouts.app')

@section('title', 'Register')

@section('content')
    <div class="mx-auto max-w-md">
        <p class="site-section-label">Sound Mix Live</p>
        <h1 class="console-title mt-2">Start broadcasting</h1>
        <p class="console-lead">Create your creator account — then set up a church, radio, or event channel and open Studio.</p>

        <form method="POST" action="{{ route('register') }}" class="mt-8 space-y-4" id="register-form">
            @csrf

            {{-- Honeypot: leave empty. Hidden from humans; bots often fill it. --}}
            <div class="absolute -left-[9999px] h-0 w-0 overflow-hidden" aria-hidden="true">
                <label for="website">Website</label>
                <input
                    id="website"
                    type="text"
                    name="{{ config('registration.honeypot', 'website') }}"
                    value=""
                    tabindex="-1"
                    autocomplete="off"
                >
            </div>

            <div>
                <label for="name">Name</label>
                <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name">
                @error('name')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username">
                @error('email')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="password">Password</label>
                <input id="password" type="password" name="password" required autocomplete="new-password">
                @error('password')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="password_confirmation">Confirm password</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
            </div>

            @if (! empty($turnstileSiteKey))
                <div class="cf-turnstile" data-sitekey="{{ $turnstileSiteKey }}" data-theme="dark"></div>
                @error('cf-turnstile-response')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            @endif

            <button type="submit" class="console-btn console-btn-primary w-full">Register</button>
        </form>

        <p class="mt-6 text-center text-sm text-[var(--stage-muted)]">
            Already registered?
            <a href="{{ route('login') }}" class="console-link">Log in</a>
        </p>
    </div>

    @if (! empty($turnstileSiteKey))
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    @endif
@endsection
