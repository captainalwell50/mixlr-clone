@extends('layouts.admin')

@section('title', 'Platform Commercial Reports')

@section('content')
<div class="space-y-6 max-w-7xl mx-auto">
    <!-- PAGE HEADER -->
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-zinc-800 pb-5">
        <div>
            <div class="flex items-center gap-2">
                <span class="inline-flex h-2 w-2 rounded-full bg-emerald-400"></span>
                <span class="text-xs font-semibold uppercase tracking-wider text-emerald-400">Commercial Reporting</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-white mt-1">Financial Statements &amp; Reports</h1>
            <p class="text-xs text-zinc-400 mt-0.5">Summary of platform earnings, recurring subscriptions, and outstanding commitments.</p>
        </div>
        <div>
            <a href="{{ route('admin.finance.index') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-1.5 text-xs font-medium text-zinc-200 hover:bg-zinc-700 hover:text-white transition">
                &larr; Back to Finance Overview
            </a>
        </div>
    </div>

    @include('admin.finance.partials.nav')

    <!-- EXECUTIVE REVENUE STATEMENT -->
    <div class="rounded-xl border border-zinc-800 bg-zinc-900/40 p-6 space-y-6">
        <div>
            <h2 class="text-base font-semibold text-white">Platform Revenue Statement (NGN)</h2>
            <p class="text-xs text-zinc-400 mt-0.5">Derived exclusively from confirmed transactions &middot; Currency: Nigerian Naira (NGN)</p>
        </div>

        <div class="border-t border-zinc-800 divide-y divide-zinc-800/80 text-xs">
            <div class="py-3 flex items-center justify-between">
                <div>
                    <span class="font-medium text-white">Confirmed Subscription Revenue (This Month)</span>
                    <span class="block text-[11px] text-zinc-500">Gross checkouts processed through payment gateway</span>
                </div>
                <span class="font-mono font-bold text-white text-sm">&#8358;{{ number_format($overview['subscription_gross_this_month_minor'] / 100, 2) }}</span>
            </div>

            <div class="py-3 flex items-center justify-between">
                <div>
                    <span class="font-medium text-white">Creator Subscription Refunds</span>
                    <span class="block text-[11px] text-zinc-500">Subscription checkout reversals</span>
                </div>
                <span class="font-mono text-rose-400">-&#8358;{{ number_format($overview['subscription_refunds_this_month_minor'] / 100, 2) }}</span>
            </div>

            <div class="py-3 flex items-center justify-between bg-zinc-950/20 px-3 rounded-lg">
                <div>
                    <span class="font-medium text-emerald-300">Creator Subscription Net Collected</span>
                    <span class="block text-[11px] text-zinc-500">Gross subscriptions minus subscription refunds</span>
                </div>
                <span class="font-mono font-bold text-emerald-400 text-sm">&#8358;{{ number_format($overview['subscription_net_this_month_minor'] / 100, 2) }}</span>
            </div>

            <div class="py-3 flex items-center justify-between">
                <div>
                    <span class="font-medium text-white">Digital Library Sales (Gross Collected)</span>
                    <span class="block text-[11px] text-zinc-500">Direct series purchases before refunds</span>
                </div>
                <span class="font-mono text-white text-sm">&#8358;{{ number_format($overview['dl_gross_this_month_minor'] / 100, 2) }}</span>
            </div>

            <div class="py-3 flex items-center justify-between">
                <div>
                    <span class="font-medium text-white">Digital Library Refunds</span>
                    <span class="block text-[11px] text-zinc-500">Authoritative customer refunds issued</span>
                </div>
                <span class="font-mono text-rose-400">-&#8358;{{ number_format($overview['dl_refunds_this_month_minor'] / 100, 2) }}</span>
            </div>

            <div class="py-3 flex items-center justify-between bg-zinc-950/20 px-3 rounded-lg">
                <div>
                    <span class="font-medium text-emerald-300">Digital Library Net Collected</span>
                    <span class="block text-[11px] text-zinc-500">Gross library sales minus library refunds</span>
                </div>
                <span class="font-mono font-bold text-emerald-400 text-sm">&#8358;{{ number_format($overview['dl_net_this_month_minor'] / 100, 2) }}</span>
            </div>

            <div class="py-3 flex items-center justify-between bg-emerald-950/30 border border-emerald-800/40 px-3 rounded-lg">
                <div>
                    <span class="font-bold text-white">Platform Net Collected (Total)</span>
                    <span class="block text-[11px] text-zinc-300">Creator subscription net + Digital Library net (each refund subtracted exactly once)</span>
                </div>
                <span class="font-mono font-bold text-emerald-300 text-base">&#8358;{{ number_format($overview['platform_net_collected_this_month_minor'] / 100, 2) }}</span>
            </div>

            <div class="py-3 flex items-center justify-between">
                <div>
                    <span class="font-medium text-white">Confirmed Subscription Revenue (Previous Month)</span>
                    <span class="block text-[11px] text-zinc-500">Benchmark comparison period</span>
                </div>
                <span class="font-mono text-zinc-300">&#8358;{{ number_format($overview['revenue_last_month_minor'] / 100, 2) }}</span>
            </div>

            <div class="py-3 flex items-center justify-between">
                <div>
                    <span class="font-medium text-white">Current Monthly Recurring Revenue (MRR Run Rate)</span>
                    <span class="block text-[11px] text-zinc-500">Annualized active paid subscription base normalized to monthly</span>
                </div>
                <span class="font-mono font-bold text-emerald-400 text-sm">&#8358;{{ number_format($overview['current_mrr_minor'] / 100, 2) }}</span>
            </div>

            <div class="py-3 flex items-center justify-between">
                <div>
                    <span class="font-medium text-white">Outstanding Invoices (Collection Risk)</span>
                    <span class="block text-[11px] text-zinc-500">Pending and overdue payment commitments</span>
                </div>
                <span class="font-mono text-amber-400">&#8358;{{ number_format($overview['outstanding_invoices_minor'] / 100, 2) }}</span>
            </div>

            <div class="py-3 flex items-center justify-between bg-zinc-950/40 px-3 rounded-lg">
                <div>
                    <span class="font-semibold text-white">Affiliate Commission Liabilities (Payable + Held)</span>
                    <span class="block text-[11px] text-zinc-400">Future payout commitments (tracked as liability, not deducted from gross revenue)</span>
                </div>
                <span class="font-mono font-bold text-amber-300 text-sm">&#8358;{{ number_format(($overview['affiliate_held_minor'] + $overview['affiliate_payable_minor']) / 100, 2) }}</span>
            </div>
        </div>
    </div>
</div>
@endsection
