@extends('layouts.admin')

@section('title', 'Platform Invoices')

@section('content')
<div class="space-y-6 max-w-7xl mx-auto">
    <!-- PAGE HEADER -->
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-zinc-800 pb-5">
        <div>
            <div class="flex items-center gap-2">
                <span class="inline-flex h-2 w-2 rounded-full bg-emerald-400"></span>
                <span class="text-xs font-semibold uppercase tracking-wider text-emerald-400">Billing Records</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-white mt-1">Platform Invoices</h1>
            <p class="text-xs text-zinc-400 mt-0.5">Authoritative invoice ledger &middot; Billing periods, due dates, settlement status, and payment references.</p>
        </div>
        <div>
            <a href="{{ route('admin.finance.index') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-1.5 text-xs font-medium text-zinc-200 hover:bg-zinc-700 hover:text-white transition">
                &larr; Back to Finance Overview
            </a>
        </div>
    </div>

    @include('admin.finance.partials.nav')

    <!-- FILTER BAR -->
    <form method="GET" action="{{ route('admin.finance.invoices') }}" class="rounded-xl border border-zinc-800 bg-zinc-900/50 p-4">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
            <div>
                <label for="search" class="block font-medium text-zinc-400 mb-1">Search Invoice # / Org</label>
                <input
                    type="search"
                    id="search"
                    name="search"
                    value="{{ $filters['search'] ?? '' }}"
                    placeholder="Invoice number, reference, or organisation..."
                    class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-zinc-100 placeholder-zinc-500 focus:border-emerald-500 focus:outline-none"
                />
            </div>
            <div>
                <label for="status" class="block font-medium text-zinc-400 mb-1">Invoice Status</label>
                <select
                    id="status"
                    name="status"
                    class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-zinc-100 focus:border-emerald-500 focus:outline-none"
                >
                    <option value="">All Statuses</option>
                    <option value="paid" {{ ($filters['status'] ?? '') === 'paid' ? 'selected' : '' }}>Paid</option>
                    <option value="pending" {{ ($filters['status'] ?? '') === 'pending' ? 'selected' : '' }}>Pending / Open</option>
                    <option value="past_due" {{ ($filters['status'] ?? '') === 'past_due' ? 'selected' : '' }}>Past Due (Overdue)</option>
                    <option value="cancelled" {{ ($filters['status'] ?? '') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 rounded-lg bg-emerald-600 px-3 py-1.5 font-medium text-white hover:bg-emerald-500 transition">
                    Filter Invoices
                </button>
                @if (!empty($filters['search']) || !empty($filters['status']))
                    <a href="{{ route('admin.finance.invoices') }}" class="rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-1.5 font-medium text-zinc-300 hover:bg-zinc-700 hover:text-white transition">
                        Reset
                    </a>
                @endif
            </div>
        </div>
    </form>

    <!-- INVOICES TABLE -->
    <div class="rounded-xl border border-zinc-800 bg-zinc-900/40 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-zinc-800 bg-zinc-950/60 text-[11px] font-bold uppercase tracking-wider text-zinc-400">
                        <th class="py-3 px-4">Invoice #</th>
                        <th class="py-3 px-4">Organisation</th>
                        <th class="py-3 px-4 text-right">Amount</th>
                        <th class="py-3 px-4 text-center">Currency</th>
                        <th class="py-3 px-4">Billing Period</th>
                        <th class="py-3 px-4">Due Date</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4">Payment Ref</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800/60 text-zinc-300">
                    @forelse ($invoices as $invoice)
                        <tr class="hover:bg-zinc-800/30 transition">
                            <td class="py-3 px-4 font-mono font-semibold text-white">
                                {{ $invoice->invoice_number }}
                            </td>
                            <td class="py-3 px-4">
                                @if ($invoice->organization)
                                    <a href="{{ route('admin.organizations.edit', $invoice->organization) }}" class="font-semibold text-white hover:text-emerald-400 hover:underline">
                                        {{ $invoice->organization->name }}
                                    </a>
                                @else
                                    <span class="text-zinc-500 italic">No Organisation</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-white whitespace-nowrap">
                                &#8358;{{ number_format($invoice->amount / 100, 2) }}
                            </td>
                            <td class="py-3 px-4 text-center font-mono text-[11px] text-zinc-400">
                                {{ $invoice->currency ?: 'NGN' }}
                            </td>
                            <td class="py-3 px-4 font-mono text-[11px] text-zinc-400">
                                @if ($invoice->billing_period_start && $invoice->billing_period_end)
                                    {{ $invoice->billing_period_start->format('M j') }} &ndash; {{ $invoice->billing_period_end->format('M j, Y') }}
                                @else
                                    {{ $invoice->created_at->format('M j, Y') }}
                                @endif
                            </td>
                            <td class="py-3 px-4 font-mono text-[11px] {{ $invoice->isOverdue() ? 'text-rose-400 font-semibold' : 'text-zinc-400' }}">
                                {{ $invoice->due_date ? \App\Support\LocalTime::format($invoice->due_date, 'M j, Y') : '—' }}
                                @if ($invoice->isOverdue())
                                    <span class="block text-[10px] text-rose-500">Overdue</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-center">
                                @if ($invoice->status === 'paid')
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2 py-0.5 text-[10px] font-semibold text-emerald-400 border border-emerald-800/60">
                                        Paid
                                    </span>
                                @elseif ($invoice->status === 'past_due' || $invoice->isOverdue())
                                    <span class="inline-flex items-center gap-1 rounded-full bg-rose-500/10 px-2 py-0.5 text-[10px] font-semibold text-rose-400 border border-rose-800/60">
                                        Past Due
                                    </span>
                                @elseif ($invoice->status === 'cancelled')
                                    <span class="inline-flex items-center gap-1 rounded-full bg-zinc-800 px-2 py-0.5 text-[10px] font-semibold text-zinc-400">
                                        Cancelled
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-500/10 px-2 py-0.5 text-[10px] font-semibold text-amber-300 border border-amber-800/60">
                                        Pending
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-4 font-mono text-[11px] text-zinc-400 select-all">
                                {{ $invoice->payment_reference ?: '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 text-center text-zinc-500">
                                No invoice records match the selected criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($invoices->hasPages())
            <div class="p-4 border-t border-zinc-800">
                {{ $invoices->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
