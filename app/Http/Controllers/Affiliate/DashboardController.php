<?php

namespace App\Http\Controllers\Affiliate;

use App\Enums\AffiliateCommissionStatus;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\AffiliateCommissionService;
use App\Services\AffiliateProgrammeSettings;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, AffiliateCommissionService $service): View
    {
        $affiliate = $request->user()?->affiliateAccount;
        abort_unless($affiliate !== null && $affiliate->canRefer(), 403);

        $balances = $service->deriveBalances($affiliate);
        $tierProgress = $affiliate->tierProgress();

        // Authoritative referral analytics derived from attributions and commissions
        $attributedOrgIds = $affiliate->attributions()
            ->whereIn('source', ['first_touch', 'prospect_reservation', 'admin'])
            ->whereNotNull('organization_id')
            ->pluck('organization_id')
            ->unique();

        $totalReferredOrgsCount = $attributedOrgIds->count();

        // Paying referred organisations (distinct orgs with at least one non-reversed commission)
        $payingOrgIds = $affiliate->commissions()
            ->whereIn('status', [
                AffiliateCommissionStatus::Held->value,
                AffiliateCommissionStatus::Payable->value,
                AffiliateCommissionStatus::Paid->value,
            ])
            ->where('commission_amount_minor', '>', 0)
            ->whereNull('reversal_of_commission_id')
            ->whereDoesntHave('reversals')
            ->distinct('organization_id')
            ->pluck('organization_id');

        $payingOrgsCount = $payingOrgIds->count();

        // Active paying referred organisations (currently active subscription)
        $activePayingOrgsCount = Organization::query()
            ->whereIn('id', $payingOrgIds)
            ->whereHas('subscription', fn ($sub) => $sub->where('status', \App\Enums\SubscriptionStatus::Active->value))
            ->count();

        // First payment conversions (distinct initial payment commissions)
        $firstPaymentConversionsCount = $affiliate->commissions()
            ->where('is_renewal', false)
            ->where('commission_amount_minor', '>', 0)
            ->distinct('organization_id')
            ->count('organization_id');

        // Lifetime gross subscription volume attributed to affiliate
        $lifetimeReferredVolumeMinor = (int) $affiliate->commissions()
            ->whereIn('status', [
                AffiliateCommissionStatus::Held->value,
                AffiliateCommissionStatus::Payable->value,
                AffiliateCommissionStatus::Paid->value,
            ])
            ->whereNull('reversal_of_commission_id')
            ->sum('gross_amount_minor');

        // Recent 5 commissions
        $recentCommissions = $affiliate->commissions()
            ->with(['organization', 'payout'])
            ->latest('id')
            ->limit(5)
            ->get();

        // Recent 5 payouts
        $recentPayouts = $affiliate->payouts()
            ->latest('id')
            ->limit(5)
            ->get();

        // Pending payout requests
        $pendingPayoutsCount = $affiliate->payouts()
            ->whereIn('status', [\App\Enums\AffiliatePayoutStatus::Draft->value, \App\Enums\AffiliatePayoutStatus::Approved->value])
            ->count();

        $settings = AffiliateProgrammeSettings::all();
        $target = (int) ($settings['payout_qualification_target'] ?? 5);
        $defaultRatePercent = ((int) ($settings['default_commission_bps'] ?? 1000)) / 100;
        $showLevel = (bool) ($settings['show_affiliate_level'] ?? false);
        $showLevelProgress = (bool) ($settings['show_level_progress'] ?? false);

        return view('affiliate.dashboard', [
            'affiliate' => $affiliate,
            'balances' => $balances,
            'tierProgress' => $tierProgress,
            'qualifyingCount' => $balances['qualifying_orgs_count'],
            'target' => $target,
            'defaultRatePercent' => $defaultRatePercent,
            'showLevel' => $showLevel,
            'showLevelProgress' => $showLevelProgress,
            'analytics' => [
                'total_referred_orgs' => $totalReferredOrgsCount,
                'paying_referred_orgs' => $payingOrgsCount,
                'active_paying_orgs' => $activePayingOrgsCount,
                'first_payment_conversions' => $firstPaymentConversionsCount,
                'lifetime_volume_minor' => $lifetimeReferredVolumeMinor,
                'conversion_rate' => $totalReferredOrgsCount > 0
                    ? round(($payingOrgsCount / $totalReferredOrgsCount) * 100, 1)
                    : 0.0,
            ],
            'recentCommissions' => $recentCommissions,
            'recentPayouts' => $recentPayouts,
            'pendingPayoutsCount' => $pendingPayoutsCount,
            'totalCommissionsCount' => (int) $affiliate->commissions()->count(),
            'referralUrl' => url('/r/'.$affiliate->referral_code),
        ]);
    }
}
