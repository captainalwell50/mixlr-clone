<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AffiliateCommissionStatus;
use App\Enums\AffiliatePayoutStatus;
use App\Enums\AffiliateStatus;
use App\Http\Controllers\Controller;
use App\Models\AffiliateAccount;
use App\Models\AffiliateAttribution;
use App\Models\AffiliateCommission;
use App\Models\AffiliatePayout;
use App\Services\AffiliateCommissionService;
use App\Services\AffiliateProgrammeSettings;
use App\Support\AffiliateQualificationPolicy;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AffiliateReportController extends Controller
{
    public function index(Request $request, AffiliateCommissionService $service): View
    {
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        // 1. High-level programme stats
        $totalAffiliates = AffiliateAccount::query()->count();
        $approvedAffiliates = AffiliateAccount::query()->where('status', AffiliateStatus::Approved)->count();
        $qualifiedAffiliates = AffiliateAccount::query()->whereNotNull('payout_qualified_at')->count();
        $qualificationRate = $approvedAffiliates > 0
            ? round(($qualifiedAffiliates / $approvedAffiliates) * 100, 1)
            : 0;

        // 2. Monthly movement (Authoritative)
        $commissionsCreatedThisMonth = AffiliateCommission::query()
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->whereNotIn('status', [AffiliateCommissionStatus::Voided, AffiliateCommissionStatus::Reversed])
            ->selectRaw('COUNT(*) as total_count, COALESCE(SUM(commission_amount_minor), 0) as total_commission, COALESCE(SUM(gross_amount_minor), 0) as total_gross')
            ->first();

        $commissionsPayableThisMonth = AffiliateCommission::query()
            ->where('status', AffiliateCommissionStatus::Payable)
            ->whereBetween('matured_at', [$startOfMonth, $endOfMonth])
            ->selectRaw('COUNT(*) as total_count, COALESCE(SUM(commission_amount_minor), 0) as total_amount')
            ->first();

        $payoutsPaidThisMonth = AffiliatePayout::query()
            ->where('status', AffiliatePayoutStatus::Paid)
            ->whereBetween('paid_at', [$startOfMonth, $endOfMonth])
            ->selectRaw('COUNT(*) as total_count, COALESCE(SUM(amount_minor), 0) as total_amount')
            ->first();

        // 3. Platform Liabilities
        $heldLiability = (int) AffiliateCommission::query()
            ->where('status', AffiliateCommissionStatus::Held)
            ->sum('commission_amount_minor');

        $payableLiability = (int) AffiliateCommission::query()
            ->where('status', AffiliateCommissionStatus::Payable)
            ->whereNull('affiliate_payout_id')
            ->sum('commission_amount_minor');

        $lifetimeSettled = (int) AffiliateCommission::query()
            ->where('status', AffiliateCommissionStatus::Paid)
            ->sum('commission_amount_minor');

        // 4. Attribution & Conversion Funnel
        $totalAttributions = AffiliateAttribution::query()->count();
        $earningAttributions = AffiliateAttribution::query()
            ->whereIn('source', ['first_touch', 'prospect_reservation', 'admin'])
            ->whereNotNull('affiliate_account_id')
            ->count();

        // Distinct paying referred organisations
        $target = AffiliateQualificationPolicy::requiredDistinctPayingOrganisations();
        $distinctPayingOrgs = AffiliateCommission::query()
            ->whereIn('status', [
                AffiliateCommissionStatus::Held,
                AffiliateCommissionStatus::Payable,
                AffiliateCommissionStatus::Paid,
            ])
            ->distinct('organization_id')
            ->count('organization_id');

        // 5. Top Affiliates by Revenue & Paying Orgs
        $topAffiliates = AffiliateAccount::query()
            ->with(['user'])
            ->withCount([
                'commissions as confirmed_commissions_count' => function ($q) {
                    $q->whereIn('status', [
                        AffiliateCommissionStatus::Held,
                        AffiliateCommissionStatus::Payable,
                        AffiliateCommissionStatus::Paid,
                    ]);
                },
            ])
            ->get()
            ->map(function (AffiliateAccount $affiliate) use ($service) {
                $balances = $service->deriveBalances($affiliate);
                $qualifyingOrgs = $affiliate->qualifyingOrganizationsCount();
                $level = $affiliate->currentLevel();

                return [
                    'affiliate' => $affiliate,
                    'user' => $affiliate->user,
                    'qualifying_orgs' => $qualifyingOrgs,
                    'is_qualified' => $affiliate->isQualified(),
                    'tier' => $level['name'] ?? 'Starter',
                    'payable_minor' => $balances['payable_minor'],
                    'held_minor' => $balances['held_minor'],
                    'paid_minor' => $balances['paid_minor'],
                    'lifetime_earned_minor' => $balances['paid_minor'] + $balances['payable_minor'] + $balances['held_minor'],
                ];
            })
            ->sortByDesc('lifetime_earned_minor')
            ->take(10)
            ->values();

        return view('admin.affiliate-programme.reports', [
            'totalAffiliates' => $totalAffiliates,
            'approvedAffiliates' => $approvedAffiliates,
            'qualifiedAffiliates' => $qualifiedAffiliates,
            'qualificationRate' => $qualificationRate,
            'commissionsCreatedThisMonth' => $commissionsCreatedThisMonth,
            'commissionsPayableThisMonth' => $commissionsPayableThisMonth,
            'payoutsPaidThisMonth' => $payoutsPaidThisMonth,
            'heldLiability' => $heldLiability,
            'payableLiability' => $payableLiability,
            'lifetimeSettled' => $lifetimeSettled,
            'totalAttributions' => $totalAttributions,
            'earningAttributions' => $earningAttributions,
            'distinctPayingOrgs' => $distinctPayingOrgs,
            'target' => $target,
            'topAffiliates' => $topAffiliates,
            'currency' => 'NGN',
        ]);
    }
}
