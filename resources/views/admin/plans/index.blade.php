@extends('layouts.app')

@section('title', 'Pricing packages')

@section('content')
    <div class="console-head">
        <div>
            <p class="site-section-label">Billing</p>
            <h1 class="console-title mt-2">Pricing packages</h1>
            <p class="console-lead">Create plans and choose which product features each package unlocks.</p>
        </div>
        <a href="{{ route('admin.plans.create') }}" class="console-btn console-btn-primary">New package</a>
    </div>

    @if (session('error'))
        <p class="mb-4 rounded-lg border border-red-500/40 bg-red-500/10 px-4 py-3 text-sm text-red-200">{{ session('error') }}</p>
    @endif

    <div class="console-table">
        <table>
            <thead>
                <tr>
                    <th>Package</th>
                    <th>Price</th>
                    <th>Features</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($plans as $plan)
                    <tr>
                        <td>
                            <p class="text-[var(--stage-cream)] font-medium">{{ $plan->name }}</p>
                            <p class="text-xs text-[var(--stage-muted)]">{{ $plan->slug }}</p>
                        </td>
                        <td class="text-[var(--stage-muted)]">
                            {{ $plan->amountLabel() }}
                            @unless ($plan->isFree())
                                <span class="text-xs">/ {{ $plan->interval }}</span>
                            @endunless
                        </td>
                        <td class="text-xs text-[var(--stage-muted)]">
                            {{ count($plan->featureBullets()) }} enabled
                            · {{ $plan->maxStreams() }} streams
                            · {{ \App\Support\PlanFeatureCatalog::formatBytes($plan->storageBytes()) }}
                        </td>
                        <td class="text-[var(--stage-muted)]">
                            {{ $plan->is_active ? 'Active' : 'Hidden' }}
                        </td>
                        <td class="text-right whitespace-nowrap">
                            <a href="{{ route('admin.plans.edit', $plan) }}" class="console-link">Edit features</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-[var(--stage-muted)]">No packages yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
