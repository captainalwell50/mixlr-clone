@extends('layouts.admin')

@section('title', 'Affiliate Commissions Ledger')

@section('content')
    @include('admin.affiliate-programme.partials.nav')

    <div class="console-head">
        <div>
            <p class="site-section-label">Financial Ledger</p>
            <h1 class="console-title mt-2">Affiliate Commissions</h1>
            <p class="console-lead">Authoritative audit ledger of subscription referral commissions.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.affiliate-payouts.index') }}" class="console-btn console-btn-ghost">View payouts</a>
            <a href="{{ route('admin.affiliate-payouts.create') }}" class="console-btn console-btn-primary">New payout batch</a>
        </div>
    </div>

    @if (session('status'))
        <p class="mt-4 rounded-lg border border-emerald-500/40 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="mt-4 rounded-lg border border-red-500/40 bg-red-500/10 px-4 py-3 text-sm text-red-200">{{ session('error') }}</p>
    @endif

    <form method="GET" action="{{ route('admin.affiliate-commissions.index') }}" class="mt-6 rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-4 flex flex-wrap gap-4 items-end">
        <div>
            <label for="q" class="block text-xs uppercase tracking-wide text-[var(--stage-muted)]">Search</label>
            <input id="q" type="text" name="q" value="{{ $q }}" placeholder="Reference, church, affiliate..." class="mt-1 rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-1.5 text-sm text-white">
        </div>
        <div>
            <label for="status" class="block text-xs uppercase tracking-wide text-[var(--stage-muted)]">Status</label>
            <select id="status" name="status" class="mt-1 rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-1.5 text-sm text-white">
                <option value="">All statuses</option>
                @foreach ($statuses as $st)
                    <option value="{{ $st->value }}" @selected($status === $st->value)>{{ $st->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="date_from" class="block text-xs uppercase tracking-wide text-[var(--stage-muted)]">From</label>
            <input id="date_from" type="date" name="date_from" value="{{ $dateFrom }}" class="mt-1 rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-1.5 text-sm text-white">
        </div>
        <div>
            <label for="date_to" class="block text-xs uppercase tracking-wide text-[var(--stage-muted)]">To</label>
            <input id="date_to" type="date" name="date_to" value="{{ $dateTo }}" class="mt-1 rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-1.5 text-sm text-white">
        </div>
        @if ($selectedAffiliate)
            <input type="hidden" name="affiliate_id" value="{{ $selectedAffiliate->id }}">
        @endif
        <div class="flex gap-2">
            <button type="submit" class="console-btn console-btn-primary">Filter</button>
            <a href="{{ route('admin.affiliate-commissions.index') }}" class="console-btn console-btn-ghost">Reset</a>
        </div>
    </form>

    @if ($selectedAffiliate)
        <div class="mt-4 flex items-center justify-between rounded-lg border border-cyan-500/30 bg-cyan-950/20 px-4 py-2 text-sm text-cyan-200">
            <span>Filtered for affiliate: <strong>{{ $selectedAffiliate->user?->name }}</strong> ({{ $selectedAffiliate->referral_code }})</span>
            <a href="{{ route('admin.affiliate-commissions.index') }}" class="underline">Clear affiliate filter</a>
        </div>
    @endif

    <div class="console-table mt-6">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Affiliate</th>
                    <th>Referred Church</th>
                    <th>Payment Ref</th>
                    <th>Gross</th>
                    <th>Rate</th>
                    <th>Commission</th>
                    <th>Status</th>
                    <th>Hold Until / Payout</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($commissions as $comm)
                    <tr>
                        <td class="text-xs text-[var(--stage-muted)]">{{ $comm->confirmed_at?->format('Y-m-d H:i') ?? '—' }}</td>
                        <td>
                            <a href="{{ route('admin.affiliates.show', $comm->affiliateAccount) }}" class="underline hover:text-white">
                                {{ $comm->affiliateAccount?->user?->name ?? 'Affiliate' }}
                            </a>
                            <span class="block text-xs font-mono text-[var(--stage-muted)]">{{ $comm->affiliateAccount?->referral_code }}</span>
                        </td>
                        <td>{{ $comm->organization?->name ?? '—' }}</td>
                        <td class="font-mono text-xs" title="{{ $comm->payment_reference }}">{{ Str::limit($comm->payment_reference, 16) }}</td>
                        <td>{{ $comm->formattedGrossAmount() }}</td>
                        <td>{{ $comm->ratePercent() }}%</td>
                        <td class="font-semibold {{ $comm->commission_amount_minor < 0 ? 'text-red-400' : 'text-emerald-400' }}">{{ $comm->formattedCommissionAmount() }}</td>
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
                        <td class="text-xs">
                            @if ($comm->payout)
                                <a href="{{ route('admin.affiliate-payouts.show', $comm->payout) }}" class="underline font-mono text-cyan-300">
                                    {{ $comm->payout->payout_reference }}
                                </a>
                            @elseif ($comm->isHeld())
                                <span class="text-[var(--stage-muted)]">Held until {{ $comm->hold_until?->format('M j, Y') }}</span>
                            @elseif ($comm->isPayable())
                                <span class="text-cyan-300">Ready for payout</span>
                            @elseif ($comm->isReversed() || $comm->isVoided())
                                <span class="text-red-300">{{ $comm->reversal_reason ?? 'Reversed' }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            @if (! $comm->isReversed() && ! $comm->isVoided())
                                <form method="POST" action="{{ route('admin.affiliate-commissions.reverse', $comm) }}" onsubmit="return confirm('Reverse this commission?');" class="inline">
                                    @csrf
                                    <input type="hidden" name="reason" value="Admin manual reversal">
                                    <button type="submit" class="text-xs text-red-400 hover:text-red-300 underline">Reverse</button>
                                </form>
                            @else
                                <span class="text-xs text-[var(--stage-muted)]">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="text-center py-6 text-[var(--stage-muted)]">No affiliate commissions found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $commissions->links() }}
    </div>
@endsection
