@extends('layouts.admin')

@section('title', 'Platform Commercial & Financial Control Center')

@section('content')
<div class="space-y-8 max-w-7xl mx-auto">
    <!-- PAGE HEADER -->
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-zinc-800 pb-5">
        <div>
            <div class="flex items-center gap-2">
                <span class="inline-flex h-2 w-2 rounded-full bg-emerald-400"></span>
                <span class="text-xs font-semibold uppercase tracking-wider text-emerald-400">Commercial Control Center</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-white mt-1">Platform Finance Overview</h1>
            <p class="text-xs text-zinc-400 mt-0.5">Authoritative revenue, subscription intelligence, recurring revenue (MRR), and platform financial liabilities.</p>
        </div>
        <div class="flex items-center gap-3">
            <span class="text-[11px] text-zinc-500 font-mono hidden sm:inline-block">Snapshot: {{ now()->timezone(\App\Support\LocalTime::tz())->format('M j, Y · H:i:s T') }}</span>
            <a href="{{ route('admin.finance.index') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-1.5 text-xs font-medium text-zinc-200 hover:bg-zinc-700 hover:text-white transition">
                <svg class="h-3.5 w-3.5 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                </svg>
                <span>Refresh</span>
            </a>
        </div>
    </div>

    @include('admin.finance.partials.nav')

    <!-- TOP COMMERCIAL KPI CARDS -->
    <section>
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-300">Executive Commercial Telemetry (NGN)</h2>
            <span class="text-xs text-zinc-500">Authoritative platform ledgers &middot; No estimated values</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- 1. Revenue this month -->
            <a href="{{ route('admin.finance.payments') }}" class="group rounded-xl border border-zinc-800 bg-zinc-900/50 p-4 transition hover:border-emerald-700/80 hover:bg-zinc-900/80 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between text-xs text-zinc-400">
                        <span class="uppercase tracking-wider font-semibold">Revenue This Month (NGN)</span>
                        <span class="text-[10px] font-mono bg-emerald-950/70 text-emerald-300 border border-emerald-800/60 px-1.5 py-0.5 rounded">Confirmed</span>
                    </div>
                    <div class="mt-2 text-2xl font-bold text-white group-hover:text-emerald-300 transition">
                        &#8358;{{ number_format($metrics['revenue_this_month_minor'] / 100, 2) }}
                    </div>
                </div>
                <div class="mt-3 text-[11px] text-zinc-400 border-t border-zinc-800/80 pt-2 flex items-center justify-between">
                    <span class="{{ $metrics['revenue_mom_change_minor'] >= 0 ? 'text-emerald-400' : 'text-rose-400' }} font-medium">
                        {{ $metrics['revenue_mom_change_minor'] >= 0 ? '+' : '' }}{{ number_format($metrics['revenue_mom_percent'], 1) }}% MoM
                    </span>
                    <span class="text-zinc-500">Prev: &#8358;{{ number_format($metrics['revenue_last_month_minor'] / 100, 2) }}</span>
                </div>
            </a>

            <!-- 2. Revenue today -->
            <a href="{{ route('admin.finance.payments') }}" class="group rounded-xl border border-zinc-800 bg-zinc-900/50 p-4 transition hover:border-emerald-700/80 hover:bg-zinc-900/80 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between text-xs text-zinc-400">
                        <span class="uppercase tracking-wider font-semibold">Revenue Today (NGN)</span>
                        <span class="h-2 w-2 rounded-full {{ $metrics['revenue_today_minor'] > 0 ? 'bg-emerald-400 animate-pulse' : 'bg-zinc-600' }}"></span>
                    </div>
                    <div class="mt-2 text-2xl font-bold text-white group-hover:text-emerald-300 transition">
                        &#8358;{{ number_format($metrics['revenue_today_minor'] / 100, 2) }}
                    </div>
                </div>
                <div class="mt-3 text-[11px] text-zinc-500 border-t border-zinc-800/80 pt-2 flex items-center justify-between">
                    <span>Since 00:00 (Local WAT)</span>
                    <span class="text-emerald-400 group-hover:translate-x-0.5 transition-transform">&rarr;</span>
                </div>
            </a>

            <!-- 3. Active Paid Subscriptions -->
            <a href="{{ route('admin.finance.subscriptions', ['health' => 'healthy']) }}" class="group rounded-xl border border-zinc-800 bg-zinc-900/50 p-4 transition hover:border-emerald-700/80 hover:bg-zinc-900/80 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between text-xs text-zinc-400">
                        <span class="uppercase tracking-wider font-semibold">Active Paid Subscriptions</span>
                        <div class="h-6 w-6 rounded-md bg-zinc-800/80 text-zinc-400 flex items-center justify-center shrink-0 border border-zinc-700/60">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-2 text-2xl font-bold text-white group-hover:text-emerald-300 transition">
                        {{ number_format($metrics['active_paid_subscriptions_count']) }}
                    </div>
                </div>
                <div class="mt-3 text-[11px] text-zinc-500 border-t border-zinc-800/80 pt-2 flex items-center justify-between">
                    <span>Excludes free &amp; trialing orgs</span>
                    <span class="text-emerald-400 group-hover:translate-x-0.5 transition-transform">&rarr;</span>
                </div>
            </a>

            <!-- 4. Monthly Recurring Revenue (MRR) -->
            <a href="{{ route('admin.finance.subscriptions') }}" class="group rounded-xl border border-zinc-800 bg-zinc-900/50 p-4 transition hover:border-emerald-700/80 hover:bg-zinc-900/80 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between text-xs text-zinc-400">
                        <span class="uppercase tracking-wider font-semibold">Monthly Recurring (MRR)</span>
                        <span class="text-[10px] font-mono bg-zinc-800 text-zinc-300 px-1.5 py-0.5 rounded">Active Base</span>
                    </div>
                    <div class="mt-2 text-2xl font-bold text-emerald-400 group-hover:text-emerald-300 transition">
                        &#8358;{{ number_format($metrics['current_mrr_minor'] / 100, 2) }}
                    </div>
                </div>
                <div class="mt-3 text-[11px] text-zinc-500 border-t border-zinc-800/80 pt-2 flex items-center justify-between">
                    <span>Normalized monthly value</span>
                    <span class="text-emerald-400 group-hover:translate-x-0.5 transition-transform">&rarr;</span>
                </div>
            </a>

            <!-- 5. Outstanding Invoices -->
            <a href="{{ route('admin.finance.invoices', ['status' => 'past_due']) }}" class="group rounded-xl border border-zinc-800 bg-zinc-900/50 p-4 transition hover:border-emerald-700/80 hover:bg-zinc-900/80 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between text-xs text-zinc-400">
                        <span class="uppercase tracking-wider font-semibold">Outstanding Invoices</span>
                        <span class="text-[10px] font-mono bg-zinc-800 text-zinc-300 px-1.5 py-0.5 rounded">{{ $metrics['outstanding_invoices_count'] }} open</span>
                    </div>
                    <div class="mt-2 text-2xl font-bold {{ $metrics['outstanding_invoices_minor'] > 0 ? 'text-amber-400' : 'text-white' }} group-hover:text-amber-300 transition">
                        &#8358;{{ number_format($metrics['outstanding_invoices_minor'] / 100, 2) }}
                    </div>
                </div>
                <div class="mt-3 text-[11px] text-zinc-500 border-t border-zinc-800/80 pt-2 flex items-center justify-between">
                    <span>Pending &amp; past-due invoices</span>
                    <span class="text-emerald-400 group-hover:translate-x-0.5 transition-transform">&rarr;</span>
                </div>
            </a>

            <!-- 6. Past-Due Subscriptions (Risk) -->
            <a href="{{ route('admin.finance.subscriptions', ['health' => 'past_due']) }}" class="group rounded-xl border {{ $metrics['past_due_subscriptions_count'] > 0 ? 'border-rose-900/50 bg-rose-950/20' : 'border-zinc-800 bg-zinc-900/50' }} p-4 transition hover:border-rose-700/80 hover:bg-zinc-900/80 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between text-xs {{ $metrics['past_due_subscriptions_count'] > 0 ? 'text-rose-300' : 'text-zinc-400' }}">
                        <span class="uppercase tracking-wider font-semibold">Past-Due Subscriptions</span>
                        @if ($metrics['past_due_subscriptions_count'] > 0)
                            <span class="h-2 w-2 rounded-full bg-rose-400 animate-pulse"></span>
                        @endif
                    </div>
                    <div class="mt-2 text-2xl font-bold {{ $metrics['past_due_subscriptions_count'] > 0 ? 'text-rose-400' : 'text-white' }} transition">
                        {{ number_format($metrics['past_due_subscriptions_count']) }}
                    </div>
                </div>
                <div class="mt-3 text-[11px] text-zinc-500 border-t border-zinc-800/80 pt-2 flex items-center justify-between">
                    <span>Collection risk / renewal fail</span>
                    <span class="text-rose-400 group-hover:translate-x-0.5 transition-transform">&rarr;</span>
                </div>
            </a>

            <!-- 7. Refunds This Month -->
            <a href="{{ route('admin.finance.refunds') }}" class="group rounded-xl border border-zinc-800 bg-zinc-900/50 p-4 transition hover:border-emerald-700/80 hover:bg-zinc-900/80 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between text-xs text-zinc-400">
                        <span class="uppercase tracking-wider font-semibold">Refunds This Month</span>
                        <span class="text-[10px] font-mono bg-zinc-800 text-zinc-300 px-1.5 py-0.5 rounded">Subtracted</span>
                    </div>
                    <div class="mt-2 text-2xl font-bold text-white group-hover:text-emerald-300 transition">
                        &#8358;{{ number_format($metrics['refunds_this_month_minor'] / 100, 2) }}
                    </div>
                </div>
                <div class="mt-3 text-[11px] text-zinc-500 border-t border-zinc-800/80 pt-2 flex items-center justify-between">
                    <span>Digital Library refunds</span>
                    <span class="text-emerald-400 group-hover:translate-x-0.5 transition-transform">&rarr;</span>
                </div>
            </a>

            <!-- 8. Affiliate Payable Liability -->
            <a href="{{ route('admin.finance.affiliate-liabilities') }}" class="group rounded-xl border border-zinc-800 bg-zinc-900/50 p-4 transition hover:border-emerald-700/80 hover:bg-zinc-900/80 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between text-xs text-zinc-400">
                        <span class="uppercase tracking-wider font-semibold">Affiliate Payable (Liability)</span>
                        <span class="text-[10px] font-mono bg-zinc-800 text-amber-300 px-1.5 py-0.5 rounded">Liability</span>
                    </div>
                    <div class="mt-2 text-2xl font-bold text-amber-400 group-hover:text-amber-300 transition">
                        &#8358;{{ number_format($metrics['affiliate_payable_minor'] / 100, 2) }}
                    </div>
                </div>
                <div class="mt-3 text-[11px] text-zinc-500 border-t border-zinc-800/80 pt-2 flex items-center justify-between">
                    <span>Held: &#8358;{{ number_format($metrics['affiliate_held_minor'] / 100, 2) }}</span>
                    <span class="text-amber-400 group-hover:translate-x-0.5 transition-transform">&rarr;</span>
                </div>
            </a>
        </div>
    </section>

    <!-- REVENUE TREND CHART (CONFIRMED SUBSCRIPTION PAYMENTS) -->
    <section class="rounded-xl border border-zinc-800 bg-zinc-900/40 p-5">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-200">Confirmed Creator Subscription Revenue</h2>
                    <span class="rounded bg-emerald-950/80 px-2 py-0.5 text-[10px] font-mono text-emerald-400 border border-emerald-800/60">
                        Total in range: &#8358;{{ number_format($revenueTrend['total_minor'] / 100, 2) }}
                    </span>
                </div>
                <p class="text-xs text-zinc-400 mt-0.5">Authoritative revenue derived exclusively from verified payment gateway checkouts.</p>
            </div>
            <div class="flex items-center gap-1 bg-zinc-950 p-1 rounded-lg border border-zinc-800 self-start sm:self-auto">
                @foreach (['7d' => '7 Days', '30d' => '30 Days', '90d' => '90 Days', '12m' => '12 Months'] as $rangeKey => $rangeLabel)
                    <a href="{{ route('admin.finance.index', ['range' => $rangeKey]) }}" class="px-2.5 py-1 text-xs rounded-md font-medium transition {{ $activeRange === $rangeKey ? 'bg-emerald-600 text-white shadow-sm' : 'text-zinc-400 hover:text-zinc-200' }}">
                        {{ $rangeLabel }}
                    </a>
                @endforeach
            </div>
        </div>

        @php
            $maxMinor = max(1, ...$revenueTrend['data_minor']);
        @endphp

        <!-- SVG / Bar Visualization -->
        <div class="mt-6">
            <div class="h-44 flex items-end gap-1.5 pt-6 pb-2 border-b border-zinc-800">
                @foreach ($revenueTrend['labels'] as $index => $label)
                    @php
                        $valMinor = $revenueTrend['data_minor'][$index];
                        $heightPercent = max(4, round(($valMinor / $maxMinor) * 100));
                    @endphp
                    <div class="flex-1 flex flex-col items-center h-full justify-end group relative" title="{{ $label }}: &#8358;{{ number_format($valMinor / 100, 2) }}">
                        <!-- Tooltip on hover -->
                        <div class="absolute -top-7 hidden group-hover:flex flex-col items-center z-20 pointer-events-none">
                            <span class="rounded bg-zinc-800 px-1.5 py-0.5 text-[10px] font-mono text-white whitespace-nowrap shadow-md border border-zinc-700">
                                &#8358;{{ number_format($valMinor / 100, 0) }}
                            </span>
                        </div>
                        <div class="w-full rounded-t transition-all {{ $valMinor > 0 ? 'bg-emerald-500 group-hover:bg-emerald-400' : 'bg-zinc-800/60' }}" style="height: {{ $heightPercent }}%;"></div>
                    </div>
                @endforeach
            </div>
            <div class="mt-2 flex justify-between text-[10px] font-mono text-zinc-500">
                <span>{{ $revenueTrend['labels'][0] ?? '' }}</span>
                @if (count($revenueTrend['labels']) > 2)
                    <span>{{ $revenueTrend['labels'][(int) (count($revenueTrend['labels']) / 2)] ?? '' }}</span>
                @endif
                <span>{{ end($revenueTrend['labels']) ?: '' }}</span>
            </div>
        </div>
    </section>

    <!-- REVENUE STREAMS SEPARATION -->
    <section class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- 1. Creator Subscriptions Stream -->
        <div class="rounded-xl border border-zinc-800 bg-zinc-900/40 p-5 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between text-xs">
                    <span class="font-semibold text-emerald-400 uppercase tracking-wider">Stream 1 &middot; Creator Subscriptions</span>
                    <span class="font-mono text-[10px] text-zinc-400 bg-zinc-800 px-1.5 py-0.5 rounded">Direct SaaS</span>
                </div>
                <div class="mt-3 grid grid-cols-3 gap-2">
                    <div>
                        <span class="text-[11px] text-zinc-500 block">Subscription Gross</span>
                        <span class="text-lg font-bold text-white">&#8358;{{ number_format($metrics['subscription_gross_this_month_minor'] / 100, 2) }}</span>
                    </div>
                    <div>
                        <span class="text-[11px] text-zinc-500 block">Sub Refunds</span>
                        <span class="text-lg font-bold text-rose-400">&#8358;{{ number_format($metrics['subscription_refunds_this_month_minor'] / 100, 2) }}</span>
                    </div>
                    <div class="text-right">
                        <span class="text-[11px] text-zinc-500 block">Subscription Net</span>
                        <span class="text-lg font-bold text-emerald-400">&#8358;{{ number_format($metrics['subscription_net_this_month_minor'] / 100, 2) }}</span>
                    </div>
                </div>

                <div class="mt-4 space-y-2 border-t border-zinc-800/80 pt-3 text-xs">
                    <div class="flex justify-between text-zinc-300">
                        <span class="text-zinc-400">Successful renewals this month</span>
                        <span class="font-mono font-semibold">{{ number_format($subscriptionPerformance['renewals']) }}</span>
                    </div>
                    <div class="flex justify-between text-zinc-300">
                        <span class="text-zinc-400">First-time subscriptions this month</span>
                        <span class="font-mono font-semibold">{{ number_format($subscriptionPerformance['first_time_payments']) }}</span>
                    </div>
                    <div class="flex justify-between text-zinc-300">
                        <span class="text-zinc-400">Failed renewal attempts this month</span>
                        <span class="font-mono font-semibold {{ $metrics['failed_checkouts_count'] > 0 ? 'text-rose-400' : 'text-zinc-300' }}">{{ number_format($metrics['failed_checkouts_count']) }}</span>
                    </div>
                </div>
            </div>

            <div class="mt-4 pt-3 border-t border-zinc-800/80 flex items-center justify-between">
                <a href="{{ route('admin.finance.payments') }}" class="text-xs text-emerald-400 hover:underline">View confirmed subscription payments &rarr;</a>
                <a href="{{ route('admin.finance.subscriptions') }}" class="text-xs text-zinc-400 hover:text-white">Subscriptions table &rarr;</a>
            </div>
        </div>

        <!-- 2. Digital Library Marketplace Stream -->
        <div class="rounded-xl border border-zinc-800 bg-zinc-900/40 p-5 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between text-xs">
                    <span class="font-semibold text-emerald-400 uppercase tracking-wider">Stream 2 &middot; Digital Library Marketplace</span>
                    <span class="font-mono text-[10px] text-zinc-400 bg-zinc-800 px-1.5 py-0.5 rounded">Marketplace</span>
                </div>
                <div class="mt-3 grid grid-cols-3 gap-2">
                    <div>
                        <span class="text-[11px] text-zinc-500 block">Digital Library Gross</span>
                        <span class="text-lg font-bold text-white">&#8358;{{ number_format($metrics['dl_gross_this_month_minor'] / 100, 2) }}</span>
                    </div>
                    <div>
                        <span class="text-[11px] text-zinc-500 block">DL Refunds</span>
                        <span class="text-lg font-bold text-rose-400">&#8358;{{ number_format($metrics['dl_refunds_this_month_minor'] / 100, 2) }}</span>
                    </div>
                    <div class="text-right">
                        <span class="text-[11px] text-zinc-500 block">DL Net Collected</span>
                        <span class="text-lg font-bold text-emerald-400">&#8358;{{ number_format($metrics['dl_net_this_month_minor'] / 100, 2) }}</span>
                    </div>
                </div>

                <div class="mt-4 space-y-2 border-t border-zinc-800/80 pt-3 text-xs">
                    <div class="flex justify-between text-zinc-300">
                        <span class="text-zinc-400">Authoritative marketplace status</span>
                        <span class="font-mono text-zinc-400">Segregated ledger</span>
                    </div>
                    <div class="flex justify-between text-zinc-300">
                        <span class="text-zinc-400">Platform fee model</span>
                        <span class="font-mono text-zinc-400">BPS platform cut</span>
                    </div>
                    <div class="flex justify-between text-zinc-300">
                        <span class="text-zinc-400">Affiliate liabilities (Held/Payable)</span>
                        <span class="font-mono text-amber-300">&#8358;{{ number_format(($metrics['affiliate_held_minor'] + $metrics['affiliate_payable_minor']) / 100, 2) }}</span>
                    </div>
                </div>
            </div>

            <div class="mt-4 pt-3 border-t border-zinc-800/80 flex items-center justify-between">
                <a href="{{ route('admin.digital-library.purchases.index') }}" class="text-xs text-emerald-400 hover:underline">View library purchases &rarr;</a>
                <a href="{{ route('admin.finance.refunds') }}" class="text-xs text-zinc-400 hover:text-white">View refunds log &rarr;</a>
            </div>
        </div>
    </section>

    <!-- PLAN DISTRIBUTION & SUBSCRIPTION INTELLIGENCE -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Plan Distribution (2 cols) -->
        <div class="lg:col-span-2 rounded-xl border border-zinc-800 bg-zinc-900/40 p-5">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-sm font-semibold uppercase tracking-wider text-zinc-200">Creator Plan Distribution</h3>
                    <p class="text-xs text-zinc-400 mt-0.5">Active paid organisations and recurring monthly contribution per plan.</p>
                </div>
                <a href="{{ route('admin.plans.index') }}" class="text-xs text-zinc-400 hover:text-white">Configure plans &rarr;</a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-zinc-800 text-[11px] font-bold uppercase tracking-wider text-zinc-400">
                            <th class="py-2.5 pr-4">Plan Name</th>
                            <th class="py-2.5 px-3">Billing Interval</th>
                            <th class="py-2.5 px-3 text-right">Price</th>
                            <th class="py-2.5 px-3 text-right">Active Orgs</th>
                            <th class="py-2.5 px-3 text-right">% Paid Base</th>
                            <th class="py-2.5 pl-3 text-right">MRR Contribution</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800/60 font-mono text-zinc-300">
                        @forelse ($planDistribution as $row)
                            @php
                                $plan = $row['plan'];
                            @endphp
                            <tr class="hover:bg-zinc-800/40 transition">
                                <td class="py-2.5 pr-4 font-sans font-semibold text-white">
                                    <div class="flex items-center gap-2">
                                        <span class="h-2 w-2 rounded-full {{ $plan->isFree() ? 'bg-zinc-500' : 'bg-emerald-400' }}"></span>
                                        <span>{{ $plan->name }}</span>
                                    </div>
                                </td>
                                <td class="py-2.5 px-3 capitalize text-zinc-400 font-sans">
                                    {{ $plan->interval ?: 'monthly' }}
                                </td>
                                <td class="py-2.5 px-3 text-right text-zinc-200">
                                    {{ $plan->isFree() ? 'Free' : '₦' . number_format($plan->amount / 100, 2) }}
                                </td>
                                <td class="py-2.5 px-3 text-right font-bold text-white">
                                    {{ number_format($row['active_count']) }}
                                </td>
                                <td class="py-2.5 px-3 text-right text-zinc-400">
                                    {{ $plan->isFree() ? '—' : $row['percentage'] . '%' }}
                                </td>
                                <td class="py-2.5 pl-3 text-right font-semibold {{ $row['mrr_minor'] > 0 ? 'text-emerald-400' : 'text-zinc-500' }}">
                                    &#8358;{{ number_format($row['mrr_minor'] / 100, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-4 text-center text-zinc-500 font-sans">No active subscription plans defined.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Subscription Status Overview (1 col) -->
        <div class="rounded-xl border border-zinc-800 bg-zinc-900/40 p-5 flex flex-col justify-between">
            <div>
                <h3 class="text-sm font-semibold uppercase tracking-wider text-zinc-200 mb-3">Subscription Intelligence</h3>
                <div class="space-y-3 text-xs">
                    <div class="flex items-center justify-between p-2.5 rounded-lg bg-zinc-950/60 border border-zinc-800">
                        <span class="text-zinc-300 flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-emerald-400"></span> Active
                        </span>
                        <span class="font-mono font-bold text-white">{{ number_format($subscriptionPerformance['active']) }}</span>
                    </div>
                    <div class="flex items-center justify-between p-2.5 rounded-lg bg-zinc-950/60 border border-zinc-800">
                        <span class="text-zinc-300 flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-blue-400"></span> Trialing / Free Period
                        </span>
                        <span class="font-mono font-bold text-white">{{ number_format($subscriptionPerformance['trialing']) }}</span>
                    </div>
                    <div class="flex items-center justify-between p-2.5 rounded-lg bg-zinc-950/60 border {{ $subscriptionPerformance['past_due'] > 0 ? 'border-rose-900/60 bg-rose-950/20' : 'border-zinc-800' }}">
                        <span class="{{ $subscriptionPerformance['past_due'] > 0 ? 'text-rose-300' : 'text-zinc-300' }} flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full {{ $subscriptionPerformance['past_due'] > 0 ? 'bg-rose-400 animate-pulse' : 'bg-zinc-600' }}"></span> Past Due (Collection Risk)
                        </span>
                        <span class="font-mono font-bold {{ $subscriptionPerformance['past_due'] > 0 ? 'text-rose-400' : 'text-white' }}">{{ number_format($subscriptionPerformance['past_due']) }}</span>
                    </div>
                    <div class="flex items-center justify-between p-2.5 rounded-lg bg-zinc-950/60 border border-zinc-800">
                        <span class="text-zinc-300 flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-zinc-600"></span> Cancelled
                        </span>
                        <span class="font-mono font-bold text-zinc-400">{{ number_format($subscriptionPerformance['cancelled']) }}</span>
                    </div>
                </div>

                <div class="mt-4 border-t border-zinc-800/80 pt-3 text-[11px] text-zinc-400 space-y-1.5">
                    <div class="flex justify-between">
                        <span>New paid this month:</span>
                        <span class="font-mono text-zinc-200">{{ number_format($subscriptionPerformance['new_this_month']) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Cancellations this month:</span>
                        <span class="font-mono text-zinc-200">{{ number_format($subscriptionPerformance['cancelled_this_month']) }}</span>
                    </div>
                </div>
            </div>

            <div class="mt-4 pt-3 border-t border-zinc-800/80">
                <a href="{{ route('admin.finance.subscriptions') }}" class="block text-center rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-1.5 text-xs font-medium text-zinc-200 hover:bg-zinc-700 hover:text-white transition">
                    Inspect Subscriptions Table &rarr;
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
