@extends('layouts.app')

@section('title', 'Platform settings')

@section('content')
    <div class="console-head">
        <div>
            <p class="site-section-label">Operator</p>
            <h1 class="console-title mt-2">Platform settings</h1>
            <p class="console-lead">Global controls for public listen playback. Studio / creator preview stays on WHEP.</p>
        </div>
    </div>

    @if (session('status'))
        <p class="mb-4 rounded-lg border border-emerald-500/40 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">{{ session('status') }}</p>
    @endif

    <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-8">
        @csrf
        @method('PUT')

        <section class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-6 space-y-5">
            <div>
                <h2 class="text-sm font-semibold uppercase tracking-wide text-[var(--stage-muted)]">Public listen playback</h2>
                <p class="mt-2 text-sm text-[var(--stage-muted)]">
                    Applies to every event and stream automatically (new and existing). No per-event toggle needed.
                </p>
            </div>

            {{-- Effective mode status: what listeners actually get right now --}}
            <div class="rounded-xl border {{ $listenStatus['prefers_hls'] ? 'border-emerald-500/50 bg-emerald-500/10' : 'border-sky-500/50 bg-sky-500/10' }} px-5 py-4 space-y-3"
                data-effective-mode="{{ $listenStatus['effective_mode'] }}"
                data-configured-choice="{{ $listenStatus['form_value'] }}">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="text-xs font-semibold uppercase tracking-wide text-[var(--stage-muted)]">Effective mode now</span>
                    <span class="inline-flex items-center rounded-md px-3 py-1 text-sm font-semibold tracking-wide
                        {{ $listenStatus['prefers_hls']
                            ? 'bg-emerald-500/25 text-emerald-100 ring-1 ring-emerald-400/40'
                            : 'bg-sky-500/25 text-sky-100 ring-1 ring-sky-400/40' }}">
                        {{ $listenStatus['effective_label'] }}
                    </span>
                </div>

                <p class="text-sm text-[var(--stage-cream)]">
                    Public listeners are using
                    <strong>{{ $listenStatus['effective_label'] }}</strong>
                    @if ($listenStatus['prefers_hls'])
                        — CDN playlist delivery (higher scale, slightly more delay).
                    @else
                        — WebRTC low-latency path.
                    @endif
                </p>

                <dl class="grid gap-2 text-xs sm:grid-cols-2">
                    <div class="rounded-lg border border-[var(--stage-border)]/60 bg-black/20 px-3 py-2">
                        <dt class="text-[var(--stage-muted)]">Configured choice</dt>
                        <dd class="mt-0.5 text-[var(--stage-cream)] font-medium">{{ $listenStatus['configured_label'] }}</dd>
                        <dd class="mt-0.5 text-[var(--stage-muted)]">
                            Form value: <code class="text-[var(--stage-cream)]">{{ $listenStatus['form_value'] }}</code>
                            @if ($listenStatus['configured_choice'] === null)
                                (no admin row yet)
                            @endif
                        </dd>
                    </div>
                    <div class="rounded-lg border border-[var(--stage-border)]/60 bg-black/20 px-3 py-2">
                        <dt class="text-[var(--stage-muted)]">Why this mode</dt>
                        <dd class="mt-0.5 text-[var(--stage-cream)] font-medium">
                            @if ($listenStatus['is_auto'] && $listenStatus['auto_reason'])
                                {{ $listenStatus['auto_reason'] }}
                            @elseif ($listenStatus['form_value'] === 'hls')
                                Forced CDN HLS (not auto)
                            @else
                                Forced WHEP (not auto)
                            @endif
                        </dd>
                        <dd class="mt-0.5 text-[var(--stage-muted)]">{{ $listenStatus['policy_source_label'] }}</dd>
                    </div>
                </dl>

                <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-[var(--stage-muted)] border-t border-[var(--stage-border)]/50 pt-3">
                    <span>
                        CDN host:
                        @if ($listenStatus['cdn_host'])
                            <code class="text-[var(--stage-cream)]">{{ $listenStatus['cdn_host'] }}</code>
                        @else
                            <span class="text-amber-200/90">not set</span>
                        @endif
                    </span>
                    <span>
                        AAC sidecar:
                        <span class="{{ $listenStatus['aac_sidecar'] ? 'text-emerald-200' : 'text-amber-200/90' }}">
                            {{ $listenStatus['aac_sidecar'] ? 'on' : 'off' }}
                        </span>
                    </span>
                    <span>
                        .env fallback:
                        <code class="text-[var(--stage-cream)]">{{ $listenStatus['env_label'] }}</code>
                    </span>
                </div>
            </div>

            <fieldset class="space-y-3">
                <legend class="text-sm text-[var(--stage-muted)] mb-2">Prefer for public listeners</legend>

                <label class="flex gap-3 items-start rounded-lg border border-[var(--stage-border)] px-4 py-3 cursor-pointer hover:bg-white/5">
                    <input type="radio" name="listen_prefer_hls" value="hls" class="mt-1"
                        @checked($listenPreferHlsFormValue === 'hls')>
                    <span>
                        <span class="block text-[var(--stage-cream)] font-medium">Prefer CDN HLS</span>
                        <span class="block text-xs text-[var(--stage-muted)] mt-0.5">Best for many concurrent listeners. Slightly higher latency than WebRTC.</span>
                    </span>
                </label>

                <label class="flex gap-3 items-start rounded-lg border border-[var(--stage-border)] px-4 py-3 cursor-pointer hover:bg-white/5">
                    <input type="radio" name="listen_prefer_hls" value="whep" class="mt-1"
                        @checked($listenPreferHlsFormValue === 'whep')>
                    <span>
                        <span class="block text-[var(--stage-cream)] font-medium">Prefer WHEP (low latency)</span>
                        <span class="block text-xs text-[var(--stage-muted)] mt-0.5">WebRTC listen path. Lower latency; heavier per-connection cost at scale.</span>
                    </span>
                </label>

                <label class="flex gap-3 items-start rounded-lg border border-[var(--stage-border)] px-4 py-3 cursor-pointer hover:bg-white/5">
                    <input type="radio" name="listen_prefer_hls" value="auto" class="mt-1"
                        @checked($listenPreferHlsFormValue === 'auto')>
                    <span>
                        <span class="block text-[var(--stage-cream)] font-medium">Auto</span>
                        <span class="block text-xs text-[var(--stage-muted)] mt-0.5">Use HLS when CDN base or AAC sidecar is ready; otherwise WHEP.</span>
                    </span>
                </label>
            </fieldset>

            @error('listen_prefer_hls')
                <p class="text-xs text-red-300">{{ $message }}</p>
            @enderror

            <div class="rounded-lg border border-[var(--stage-border)]/80 px-4 py-3 text-xs text-[var(--stage-muted)] space-y-2">
                <p class="font-medium text-[var(--stage-cream)]">How to verify HLS is in use</p>
                <ol class="list-decimal list-inside space-y-1">
                    <li>Open a public event listen page → DevTools → Network. Look for a request to <code class="text-[var(--stage-cream)]">…/index.m3u8</code> (often under your CDN host{{ $listenStatus['cdn_base'] ? ', e.g. '.e($listenStatus['cdn_base']) : '' }}).</li>
                    <li>Or inspect the player root: <code class="text-[var(--stage-cream)]">data-prefer-hls="1"</code> means the page prefers HLS; the Listen API also returns <code class="text-[var(--stage-cream)]">prefer_hls</code> / <code class="text-[var(--stage-cream)]">playback_mode</code>.</li>
                </ol>
                <p class="pt-1">Saving here overrides <code class="text-[var(--stage-cream)]">LISTEN_PREFER_HLS</code> in .env. Leave .env in place as the default until an admin choice is saved.</p>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="console-btn console-btn-primary">Save</button>
            </div>
        </section>
    </form>
@endsection
