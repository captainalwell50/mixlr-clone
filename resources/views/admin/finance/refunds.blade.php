@extends('layouts.admin')

@section('title', 'Platform Refunds & Reversals')

@section('content')
<div class="space-y-6 max-w-7xl mx-auto">
    <!-- PAGE HEADER -->
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-zinc-800 pb-5">
        <div>
            <div class="flex items-center gap-2">
                <span class="inline-flex h-2 w-2 rounded-full bg-emerald-400"></span>
                <span class="text-xs font-semibold uppercase tracking-wider text-emerald-400">Financial Deductions</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-white mt-1">Platform Refunds &amp; Reversals</h1>
            <p class="text-xs text-zinc-400 mt-0.5">Authoritative record of refund actions, charge reversals, and marketplace deductions.</p>
        </div>
        <div>
            <a href="{{ route('admin.finance.index') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-1.5 text-xs font-medium text-zinc-200 hover:bg-zinc-700 hover:text-white transition">
                &larr; Back to Finance Overview
            </a>
        </div>
    </div>

    @include('admin.finance.partials.nav')

    <!-- REFUNDS TABLE -->
    <div class="rounded-xl border border-zinc-800 bg-zinc-900/40 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-zinc-800 bg-zinc-950/60 text-[11px] font-bold uppercase tracking-wider text-zinc-400">
                        <th class="py-3 px-4">Date &middot; Time (WAT)</th>
                        <th class="py-3 px-4">Customer / Organisation</th>
                        <th class="py-3 px-4">Original Payment</th>
                        <th class="py-3 px-4 text-right">Refund Amount</th>
                        <th class="py-3 px-4 text-center">Currency</th>
                        <th class="py-3 px-4">Source / Context</th>
                        <th class="py-3 px-4 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800/60 text-zinc-300">
                    @forelse ($refunds as $refund)
                        <tr class="hover:bg-zinc-800/30 transition">
                            <td class="py-3 px-4 whitespace-nowrap font-mono text-zinc-400">
                                {{ \App\Support\LocalTime::format($refund->refunded_at ?? $refund->updated_at, 'M j, Y · H:i') }}
                            </td>
                            <td class="py-3 px-4">
                                @if ($refund->user)
                                    <span class="font-semibold text-white">{{ $refund->user->name }}</span>
                                    <span class="block text-[11px] text-zinc-500 font-mono">{{ $refund->user->email }}</span>
                                @elseif ($refund->seller?->organization)
                                    <span class="font-semibold text-white">{{ $refund->seller->organization->name }}</span>
                                @else
                                    <span class="text-zinc-500 italic">User detached</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 font-mono text-[11px] text-zinc-400 select-all">
                                {{ $refund->provider_reference }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-rose-400 whitespace-nowrap">
                                -&#8358;{{ number_format(($refund->refunded_amount_minor ?: $refund->amount_collected_minor) / 100, 2) }}
                            </td>
                            <td class="py-3 px-4 text-center font-mono text-[11px] text-zinc-400">
                                {{ $refund->currency ?: 'NGN' }}
                            </td>
                            <td class="py-3 px-4 text-zinc-300">
                                {{ $refund->series?->title ? 'Digital Library: ' . $refund->series->title : 'Digital Library Purchase' }}
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="inline-flex items-center gap-1 rounded-full bg-rose-500/10 px-2 py-0.5 text-[10px] font-semibold text-rose-400 border border-rose-800/60">
                                    {{ ucfirst(str_replace('_', ' ', $refund->status)) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-zinc-500">
                                No refund records exist. All transactions remain fully confirmed and settled.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($refunds->hasPages())
            <div class="p-4 border-t border-zinc-800">
                {{ $refunds->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
