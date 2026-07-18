@extends('layouts.app')

@section('title', 'Log in')

@section('content')
    <div class="mx-auto max-w-md">
        <p class="site-section-label">Live Mix Audio</p>
        <h1 class="console-title mt-2">Log in</h1>
        <p class="console-lead">Access your account or the operator console.</p>

        <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-4">
            @csrf
            <div>
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
            </div>
            <div>
                <label for="password">Password</label>
                <input id="password" type="password" name="password" required autocomplete="current-password">
            </div>
            <label class="flex items-center gap-2 text-sm text-[var(--stage-muted)]">
                <input type="checkbox" name="remember">
                Remember me
            </label>
            <button type="submit" class="console-btn console-btn-primary w-full">Log in</button>
        </form>

        @if (config('app.registration_enabled') && Route::has('register'))
            <p class="mt-6 text-center text-sm text-[var(--stage-muted)]">
                No account?
                <a href="{{ route('register') }}" class="console-link">Register</a>
            </p>
        @endif
    </div>
@endsection
