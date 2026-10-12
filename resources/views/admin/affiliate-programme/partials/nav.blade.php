<nav class="mb-6 flex flex-wrap gap-2 text-sm" aria-label="Affiliate programme">
    @foreach ([
        'Overview' => 'admin.affiliate-programme.overview',
        'Affiliates' => 'admin.affiliates.*',
        'Commissions' => 'admin.affiliate-commissions.*',
        'Payouts' => 'admin.affiliate-payouts.*',
        'Prospects' => 'admin.prospects.*',
        'Levels' => 'admin.affiliate-programme.levels',
        'Settings' => 'admin.affiliate-programme.settings',
        'Audit log' => 'admin.affiliate-programme.audit',
    ] as $label => $pattern)
        @php
            $href = match ($pattern) {
                'admin.affiliates.*' => route('admin.affiliates.index'),
                'admin.affiliate-commissions.*' => route('admin.affiliate-commissions.index'),
                'admin.affiliate-payouts.*' => route('admin.affiliate-payouts.index'),
                'admin.prospects.*' => route('admin.prospects.index'),
                default => route($pattern),
            };
            $current = request()->routeIs($pattern);
        @endphp
        <a href="{{ $href }}" class="{{ $current ? 'console-btn console-btn-primary' : 'console-btn console-btn-ghost' }}">{{ $label }}</a>
    @endforeach
</nav>
