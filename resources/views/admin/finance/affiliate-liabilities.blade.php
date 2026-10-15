@extends('layouts.admin')

@section('title', 'Affiliate Financial Liabilities')

@section('content')
<div class="space-y-6 max-w-7xl mx-auto">
    <!-- PAGE HEADER -->
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-zinc-800 pb-5">
        <div>
            <div class="flex items-center gap-2">
                <span class="inline-flex h-2 w-2 rounded-full bg-amber-400"></span>
                <span class="text-xs font-semibold uppercase tracking-wider text-amber-400">Balance Sheet Liabilities</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-white mt-1">Affiliate Liabilities Ledger</h1>
            <p class="text-xs text-zinc-400 mt-0.5">Held and payable affiliate commissions &middot; These obligations represent future payouts and are tracked as liabilities, not deducted from gross revenue.</p>
        </div>
        <div>
            <a href="{{ route('admin.finance.index') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-1.5 text-xs font-medium text-zinc-200 hover:bg-zinc-700 hover:text-white transition">
                &larr; Back to Finance Overview
            </a>
        </div>
    </div>

    @include('admin.finance.partials.nav')

    <!-- SUMMARY CARDS -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <!-- Held Commissions -->
        <div class="rounded-xl border border-zinc-800 bg-zinc-900/50 p-4">
            <div class="text-xs text-zinc-400 uppercase tracking-wider font-semibold">Held Commissions (14-Day Hold Period)</div>
            <div class="mt-2 text-2xl font-bold text-white">
                &#8358;{{ number_format($overview['affiliate_held_minor'] / 100, 2) }}
            </div>
            <p class="mt-1 text-[11px] text-zinc-500">Unvested commissions within 14-day holding protection window governed by hold_until.</p>
        </div>

        <!-- Payable Commissions -->
        <div class="rounded-xl border border-amber-900/40 bg-amber-950/20 p-4">
            <div class="text-xs text-amber-300 uppercase tracking-wider font-semibold">Payable Commissions (Immediate Liability)</div>
            <div class="mt-2 text-2xl font-bold text-amber-400">
                &#8358;{{ number_format($overview['affiliate_payable_minor'] / 100, 2) }}
            </div>
            <p class="mt-1 text-[11px] text-zinc-400">Matured obligations ready for affiliate payout run.</p>
        </div>

        <!-- Paid This Month -->
        <div class="rounded-xl border border-zinc-800 bg-zinc-900/50 p-4">
            <div class="text-xs text-zinc-400 uppercase tracking-wider font-semibold">Disbursed This Month</div>
            <div class="mt-2 text-2xl font-bold text-emerald-400">
                &#8358;{{ number_format($overview['affiliate_paid_this_month_minor'] / 100, 2) }}
            </div>
            <p class="mt-1 text-[11px] text-zinc-500">Settled payouts disbursed to affiliate bank accounts.</p>
        </div>
    </div>

    <!-- PAYABLE COMMISSIONS (READY FOR DISBURSEMENT) -->
    <div class="rounded-xl border border-zinc-800 bg-zinc-900/40 overflow-hidden">
        <div class="p-4 border-b border-zinc-800 flex items-center justify-between">
            <div>
                <h3 class="text-sm font-semibold uppercase tracking-wider text-zinc-200">Payable Commissions (Due)</h3>
                <p class="text-xs text-zinc-400 mt-0.5">Affiliate earnings that have passed their holding period.</p>
            </div>
            <a href="{{ route('admin.affiliate-payouts.create') }}" class="rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-amber-500 transition">
                Create Payout Run &rarr;
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-zinc-800 bg-zinc-950/60 text-[11px] font-bold uppercase tracking-wider text-zinc-400">
                        <th class="py-3 px-4">Affiliate</th>
                        <th class="py-3 px-4">Organisation</th>
                        <th class="py-3 px-4">Payable Since</th>
                        <th class="py-3 px-4 text-right">Commission Amount</th>
                        <th class="py-3 px-4 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800/60 text-zinc-300">
                    @forelse ($payableCommissions as $comm)
                        <tr class="hover:bg-zinc-800/30 transition">
                            <td class="py-3 px-4">
                                <span class="font-semibold text-white">{{ $comm->affiliateAccount?->user?->name ?? 'Affiliate #' . $comm->affiliate_account_id }}</span>
                                <span class="block text-[11px] text-zinc-500 font-mono">{{ $comm->affiliateAccount?->referral_code }}</span>
                            </td>
                            <td class="py-3 px-4">
                                {{ $comm->organization?->name ?? '—' }}
                            </td>
                            <td class="py-3 px-4 font-mono text-[11px] text-zinc-400">
                                {{ $comm->payable_at ? \App\Support\LocalTime::format($comm->payable_at, 'M j, Y') : '—' }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-amber-400 whitespace-nowrap">
                                &#8358;{{ number_format($comm->commission_amount_minor / 100, 2) }}
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="inline-flex items-center gap-1 rounded-full bg-amber-500/10 px-2 py-0.5 text-[10px] font-semibold text-amber-300 border border-amber-800/60">
                                    Payable
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-6 text-center text-zinc-500">
                                No payable commissions currently pending.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- HELD COMMISSIONS (HOLDING WINDOW) -->
    <div class="rounded-xl border border-zinc-800 bg-zinc-900/40 overflow-hidden">
        <div class="p-4 border-b border-zinc-800">
            <h3 class="text-sm font-semibold uppercase tracking-wider text-zinc-200">Held Commissions (14-Day Holding Window)</h3>
            <p class="text-xs text-zinc-400 mt-0.5">Holding buffer (14 days from attribution) protecting against chargebacks and payment reversals, maturing at hold_until.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-zinc-800 bg-zinc-950/60 text-[11px] font-bold uppercase tracking-wider text-zinc-400">
                        <th class="py-3 px-4">Affiliate</th>
                        <th class="py-3 px-4">Organisation</th>
                        <th class="py-3 px-4">Hold Until</th>
                        <th class="py-3 px-4 text-right">Commission Amount</th>
                        <th class="py-3 px-4 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800/60 text-zinc-300">
                    @forelse ($heldCommissions as $comm)
                        <tr class="hover:bg-zinc-800/30 transition">
                            <td class="py-3 px-4">
                                <span class="font-semibold text-white">{{ $comm->affiliateAccount?->user?->name ?? 'Affiliate #' . $comm->affiliate_account_id }}</span>
                                <span class="block text-[11px] text-zinc-500 font-mono">{{ $comm->affiliateAccount?->referral_code }}</span>
                            </td>
                            <td class="py-3 px-4">
                                {{ $comm->organization?->name ?? '—' }}
                            </td>
                            <td class="py-3 px-4 font-mono text-[11px] text-zinc-400">
                                {{ $comm->hold_until ? \App\Support\LocalTime::format($comm->hold_until, 'M j, Y') : '—' }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-white whitespace-nowrap">
                                &#8358;{{ number_format($comm->commission_amount_minor / 100, 2) }}
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="inline-flex items-center gap-1 rounded-full bg-zinc-800 px-2 py-0.5 text-[10px] font-semibold text-zinc-300">
                                    Held
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-6 text-center text-zinc-500">
                                No commissions currently held.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
