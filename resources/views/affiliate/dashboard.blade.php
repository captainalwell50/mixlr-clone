@extends('layouts.app')

@section('title', 'Affiliate Partner Dashboard')

@section('content')
    @include('affiliate.partials.nav')

    <!-- HEADER & WELCOME -->
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2">
                <span class="inline-flex h-2 w-2 rounded-full bg-emerald-400"></span>
                <p class="site-section-label">Affiliate Partner Network</p>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-[var(--stage-cream)] mt-1">Partner Control Dashboard</h1>
            <p class="text-xs text-[var(--stage-muted)] mt-0.5">Earn 10% recurring commission on confirmed church and creator subscriptions.</p>
        </div>
        <div class="flex items-center gap-2.5">
            <a href="{{ route('affiliate.commissions.index') }}" class="console-btn console-btn-ghost text-xs">
                Earnings Statement &rarr;
            </a>
            @if ($balances['payable_minor'] > 0 && $balances['is_qualified'])
                <a href="{{ route('affiliate.payouts.index') }}" class="console-btn console-btn-primary text-xs">
                    Request Payout
                </a>
            @endif
        </div>
    </div>

    <!-- REFERRAL LINK BANNER -->
    <div class="mb-8 rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-4 sm:p-5 shadow-sm">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <span class="text-xs uppercase font-semibold tracking-wider text-emerald-400">Your Unique Referral Link</span>
                    <span class="rounded bg-emerald-500/10 px-1.5 py-0.5 text-[10px] font-mono font-medium text-emerald-300 border border-emerald-500/20">90-Day First Touch</span>
                </div>
                <div class="flex items-baseline gap-2">
                    <span class="text-xs text-[var(--stage-muted)]">Partner Code:</span>
                    <span class="font-mono text-base font-bold text-[var(--stage-cream)]">{{ $affiliate->referral_code }}</span>
                </div>
                <div class="font-mono text-xs text-emerald-400 break-all select-all bg-[var(--stage-ink)] px-3 py-2 rounded-lg border border-[var(--stage-border)]">
                    {{ $referralUrl }}
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                <button
                    type="button"
                    onclick="copyReferralLink('{{ $referralUrl }}')"
                    id="copy-btn"
                    class="console-btn console-btn-primary text-xs py-2 px-3.5 flex items-center gap-1.5"
                >
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" />
                    </svg>
                    <span id="copy-text">Copy Link</span>
                </button>
                <button
                    type="button"
                    onclick="shareReferralLink('{{ $referralUrl }}', 'Join SoundMix Live with my referral link')"
                    class="console-btn console-btn-ghost text-xs py-2 px-3.5 flex items-center gap-1.5"
                >
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.217 10.907a2.25 2.25 0 1 0 0 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186 9.566-5.314m-9.566 7.5 9.566 5.314m0 0a2.25 2.25 0 1 0 3.935 2.186 2.25 2.25 0 0 0-3.935-2.186Zm0-12.814a2.25 2.25 0 1 0 3.933-2.185 2.25 2.25 0 0 0-3.933 2.185Z" />
                    </svg>
                    <span>Share</span>
                </button>
            </div>
        </div>
        <p class="mt-3 text-[11px] text-[var(--stage-muted)]">
            Note: Custom campaign and sub-source tracking parameters are planned for a future update; all referrals are currently attributed via your canonical referral link.
        </p>
    </div>

    <!-- PRIMARY SUMMARY: QUALIFICATION & TIER ROW -->
    <div class="grid gap-5 md:grid-cols-2 mb-8">
        <!-- Payout Qualification Card -->
        <div class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-5 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between">
                    <span class="text-xs uppercase font-semibold tracking-wider text-[var(--stage-muted)]">Payout Qualification</span>
                    <span class="text-xs font-mono font-medium text-[var(--stage-muted)]">Threshold: {{ $target }} Organisations</span>
                </div>

                <div class="mt-3">
                    @if ($balances['is_qualified'])
                        <div class="flex items-center gap-2 text-emerald-400">
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <span class="text-base font-bold text-[var(--stage-cream)]">Qualified for Payouts (Permanent)</span>
                        </div>
                        <p class="text-xs text-[var(--stage-muted)] mt-1">
                            Earned on {{ $affiliate->payout_qualified_at?->format('F j, Y') ?? 'Confirmed' }}. You have met the permanent 5-organisation threshold. Matured commissions automatically become payable.
                        </p>
                    @else
                        <div class="flex items-baseline justify-between">
                            <span class="text-xl font-bold text-amber-400">
                                {{ $qualifyingCount }} of {{ $target }} paying organisations
                            </span>
                            <span class="text-xs font-mono text-[var(--stage-muted)]">
                                {{ max(0, $target - $qualifyingCount) }} more needed
                            </span>
                        </div>
                        <p class="text-xs text-[var(--stage-muted)] mt-1.5">
                            Payout qualification requires referring at least 5 distinct paying churches or organisations. Commissions accrue as HELD until qualified.
                        </p>
                    @endif
                </div>
            </div>

            <!-- Progress Bar -->
            <div class="mt-4 pt-3 border-t border-[var(--stage-border)]">
                <div class="flex justify-between text-[11px] font-mono text-[var(--stage-muted)] mb-1.5">
                    <span>Qualification Progress</span>
                    <span>{{ min(100, (int) round(($qualifyingCount / max(1, $target)) * 100)) }}%</span>
                </div>
                <div class="w-full bg-[var(--stage-border)] rounded-full h-2.5 overflow-hidden">
                    <div
                        class="h-2.5 rounded-full transition-all duration-500 {{ $balances['is_qualified'] ? 'bg-emerald-500' : 'bg-amber-500' }}"
                        style="width: {{ min(100, (int) round(($qualifyingCount / max(1, $target)) * 100)) }}%"
                    ></div>
                </div>
            </div>
        </div>

        <!-- Secondary Card: Either Tier (if enabled in settings) or Authoritative Programme Terms & Eligibility -->
        @if ($showLevel)
            <div class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-5 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs uppercase font-semibold tracking-wider text-[var(--stage-muted)]">Partner Tier</span>
                        <span class="rounded-full bg-cyan-500/10 px-2.5 py-0.5 text-xs font-bold text-cyan-400 border border-cyan-500/30">
                            {{ $tierProgress['current']['name'] ?? 'Starter' }}
                        </span>
                    </div>

                    <div class="mt-3">
                        <div class="flex items-baseline gap-2">
                            <span class="text-2xl font-bold text-[var(--stage-cream)]">{{ $tierProgress['current']['name'] ?? 'Starter' }} Tier</span>
                            <span class="text-xs text-[var(--stage-muted)] font-mono">({{ $defaultRatePercent }}% Base Commission)</span>
                        </div>
                        @if ($showLevelProgress && $tierProgress['next'])
                            <p class="text-xs text-[var(--stage-muted)] mt-1">
                                Next Tier: <strong class="text-cyan-400">{{ $tierProgress['next']['name'] }}</strong> requires {{ $tierProgress['next']['min_active_paying_churches'] }} distinct paying churches ({{ $tierProgress['remaining'] }} more needed).
                            </p>
                        @elseif ($tierProgress['next'])
                            <p class="text-xs text-[var(--stage-muted)] mt-1">
                                Active Partner Tier: {{ $tierProgress['current']['name'] }}.
                            </p>
                        @else
                            <p class="text-xs text-emerald-400 mt-1">
                                Highest recognition tier achieved: {{ $tierProgress['current']['name'] }} Partner.
                            </p>
                        @endif
                    </div>
                </div>

                @if ($showLevelProgress && $tierProgress['next'])
                    <!-- Tier Progression Bar -->
                    <div class="mt-4 pt-3 border-t border-[var(--stage-border)]">
                        <div class="flex justify-between text-[11px] font-mono text-[var(--stage-muted)] mb-1.5">
                            <span>Progress to {{ $tierProgress['next']['name'] ?? 'Max Tier' }}</span>
                            <span>{{ $tierProgress['progress_percent'] }}%</span>
                        </div>
                        <div class="w-full bg-[var(--stage-border)] rounded-full h-2.5 overflow-hidden">
                            <div
                                class="bg-cyan-500 h-2.5 rounded-full transition-all duration-500"
                                style="width: {{ $tierProgress['progress_percent'] }}%"
                            ></div>
                        </div>
                    </div>
                @else
                    <div class="mt-4 pt-3 border-t border-[var(--stage-border)] text-[11px] text-[var(--stage-muted)]">
                        Tiers reflect cumulative active referred organisations and recognise partner milestones.
                    </div>
                @endif
            </div>
        @else
            <!-- Authoritative Programme Terms & Earning Rules Card -->
            <div class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-5 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs uppercase font-semibold tracking-wider text-[var(--stage-muted)]">Programme Terms & Eligibility</span>
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-bold border {{ $balances['is_qualified'] ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30' : 'bg-amber-500/10 text-amber-400 border-amber-500/30' }}">
                            {{ $balances['is_qualified'] ? 'Payout Eligible' : 'Qualification Pending' }}
                        </span>
                    </div>

                    <div class="mt-3">
                        <div class="flex items-baseline gap-2">
                            <span class="text-2xl font-bold text-[var(--stage-cream)]">{{ $defaultRatePercent }}% Recurring</span>
                            <span class="text-xs text-[var(--stage-muted)] font-mono">(14-day hold clearance)</span>
                        </div>
                        <p class="text-xs text-[var(--stage-muted)] mt-1">
                            Earn 10% recurring commission on all confirmed subscription payments from your referred churches and organisations.
                        </p>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-[var(--stage-border)] grid grid-cols-2 gap-3 text-xs">
                    <div>
                        <span class="text-[var(--stage-muted)] block text-[11px]">Paying Churches:</span>
                        <span class="font-bold text-[var(--stage-cream)]">{{ $qualifyingCount }} distinct</span>
                    </div>
                    <div>
                        <span class="text-[var(--stage-muted)] block text-[11px]">Settlement Mode:</span>
                        <span class="font-bold text-cyan-300">Manual review</span>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <!-- COMMISSION LEDGER BALANCES: HELD vs PAYABLE vs PAID -->
    <div class="mb-8">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-xs uppercase font-semibold tracking-wider text-[var(--stage-muted)]">Authoritative Commission Balances</h2>
            <span class="text-[11px] text-[var(--stage-muted)] font-mono">Immutable Ledger Derived</span>
        </div>

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <!-- 1. HELD BALANCE -->
            <div class="rounded-xl border border-amber-900/40 bg-amber-950/20 p-4">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-amber-400">Held Commission</span>
                    <span class="rounded bg-amber-500/10 px-1.5 py-0.2 text-[10px] font-mono text-amber-400">14-Day Hold</span>
                </div>
                <div class="mt-2 text-2xl font-bold font-mono text-amber-400">
                    {{ $balances['currency'] }} {{ number_format($balances['held_minor'] / 100, 2) }}
                </div>
                <p class="mt-1 text-[11px] text-[var(--stage-muted)]">
                    Accruing in 14-day hold protection window. Non-withdrawable until hold completes and qualification is met.
                </p>
            </div>

            <!-- 2. PAYABLE BALANCE -->
            <div class="rounded-xl border border-emerald-900/50 bg-emerald-950/20 p-4">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-emerald-400">Payable Balance</span>
                    <span class="rounded bg-emerald-500/10 px-1.5 py-0.2 text-[10px] font-mono text-emerald-400">Ready</span>
                </div>
                <div class="mt-2 text-2xl font-bold font-mono text-emerald-400">
                    {{ $balances['currency'] }} {{ number_format($balances['payable_minor'] / 100, 2) }}
                </div>
                <p class="mt-1 text-[11px] text-[var(--stage-muted)]">
                    Matured and available for payout request.
                </p>
            </div>

            <!-- 3. LIFETIME PAID -->
            <div class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-4">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-[var(--stage-muted)]">Lifetime Paid</span>
                    <span class="rounded bg-[var(--stage-border)] px-1.5 py-0.2 text-[10px] font-mono text-[var(--stage-muted)]">Settled</span>
                </div>
                <div class="mt-2 text-2xl font-bold font-mono text-cyan-400">
                    {{ $balances['currency'] }} {{ number_format($balances['paid_minor'] / 100, 2) }}
                </div>
                <p class="mt-1 text-[11px] text-[var(--stage-muted)]">
                    Total commissions disbursed to your account.
                </p>
            </div>

            <!-- 4. REVERSED / ADJUSTED -->
            <div class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-4">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-[var(--stage-muted)]">Reversed / Adjustments</span>
                    <span class="rounded bg-rose-500/10 px-1.5 py-0.2 text-[10px] font-mono text-rose-400">Refunds</span>
                </div>
                <div class="mt-2 text-2xl font-bold font-mono {{ $balances['reversed_minor'] > 0 ? 'text-rose-400' : 'text-[var(--stage-muted)]' }}">
                    {{ $balances['currency'] }} {{ number_format($balances['reversed_minor'] / 100, 2) }}
                </div>
                <p class="mt-1 text-[11px] text-[var(--stage-muted)]">
                    Deductions from customer refunds or payment disputes.
                </p>
            </div>
        </div>
    </div>

    <!-- REFERRAL NETWORK ANALYTICS -->
    <div class="mb-8 rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-5">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-[var(--stage-border)] pb-3 mb-4">
            <div>
                <h3 class="text-sm font-semibold uppercase tracking-wider text-[var(--stage-cream)]">Referral Network Analytics</h3>
                <p class="text-xs text-[var(--stage-muted)] mt-0.5">Authoritative performance metrics based on confirmed database events.</p>
            </div>
            <span class="text-[11px] font-mono text-[var(--stage-muted)]">Click tracking not currently authoritative</span>
        </div>

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5 text-center">
            <div class="p-3 rounded-lg bg-[var(--stage-ink)] border border-[var(--stage-border)]">
                <span class="block text-[11px] uppercase tracking-wider text-[var(--stage-muted)]">Referred Orgs</span>
                <span class="mt-1 block text-2xl font-bold text-[var(--stage-cream)] font-mono">{{ $analytics['total_referred_orgs'] }}</span>
                <span class="block text-[10px] text-[var(--stage-muted)] mt-0.5">Total attributed</span>
            </div>
            <div class="p-3 rounded-lg bg-[var(--stage-ink)] border border-[var(--stage-border)]">
                <span class="block text-[11px] uppercase tracking-wider text-[var(--stage-muted)]">Paying Orgs</span>
                <span class="mt-1 block text-2xl font-bold text-emerald-400 font-mono">{{ $analytics['paying_referred_orgs'] }}</span>
                <span class="block text-[10px] text-[var(--stage-muted)] mt-0.5">Subscription active/ever</span>
            </div>
            <div class="p-3 rounded-lg bg-[var(--stage-ink)] border border-[var(--stage-border)]">
                <span class="block text-[11px] uppercase tracking-wider text-[var(--stage-muted)]">Active Subscriptions</span>
                <span class="mt-1 block text-2xl font-bold text-cyan-400 font-mono">{{ $analytics['active_paying_orgs'] }}</span>
                <span class="block text-[10px] text-[var(--stage-muted)] mt-0.5">Currently paying</span>
            </div>
            <div class="p-3 rounded-lg bg-[var(--stage-ink)] border border-[var(--stage-border)]">
                <span class="block text-[11px] uppercase tracking-wider text-[var(--stage-muted)]">Conversion Rate</span>
                <span class="mt-1 block text-2xl font-bold text-amber-400 font-mono">{{ $analytics['conversion_rate'] }}%</span>
                <span class="block text-[10px] text-[var(--stage-muted)] mt-0.5">Referred to Paid</span>
            </div>
            <div class="p-3 rounded-lg bg-[var(--stage-ink)] border border-[var(--stage-border)] col-span-2 sm:col-span-1">
                <span class="block text-[11px] uppercase tracking-wider text-[var(--stage-muted)]">First-Time Conversions</span>
                <span class="mt-1 block text-2xl font-bold text-indigo-400 font-mono">{{ $analytics['first_payment_conversions'] }}</span>
                <span class="block text-[10px] text-[var(--stage-muted)] mt-0.5">Distinct conversions</span>
            </div>
        </div>

        <div class="mt-4 rounded-lg bg-[var(--stage-ink)] p-3 border border-[var(--stage-border)] text-xs text-[var(--stage-muted)] flex items-start gap-2">
            <svg class="h-4 w-4 text-[var(--stage-muted)] shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
            </svg>
            <div>
                <strong class="text-[var(--stage-cream)]">Telemetry Notice:</strong> Referral click tracking not currently authoritative. Cookie creation occurs on initial visit to your link; repeated impressions and non-converting raw clicks are not tracked as distinct metrics.
            </div>
        </div>
    </div>

    <!-- SPLIT SECTION: RECENT EARNINGS & RECENT PAYOUTS -->
    <div class="grid gap-6 lg:grid-cols-2 mb-8">
        <!-- Recent Earnings Preview -->
        <div class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-5 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-3 border-b border-[var(--stage-border)] pb-3">
                    <h3 class="text-sm font-semibold uppercase tracking-wider text-[var(--stage-cream)]">Recent Earnings</h3>
                    <a href="{{ route('affiliate.commissions.index') }}" class="text-xs text-emerald-400 hover:underline">
                        Full Ledger &rarr;
                    </a>
                </div>

                <div class="space-y-2.5">
                    @forelse ($recentCommissions as $comm)
                        <div class="flex items-center justify-between p-2.5 rounded-lg bg-[var(--stage-ink)] border border-[var(--stage-border)] text-xs">
                            <div>
                                <div class="font-medium text-[var(--stage-cream)]">{{ $comm->organization?->name ?? 'Organisation' }}</div>
                                <div class="text-[11px] text-[var(--stage-muted)]">{{ $comm->confirmed_at?->format('M j, Y') ?? '—' }} &middot; {{ $comm->is_renewal ? 'Renewal' : 'Initial Subscription' }}</div>
                            </div>
                            <div class="text-right">
                                <div class="font-mono font-bold {{ $comm->commission_amount_minor < 0 ? 'text-rose-400' : 'text-emerald-400' }}">
                                    {{ $comm->formattedCommissionAmount() }}
                                </div>
                                <span class="inline-block mt-0.5 text-[10px] font-medium rounded px-1.5 py-0.2
                                    @if($comm->status->value === 'held') bg-amber-500/20 text-amber-300
                                    @elseif($comm->status->value === 'payable') bg-cyan-500/20 text-cyan-300
                                    @elseif($comm->status->value === 'paid') bg-emerald-500/20 text-emerald-300
                                    @elseif($comm->status->value === 'reversed') bg-rose-500/20 text-rose-300
                                    @else bg-[var(--stage-border)] text-[var(--stage-muted)] @endif">
                                    {{ $comm->status->label() }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="py-6 text-center text-xs text-[var(--stage-muted)]">
                            No commission records yet. Share your referral link to earn.
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="mt-4 pt-3 border-t border-[var(--stage-border)] text-right">
                <a href="{{ route('affiliate.commissions.index') }}" class="text-xs text-[var(--stage-muted)] hover:text-[var(--stage-cream)] transition">
                    View all {{ $totalCommissionsCount ?? 0 }} transactions &rarr;
                </a>
            </div>
        </div>

        <!-- Recent Payouts Preview -->
        <div class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-5 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-3 border-b border-[var(--stage-border)] pb-3">
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-semibold uppercase tracking-wider text-[var(--stage-cream)]">Payout History</h3>
                        @if (($pendingPayoutsCount ?? 0) > 0)
                            <span class="rounded-full bg-amber-500/20 px-2 py-0.2 text-[10px] font-bold text-amber-300 border border-amber-600/40">
                                {{ $pendingPayoutsCount }} Pending Review
                            </span>
                        @endif
                    </div>
                    <a href="{{ route('affiliate.payouts.index') }}" class="text-xs text-cyan-400 hover:underline">
                        Manage Payouts &rarr;
                    </a>
                </div>

                <div class="space-y-2.5">
                    @forelse ($recentPayouts as $payout)
                        <div class="flex items-center justify-between p-2.5 rounded-lg bg-[var(--stage-ink)] border border-[var(--stage-border)] text-xs">
                            <div>
                                <div class="font-mono font-medium text-[var(--stage-cream)]">{{ $payout->payout_reference }}</div>
                                <div class="text-[11px] text-[var(--stage-muted)]">{{ $payout->created_at?->format('M j, Y') ?? '—' }}</div>
                            </div>
                            <div class="text-right">
                                <div class="font-mono font-bold text-[var(--stage-cream)]">{{ $payout->formattedAmount() }}</div>
                                <span class="inline-block mt-0.5 text-[10px] font-medium rounded px-1.5 py-0.2
                                    @if($payout->status->value === 'draft') bg-amber-500/20 text-amber-300
                                    @elseif($payout->status->value === 'approved') bg-cyan-500/20 text-cyan-300
                                    @elseif($payout->status->value === 'paid') bg-emerald-500/20 text-emerald-300
                                    @elseif($payout->status->value === 'cancelled') bg-rose-500/20 text-rose-300
                                    @else bg-[var(--stage-border)] text-[var(--stage-muted)] @endif">
                                    {{ $payout->status->label() }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="py-6 text-center text-xs text-[var(--stage-muted)]">
                            No payout requests or history yet.
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="mt-4 pt-3 border-t border-[var(--stage-border)] flex items-center justify-between text-xs">
                <span class="text-[var(--stage-muted)]">Settlement: Manual Bank Transfer</span>
                <a href="{{ route('affiliate.payouts.index') }}" class="text-cyan-400 hover:underline">
                    View Payouts &rarr;
                </a>
            </div>
        </div>
    </div>

    <script>
        function copyReferralLink(url) {
            navigator.clipboard.writeText(url).then(() => {
                const btnText = document.getElementById('copy-text');
                const orig = btnText.innerText;
                btnText.innerText = 'Copied!';
                setTimeout(() => { btnText.innerText = orig; }, 2000);
            }).catch(() => {
                prompt('Copy your referral link:', url);
            });
        }

        function shareReferralLink(url, title) {
            if (navigator.share) {
                navigator.share({
                    title: 'SoundMix Live Partner',
                    text: title,
                    url: url,
                }).catch(() => {});
            } else {
                copyReferralLink(url);
            }
        }
    </script>
@endsection
