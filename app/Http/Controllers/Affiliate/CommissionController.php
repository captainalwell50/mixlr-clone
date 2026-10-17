<?php

namespace App\Http\Controllers\Affiliate;

use App\Enums\AffiliateCommissionStatus;
use App\Http\Controllers\Controller;
use App\Services\AffiliateCommissionService;
use App\Services\AffiliateProgrammeSettings;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommissionController extends Controller
{
    public function statement(Request $request, AffiliateCommissionService $service): View
    {
        $affiliate = $request->user()?->affiliateAccount;
        abort_unless($affiliate !== null && $affiliate->canRefer(), 403);

        $status = $request->query('status');
        $validStatus = $status && AffiliateCommissionStatus::tryFrom((string) $status) ? $status : null;

        $balances = $service->deriveBalances($affiliate);
        $commissions = $affiliate->commissions()
            ->with(['organization', 'payout'])
            ->when($validStatus, fn ($q) => $q->where('status', $validStatus))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('affiliate.commissions.index', [
            'affiliate' => $affiliate,
            'balances' => $balances,
            'commissions' => $commissions,
            'selectedStatus' => $validStatus,
            'statuses' => AffiliateCommissionStatus::cases(),
            'tierProgress' => $affiliate->tierProgress(),
            'level' => $affiliate->currentLevel(),
            'target' => (int) (AffiliateProgrammeSettings::all()['payout_qualification_target'] ?? 5),
            'defaultRatePercent' => ((int) (AffiliateProgrammeSettings::all()['default_commission_bps'] ?? 1000)) / 100,
        ]);
    }
}

