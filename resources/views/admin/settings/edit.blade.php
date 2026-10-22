@extends('layouts.admin')

@section('title', 'Platform settings')

@section('content')
    <div class="console-head">
        <div>
            <p class="site-section-label">Platform Admin</p>
            <h1 class="console-title mt-2">Platform settings</h1>
            <p class="console-lead">Global controls for public listen playback, infrastructure capacity, email, contact, and payment gateway billing.</p>
        </div>
    </div>

    <!-- Sub-navigation Tabs -->
    <div class="flex items-center gap-2 border-b border-[var(--stage-border)] pb-3 mb-6">
        <a href="{{ route('admin.settings.edit') }}" class="rounded-lg px-3 py-1.5 text-xs font-semibold bg-emerald-950/70 text-emerald-300 border border-emerald-800/60">
            Platform Settings
        </a>
        <a href="{{ route('admin.settings.email') }}" class="rounded-lg px-3 py-1.5 text-xs font-medium text-[var(--stage-muted)] hover:text-white hover:bg-zinc-800 transition">
            Email Settings
        </a>
        <a href="{{ route('admin.email-deliveries.index') }}" class="rounded-lg px-3 py-1.5 text-xs font-medium text-[var(--stage-muted)] hover:text-white hover:bg-zinc-800 transition">
            Email Deliveries
        </a>
    </div>

    @if (session('status'))
        <p class="mb-4 rounded-lg border border-emerald-500/40 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="mb-4 rounded-lg border border-red-500/40 bg-red-500/10 px-4 py-3 text-sm text-red-200">{{ session('error') }}</p>
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

        <section class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-6 space-y-5">
            <div>
                <h2 class="text-sm font-semibold uppercase tracking-wide text-[var(--stage-muted)]">Opus send rate</h2>
                <p class="mt-2 text-sm text-[var(--stage-muted)]">
                    Stereo bitrate for the studio WHIP publish. Listeners receive this rate. A broadcast already on air keeps its current rate until the studio reconnects.
                </p>
            </div>

            <label class="block text-sm text-[var(--stage-muted)]" for="opus_send_kbps">
                Send rate
                <select id="opus_send_kbps" name="opus_send_kbps" class="mt-2 block w-full max-w-xs rounded-lg border border-[var(--stage-border)] bg-black/30 px-3 py-2 text-[var(--stage-cream)]">
                    @foreach ($opusSendOptions as $kbps)
                        <option value="{{ $kbps }}" @selected($opusSendKbps === $kbps)>
                            {{ $kbps }} kbps{{ $kbps === 510 ? ' (Opus maximum)' : '' }}
                        </option>
                    @endforeach
                </select>
            </label>

            @error('opus_send_kbps')
                <p class="text-xs text-red-300">{{ $message }}</p>
            @enderror

            <div class="flex gap-3">
                <button type="submit" class="console-btn console-btn-primary">Save</button>
            </div>
        </section>

        <section class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-6 space-y-5">
            <div>
                <h2 class="text-sm font-semibold uppercase tracking-wide text-[var(--stage-muted)]">Infrastructure Capacity &amp; Compute Scaling</h2>
                <p class="mt-2 text-sm text-[var(--stage-muted)]">
                    With CDN HLS on, compute capacity only needs to scale up while someone is publishing. Idle state uses a lower-tier profile.
                    Scaling is performed by infrastructure automation (not from this page directly).
                </p>
            </div>

            <div class="rounded-xl border {{ $vmCapacity['desired'] === 'peak' ? 'border-amber-500/50 bg-amber-500/10' : 'border-emerald-500/50 bg-emerald-500/10' }} px-5 py-4 space-y-3">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="text-xs font-semibold uppercase tracking-wide text-[var(--stage-muted)]">Desired size now</span>
                    <span class="inline-flex items-center rounded-md px-3 py-1 text-sm font-semibold tracking-wide
                        {{ $vmCapacity['desired'] === 'peak'
                            ? 'bg-amber-500/25 text-amber-100 ring-1 ring-amber-400/40'
                            : 'bg-emerald-500/25 text-emerald-100 ring-1 ring-emerald-400/40' }}">
                        {{ strtoupper($vmCapacity['desired']) }}
                        ·
                        <code class="ml-1 font-mono text-xs">{{ $vmCapacity['desired_sku'] }}</code>
                    </span>
                </div>
                <p class="text-sm text-[var(--stage-cream)]">
                    Reason: <strong>{{ str_replace('_', ' ', $vmCapacity['reason']) }}</strong>
                    — live streams {{ $vmCapacity['live_streams'] }},
                    live/paused events {{ $vmCapacity['live_or_paused_events'] }},
                    upcoming {{ $vmCapacity['upcoming_events'] }}.
                </p>
                <p class="text-xs text-[var(--stage-muted)]">
                    Autoscale env flag:
                    <span class="{{ $vmCapacity['enabled'] ? 'text-emerald-200' : 'text-amber-200/90' }}">
                        {{ $vmCapacity['enabled'] ? 'on' : 'off (autoscale env flag not set)' }}
                    </span>
                    @if ($vmCapacity['hold_peak_until'])
                        · Hold peak until {{ $vmCapacity['hold_peak_until'] }}
                    @endif
                </p>
                <p class="text-xs text-[var(--stage-muted)]">
                    Idle SKU <code class="text-[var(--stage-cream)]">{{ $vmCapacity['sku_idle'] }}</code>
                    · Peak SKU <code class="text-[var(--stage-cream)]">{{ $vmCapacity['sku_peak'] }}</code>
                    · Auto-end idle events after
                    <code class="text-[var(--stage-cream)]">{{ (int) config('streaming.idle_event_auto_end_minutes') }}</code>
                    minutes (paused events / offline publishers only; default 1 hour).
                </p>
            </div>

            <label class="block text-sm text-[var(--stage-muted)]">
                Hold peak size for the next
                <input type="number" name="vm_hold_peak_hours" min="1" max="72" placeholder="hours"
                    class="mt-1 w-32 rounded-md border border-[var(--stage-border)] bg-black/30 px-3 py-2 text-[var(--stage-cream)]">
                <span class="block text-xs mt-1">Leave blank to keep the current hold. Use before a large service so the scaler stays on the peak SKU.</span>
            </label>
            <label class="flex gap-2 items-center text-sm text-[var(--stage-muted)]">
                <input type="checkbox" name="vm_clear_peak_hold" value="1">
                Clear peak hold now
            </label>
        </section>
    </form>

    <form method="POST" action="{{ route('admin.settings.payments') }}" class="mt-8" id="payments" autocomplete="off">
        @csrf
        @method('PUT')

        <section class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-6 space-y-5">
            <div>
                <h2 class="text-sm font-semibold uppercase tracking-wide text-[var(--stage-muted)]">Payments</h2>
                <p class="mt-2 text-sm text-[var(--stage-muted)]">
                    Paystack keys for checkout and webhooks. Saved keys override <code class="text-[var(--stage-cream)]">.env</code>.
                    The secret is stored encrypted and is never shown in full.
                </p>
            </div>

            <div class="rounded-xl border {{ $paystackEnabled ? 'border-emerald-500/50 bg-emerald-500/10' : 'border-amber-500/50 bg-amber-500/10' }} px-5 py-4 space-y-2">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="text-xs font-semibold uppercase tracking-wide text-[var(--stage-muted)]">Paystack now</span>
                    <span class="inline-flex items-center rounded-md px-3 py-1 text-sm font-semibold tracking-wide
                        {{ $paystackEnabled
                            ? 'bg-emerald-500/25 text-emerald-100 ring-1 ring-emerald-400/40'
                            : 'bg-amber-500/25 text-amber-100 ring-1 ring-amber-400/40' }}">
                        {{ $paystackEnabled ? 'CONNECTED' : 'NOT CONNECTED' }}
                    </span>
                </div>
                <p class="text-sm text-[var(--stage-cream)]">
                    Secret source:
                    <strong>{{ $paystackSecretFromAdmin ? 'admin settings' : '.env' }}</strong>
                    @if ($paystackSecretMask)
                        · {{ $paystackSecretMask }}
                    @else
                        · none saved
                    @endif
                </p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block text-sm sm:col-span-2">
                    <span class="text-[var(--stage-muted)]">Public key</span>
                    <input name="paystack_public_key" value="{{ $paystackPublicKey }}"
                        class="mt-1 w-full rounded-lg border border-[var(--stage-border)] bg-transparent px-3 py-2 font-mono text-sm"
                        placeholder="pk_live_… or pk_test_…" autocomplete="off">
                </label>
                <label class="block text-sm sm:col-span-2">
                    <span class="text-[var(--stage-muted)]">Secret key</span>
                    <input type="password" name="paystack_secret_key" value=""
                        class="mt-1 w-full rounded-lg border border-[var(--stage-border)] bg-transparent px-3 py-2 font-mono text-sm"
                        placeholder="{{ $paystackSecretMask ? 'Leave blank to keep the saved secret' : 'sk_live_… or sk_test_…' }}"
                        autocomplete="new-password">
                </label>
                <label class="block text-sm">
                    <span class="text-[var(--stage-muted)]">Currency</span>
                    <input name="paystack_currency" value="{{ $paystackCurrency }}" maxlength="3"
                        class="mt-1 w-full rounded-lg border border-[var(--stage-border)] bg-transparent px-3 py-2 uppercase"
                        placeholder="NGN">
                </label>
                <label class="block text-sm">
                    <span class="text-[var(--stage-muted)]">Webhook URL</span>
                    <input type="text" readonly value="{{ $paystackWebhookUrl }}"
                        class="mt-1 w-full rounded-lg border border-[var(--stage-border)] bg-black/20 px-3 py-2 font-mono text-xs text-[var(--stage-cream)]"
                        onclick="this.select()">
                </label>
            </div>

            <label class="flex gap-2 items-center text-sm text-[var(--stage-muted)]">
                <input type="checkbox" name="paystack_clear_secret" value="1">
                Remove saved secret (fall back to .env)
            </label>

            <p class="text-xs text-[var(--stage-muted)]">
                In Paystack, set the webhook URL to the value above and enable
                <code class="text-[var(--stage-cream)]">charge.success</code>,
                <code class="text-[var(--stage-cream)]">subscription.create</code>,
                <code class="text-[var(--stage-cream)]">subscription.enable</code>,
                <code class="text-[var(--stage-cream)]">subscription.disable</code>,
                and <code class="text-[var(--stage-cream)]">invoice.payment_failed</code>.
            </p>

            @error('paystack_public_key')
                <p class="text-xs text-red-300">{{ $message }}</p>
            @enderror
            @error('paystack_secret_key')
                <p class="text-xs text-red-300">{{ $message }}</p>
            @enderror
            @error('paystack_currency')
                <p class="text-xs text-red-300">{{ $message }}</p>
            @enderror

            <div class="flex gap-3">
                <button type="submit" class="console-btn console-btn-primary">Save payments</button>
            </div>
        </section>
    </form>

    <form method="POST" action="{{ route('admin.settings.mail') }}" class="mt-8" id="mail" autocomplete="off">
        @csrf
        @method('PUT')

        <section class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-6 space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-[var(--stage-muted)]">Outbound Email &amp; SMTP</h2>
                    <p class="mt-1 text-sm text-[var(--stage-muted)]">
                        Provider-agnostic outbound SMTP for transactional mail (password resets, verification, receipts, billing notifications).
                        Saved credentials override <code class="text-[var(--stage-cream)]">MAIL_*</code> in .env and are encrypted at rest.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center rounded-md px-2.5 py-1 text-xs font-semibold tracking-wider uppercase
                        {{ $mailEnabled && $mailEffectiveMailer === 'smtp'
                            ? 'bg-emerald-500/20 text-emerald-300 ring-1 ring-emerald-500/40'
                            : 'bg-amber-500/20 text-amber-300 ring-1 ring-amber-500/40' }}">
                        {{ $mailEnabled && $mailEffectiveMailer === 'smtp' ? '● Live SMTP Delivery Active' : '○ Safe / Log Mode (No external send)' }}
                    </span>
                </div>
            </div>

            <!-- Diagnostics & Status Overview -->
            <div class="rounded-xl border border-[var(--stage-border)] bg-black/20 p-5 space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[var(--stage-border)] pb-3">
                    <div class="flex items-center gap-3">
                        <span class="text-xs font-semibold uppercase tracking-wider text-[var(--stage-muted)]">Delivery Status</span>
                        <span class="inline-flex items-center rounded-md px-2.5 py-0.5 text-xs font-semibold
                            {{ $mailReady
                                ? 'bg-emerald-500/20 text-emerald-300 ring-1 ring-emerald-400/40'
                                : 'bg-amber-500/20 text-amber-300 ring-1 ring-amber-400/40' }}">
                            {{ $mailReady ? 'SMTP READY' : ($mailEffectiveMailer === 'log' ? 'LOGGING ONLY' : 'INCOMPLETE') }}
                        </span>
                    </div>
                    <div class="text-xs text-[var(--stage-muted)]">
                        Source: <strong class="text-[var(--stage-cream)]">{{ $mailFromAdmin ? 'Admin Platform Settings' : '.env defaults' }}</strong>
                        @if ($mailPasswordMask)
                            · Password: <span class="font-mono text-[var(--stage-cream)]">{{ $mailPasswordMask }}</span>
                        @endif
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 text-xs">
                    <div>
                        <span class="text-[var(--stage-muted)] block">Active Transport:</span>
                        <span class="font-mono font-medium text-[var(--stage-cream)]">{{ strtoupper((string) $mailEffectiveMailer) }}</span>
                    </div>
                    <div>
                        <span class="text-[var(--stage-muted)] block">SMTP Host &amp; Port:</span>
                        <span class="font-mono font-medium text-[var(--stage-cream)]">
                            {{ $mailHost ?: '—' }}:{{ $mailPort }} ({{ strtoupper((string) $mailEncryption) }})
                        </span>
                    </div>
                    <div>
                        <span class="text-[var(--stage-muted)] block">From Sender:</span>
                        <span class="font-mono font-medium text-[var(--stage-cream)] truncate block" title="{{ $mailFromAddress }}">
                            {{ $mailFromAddress ?: '—' }}
                        </span>
                    </div>
                    <div>
                        <span class="text-[var(--stage-muted)] block">Reply-To Address:</span>
                        <span class="font-mono font-medium text-[var(--stage-cream)] truncate block" title="{{ $mailReplyTo }}">
                            {{ $mailReplyTo ?: '—' }}
                        </span>
                    </div>
                </div>

                @if($mailLastTestAt)
                    <div class="border-t border-[var(--stage-border)] pt-3 flex flex-wrap items-center justify-between gap-2 text-xs">
                        <div class="flex items-center gap-2">
                            <span class="text-[var(--stage-muted)]">Last Test Send:</span>
                            <span class="inline-flex items-center rounded px-2 py-0.5 font-semibold text-[11px]
                                {{ $mailLastTestStatus === 'success' ? 'bg-emerald-500/20 text-emerald-300' : 'bg-red-500/20 text-red-300' }}">
                                {{ strtoupper((string) $mailLastTestStatus) }}
                            </span>
                            <span class="text-[var(--stage-cream)]">{{ $mailLastTestAt }}</span>
                            @if($mailLastTestRecipient)
                                <span class="text-[var(--stage-muted)]">to <span class="font-mono text-[var(--stage-cream)]">{{ $mailLastTestRecipient }}</span></span>
                            @endif
                        </div>
                        @if($mailLastTestError)
                            <div class="w-full mt-1.5 p-2 rounded bg-red-950/40 border border-red-800/40 text-red-300 font-mono text-[11px] break-all">
                                {{ $mailLastTestError }}
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            <!-- Master Kill-Switch Toggle -->
            <div class="p-4 rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] flex items-start gap-3">
                <input type="hidden" name="mail_enabled" value="0">
                <input type="checkbox" name="mail_enabled" id="mail_enabled" value="1"
                    @checked($mailEnabled)
                    class="mt-1 rounded border-[var(--stage-border)] bg-transparent text-emerald-500 focus:ring-emerald-500">
                <label for="mail_enabled" class="cursor-pointer">
                    <span class="block text-sm font-semibold text-[var(--stage-cream)]">
                        Enable live outbound delivery (Kill-switch)
                    </span>
                    <span class="block text-xs text-[var(--stage-muted)] mt-0.5">
                        When unchecked, all outbound mail operations (password resets, verification, receipts) are safely diverted to the system log without external network connections.
                    </span>
                </label>
            </div>

            <!-- Provider Presets -->
            <div class="space-y-2">
                <span class="text-xs uppercase tracking-wider text-[var(--stage-muted)]">Quick Presets</span>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="console-btn console-btn-ghost text-xs" id="mail-gmail-preset">Use Gmail SMTP</button>
                    <button type="button" class="console-btn console-btn-ghost text-xs" id="mail-brevo-preset">Brevo / Sendinblue (587 TLS)</button>
                    <button type="button" class="console-btn console-btn-ghost text-xs" id="mail-standard-ssl-preset">Standard Host (465 SSL)</button>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block text-sm">
                    <span class="text-[var(--stage-muted)]">Mailer Transport</span>
                    <select name="mail_mailer" id="mail_mailer" class="mt-1 w-full rounded-lg border border-[var(--stage-border)] bg-transparent px-3 py-2">
                        <option value="smtp" @selected($mailMailer === 'smtp')>SMTP (Live or relay delivery)</option>
                        <option value="log" @selected($mailMailer === 'log')>Log only (Write to storage/logs/laravel.log)</option>
                    </select>
                </label>
                <label class="block text-sm">
                    <span class="text-[var(--stage-muted)]">Encryption Protocol</span>
                    <select name="mail_encryption" id="mail_encryption" class="mt-1 w-full rounded-lg border border-[var(--stage-border)] bg-transparent px-3 py-2">
                        <option value="tls" @selected($mailEncryption === 'tls')>STARTTLS (Port 587 recommended)</option>
                        <option value="ssl" @selected($mailEncryption === 'ssl')>SSL / SMTPS (Port 465)</option>
                        <option value="none" @selected($mailEncryption === 'none')>None (Plaintext - testing only)</option>
                    </select>
                </label>
                <label class="block text-sm">
                    <span class="text-[var(--stage-muted)]">SMTP Host</span>
                    <input name="mail_host" id="mail_host" value="{{ $mailHost }}"
                        class="mt-1 w-full rounded-lg border border-[var(--stage-border)] bg-transparent px-3 py-2 font-mono text-sm"
                        placeholder="e.g. smtp-relay.brevo.com or smtp.gmail.com">
                </label>
                <label class="block text-sm">
                    <span class="text-[var(--stage-muted)]">SMTP Port</span>
                    <input type="number" name="mail_port" id="mail_port" value="{{ $mailPort }}" min="1" max="65535"
                        class="mt-1 w-full rounded-lg border border-[var(--stage-border)] bg-transparent px-3 py-2 font-mono text-sm">
                </label>
                <label class="block text-sm sm:col-span-2">
                    <span class="text-[var(--stage-muted)]">SMTP Username / Login Key</span>
                    <input name="mail_username" id="mail_username" value="{{ $mailUsername }}"
                        class="mt-1 w-full rounded-lg border border-[var(--stage-border)] bg-transparent px-3 py-2 font-mono text-sm"
                        placeholder="e.g. your-login@provider.com or API username" autocomplete="off">
                </label>
                <label class="block text-sm sm:col-span-2">
                    <span class="text-[var(--stage-muted)]">SMTP Password / API Secret</span>
                    <input type="password" name="mail_password" value=""
                        class="mt-1 w-full rounded-lg border border-[var(--stage-border)] bg-transparent px-3 py-2 font-mono text-sm"
                        placeholder="{{ $mailPasswordMask ? '•••••••••••••••• (Leave blank to keep existing encrypted password)' : 'Enter SMTP password or API token' }}"
                        autocomplete="new-password">
                </label>
                <label class="block text-sm">
                    <span class="text-[var(--stage-muted)]">From Address</span>
                    <input type="email" name="mail_from_address" id="mail_from_address" value="{{ $mailFromAddress }}"
                        class="mt-1 w-full rounded-lg border border-[var(--stage-border)] bg-transparent px-3 py-2"
                        placeholder="e.g. support@soundmix.live">
                </label>
                <label class="block text-sm">
                    <span class="text-[var(--stage-muted)]">From Name</span>
                    <input name="mail_from_name" value="{{ $mailFromName }}"
                        class="mt-1 w-full rounded-lg border border-[var(--stage-border)] bg-transparent px-3 py-2"
                        placeholder="SoundMix Live">
                </label>
                <label class="block text-sm sm:col-span-2">
                    <span class="text-[var(--stage-muted)]">Reply-To Address</span>
                    <input type="email" name="mail_reply_to" id="mail_reply_to" value="{{ $mailReplyTo }}"
                        class="mt-1 w-full rounded-lg border border-[var(--stage-border)] bg-transparent px-3 py-2"
                        placeholder="support@soundmix.live">
                    <span class="mt-1 block text-[11px] text-[var(--stage-muted)]">
                        Inbound replies from recipients will be routed here (Cloudflare Email Routing or support inbox).
                    </span>
                </label>
            </div>

            <label class="flex gap-2 items-center text-sm text-[var(--stage-muted)]">
                <input type="checkbox" name="mail_clear_password" value="1">
                Remove saved password (clear stored encrypted secret)
            </label>

            @error('mail_mailer')
                <p class="text-xs text-red-300">{{ $message }}</p>
            @enderror
            @error('mail_host')
                <p class="text-xs text-red-300">{{ $message }}</p>
            @enderror
            @error('mail_port')
                <p class="text-xs text-red-300">{{ $message }}</p>
            @enderror
            @error('mail_from_address')
                <p class="text-xs text-red-300">{{ $message }}</p>
            @enderror
            @error('mail_reply_to')
                <p class="text-xs text-red-300">{{ $message }}</p>
            @enderror

            <div class="flex flex-wrap gap-3 pt-2">
                <button type="submit" class="console-btn console-btn-primary">Save email</button>
            </div>
        </section>
    </form>

    <!-- Outbound Delivery Verification Form -->
    <form method="POST" action="{{ route('admin.settings.mail.test') }}" class="mt-4" autocomplete="off">
        @csrf
        <section class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-6 space-y-4">
            <div>
                <h3 class="text-xs font-semibold uppercase tracking-wider text-[var(--stage-muted)]">Send Test Email</h3>
                <p class="mt-1 text-xs text-[var(--stage-muted)]">
                    Dispatches a real diagnostic verification email via the active transport to test credentials, TLS handshake, and recipient delivery.
                </p>
            </div>

            <div class="flex flex-wrap gap-3 items-end max-w-xl">
                <label class="flex-1 min-w-[240px] block text-sm">
                    <span class="text-[var(--stage-muted)] text-xs">Test Recipient Address</span>
                    <input type="email" name="test_recipient" value="{{ old('test_recipient', auth()->user()?->email) }}"
                        class="mt-1 w-full rounded-lg border border-[var(--stage-border)] bg-transparent px-3 py-2 text-sm"
                        placeholder="admin@example.com" required>
                </label>
                <button type="submit" class="console-btn console-btn-ghost">Send test email</button>
            </div>
        </section>
    </form>

    <form method="POST" action="{{ route('admin.settings.contact') }}" class="mt-8" id="contact">
        @csrf
        @method('PUT')

        <section class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-6 space-y-5">
            <div>
                <h2 class="text-sm font-semibold uppercase tracking-wide text-[var(--stage-muted)]">Contact</h2>
                <p class="mt-2 text-sm text-[var(--stage-muted)]">
                    Public support inbox for /support, legal pages, the site footer, and Contact sales.
                    A saved address overrides <code class="text-[var(--stage-cream)]">SUPPORT_EMAIL</code> in .env.
                </p>
            </div>

            <p class="text-sm text-[var(--stage-cream)]">
                Source:
                <strong>{{ $supportEmailFromAdmin ? 'admin settings' : '.env' }}</strong>
            </p>

            <label class="block text-sm">
                <span class="text-[var(--stage-muted)]">Support email</span>
                <input type="email" name="support_email" value="{{ $supportEmail }}"
                    class="mt-1 w-full rounded-lg border border-[var(--stage-border)] bg-transparent px-3 py-2"
                    placeholder="support@soundmix.live">
            </label>

            @error('support_email')
                <p class="text-xs text-red-300">{{ $message }}</p>
            @enderror

            <p class="text-xs text-[var(--stage-muted)]">Leave blank and save to fall back to .env.</p>

            <div class="pt-4 border-t border-[var(--stage-border)] space-y-4">
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-[var(--stage-muted)]">WhatsApp Support Widget</h3>
                    <p class="mt-1 text-xs text-[var(--stage-muted)]">
                        Enables a global one-click WhatsApp support button across public, listener, creator, and super admin pages, plus a compact action in Web Studio.
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <input type="checkbox" name="whatsapp_support_enabled" id="whatsapp_support_enabled" value="1"
                        @checked($whatsappEnabled)
                        class="rounded border-[var(--stage-border)] bg-transparent text-emerald-500 focus:ring-emerald-500">
                    <label for="whatsapp_support_enabled" class="text-sm font-medium text-[var(--stage-cream)]">
                        Enable WhatsApp Support Widget
                    </label>
                </div>

                <p class="text-sm text-[var(--stage-cream)]">
                    Number Source:
                    <strong>{{ $whatsappNumberFromAdmin ? 'admin settings' : '.env' }}</strong>
                </p>

                <label class="block text-sm">
                    <span class="text-[var(--stage-muted)]">WhatsApp Support Number</span>
                    <input type="text" name="whatsapp_support_number" value="{{ $whatsappNumber }}"
                        class="mt-1 w-full rounded-lg border border-[var(--stage-border)] bg-transparent px-3 py-2 font-mono text-sm"
                        placeholder="2348012345678">
                    <span class="mt-1 block text-[11px] text-[var(--stage-muted)]">
                        Use an international WhatsApp number, e.g. 2348012345678.
                    </span>
                </label>

                @error('whatsapp_support_number')
                    <p class="text-xs text-red-300">{{ $message }}</p>
                @enderror

                <label class="block text-sm">
                    <span class="text-[var(--stage-muted)]">Default Support Message Greeting (Optional)</span>
                    <textarea name="whatsapp_default_message" rows="2"
                        class="mt-1 w-full rounded-lg border border-[var(--stage-border)] bg-transparent px-3 py-2 text-sm"
                        placeholder="Hello SoundMix Support,&#10;I need help with SoundMix.">{{ $whatsappDefaultMessage }}</textarea>
                </label>

                @error('whatsapp_default_message')
                    <p class="text-xs text-red-300">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex gap-3">
                <button type="submit" class="console-btn console-btn-primary">Save contact &amp; support</button>
            </div>
        </section>
    </form>
@endsection

@section('vite')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const setSmtpPreset = (hostVal, portVal, encryptionVal) => {
                const mailer = document.getElementById('mail_mailer');
                const host = document.getElementById('mail_host');
                const port = document.getElementById('mail_port');
                const encryption = document.getElementById('mail_encryption');
                if (mailer) mailer.value = 'smtp';
                if (host) host.value = hostVal;
                if (port) port.value = portVal;
                if (encryption) encryption.value = encryptionVal;
            };

            document.getElementById('mail-gmail-preset')?.addEventListener('click', () => {
                setSmtpPreset('smtp.gmail.com', '587', 'tls');
            });
            document.getElementById('mail-brevo-preset')?.addEventListener('click', () => {
                setSmtpPreset('smtp-relay.brevo.com', '587', 'tls');
            });
            document.getElementById('mail-standard-ssl-preset')?.addEventListener('click', () => {
                setSmtpPreset('mail.soundmix.live', '465', 'ssl');
            });
        });
    </script>
@endsection
