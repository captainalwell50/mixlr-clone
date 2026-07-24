@extends('layouts.app')

@section('title', $user->exists ? 'Edit '.$user->name : 'New user')

@section('content')
    <div class="console-head">
        <div>
            <p class="site-section-label">Operator</p>
            <h1 class="console-title mt-2">{{ $user->exists ? 'Edit user' : 'New user' }}</h1>
            <p class="console-lead">
                {{ $user->exists ? 'Update profile, password, or admin access.' : 'Create a login and optionally grant platform admin.' }}
            </p>
        </div>
        <a href="{{ route('admin.users.index') }}" class="console-btn console-btn-ghost">All users</a>
    </div>

    @if (session('error'))
        <p class="mb-4 rounded-lg border border-red-500/40 bg-red-500/10 px-4 py-3 text-sm text-red-200">{{ session('error') }}</p>
    @endif
    @if (session('status'))
        <p class="mb-4 rounded-lg border border-[var(--stage-accent)]/40 bg-[var(--stage-accent)]/10 px-4 py-3 text-sm text-[var(--stage-cream)]">{{ session('status') }}</p>
    @endif

    <form method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}" class="space-y-8">
        @csrf
        @if ($user->exists)
            @method('PUT')
        @endif

        <section class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-6 space-y-4">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-[var(--stage-muted)]">Profile</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block text-sm">
                    <span class="text-[var(--stage-muted)]">Name</span>
                    <input name="name" value="{{ old('name', $user->name) }}" required class="mt-1 w-full rounded-lg border border-[var(--stage-border)] bg-transparent px-3 py-2">
                    @error('name') <span class="mt-1 block text-xs text-red-300">{{ $message }}</span> @enderror
                </label>
                <label class="block text-sm">
                    <span class="text-[var(--stage-muted)]">Email</span>
                    <input type="email" name="email" value="{{ old('email', $user->email) }}" required class="mt-1 w-full rounded-lg border border-[var(--stage-border)] bg-transparent px-3 py-2">
                    @error('email') <span class="mt-1 block text-xs text-red-300">{{ $message }}</span> @enderror
                </label>
                <label class="flex items-center gap-2 text-sm mt-2 sm:col-span-2">
                    <input type="checkbox" name="is_admin" value="1" @checked(old('is_admin', $user->is_admin))>
                    <span>Platform admin (full operator access)</span>
                </label>
            </div>
        </section>

        <section class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-6 space-y-4">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-[var(--stage-muted)]">Password</h2>
            <p class="text-sm text-[var(--stage-muted)]">
                {{ $user->exists ? 'Leave blank to keep the current password.' : 'Required for new accounts.' }}
            </p>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block text-sm">
                    <span class="text-[var(--stage-muted)]">{{ $user->exists ? 'New password' : 'Password' }}</span>
                    <input type="password" name="password" {{ $user->exists ? '' : 'required' }} autocomplete="new-password" class="mt-1 w-full rounded-lg border border-[var(--stage-border)] bg-transparent px-3 py-2">
                    @error('password') <span class="mt-1 block text-xs text-red-300">{{ $message }}</span> @enderror
                </label>
                <label class="block text-sm">
                    <span class="text-[var(--stage-muted)]">Confirm password</span>
                    <input type="password" name="password_confirmation" {{ $user->exists ? '' : 'required' }} autocomplete="new-password" class="mt-1 w-full rounded-lg border border-[var(--stage-border)] bg-transparent px-3 py-2">
                </label>
            </div>
        </section>

        @if ($user->exists && $user->organizations->isNotEmpty())
            <section class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-6 space-y-3">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-[var(--stage-muted)]">Channels</h2>
                <ul class="space-y-2 text-sm">
                    @foreach ($user->organizations as $org)
                        <li class="flex flex-wrap items-center justify-between gap-2">
                            <span class="text-[var(--stage-cream)]">{{ $org->name }} <span class="text-[var(--stage-muted)]">({{ $org->pivot->role }})</span></span>
                            <a href="{{ route('admin.organizations.members', $org) }}" class="console-link">Members</a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <div class="flex flex-wrap gap-3">
            <button type="submit" class="console-btn console-btn-primary">
                {{ $user->exists ? 'Save user' : 'Create user' }}
            </button>
            @if ($user->exists && ! auth()->user()->is($user))
                <button
                    type="submit"
                    form="user-delete"
                    class="console-btn console-btn-ghost"
                    onclick="return confirm('Delete this user permanently?')"
                >Delete</button>
            @endif
        </div>
    </form>

    @if ($user->exists && ! auth()->user()->is($user))
        <form id="user-delete" method="POST" action="{{ route('admin.users.destroy', $user) }}" class="hidden">
            @csrf
            @method('DELETE')
        </form>
    @endif
@endsection
