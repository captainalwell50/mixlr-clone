@php
    $compact = $compact ?? false;
    // Light header → full lockup (dark wordmark). Dark footer/nav → mark + CSS wordmark.
    $onDark = $onDark ?? false;
    $label = config('app.name', 'Sound Mix Live');
@endphp
@if ($onDark)
    <a href="{{ url('/') }}" class="mkt-logo mkt-logo-ondark{{ $compact ? ' mkt-logo-compact' : '' }}" aria-label="{{ $label }} home">
        <img
            class="mkt-logo-mark-img"
            src="{{ asset('brand/soundmix-mark.png') }}?v=20260723d"
            alt=""
            width="40"
            height="40"
            decoding="async"
            aria-hidden="true"
        >
        <span class="mkt-logo-word">
            <span class="mkt-logo-primary">Sound Mix</span>
            <span class="mkt-logo-dot" aria-hidden="true"></span>
            <span class="mkt-logo-secondary">Live</span>
        </span>
    </a>
@else
    <a href="{{ url('/') }}" class="mkt-logo{{ $compact ? ' mkt-logo-compact' : '' }}" aria-label="{{ $label }} home">
        <img
            class="mkt-logo-img"
            src="{{ asset('brand/soundmix-logo.png') }}?v=20260723d"
            alt="{{ $label }}"
            width="220"
            height="52"
            decoding="async"
        >
    </a>
@endif
