@extends('layouts.admin')

@section('title', 'Affiliate programme settings')

@section('content')
    @include('admin.affiliate-programme.partials.nav')

    <div class="console-head">
        <div>
            <p class="site-section-label">Super admin</p>
            <h1 class="console-title mt-2">Settings</h1>
            <p class="console-lead">Changes are audited. Commission, penalty, hold, and level values saved here do not rewrite history, and they do not create a commission.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.affiliate-programme.settings.update') }}" class="mt-8 max-w-3xl space-y-8">
        @csrf
        @method('PUT')

        @foreach ($sections as $section)
            <section class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-6">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-[var(--stage-muted)]">{{ $section['title'] }}</h2>
                <p class="mt-2 text-sm text-[var(--stage-muted)]">{{ $section['intro'] }}</p>
                <div class="mt-5 space-y-5">
                    @foreach ($section['fields'] as $field)
                        <div>
                            @if ($field['type'] === 'locked')
                                <p class="text-sm font-medium text-zinc-300">{{ $field['label'] }}</p>
                                <p class="mt-1 text-sm text-[var(--stage-cream)]">{{ $field['value'] }}</p>
                            @elseif ($field['type'] === 'bool')
                                <input type="hidden" name="{{ $field['key'] }}" value="0">
                                <label class="flex items-start gap-2 text-sm text-zinc-300">
                                    <input type="checkbox" name="{{ $field['key'] }}" value="1" class="mt-1"
                                        @checked(old($field['key'], $settings[$field['key']] ? '1' : '0') === '1' || old($field['key'], $settings[$field['key']]) === true || old($field['key'], $settings[$field['key']]) === 1)>
                                    <span>{{ $field['label'] }}</span>
                                </label>
                            @elseif ($field['type'] === 'select')
                                <label for="{{ $field['key'] }}" class="block text-sm font-medium text-zinc-300">{{ $field['label'] }}</label>
                                <select id="{{ $field['key'] }}" name="{{ $field['key'] }}" class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white">
                                    @foreach ($field['options'] as $option => $optionLabel)
                                        <option value="{{ $option }}" @selected(old($field['key'], $settings[$field['key']]) === $option)>{{ $optionLabel }}</option>
                                    @endforeach
                                </select>
                            @elseif ($field['type'] === 'percent')
                                <label for="{{ $field['key'] }}" class="block text-sm font-medium text-zinc-300">{{ $field['label'] }}</label>
                                <input id="{{ $field['key'] }}" type="number" name="{{ $field['key'] }}" min="0" max="100" step="0.01"
                                    value="{{ old($field['key'], number_format(((int) $settings[$field['key']]) / 100, 2, '.', '')) }}"
                                    class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white">
                            @elseif ($field['type'] === 'money')
                                <label for="{{ $field['key'] }}" class="block text-sm font-medium text-zinc-300">{{ $field['label'] }}</label>
                                <input id="{{ $field['key'] }}" type="number" name="{{ $field['key'] }}" min="0" step="0.01"
                                    value="{{ old($field['key'], number_format(((int) $settings[$field['key']]) / 100, 2, '.', '')) }}"
                                    class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white">
                            @else
                                <label for="{{ $field['key'] }}" class="block text-sm font-medium text-zinc-300">{{ $field['label'] }}</label>
                                <input id="{{ $field['key'] }}" type="{{ $field['type'] === 'number' ? 'number' : 'text' }}" name="{{ $field['key'] }}"
                                    value="{{ old($field['key'], $settings[$field['key']]) }}"
                                    class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white"
                                    @if ($field['key'] === 'registration_currency') maxlength="3" @endif>
                            @endif
                            <p class="mt-1 text-sm text-[var(--stage-muted)]">{{ $field['help'] }}</p>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach

        <button type="submit" class="console-btn console-btn-primary">Save settings</button>
    </form>
@endsection
