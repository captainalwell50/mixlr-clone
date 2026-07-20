@extends('layouts.app')

@section('title', $organization->name)

@section('content')
    <div class="mx-auto max-w-3xl">
        <p class="site-section-label">Creator home</p>
        <h1 class="console-title mt-2">{{ $organization->name }}</h1>
        <p class="console-lead">
            Your channel is live at
            <a href="{{ route('channels.show', $organization) }}" class="console-link">/c/{{ $organization->slug }}</a>
        </p>

        @if (session('status'))
            <p class="mt-4 rounded-lg border border-[var(--stage-accent)]/40 bg-[var(--stage-accent)]/10 px-4 py-3 text-sm">{{ session('status') }}</p>
        @endif
        @if (session('error'))
            <p class="mt-4 rounded-lg border border-red-500/40 bg-red-500/10 px-4 py-3 text-sm text-red-200">{{ session('error') }}</p>
        @endif

        <div class="mt-6 flex flex-wrap items-center gap-3">
            @if ($subscription)
                <span class="rounded-full border border-[var(--stage-border)] px-3 py-1 text-xs uppercase tracking-wide text-[var(--stage-muted)]">
                    {{ $subscription->status->label() }}
                    @if ($subscription->plan)
                        · {{ $subscription->plan->name }}
                    @endif
                </span>
            @endif
            @if ($isOwner)
                <a href="{{ route('billing.plans') }}" class="console-link text-sm">Manage billing</a>
            @endif
        </div>

        @unless ($canBroadcast)
            <p class="mt-4 rounded-lg border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm text-amber-100">
                Your trial or subscription isn’t active. You can still manage the channel —
                <a href="{{ route('billing.plans') }}" class="console-link">upgrade to go on air</a>.
            </p>
        @endunless

        <div class="console-actions mt-8">
            @if ($studioUrl)
                @if ($canBroadcast)
                    <a href="{{ $studioUrl }}" class="console-btn console-btn-primary">Open Studio</a>
                @else
                    <a href="{{ route('billing.plans') }}" class="console-btn console-btn-primary">Upgrade to go on air</a>
                @endif
            @endif
            <a href="{{ route('admin.events.create') }}" class="console-btn console-btn-ghost">Schedule event</a>
            <a href="{{ route('channels.show', $organization) }}" class="console-btn console-btn-ghost">Public channel</a>
            <a href="{{ route('archive.index') }}" class="console-btn console-btn-ghost">Recorded Audio</a>
            <a href="{{ route('discover') }}" class="console-btn console-btn-ghost">Discover</a>
            @if ($stream)
                <a href="{{ route('admin.streams.edit', $stream) }}" class="console-btn console-btn-ghost">Stream settings</a>
            @endif
        </div>
    </div>
@endsection
