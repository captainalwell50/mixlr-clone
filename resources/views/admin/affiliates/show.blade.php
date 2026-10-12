@extends('layouts.admin')

@section('title', 'Affiliate — ' . ($affiliate->user?->name ?? 'Detail'))

@section('content')
    @include('admin.affiliate-programme.partials.nav')
    <div class="console-head">
        <div>
            <p class="site-section-label">Platform Admin</p>
            <h1 class="console-title mt-2">{{ $affiliate->user?->name ?? 'Affiliate' }}</h1>
            <p class="console-lead">{{ $affiliate->user?->email }}</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.affiliate-commissions.index', ['affiliate_id' => $affiliate->id]) }}" class="console-btn console-btn-ghost">View commissions</a>
            @if ($balances['payable_minor'] > 0)
                <a href="{{ route('admin.affiliate-payouts.create', ['affiliate_id' => $affiliate->id]) }}" class="console-btn console-btn-primary">Payout batch</a>
            @endif
            <a href="{{ route('admin.affiliates.index') }}" class="console-btn console-btn-ghost">All affiliates</a>
        </div>
    </div>

    @if (session('status'))
        <p class="mt-4 rounded-lg border border-emerald-500/40 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="mt-4 rounded-lg border border-red-500/40 bg-red-500/10 px-4 py-3 text-sm text-red-200">{{ session('error') }}</p>
    @endif

    <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-4">
            <dt class="text-xs uppercase tracking-wide text-[var(--stage-muted)]">Referral code</dt>
            <dd class="mt-1 font-mono text-lg text-[var(--stage-cream)]">{{ $affiliate->referral_code }}</dd>
        </div>
        <div class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-4">
            <dt class="text-xs uppercase tracking-wide text-[var(--stage-muted)]">Status</dt>
            <dd class="mt-1 text-[var(--stage-cream)]">{{ $affiliate->status->label() }}</dd>
        </div>
        <div class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-4">
            <dt class="text-xs uppercase tracking-wide text-[var(--stage-muted)]">Payout Qualification</dt>
            <dd class="mt-1 text-sm font-semibold {{ $balances['is_qualified'] ? 'text-emerald-400' : 'text-amber-300' }}">
                @if ($balances['is_qualified'])
                    Qualified (Permanent)
                    <span class="block text-xs font-normal text-zinc-400">Since {{ $affiliate->payout_qualified_at?->format('M j, Y') }}</span>
                @else
                    {{ $balances['qualifying_orgs_count'] }} / {{ $balances['qualification_target'] }} paying churches
                    <span class="block text-xs font-normal text-zinc-400">Needs {{ max(0, $balances['qualification_target'] - $balances['qualifying_orgs_count']) }} more for payouts</span>
                @endif
            </dd>
        </div>
        <div class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-4">
            <dt class="text-xs uppercase tracking-wide text-[var(--stage-muted)]">Programme Level</dt>
            <dd class="mt-1 text-sm font-semibold text-cyan-300">
                {{ $level['name'] ?? 'Starter' }}
            </dd>
            <p class="mt-0.5 text-xs text-zinc-400">{{ $balances['qualifying_orgs_count'] }} paying church(es)</p>
        </div>
    </div>

    <!-- Authoritative Balances Breakdown -->
    <section class="mt-6 rounded-xl border border-zinc-800 bg-zinc-950/60 p-5">
        <h2 class="text-xs uppercase tracking-wide font-semibold text-zinc-400">Authoritative Commission Balances</h2>
        <div class="mt-3 grid grid-cols-2 gap-4 sm:grid-cols-5">
            <div class="rounded-lg border border-zinc-800/80 bg-zinc-900/60 p-3">
                <span class="block text-xs text-zinc-400">Payable (Ready)</span>
                <span class="mt-1 block text-lg font-bold text-emerald-400">
                    {{ $balances['currency'] }} {{ number_format($balances['payable_minor'] / 100, 2) }}
                </span>
            </div>
            <div class="rounded-lg border border-zinc-800/80 bg-zinc-900/60 p-3">
                <span class="block text-xs text-zinc-400">Held (14-day hold)</span>
                <span class="mt-1 block text-lg font-semibold text-amber-300">
                    {{ $balances['currency'] }} {{ number_format($balances['held_minor'] / 100, 2) }}
                </span>
            </div>
            <div class="rounded-lg border border-zinc-800/80 bg-zinc-900/60 p-3">
                <span class="block text-xs text-zinc-400">Current Unpaid</span>
                <span class="mt-1 block text-lg font-semibold text-white">
                    {{ $balances['currency'] }} {{ number_format($balances['current_unpaid_minor'] / 100, 2) }}
                </span>
            </div>
            <div class="rounded-lg border border-zinc-800/80 bg-zinc-900/60 p-3">
                <span class="block text-xs text-zinc-400">Paid (Lifetime)</span>
                <span class="mt-1 block text-lg font-semibold text-cyan-300">
                    {{ $balances['currency'] }} {{ number_format($balances['paid_minor'] / 100, 2) }}
                </span>
            </div>
            <div class="rounded-lg border border-zinc-800/80 bg-zinc-900/60 p-3">
                <span class="block text-xs text-zinc-400">Reversed / Adjustments</span>
                <span class="mt-1 block text-lg font-semibold text-red-400">
                    {{ $balances['currency'] }} {{ number_format($balances['reversed_minor'] / 100, 2) }}
                </span>
            </div>
        </div>
    </section>

    <div class="mt-6 flex flex-wrap gap-3">
        @if (in_array($affiliate->status->value, ['invited', 'pending', 'rejected'], true))
            <form method="POST" action="{{ route('admin.affiliates.approve', $affiliate) }}">
                @csrf
                <button type="submit" class="console-btn console-btn-primary">Approve</button>
            </form>
        @endif
        @if (in_array($affiliate->status->value, ['invited', 'pending'], true))
            <form method="POST" action="{{ route('admin.affiliates.reject', $affiliate) }}">
                @csrf
                <button type="submit" class="console-btn console-btn-ghost">Reject</button>
            </form>
        @endif
        @if ($affiliate->status->value === 'approved')
            <form method="POST" action="{{ route('admin.affiliates.suspend', $affiliate) }}">
                @csrf
                <button type="submit" class="console-btn console-btn-ghost">Suspend</button>
            </form>
        @endif
        @if ($affiliate->status->value === 'suspended')
            <form method="POST" action="{{ route('admin.affiliates.restore', $affiliate) }}">
                @csrf
                <button type="submit" class="console-btn console-btn-primary">Restore</button>
            </form>
        @endif
    </div>

    <!-- Recent Commissions -->
    <section class="mt-8">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-[var(--stage-muted)]">Recent Commissions</h2>
            <a href="{{ route('admin.affiliate-commissions.index', ['affiliate_id' => $affiliate->id]) }}" class="text-xs text-cyan-400 hover:underline">
                View all commissions ({{ $affiliate->commissions_count }}) &rarr;
            </a>
        </div>
        <div class="console-table mt-3">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Church</th>
                        <th>Reference</th>
                        <th>Gross</th>
                        <th>Rate</th>
                        <th>Commission</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentCommissions as $comm)
                        <tr>
                            <td class="text-xs text-[var(--stage-muted)]">{{ $comm->confirmed_at?->format('Y-m-d') }}</td>
                            <td>{{ $comm->organization?->name ?? 'Church' }}</td>
                            <td class="font-mono text-xs">{{ Str::limit($comm->payment_reference, 14) }}</td>
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
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-xs text-[var(--stage-muted)]">No commissions recorded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="mt-8 max-w-lg rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-6">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-[var(--stage-muted)]">Location</h2>
        <form method="POST" action="{{ route('admin.affiliates.location', $affiliate) }}" class="mt-4 space-y-4">
            @csrf
            @method('PUT')
            <div>
                <label for="country_code" class="block text-sm font-medium text-zinc-300">Country code</label>
                <input id="country_code" type="text" name="country_code" value="{{ old('country_code', $affiliate->country_code) }}" required maxlength="2"
                    class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white">
            </div>
            <div>
                <label for="state_region" class="block text-sm font-medium text-zinc-300">State or region</label>
                <input id="state_region" type="text" name="state_region" value="{{ old('state_region', $affiliate->state_region) }}" required maxlength="120"
                    class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white">
            </div>
            <div>
                <label for="city" class="block text-sm font-medium text-zinc-300">City</label>
                <input id="city" type="text" name="city" value="{{ old('city', $affiliate->city) }}" required maxlength="120"
                    class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white">
            </div>
            <button type="submit" class="console-btn console-btn-primary">Save location</button>
        </form>
    </section>

    <section class="mt-8">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-[var(--stage-muted)]">Attributed channels ({{ $affiliate->attributions_count }})</h2>
        <div class="console-table mt-4">
            <table>
                <thead>
                    <tr>
                        <th>Channel</th>
                        <th>Source</th>
                        <th>Earning</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($attributions as $attribution)
                        <tr>
                            <td class="text-[var(--stage-cream)]">{{ $attribution->organization?->name ?? 'Removed channel' }}</td>
                            <td class="text-[var(--stage-muted)]">{{ $attribution->source->label() }}</td>
                            <td class="text-[var(--stage-muted)]">{{ $attribution->isEarningEligible() ? 'Eligible' : 'Not eligible' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-[var(--stage-muted)]">No attributed channels yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
