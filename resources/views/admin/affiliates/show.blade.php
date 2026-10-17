@extends('layouts.admin')

@section('title', 'Affiliate — ' . ($affiliate->user?->name ?? 'Detail'))

@section('content')
    @include('admin.affiliate-programme.partials.nav')
    <div class="console-head">
        <div>
            <p class="site-section-label">Platform Admin</p>
            <h1 class="console-title mt-2">{{ $affiliate->user?->name ?? 'Affiliate' }}</h1>
            <p class="console-lead">{{ $affiliate->user?->email }} &bull; Joined {{ $affiliate->created_at->format('M j, Y') }}</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.affiliate-commissions.index', ['affiliate_id' => $affiliate->id]) }}" class="console-btn console-btn-ghost text-xs">View commissions</a>
            @if ($balances['payable_minor'] > 0 && $balances['is_qualified'])
                <a href="{{ route('admin.affiliate-payouts.create', ['affiliate_id' => $affiliate->id]) }}" class="console-btn console-btn-primary text-xs">Draft Payout</a>
            @endif
            <a href="{{ route('admin.affiliates.index') }}" class="console-btn console-btn-ghost text-xs">All affiliates</a>
        </div>
    </div>

    @if (session('status'))
        <p class="mt-4 rounded-lg border border-emerald-500/40 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="mt-4 rounded-lg border border-red-500/40 bg-red-500/10 px-4 py-3 text-sm text-red-200">{{ session('error') }}</p>
    @endif

    <!-- Risk Indicators Banner -->
    @if (!empty($riskFlags) && count($riskFlags) > 0)
        <div class="mt-6 rounded-xl border border-amber-500/40 bg-amber-950/20 p-5">
            <div class="flex items-center gap-2 text-amber-300 font-semibold text-sm">
                <svg class="h-5 w-5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                Risk Indicators &amp; Integrity Review
            </div>
            <ul class="mt-3 space-y-1.5 text-xs text-amber-200 pl-7 list-disc">
                @foreach ($riskFlags as $flag)
                    <li>
                        <span class="font-semibold uppercase tracking-wider text-[11px] {{ ($flag['severity'] ?? '') === 'danger' ? 'text-red-400' : 'text-amber-300' }}">
                            [{{ strtoupper($flag['severity'] ?? 'warning') }}]
                        </span>
                        {{ $flag['message'] }}
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Overview Cards -->
    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-4">
            <dt class="text-xs uppercase tracking-wide text-[var(--stage-muted)]">Referral code</dt>
            <dd class="mt-1 font-mono text-lg text-cyan-300 font-bold">{{ $affiliate->referral_code }}</dd>
            <p class="text-[11px] text-[var(--stage-muted)] mt-1">Share: /r/{{ $affiliate->referral_code }}</p>
        </div>
        <div class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-4">
            <dt class="text-xs uppercase tracking-wide text-[var(--stage-muted)]">Status</dt>
            <dd class="mt-1">
                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold
                    @if($affiliate->status->value === 'approved') bg-emerald-500/20 text-emerald-300
                    @elseif($affiliate->status->value === 'pending') bg-amber-500/20 text-amber-300
                    @elseif($affiliate->status->value === 'suspended') bg-red-500/20 text-red-300
                    @else bg-zinc-700 text-zinc-300 @endif">
                    {{ $affiliate->status->label() }}
                </span>
            </dd>
            <p class="text-[11px] text-[var(--stage-muted)] mt-1">
                @if ($affiliate->approved_at)
                    Approved {{ $affiliate->approved_at->format('M j, Y') }}
                @elseif ($affiliate->suspended_at)
                    Suspended {{ $affiliate->suspended_at->format('M j, Y') }}
                @else
                    Awaiting review
                @endif
            </p>
        </div>
        <div class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-4">
            <dt class="text-xs uppercase tracking-wide text-[var(--stage-muted)]">Payout Qualification</dt>
            <dd class="mt-1 text-sm font-semibold {{ $balances['is_qualified'] ? 'text-emerald-400' : 'text-amber-300' }}">
                @if ($balances['is_qualified'])
                    Qualified (Permanent)
                    <span class="block text-xs font-normal text-zinc-400">Earned {{ $affiliate->payout_qualified_at?->format('M j, Y') }}</span>
                @else
                    {{ $balances['qualifying_orgs_count'] }} / {{ $balances['qualification_target'] }} paying churches
                    <span class="block text-xs font-normal text-zinc-400">Needs {{ max(0, $balances['qualification_target'] - $balances['qualifying_orgs_count']) }} more for payouts</span>
                @endif
            </dd>
        </div>
        <div class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-4">
            <dt class="text-xs uppercase tracking-wide text-[var(--stage-muted)]">Programme Tier</dt>
            <dd class="mt-1 text-lg font-bold text-cyan-300">
                {{ $tierProgress['current']['name'] ?? 'Starter' }}
            </dd>
            <div class="mt-2">
                <div class="w-full bg-zinc-800 rounded-full h-1.5">
                    <div class="bg-cyan-500 h-1.5 rounded-full" style="width: {{ $tierProgress['progress_percent'] }}%"></div>
                </div>
                <p class="text-[11px] text-[var(--stage-muted)] mt-1">
                    @if ($tierProgress['next'])
                        {{ $tierProgress['remaining'] }} more paying org(s) to {{ $tierProgress['next']['name'] }}
                    @else
                        Top tier reached
                    @endif
                </p>
            </div>
        </div>
    </div>

    <!-- Authoritative Balances Breakdown -->
    <section class="mt-6 rounded-xl border border-[var(--stage-border)] bg-[var(--stage-surface)] p-5">
        <h2 class="text-xs uppercase tracking-wide font-semibold text-[var(--stage-muted)]">Authoritative Commission Balances</h2>
        <div class="mt-3 grid grid-cols-2 gap-4 sm:grid-cols-5">
            <div class="rounded-lg border border-[var(--stage-border)] bg-[var(--stage-panel)] p-3">
                <span class="block text-xs text-[var(--stage-muted)]">Payable (Ready)</span>
                <span class="mt-1 block text-lg font-bold text-emerald-400">
                    {{ $balances['currency'] }} {{ number_format($balances['payable_minor'] / 100, 2) }}
                </span>
            </div>
            <div class="rounded-lg border border-[var(--stage-border)] bg-[var(--stage-panel)] p-3">
                <span class="block text-xs text-[var(--stage-muted)]">Held (14-day hold)</span>
                <span class="mt-1 block text-lg font-semibold text-amber-300">
                    {{ $balances['currency'] }} {{ number_format($balances['held_minor'] / 100, 2) }}
                </span>
            </div>
            <div class="rounded-lg border border-[var(--stage-border)] bg-[var(--stage-panel)] p-3">
                <span class="block text-xs text-[var(--stage-muted)]">Current Unpaid</span>
                <span class="mt-1 block text-lg font-semibold text-white">
                    {{ $balances['currency'] }} {{ number_format($balances['current_unpaid_minor'] / 100, 2) }}
                </span>
            </div>
            <div class="rounded-lg border border-[var(--stage-border)] bg-[var(--stage-panel)] p-3">
                <span class="block text-xs text-[var(--stage-muted)]">Paid (Settled)</span>
                <span class="mt-1 block text-lg font-semibold text-cyan-300">
                    {{ $balances['currency'] }} {{ number_format($balances['paid_minor'] / 100, 2) }}
                </span>
            </div>
            <div class="rounded-lg border border-[var(--stage-border)] bg-[var(--stage-panel)] p-3">
                <span class="block text-xs text-[var(--stage-muted)]">Reversed / Adjustments</span>
                <span class="mt-1 block text-lg font-semibold text-red-400">
                    {{ $balances['currency'] }} {{ number_format($balances['reversed_minor'] / 100, 2) }}
                </span>
            </div>
        </div>
    </section>

    <!-- Administrative State Actions -->
    <div class="mt-6 flex flex-wrap gap-3">
        @if (in_array($affiliate->status->value, ['invited', 'pending', 'rejected'], true))
            <form method="POST" action="{{ route('admin.affiliates.approve', $affiliate) }}">
                @csrf
                <button type="submit" class="console-btn console-btn-primary text-xs">Approve Partner</button>
            </form>
        @endif
        @if (in_array($affiliate->status->value, ['invited', 'pending'], true))
            <form method="POST" action="{{ route('admin.affiliates.reject', $affiliate) }}">
                @csrf
                <button type="submit" class="console-btn console-btn-ghost text-xs">Reject Partner</button>
            </form>
        @endif
        @if ($affiliate->status->value === 'approved')
            <form method="POST" action="{{ route('admin.affiliates.suspend', $affiliate) }}">
                @csrf
                <button type="submit" class="console-btn console-btn-ghost text-xs text-red-300">Suspend Partner</button>
            </form>
        @endif
        @if ($affiliate->status->value === 'suspended')
            <form method="POST" action="{{ route('admin.affiliates.restore', $affiliate) }}">
                @csrf
                <button type="submit" class="console-btn console-btn-primary text-xs">Restore Partner</button>
            </form>
        @endif
    </div>

    <!-- Referred Organisations Breakdown -->
    <section class="mt-8 rounded-xl border border-[var(--stage-border)] bg-[var(--stage-surface)] p-5">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-sm font-semibold uppercase tracking-wide text-white">Referred Organisations Breakdown</h2>
                <p class="text-xs text-[var(--stage-muted)]">{{ $referredOrganizations->count() }} total church organisations attributed</p>
            </div>
            <div class="flex items-center gap-2 text-xs">
                <span class="text-emerald-400 font-semibold">{{ $earningCount }} Earning eligible</span>
                <span class="text-zinc-500">&bull;</span>
                <span class="text-red-400 font-semibold">{{ $blockedCount }} Disqualified/Self-referral</span>
            </div>
        </div>

        <div class="console-table">
            <table>
                <thead>
                    <tr>
                        <th>Organisation</th>
                        <th>Attribution Source</th>
                        <th>Attributed Date</th>
                        <th>Subscription Status</th>
                        <th>Earning Eligible</th>
                        <th>Commissions Count</th>
                        <th>Total Commission Earned</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($referredOrganizations as $item)
                        <tr>
                            <td>
                                <div class="font-medium text-white">{{ $item['organization']?->name ?? 'Removed Organisation' }}</div>
                                <div class="text-xs text-[var(--stage-muted)]">Slug: {{ $item['organization']?->slug ?? '—' }}</div>
                            </td>
                            <td class="text-xs text-[var(--stage-muted)]">
                                {{ $item['source']->label() }}
                            </td>
                            <td class="text-xs text-[var(--stage-muted)]">
                                {{ $item['attributed_at']?->format('M j, Y') ?? '—' }}
                            </td>
                            <td>
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium bg-zinc-800 text-zinc-300">
                                    {{ ucfirst(str_replace('_', ' ', $item['subscription_status'])) }}
                                </span>
                            </td>
                            <td>
                                @if ($item['is_earning'])
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold bg-emerald-500/20 text-emerald-300">
                                        Eligible
                                    </span>
                                @else
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold bg-red-500/20 text-red-300">
                                        Blocked / Ineligible
                                    </span>
                                @endif
                            </td>
                            <td class="text-xs text-white font-semibold">
                                {{ $item['total_commissions_count'] }}
                            </td>
                            <td class="font-bold text-emerald-400">
                                {{ $balances['currency'] }} {{ number_format($item['total_earned_minor'] / 100, 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-6 text-[var(--stage-muted)]">
                                No organisations referred yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <!-- Payout History -->
    <section class="mt-8 rounded-xl border border-[var(--stage-border)] bg-[var(--stage-surface)] p-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-white">Payout History ({{ $payouts->count() }})</h2>
            @if ($balances['payable_minor'] > 0 && $balances['is_qualified'])
                <a href="{{ route('admin.affiliate-payouts.create', ['affiliate_id' => $affiliate->id]) }}" class="console-btn console-btn-primary text-xs py-1 px-3">
                    New Payout Batch
                </a>
            @endif
        </div>

        <div class="console-table">
            <table>
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Date Requested</th>
                        <th>Amount</th>
                        <th>Items Count</th>
                        <th>Status</th>
                        <th>Approved Date</th>
                        <th>Paid Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($payouts as $payout)
                        <tr>
                            <td class="font-mono text-xs font-semibold text-cyan-300">{{ $payout->payout_reference }}</td>
                            <td class="text-xs text-[var(--stage-muted)]">{{ $payout->created_at->format('M j, Y H:i') }}</td>
                            <td class="font-bold text-white">{{ $payout->formattedAmount() }}</td>
                            <td class="text-xs text-[var(--stage-muted)]">{{ $payout->commissions_count }}</td>
                            <td>
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium
                                    @if($payout->status->value === 'approved') bg-cyan-500/20 text-cyan-300
                                    @elseif($payout->status->value === 'paid') bg-emerald-500/20 text-emerald-300
                                    @elseif($payout->status->value === 'cancelled') bg-red-500/20 text-red-300
                                    @else bg-zinc-700 text-zinc-300 @endif">
                                    {{ $payout->status->label() }}
                                </span>
                            </td>
                            <td class="text-xs text-[var(--stage-muted)]">{{ $payout->approved_at?->format('M j, Y') ?? '—' }}</td>
                            <td class="text-xs text-[var(--stage-muted)]">{{ $payout->paid_at?->format('M j, Y') ?? '—' }}</td>
                            <td>
                                <a href="{{ route('admin.affiliate-payouts.show', $payout) }}" class="console-btn console-btn-ghost text-xs py-1 px-2.5">
                                    View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-6 text-[var(--stage-muted)]">No payout batches created for this affiliate yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <!-- Commission Audit Trail -->
    <section class="mt-8 rounded-xl border border-[var(--stage-border)] bg-[var(--stage-surface)] p-5">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-white mb-4">Commission Audit Trail (Recent 20)</h2>

        <div class="console-table">
            <table>
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>Action</th>
                        <th>Actor</th>
                        <th>Transition</th>
                        <th>Amount</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($auditLogs as $audit)
                        <tr>
                            <td class="text-xs text-[var(--stage-muted)]">{{ $audit->created_at?->format('M j, Y H:i:s') ?? '—' }}</td>
                            <td class="font-mono text-xs text-white">{{ $audit->action }}</td>
                            <td class="text-xs text-[var(--stage-muted)]">{{ $audit->actor?->name ?? 'System' }}</td>
                            <td class="text-xs">
                                @if ($audit->from_status || $audit->to_status)
                                    <span class="text-zinc-400">{{ $audit->from_status ?? '—' }}</span> &rarr;
                                    <span class="text-cyan-300 font-semibold">{{ $audit->to_status ?? '—' }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-xs font-semibold {{ $audit->amount_minor ? 'text-white' : 'text-zinc-500' }}">
                                @if ($audit->amount_minor !== null)
                                    {{ $audit->currency ?? 'NGN' }} {{ number_format($audit->amount_minor / 100, 2) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-xs text-[var(--stage-muted)] max-w-sm truncate">{{ $audit->notes ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-6 text-[var(--stage-muted)]">No audit entries recorded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <!-- Location Settings Card -->
    <section class="mt-8 max-w-lg rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-6">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-[var(--stage-muted)]">Location Settings</h2>
        <form method="POST" action="{{ route('admin.affiliates.location', $affiliate) }}" class="mt-4 space-y-4">
            @csrf
            @method('PUT')
            <div>
                <label for="country_code" class="block text-xs font-medium text-zinc-300">Country code</label>
                <input id="country_code" type="text" name="country_code" value="{{ old('country_code', $affiliate->country_code) }}" required maxlength="2"
                    class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white text-xs">
            </div>
            <div>
                <label for="state_region" class="block text-xs font-medium text-zinc-300">State or region</label>
                <input id="state_region" type="text" name="state_region" value="{{ old('state_region', $affiliate->state_region) }}" required maxlength="120"
                    class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white text-xs">
            </div>
            <div>
                <label for="city" class="block text-xs font-medium text-zinc-300">City</label>
                <input id="city" type="text" name="city" value="{{ old('city', $affiliate->city) }}" required maxlength="120"
                    class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white text-xs">
            </div>
            <button type="submit" class="console-btn console-btn-primary text-xs">Save location</button>
        </form>
    </section>
@endsection
