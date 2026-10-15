<nav class="mb-6 flex flex-wrap gap-2 text-sm" aria-label="Finance navigation">
    @foreach ([
        'Overview' => 'admin.finance.index',
        'Payments' => 'admin.finance.payments',
        'Subscriptions' => 'admin.finance.subscriptions',
        'Invoices' => 'admin.finance.invoices',
        'Refunds' => 'admin.finance.refunds',
        'Affiliate Liabilities' => 'admin.finance.affiliate-liabilities',
        'Reports' => 'admin.finance.reports',
    ] as $label => $routeName)
        @php
            $current = request()->routeIs($routeName);
        @endphp
        <a href="{{ route($routeName) }}" class="{{ $current ? 'console-btn console-btn-primary' : 'console-btn console-btn-ghost' }}">{{ $label }}</a>
    @endforeach
</nav>
