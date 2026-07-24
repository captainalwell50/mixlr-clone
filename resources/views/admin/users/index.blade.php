@extends('layouts.app')

@section('title', 'Users')

@section('content')
    <div class="console-head">
        <div>
            <p class="site-section-label">Operator</p>
            <h1 class="console-title mt-2">Users</h1>
            <p class="console-lead">Create accounts, set passwords, and grant platform admin access.</p>
        </div>
        <a href="{{ route('admin.users.create') }}" class="console-btn console-btn-primary">New user</a>
    </div>

    @if (session('error'))
        <p class="mb-4 rounded-lg border border-red-500/40 bg-red-500/10 px-4 py-3 text-sm text-red-200">{{ session('error') }}</p>
    @endif
    @if (session('status'))
        <p class="mb-4 rounded-lg border border-[var(--stage-accent)]/40 bg-[var(--stage-accent)]/10 px-4 py-3 text-sm text-[var(--stage-cream)]">{{ session('status') }}</p>
    @endif

    <form method="GET" action="{{ route('admin.users.index') }}" class="mb-4 flex flex-wrap gap-2">
        <input
            type="search"
            name="q"
            value="{{ $q }}"
            placeholder="Search name or email"
            class="min-w-[16rem] flex-1 rounded-lg border border-[var(--stage-border)] bg-transparent px-3 py-2 text-sm"
        >
        <button type="submit" class="console-btn console-btn-ghost">Search</button>
    </form>

    <div class="console-table">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Channels</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td class="text-[var(--stage-cream)] font-medium">{{ $user->name }}</td>
                        <td class="text-[var(--stage-muted)]">{{ $user->email }}</td>
                        <td class="text-[var(--stage-muted)]">
                            {{ $user->is_admin ? 'Platform admin' : 'User' }}
                        </td>
                        <td class="text-[var(--stage-muted)]">{{ $user->organizations_count }}</td>
                        <td class="text-right whitespace-nowrap">
                            <a href="{{ route('admin.users.edit', $user) }}" class="console-link">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-[var(--stage-muted)]">No users found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $users->links() }}
    </div>
@endsection
