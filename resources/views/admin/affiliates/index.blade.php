@extends('layouts.admin')

@section('title', 'Affiliates')

@section('content')
    @include('admin.affiliate-programme.partials.nav')
    <div class="console-head mb-6">
        <div>
            <p class="site-section-label">Platform Admin</p>
            <h1 class="console-title mt-2">Affiliates</h1>
            <p class="console-lead">Referral identity, qualification status, tiers, and real-time ledger balances.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.affiliate-reports.index') }}" class="console-btn console-btn-ghost text-xs">Reports</a>
            <a href="{{ route('admin.affiliates.create') }}" class="console-btn console-btn-primary text-xs">New affiliate</a>
        </div>
    </div>

    @if (session('error'))
        <p class="mb-4 rounded-lg border border-red-500/40 bg-red-500/10 px-4 py-3 text-sm text-red-200">{{ session('error') }}</p>
    @endif

    <!-- Comprehensive Filter Bar -->
    <form method="GET" action="{{ route('admin.affiliates.index') }}" class="mb-6 rounded-xl border border-[var(--stage-border)] bg-[var(--stage-surface)] p-4 space-y-3">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
            <!-- Search -->
            <div class="lg:col-span-2">
                <label for="q" class="block text-xs font-medium text-[var(--stage-muted)] mb-1">Search</label>
                <input
                    type="search"
                    id="q"
                    name="q"
                    value="{{ $q }}"
                    placeholder="Name, email, code, or city"
                    class="w-full text-xs rounded-lg border border-[var(--stage-border)] bg-zinc-900 px-3 py-2 text-white placeholder-zinc-500 focus:border-cyan-500 focus:outline-none"
                >
            </div>

            <!-- Status -->
            <div>
                <label for="status" class="block text-xs font-medium text-[var(--stage-muted)] mb-1">Status</label>
                <select id="status" name="status" class="w-full text-xs rounded-lg border border-[var(--stage-border)] bg-zinc-900 px-3 py-2 text-white focus:border-cyan-500 focus:outline-none">
                    <option value="">All Statuses</option>
                    @foreach ($statuses as $st)
                        <option value="{{ $st->value }}" {{ $selectedStatus === $st->value ? 'selected' : '' }}>
                            {{ $st->label() }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Tier -->
            <div>
                <label for="tier" class="block text-xs font-medium text-[var(--stage-muted)] mb-1">Tier</label>
                <select id="tier" name="tier" class="w-full text-xs rounded-lg border border-[var(--stage-border)] bg-zinc-900 px-3 py-2 text-white focus:border-cyan-500 focus:outline-none">
                    <option value="">All Tiers</option>
                    @foreach ($levels as $lvl)
                        <option value="{{ $lvl['name'] }}" {{ strcasecmp($selectedTier ?? '', $lvl['name']) === 0 ? 'selected' : '' }}>
                            {{ $lvl['name'] }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Qualified -->
            <div>
                <label for="qualified" class="block text-xs font-medium text-[var(--stage-muted)] mb-1">Qualification</label>
                <select id="qualified" name="qualified" class="w-full text-xs rounded-lg border border-[var(--stage-border)] bg-zinc-900 px-3 py-2 text-white focus:border-cyan-500 focus:outline-none">
                    <option value="">All</option>
                    <option value="yes" {{ $selectedQualified === 'yes' ? 'selected' : '' }}>Qualified (5+)</option>
                    <option value="no" {{ $selectedQualified === 'no' ? 'selected' : '' }}>Pending (&lt;5)</option>
                </select>
            </div>

            <!-- Balances filter -->
            <div>
                <label for="has_payable" class="block text-xs font-medium text-[var(--stage-muted)] mb-1">Payable Balance</label>
                <select id="has_payable" name="has_payable" class="w-full text-xs rounded-lg border border-[var(--stage-border)] bg-zinc-900 px-3 py-2 text-white focus:border-cyan-500 focus:outline-none">
                    <option value="">Any</option>
                    <option value="yes" {{ $selectedHasPayable === 'yes' ? 'selected' : '' }}>Has Payable (&gt; 0)</option>
                </select>
            </div>
        </div>

        <div class="flex items-center justify-between pt-2 border-t border-[var(--stage-border)]">
            <span class="text-xs text-[var(--stage-muted)]">
                Showing {{ $affiliates->total() }} matching {{ Str::plural('affiliate', $affiliates->total()) }}
            </span>
            <div class="flex items-center gap-2">
                @if ($q || $selectedStatus || $selectedQualified || $selectedHasPayable || $selectedTier)
                    <a href="{{ route('admin.affiliates.index') }}" class="text-xs text-zinc-400 hover:text-white mr-2">
                        Clear Filters
                    </a>
                @endif
                <button type="submit" class="console-btn console-btn-primary text-xs py-1.5 px-3">
                    Apply Filters
                </button>
            </div>
        </div>
    </form>

    <div class="console-table">
        <table>
            <thead>
                <tr>
                    <th>Affiliate</th>
                    <th>Code</th>
                    <th>Tier</th>
                    <th>Qualification</th>
                    <th>Status</th>
                    <th>Payable</th>
                    <th>Held</th>
                    <th>Paid</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($affiliates as $affiliate)
                    @php
                        $b = $affiliate->balances ?? ['payable_minor' => 0, 'held_minor' => 0, 'paid_minor' => 0, 'is_qualified' => false, 'qualifying_orgs_count' => 0, 'qualification_target' => 5, 'currency' => 'NGN'];
                    @endphp
                    <tr>
                        <td>
                            <div class="font-medium text-white">{{ $affiliate->user?->name ?? 'Unknown' }}</div>
                            <div class="text-xs text-[var(--stage-muted)]">{{ $affiliate->user?->email }}</div>
                            <div class="text-[11px] text-zinc-500 mt-0.5">{{ $affiliate->city ?? 'Unknown location' }}</div>
                        </td>
                        <td class="font-mono text-xs font-semibold text-cyan-300">
                            {{ $affiliate->referral_code }}
                        </td>
                        <td>
                            <span class="inline-flex rounded-md px-2 py-0.5 text-xs font-medium bg-zinc-800 text-zinc-200">
                                {{ $affiliate->level['name'] ?? 'Starter' }}
                            </span>
                        </td>
                        <td>
                            @if ($b['is_qualified'])
                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold bg-emerald-500/20 text-emerald-300">
                                    <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                    Qualified
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold bg-amber-500/20 text-amber-300">
                                    {{ $b['qualifying_orgs_count'] }} / {{ $b['qualification_target'] }}
                                </span>
                            @endif
                        </td>
                        <td>
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium
                                @if($affiliate->status->value === 'approved') bg-emerald-500/20 text-emerald-300
                                @elseif($affiliate->status->value === 'pending') bg-amber-500/20 text-amber-300
                                @elseif($affiliate->status->value === 'suspended') bg-red-500/20 text-red-300
                                @elseif($affiliate->status->value === 'rejected') bg-zinc-700 text-zinc-400
                                @else bg-cyan-500/20 text-cyan-300 @endif">
                                {{ $affiliate->status->label() }}
                            </span>
                        </td>
                        <td class="font-bold text-emerald-400">
                            {{ $b['currency'] }} {{ number_format($b['payable_minor'] / 100, 2) }}
                        </td>
                        <td class="font-semibold text-amber-300">
                            {{ $b['currency'] }} {{ number_format($b['held_minor'] / 100, 2) }}
                        </td>
                        <td class="text-xs text-[var(--stage-muted)]">
                            {{ $b['currency'] }} {{ number_format($b['paid_minor'] / 100, 2) }}
                        </td>
                        <td class="text-right whitespace-nowrap">
                            <a href="{{ route('admin.affiliates.show', $affiliate) }}" class="console-btn console-btn-ghost text-xs py-1 px-2.5">
                                Detail
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center py-8 text-[var(--stage-muted)]">
                            No affiliates found matching the selected filter criteria.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $affiliates->links() }}
    </div>
@endsection
