@extends(auth()->user()?->organizations()->exists() ? 'layouts.creator' : 'layouts.app')

@section('title', 'Choose a plan')

@section('main_class', 'console billing-page w-full max-w-none px-4 py-8 sm:px-6 lg:px-8')

@section('content')
    <div class="billing-plans billing-page">
        <p class="site-section-label">Billing</p>
        <h1 class="console-title mt-2">Choose how you’ll broadcast</h1>
        <p class="console-lead">
            Start with 30 days Free to test Studio and your channel, then subscribe to keep broadcasting.
        </p>

        @if (session('error'))
            <p class="mt-4 rounded-lg border border-red-500/40 bg-red-500/10 px-4 py-3 text-sm text-red-200">{{ session('error') }}</p>
        @endif
        @if (! empty($pendingCheckout))
            <p class="mt-4 rounded-lg border border-[var(--stage-accent)]/40 bg-[var(--stage-accent)]/10 px-4 py-3 text-sm">
                We’re confirming a Paystack payment for {{ $pendingCheckout->plan?->name ?? 'your plan' }}.
                This page refreshes the status automatically if the bank or network dropped the return.
            </p>
        @endif

        @if ($subscription)
            <p class="mt-6 text-sm text-[var(--stage-muted)]">
                Current status:
                <span class="text-[var(--stage-text)]">{{ $subscription->status->label() }}</span>
                @if ($subscription->plan)
                    · {{ $subscription->plan->name }}
                @endif
                @if ($subscription->trial_ends_at && ($subscription->status->value === 'trialing' || $subscription->plan?->isFree()))
                    · {{ $subscription->trial_ends_at->isFuture() ? 'Free access ends' : 'Free access ended' }}
                    {{ \App\Support\LocalTime::format($subscription->trial_ends_at, 'M j, Y') }}
                @endif
            </p>
        @endif

        @php
            $freeExpired = $subscription?->isOnFreePeriod() && $subscription->trial_ends_at?->isPast();
        @endphp

        <div class="billing-plans-grid">
            @foreach ($plans as $plan)
                @php
                    $recommended = $plan->isRecommended();
                @endphp
                <article
                    class="billing-plan-card {{ $recommended ? 'is-recommended' : '' }}"
                    @if ($recommended) aria-current="true" @endif
                >
                    <div class="billing-plan-head">
                        <h2>{{ $plan->name }}</h2>
                        @if ($recommended)
                            <span class="billing-plan-badge">Recommended</span>
                        @endif
                    </div>
                    @if ($plan->isCustom())
                        <p class="billing-plan-price">Custom</p>
                        <p class="billing-plan-note">Pricing and features are tailored to your channel.</p>
                        <p class="billing-plan-copy">
                            For large churches, networks, and anything beyond the listed packages.
                        </p>
                        <a href="{{ $plan->contactSalesMailto($organization->name ?? null) }}" class="console-btn console-btn-ghost billing-plan-cta">
                            Contact sales
                        </a>
                    @else
                        <p class="billing-plan-price tabular-nums">
                            {{ $plan->amountLabel() }}
                            @unless ($plan->isFree())
                                <span class="billing-plan-interval"> / {{ $plan->interval }}</span>
                            @endunless
                        </p>
                        @if ($plan->isFree())
                            <p class="billing-plan-note">{{ (int) config('billing.free_days', 30) }} days, then subscribe</p>
                        @endif
                        <ul class="billing-plan-features">
                            @forelse ($plan->featureBullets() as $bullet)
                                <li>{{ $bullet }}</li>
                            @empty
                                <li>Studio go-live + Podcasts</li>
                            @endforelse
                        </ul>
                        <form method="POST" action="{{ route('billing.checkout', $plan) }}" class="billing-plan-cta">
                            @csrf
                            <button
                                type="submit"
                                class="console-btn {{ $recommended || ($plan->isFree() && ! $freeExpired) ? 'console-btn-primary' : 'console-btn-ghost' }} w-full"
                                @if ($plan->isFree() && $freeExpired) disabled @endif
                                @if ($recommended) autofocus @endif
                            >
                                @if ($plan->isFree())
                                    {{ $freeExpired ? 'Free period ended' : 'Start 30-day Free' }}
                                @else
                                    Activate
                                @endif
                            </button>
                        </form>
                    @endif
                </article>
            @endforeach
        </div>

        <p class="mt-8 text-sm text-[var(--stage-muted)]">
            Already set up?
            <a href="{{ route('creator.home') }}" class="console-link">Go to creator home</a>
        </p>
    </div>
@endsection
