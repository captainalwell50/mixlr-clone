@extends('layouts.auth')

@section('title', 'Welcome back')

@section('content')
    <div>
        <h1 class="auth-title">Welcome back</h1>
        <p class="auth-subtitle">Sign in to continue to SoundMix.</p>

        @if (session('status'))
            <div class="auth-alert-success" role="status">
                <svg class="h-4 w-4 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                </svg>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @if ($errors->has('throttle'))
            <div class="auth-alert-error" role="alert">
                <svg class="h-4 w-4 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                </svg>
                <span>{{ $errors->first('throttle') }}</span>
            </div>
        @endif

        {{-- Google One-Click Login --}}
        <div class="mb-5">
            <a href="{{ route('auth.google', ['intent' => 'listener', 'intended' => request('intended')]) }}" class="auth-btn-google" id="login-google-btn">
                <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" aria-hidden="true">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                </svg>
                <span>Continue with Google</span>
            </a>

            <div class="auth-divider">
                <span class="auth-divider-text">or sign in with email</span>
            </div>
        </div>

        <form method="POST" action="{{ route('login') }}" class="auth-form" novalidate>
            @csrf

            {{-- Email Field --}}
            <div class="auth-field-group">
                <label for="email" class="auth-label">Email address</label>
                <input
                    id="email"
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    required
                    autofocus
                    autocomplete="username"
                    placeholder="name@example.com"
                    aria-describedby="@error('email') email-error @enderror"
                    aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                    class="auth-input @error('email') is-invalid @enderror"
                >
                @error('email')
                    <p class="auth-error-msg" id="email-error" role="alert">
                        <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                        </svg>
                        <span>{{ $message }}</span>
                    </p>
                @enderror
            </div>

            {{-- Password Field with Visibility Toggle --}}
            <div class="auth-field-group">
                <label for="password" class="auth-label">Password</label>
                <div class="auth-input-password-wrap">
                    <input
                        id="password"
                        type="password"
                        name="password"
                        required
                        autocomplete="current-password"
                        placeholder="••••••••"
                        aria-describedby="@error('password') password-error @enderror"
                        aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                        class="auth-input @error('password') is-invalid @enderror"
                    >
                    <button
                        type="button"
                        class="auth-password-toggle"
                        aria-label="Show password"
                        title="Show password"
                        onclick="toggleAuthPassword(this)"
                    >
                        {{-- Eye Open (default visible) --}}
                        <svg class="auth-eye-open h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.964-7.178Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        </svg>
                        {{-- Eye Closed (hidden when password shown) --}}
                        <svg class="auth-eye-closed hidden h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                        </svg>
                    </button>
                </div>
                @error('password')
                    <p class="auth-error-msg" id="password-error" role="alert">
                        <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                        </svg>
                        <span>{{ $message }}</span>
                    </p>
                @enderror
            </div>

            {{-- Controls: Remember Me + Forgot Password --}}
            <div class="flex items-center justify-between pt-0.5">
                <label class="auth-checkbox-label">
                    <input type="checkbox" name="remember" class="auth-checkbox">
                    <span>Remember me</span>
                </label>

                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="auth-link text-xs">
                        Forgot password?
                    </a>
                @endif
            </div>

            {{-- Submit Action --}}
            <div class="pt-2">
                <button type="submit" class="auth-btn-primary">
                    <span>Sign in</span>
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                    </svg>
                </button>
            </div>
        </form>

        {{-- Secondary Action: Create Account --}}
        @if (Route::has('register'))
            <p class="mt-6 text-center text-xs text-[var(--app-text-muted)]">
                Don't have an account?
                <a href="{{ route('register') }}" class="auth-link font-semibold">
                    Create account
                </a>
            </p>
        @endif
    </div>
@endsection
