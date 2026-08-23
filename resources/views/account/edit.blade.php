@extends('layouts.app')

@section('title', 'Account')

@section('content')
    <div class="console-head">
        <div>
            <p class="site-section-label">Account</p>
            <h1 class="console-title mt-2">Your account</h1>
            <p class="console-lead">Update your display name and password, or delete your account.</p>
        </div>
    </div>

    @if (session('status'))
        <p class="mb-4 rounded-lg border border-[var(--stage-accent)]/40 bg-[var(--stage-accent)]/10 px-4 py-3 text-sm text-[var(--stage-cream)]">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="mb-4 rounded-lg border border-red-900/60 bg-red-950/40 px-4 py-3 text-sm text-red-200">{{ session('error') }}</p>
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

    <section class="mt-10 max-w-xl space-y-4">
        <h2 class="text-lg font-semibold text-[var(--stage-cream)]">Legal &amp; support</h2>
        <p class="text-sm text-[var(--stage-muted)]">
            <a class="underline" href="{{ route('legal.privacy') }}">Privacy Policy</a>
            ·
            <a class="underline" href="{{ route('legal.terms') }}">Terms of Service</a>
            ·
            <a class="underline" href="{{ route('legal.support') }}">Support</a>
            ({{ $supportEmail }})
        </p>
    </section>

    <section class="mt-10 max-w-xl rounded-xl border border-red-900/50 bg-red-950/20 p-6 space-y-4">
        <h2 class="text-lg font-semibold text-red-200">Delete account</h2>
        <p class="text-sm text-[var(--stage-muted)]">
            This permanently removes your login, API tokens, and channel memberships.
            Organizations and stream content may remain for other organizers or platform admins.
            Type <strong class="text-red-100">DELETE</strong> and enter your password to confirm.
        </p>
        <form method="POST" action="{{ route('account.destroy') }}" class="space-y-4"
              onsubmit="return confirm('Delete your Sound Mix Live account permanently?');">
            @csrf
            @method('DELETE')
            <label class="block text-sm">
                <span class="text-[var(--stage-muted)]">Type DELETE</span>
                <input name="confirm" required autocomplete="off" class="mt-1 w-full rounded-lg border border-red-900/50 bg-transparent px-3 py-2">
                @error('confirm') <span class="mt-1 block text-xs text-red-300">{{ $message }}</span> @enderror
            </label>
            <label class="block text-sm">
                <span class="text-[var(--stage-muted)]">Password</span>
                <input type="password" name="password" required autocomplete="current-password" class="mt-1 w-full rounded-lg border border-red-900/50 bg-transparent px-3 py-2">
                @error('password') <span class="mt-1 block text-xs text-red-300">{{ $message }}</span> @enderror
            </label>
            <button type="submit" class="console-btn" style="background:#7f1d1d;color:#fecaca;">Delete my account</button>
        </form>
    </section>
@endsection
