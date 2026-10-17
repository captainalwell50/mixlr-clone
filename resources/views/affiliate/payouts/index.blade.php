@extends('layouts.app')

@section('title', 'Affiliate Payouts')

@section('content')
    <div class="mb-6">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="site-section-label">Affiliate Partner</p>
                <h1 class="text-2xl font-bold text-[var(--stage-cream)]">Payouts & Settlements</h1>
                <p class="text-sm text-[var(--stage-muted)]">Manage your payout requests, payment history, and settlement statuses.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('affiliate.commissions.index') }}" class="console-btn console-btn-ghost text-xs">
                    View Earnings Ledger
                </a>
            </div>
        </div>
    </div>

    @include('affiliate.partials.nav')

    @if (session('status'))
        <div class="mb-6 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-sm text-emerald-300">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-6 rounded-xl border border-red-500/30 bg-red-500/10 p-4 text-sm text-red-300">
            <ul class="list-disc pl-5 space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Balances & Request Payout Section -->
    <div class="grid gap-6 lg:grid-cols-3 mb-8">
        <!-- Balance Overview -->
        <div class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-surface)] p-5">
            <span class="block text-xs uppercase tracking-wider text-[var(--stage-muted)]">Available Payable Balance</span>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-3xl font-extrabold text-emerald-400">
                    {{ $currency }} {{ number_format($balances['payable_minor'] / 100, 2) }}
                </span>
            </div>
            <p class="mt-1 text-xs text-[var(--stage-muted)]">
                Unencumbered matured commissions ready for immediate payout request.
            </p>

            <div class="mt-4 pt-4 border-t border-[var(--stage-border)] grid grid-cols-2 gap-3 text-xs">
                <div>
                    <span class="text-[var(--stage-muted)] block">Held in 14-day hold:</span>
                    <span class="font-semibold text-amber-300">{{ $currency }} {{ number_format($balances['held_minor'] / 100, 2) }}</span>
                </div>
                <div>
                    <span class="text-[var(--stage-muted)] block">Total settled lifetime:</span>
                    <span class="font-semibold text-cyan-300">{{ $currency }} {{ number_format($balances['paid_minor'] / 100, 2) }}</span>
                </div>
            </div>
        </div>

        <!-- Qualification Status & Rules -->
        <div class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-surface)] p-5">
            <span class="block text-xs uppercase tracking-wider text-[var(--stage-muted)]">Qualification Rule</span>
            <div class="mt-2 flex items-center justify-between">
                @if ($balances['is_qualified'])
                    <span class="inline-flex items-center gap-1.5 text-sm font-semibold text-emerald-400">
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                        Payout Qualified (Permanent)
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 text-sm font-semibold text-amber-300">
                        Pending Qualification ({{ $balances['qualifying_orgs_count'] }}/{{ $balances['qualification_target'] }})
                    </span>
                @endif
            </div>

            <div class="mt-3">
                <div class="w-full bg-zinc-800 rounded-full h-2">
                    <div class="bg-emerald-500 h-2 rounded-full transition-all duration-300"
                        style="width: {{ min(100, (int) round(($balances['qualifying_orgs_count'] / max(1, $balances['qualification_target'])) * 100)) }}%">
                    </div>
                </div>
            </div>

            <p class="mt-3 text-xs text-[var(--stage-muted)]">
                @if ($balances['is_qualified'])
                    You have unlocked lifetime payout eligibility with {{ $balances['qualifying_orgs_count'] }} distinct paying referred organisations.
                @else
                    Commissions accrue as HELD until {{ $balances['qualification_target'] }} distinct paying organisations are referred. Once reached, payout eligibility is permanent.
                @endif
            </p>
        </div>

        <!-- Request Payout Action Box -->
        <div class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-surface)] p-5 flex flex-col justify-between">
            <div>
                <span class="block text-xs uppercase tracking-wider text-[var(--stage-muted)]">Request Settlement</span>
                <p class="mt-1 text-xs text-[var(--stage-muted)]">
                    Payouts are reviewed and manually processed via bank transfer.
                </p>
            </div>

            <div class="mt-4">
                @if (! $balances['is_qualified'])
                    <div class="rounded-lg bg-amber-500/10 border border-amber-500/20 p-3 text-xs text-amber-300">
                        <strong>Qualification required:</strong> Reach {{ $balances['qualification_target'] }} paying referred churches to submit payout requests.
                    </div>
                @elseif ($balances['payable_minor'] <= 0)
                    <div class="rounded-lg bg-zinc-800/60 border border-zinc-700 p-3 text-xs text-zinc-400">
                        No payable balance is currently unallocated. Check your earnings ledger for commissions currently maturing through the 14-day hold.
                    </div>
                @elseif ($minPayoutMinor > 0 && $balances['payable_minor'] < $minPayoutMinor)
                    <div class="rounded-lg bg-zinc-800/60 border border-zinc-700 p-3 text-xs text-zinc-400">
                        Minimum payout threshold is {{ $currency }} {{ number_format($minPayoutMinor / 100, 2) }}. Your current payable balance is {{ $currency }} {{ number_format($balances['payable_minor'] / 100, 2) }}.
                    </div>
                @else
                    @if ($affiliate->hasPayoutDestination())
                        <div class="mb-3 rounded-lg border border-[var(--stage-border)] bg-[var(--stage-ink)] p-3 text-xs">
                            <span class="block text-[11px] uppercase tracking-wider text-[var(--stage-muted)]">Designated Settlement Account</span>
                            <div class="mt-1 font-semibold text-emerald-300">
                                {{ $affiliate->bank_name }} ({{ $affiliate->maskedAccountNumber() }})
                            </div>
                            <div class="text-[var(--stage-muted)] text-[11px]">
                                {{ $affiliate->account_name }}
                            </div>
                        </div>

                        <form method="POST" action="{{ route('affiliate.payouts.request') }}" class="space-y-3">
                            @csrf
                            <div>
                                <label for="notes" class="block text-xs font-medium text-[var(--stage-muted)] mb-1">
                                    Settlement Memo (Optional)
                                </label>
                                <input
                                    type="text"
                                    id="notes"
                                    name="notes"
                                    maxlength="500"
                                    placeholder="Optional note for administrator review"
                                    class="w-full text-xs rounded-lg border border-[var(--stage-border)] bg-[var(--stage-ink)] px-3 py-2 text-[var(--stage-cream)] placeholder-[var(--stage-muted)] focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                                />
                            </div>
                            <button
                                type="submit"
                                class="w-full console-btn console-btn-primary text-xs py-2 justify-center font-bold"
                            >
                                Request Payout ({{ $currency }} {{ number_format($balances['payable_minor'] / 100, 2) }})
                            </button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('affiliate.payouts.request') }}" class="space-y-2.5">
                            @csrf
                            <p class="text-xs text-amber-300 font-medium">Add bank destination to request payout:</p>
                            <div>
                                <input
                                    type="text"
                                    name="bank_name"
                                    required
                                    placeholder="Bank Name (e.g. Zenith Bank)"
                                    class="w-full text-xs rounded-lg border border-[var(--stage-border)] bg-[var(--stage-ink)] px-3 py-1.5 text-[var(--stage-cream)] placeholder-[var(--stage-muted)] focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                                />
                            </div>
                            <div>
                                <input
                                    type="text"
                                    name="account_name"
                                    required
                                    placeholder="Account Name (e.g. John Doe)"
                                    class="w-full text-xs rounded-lg border border-[var(--stage-border)] bg-[var(--stage-ink)] px-3 py-1.5 text-[var(--stage-cream)] placeholder-[var(--stage-muted)] focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                                />
                            </div>
                            <div>
                                <input
                                    type="text"
                                    name="account_number"
                                    required
                                    placeholder="Account Number (Encrypted at rest)"
                                    class="w-full text-xs rounded-lg border border-[var(--stage-border)] bg-[var(--stage-ink)] px-3 py-1.5 text-[var(--stage-cream)] placeholder-[var(--stage-muted)] focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                                />
                            </div>
                            <div>
                                <input
                                    type="text"
                                    name="notes"
                                    maxlength="500"
                                    placeholder="Optional settlement memo"
                                    class="w-full text-xs rounded-lg border border-[var(--stage-border)] bg-[var(--stage-ink)] px-3 py-1.5 text-[var(--stage-cream)] placeholder-[var(--stage-muted)] focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                                />
                            </div>
                            <button
                                type="submit"
                                class="w-full console-btn console-btn-primary text-xs py-2 justify-center font-bold"
                            >
                                Save Destination & Request Payout
                            </button>
                        </form>
                    @endif
                @endif
            </div>
        </div>
    </div>

    <!-- Payout Destination Management Card -->
    <div class="mb-8 rounded-xl border border-[var(--stage-border)] bg-[var(--stage-surface)] p-5">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h3 class="text-sm font-semibold text-[var(--stage-cream)]">Settlement Bank Account</h3>
                <p class="text-xs text-[var(--stage-muted)] mt-0.5">
                    Account numbers are encrypted at rest with AES-256 and masked in presentations. Only platform finance administrators can view details for manual disbursals.
                </p>
            </div>
            @if ($affiliate->hasPayoutDestination())
                <div class="text-xs text-right">
                    <span class="text-[var(--stage-muted)] block">Current Destination:</span>
                    <span class="font-mono font-bold text-emerald-400">{{ $affiliate->bank_name }} ({{ $affiliate->maskedAccountNumber() }})</span>
                    <span class="text-[var(--stage-muted)] block">{{ $affiliate->account_name }}</span>
                </div>
            @endif
        </div>

        <div class="mt-4 pt-4 border-t border-[var(--stage-border)]">
            <details class="group">
                <summary class="cursor-pointer text-xs font-semibold text-cyan-400 hover:text-cyan-300 select-none">
                    {{ $affiliate->hasPayoutDestination() ? 'Update Settlement Bank Details' : 'Configure Settlement Bank Details' }}
                </summary>
                <form method="POST" action="{{ route('affiliate.payouts.destination') }}" class="mt-4 grid gap-3 sm:grid-cols-4 max-w-3xl">
                    @csrf
                    <div>
                        <label class="block text-xs text-[var(--stage-muted)] mb-1">Bank Name</label>
                        <input
                            type="text"
                            name="bank_name"
                            value="{{ old('bank_name', $affiliate->bank_name) }}"
                            required
                            placeholder="e.g. Access Bank"
                            class="w-full text-xs rounded-lg border border-[var(--stage-border)] bg-[var(--stage-ink)] px-3 py-2 text-[var(--stage-cream)] focus:border-emerald-500 focus:outline-none"
                        />
                    </div>
                    <div>
                        <label class="block text-xs text-[var(--stage-muted)] mb-1">Bank Code (Optional)</label>
                        <input
                            type="text"
                            name="bank_code"
                            value="{{ old('bank_code', $affiliate->bank_code) }}"
                            placeholder="e.g. 044"
                            class="w-full text-xs rounded-lg border border-[var(--stage-border)] bg-[var(--stage-ink)] px-3 py-2 text-[var(--stage-cream)] focus:border-emerald-500 focus:outline-none"
                        />
                    </div>
                    <div>
                        <label class="block text-xs text-[var(--stage-muted)] mb-1">Account Name</label>
                        <input
                            type="text"
                            name="account_name"
                            value="{{ old('account_name', $affiliate->account_name) }}"
                            required
                            placeholder="e.g. Emmanuel Adebayo"
                            class="w-full text-xs rounded-lg border border-[var(--stage-border)] bg-[var(--stage-ink)] px-3 py-2 text-[var(--stage-cream)] focus:border-emerald-500 focus:outline-none"
                        />
                    </div>
                    <div>
                        <label class="block text-xs text-[var(--stage-muted)] mb-1">Account Number</label>
                        <input
                            type="text"
                            name="account_number"
                            required
                            placeholder="{{ $affiliate->hasPayoutDestination() ? 'Enter new account number' : 'e.g. 0123456789' }}"
                            class="w-full text-xs rounded-lg border border-[var(--stage-border)] bg-[var(--stage-ink)] px-3 py-2 text-[var(--stage-cream)] focus:border-emerald-500 focus:outline-none"
                        />
                    </div>
                    <div class="sm:col-span-4">
                        <button type="submit" class="console-btn console-btn-ghost text-xs">
                            Save Bank Details
                        </button>
                    </div>
                </form>
            </details>
        </div>
    </div>

    <!-- Payout History Table -->
    <section class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-surface)] p-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-semibold text-[var(--stage-cream)]">Payout History</h2>
            <span class="text-xs text-[var(--stage-muted)]">{{ $payouts->total() }} recorded {{ Str::plural('batch', $payouts->total()) }}</span>
        </div>

        <div class="console-table">
            <table>
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Date Requested</th>
                        <th>Amount</th>
                        <th>Commissions Count</th>
                        <th>Status</th>
                        <th>Destination</th>
                        <th>Approved Date</th>
                        <th>Paid Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($payouts as $payout)
                        <tr>
                            <td class="font-mono text-xs font-semibold text-emerald-400">
                                {{ $payout->payout_reference }}
                            </td>
                            <td class="text-xs text-[var(--stage-muted)]">
                                {{ $payout->created_at?->format('M j, Y H:i') ?? '—' }}
                            </td>
                            <td class="font-bold text-[var(--stage-cream)]">
                                {{ $payout->formattedAmount() }}
                            </td>
                            <td class="text-xs text-[var(--stage-muted)]">
                                {{ $payout->commissions_count }}
                            </td>
                            <td>
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium
                                    @if($payout->status->value === 'draft') bg-amber-500/20 text-amber-300
                                    @elseif($payout->status->value === 'approved') bg-cyan-500/20 text-cyan-300
                                    @elseif($payout->status->value === 'paid') bg-emerald-500/20 text-emerald-300
                                    @elseif($payout->status->value === 'cancelled') bg-red-500/20 text-red-300
                                    @else bg-zinc-700 text-zinc-300 @endif">
                                    {{ $payout->status->label() }}
                                </span>
                            </td>
                            <td class="text-xs text-[var(--stage-muted)]">
                                {{ $payout->payoutDestinationSummary() ?? '—' }}
                            </td>
                            <td class="text-xs text-[var(--stage-muted)]">
                                {{ $payout->approved_at?->format('M j, Y') ?? '—' }}
                            </td>
                            <td class="text-xs text-[var(--stage-muted)]">
                                {{ $payout->paid_at?->format('M j, Y') ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-8 text-[var(--stage-muted)]">
                                No payout requests submitted yet. Once you accrue payable commissions after meeting qualification requirements, you can request manual settlement here.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($payouts->hasPages())
            <div class="mt-4">
                {{ $payouts->links() }}
            </div>
        @endif
    </section>
@endsection
