@extends('layouts.admin')

@section('title', 'Affiliate Programme Reports')

@section('content')
    @include('admin.affiliate-programme.partials.nav')

    <div class="console-head mb-6">
        <div>
            <p class="site-section-label">Platform Admin</p>
            <h1 class="console-title mt-2">Affiliate Operational & Financial Reports</h1>
            <p class="console-lead">Authoritative metrics derived directly from the commission ledger, qualification policies, and payout batches.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.affiliates.index') }}" class="console-btn console-btn-ghost text-xs">
                Manage Affiliates
            </a>
            <a href="{{ route('admin.affiliate-payouts.index') }}" class="console-btn console-btn-primary text-xs">
                Payout Operations
            </a>
        </div>
    </div>

    <!-- Monthly Velocity & Growth KPI Cards -->
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <div class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-4">
            <span class="block text-xs uppercase tracking-wide text-[var(--stage-muted)]">Commissions Created (MTD)</span>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl font-bold text-white">
                    {{ $currency }} {{ number_format(($commissionsCreatedThisMonth->total_commission ?? 0) / 100, 2) }}
                </span>
            </div>
            <p class="mt-1 text-xs text-[var(--stage-muted)]">
                From {{ number_format($commissionsCreatedThisMonth->total_count ?? 0) }} subscription transactions (Gross: {{ $currency }} {{ number_format(($commissionsCreatedThisMonth->total_gross ?? 0) / 100, 2) }})
            </p>
        </div>

        <div class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-4">
            <span class="block text-xs uppercase tracking-wide text-[var(--stage-muted)]">Commissions Matured (MTD)</span>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl font-bold text-emerald-400">
                    {{ $currency }} {{ number_format(($commissionsPayableThisMonth->total_amount ?? 0) / 100, 2) }}
                </span>
            </div>
            <p class="mt-1 text-xs text-[var(--stage-muted)]">
                {{ number_format($commissionsPayableThisMonth->total_count ?? 0) }} commissions cleared the 14-day hold this month
            </p>
        </div>

        <div class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-4">
            <span class="block text-xs uppercase tracking-wide text-[var(--stage-muted)]">Payouts Settled (MTD)</span>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl font-bold text-cyan-300">
                    {{ $currency }} {{ number_format(($payoutsPaidThisMonth->total_amount ?? 0) / 100, 2) }}
                </span>
            </div>
            <p class="mt-1 text-xs text-[var(--stage-muted)]">
                {{ number_format($payoutsPaidThisMonth->total_count ?? 0) }} payout batches marked as paid this month
            </p>
        </div>

        <div class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-4">
            <span class="block text-xs uppercase tracking-wide text-[var(--stage-muted)]">Qualification Rate</span>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl font-bold text-amber-300">
                    {{ $qualificationRate }}%
                </span>
            </div>
            <p class="mt-1 text-xs text-[var(--stage-muted)]">
                {{ $qualifiedAffiliates }} of {{ $approvedAffiliates }} approved affiliates reached {{ $target }}+ paying churches
            </p>
        </div>
    </div>

    <!-- Platform Liabilities & Funnel Section -->
    <div class="grid gap-6 lg:grid-cols-2 mb-8">
        <!-- Balance Liabilities -->
        <div class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-surface)] p-5">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-white mb-4">Programme Balance Liabilities</h2>
            <div class="grid grid-cols-3 gap-3 text-center">
                <div class="rounded-lg border border-[var(--stage-border)] bg-[var(--stage-panel)] p-3">
                    <span class="text-xs text-[var(--stage-muted)] block">Held Liability</span>
                    <span class="mt-1 block text-lg font-bold text-amber-300">
                        {{ $currency }} {{ number_format($heldLiability / 100, 2) }}
                    </span>
                    <span class="text-[10px] text-[var(--stage-muted)] block mt-0.5">14-day hold maturing</span>
                </div>
                <div class="rounded-lg border border-[var(--stage-border)] bg-[var(--stage-panel)] p-3">
                    <span class="text-xs text-[var(--stage-muted)] block">Payable Liability</span>
                    <span class="mt-1 block text-lg font-bold text-emerald-400">
                        {{ $currency }} {{ number_format($payableLiability / 100, 2) }}
                    </span>
                    <span class="text-[10px] text-[var(--stage-muted)] block mt-0.5">Ready for payout</span>
                </div>
                <div class="rounded-lg border border-[var(--stage-border)] bg-[var(--stage-panel)] p-3">
                    <span class="text-xs text-[var(--stage-muted)] block">Lifetime Paid</span>
                    <span class="mt-1 block text-lg font-bold text-cyan-300">
                        {{ $currency }} {{ number_format($lifetimeSettled / 100, 2) }}
                    </span>
                    <span class="text-[10px] text-[var(--stage-muted)] block mt-0.5">Settled payouts</span>
                </div>
            </div>

            <div class="mt-4 pt-4 border-t border-[var(--stage-border)] text-xs text-[var(--stage-muted)] space-y-1">
                <p>Total Outstanding Balance: <strong class="text-white">{{ $currency }} {{ number_format(($heldLiability + $payableLiability) / 100, 2) }}</strong></p>
                <p>Total Programme Liability (All time): <strong class="text-white">{{ $currency }} {{ number_format(($heldLiability + $payableLiability + $lifetimeSettled) / 100, 2) }}</strong></p>
            </div>
        </div>

        <!-- Attribution & Conversion Funnel -->
        <div class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-surface)] p-5">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-white mb-4">Attribution & Referral Conversion</h2>
            <div class="grid grid-cols-3 gap-3 text-center">
                <div class="rounded-lg border border-[var(--stage-border)] bg-[var(--stage-panel)] p-3">
                    <span class="text-xs text-[var(--stage-muted)] block">Attributed Channels</span>
                    <span class="mt-1 block text-lg font-bold text-white">{{ number_format($totalAttributions) }}</span>
                    <span class="text-[10px] text-[var(--stage-muted)] block mt-0.5">Referred organisations</span>
                </div>
                <div class="rounded-lg border border-[var(--stage-border)] bg-[var(--stage-panel)] p-3">
                    <span class="text-xs text-[var(--stage-muted)] block">Earning Channels</span>
                    <span class="mt-1 block text-lg font-bold text-cyan-300">{{ number_format($earningAttributions) }}</span>
                    <span class="text-[10px] text-[var(--stage-muted)] block mt-0.5">Non-self referrals</span>
                </div>
                <div class="rounded-lg border border-[var(--stage-border)] bg-[var(--stage-panel)] p-3">
                    <span class="text-xs text-[var(--stage-muted)] block">Paying Churches</span>
                    <span class="mt-1 block text-lg font-bold text-emerald-400">{{ number_format($distinctPayingOrgs) }}</span>
                    <span class="text-[10px] text-[var(--stage-muted)] block mt-0.5">Active or historical</span>
                </div>
            </div>

            <div class="mt-4 pt-4 border-t border-[var(--stage-border)] rounded bg-zinc-900/50 p-2.5 text-xs text-[var(--stage-muted)]">
                <span class="font-semibold text-zinc-300">Telemetry Status:</span> Referral click tracking not currently authoritative. Metrics reflect authoritative database ledger and organization attribution records.
            </div>
        </div>
    </div>

    <!-- Top Affiliates Performance Leaderboard -->
    <section class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-surface)] p-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-semibold text-white">Top Performing Affiliates</h2>
            <span class="text-xs text-[var(--stage-muted)]">Ranked by lifetime earned commission</span>
        </div>

        <div class="console-table">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Affiliate</th>
                        <th>Code</th>
                        <th>Tier</th>
                        <th>Qualification</th>
                        <th>Paying Churches</th>
                        <th>Lifetime Earned</th>
                        <th>Payable</th>
                        <th>Held</th>
                        <th>Paid</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($topAffiliates as $index => $row)
                        <tr>
                            <td class="font-mono text-xs text-[var(--stage-muted)]">{{ $index + 1 }}</td>
                            <td>
                                <div class="font-medium text-white">{{ $row['user']?->name ?? 'Unknown' }}</div>
                                <div class="text-xs text-[var(--stage-muted)]">{{ $row['user']?->email }}</div>
                            </td>
                            <td class="font-mono text-xs text-cyan-300">{{ $row['affiliate']->referral_code }}</td>
                            <td>
                                <span class="text-xs font-semibold text-zinc-300">{{ $row['tier'] }}</span>
                            </td>
                            <td>
                                @if ($row['is_qualified'])
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-500/20 text-emerald-300">
                                        Qualified
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-500/20 text-amber-300">
                                        {{ $row['qualifying_orgs'] }}/{{ $target }}
                                    </span>
                                @endif
                            </td>
                            <td class="font-bold text-white">{{ $row['qualifying_orgs'] }}</td>
                            <td class="font-bold text-emerald-400">
                                {{ $currency }} {{ number_format($row['lifetime_earned_minor'] / 100, 2) }}
                            </td>
                            <td class="text-emerald-300">
                                {{ $currency }} {{ number_format($row['payable_minor'] / 100, 2) }}
                            </td>
                            <td class="text-amber-300">
                                {{ $currency }} {{ number_format($row['held_minor'] / 100, 2) }}
                            </td>
                            <td class="text-cyan-300">
                                {{ $currency }} {{ number_format($row['paid_minor'] / 100, 2) }}
                            </td>
                            <td class="text-right">
                                <a href="{{ route('admin.affiliates.show', $row['affiliate']) }}" class="console-link text-xs">
                                    View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="text-center py-6 text-[var(--stage-muted)]">
                                No active affiliate earnings recorded yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
