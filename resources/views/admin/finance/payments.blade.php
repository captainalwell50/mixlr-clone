@extends('layouts.admin')

@section('title', 'Confirmed Creator Subscription Payments')

@section('content')
<div class="space-y-6 max-w-7xl mx-auto">
    <!-- PAGE HEADER -->
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-zinc-800 pb-5">
        <div>
            <div class="flex items-center gap-2">
                <span class="inline-flex h-2 w-2 rounded-full bg-emerald-400"></span>
                <span class="text-xs font-semibold uppercase tracking-wider text-emerald-400">Commercial Audit</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-white mt-1">Confirmed Creator Payments</h1>
            <p class="text-xs text-zinc-400 mt-0.5">Verified payment gateway transactions for creator subscriptions &middot; Authoritative payment records.</p>
        </div>
        <div>
            <a href="{{ route('admin.finance.index') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-1.5 text-xs font-medium text-zinc-200 hover:bg-zinc-700 hover:text-white transition">
                &larr; Back to Finance Overview
            </a>
        </div>
    </div>

    @include('admin.finance.partials.nav')

    <!-- FILTER BAR -->
    <form method="GET" action="{{ route('admin.finance.payments') }}" class="rounded-xl border border-zinc-800 bg-zinc-900/50 p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
            <div>
                <label for="search" class="block font-medium text-zinc-400 mb-1">Search Reference / Org</label>
                <input
                    type="search"
                    id="search"
                    name="search"
                    value="{{ $filters['search'] ?? '' }}"
                    placeholder="Reference or organisation..."
                    class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-zinc-100 placeholder-zinc-500 focus:border-emerald-500 focus:outline-none"
                />
            </div>
            <div>
                <label for="status" class="block font-medium text-zinc-400 mb-1">Payment Status</label>
                <select
                    id="status"
                    name="status"
                    class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-zinc-100 focus:border-emerald-500 focus:outline-none"
                >
                    <option value="">All Statuses</option>
                    <option value="succeeded" {{ ($filters['status'] ?? '') === 'succeeded' ? 'selected' : '' }}>Succeeded (Confirmed)</option>
                    <option value="failed" {{ ($filters['status'] ?? '') === 'failed' ? 'selected' : '' }}>Failed</option>
                    <option value="pending" {{ ($filters['status'] ?? '') === 'pending' ? 'selected' : '' }}>Pending</option>
                </select>
            </div>
            <div>
                <label for="plan_id" class="block font-medium text-zinc-400 mb-1">Plan</label>
                <select
                    id="plan_id"
                    name="plan_id"
                    class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-zinc-100 focus:border-emerald-500 focus:outline-none"
                >
                    <option value="">All Plans</option>
                    @foreach ($plans as $p)
                        <option value="{{ $p->id }}" {{ (string) ($filters['plan_id'] ?? '') === (string) $p->id ? 'selected' : '' }}>
                            {{ $p->name }} ({{ $p->interval }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 rounded-lg bg-emerald-600 px-3 py-1.5 font-medium text-white hover:bg-emerald-500 transition">
                    Filter
                </button>
                @if (!empty($filters['search']) || !empty($filters['status']) || !empty($filters['plan_id']))
                    <a href="{{ route('admin.finance.payments') }}" class="rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-1.5 font-medium text-zinc-300 hover:bg-zinc-700 hover:text-white transition">
                        Reset
                    </a>
                @endif
            </div>
        </div>
    </form>

    <!-- PAYMENTS TABLE -->
    <div class="rounded-xl border border-zinc-800 bg-zinc-900/40 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-zinc-800 bg-zinc-950/60 text-[11px] font-bold uppercase tracking-wider text-zinc-400">
                        <th class="py-3 px-4">Date &middot; Time (WAT)</th>
                        <th class="py-3 px-4">Organisation</th>
                        <th class="py-3 px-4">Plan</th>
                        <th class="py-3 px-4">Type</th>
                        <th class="py-3 px-4 text-right">Amount</th>
                        <th class="py-3 px-4 text-center">Currency</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4">Reference</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800/60 text-zinc-300">
                    @forelse ($payments as $payment)
                        <tr class="hover:bg-zinc-800/30 transition">
                            <td class="py-3 px-4 whitespace-nowrap font-mono text-zinc-400">
                                {{ \App\Support\LocalTime::format($payment->verified_at ?? $payment->created_at, 'M j, Y · H:i') }}
                            </td>
                            <td class="py-3 px-4">
                                @if ($payment->organization)
                                    <a href="{{ route('admin.organizations.edit', $payment->organization) }}" class="font-semibold text-white hover:text-emerald-400 hover:underline">
                                        {{ $payment->organization->name }}
                                    </a>
                                @else
                                    <span class="text-zinc-500 italic">Detached Org</span>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                @if ($payment->plan)
                                    <span class="inline-flex items-center gap-1.5 rounded bg-zinc-800 px-2 py-0.5 text-[11px] text-zinc-200">
                                        {{ $payment->plan->name }}
                                    </span>
                                @else
                                    <span class="text-zinc-500">—</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-zinc-400">
                                {{ ($payment->attempts ?? 1) > 1 ? 'Renewal / Retry' : 'Subscription Checkout' }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-white whitespace-nowrap">
                                &#8358;{{ number_format($payment->amount / 100, 2) }}
                            </td>
                            <td class="py-3 px-4 text-center font-mono text-[11px] text-zinc-400">
                                {{ $payment->currency ?: 'NGN' }}
                            </td>
                            <td class="py-3 px-4 text-center">
                                @if ($payment->status === 'succeeded')
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2 py-0.5 text-[10px] font-semibold text-emerald-400 border border-emerald-800/60">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span> Confirmed
                                    </span>
                                @elseif ($payment->status === 'failed')
                                    <span class="inline-flex items-center gap-1 rounded-full bg-rose-500/10 px-2 py-0.5 text-[10px] font-semibold text-rose-400 border border-rose-800/60" title="{{ $payment->failure_reason }}">
                                        Failed
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-500/10 px-2 py-0.5 text-[10px] font-semibold text-amber-300 border border-amber-800/60">
                                        Pending
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-4 font-mono text-[11px] text-zinc-400 select-all">
                                {{ $payment->reference }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 text-center text-zinc-500">
                                No payments match the selected criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($payments->hasPages())
            <div class="p-4 border-t border-zinc-800">
                {{ $payments->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
