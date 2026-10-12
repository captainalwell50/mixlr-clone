@extends('layouts.admin')

@section('title', 'Email Settings')

@section('content')
    <div class="console-head">
        <div>
            <p class="site-section-label">Platform Admin</p>
            <h1 class="console-title mt-2">Email Settings</h1>
            <p class="console-lead">Manage inbound email forwarding via Cloudflare Email Routing and monitor platform mail configuration.</p>
        </div>
    </div>

    <!-- Sub-navigation Tabs -->
    <div class="flex items-center gap-2 border-b border-[var(--stage-border)] pb-3 mb-6">
        <a href="{{ route('admin.settings.edit') }}" class="rounded-lg px-3 py-1.5 text-xs font-medium text-[var(--stage-muted)] hover:text-white hover:bg-zinc-800 transition">
            Platform Settings
        </a>
        <a href="{{ route('admin.settings.email') }}" class="rounded-lg px-3 py-1.5 text-xs font-semibold bg-emerald-950/70 text-emerald-300 border border-emerald-800/60">
            Email Settings
        </a>
        <a href="{{ route('admin.email-deliveries.index') }}" class="rounded-lg px-3 py-1.5 text-xs font-medium text-[var(--stage-muted)] hover:text-white hover:bg-zinc-800 transition">
            Email Deliveries
        </a>
    </div>

    @if (session('status'))
        <div class="mb-6 rounded-lg border border-emerald-800/60 bg-emerald-950/40 px-4 py-3 text-sm text-emerald-200">
            {{ session('status') }}
        </div>
    @endif

    @if (session('error'))
        <div class="mb-6 rounded-lg border border-red-900/60 bg-red-950/40 px-4 py-3 text-sm text-red-200">
            {{ session('error') }}
        </div>
    @endif

    <div class="space-y-8">
        <!-- SECTION 1: INBOUND EMAIL ROUTING -->
        <section class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-6 space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-4 border-b border-[var(--stage-border)] pb-4">
                <div>
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-[var(--stage-muted)]">Inbound Email Routing</h2>
                    <p class="mt-1 text-sm text-[var(--stage-cream)]">
                        Cloudflare Email Routing configuration for <strong class="text-white">{{ $domain }}</strong>.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    @if ($cfConfigured)
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/30">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                            Cloudflare API Connected
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium bg-amber-500/10 text-amber-400 border border-amber-500/30">
                            <span class="h-1.5 w-1.5 rounded-full bg-amber-400"></span>
                            Manual Mode (API Not Configured)
                        </span>
                    @endif
                </div>
            </div>

            <!-- Routing Status Cards -->
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-lg border border-[var(--stage-border)] bg-[var(--stage-card)] p-4">
                    <span class="text-xs text-[var(--stage-muted)]">Domain</span>
                    <p class="mt-1 font-semibold text-white">{{ $domain }}</p>
                    <span class="text-[11px] text-[var(--stage-muted)]">Phase 1 Inbound Domain</span>
                </div>

                <div class="rounded-lg border border-[var(--stage-border)] bg-[var(--stage-card)] p-4">
                    <span class="text-xs text-[var(--stage-muted)]">Routing Status</span>
                    <div class="mt-1 flex items-center gap-2">
                        @if ($cfConfigured)
                            @if (!empty($cfRouting['enabled']))
                                <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                    Active
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium bg-amber-500/20 text-amber-300 border border-amber-500/30">
                                    Attention Required
                                </span>
                            @endif
                        @else
                            <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                Active (DNS Routing)
                            </span>
                        @endif
                    </div>
                    <span class="text-[11px] text-[var(--stage-muted)]">Cloudflare MX & SPF Verified</span>
                </div>

                <div class="rounded-lg border border-[var(--stage-border)] bg-[var(--stage-card)] p-4">
                    <span class="text-xs text-[var(--stage-muted)]">Last Routing Sync</span>
                    <p class="mt-1 font-medium text-white text-sm">
                        {{ $lastSyncedAt ? \Carbon\Carbon::parse($lastSyncedAt)->diffForHumans() : 'Phase 1 Provisioned' }}
                    </p>
                    <span class="text-[11px] text-[var(--stage-muted)]">
                        {{ $lastSyncedAt ? \Carbon\Carbon::parse($lastSyncedAt)->format('M d, Y H:i T') : 'Initial setup' }}
                    </span>
                </div>
            </div>

            <!-- Destination Management -->
            <div class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-card)] p-5 space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[var(--stage-border)] pb-3">
                    <div>
                        <h3 class="text-sm font-semibold text-white">Forwarding Destination</h3>
                        <p class="text-xs text-[var(--stage-muted)]">Target mailbox where inbound messages to all SoundMix aliases are delivered.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-[var(--stage-muted)]">Active Inbox:</span>
                        @if ($activeDestination)
                            <span class="font-mono text-xs font-medium text-emerald-400 bg-emerald-950/60 px-2.5 py-1 rounded border border-emerald-800/60">
                                {{ $activeDestination }}
                            </span>
                        @else
                            <span class="font-sans text-xs font-medium text-amber-400 bg-amber-950/60 px-2.5 py-1 rounded border border-amber-800/60">
                                Not configured locally
                            </span>
                        @endif
                    </div>
                </div>

                <!-- SAFE CHANGE FLOW UI -->
                @if ($pendingDestination)
                    <!-- Verification Pending Alert Banner -->
                    <div class="rounded-lg border border-amber-500/50 bg-amber-950/30 p-4 space-y-3">
                        <div class="flex items-start gap-3">
                            <svg class="h-5 w-5 text-amber-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                            </svg>
                            <div class="flex-1 space-y-1">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <h4 class="text-sm font-semibold text-amber-200">Destination Change in Progress</h4>
                                    @if ($destinationStatus === 'verified')
                                        <span class="rounded bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 px-2 py-0.5 text-xs font-medium">
                                            ✓ Verified & Ready to Sync
                                        </span>
                                    @elseif ($destinationStatus === 'pending')
                                        <span class="rounded bg-amber-500/20 text-amber-300 border border-amber-500/40 px-2 py-0.5 text-xs font-medium">
                                            ⏳ Verification Pending
                                        </span>
                                    @else
                                        <span class="rounded bg-rose-500/20 text-rose-300 border border-rose-500/40 px-2 py-0.5 text-xs font-medium">
                                            ✕ Verification Issue
                                        </span>
                                    @endif
                                </div>
                                <div class="grid gap-1 sm:grid-cols-2 text-xs pt-1">
                                    <p class="text-zinc-300">
                                        <strong class="text-amber-300">Proposed New:</strong> <span class="font-mono text-white">{{ $pendingDestination }}</span>
                                    </p>
                                    <p class="text-zinc-300">
                                        <strong class="text-emerald-400">Current Active:</strong> <span class="font-mono text-white">{{ $activeDestination ?: 'Not configured locally' }}</span>
                                    </p>
                                </div>
                                <p class="text-xs text-amber-300/90 font-medium pt-1">
                                    ⚠️ Your current forwarding inbox remains active until the new address is verified and routing rules are switched.
                                </p>
                            </div>
                        </div>

                        <!-- Action Controls -->
                        <div class="flex flex-wrap items-center gap-3 pt-2 border-t border-amber-800/40">
                            <!-- Check Verification Action -->
                            <form method="POST" action="{{ route('admin.settings.email.verify') }}" class="inline">
                                @csrf
                                <button type="submit" class="rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-1.5 text-xs font-medium text-white hover:bg-zinc-700 transition">
                                    Check verification
                                </button>
                            </form>

                            @if (!$cfConfigured)
                                <!-- Manual confirmation option -->
                                <form method="POST" action="{{ route('admin.settings.email.verify') }}" class="inline">
                                    @csrf
                                    <input type="hidden" name="manual_confirm" value="1">
                                    <button type="submit" class="rounded-lg border border-amber-700/60 bg-amber-900/40 px-3 py-1.5 text-xs font-medium text-amber-200 hover:bg-amber-800/40 transition">
                                        Confirm manual verification
                                    </button>
                                </form>
                            @endif

                            <!-- Sync Routing Action -->
                            <form method="POST" action="{{ route('admin.settings.email.sync') }}" class="inline">
                                @csrf
                                <button
                                    type="submit"
                                    class="rounded-lg px-3 py-1.5 text-xs font-semibold transition {{ $destinationStatus === 'verified' ? 'bg-emerald-600 text-white hover:bg-emerald-500 shadow-sm' : 'bg-zinc-800 text-zinc-500 border border-zinc-700 cursor-not-allowed' }}"
                                    {{ $destinationStatus !== 'verified' ? 'disabled' : '' }}
                                    title="{{ $destinationStatus === 'verified' ? 'Switch all routing rules to the new destination' : 'Destination must be verified first' }}"
                                >
                                    Sync routing rules
                                </button>
                            </form>

                            <!-- Cancel Change -->
                            <form method="POST" action="{{ route('admin.settings.email.cancel-pending') }}" class="inline ml-auto">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs text-zinc-400 hover:text-rose-400 transition underline underline-offset-2">
                                    Cancel proposed change
                                </button>
                            </form>
                        </div>
                    </div>
                @else
                    <!-- Change Destination Form -->
                    <form method="POST" action="{{ route('admin.settings.email.destination') }}" class="space-y-4">
                        @csrf
                        @method('PUT')

                        <div>
                            <label for="destination_email" class="block text-xs font-semibold uppercase tracking-wider text-[var(--stage-muted)]">
                                Default forwarding inbox
                            </label>
                            <div class="mt-1 flex max-w-lg gap-2">
                                <input
                                    type="email"
                                    name="destination_email"
                                    id="destination_email"
                                    value="{{ old('destination_email') }}"
                                    placeholder="e.g. admin@example.com"
                                    required
                                    class="w-full rounded-lg border border-[var(--stage-border)] bg-[var(--stage-panel)] px-3 py-2 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-emerald-500"
                                >
                                <button
                                    type="submit"
                                    class="rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-500 transition shrink-0"
                                >
                                    Change destination
                                </button>
                            </div>
                            <p class="mt-1.5 text-xs text-[var(--stage-muted)]">
                                Submitting a new destination initiates Cloudflare destination verification. Current forwarding remains 100% active until verification completes.
                            </p>
                        </div>
                    </form>
                @endif
            </div>

            <!-- PER-ALIAS STATUS TABLE -->
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-[var(--stage-muted)]">Managed Inbound Aliases</h3>
                    <span class="text-xs text-[var(--stage-muted)]">Phase 1: All aliases forward to the default destination</span>
                </div>

                <div class="overflow-x-auto rounded-lg border border-[var(--stage-border)] bg-[var(--stage-card)]">
                    <table class="w-full text-left text-xs">
                        <thead class="border-b border-[var(--stage-border)] bg-black/20 text-[var(--stage-muted)]">
                            <tr>
                                <th class="px-4 py-2.5 font-medium">Inbound Address</th>
                                <th class="px-4 py-2.5 font-medium">Forwarding Destination</th>
                                <th class="px-4 py-2.5 font-medium">Status</th>
                                <th class="px-4 py-2.5 font-medium">Cloudflare Routing</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--stage-border)] font-mono">
                            @foreach ($aliasesTable as $row)
                                <tr class="hover:bg-zinc-800/30 transition">
                                    <td class="px-4 py-3 font-semibold text-emerald-300">
                                        {{ $row['address'] }}
                                    </td>
                                    <td class="px-4 py-3 text-zinc-300">
                                        {{ $row['destination'] }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center gap-1 rounded bg-emerald-500/20 text-emerald-400 px-2 py-0.5 text-[11px] font-sans font-medium border border-emerald-500/30">
                                            <span class="h-1 w-1 rounded-full bg-emerald-400"></span>
                                            {{ $row['status'] }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 font-sans text-zinc-400 text-xs">
                                        {{ $row['cloudflare_status'] }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- RECENT AUDIT LOG -->
            @if ($recentAudits->isNotEmpty())
                <div class="space-y-3 pt-2">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-[var(--stage-muted)]">Recent Email Routing Audit Log</h3>
                    <div class="overflow-x-auto rounded-lg border border-[var(--stage-border)] bg-[var(--stage-card)]">
                        <table class="w-full text-left text-xs">
                            <thead class="border-b border-[var(--stage-border)] bg-black/20 text-[var(--stage-muted)]">
                                <tr>
                                    <th class="px-4 py-2 font-medium">Time</th>
                                    <th class="px-4 py-2 font-medium">Actor</th>
                                    <th class="px-4 py-2 font-medium">Action</th>
                                    <th class="px-4 py-2 font-medium">Previous → Proposed</th>
                                    <th class="px-4 py-2 font-medium">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[var(--stage-border)] font-mono text-[11px]">
                                @foreach ($recentAudits as $audit)
                                    <tr class="hover:bg-zinc-800/30 transition">
                                        <td class="px-4 py-2 font-sans text-zinc-400">
                                            {{ $audit->created_at->format('M d, H:i:s') }}
                                        </td>
                                        <td class="px-4 py-2 font-sans text-zinc-300">
                                            {{ $audit->actor?->name ?? 'System' }}
                                        </td>
                                        <td class="px-4 py-2 font-sans font-medium text-white">
                                            {{ ucwords(str_replace('_', ' ', $audit->action)) }}
                                        </td>
                                        <td class="px-4 py-2 text-zinc-300">
                                            @if ($audit->previous_destination || $audit->proposed_destination)
                                                {{ $audit->previous_destination ?? 'none' }} → {{ $audit->proposed_destination ?? 'none' }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 font-sans">
                                            <span class="rounded px-1.5 py-0.5 text-[10px] font-medium {{ $audit->status === 'success' || $audit->status === 'verified' ? 'bg-emerald-500/20 text-emerald-300' : ($audit->status === 'pending' ? 'bg-amber-500/20 text-amber-300' : 'bg-rose-500/20 text-rose-300') }}">
                                                {{ strtoupper($audit->status) }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </section>

        <!-- SECTION 2: OUTBOUND APPLICATION EMAIL (Strictly Informational & Separated) -->
        <section class="rounded-xl border border-[var(--stage-border)] bg-[var(--stage-panel)] p-6 space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-4 border-b border-[var(--stage-border)] pb-4">
                <div>
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-[var(--stage-muted)]">Outbound Application Email</h2>
                    <p class="mt-1 text-sm text-[var(--stage-cream)]">
                        Transactional system messages, payment receipts, onboarding, and billing notifications.
                    </p>
                </div>
                <div>
                    <span class="inline-flex items-center rounded-md px-2.5 py-1 text-xs font-semibold tracking-wide
                        {{ str_contains($outboundStatus, 'Provider configured') ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40' : 'bg-sky-500/20 text-sky-300 border border-sky-500/40' }}">
                        {{ $outboundStatus }}
                    </span>
                </div>
            </div>

            <div class="rounded-lg border border-[var(--stage-border)] bg-[var(--stage-card)] p-4 text-xs text-[var(--stage-muted)] space-y-2">
                <p>
                    <strong class="text-white">Architecture Separation:</strong> Inbound forwarding and outbound transactional mailing operate on completely separate subsystems.
                    Inbound mail routes through Cloudflare Email Routing without storing mail on the server.
                    Outbound transactional notifications are queued in Laravel and recorded in the audit delivery ledger.
                </p>
                <div class="flex items-center gap-3 pt-2">
                    <a
                        href="{{ route('admin.email-deliveries.index') }}"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-1.5 text-xs font-medium text-white hover:bg-zinc-700 transition"
                    >
                        <span>Open Outbound Delivery Ledger</span>
                        <svg class="h-3.5 w-3.5 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                        </svg>
                    </a>
                </div>
            </div>
        </section>
    </div>
@endsection
