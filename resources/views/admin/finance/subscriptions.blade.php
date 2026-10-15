@extends('layouts.admin')

@section('title', 'Creator Subscriptions & Recurring Health')

@section('content')
<div class="space-y-6 max-w-7xl mx-auto">
    <!-- PAGE HEADER -->
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-zinc-800 pb-5">
        <div>
            <div class="flex items-center gap-2">
                <span class="inline-flex h-2 w-2 rounded-full bg-emerald-400"></span>
                <span class="text-xs font-semibold uppercase tracking-wider text-emerald-400">Subscription Intelligence</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-white mt-1">Creator Subscriptions</h1>
            <p class="text-xs text-zinc-400 mt-0.5">Recurring revenue health, renewal schedules, and lifecycle states across all organisations.</p>
        </div>
        <div>
            <a href="{{ route('admin.finance.index') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-1.5 text-xs font-medium text-zinc-200 hover:bg-zinc-700 hover:text-white transition">
                &larr; Back to Finance Overview
            </a>
        </div>
    </div>

    @include('admin.finance.partials.nav')

    <!-- FILTER BAR -->
    <form method="GET" action="{{ route('admin.finance.subscriptions') }}" class="rounded-xl border border-zinc-800 bg-zinc-900/50 p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
            <div>
                <label for="search" class="block font-medium text-zinc-400 mb-1">Search Organisation</label>
                <input
                    type="search"
                    id="search"
                    name="search"
                    value="{{ $filters['search'] ?? '' }}"
                    placeholder="Organisation name or slug..."
                    class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-zinc-100 placeholder-zinc-500 focus:border-emerald-500 focus:outline-none"
                />
            </div>
            <div>
                <label for="status" class="block font-medium text-zinc-400 mb-1">Status</label>
                <select
                    id="status"
                    name="status"
                    class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-zinc-100 focus:border-emerald-500 focus:outline-none"
                >
                    <option value="">All Statuses</option>
                    <option value="active" {{ ($filters['status'] ?? '') === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="trialing" {{ ($filters['status'] ?? '') === 'trialing' ? 'selected' : '' }}>Trialing / Free</option>
                    <option value="past_due" {{ ($filters['status'] ?? '') === 'past_due' ? 'selected' : '' }}>Past Due (At Risk)</option>
                    <option value="cancelled" {{ ($filters['status'] ?? '') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                </select>
            </div>
            <div>
                <label for="health" class="block font-medium text-zinc-400 mb-1">Payment Health</label>
                <select
                    id="health"
                    name="health"
                    class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-zinc-100 focus:border-emerald-500 focus:outline-none"
                >
                    <option value="">All Health States</option>
                    <option value="healthy" {{ ($filters['health'] ?? '') === 'healthy' ? 'selected' : '' }}>Healthy / Current</option>
                    <option value="past_due" {{ ($filters['health'] ?? '') === 'past_due' ? 'selected' : '' }}>Past Due (Critical)</option>
                    <option value="trialing" {{ ($filters['health'] ?? '') === 'trialing' ? 'selected' : '' }}>Trial / Free</option>
                    <option value="cancelled" {{ ($filters['health'] ?? '') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
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
            <div class="sm:col-span-2 lg:col-span-4 flex justify-end gap-2 pt-2 border-t border-zinc-800/60">
                <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-1.5 font-medium text-white hover:bg-emerald-500 transition">
                    Apply Filters
                </button>
                @if (!empty($filters['search']) || !empty($filters['status']) || !empty($filters['health']) || !empty($filters['plan_id']))
                    <a href="{{ route('admin.finance.subscriptions') }}" class="rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-1.5 font-medium text-zinc-300 hover:bg-zinc-700 hover:text-white transition">
                        Reset
                    </a>
                @endif
            </div>
        </div>
    </form>

    <!-- SUBSCRIPTIONS TABLE -->
    <div class="rounded-xl border border-zinc-800 bg-zinc-900/40 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-zinc-800 bg-zinc-950/60 text-[11px] font-bold uppercase tracking-wider text-zinc-400">
                        <th class="py-3 px-4">Organisation</th>
                        <th class="py-3 px-4">Plan &middot; Interval</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-center">Health</th>
                        <th class="py-3 px-4 text-right">Monthly Value (MRR)</th>
                        <th class="py-3 px-4">Renewal / Expiry</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800/60 text-zinc-300">
                    @forelse ($subscriptions as $subscription)
                        @php
                            $plan = $subscription->plan;
                            $org = $subscription->organization;
                            $monthlyValue = $plan && !$plan->isFree()
                                ? app(\App\Services\PlatformFinanceService::class)->normalizeToMonthlyMinor($plan->amount, $plan->interval)
                                : 0;
                            $renewalDate = $subscription->renews_at ?: $subscription->renewalAt();
                        @endphp
                        <tr class="hover:bg-zinc-800/30 transition">
                            <td class="py-3 px-4">
                                @if ($org)
                                    <div>
                                        <a href="{{ route('admin.organizations.edit', $org) }}" class="font-semibold text-white hover:text-emerald-400 hover:underline">
                                            {{ $org->name }}
                                        </a>
                                        <span class="block text-[11px] text-zinc-500 font-mono">{{ $org->slug }}</span>
                                    </div>
                                @else
                                    <span class="text-zinc-500 italic">No Organisation</span>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                @if ($plan)
                                    <div>
                                        <span class="font-medium text-zinc-200">{{ $plan->name }}</span>
                                        <span class="block text-[11px] text-zinc-400 capitalize">{{ $plan->interval ?: 'Monthly' }} &middot; {{ $plan->isFree() ? 'Free' : '₦' . number_format($plan->amount / 100, 2) }}</span>
                                    </div>
                                @else
                                    <span class="text-zinc-500">Unassigned</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-center">
                                @php
                                    $st = $subscription->status->value ?? 'unknown';
                                @endphp
                                @if ($st === 'active')
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2 py-0.5 text-[10px] font-semibold text-emerald-400 border border-emerald-800/60">
                                        Active
                                    </span>
                                @elseif ($st === 'past_due')
                                    <span class="inline-flex items-center gap-1 rounded-full bg-rose-500/10 px-2 py-0.5 text-[10px] font-semibold text-rose-400 border border-rose-800/60">
                                        Past Due
                                    </span>
                                @elseif ($st === 'trialing')
                                    <span class="inline-flex items-center gap-1 rounded-full bg-blue-500/10 px-2 py-0.5 text-[10px] font-semibold text-blue-300 border border-blue-800/60">
                                        Trialing
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full bg-zinc-800 px-2 py-0.5 text-[10px] font-semibold text-zinc-400">
                                        Cancelled
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-center">
                                @if ($st === 'past_due')
                                    <span class="inline-flex items-center gap-1 text-[11px] font-medium text-rose-400">
                                        <span class="h-1.5 w-1.5 rounded-full bg-rose-400 animate-pulse"></span> At Risk
                                    </span>
                                @elseif ($st === 'active')
                                    <span class="inline-flex items-center gap-1 text-[11px] font-medium text-emerald-400">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span> Healthy
                                    </span>
                                @elseif ($st === 'trialing')
                                    <span class="text-[11px] text-blue-300 font-medium">Free Period</span>
                                @else
                                    <span class="text-[11px] text-zinc-500">Inactive</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-white whitespace-nowrap">
                                @if ($monthlyValue > 0)
                                    &#8358;{{ number_format($monthlyValue / 100, 2) }}
                                @else
                                    <span class="text-zinc-500 font-normal">&#8358;0.00</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap text-zinc-400 font-mono text-[11px]">
                                @if ($renewalDate)
                                    <span>{{ \App\Support\LocalTime::format($renewalDate, 'M j, Y') }}</span>
                                    <span class="block text-[10px] text-zinc-500">{{ $renewalDate->diffForHumans() }}</span>
                                @else
                                    <span class="text-zinc-500">—</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right space-x-1.5 whitespace-nowrap">
                                @if ($org)
                                    <a href="{{ route('admin.organizations.edit', $org) }}" class="rounded bg-zinc-800 px-2 py-1 text-[11px] font-medium text-zinc-200 hover:bg-zinc-700 hover:text-white transition" title="Inspect organisation">
                                        Org
                                    </a>
                                    <a href="{{ route('admin.finance.payments', ['organization_id' => $org->id]) }}" class="rounded bg-zinc-800 px-2 py-1 text-[11px] font-medium text-zinc-200 hover:bg-zinc-700 hover:text-white transition" title="View org payments">
                                        Payments
                                    </a>
                                    <a href="{{ route('admin.finance.invoices', ['organization_id' => $org->id]) }}" class="rounded bg-zinc-800 px-2 py-1 text-[11px] font-medium text-zinc-200 hover:bg-zinc-700 hover:text-white transition" title="View org invoices">
                                        Invoices
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-zinc-500">
                                No creator subscriptions match the selected criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($subscriptions->hasPages())
            <div class="p-4 border-t border-zinc-800">
                {{ $subscriptions->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
