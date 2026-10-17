@extends('layouts.app')

@section('title', 'My Earnings & Statement')

@section('content')
    <div class="mb-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="site-section-label">Affiliate Partner</p>
                <h1 class="text-2xl font-bold text-[var(--stage-cream)]">My Earnings & Statement</h1>
                <p class="text-sm text-[var(--stage-muted)]">10% recurring commission on confirmed church subscriptions.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('affiliate.payouts.index') }}" class="console-btn console-btn-primary text-xs">
                    Payouts & Settlement
                </a>
            </div>
        </div>

        <!-- Referral Link Bar -->
        <div class="mt-4 rounded-xl border border-[var(--stage-border)] bg-[var(--stage-surface)] p-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
            <div>
                <span class="block text-xs uppercase tracking-wider text-[var(--stage-muted)]">Your Referral Code</span>
                <span class="font-mono text-lg font-bold text-cyan-400">{{ $affiliate->referral_code }}</span>
                <span class="block text-xs text-[var(--stage-muted)] mt-0.5">Share: {{ url('/r/' . $affiliate->referral_code) }}</span>
            </div>
            <button
                type="button"
                onclick="navigator.clipboard.writeText('{{ url('/r/' . $affiliate->referral_code) }}'); alert('Referral link copied to clipboard!');"
                class="console-btn console-btn-primary text-xs py-1.5 px-3"
            >
                Copy Referral Link
            </button>
        </div>
    </div>

    @include('affiliate.partials.nav')

    <!-- Qualification & Level Cards -->
    <div class="grid gap-4 sm:grid-cols-2 mb-6">
        <div class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-surface)] p-4">
            <span class="block text-xs uppercase tracking-wide text-[var(--stage-muted)]">Payout Qualification</span>
            <div class="mt-2 flex items-center justify-between">
                <div>
                    @if ($balances['is_qualified'])
                        <span class="inline-flex items-center gap-1.5 text-sm font-semibold text-emerald-400">
                            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            Qualified for Payouts (Permanent)
                        </span>
                        <p class="text-xs text-[var(--stage-muted)] mt-1">Earned on {{ $affiliate->payout_qualified_at?->format('M j, Y') ?? 'Confirmed' }}</p>
                    @else
                        <span class="text-sm font-semibold text-amber-400">
                            {{ $balances['qualifying_orgs_count'] }} of {{ $target }} distinct paying churches
                        </span>
                        <p class="text-xs text-[var(--stage-muted)] mt-1">
                            {{ max(0, $target - $balances['qualifying_orgs_count']) }} more paying church(es) needed to unlock payouts.
                        </p>
                    @endif
                </div>
                <div class="text-right">
                    <span class="text-xs text-[var(--stage-muted)]">Requirement</span>
                    <span class="block text-sm font-bold text-[var(--stage-cream)]">{{ $target }} Churches</span>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-surface)] p-4">
            <span class="block text-xs uppercase tracking-wide text-[var(--stage-muted)]">Affiliate Tier</span>
            <div class="mt-2 flex items-center justify-between">
                <div>
                    <span class="text-lg font-bold text-cyan-400">{{ $level['name'] ?? ($tierProgress['current']['name'] ?? 'Starter') }}</span>
                    <p class="text-xs text-[var(--stage-muted)] mt-0.5">
                        @if ($tierProgress['next'])
                            {{ $tierProgress['remaining'] }} more paying churches to reach {{ $tierProgress['next']['name'] }}
                        @else
                            Highest tier achieved
                        @endif
                    </p>
                </div>
                <div class="text-right">
                    <span class="text-xs text-[var(--stage-muted)]">Commission Rate</span>
                    <span class="block text-sm font-bold text-[var(--stage-cream)]">{{ $defaultRatePercent }}%</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Earnings Balance Cards -->
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4 mb-6">
        <div class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-surface)] p-4">
            <span class="block text-xs uppercase text-[var(--stage-muted)]">Payable Now</span>
            <span class="mt-1 block text-xl font-bold text-emerald-400">
                {{ $balances['currency'] }} {{ number_format($balances['payable_minor'] / 100, 2) }}
            </span>
            <span class="text-xs text-[var(--stage-muted)] mt-1 block">Ready for settlement</span>
        </div>
        <div class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-surface)] p-4">
            <span class="block text-xs uppercase text-[var(--stage-muted)]">Held (In Hold)</span>
            <span class="mt-1 block text-xl font-semibold text-amber-400">
                {{ $balances['currency'] }} {{ number_format($balances['held_minor'] / 100, 2) }}
            </span>
            <span class="text-xs text-[var(--stage-muted)] mt-1 block">14-day hold period</span>
        </div>
        <div class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-surface)] p-4">
            <span class="block text-xs uppercase text-[var(--stage-muted)]">Total Unpaid</span>
            <span class="mt-1 block text-xl font-semibold text-[var(--stage-cream)]">
                {{ $balances['currency'] }} {{ number_format($balances['current_unpaid_minor'] / 100, 2) }}
            </span>
            <span class="text-xs text-[var(--stage-muted)] mt-1 block">Payable + Held</span>
        </div>
        <div class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-surface)] p-4">
            <span class="block text-xs uppercase text-[var(--stage-muted)]">Lifetime Paid</span>
            <span class="mt-1 block text-xl font-semibold text-cyan-400">
                {{ $balances['currency'] }} {{ number_format($balances['paid_minor'] / 100, 2) }}
            </span>
            <span class="text-xs text-[var(--stage-muted)] mt-1 block">Settled payouts</span>
        </div>
    </div>

    <!-- Statement Table & Filters -->
    <section class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-surface)] p-5">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
            <h2 class="text-base font-semibold text-[var(--stage-cream)]">Commissions Statement</h2>

            <!-- Status Filter Tabs -->
            <div class="flex items-center gap-1.5 overflow-x-auto scrollbar-none pb-1 sm:pb-0">
                <a
                    href="{{ route('affiliate.commissions.index') }}"
                    class="px-2.5 py-1 text-xs rounded-md font-medium transition {{ empty($selectedStatus) ? 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30 font-semibold' : 'text-[var(--stage-muted)] hover:text-[var(--stage-cream)] hover:bg-[var(--stage-ink)]' }}"
                >
                    All
                </a>
                @foreach ($statuses as $st)
                    <a
                        href="{{ route('affiliate.commissions.index', ['status' => $st->value]) }}"
                        class="px-2.5 py-1 text-xs rounded-md font-medium transition whitespace-nowrap {{ $selectedStatus === $st->value ? 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30 font-semibold' : 'text-[var(--stage-muted)] hover:text-[var(--stage-cream)] hover:bg-[var(--stage-ink)]' }}"
                    >
                        {{ $st->label() }}
                    </a>
                @endforeach
            </div>
        </div>

        <div class="console-table">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Referred Church</th>
                        <th>Subscription Amount</th>
                        <th>Rate</th>
                        <th>Commission</th>
                        <th>Status</th>
                        <th>Settlement Note</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($commissions as $comm)
                        <tr>
                            <td class="text-xs text-[var(--stage-muted)]">{{ $comm->confirmed_at?->format('M j, Y') ?? '—' }}</td>
                            <td>
                                <div class="font-medium text-[var(--stage-cream)]">{{ $comm->organization?->name ?? 'Church' }}</div>
                                <div class="font-mono text-[11px] text-[var(--stage-muted)]">{{ $comm->payment_reference }}</div>
                            </td>
                            <td class="text-[var(--stage-cream)]">{{ $comm->formattedGrossAmount() }}</td>
                            <td class="text-[var(--stage-muted)]">{{ $comm->ratePercent() }}%</td>
                            <td class="font-bold {{ $comm->commission_amount_minor < 0 ? 'text-red-400' : 'text-emerald-400' }}">
                                {{ $comm->formattedCommissionAmount() }}
                            </td>
                            <td>
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium
                                    @if($comm->status->value === 'held') bg-amber-500/20 text-amber-300
                                    @elseif($comm->status->value === 'payable') bg-cyan-500/20 text-cyan-300
                                    @elseif($comm->status->value === 'paid') bg-emerald-500/20 text-emerald-300
                                    @elseif($comm->status->value === 'reversed') bg-red-500/20 text-red-300
                                    @elseif($comm->status->value === 'voided') bg-zinc-700 text-zinc-300
                                    @else bg-zinc-700 text-zinc-300 @endif">
                                    {{ $comm->status->label() }}
                                </span>
                            </td>
                            <td class="text-xs text-[var(--stage-muted)]">
                                @if ($comm->isPaid() && $comm->payout)
                                    <span class="text-emerald-300 font-mono">Paid (Ref: {{ $comm->payout->payout_reference }})</span>
                                @elseif ($comm->isHeld())
                                    <span>Hold expires {{ $comm->hold_until?->format('M j, Y') }}</span>
                                @elseif ($comm->isPayable())
                                    @if ($comm->affiliate_payout_id)
                                        <span class="text-amber-300 font-mono">In payout batch</span>
                                    @else
                                        <span class="text-cyan-300">Ready for payout request</span>
                                    @endif
                                @elseif ($comm->isReversed() || $comm->isVoided())
                                    <span class="text-red-400">Reversed: {{ $comm->reversal_reason ?? 'Payment refunded' }}</span>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-8 text-[var(--stage-muted)]">
                                No commission transactions recorded yet. Share your referral link with church administrators to start earning.
                                @if ($selectedStatus) <span class="text-xs text-zinc-500 block mt-1">(filtered by: {{ $selectedStatus }})</span> @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($commissions->hasPages())
            <div class="mt-4">
                {{ $commissions->links() }}
            </div>
        @endif
    </section>
@endsection
