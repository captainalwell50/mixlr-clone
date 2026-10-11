@extends('layouts.admin')

@section('title', 'Platform settings')

@section('content')
    <div class="console-head">
        <div>
            <p class="site-section-label">Operator</p>
            <h1 class="console-title mt-2">Platform settings</h1>
            <p class="console-lead">Global controls for public listen playback, origin VM size, email, contact, and Paystack billing.</p>
        </div>
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
                <h2 class="text-sm font-semibold uppercase tracking-wide text-[var(--stage-muted)]">Origin host capacity / VM size</h2>
                <p class="mt-2 text-sm text-[var(--stage-muted)]">
                    With CDN HLS on, this media host only needs to be large while someone is publishing. Idle uses a cheaper SKU.
                    Resize reboots the server for a few minutes — it is performed by host infrastructure automation (not from this page directly).
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
                        {{ $vmCapacity['enabled'] ? 'on' : 'off (set AZURE_VM_AUTOSCALE_ENABLED=true)' }}
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

        <section class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-6 space-y-5">
            <div>
                <h2 class="text-sm font-semibold uppercase tracking-wide text-[var(--stage-muted)]">Email</h2>
                <p class="mt-2 text-sm text-[var(--stage-muted)]">
                    SMTP for Free-plan reminders, expiry mail, and other platform messages.
                    Saved settings override <code class="text-[var(--stage-cream)]">MAIL_*</code> in .env. The password is stored encrypted.
                </p>
            </div>

            <div class="rounded-xl border {{ $mailReady ? 'border-emerald-500/50 bg-emerald-500/10' : 'border-amber-500/50 bg-amber-500/10' }} px-5 py-4 space-y-2">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="text-xs font-semibold uppercase tracking-wide text-[var(--stage-muted)]">Mail now</span>
                    <span class="inline-flex items-center rounded-md px-3 py-1 text-sm font-semibold tracking-wide
                        {{ $mailReady
                            ? 'bg-emerald-500/25 text-emerald-100 ring-1 ring-emerald-400/40'
                            : 'bg-amber-500/25 text-amber-100 ring-1 ring-amber-400/40' }}">
                        {{ $mailReady ? 'SMTP READY' : (strtoupper((string) $mailMailer) === 'LOG' ? 'LOGGING ONLY' : 'NOT READY') }}
                    </span>
                </div>
                <p class="text-sm text-[var(--stage-cream)]">
                    Source: <strong>{{ $mailFromAdmin ? 'admin settings' : '.env' }}</strong>
                    @if ($mailPasswordMask)
                        · {{ $mailPasswordMask }}
                    @endif
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <button type="button" class="console-btn console-btn-ghost" id="mail-gmail-preset">Use Gmail SMTP</button>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block text-sm">
                    <span class="text-[var(--stage-muted)]">Mailer</span>
                    <select name="mail_mailer" id="mail_mailer" class="mt-1 w-full rounded-lg border border-[var(--stage-border)] bg-transparent px-3 py-2">
                        <option value="smtp" @selected($mailMailer === 'smtp')>SMTP (Gmail or other)</option>
                        <option value="log" @selected($mailMailer === 'log')>Log only (don’t send)</option>
                    </select>
                </label>
                <label class="block text-sm">
                    <span class="text-[var(--stage-muted)]">Encryption</span>
                    <select name="mail_encryption" id="mail_encryption" class="mt-1 w-full rounded-lg border border-[var(--stage-border)] bg-transparent px-3 py-2">
                        <option value="tls" @selected($mailEncryption === 'tls')>TLS (Gmail port 587)</option>
                        <option value="ssl" @selected($mailEncryption === 'ssl')>SSL (Gmail port 465)</option>
                        <option value="none" @selected($mailEncryption === 'none')>None</option>
                    </select>
                </label>
                <label class="block text-sm">
                    <span class="text-[var(--stage-muted)]">Host</span>
                    <input name="mail_host" id="mail_host" value="{{ $mailHost }}"
                        class="mt-1 w-full rounded-lg border border-[var(--stage-border)] bg-transparent px-3 py-2 font-mono text-sm"
                        placeholder="smtp.gmail.com">
                </label>
                <label class="block text-sm">
                    <span class="text-[var(--stage-muted)]">Port</span>
                    <input type="number" name="mail_port" id="mail_port" value="{{ $mailPort }}" min="1" max="65535"
                        class="mt-1 w-full rounded-lg border border-[var(--stage-border)] bg-transparent px-3 py-2">
                </label>
                <label class="block text-sm sm:col-span-2">
                    <span class="text-[var(--stage-muted)]">Username</span>
                    <input name="mail_username" id="mail_username" value="{{ $mailUsername }}"
                        class="mt-1 w-full rounded-lg border border-[var(--stage-border)] bg-transparent px-3 py-2"
                        placeholder="you@gmail.com" autocomplete="off">
                </label>
                <label class="block text-sm sm:col-span-2">
                    <span class="text-[var(--stage-muted)]">Password</span>
                    <input type="password" name="mail_password" value=""
                        class="mt-1 w-full rounded-lg border border-[var(--stage-border)] bg-transparent px-3 py-2 font-mono text-sm"
                        placeholder="{{ $mailPasswordMask ? 'Leave blank to keep the saved password' : 'Gmail App Password' }}"
                        autocomplete="new-password">
                </label>
                <label class="block text-sm">
                    <span class="text-[var(--stage-muted)]">From address</span>
                    <input type="email" name="mail_from_address" id="mail_from_address" value="{{ $mailFromAddress }}"
                        class="mt-1 w-full rounded-lg border border-[var(--stage-border)] bg-transparent px-3 py-2"
                        placeholder="you@gmail.com">
                </label>
                <label class="block text-sm">
                    <span class="text-[var(--stage-muted)]">From name</span>
                    <input name="mail_from_name" value="{{ $mailFromName }}"
                        class="mt-1 w-full rounded-lg border border-[var(--stage-border)] bg-transparent px-3 py-2"
                        placeholder="Sound Mix Live">
                </label>
            </div>

            <label class="flex gap-2 items-center text-sm text-[var(--stage-muted)]">
                <input type="checkbox" name="mail_clear_password" value="1">
                Remove saved password (fall back to .env)
            </label>

            <p class="text-xs text-[var(--stage-muted)]">
                Gmail: turn on 2-Step Verification, then create an
                <a class="console-link" href="https://myaccount.google.com/apppasswords" target="_blank" rel="noreferrer">App Password</a>
                and paste it above. A normal Gmail password will be rejected.
            </p>

            @error('mail_mailer')
                <p class="text-xs text-red-300">{{ $message }}</p>
            @enderror
            @error('mail_from_address')
                <p class="text-xs text-red-300">{{ $message }}</p>
            @enderror

            <div class="flex flex-wrap gap-3">
                <button type="submit" class="console-btn console-btn-primary">Save email</button>
            </div>
        </section>
    </form>

    <form method="POST" action="{{ route('admin.settings.mail.test') }}" class="mt-3">
        @csrf
        <button type="submit" class="console-btn console-btn-ghost">Send test email to my account</button>
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

            <div class="flex gap-3">
                <button type="submit" class="console-btn console-btn-primary">Save contact</button>
            </div>
        </section>
    </form>
@endsection

@section('vite')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('mail-gmail-preset')?.addEventListener('click', () => {
                const mailer = document.getElementById('mail_mailer');
                const host = document.getElementById('mail_host');
                const port = document.getElementById('mail_port');
                const encryption = document.getElementById('mail_encryption');
                if (mailer) mailer.value = 'smtp';
                if (host) host.value = 'smtp.gmail.com';
                if (port) port.value = '587';
                if (encryption) encryption.value = 'tls';
            });
        });
    </script>
@endsection
