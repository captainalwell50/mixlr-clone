<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AffiliateCommissionStatus;
use App\Http\Controllers\Controller;
use App\Models\AffiliateAccount;
use App\Models\AffiliateCommission;
use App\Services\AffiliateCommissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AffiliateCommissionController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status');
        $affiliateId = $request->query('affiliate_id');
        $q = trim((string) $request->query('q', ''));
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $query = AffiliateCommission::query()
            ->with(['affiliateAccount.user', 'organization', 'payout'])
            ->when($status && AffiliateCommissionStatus::tryFrom((string) $status), function ($b, $s) use ($status): void {
                $b->where('status', $status);
            })
            ->when($affiliateId, function ($b, $id): void {
                $b->where('affiliate_account_id', $id);
            })
            ->when($q !== '', function ($b) use ($q): void {
                $b->where(function ($inner) use ($q): void {
                    $inner->where('payment_reference', 'like', "%{$q}%")
                        ->orWhere('provider_reference', 'like', "%{$q}%")
                        ->orWhereHas('organization', fn ($org) => $org->where('name', 'like', "%{$q}%"))
                        ->orWhereHas('affiliateAccount.user', fn ($u) => $u->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%"));
                });
            })
            ->when($dateFrom, fn ($b, $df) => $b->whereDate('confirmed_at', '>=', $df))
            ->when($dateTo, fn ($b, $dt) => $b->whereDate('confirmed_at', '<=', $dt))
            ->latest('id');

        $commissions = $query->paginate(25)->withQueryString();

        $selectedAffiliate = $affiliateId ? AffiliateAccount::query()->with('user')->find($affiliateId) : null;

        return view('admin.affiliate-commissions.index', [
            'commissions' => $commissions,
            'status' => $status,
            'selectedAffiliate' => $selectedAffiliate,
            'q' => $q,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'statuses' => AffiliateCommissionStatus::cases(),
        ]);
    }

    public function reverse(
        Request $request,
        AffiliateCommission $commission,
        AffiliateCommissionService $service,
    ): RedirectResponse {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $actor = $request->user();
        abort_unless($actor !== null, 403);

        $service->reverseCommission($commission, $validated['reason'], $actor->id);

        return back()->with('status', __('Commission reversed successfully.'));
    }
}
