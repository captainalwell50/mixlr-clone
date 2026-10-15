<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AffiliateCommissionStatus;
use App\Enums\AffiliatePayoutStatus;
use App\Http\Controllers\Controller;
use App\Models\AffiliateCommission;
use App\Models\AffiliatePayout;
use App\Models\Plan;
use App\Services\PlatformFinanceService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminFinanceController extends Controller
{
    public function overview(Request $request, PlatformFinanceService $finance): View
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $range = (string) $request->query('range', '30d');
        $range = in_array($range, ['7d', '30d', '90d', '12m'], true) ? $range : '30d';

        $metrics = $finance->getOverviewMetrics();
        $subscriptionPerformance = $finance->getSubscriptionPerformance();
        $planDistribution = $finance->getPlanDistribution();
        $revenueTrend = $finance->getRevenueTrend($range);

        return view('admin.finance.index', [
            'metrics' => $metrics,
            'subscriptionPerformance' => $subscriptionPerformance,
            'planDistribution' => $planDistribution,
            'revenueTrend' => $revenueTrend,
            'activeRange' => $range,
        ]);
    }

    public function payments(Request $request, PlatformFinanceService $finance): View
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $filters = $request->only(['status', 'plan_id', 'organization_id', 'currency', 'search']);
        $payments = $finance->getPaymentsPaginated($filters, 15);
        $plans = Plan::query()->where('is_active', true)->orderBy('sort_order')->get();

        return view('admin.finance.payments', [
            'payments' => $payments,
            'plans' => $plans,
            'filters' => $filters,
        ]);
    }

    public function subscriptions(Request $request, PlatformFinanceService $finance): View
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $filters = $request->only(['status', 'plan_id', 'health', 'search']);
        $subscriptions = $finance->getSubscriptionsPaginated($filters, 15);
        $plans = Plan::query()->where('is_active', true)->orderBy('sort_order')->get();
        $performance = $finance->getSubscriptionPerformance();

        return view('admin.finance.subscriptions', [
            'subscriptions' => $subscriptions,
            'plans' => $plans,
            'performance' => $performance,
            'filters' => $filters,
        ]);
    }

    public function invoices(Request $request, PlatformFinanceService $finance): View
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $filters = $request->only(['status', 'organization_id', 'search']);
        $invoices = $finance->getInvoicesPaginated($filters, 15);

        return view('admin.finance.invoices', [
            'invoices' => $invoices,
            'filters' => $filters,
        ]);
    }

    public function refunds(Request $request, PlatformFinanceService $finance): View
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $filters = $request->only(['search']);
        $refunds = $finance->getRefundsPaginated($filters, 15);

        return view('admin.finance.refunds', [
            'refunds' => $refunds,
            'filters' => $filters,
        ]);
    }

    public function affiliateLiabilities(Request $request, PlatformFinanceService $finance): View
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $heldCommissions = AffiliateCommission::query()
            ->with(['affiliateAccount.user', 'organization'])
            ->where('status', AffiliateCommissionStatus::Held)
            ->latest('id')
            ->limit(20)
            ->get();

        $payableCommissions = AffiliateCommission::query()
            ->with(['affiliateAccount.user', 'organization'])
            ->where('status', AffiliateCommissionStatus::Payable)
            ->latest('id')
            ->limit(20)
            ->get();

        $recentPayouts = AffiliatePayout::query()
            ->with('affiliateAccount.user')
            ->latest('id')
            ->limit(15)
            ->get();

        $overview = $finance->getOverviewMetrics();

        return view('admin.finance.affiliate-liabilities', [
            'overview' => $overview,
            'heldCommissions' => $heldCommissions,
            'payableCommissions' => $payableCommissions,
            'recentPayouts' => $recentPayouts,
        ]);
    }

    public function reports(Request $request, PlatformFinanceService $finance): View
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $overview = $finance->getOverviewMetrics();
        $planDistribution = $finance->getPlanDistribution();
        $subscriptionPerformance = $finance->getSubscriptionPerformance();

        return view('admin.finance.reports', [
            'overview' => $overview,
            'planDistribution' => $planDistribution,
            'subscriptionPerformance' => $subscriptionPerformance,
        ]);
    }
}
