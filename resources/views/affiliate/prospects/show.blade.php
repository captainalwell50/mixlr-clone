@extends('layouts.app')

@section('title', $prospect->church_name)

@section('content')
    <div class="console-head">
        <div>
            <p class="site-section-label">Affiliate</p>
            <h1 class="console-title mt-2">{{ $prospect->church_name }}</h1>
            <p class="console-lead">{{ $prospect->city }}, {{ $prospect->state_region }} · {{ $prospect->country_code }}</p>
        </div>
        <a href="{{ route('affiliate.prospects.index') }}" class="console-btn console-btn-ghost">My prospects</a>
    </div>

    <dl class="mt-8 grid gap-4 sm:grid-cols-2">
        <div class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-4">
            <dt class="text-xs uppercase tracking-wide text-[var(--stage-muted)]">Method</dt>
            <dd class="mt-1 text-[var(--stage-cream)]">{{ $prospect->outreach_method->label() }}</dd>
        </div>
        <div class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-4">
            <dt class="text-xs uppercase tracking-wide text-[var(--stage-muted)]">Status</dt>
            <dd class="mt-1 text-[var(--stage-cream)]">{{ $prospect->status->label() }}</dd>
        </div>
        <div class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-4">
            <dt class="text-xs uppercase tracking-wide text-[var(--stage-muted)]">Planned</dt>
            <dd class="mt-1 text-[var(--stage-cream)]">{{ \App\Support\LocalTime::format($prospect->planned_for, 'M j, Y') ?: 'Not set' }}</dd>
        </div>
        <div class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-4">
            <dt class="text-xs uppercase tracking-wide text-[var(--stage-muted)]">Reservation</dt>
            <dd class="mt-1 text-[var(--stage-cream)]">
                {{ \App\Support\LocalTime::format($prospect->reserved_until, 'M j, Y') }}
                · {{ $prospect->daysRemaining() }} days left
            </dd>
        </div>
    </dl>

    <section class="mt-8 max-w-xl space-y-2 text-sm text-[var(--stage-muted)]">
        @if ($prospect->address)<p>Address: <span class="text-[var(--stage-cream)]">{{ $prospect->address }}</span></p>@endif
        @if ($prospect->contact_name)<p>Contact: <span class="text-[var(--stage-cream)]">{{ $prospect->contact_name }}{{ $prospect->contact_role ? ' · '.$prospect->contact_role : '' }}</span></p>@endif
        @if ($prospect->email)<p>Email: <span class="text-[var(--stage-cream)]">{{ $prospect->email }}</span></p>@endif
        @if ($prospect->phone)<p>Phone: <span class="text-[var(--stage-cream)]">{{ $prospect->phone }}</span></p>@endif
        @if ($prospect->website)<p>Website: <span class="text-[var(--stage-cream)]">{{ $prospect->website }}</span></p>@endif
        @if ($prospect->notes)<p class="whitespace-pre-wrap">Notes: <span class="text-[var(--stage-cream)]">{{ $prospect->notes }}</span></p>@endif
        @if ($prospect->convertedOrganization)
            <p>Channel: <span class="text-[var(--stage-cream)]">{{ $prospect->convertedOrganization->name }}</span></p>
        @endif
    </section>

    @if ($nextStatuses !== [])
        <div class="mt-8 flex flex-wrap gap-3">
            @foreach ($nextStatuses as $status)
                <form method="POST" action="{{ route('affiliate.prospects.status', $prospect) }}">
                    @csrf
                    <input type="hidden" name="status" value="{{ $status->value }}">
                    <button type="submit" class="console-btn console-btn-ghost">Mark {{ $status->label() }}</button>
                </form>
            @endforeach
        </div>
    @endif
@endsection
