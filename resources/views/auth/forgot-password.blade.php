@extends('layouts.auth')

@section('title', 'Forgot Password')

@section('content')
    <div>
        <h1 class="auth-title">Forgot Password</h1>
        <p class="auth-subtitle">
            Enter the email address connected to your SoundMix account and we'll send you a reset link.
        </p>

        @if (session('status'))
            <div class="auth-alert-success" role="status">
                <svg class="h-4 w-4 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                </svg>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        <form method="POST" action="{{ route('password.email') }}" class="auth-form" novalidate>
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

            {{-- Submit Action --}}
            <div class="pt-2">
                <button type="submit" class="auth-btn-primary">
                    <span>Send reset link</span>
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
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
