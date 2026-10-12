@extends('layouts.admin')

@section('title', $prospect->church_name)

@section('content')
    @include('admin.affiliate-programme.partials.nav')
    <div class="console-head">
        <div>
            <p class="site-section-label">Platform Admin</p>
            <h1 class="console-title mt-2">{{ $prospect->church_name }}</h1>
            <p class="console-lead">
                {{ $prospect->affiliateAccount?->user?->name }}
                · {{ $prospect->affiliateAccount?->user?->email }}
                · {{ $prospect->affiliateAccount?->referral_code }}
            </p>
        </div>
        <a href="{{ route('admin.prospects.index') }}" class="console-btn console-btn-ghost">All prospects</a>
    </div>

    <dl class="mt-8 grid gap-4 sm:grid-cols-2">
        <div class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-4">
            <dt class="text-xs uppercase tracking-wide text-[var(--stage-muted)]">Status</dt>
            <dd class="mt-1">{{ $prospect->status->label() }} · {{ $prospect->outreach_method->label() }}</dd>
        </div>
        <div class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-4">
            <dt class="text-xs uppercase tracking-wide text-[var(--stage-muted)]">Reservation</dt>
            <dd class="mt-1">{{ \App\Support\LocalTime::format($prospect->reserved_at, 'M j, Y') }} – {{ \App\Support\LocalTime::format($prospect->reserved_until, 'M j, Y') }}</dd>
        </div>
    </dl>

    <section class="mt-6 max-w-xl space-y-2 text-sm text-[var(--stage-muted)]">
        <p>{{ $prospect->city }}, {{ $prospect->state_region }} {{ $prospect->country_code }}</p>
        @if ($prospect->address)<p>{{ $prospect->address }}</p>@endif
        @if ($prospect->phone)<p>{{ $prospect->phone }}</p>@endif
        @if ($prospect->email)<p>{{ $prospect->email }}</p>@endif
        @if ($prospect->website)<p>{{ $prospect->website }}</p>@endif
        @if ($prospect->notes)<p class="whitespace-pre-wrap">{{ $prospect->notes }}</p>@endif
        @if ($prospect->convertedOrganization)
            <p>Converted channel: {{ $prospect->convertedOrganization->name }}</p>
        @endif
    </section>

    @if ($prospect->status->value !== 'converted')
        <form method="POST" action="{{ route('admin.prospects.release', $prospect) }}" class="mt-8 max-w-xl space-y-3">
            @csrf
            <label for="reason" class="block text-sm font-medium text-zinc-300">Release reason</label>
            <textarea id="reason" name="reason" required minlength="3" maxlength="500" rows="3"
                class="w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white">{{ old('reason') }}</textarea>
            <button type="submit" class="console-btn console-btn-ghost">Release reservation</button>
        </form>
    @endif

    @if ($duplicates->isNotEmpty())
        <h2 class="mt-8 text-sm font-semibold uppercase tracking-wide text-[var(--stage-muted)]">Same name in this city</h2>
        <ul class="mt-3 space-y-2 text-sm">
            @foreach ($duplicates as $duplicate)
                <li>
                    <a href="{{ route('admin.prospects.show', $duplicate) }}" class="console-link">{{ $duplicate->church_name }}</a>
                    · {{ $duplicate->status->label() }}
                    · {{ $duplicate->affiliateAccount?->user?->name }}
                </li>
            @endforeach
        </ul>
    @endif

    @if ($prospect->audits->isNotEmpty())
        <h2 class="mt-8 text-sm font-semibold uppercase tracking-wide text-[var(--stage-muted)]">History</h2>
        <ul class="mt-3 space-y-3 text-sm">
            @foreach ($prospect->audits as $audit)
                <li class="border-t border-[var(--stage-border)] pt-3">
                    <p class="text-[var(--stage-cream)]">{{ $audit->reason }}</p>
                    <p class="text-[var(--stage-muted)]">{{ $audit->actor?->name ?? 'System' }} · {{ \App\Support\LocalTime::format($audit->created_at, 'M j, Y g:i A') }}</p>
                </li>
            @endforeach
        </ul>
    @endif
@endsection