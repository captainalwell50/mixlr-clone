@extends('layouts.app')

@section('title', 'Register')

@section('content')
    <div class="mx-auto max-w-md">
        <p class="site-section-label">Live Mix Audio</p>
        <h1 class="console-title mt-2">Start broadcasting</h1>
        <p class="console-lead">Create your creator account — then set up a church, radio, or event channel and open Studio.</p>

        <form method="POST" action="{{ route('register') }}" class="mt-8 space-y-4">
            @csrf
            <div>
                <label for="name">Name</label>
                <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name">
            </div>
            <div>
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username">
            </div>
            <div>
                <label for="password">Password</label>
                <input id="password" type="password" name="password" required autocomplete="new-password">
            </div>
            <div>
                <label for="password_confirmation">Confirm password</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
            </div>
            <button type="submit" class="console-btn console-btn-primary w-full">Register</button>
        </form>

        <p class="mt-6 text-center text-sm text-[var(--stage-muted)]">
            Already registered?
            <a href="{{ route('login') }}" class="console-link">Log in</a>
        </p>
    </div>
@endsection
