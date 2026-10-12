@extends('layouts.app')

@section('title', 'My Earnings & Statement')

@section('content')
    <div class="mb-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs uppercase tracking-wider text-[var(--stage-muted)]">Affiliate Partner</p>
                <h1 class="text-2xl font-bold text-white">My Earnings & Statement</h1>
                <p class="text-sm text-[var(--stage-muted)]">10% recurring commission on confirmed church subscriptions.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('affiliate.prospects.index') }}" class="console-btn console-btn-ghost text-xs">
                    My Prospects
                </a>
            </div>
        </div>

        <!-- Referral Link Banner -->
        <div class="mt-4 rounded-xl border border-zinc-800 bg-zinc-900/90 p-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
            <div>
                <span class="block text-xs uppercase tracking-wider text-zinc-400">Your Referral Code</span>
                <span class="font-mono text-lg font-bold text-cyan-300">{{ $affiliate->referral_code }}</span>
                <span class="block text-xs text-zinc-400 mt-0.5">Share: {{ url('/r/' . $affiliate->referral_code) }}</span>
            </div>
            <button type="button" onclick="navigator.clipboard.writeText('{{ url('/r/' . $affiliate->referral_code) }}'); alert('Referral link copied to clipboard!');"
                class="console-btn console-btn-primary text-xs py-1.5 px-3">
                Copy Referral Link
            </button>
        </div>
    </div>

    <!-- Qualification & Level Cards -->
    <div class="grid gap-4 sm:grid-cols-2 mb-6">
        <div class="rounded-xl border border-zinc-800 bg-zinc-900/60 p-4">
            <span class="block text-xs uppercase tracking-wide text-zinc-400">Payout Qualification</span>
            <div class="mt-2 flex items-center justify-between">
                <div>
                    @if ($balances['is_qualified'])
                        <span class="inline-flex items-center gap-1.5 text-sm font-semibold text-emerald-400">
                            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            Qualified for Payouts (Permanent)
                        </span>
                        <p class="text-xs text-zinc-400 mt-1">Earned on {{ $affiliate->payout_qualified_at?->format('M j, Y') }}</p>
                    @else
                        <span class="text-sm font-semibold text-amber-300">
                            {{ $balances['qualifying_orgs_count'] }} of {{ $target }} distinct paying churches
                        </span>
                        <p class="text-xs text-zinc-400 mt-1">
                            {{ max(0, $target - $balances['qualifying_orgs_count']) }} more paying church(es) needed to unlock payouts.
                        </p>
                    @endif
                </div>
                <div class="text-right">
                    <span class="text-xs text-zinc-500">Requirement</span>
                    <span class="block text-sm font-bold text-white">{{ $target }} Churches</span>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-zinc-800 bg-zinc-900/60 p-4">
            <span class="block text-xs uppercase tracking-wide text-zinc-400">Affiliate Level</span>
            <div class="mt-2 flex items-center justify-between">
                <div>
                    <span class="text-lg font-bold text-cyan-300">{{ $level['name'] ?? 'Starter' }}</span>
                    <p class="text-xs text-zinc-400 mt-0.5">Based on your active referred church network</p>
                </div>
                <div class="text-right">
                    <span class="text-xs text-zinc-500">Commission Rate</span>
                    <span class="block text-sm font-bold text-white">{{ $defaultRatePercent }}%</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Earnings Balance Cards -->
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4 mb-8">
        <div class="rounded-xl border border-zinc-800 bg-zinc-900/80 p-4">
            <span class="block text-xs uppercase text-zinc-400">Payable Now</span>
            <span class="mt-1 block text-xl font-bold text-emerald-400">
                {{ $balances['currency'] }} {{ number_format($balances['payable_minor'] / 100, 2) }}
            </span>
            <span class="text-xs text-zinc-500 mt-1 block">Ready for settlement</span>
        </div>
        <div class="rounded-xl border border-zinc-800 bg-zinc-900/80 p-4">
            <span class="block text-xs uppercase text-zinc-400">Held (In Hold)</span>
            <span class="mt-1 block text-xl font-semibold text-amber-300">
                {{ $balances['currency'] }} {{ number_format($balances['held_minor'] / 100, 2) }}
            </span>
            <span class="text-xs text-zinc-500 mt-1 block">14-day hold period</span>
        </div>
        <div class="rounded-xl border border-zinc-800 bg-zinc-900/80 p-4">
            <span class="block text-xs uppercase text-zinc-400">Total Unpaid</span>
            <span class="mt-1 block text-xl font-semibold text-white">
                {{ $balances['currency'] }} {{ number_format($balances['current_unpaid_minor'] / 100, 2) }}
            </span>
            <span class="text-xs text-zinc-500 mt-1 block">Payable + Held</span>
        </div>
        <div class="rounded-xl border border-zinc-800 bg-zinc-900/80 p-4">
            <span class="block text-xs uppercase text-zinc-400">Lifetime Paid</span>
            <span class="mt-1 block text-xl font-semibold text-cyan-300">
                {{ $balances['currency'] }} {{ number_format($balances['paid_minor'] / 100, 2) }}
            </span>
            <span class="text-xs text-zinc-500 mt-1 block">Settled payouts</span>
        </div>
    </div>

    <!-- Statement Table -->
    <section class="rounded-xl border border-zinc-800 bg-zinc-900/40 p-5">
        <h2 class="text-base font-semibold text-white mb-4">Commissions Statement</h2>

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
                            <td class="text-xs text-zinc-400">{{ $comm->confirmed_at?->format('M j, Y') ?? '—' }}</td>
                            <td class="font-medium text-white">{{ $comm->organization?->name ?? 'Church' }}</td>
                            <td class="text-zinc-300">{{ $comm->formattedGrossAmount() }}</td>
                            <td class="text-zinc-400">{{ $comm->ratePercent() }}%</td>
                            <td class="font-bold {{ $comm->commission_amount_minor < 0 ? 'text-red-400' : 'text-emerald-400' }}">
                                {{ $comm->formattedCommissionAmount() }}
                            </td>
                            <td>
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium
                                    @if($comm->status->value === 'held') bg-amber-500/20 text-amber-300
                                    @elseif($comm->status->value === 'payable') bg-cyan-500/20 text-cyan-300
                                    @elseif($comm->status->value === 'paid') bg-emerald-500/20 text-emerald-300
                                    @elseif($comm->status->value === 'reversed') bg-red-500/20 text-red-300
                                    @else bg-zinc-700 text-zinc-300 @endif">
                                    {{ $comm->status->label() }}
                                </span>
                            </td>
                            <td class="text-xs text-zinc-400">
                                @if ($comm->isPaid() && $comm->payout)
                                    <span class="text-emerald-300 font-mono">Paid (Ref: {{ $comm->payout->payout_reference }})</span>
                                @elseif ($comm->isHeld())
                                    <span>Hold expires {{ $comm->hold_until?->format('M j, Y') }}</span>
                                @elseif ($comm->isPayable())
                                    <span class="text-cyan-300">Ready for next payout</span>
                                @elseif ($comm->isReversed() || $comm->isVoided())
                                    <span class="text-red-400">Reversed: {{ $comm->reversal_reason ?? 'Payment refunded' }}</span>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-6 text-zinc-500">
                                No commission transactions recorded yet. Share your referral link with church administrators to start earning.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $commissions->links() }}
        </div>
    </section>
@endsection
