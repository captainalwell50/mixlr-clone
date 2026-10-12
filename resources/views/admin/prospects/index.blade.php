@extends('layouts.admin')

@section('title', 'Prospects')

@section('content')
    @include('admin.affiliate-programme.partials.nav')
    <div class="console-head">
        <div>
            <p class="site-section-label">Platform Admin</p>
            <h1 class="console-title mt-2">Prospects</h1>
            <p class="console-lead">Church plans and reservations. This screen does not calculate commissions.</p>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.prospects.index') }}" class="mb-4 flex flex-wrap gap-2">
        <input type="search" name="q" value="{{ $q }}" placeholder="Search church, city, or affiliate"
            class="min-w-[16rem] flex-1 rounded-lg border border-[var(--stage-border)] bg-transparent px-3 py-2 text-sm">
        <button type="submit" class="console-btn console-btn-ghost">Search</button>
    </form>

    <div class="console-table">
        <table>
            <thead>
                <tr>
                    <th>Church</th>
                    <th>Affiliate</th>
                    <th>Status</th>
                    <th>Reserved until</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($prospects as $prospect)
                    <tr>
                        <td>
                            <div class="text-[var(--stage-cream)]">{{ $prospect->church_name }}</div>
                            <div class="text-[var(--stage-muted)]">{{ $prospect->city }}, {{ $prospect->state_region }}</div>
                        </td>
                        <td class="text-[var(--stage-muted)]">{{ $prospect->affiliateAccount?->user?->name }}</td>
                        <td class="text-[var(--stage-muted)]">{{ $prospect->status->label() }}</td>
                        <td class="text-[var(--stage-muted)]">{{ \App\Support\LocalTime::format($prospect->reserved_until, 'M j, Y') }}</td>
                        <td class="text-right"><a href="{{ route('admin.prospects.show', $prospect) }}" class="console-link">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-[var(--stage-muted)]">No prospects yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $prospects->links() }}</div>
@endsection
