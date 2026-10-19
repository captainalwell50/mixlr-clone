@extends('layouts.admin')

@section('title', 'Email Deliveries')

@section('content')
    <div class="console-head">
        <div>
            <p class="site-section-label">Platform Admin</p>
            <h1 class="console-title mt-2">Email Deliveries</h1>
            <p class="console-lead">Audit ledger for transactional, operational, and billing email delivery.</p>
        </div>
    </div>

    <!-- Sub-navigation Tabs -->
    <div class="flex items-center gap-2 border-b border-[var(--stage-border)] pb-3 mb-6">
        <a href="{{ route('admin.settings.edit') }}" class="rounded-lg px-3 py-1.5 text-xs font-medium text-[var(--stage-muted)] hover:text-white hover:bg-zinc-800 transition">
            Platform Settings
        </a>
        <a href="{{ route('admin.settings.email') }}" class="rounded-lg px-3 py-1.5 text-xs font-medium text-[var(--stage-muted)] hover:text-white hover:bg-zinc-800 transition">
            Email Settings
        </a>
        <a href="{{ route('admin.email-deliveries.index') }}" class="rounded-lg px-3 py-1.5 text-xs font-semibold bg-emerald-950/70 text-emerald-300 border border-emerald-800/60">
            Email Deliveries
        </a>
    </div>

    <!-- Quick Stats -->
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 my-6">
        <div class="rounded-lg bg-[var(--stage-card)] p-4 border border-[var(--stage-border)]">
            <span class="text-xs text-[var(--stage-muted)]">Total Dispatched</span>
            <p class="text-xl font-bold text-white mt-1">{{ number_format($stats['total']) }}</p>
        </div>
        <div class="rounded-lg bg-[var(--stage-card)] p-4 border border-[var(--stage-border)]">
            <span class="text-xs text-[var(--stage-muted)]">Sent</span>
            <p class="text-xl font-bold text-sky-400 mt-1">{{ number_format($stats['sent']) }}</p>
        </div>
        <div class="rounded-lg bg-[var(--stage-card)] p-4 border border-[var(--stage-border)]">
            <span class="text-xs text-[var(--stage-muted)]">Delivered</span>
            <p class="text-xl font-bold text-emerald-400 mt-1">{{ number_format($stats['delivered']) }}</p>
        </div>
        <div class="rounded-lg bg-[var(--stage-card)] p-4 border border-[var(--stage-border)]">
            <span class="text-xs text-[var(--stage-muted)]">Failed</span>
            <p class="text-xl font-bold text-rose-400 mt-1">{{ number_format($stats['failed']) }}</p>
        </div>
        <div class="rounded-lg bg-[var(--stage-card)] p-4 border border-[var(--stage-border)]">
            <span class="text-xs text-[var(--stage-muted)]">Bounced</span>
            <p class="text-xl font-bold text-amber-400 mt-1">{{ number_format($stats['bounced']) }}</p>
        </div>
    </div>

    <!-- Filter Bar -->
    <form method="GET" action="{{ route('admin.email-deliveries.index') }}" class="flex flex-wrap gap-3 mb-6 items-center">
        <input type="text" name="q" value="{{ $search }}" placeholder="Search recipient, type, event key..." class="bg-[var(--stage-card)] border border-[var(--stage-border)] rounded px-3 py-1.5 text-sm text-white placeholder-slate-400 focus:outline-none focus:border-sky-400">
        
        <select name="status" class="bg-[var(--stage-card)] border border-[var(--stage-border)] rounded px-3 py-1.5 text-sm text-white focus:outline-none">
            <option value="">All Statuses</option>
            <option value="sent" {{ $currentStatus === 'sent' ? 'selected' : '' }}>Sent</option>
            <option value="delivered" {{ $currentStatus === 'delivered' ? 'selected' : '' }}>Delivered</option>
            <option value="failed" {{ $currentStatus === 'failed' ? 'selected' : '' }}>Failed</option>
            <option value="bounced" {{ $currentStatus === 'bounced' ? 'selected' : '' }}>Bounced</option>
            <option value="suppressed" {{ $currentStatus === 'suppressed' ? 'selected' : '' }}>Suppressed</option>
        </select>

        <button type="submit" class="console-btn console-btn-primary text-xs py-1.5 px-3">Filter</button>
        @if($currentStatus || $currentType || $search)
            <a href="{{ route('admin.email-deliveries.index') }}" class="console-link text-xs">Clear</a>
        @endif
    </form>

    <!-- Ledger Table -->
    <div class="console-table">
        <table>
            <thead>
                <tr>
                    <th>Recipient</th>
                    <th>Type</th>
                    <th>Category</th>
                    <th>Status</th>
                    <th>Attempts</th>
                    <th>Timestamp</th>
                    <th>Error / Notes</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($deliveries as $delivery)
                    <tr>
                        <td class="text-white font-medium">
                            {{ $delivery->recipient }}
                            @if($delivery->user)
                                <span class="block text-xs text-[var(--stage-muted)]">{{ $delivery->user->name }}</span>
                            @endif
                        </td>
                        <td class="text-[var(--stage-muted)]">
                            <span class="inline-block px-2 py-0.5 rounded text-xs bg-slate-800 text-slate-300 font-mono">{{ $delivery->type }}</span>
                        </td>
                        <td class="text-xs uppercase text-[var(--stage-muted)]">
                            {{ $delivery->category?->value ?? 'transactional' }}
                        </td>
                        <td>
                            @php
                                $badgeColor = match($delivery->status) {
                                    'sent' => 'bg-sky-500/10 text-sky-400 border-sky-500/20',
                                    'delivered' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
                                    'failed' => 'bg-rose-500/10 text-rose-400 border-rose-500/20',
                                    'bounced' => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
                                    'suppressed' => 'bg-purple-500/10 text-purple-400 border-purple-500/20',
                                    default => 'bg-slate-500/10 text-slate-400 border-slate-500/20',
                                };
                            @endphp
                            <span class="inline-block px-2 py-0.5 rounded text-xs border {{ $badgeColor }} uppercase font-medium">
                                {{ $delivery->status }}
                            </span>
                        </td>
                        <td class="text-xs text-[var(--stage-muted)]">
                            {{ $delivery->attempts }}
                        </td>
                        <td class="text-xs text-[var(--stage-muted)] whitespace-nowrap">
                            {{ \App\Support\LocalTime::format($delivery->created_at, 'M j, Y H:i') }}
                        </td>
                        <td class="text-xs text-rose-400 max-w-xs truncate" title="{{ $delivery->last_error }}">
                            {{ $delivery->last_error ? Str::limit($delivery->last_error, 40) : '—' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-[var(--stage-muted)] py-8">
                            No email delivery ledger entries found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $deliveries->links() }}
    </div>
@endsection
