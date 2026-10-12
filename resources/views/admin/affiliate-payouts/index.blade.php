@extends('layouts.admin')

@section('title', 'Affiliate Payouts')

@section('content')
    @include('admin.affiliate-programme.partials.nav')

    <div class="console-head">
        <div>
            <p class="site-section-label">Financial Operations</p>
            <h1 class="console-title mt-2">Affiliate Payouts</h1>
            <p class="console-lead">Manage manual payout batches and settlement for qualified affiliates.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.affiliate-commissions.index') }}" class="console-btn console-btn-ghost">Commissions ledger</a>
            <a href="{{ route('admin.affiliate-payouts.create') }}" class="console-btn console-btn-primary">New payout batch</a>
        </div>
    </div>

    @if (session('status'))
        <p class="mt-4 rounded-lg border border-emerald-500/40 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="mt-4 rounded-lg border border-red-500/40 bg-red-500/10 px-4 py-3 text-sm text-red-200">{{ session('error') }}</p>
    @endif

    @if ($payableAffiliates->isNotEmpty())
        <section class="mt-8 rounded-xl border border-cyan-500/30 bg-cyan-950/20 p-5">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-cyan-300">Affiliates with Payable Balance</h2>
            <p class="mt-1 text-xs text-zinc-300">Qualified affiliates with matured commissions ready to be paid out.</p>

            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($payableAffiliates as $item)
                    @php
                        $aff = $item['affiliate'];
                        $b = $item['balances'];
                    @endphp
                    <div class="rounded-lg border border-cyan-800/40 bg-zinc-900/80 p-3.5 flex items-center justify-between">
                        <div>
                            <p class="font-medium text-white text-sm">{{ $aff->user?->name ?? 'Affiliate' }}</p>
                            <p class="text-xs font-mono text-zinc-400">{{ $aff->referral_code }}</p>
                            <p class="mt-1 text-xs font-semibold text-emerald-400">
                                {{ $b['currency'] }} {{ number_format($b['payable_minor'] / 100, 2) }} payable
                            </p>
                        </div>
                        <a href="{{ route('admin.affiliate-payouts.create', ['affiliate_id' => $aff->id]) }}" class="console-btn console-btn-primary text-xs py-1 px-3">
                            Payout
                        </a>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <div class="mt-8 flex items-center justify-between">
        <h2 class="text-base font-semibold text-white">Payout Batches</h2>
        <div class="flex gap-2">
            <a href="{{ route('admin.affiliate-payouts.index') }}" class="console-btn text-xs {{ ! $status ? 'console-btn-primary' : 'console-btn-ghost' }}">All</a>
            @foreach ($statuses as $st)
                <a href="{{ route('admin.affiliate-payouts.index', ['status' => $st->value]) }}" class="console-btn text-xs {{ $status === $st->value ? 'console-btn-primary' : 'console-btn-ghost' }}">
                    {{ $st->label() }}
                </a>
            @endforeach
        </div>
    </div>

    <div class="console-table mt-4">
        <table>
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Affiliate</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Created By</th>
                    <th>Paid At</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($payouts as $payout)
                    <tr>
                        <td class="font-mono text-xs text-cyan-300 font-semibold">{{ $payout->payout_reference }}</td>
                        <td>
                            <a href="{{ route('admin.affiliates.show', $payout->affiliateAccount) }}" class="underline hover:text-white">
                                {{ $payout->affiliateAccount?->user?->name ?? 'Affiliate' }}
                            </a>
                            <span class="block text-xs font-mono text-[var(--stage-muted)]">{{ $payout->affiliateAccount?->referral_code }}</span>
                        </td>
                        <td class="font-semibold text-white">{{ $payout->formattedAmount() }}</td>
                        <td>
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium
                                @if($payout->status->value === 'approved') bg-cyan-500/20 text-cyan-300
                                @elseif($payout->status->value === 'paid') bg-emerald-500/20 text-emerald-300
                                @elseif($payout->status->value === 'cancelled') bg-red-500/20 text-red-300
                                @else bg-zinc-700 text-zinc-300 @endif">
                                {{ $payout->status->label() }}
                            </span>
                        </td>
                        <td class="text-xs text-[var(--stage-muted)]">
                            {{ $payout->admin?->name ?? 'Admin' }}<br>
                            {{ $payout->created_at->format('Y-m-d H:i') }}
                        </td>
                        <td class="text-xs text-[var(--stage-muted)]">
                            @if ($payout->paid_at)
                                {{ $payout->paid_at->format('Y-m-d H:i') }}<br>
                                <span class="text-emerald-400">by {{ $payout->paidBy?->name ?? 'Admin' }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('admin.affiliate-payouts.show', $payout) }}" class="console-btn console-btn-ghost text-xs py-1 px-2.5">
                                View
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-6 text-[var(--stage-muted)]">No payout batches found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $payouts->links() }}
    </div>
@endsection
