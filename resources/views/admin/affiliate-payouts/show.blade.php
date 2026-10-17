@extends('layouts.admin')

@section('title', 'Payout ' . $payout->payout_reference)

@section('content')
    @include('admin.affiliate-programme.partials.nav')

    <div class="console-head">
        <div>
            <p class="site-section-label">Financial Operations</p>
            <h1 class="console-title mt-2 font-mono">{{ $payout->payout_reference }}</h1>
            <p class="console-lead">Payout batch for {{ $payout->affiliateAccount?->user?->name ?? 'Affiliate' }}</p>
        </div>
        <a href="{{ route('admin.affiliate-payouts.index') }}" class="console-btn console-btn-ghost">All payouts</a>
    </div>

    @if (session('status'))
        <p class="mt-4 rounded-lg border border-emerald-500/40 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="mt-4 rounded-lg border border-red-500/40 bg-red-500/10 px-4 py-3 text-sm text-red-200">{{ session('error') }}</p>
    @endif

    <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-4">
            <span class="block text-xs uppercase tracking-wide text-[var(--stage-muted)]">Total Amount</span>
            <span class="mt-1 block text-2xl font-bold text-white">{{ $payout->formattedAmount() }}</span>
        </div>
        <div class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-4">
            <span class="block text-xs uppercase tracking-wide text-[var(--stage-muted)]">Status</span>
            <span class="mt-1 inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold
                @if($payout->status->value === 'approved') bg-cyan-500/20 text-cyan-300
                @elseif($payout->status->value === 'paid') bg-emerald-500/20 text-emerald-300
                @elseif($payout->status->value === 'cancelled') bg-red-500/20 text-red-300
                @else bg-zinc-700 text-zinc-300 @endif">
                {{ $payout->status->label() }}
            </span>
        </div>
        <div class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-4">
            <span class="block text-xs uppercase tracking-wide text-[var(--stage-muted)]">Affiliate</span>
            <a href="{{ route('admin.affiliates.show', $payout->affiliateAccount) }}" class="mt-1 block text-sm font-medium text-cyan-300 underline">
                {{ $payout->affiliateAccount?->user?->name }} ({{ $payout->affiliateAccount?->referral_code }})
            </a>
        </div>
        <div class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-4">
            <span class="block text-xs uppercase tracking-wide text-[var(--stage-muted)]">Commissions</span>
            <span class="mt-1 block text-lg font-semibold text-white">{{ $payout->items->count() }} item(s)</span>
        </div>
    </div>

    <div class="mt-4 rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-4 text-xs">
        <span class="block text-xs uppercase tracking-wide text-[var(--stage-muted)] mb-2 font-semibold">Settlement Bank Destination Snapshot (Encrypted at Rest)</span>
        <div class="grid gap-3 sm:grid-cols-4">
            <div>
                <span class="text-[var(--stage-muted)] block">Bank Name:</span>
                <span class="text-white font-medium">{{ $payout->bank_name ?? 'Not snapshotted' }}</span>
            </div>
            <div>
                <span class="text-[var(--stage-muted)] block">Bank Code:</span>
                <span class="text-white font-mono">{{ $payout->bank_code ?? '—' }}</span>
            </div>
            <div>
                <span class="text-[var(--stage-muted)] block">Account Name:</span>
                <span class="text-white font-medium">{{ $payout->account_name ?? '—' }}</span>
            </div>
            <div>
                <span class="text-[var(--stage-muted)] block">Account Number:</span>
                <span class="text-emerald-400 font-mono font-medium">{{ $payout->maskedAccountNumber() ?? '—' }}</span>
            </div>
        </div>
    </div>

    <div class="mt-4 rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-4 text-xs text-zinc-300">
        <span class="block text-xs uppercase tracking-wide text-[var(--stage-muted)] mb-2 font-semibold">Lifecycle Trail</span>
        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <span class="text-[var(--stage-muted)]">Created:</span>
                <span class="text-white ml-1">{{ $payout->createdBy?->name ?? 'Admin' }} ({{ $payout->created_at->format('Y-m-d H:i') }})</span>
            </div>
            @if ($payout->approved_at)
                <div>
                    <span class="text-[var(--stage-muted)]">Approved:</span>
                    <span class="text-white ml-1">{{ $payout->approvedBy?->name ?? 'Admin' }} ({{ $payout->approved_at->format('Y-m-d H:i') }})</span>
                </div>
            @endif
            @if ($payout->paid_at)
                <div>
                    <span class="text-[var(--stage-muted)]">Paid:</span>
                    <span class="text-white ml-1">{{ $payout->paidBy?->name ?? 'Admin' }} ({{ $payout->paid_at->format('Y-m-d H:i') }})</span>
                </div>
            @endif
            @if ($payout->cancelled_at)
                <div>
                    <span class="text-[var(--stage-muted)]">Cancelled:</span>
                    <span class="text-white ml-1">{{ $payout->cancelledBy?->name ?? 'Admin' }} ({{ $payout->cancelled_at->format('Y-m-d H:i') }})</span>
                </div>
            @endif
        </div>
    </div>

    @if ($payout->notes)
        <div class="mt-4 rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-4">
            <span class="block text-xs uppercase tracking-wide text-[var(--stage-muted)]">Notes / Reference</span>
            <p class="mt-1 text-sm text-zinc-200">{{ $payout->notes }}</p>
        </div>
    @endif

    @if ($payout->status->value === 'cancelled' && $payout->cancellation_reason)
        <div class="mt-4 rounded-xl border border-red-500/30 bg-red-950/20 p-4">
            <span class="block text-xs uppercase tracking-wide text-red-300">Cancellation Reason</span>
            <p class="mt-1 text-sm text-red-200">{{ $payout->cancellation_reason }}</p>
        </div>
    @endif

    <div class="mt-6 flex flex-wrap gap-3">
        @if ($payout->canBeApproved())
            <form method="POST" action="{{ route('admin.affiliate-payouts.approve', $payout) }}">
                @csrf
                <button type="submit" class="console-btn console-btn-primary">Approve payout</button>
            </form>
        @endif

        @if ($payout->canBePaid())
            <form method="POST" action="{{ route('admin.affiliate-payouts.mark-paid', $payout) }}" onsubmit="return confirm('Confirm settlement: mark this payout batch as PAID?');">
                @csrf
                <button type="submit" class="console-btn console-btn-primary bg-emerald-600 hover:bg-emerald-500">Mark as Paid</button>
            </form>
        @endif

        @if ($payout->canBeCancelled())
            <form method="POST" action="{{ route('admin.affiliate-payouts.cancel', $payout) }}" onsubmit="return confirm('Cancel this payout batch and release commissions back to payable?');">
                @csrf
                <input type="hidden" name="reason" value="Admin cancelled payout batch">
                <button type="submit" class="console-btn console-btn-ghost text-red-400 hover:text-red-300">Cancel payout</button>
            </form>
        @endif
    </div>

    <section class="mt-8">
        <h2 class="text-base font-semibold text-white">Included Commissions</h2>
        <div class="console-table mt-4">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Referred Church</th>
                        <th>Payment Reference</th>
                        <th>Gross Amount</th>
                        <th>Rate</th>
                        <th>Commission Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($payout->items as $item)
                        @php $comm = $item->commission; @endphp
                        <tr>
                            <td class="text-xs text-[var(--stage-muted)]">{{ $comm?->confirmed_at?->format('Y-m-d H:i') ?? '—' }}</td>
                            <td class="text-white">{{ $comm?->organization?->name ?? 'Church' }}</td>
                            <td class="font-mono text-xs text-zinc-300">{{ $comm?->payment_reference ?? '—' }}</td>
                            <td>{{ $comm?->formattedGrossAmount() ?? '—' }}</td>
                            <td>{{ $comm?->ratePercent() ?? 10 }}%</td>
                            <td class="font-semibold text-emerald-400">
                                {{ $payout->currency }} {{ number_format($item->amount_minor / 100, 2) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection
