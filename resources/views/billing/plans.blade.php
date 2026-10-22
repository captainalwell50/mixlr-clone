@extends(((auth()->user()?->relationLoaded('organizations') ? auth()->user()->organizations->isNotEmpty() : auth()->user()?->organizations()->exists()) ? 'layouts.creator' : 'layouts.app'))

@section('title', 'Choose a plan')

@section('main_class', 'console billing-page w-full max-w-none px-4 py-8 sm:px-6 lg:px-8')

@section('content')
    <div class="billing-plans billing-page max-w-6xl mx-auto space-y-8">
        <!-- HEADER -->
        <div class="border-b border-zinc-800 pb-5">
            <div class="flex items-center gap-2">
                <span class="inline-flex h-2 w-2 rounded-full bg-emerald-400"></span>
                <span class="text-xs font-semibold uppercase tracking-wider text-emerald-400">Subscription & Plans</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-white mt-1">Choose how you’ll broadcast</h1>
            <p class="text-xs text-zinc-400 mt-1 max-w-2xl leading-relaxed">
                Start with a 30-day Free trial to test Web Studio, live recordings, and your broadcast channel, then activate your preferred broadcasting package.
            </p>
        </div>

        @if (session('error'))
            <div class="rounded-xl border border-red-800/60 bg-red-950/40 p-4 text-xs text-red-200">
                {{ session('error') }}
            </div>
        @endif

        @if (! empty($pendingCheckout))
            <div class="rounded-xl border border-emerald-800/60 bg-emerald-950/30 p-4 text-xs text-emerald-200 flex items-center gap-3">
                <span class="h-2 w-2 rounded-full bg-emerald-400 animate-pulse shrink-0"></span>
                <span>
                    We’re confirming a Paystack payment for {{ $pendingCheckout->plan?->name ?? 'your plan' }}.
                    This page refreshes the status automatically if the payment gateway dropped the return.
                </span>
            </div>
        @endif

        <!-- CURRENT SUBSCRIPTION PROMINENCE -->
        @if ($subscription)
            <div class="rounded-xl border border-zinc-800 bg-zinc-900/60 p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 shadow-sm">
                <div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-400 block">Current Subscription</span>
                    <div class="flex items-baseline gap-2 mt-0.5">
                        <h2 class="text-lg font-bold text-white">{{ $subscription->plan?->name ?? 'Active Subscription' }}</h2>
                        <span class="text-xs text-zinc-400">· {{ $subscription->status->label() }}</span>
                    </div>
                    <p class="text-xs text-zinc-400 mt-1">
                        @if ($subscription->trial_ends_at && ($subscription->status->value === 'trialing' || $subscription->plan?->isFree()))
                            <span>{{ $subscription->trial_ends_at->isFuture() ? 'Free access ends' : 'Free access ended' }}
                            {{ \App\Support\LocalTime::format($subscription->trial_ends_at, 'M j, Y') }}</span>
                        @endif
                        @if ($subscription->renews_at && $subscription->isActive())
                            <span>· Renews {{ \App\Support\LocalTime::format($subscription->renews_at, 'M j, Y') }}</span>
                        @endif
                    </p>
                </div>
                <div class="shrink-0 flex items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/10 px-3 py-1 text-xs font-semibold text-emerald-300 border border-emerald-700/50">
                        <span class="h-2 w-2 rounded-full bg-emerald-400"></span>
                        <span>{{ $subscription->status->label() }}</span>
                    </span>
                </div>
            </div>
        @endif

        @php
            $freeExpired = $subscription?->isOnFreePeriod() && $subscription->trial_ends_at?->isPast();
        @endphp

        <!-- PRICING TIERS GRID -->
        <div class="billing-plans-grid">
            @foreach ($plans as $plan)
                @php
                    $recommended = $plan->isRecommended();
                    $isCurrent = $subscription && $subscription->plan_id === $plan->id && $subscription->isActive();
                    $bullets = $plan->featureBullets();

                    // Group bullets cleanly
                    $broadcasting = [];
                    $content = [];
                    $audience = [];
                    $support = [];

                    foreach ($bullets as $b) {
                        $low = strtolower($b);
                        if (str_contains($low, 'streaming') || str_contains($low, 'stream') || str_contains($low, 'rtmp') || str_contains($low, 'radio') || str_contains($low, 'fallback') || str_contains($low, 'scheduling')) {
                            $broadcasting[] = $b;
                        } elseif (str_contains($low, 'podcast') || str_contains($low, 'storage') || str_contains($low, 'donation') || str_contains($low, 'upload')) {
                            $content[] = $b;
                        } elseif (str_contains($low, 'channel') || str_contains($low, 'profile') || str_contains($low, 'notification') || str_contains($low, 'analytic') || str_contains($low, 'embed') || str_contains($low, 'gallery')) {
                            $audience[] = $b;
                        } else {
                            $support[] = $b;
                        }
                    }
                @endphp
                <article
                    class="billing-plan-card {{ $recommended ? 'is-recommended' : '' }} flex flex-col justify-between"
                    @if ($recommended) aria-current="true" @endif
                >
                    <div>
                        <div class="billing-plan-head">
                            <h2 class="text-base font-bold text-white">{{ $plan->name }}</h2>
                            @if ($isCurrent)
                                <span class="rounded-full bg-emerald-950 px-2.5 py-0.5 text-[10px] font-bold text-emerald-300 border border-emerald-700/60 uppercase">Current</span>
                            @elseif ($recommended)
                                <span class="billing-plan-badge">Recommended</span>
                            @endif
                        </div>

                        @if ($plan->isCustom())
                            <p class="billing-plan-price">Custom</p>
                            <p class="billing-plan-note">Pricing and features are tailored to your channel.</p>
                            <p class="billing-plan-copy text-xs text-zinc-400 mt-2 leading-relaxed">
                                For large broadcast networks, organisations, media teams, and requirements beyond the standard packages.
                            </p>
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

                            <!-- FEATURE BULLETS GROUPED -->
                            <ul class="billing-plan-features space-y-3.5 mt-4">
                                @if (!empty($broadcasting))
                                    <li class="space-y-1">
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-400/90 block">Broadcasting</span>
                                        <ul class="space-y-1 text-xs text-zinc-300 pl-0">
                                            @foreach ($broadcasting as $b)
                                                <li class="flex items-start gap-1.5">
                                                    <svg class="h-3.5 w-3.5 text-emerald-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                                    </svg>
                                                    <span>{{ $b }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </li>
                                @endif

                                @if (!empty($content))
                                    <li class="space-y-1">
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-400/90 block">Content & Library</span>
                                        <ul class="space-y-1 text-xs text-zinc-300 pl-0">
                                            @foreach ($content as $b)
                                                <li class="flex items-start gap-1.5">
                                                    <svg class="h-3.5 w-3.5 text-emerald-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                                    </svg>
                                                    <span>{{ $b }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </li>
                                @endif

                                @if (!empty($audience))
                                    <li class="space-y-1">
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-400/90 block">Audience & Reach</span>
                                        <ul class="space-y-1 text-xs text-zinc-300 pl-0">
                                            @foreach ($audience as $b)
                                                <li class="flex items-start gap-1.5">
                                                    <svg class="h-3.5 w-3.5 text-emerald-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                                    </svg>
                                                    <span>{{ $b }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </li>
                                @endif

                                @if (!empty($support))
                                    <li class="space-y-1">
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-400/90 block">Support & Scale</span>
                                        <ul class="space-y-1 text-xs text-zinc-300 pl-0">
                                            @foreach ($support as $b)
                                                <li class="flex items-start gap-1.5">
                                                    <svg class="h-3.5 w-3.5 text-emerald-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                                    </svg>
                                                    <span>{{ $b }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </li>
                                @endif

                                @if (empty($bullets))
                                    <li class="flex items-start gap-1.5 text-xs text-zinc-300">
                                        <svg class="h-3.5 w-3.5 text-emerald-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                        </svg>
                                        <span>Studio go-live + Podcasts</span>
                                    </li>
                                @endif
                            </ul>
                        @endif
                    </div>

                    <!-- CTA BUTTON -->
                    <div class="billing-plan-cta pt-6">
                        @if ($plan->isCustom())
                            <a href="{{ $plan->contactSalesMailto($organization->name ?? null) }}" class="console-btn console-btn-ghost billing-plan-cta w-full text-center">
                                Contact sales
                            </a>
                        @else
                            <form method="POST" action="{{ route('billing.checkout', $plan) }}">
                                @csrf
                                <button
                                    type="submit"
                                    class="console-btn {{ $recommended || ($plan->isFree() && ! $freeExpired) ? 'console-btn-primary' : 'console-btn-ghost' }} w-full"
                                    @if (($plan->isFree() && $freeExpired) || $isCurrent) disabled @endif
                                >
                                    @if ($isCurrent)
                                        Current plan
                                    @elseif ($plan->isFree())
                                        {{ $freeExpired ? 'Free period ended' : 'Start 30-day Free' }}
                                    @else
                                        Activate
                                    @endif
                                </button>
                            </form>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>

        <p class="pt-4 text-xs text-zinc-400 text-center">
            Need to return to your dashboard?
            <a href="{{ route('creator.home') }}" class="text-emerald-400 hover:text-emerald-300 font-semibold underline">Go to creator home</a>
        </p>
    </div>
@endsection
