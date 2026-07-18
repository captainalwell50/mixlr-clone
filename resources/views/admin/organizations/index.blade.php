@extends('layouts.app')

@section('title', 'Channels')

@section('content')
    <div class="console-head">
        <div>
            <p class="site-section-label">Operator</p>
            <h1 class="console-title mt-2">Channels</h1>
            <p class="console-lead">Branding, members, and public channel pages.</p>
        </div>
        @if(auth()->user()->is_admin)
            <a href="{{ route('admin.organizations.create') }}" class="console-btn console-btn-primary">New channel</a>
        @endif
    </div>

    <div class="console-table">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Slug</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($organizations as $org)
                    <tr>
                        <td class="text-[var(--stage-cream)]">{{ $org->name }}</td>
                        <td class="text-[var(--stage-muted)]">{{ $org->slug }}</td>
                        <td class="text-right whitespace-nowrap">
                            <a href="{{ route('channels.show', $org) }}" class="console-muted-link" target="_blank">Page</a>
                            <span class="text-[var(--stage-muted)]">·</span>
                            <a href="{{ route('admin.organizations.members', $org) }}" class="console-muted-link">Members</a>
                            <span class="text-[var(--stage-muted)]">·</span>
                            <a href="{{ route('admin.organizations.edit', $org) }}" class="console-link">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="py-8 text-center text-[var(--stage-muted)]">No organizations yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $organizations->links() }}
    </div>
@endsection
