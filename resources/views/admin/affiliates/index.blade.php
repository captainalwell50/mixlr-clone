@extends('layouts.admin')

@section('title', 'Affiliates')

@section('content')
    @include('admin.affiliate-programme.partials.nav')
    <div class="console-head">
        <div>
            <p class="site-section-label">Platform Admin</p>
            <h1 class="console-title mt-2">Affiliates</h1>
            <p class="console-lead">Referral identity, approval, and location. Commissions and payouts are not on this screen.</p>
        </div>
        <a href="{{ route('admin.affiliates.create') }}" class="console-btn console-btn-primary">New affiliate</a>
    </div>

    @if (session('error'))
        <p class="mb-4 rounded-lg border border-red-500/40 bg-red-500/10 px-4 py-3 text-sm text-red-200">{{ session('error') }}</p>
    @endif

    <form method="GET" action="{{ route('admin.affiliates.index') }}" class="mb-4 flex flex-wrap gap-2">
        <input
            type="search"
            name="q"
            value="{{ $q }}"
            placeholder="Search name, email, code, or city"
            class="min-w-[16rem] flex-1 rounded-lg border border-[var(--stage-border)] bg-transparent px-3 py-2 text-sm"
        >
        <button type="submit" class="console-btn console-btn-ghost">Search</button>
    </form>

    <div class="console-table">
        <table>
            <thead>
                <tr>
                    <th>Affiliate</th>
                    <th>Code</th>
                    <th>Status</th>
                    <th>Location</th>
                    <th>Channels</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($affiliates as $affiliate)
                    <tr>
                        <td>
                            <div class="font-medium text-[var(--stage-cream)]">{{ $affiliate->user?->name ?? 'Unknown' }}</div>
                            <div class="text-[var(--stage-muted)]">{{ $affiliate->user?->email }}</div>
                        </td>
                        <td class="font-mono text-[var(--stage-cream)]">{{ $affiliate->referral_code }}</td>
                        <td class="text-[var(--stage-muted)]">{{ $affiliate->status->label() }}</td>
                        <td class="text-[var(--stage-muted)]">{{ $affiliate->city }}, {{ $affiliate->state_region }} ({{ $affiliate->country_code }})</td>
                        <td class="text-[var(--stage-muted)]">{{ $affiliate->attributions_count }}</td>
                        <td class="text-right whitespace-nowrap">
                            <a href="{{ route('admin.affiliates.show', $affiliate) }}" class="console-link">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-[var(--stage-muted)]">No affiliates yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $affiliates->links() }}
    </div>
@endsection
