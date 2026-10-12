@extends('layouts.app')

@section('title', 'My prospects')

@section('content')
    <div class="console-head">
        <div>
            <p class="site-section-label">Affiliate</p>
            <h1 class="console-title mt-2">My prospects</h1>
            <p class="console-lead">Plan a visit, a direct email, or a phone call. An active plan reserves that church for the configured protection period.</p>
        </div>
        <a href="{{ route('affiliate.prospects.create') }}" class="console-btn console-btn-primary">Plan a church</a>
    </div>

    @if ($showProspectStatistics)
    <dl class="mt-6 grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
        @foreach ([
            'Active' => $metrics['active'],
            'Visits' => $metrics['visits'],
            'Emails' => $metrics['emails'],
            'Calls' => $metrics['calls'],
            'Converted' => $metrics['converted'],
            'Expired' => $metrics['expired'],
        ] as $label => $value)
            <div class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-4">
                <dt class="text-xs uppercase tracking-wide text-[var(--stage-muted)]">{{ $label }}</dt>
                <dd class="mt-1 text-lg text-[var(--stage-cream)]">{{ $value }}</dd>
            </div>
        @endforeach
    </dl>
    @endif
    @if ($showConversionRate)
    <p class="mt-3 text-sm text-[var(--stage-muted)]">
        Conversion rate:
        {{ $metrics['rate'] === null ? 'No completed plans yet' : $metrics['rate'].'%' }}.
        Converted ÷ plans that were not cancelled.
    </p>
    @endif

    <div class="mt-6 flex flex-wrap gap-2 text-sm">
        @foreach ([
            'active' => 'Active',
            'follow_up' => 'Follow-up',
            'interested' => 'Interested',
            'converted' => 'Converted',
            'expired' => 'Expired',
            'cancelled' => 'Cancelled',
        ] as $key => $label)
            <a href="{{ route('affiliate.prospects.index', ['filter' => $key]) }}"
                class="{{ $filter === $key ? 'console-btn console-btn-primary' : 'console-btn console-btn-ghost' }}">{{ $label }}</a>
        @endforeach
    </div>

    <div class="console-table mt-6">
        <table>
            <thead>
                <tr>
                    <th>Church</th>
                    <th>Place</th>
                    <th>Method</th>
                    <th>Status</th>
                    <th>Planned</th>
                    <th>Reserved until</th>
                    <th>Days left</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($prospects as $prospect)
                    <tr>
                        <td><a href="{{ route('affiliate.prospects.show', $prospect) }}" class="console-link">{{ $prospect->church_name }}</a></td>
                        <td class="text-[var(--stage-muted)]">{{ $prospect->city }}, {{ $prospect->state_region }}</td>
                        <td class="text-[var(--stage-muted)]">{{ $prospect->outreach_method->label() }}</td>
                        <td class="text-[var(--stage-muted)]">{{ $prospect->status->label() }}</td>
                        <td class="text-[var(--stage-muted)]">{{ \App\Support\LocalTime::format($prospect->planned_for, 'M j, Y') ?: '—' }}</td>
                        <td class="text-[var(--stage-muted)]">{{ \App\Support\LocalTime::format($prospect->reserved_until, 'M j, Y') }}</td>
                        <td class="text-[var(--stage-muted)]">{{ $prospect->daysRemaining() }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-[var(--stage-muted)]">No plans in this view.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $prospects->links() }}</div>
@endsection
