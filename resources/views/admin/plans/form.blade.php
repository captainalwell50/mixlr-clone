@extends('layouts.app')

@section('title', $plan->exists ? 'Edit '.$plan->name : 'New pricing package')

@section('content')
    <div class="console-head">
        <div>
            <p class="site-section-label">Billing</p>
            <h1 class="console-title mt-2">{{ $plan->exists ? 'Edit package' : 'New package' }}</h1>
            <p class="console-lead">All product features are available — tick what this package includes.</p>
        </div>
        <a href="{{ route('admin.plans.index') }}" class="console-btn console-btn-ghost">All packages</a>
    </div>

    <form method="POST" action="{{ $plan->exists ? route('admin.plans.update', $plan) : route('admin.plans.store') }}" class="space-y-8">
        @csrf
        @if ($plan->exists)
            @method('PUT')
        @endif

        <section class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-6 space-y-4">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-[var(--stage-muted)]">Package details</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block text-sm">
                    <span class="text-[var(--stage-muted)]">Name</span>
                    <input name="name" value="{{ old('name', $plan->name) }}" required class="mt-1 w-full rounded-lg border border-[var(--stage-border)] bg-transparent px-3 py-2">
                </label>
                <label class="block text-sm">
                    <span class="text-[var(--stage-muted)]">Slug</span>
                    <input name="slug" value="{{ old('slug', $plan->slug) }}" class="mt-1 w-full rounded-lg border border-[var(--stage-border)] bg-transparent px-3 py-2" placeholder="auto-from-name">
                </label>
                <label class="block text-sm">
                    <span class="text-[var(--stage-muted)]">Amount (smallest currency unit, e.g. kobo)</span>
                    <input type="number" min="0" name="amount" value="{{ old('amount', $plan->amount ?? 0) }}" required class="mt-1 w-full rounded-lg border border-[var(--stage-border)] bg-transparent px-3 py-2">
                </label>
                <label class="block text-sm">
                    <span class="text-[var(--stage-muted)]">Currency</span>
                    <input name="currency" value="{{ old('currency', $plan->currency ?? 'NGN') }}" maxlength="3" required class="mt-1 w-full rounded-lg border border-[var(--stage-border)] bg-transparent px-3 py-2">
                </label>
                <label class="block text-sm">
                    <span class="text-[var(--stage-muted)]">Interval</span>
                    <select name="interval" class="mt-1 w-full rounded-lg border border-[var(--stage-border)] bg-transparent px-3 py-2">
                        @foreach (['monthly', 'yearly'] as $interval)
                            <option value="{{ $interval }}" @selected(old('interval', $plan->interval) === $interval)>{{ ucfirst($interval) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm">
                    <span class="text-[var(--stage-muted)]">Paystack plan code</span>
                    <input name="paystack_plan_code" value="{{ old('paystack_plan_code', $plan->paystack_plan_code) }}" class="mt-1 w-full rounded-lg border border-[var(--stage-border)] bg-transparent px-3 py-2" placeholder="Optional">
                </label>
                <label class="block text-sm">
                    <span class="text-[var(--stage-muted)]">Sort order</span>
                    <input type="number" min="0" name="sort_order" value="{{ old('sort_order', $plan->sort_order ?? 0) }}" class="mt-1 w-full rounded-lg border border-[var(--stage-border)] bg-transparent px-3 py-2">
                </label>
                <label class="flex items-center gap-2 text-sm mt-6">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $plan->is_active ?? true))>
                    <span>Active (shown on billing page)</span>
                </label>
            </div>
        </section>

        <section class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-6 space-y-4">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-[var(--stage-muted)]">Quotas</h2>
            <div class="grid gap-4 sm:grid-cols-3">
                <label class="block text-sm">
                    <span class="text-[var(--stage-muted)]">{{ $quotas['max_streams']['label'] }}</span>
                    <input type="number" min="0" name="max_streams" value="{{ old('max_streams', data_get($plan->limits, 'max_streams', 1)) }}" class="mt-1 w-full rounded-lg border border-[var(--stage-border)] bg-transparent px-3 py-2">
                </label>
                <label class="block text-sm">
                    <span class="text-[var(--stage-muted)]">{{ $quotas['max_recordings']['label'] }}</span>
                    <input type="number" min="0" name="max_recordings" value="{{ old('max_recordings', data_get($plan->limits, 'max_recordings', 20)) }}" class="mt-1 w-full rounded-lg border border-[var(--stage-border)] bg-transparent px-3 py-2">
                </label>
                <label class="block text-sm">
                    <span class="text-[var(--stage-muted)]">{{ $quotas['storage_bytes']['label'] }}</span>
                    <input type="number" min="0" name="storage_gb" value="{{ old('storage_gb', $storageGb) }}" class="mt-1 w-full rounded-lg border border-[var(--stage-border)] bg-transparent px-3 py-2">
                </label>
            </div>
        </section>

        @foreach ($featureGroups as $group => $features)
            <section class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-6 space-y-3">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-[var(--stage-muted)]">{{ $group }}</h2>
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ($features as $key => $meta)
                        <label class="flex items-start gap-2 rounded-lg border border-[var(--stage-border)]/70 px-3 py-2 text-sm">
                            <input
                                type="checkbox"
                                class="mt-1"
                                name="features[{{ $key }}]"
                                value="1"
                                @checked(old('features.'.$key, data_get($plan->limits, $key, $meta['default'] ?? false)))
                            >
                            <span>
                                <span class="text-[var(--stage-cream)]">{{ $meta['label'] }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </section>
        @endforeach

        <div class="flex flex-wrap gap-3">
            <button type="submit" class="console-btn console-btn-primary">
                {{ $plan->exists ? 'Save package' : 'Create package' }}
            </button>
            @if ($plan->exists && $plan->subscriptions()->doesntExist())
                <button
                    type="submit"
                    form="plan-delete"
                    class="console-btn console-btn-ghost"
                    onclick="return confirm('Delete this package permanently?')"
                >Delete</button>
            @endif
        </div>
    </form>

    @if ($plan->exists && $plan->subscriptions()->doesntExist())
        <form id="plan-delete" method="POST" action="{{ route('admin.plans.destroy', $plan) }}" class="hidden">
            @csrf
            @method('DELETE')
        </form>
    @endif
@endsection
