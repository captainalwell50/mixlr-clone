@extends('layouts.admin')

@section('title', 'Affiliate levels')

@section('content')
    @include('admin.affiliate-programme.partials.nav')

    <div class="console-head">
        <div>
            <p class="site-section-label">Super admin</p>
            <h1 class="console-title mt-2">Levels</h1>
            <p class="console-lead">Edit the names and the minimum number of active paying churches. Promotion and demotion are not assigned yet. The {{ $graceDays }}-day demotion grace period is saved under Settings and would apply only after assignment exists. Changing a threshold does not rewrite a commission.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.affiliate-programme.levels.update') }}" class="mt-8 max-w-3xl space-y-4">
        @csrf
        @method('PUT')

        @foreach ($levels as $index => $level)
            <div class="grid gap-3 rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-4 sm:grid-cols-4">
                <div>
                    <label class="block text-xs uppercase tracking-wide text-[var(--stage-muted)]" for="level-name-{{ $index }}">Name</label>
                    <input id="level-name-{{ $index }}" name="levels[{{ $index }}][name]" value="{{ old('levels.'.$index.'.name', $level['name']) }}" maxlength="40"
                        class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white">
                </div>
                <div>
                    <label class="block text-xs uppercase tracking-wide text-[var(--stage-muted)]" for="level-min-{{ $index }}">Minimum churches</label>
                    <input id="level-min-{{ $index }}" type="number" min="0" name="levels[{{ $index }}][min_active_paying_churches]" value="{{ old('levels.'.$index.'.min_active_paying_churches', $level['min_active_paying_churches']) }}"
                        class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white">
                </div>
                <div>
                    <label class="block text-xs uppercase tracking-wide text-[var(--stage-muted)]" for="level-order-{{ $index }}">Display order</label>
                    <input id="level-order-{{ $index }}" type="number" min="0" name="levels[{{ $index }}][display_order]" value="{{ old('levels.'.$index.'.display_order', $level['display_order']) }}"
                        class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white">
                </div>
                <div class="flex items-end">
                    <input type="hidden" name="levels[{{ $index }}][enabled]" value="0">
                    <label class="flex items-center gap-2 text-sm text-zinc-300">
                        <input type="checkbox" name="levels[{{ $index }}][enabled]" value="1" @checked(old('levels.'.$index.'.enabled', $level['enabled'] ? '1' : '0') == '1' || old('levels.'.$index.'.enabled', $level['enabled']) === true)>
                        Enabled
                    </label>
                </div>
            </div>
        @endforeach

        <div class="grid gap-3 rounded-xl border border-dashed border-[var(--stage-border)] p-4 sm:grid-cols-4">
            <div>
                <label class="block text-xs uppercase tracking-wide text-[var(--stage-muted)]" for="level-name-new">Add a level</label>
                <input id="level-name-new" name="levels[{{ count($levels) }}][name]" value="{{ old('levels.'.count($levels).'.name') }}" maxlength="40" placeholder="Name"
                    class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white">
            </div>
            <div>
                <label class="block text-xs uppercase tracking-wide text-[var(--stage-muted)]" for="level-min-new">Minimum churches</label>
                <input id="level-min-new" type="number" min="0" name="levels[{{ count($levels) }}][min_active_paying_churches]" value="{{ old('levels.'.count($levels).'.min_active_paying_churches') }}"
                    class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white">
            </div>
            <div>
                <label class="block text-xs uppercase tracking-wide text-[var(--stage-muted)]" for="level-order-new">Display order</label>
                <input id="level-order-new" type="number" min="0" name="levels[{{ count($levels) }}][display_order]" value="{{ old('levels.'.count($levels).'.display_order', count($levels) + 1) }}"
                    class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white">
            </div>
            <div class="flex items-end">
                <input type="hidden" name="levels[{{ count($levels) }}][enabled]" value="0">
                <label class="flex items-center gap-2 text-sm text-zinc-300">
                    <input type="checkbox" name="levels[{{ count($levels) }}][enabled]" value="1" @checked(old('levels.'.count($levels).'.enabled') == '1')>
                    Enabled
                </label>
            </div>
        </div>

        <button type="submit" class="console-btn console-btn-primary">Save levels</button>
    </form>
@endsection
