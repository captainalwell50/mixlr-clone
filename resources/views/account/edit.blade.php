@extends('layouts.app')

@section('title', 'Account')

@section('content')
    <div class="console-head">
        <div>
            <p class="site-section-label">Account</p>
            <h1 class="console-title mt-2">Your account</h1>
            <p class="console-lead">Update your display name and password.</p>
        </div>
    </div>

    @if (session('status'))
        <p class="mb-4 rounded-lg border border-[var(--stage-accent)]/40 bg-[var(--stage-accent)]/10 px-4 py-3 text-sm text-[var(--stage-cream)]">{{ session('status') }}</p>
    @endif

    <form method="POST" action="{{ route('account.update') }}" class="max-w-xl space-y-6">
        @csrf
        @method('PUT')

        <section class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-6 space-y-4">
            <label class="block text-sm">
                <span class="text-[var(--stage-muted)]">Name</span>
                <input name="name" value="{{ old('name', $user->name) }}" required class="mt-1 w-full rounded-lg border border-[var(--stage-border)] bg-transparent px-3 py-2">
                @error('name') <span class="mt-1 block text-xs text-red-300">{{ $message }}</span> @enderror
            </label>
            <label class="block text-sm">
                <span class="text-[var(--stage-muted)]">Email</span>
                <input type="email" value="{{ $user->email }}" disabled class="mt-1 w-full rounded-lg border border-[var(--stage-border)] bg-transparent px-3 py-2 opacity-60">
                <span class="mt-1 block text-xs text-[var(--stage-muted)]">Ask a platform admin to change your email.</span>
            </label>
            <label class="block text-sm">
                <span class="text-[var(--stage-muted)]">Current password</span>
                <input type="password" name="current_password" required autocomplete="current-password" class="mt-1 w-full rounded-lg border border-[var(--stage-border)] bg-transparent px-3 py-2">
                @error('current_password') <span class="mt-1 block text-xs text-red-300">{{ $message }}</span> @enderror
            </label>
            <label class="block text-sm">
                <span class="text-[var(--stage-muted)]">New password</span>
                <input type="password" name="password" required autocomplete="new-password" class="mt-1 w-full rounded-lg border border-[var(--stage-border)] bg-transparent px-3 py-2">
                @error('password') <span class="mt-1 block text-xs text-red-300">{{ $message }}</span> @enderror
            </label>
            <label class="block text-sm">
                <span class="text-[var(--stage-muted)]">Confirm new password</span>
                <input type="password" name="password_confirmation" required autocomplete="new-password" class="mt-1 w-full rounded-lg border border-[var(--stage-border)] bg-transparent px-3 py-2">
            </label>
        </section>

        <button type="submit" class="console-btn console-btn-primary">Save account</button>
    </form>
@endsection
