@extends('layouts.auth')

@section('title', 'Set a new password')

@section('content')
    <div>
        <h1 class="auth-title">Set a new password</h1>
        <p class="auth-subtitle">
            Choose a new password for your SoundMix account.
        </p>

        @if (session('status'))
            <div class="auth-alert-success" role="status">
                <svg class="h-4 w-4 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                </svg>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        <form method="POST" action="{{ route('password.store') }}" class="auth-form" novalidate>
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            {{-- Email Address --}}
            @php
                $presetEmail = old('email', $request->email ?? $request->query('email'));
            @endphp
            <div class="auth-field-group">
                <label for="email" class="auth-label">Email address</label>
                @if ($presetEmail)
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value="{{ $presetEmail }}"
                        required
                        readonly
                        autocomplete="username"
                        aria-describedby="email-hint @error('email') email-error @enderror"
                        aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                        class="auth-input bg-[var(--app-surface-muted)] cursor-not-allowed @error('email') is-invalid @enderror"
                    >
                    <p class="text-[11px] text-[var(--app-text-muted)] mt-0.5" id="email-hint">
                        Resetting password for this verified account email.
                    </p>
                @else
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
                @endif
                @error('email')
                    <p class="auth-error-msg" id="email-error" role="alert">
                        <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                        </svg>
                        <span>{{ $message }}</span>
                    </p>
                @enderror
            </div>

            {{-- New Password with Visibility Toggle --}}
            <div class="auth-field-group">
                <label for="password" class="auth-label">New password</label>
                <div class="auth-input-password-wrap">
                    <input
                        id="password"
                        type="password"
                        name="password"
                        required
                        @if (! $presetEmail) autofocus @endif
                        autocomplete="new-password"
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
                        <svg class="auth-eye-open h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.964-7.178Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        </svg>
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

            {{-- Confirm New Password with Visibility Toggle --}}
            <div class="auth-field-group">
                <label for="password_confirmation" class="auth-label">Confirm new password</label>
                <div class="auth-input-password-wrap">
                    <input
                        id="password_confirmation"
                        type="password"
                        name="password_confirmation"
                        required
                        autocomplete="new-password"
                        placeholder="••••••••"
                        aria-describedby="@error('password_confirmation') password-confirm-error @enderror"
                        aria-invalid="{{ $errors->has('password_confirmation') ? 'true' : 'false' }}"
                        class="auth-input @error('password_confirmation') is-invalid @enderror"
                    >
                    <button
                        type="button"
                        class="auth-password-toggle"
                        aria-label="Show password confirmation"
                        title="Show password confirmation"
                        onclick="toggleAuthPassword(this)"
                    >
                        <svg class="auth-eye-open h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.964-7.178Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        </svg>
                        <svg class="auth-eye-closed hidden h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                        </svg>
                    </button>
                </div>
                @error('password_confirmation')
                    <p class="auth-error-msg" id="password-confirm-error" role="alert">
                        <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                        </svg>
                        <span>{{ $message }}</span>
                    </p>
                @enderror
            </div>

            {{-- Submit Action --}}
            <div class="pt-2">
                <button type="submit" class="auth-btn-primary">
                    <span>Reset password</span>
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                </button>
            </div>
        </form>

        {{-- Secondary Action: Back to sign in --}}
        <div class="mt-6 text-center">
            <a href="{{ route('login') }}" class="auth-btn-secondary">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                </svg>
                <span>Back to sign in</span>
            </a>
        </div>
    </div>
@endsection
