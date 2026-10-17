<div class="mb-6 border-b border-[var(--stage-border)]">
    <nav class="flex space-x-2 sm:space-x-4 overflow-x-auto py-1 scrollbar-none" aria-label="Affiliate Partner Navigation">
        <a
            href="{{ route('affiliate.dashboard') }}"
            class="px-3 py-2 text-xs font-semibold rounded-lg transition whitespace-nowrap {{ request()->routeIs('affiliate.dashboard') ? 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30' : 'text-[var(--stage-muted)] hover:text-[var(--stage-cream)] hover:bg-[var(--stage-panel)]' }}"
        >
            Partner Dashboard
        </a>
        <a
            href="{{ route('affiliate.commissions.index') }}"
            class="px-3 py-2 text-xs font-semibold rounded-lg transition whitespace-nowrap {{ request()->routeIs('affiliate.commissions.*') ? 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30' : 'text-[var(--stage-muted)] hover:text-[var(--stage-cream)] hover:bg-[var(--stage-panel)]' }}"
        >
            Earnings Ledger
        </a>
        <a
            href="{{ route('affiliate.payouts.index') }}"
            class="px-3 py-2 text-xs font-semibold rounded-lg transition whitespace-nowrap {{ request()->routeIs('affiliate.payouts.*') ? 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30' : 'text-[var(--stage-muted)] hover:text-[var(--stage-cream)] hover:bg-[var(--stage-panel)]' }}"
        >
            Payouts
        </a>
        <a
            href="{{ route('affiliate.prospects.index') }}"
            class="px-3 py-2 text-xs font-semibold rounded-lg transition whitespace-nowrap {{ request()->routeIs('affiliate.prospects.*') ? 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30' : 'text-[var(--stage-muted)] hover:text-[var(--stage-cream)] hover:bg-[var(--stage-panel)]' }}"
        >
            Church Prospects
        </a>
    </nav>
</div>
