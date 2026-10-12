@extends('layouts.app')

@section('title', 'Plan a church')

@section('content')
    <div class="console-head">
        <div>
            <p class="site-section-label">Affiliate</p>
            <h1 class="console-title mt-2">Plan a church</h1>
            <p class="console-lead">Search before you save. A plan does not create a Sound Mix channel.</p>
        </div>
        <a href="{{ route('affiliate.prospects.index') }}" class="console-btn console-btn-ghost">My prospects</a>
    </div>

    <form method="POST" action="{{ route('affiliate.prospects.store') }}" class="mt-8 max-w-xl space-y-4" id="plan-form">
        @csrf
        <div>
            <label for="church_name" class="block text-sm font-medium text-zinc-300">Church name</label>
            <input id="church_name" type="text" name="church_name" value="{{ old('church_name') }}" required maxlength="255"
                class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white">
        </div>
        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label for="country_code" class="block text-sm font-medium text-zinc-300">Country</label>
                <input id="country_code" type="text" name="country_code" value="{{ old('country_code') }}" required maxlength="2"
                    class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white" placeholder="NG">
            </div>
            <div>
                <label for="state_region" class="block text-sm font-medium text-zinc-300">State or region</label>
                <input id="state_region" type="text" name="state_region" value="{{ old('state_region') }}" required maxlength="120"
                    class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white">
            </div>
            <div>
                <label for="city" class="block text-sm font-medium text-zinc-300">City</label>
                <input id="city" type="text" name="city" value="{{ old('city') }}" required maxlength="120"
                    class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white">
            </div>
        </div>

        <div id="search-result" class="whitespace-pre-line rounded-lg border border-[var(--stage-border)] px-4 py-3 text-sm text-[var(--stage-muted)]">
            Enter the church and its location to search.
        </div>

        <fieldset>
            <legend class="text-sm font-medium text-zinc-300">Outreach</legend>
            <div class="mt-2 flex flex-wrap gap-3">
                @forelse ($methods as $method)
                    <label class="flex items-center gap-2 text-sm text-zinc-300">
                        <input type="radio" name="outreach_method" value="{{ $method->value }}" @checked(old('outreach_method', $methods[0]->value) === $method->value) required>
                        {{ $method->label() }}
                    </label>
                @empty
                    <p class="text-sm text-[var(--stage-muted)]">Outreach methods are turned off.</p>
                @endforelse
            </div>
        </fieldset>

        <div>
            <label for="planned_for" class="block text-sm font-medium text-zinc-300">Date planned <span class="text-[var(--stage-muted)]">(optional)</span></label>
            <input id="planned_for" type="date" name="planned_for" value="{{ old('planned_for') }}"
                class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white">
        </div>
        <div>
            <label for="address" class="block text-sm font-medium text-zinc-300">Address</label>
            <input id="address" type="text" name="address" value="{{ old('address') }}" maxlength="255"
                class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white">
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="phone" class="block text-sm font-medium text-zinc-300">Phone</label>
                <input id="phone" type="text" name="phone" value="{{ old('phone') }}" maxlength="40"
                    class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white">
            </div>
            <div>
                <label for="website" class="block text-sm font-medium text-zinc-300">Website</label>
                <input id="website" type="text" name="website" value="{{ old('website') }}" maxlength="255"
                    class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white">
            </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="contact_name" class="block text-sm font-medium text-zinc-300">Contact name</label>
                <input id="contact_name" type="text" name="contact_name" value="{{ old('contact_name') }}" maxlength="255"
                    class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white">
            </div>
            <div>
                <label for="contact_role" class="block text-sm font-medium text-zinc-300">Contact role</label>
                <input id="contact_role" type="text" name="contact_role" value="{{ old('contact_role') }}" maxlength="120"
                    class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white">
            </div>
        </div>
        <div>
            <label for="email" class="block text-sm font-medium text-zinc-300">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" maxlength="255"
                class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white">
        </div>
        <div>
            <label for="notes" class="block text-sm font-medium text-zinc-300">Notes</label>
            <textarea id="notes" name="notes" rows="3" maxlength="2000"
                class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white">{{ old('notes') }}</textarea>
        </div>
        <label id="different-wrap" hidden class="flex items-start gap-2 text-sm text-zinc-300">
            <input type="checkbox" name="different_church" value="1" @checked(old('different_church'))>
            This is a different church from the possible matches.
        </label>
        <button type="submit" class="console-btn console-btn-primary">Save plan</button>
    </form>

    <script>
        const result = document.getElementById('search-result');
        const differentWrap = document.getElementById('different-wrap');
        const fields = ['church_name', 'country_code', 'state_region', 'city', 'phone', 'website'];
        let timer = null;

        async function searchChurches() {
            const params = new URLSearchParams();
            fields.forEach((id) => params.set(id, document.getElementById(id).value || ''));
            if ((params.get('church_name') || '').trim().length < 2) {
                result.textContent = 'Enter the church and its location to search.';
                differentWrap.classList.add('hidden');
                return;
            }
            const response = await fetch(@json(route('affiliate.prospects.search')) + '?' + params.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!response.ok) {
                result.textContent = 'Search could not be completed.';
                return;
            }
            const data = await response.json();
            result.replaceChildren();
            const summary = document.createElement('p');
            summary.textContent = data.message;
            result.appendChild(summary);
            (data.matches || []).forEach((match) => {
                const line = document.createElement('p');
                if (match.type === 'organization') {
                    line.textContent = (match.name || 'Channel') + (match.city ? ' · ' + match.city : '') + (match.state ? ', ' + match.state : '');
                    result.appendChild(line);
                } else if (match.type === 'reserved') {
                    line.textContent = 'Planned ' + (match.planned_for || '—') + ' · reserved until ' + (match.reserved_until || '');
                    result.appendChild(line);
                } else if (match.type === 'own') {
                    line.textContent = match.outreach_label + ' · ' + match.status_label + ' · reserved until ' + (match.reserved_until || '');
                    result.appendChild(line);
                    const link = document.createElement('a');
                    link.href = @json(url('/affiliate/prospects')) + '/' + encodeURIComponent(match.id);
                    link.textContent = 'Open your existing plan';
                    link.className = 'console-link';
                    result.appendChild(link);
                } else if (match.church_name) {
                    line.textContent = 'Possible: ' + match.church_name;
                    result.appendChild(line);
                }
            });
            differentWrap.hidden = !data.requires_disambiguation;
        }

        fields.forEach((id) => {
            document.getElementById(id).addEventListener('input', () => {
                clearTimeout(timer);
                timer = setTimeout(searchChurches, 250);
            });
        });
    </script>
@endsection
