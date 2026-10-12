@extends('layouts.admin')

@section('title', 'Create Payout Batch')

@section('content')
    @include('admin.affiliate-programme.partials.nav')

    <div class="console-head">
        <div>
            <p class="site-section-label">Financial Operations</p>
            <h1 class="console-title mt-2">Create Affiliate Payout Batch</h1>
            <p class="console-lead">Bundle payable commissions for settlement.</p>
        </div>
        <a href="{{ route('admin.affiliate-payouts.index') }}" class="console-btn console-btn-ghost">All payouts</a>
    </div>

    @if ($errors->any())
        <div class="mt-4 rounded-lg border border-red-500/40 bg-red-500/10 px-4 py-3 text-sm text-red-200">
            <ul class="list-disc pl-5 space-y-1">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mt-8 max-w-2xl rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-6">
        <form method="GET" action="{{ route('admin.affiliate-payouts.create') }}" class="mb-6">
            <label for="select_affiliate" class="block text-sm font-medium text-zinc-300">Select Qualified Affiliate</label>
            <div class="mt-2 flex gap-3">
                <select id="select_affiliate" name="affiliate_id" onchange="this.form.submit()" class="w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white text-sm">
                    <option value="">-- Choose affiliate with payable balance --</option>
                    @foreach ($eligibleAffiliates as $aff)
                        <option value="{{ $aff->id }}" @selected($selectedAffiliate?->id === $aff->id)>
                            {{ $aff->user?->name }} ({{ $aff->referral_code }})
                        </option>
                    @endforeach
                </select>
                <button type="submit" class="console-btn console-btn-ghost text-xs">Load</button>
            </div>
        </form>

        @if ($selectedAffiliate && $balances)
            <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4 rounded-lg border border-zinc-800 bg-zinc-900/50 p-4">
                <div>
                    <span class="block text-xs uppercase text-zinc-400">Payable</span>
                    <span class="text-base font-bold text-emerald-400">{{ $balances['currency'] }} {{ number_format($balances['payable_minor'] / 100, 2) }}</span>
                </div>
                <div>
                    <span class="block text-xs uppercase text-zinc-400">Held</span>
                    <span class="text-base font-medium text-amber-300">{{ $balances['currency'] }} {{ number_format($balances['held_minor'] / 100, 2) }}</span>
                </div>
                <div>
                    <span class="block text-xs uppercase text-zinc-400">Lifetime Paid</span>
                    <span class="text-base font-medium text-white">{{ $balances['currency'] }} {{ number_format($balances['paid_minor'] / 100, 2) }}</span>
                </div>
                <div>
                    <span class="block text-xs uppercase text-zinc-400">Qualifying Orgs</span>
                    <span class="text-base font-medium text-cyan-300">{{ $balances['qualifying_orgs_count'] }} (Qualified)</span>
                </div>
            </div>

            @if ($payableCommissions->isEmpty())
                <p class="text-sm text-amber-300">This affiliate has no payable commissions ready for payout.</p>
            @else
                <form method="POST" action="{{ route('admin.affiliate-payouts.store') }}" class="space-y-6">
                    @csrf
                    <input type="hidden" name="affiliate_account_id" value="{{ $selectedAffiliate->id }}">
                    <input type="hidden" name="currency" value="{{ $balances['currency'] }}">

                    <div>
                        <h3 class="text-sm font-semibold uppercase text-zinc-300 mb-2">Commissions to Include</h3>
                        <div class="space-y-2 max-h-64 overflow-y-auto border border-zinc-800 rounded-lg p-3 bg-zinc-900">
                            @foreach ($payableCommissions as $comm)
                                <label class="flex items-center justify-between text-sm py-1.5 border-b border-zinc-800/60 last:border-0 cursor-pointer">
                                    <div class="flex items-center gap-2">
                                        <input type="checkbox" name="commission_ids[]" value="{{ $comm->id }}" checked class="rounded border-zinc-700 bg-zinc-900 text-cyan-500">
                                        <span class="text-white">{{ $comm->organization?->name ?? 'Church' }}</span>
                                        <span class="text-xs font-mono text-zinc-400">({{ Str::limit($comm->payment_reference, 12) }})</span>
                                    </div>
                                    <span class="font-semibold text-emerald-400">{{ $comm->formattedCommissionAmount() }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <label for="notes" class="block text-sm font-medium text-zinc-300">Internal Reference / Notes</label>
                        <textarea id="notes" name="notes" rows="3" placeholder="Bank payment reference, memo, or receipt identifier..." class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white text-sm"></textarea>
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="submit" class="console-btn console-btn-primary">Create Payout Draft</button>
                        <a href="{{ route('admin.affiliate-payouts.index') }}" class="console-btn console-btn-ghost">Cancel</a>
                    </div>
                </form>
            @endif
        @else
            <p class="text-sm text-zinc-400">Please choose an affiliate from the dropdown to prepare a payout batch.</p>
        @endif
    </div>
@endsection
