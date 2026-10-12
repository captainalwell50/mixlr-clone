@extends('layouts.admin')

@section('title', 'Affiliate programme')

@section('content')
    @include('admin.affiliate-programme.partials.nav')

    <div class="console-head">
        <div>
            <p class="site-section-label">Super admin</p>
            <h1 class="console-title mt-2">Affiliate programme</h1>
            <p class="console-lead">Programme totals from affiliate accounts, referrals, and prospect plans. Commission, penalty, and paying-church figures are not calculated.</p>
        </div>
    </div>

    <dl class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
            'Total affiliates' => $counts['total'],
            'Approved' => $counts['approved'],
            'Pending' => $counts['pending'],
            'Suspended' => $counts['suspended'],
            'Referred channels' => $counts['referred'],
            'Active prospect reservations' => $counts['reservations'],
        ] as $label => $value)
            <div class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-4">
                <dt class="text-xs uppercase tracking-wide text-[var(--stage-muted)]">{{ $label }}</dt>
                <dd class="mt-1 text-lg text-[var(--stage-cream)]">{{ $value }}</dd>
            </div>
        @endforeach
        <div class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-4">
            <dt class="text-xs uppercase tracking-wide text-[var(--stage-muted)]">Prospect conversion</dt>
            <dd class="mt-1 text-lg text-[var(--stage-cream)]">{{ $counts['conversion'] === null ? '—' : $counts['conversion'].'%' }}</dd>
            <dd class="mt-1 text-xs text-[var(--stage-muted)]">Converted plans ÷ plans that were not cancelled.</dd>
        </div>
    </dl>

    <section class="mt-8">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-[var(--stage-muted)]">Affiliates by city</h2>
        <div class="console-table mt-3">
            <table>
                <thead>
                    <tr>
                        <th>City</th>
                        <th>State</th>
                        <th>Affiliates</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($cities as $city)
                        <tr>
                            <td>{{ $city->city }}</td>
                            <td class="text-[var(--stage-muted)]">{{ $city->state_region }}</td>
                            <td>{{ $city->total }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-[var(--stage-muted)]">No affiliates yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="mt-8">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-[var(--stage-muted)]">Levels</h2>
        <p class="mt-2 text-sm text-[var(--stage-muted)]">The ladder is configurable. Nobody is placed on a level yet, because active paying churches are counted from collected payments, and those payments are not recorded.</p>
        <div class="console-table mt-3">
            <table>
                <thead>
                    <tr>
                        <th>Level</th>
                        <th>Minimum active paying churches</th>
                        <th>Status</th>
                        <th>Assigned</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($levels as $level)
                        <tr>
                            <td>{{ $level['name'] }}</td>
                            <td>{{ $level['min_active_paying_churches'] }}{{ $loop->last ? '+' : '' }}</td>
                            <td class="text-[var(--stage-muted)]">{{ $level['enabled'] ? 'Enabled' : 'Disabled' }}</td>
                            <td class="text-[var(--stage-muted)]">Not assigned</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="mt-8 grid gap-3 sm:grid-cols-2">
        @foreach ([
            'Active paying referred churches' => 'Not available. Collected payments are not recorded, and payment checkouts are not used.',
            'Pending commissions' => 'Not recorded. No commission ledger has been created.',
            'Payable commissions' => 'Not recorded.',
            'Paid commissions' => 'Not recorded.',
            'Affiliates under warning' => 'Not applied. The warning period is saved in settings only.',
            'Affiliates on the first penalty rate' => 'Not applied. Penalty rates are not written onto affiliates.',
            'Affiliates on the second penalty rate' => 'Not applied.',
            'Retention-protected affiliates' => 'Not calculated. Retention is not measured yet.',
        ] as $label => $note)
            <div class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-4">
                <h3 class="text-sm text-[var(--stage-cream)]">{{ $label }}</h3>
                <p class="mt-2 text-sm text-[var(--stage-muted)]">{{ $note }}</p>
            </div>
        @endforeach
    </section>
@endsection
