@extends(auth()->user()?->isAdmin() ? 'layouts.admin' : 'layouts.app')

@section('title', 'Edit channel')

@section('content')
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="console-title">Edit channel</h1>
            <p class="mt-1 text-sm text-[var(--stage-muted)]">Name, URLs, and discoverability. Visual branding lives on Customise channel.</p>
        </div>
        <a href="{{ $organization->channelUrl() }}" target="_blank" class="text-sm text-emerald-400 hover:text-emerald-300">View channel →</a>
    </div>

    @if (session('error'))
        <p class="mt-4 rounded-lg border border-red-500/40 bg-red-500/10 px-4 py-3 text-sm text-red-200">{{ session('error') }}</p>
    @endif
    @if (session('status'))
        <p class="mt-4 rounded-lg border border-[var(--stage-accent)]/40 bg-[var(--stage-accent)]/10 px-4 py-3 text-sm">{{ session('status') }}</p>
    @endif

    @if (auth()->user()?->isAdmin() && $plans->isNotEmpty())
        <section class="mt-8 max-w-lg rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-6">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-[var(--stage-muted)]">Pricing package</h2>
            <p class="mt-1 mb-4 text-sm text-[var(--stage-muted)]">Assign a package so this creator can go live without automated checkout.</p>
            @include('admin.partials.assign-plan', ['organization' => $organization, 'plans' => $plans])
        </section>
    @endif

    <form method="POST" action="{{ route('admin.organizations.update', $organization) }}" class="mt-8 max-w-lg space-y-4">
        @csrf
        @method('PUT')
        <div>
            <label for="name" class="block text-sm font-medium text-zinc-300">Name</label>
            <input id="name" type="text" name="name" value="{{ old('name', $organization->name) }}" required
                class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white">
        </div>
        <div>
            <label for="slug" class="block text-sm font-medium text-zinc-300">Slug (URL)</label>
            <input id="slug" type="text" name="slug" value="{{ old('slug', $organization->slug) }}" required
                class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white">
            <p class="mt-1 text-xs text-zinc-500">{{ $organization->channelUrl() }}</p>
        </div>
        <div>
            <label for="tagline" class="block text-sm font-medium text-zinc-300">Tagline</label>
            <input id="tagline" type="text" name="tagline" value="{{ old('tagline', $organization->tagline) }}"
                class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white">
        </div>
        <div>
            <label for="theme_color" class="block text-sm font-medium text-zinc-300">Theme color</label>
            <input id="theme_color" type="text" name="theme_color" value="{{ old('theme_color', $organization->theme_color ?: '#3d9b7a') }}"
                class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white" placeholder="#3d9b7a">
        </div>
        <div>
            <label for="support_url" class="block text-sm font-medium text-zinc-300">Support / donate URL</label>
            <input id="support_url" type="url" name="support_url" value="{{ old('support_url', $organization->support_url) }}"
                class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white" placeholder="https://…">
            <p class="mt-1 text-xs text-zinc-500">Listener giving (giving page or bank details) is on Customise channel.</p>
        </div>
        <div>
            <label for="social_feed_url" class="block text-sm font-medium text-zinc-300">Social photo feed URL</label>
            <input id="social_feed_url" type="url" name="social_feed_url" value="{{ old('social_feed_url', $organization->social_feed_url) }}"
                class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white" placeholder="https://instagram.com/… or Facebook album">
            <p class="mt-1 text-xs text-zinc-500">Shown on the listen page so people can open photos from this service.</p>
        </div>
        <label class="flex items-center gap-2 text-sm text-zinc-300">
            <input type="hidden" name="is_public" value="0">
            <input type="checkbox" name="is_public" value="1" @checked(old('is_public', $organization->is_public ?? true)) class="rounded border-zinc-600">
            Public channel (discoverable)
        </label>
        <div class="flex flex-wrap gap-3">
            <button type="submit" class="console-btn console-btn-primary">Save</button>
            <a href="{{ route('admin.organizations.customise', $organization) }}" class="console-btn console-btn-ghost">Customise channel</a>
            <a href="{{ route('admin.organizations.members', $organization) }}" class="console-btn console-btn-ghost">Members</a>
            <a href="{{ route('admin.organizations.index') }}" class="console-btn console-btn-ghost">Back</a>
        </div>
    </form>

    @if (auth()->user()?->isAdmin())
        <section class="mt-8 max-w-lg rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-6">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-[var(--stage-muted)]">Affiliate attribution</h2>
            @if ($attribution)
                <p class="mt-2 text-sm text-[var(--stage-cream)]">
                    {{ $attribution->source->label() }}
                    @if ($attribution->affiliate)
                        — {{ $attribution->affiliate->referral_code }}
                        ({{ $attribution->affiliate->user?->name }})
                    @endif
                </p>
                <p class="mt-1 text-xs text-[var(--stage-muted)]">
                    {{ $attribution->isEarningEligible() ? 'Earning-eligible. No commission is recorded.' : 'Not earning-eligible.' }}
                </p>
            @else
                <p class="mt-2 text-sm text-[var(--stage-muted)]">No attribution recorded yet.</p>
            @endif

            @if ($affiliates->isNotEmpty())
                <form method="POST" action="{{ route('admin.organizations.attribution', $organization) }}" class="mt-4 space-y-4">
                    @csrf
                    @method('PUT')
                    <div>
                        <label for="affiliate_account_id" class="block text-sm font-medium text-zinc-300">Affiliate</label>
                        <select id="affiliate_account_id" name="affiliate_account_id" required
                            class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white">
                            <option value="">Choose an approved affiliate</option>
                            @foreach ($affiliates as $affiliate)
                                <option value="{{ $affiliate->id }}" @selected((string) old('affiliate_account_id') === (string) $affiliate->id)>
                                    {{ $affiliate->referral_code }} — {{ $affiliate->user?->name }} ({{ $affiliate->city }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="reason" class="block text-sm font-medium text-zinc-300">Reason</label>
                        <textarea id="reason" name="reason" required minlength="3" maxlength="500" rows="3"
                            class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white">{{ old('reason') }}</textarea>
                    </div>
                    <button type="submit" class="console-btn console-btn-primary">Save attribution</button>
                </form>
            @else
                <p class="mt-4 text-sm text-[var(--stage-muted)]">Approve an affiliate before assigning one.</p>
            @endif

            @if ($attributionChanges->isNotEmpty())
                <h3 class="mt-6 text-xs font-semibold uppercase tracking-wide text-[var(--stage-muted)]">History</h3>
                <ul class="mt-3 space-y-3 text-sm">
                    @foreach ($attributionChanges as $change)
                        <li class="border-t border-[var(--stage-border)] pt-3">
                            <p class="text-[var(--stage-cream)]">
                                {{ $change->previousAffiliate?->referral_code ?? 'None' }}
                                →
                                {{ $change->newAffiliate?->referral_code ?? 'None' }}
                                ({{ $change->new_source->label() }})
                            </p>
                            <p class="mt-1 text-[var(--stage-muted)]">{{ $change->reason }}</p>
                            <p class="mt-1 text-xs text-[var(--stage-muted)]">
                                {{ $change->changedBy?->name ?? 'System' }}
                                · {{ \App\Support\LocalTime::format($change->created_at, 'M j, Y g:i A') }}
                            </p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endif
@endsection
