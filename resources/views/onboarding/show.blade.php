@extends('layouts.app')

@section('title', 'Set up your channel')

@section('content')
    <div class="mx-auto max-w-3xl">
        <p class="site-section-label">Onboarding</p>
        <h1 class="console-title mt-2">What are you broadcasting?</h1>
        <p class="console-lead">Pick a creator type so we can set up your channel and Studio stream.</p>

        <form method="POST" action="{{ route('onboarding.type') }}" class="mt-10">
            @csrf
            <div class="grid gap-4 sm:grid-cols-3">
                @foreach ($types as $type)
                    <label class="onboard-type-card {{ $selectedType === $type->value ? 'is-selected' : '' }}">
                        <input
                            type="radio"
                            name="creator_type"
                            value="{{ $type->value }}"
                            class="sr-only"
                            @checked(old('creator_type', $selectedType) === $type->value)
                            required
                        >
                        <span class="onboard-type-title">{{ $type->label() }}</span>
                        <span class="onboard-type-blurb">{{ $type->blurb() }}</span>
                    </label>
                @endforeach
            </div>
            @error('creator_type')
                <p class="mt-3 text-sm text-red-400">{{ $message }}</p>
            @enderror
            <button type="submit" class="console-btn console-btn-primary mt-8">Continue</button>
        </form>
    </div>

    <style>
        .onboard-type-card {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            padding: 1.25rem;
            border: 1px solid color-mix(in srgb, var(--stage-border) 80%, transparent);
            border-radius: 0.75rem;
            background: color-mix(in srgb, var(--stage-panel) 88%, transparent);
            cursor: pointer;
            transition: border-color 0.15s ease, background 0.15s ease, transform 0.15s ease;
        }
        .onboard-type-card:hover,
        .onboard-type-card:has(input:checked),
        .onboard-type-card.is-selected {
            border-color: var(--stage-accent, #3d9b7a);
            background: color-mix(in srgb, var(--stage-accent, #3d9b7a) 12%, var(--stage-panel));
            transform: translateY(-1px);
        }
        .onboard-type-title {
            font-weight: 600;
            color: var(--stage-text);
        }
        .onboard-type-blurb {
            font-size: 0.875rem;
            color: var(--stage-muted);
            line-height: 1.4;
        }
    </style>
@endsection
