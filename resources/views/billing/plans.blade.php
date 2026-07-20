@extends('layouts.app')

@section('title', 'Choose a plan')

@section('content')
    <div class="mx-auto max-w-4xl">
        <p class="site-section-label">Billing</p>
        <h1 class="console-title mt-2">Choose how you’ll broadcast</h1>
        <p class="console-lead">
            Start on Free to test Studio and your channel, or subscribe when you’re ready for a paid plan.
        </p>

        @if (session('error'))
            <p class="mt-4 rounded-lg border border-red-500/40 bg-red-500/10 px-4 py-3 text-sm text-red-200">{{ session('error') }}</p>
        @endif
        @if (session('status'))
            <p class="mt-4 rounded-lg border border-[var(--stage-accent)]/40 bg-[var(--stage-accent)]/10 px-4 py-3 text-sm">{{ session('status') }}</p>
        @endif

        @if ($subscription)
            <p class="mt-6 text-sm text-[var(--stage-muted)]">
                Current status:
                <span class="text-[var(--stage-text)]">{{ $subscription->status->label() }}</span>
                @if ($subscription->plan)
                    · {{ $subscription->plan->name }}
                @endif
                @if ($subscription->trial_ends_at && $subscription->status->value === 'trialing')
                    · trial ends {{ $subscription->trial_ends_at->toFormattedDateString() }}
                @endif
            </p>
        @endif

        <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($plans as $plan)
                <div class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-6 {{ $plan->isFree() ? 'ring-1 ring-[var(--stage-accent)]/40' : '' }}">
                    <h2 class="text-lg font-semibold text-[var(--stage-text)]">{{ $plan->name }}</h2>
                    <p class="mt-2 text-2xl font-semibold tabular-nums">
                        {{ $plan->amountLabel() }}
                        @unless ($plan->isFree())
                            <span class="text-sm font-normal text-[var(--stage-muted)]"> / {{ $plan->interval }}</span>
                        @endunless
                    </p>
                    <ul class="mt-4 space-y-1 text-sm text-[var(--stage-muted)]">
                        @if ($plan->isFree())
                            <li>Test Studio go-live &amp; listen</li>
                            <li>1 channel stream</li>
                            <li>Upgrade anytime</li>
                        @else
                            <li>Up to {{ $plan->maxStreams() }} stream{{ $plan->maxStreams() === 1 ? '' : 's' }}</li>
                            @if (data_get($plan->limits, 'gallery'))
                                <li>Event gallery &amp; video reels</li>
                            @endif
                            <li>Studio go-live + Recorded Audio</li>
                        @endif
                    </ul>
                    <form method="POST" action="{{ route('billing.checkout', $plan) }}" class="mt-6">
                        @csrf
                        <button type="submit" class="console-btn {{ $plan->isFree() ? 'console-btn-primary' : 'console-btn-ghost' }} w-full">
                            @if ($plan->isFree())
                                Start free
                            @elseif ($paystackEnabled)
                                Subscribe with Paystack
                            @else
                                Activate plan
                            @endif
                        </button>
                    </form>
                </div>
            @endforeach
        </div>

        <p class="mt-8 text-sm text-[var(--stage-muted)]">
            Already set up?
            <a href="{{ route('creator.home') }}" class="console-link">Go to creator home</a>
        </p>
    </div>
@endsection
