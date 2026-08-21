@extends('layouts.app')

@section('title', 'Customise channel')

@section('content')
    <div class="console-head">
        <div>
            <p class="site-section-label">Channel</p>
            <h1 class="console-title mt-2">Customise channel</h1>
            <p class="console-lead">
                Branding for
                <span class="text-[var(--stage-cream)]">{{ $organization->name }}</span>
                — logo, artwork, and the full-screen listen background.
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ $organization->channelUrl() }}" target="_blank" class="console-btn console-btn-ghost">View channel</a>
            <a href="{{ route('admin.organizations.edit', $organization) }}" class="console-btn console-btn-ghost">Edit details</a>
        </div>
    </div>

    <div class="mt-10 space-y-8 max-w-2xl">
        {{-- Channel logo --}}
        <section class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-6 space-y-4">
            <div>
                <h2 class="text-sm font-semibold uppercase tracking-wide text-[var(--stage-muted)]">Channel logo</h2>
                <p class="mt-2 text-sm text-[var(--stage-muted)]">
                    Shown on mobile Live Now and the public channel page.
                </p>
            </div>

            @if ($logoUrl)
                <div class="flex items-center gap-4">
                    <img src="{{ $logoUrl }}" alt="" class="h-16 w-16 rounded-xl object-cover ring-1 ring-white/15">
                    <form method="POST" action="{{ route('admin.organizations.customise.logo.destroy', $organization) }}"
                        onsubmit="return confirm('Remove channel logo?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="console-btn console-btn-ghost text-sm">Remove</button>
                    </form>
                </div>
            @else
                <p class="text-sm text-[var(--stage-muted)]">No logo set.</p>
            @endif

            <form method="POST" action="{{ route('admin.organizations.customise.logo', $organization) }}" enctype="multipart/form-data" class="space-y-3">
                @csrf
                <div>
                    <label for="logo" class="block text-sm font-medium text-zinc-300">{{ $logoUrl ? 'Replace logo' : 'Upload logo' }}</label>
                    <input id="logo" type="file" name="logo" accept="image/*" required
                        class="mt-1 block w-full text-sm text-[var(--stage-muted)] file:mr-3 file:rounded-lg file:border-0 file:bg-zinc-800 file:px-3 file:py-2 file:text-sm file:text-[var(--stage-cream)]">
                </div>
                <button type="submit" class="console-btn console-btn-primary">Save logo</button>
            </form>
        </section>

        {{-- Artwork --}}
        <section class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-6 space-y-4">
            <div>
                <h2 class="text-sm font-semibold uppercase tracking-wide text-[var(--stage-muted)]">Artwork</h2>
                <p class="mt-2 text-sm text-[var(--stage-muted)]">
                    Channel and listen-stage imagery when a dedicated listen background is not set.
                </p>
            </div>

            @if ($artworkUrl)
                <div class="space-y-3">
                    <div class="h-36 w-full max-w-md rounded-lg bg-cover bg-center ring-1 ring-white/10"
                        style="background-image: url('{{ $artworkUrl }}')"
                        role="img" aria-label="Current artwork"></div>
                    <form method="POST" action="{{ route('admin.organizations.customise.artwork.destroy', $organization) }}"
                        onsubmit="return confirm('Remove channel artwork?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="console-btn console-btn-ghost text-sm">Remove</button>
                    </form>
                </div>
            @else
                <p class="text-sm text-[var(--stage-muted)]">No artwork set.</p>
            @endif

            <form method="POST" action="{{ route('admin.organizations.customise.artwork', $organization) }}" enctype="multipart/form-data" class="space-y-3">
                @csrf
                <div>
                    <label for="artwork" class="block text-sm font-medium text-zinc-300">{{ $artworkUrl ? 'Replace artwork' : 'Upload artwork' }}</label>
                    <input id="artwork" type="file" name="artwork" accept="image/*" required
                        class="mt-1 block w-full text-sm text-[var(--stage-muted)] file:mr-3 file:rounded-lg file:border-0 file:bg-zinc-800 file:px-3 file:py-2 file:text-sm file:text-[var(--stage-cream)]">
                </div>
                <button type="submit" class="console-btn console-btn-primary">Save artwork</button>
            </form>
        </section>

        {{-- Listen background --}}
        <section class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-6 space-y-4">
            <div>
                <h2 class="text-sm font-semibold uppercase tracking-wide text-[var(--stage-muted)]">Listen background</h2>
                <p class="mt-2 text-sm text-[var(--stage-muted)]">
                    Full-screen image behind the listener page (replaces the default).
                </p>
            </div>

            @unless ($stream)
                <p class="rounded-lg border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm text-amber-100">
                    Create a stream for this channel before setting a listen background.
                </p>
            @else
                @if ($listenBackgroundUrl)
                    <div class="space-y-3">
                        <div class="h-40 w-full max-w-md rounded-lg bg-cover bg-center ring-1 ring-white/10"
                            style="background-image: url('{{ $listenBackgroundUrl }}')"
                            role="img" aria-label="Current listen background"></div>
                        <form method="POST" action="{{ route('admin.organizations.customise.background.destroy', $organization) }}"
                            onsubmit="return confirm('Remove listen background?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="console-btn console-btn-ghost text-sm">Remove</button>
                        </form>
                    </div>
                @else
                    <p class="text-sm text-[var(--stage-muted)]">Using the default listen background.</p>
                @endif

                <form method="POST" action="{{ route('admin.organizations.customise.background', $organization) }}" enctype="multipart/form-data" class="space-y-3">
                    @csrf
                    <div>
                        <label for="background" class="block text-sm font-medium text-zinc-300">{{ $listenBackgroundUrl ? 'Replace background' : 'Set background' }}</label>
                        <input id="background" type="file" name="background" accept="image/*" required
                            class="mt-1 block w-full text-sm text-[var(--stage-muted)] file:mr-3 file:rounded-lg file:border-0 file:bg-zinc-800 file:px-3 file:py-2 file:text-sm file:text-[var(--stage-cream)]">
                    </div>
                    <button type="submit" class="console-btn console-btn-primary">Save background</button>
                </form>
            @endunless
        </section>
    </div>
@endsection
