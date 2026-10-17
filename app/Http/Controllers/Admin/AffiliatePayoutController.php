<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AffiliateCommissionStatus;
use App\Enums\AffiliatePayoutStatus;
use App\Http\Controllers\Controller;
use App\Models\AffiliateAccount;
use App\Models\AffiliateCommission;
use App\Models\AffiliatePayout;
use App\Services\AffiliateCommissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AffiliatePayoutController extends Controller
{
    public function index(Request $request, AffiliateCommissionService $service): View
    {
        $status = $request->query('status');

        $payouts = AffiliatePayout::query()
            ->with(['affiliateAccount.user', 'admin', 'paidBy'])
            ->when($status && AffiliatePayoutStatus::tryFrom((string) $status), function ($b) use ($status): void {
                $b->where('status', $status);
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        // Qualified affiliates with payable commissions available
        $payableAffiliateIds = AffiliateCommission::query()
            ->where('status', AffiliateCommissionStatus::Payable->value)
            ->whereNull('affiliate_payout_id')
            ->distinct('affiliate_account_id')
            ->pluck('affiliate_account_id');

        $payableAffiliates = AffiliateAccount::query()
            ->whereIn('id', $payableAffiliateIds)
            ->whereNotNull('payout_qualified_at')
            ->with('user')
            ->get()
            ->map(function ($affiliate) use ($service) {
                return [
                    'affiliate' => $affiliate,
                    'balances' => $service->deriveBalances($affiliate),
                ];
            });

        return view('admin.affiliate-payouts.index', [
            'payouts' => $payouts,
            'status' => $status,
            'statuses' => AffiliatePayoutStatus::cases(),
            'payableAffiliates' => $payableAffiliates,
        ]);
    }

    public function create(Request $request, AffiliateCommissionService $service): View
    {
        $selectedId = $request->query('affiliate_id');
        $selectedAffiliate = $selectedId ? AffiliateAccount::query()->with('user')->find($selectedId) : null;

        $payableAffiliateIds = AffiliateCommission::query()
            ->where('status', AffiliateCommissionStatus::Payable->value)
            ->whereNull('affiliate_payout_id')
            ->distinct('affiliate_account_id')
            ->pluck('affiliate_account_id');

        $eligibleAffiliates = AffiliateAccount::query()
            ->whereIn('id', $payableAffiliateIds)
            ->whereNotNull('payout_qualified_at')
            ->with('user')
            ->get();

        $payableCommissions = collect();
        if ($selectedAffiliate !== null) {
            $payableCommissions = AffiliateCommission::query()
                ->where('affiliate_account_id', $selectedAffiliate->id)
                ->where('status', AffiliateCommissionStatus::Payable->value)
                ->whereNull('affiliate_payout_id')
                ->with('organization')
                ->get();
        }

        return view('admin.affiliate-payouts.create', [
            'eligibleAffiliates' => $eligibleAffiliates,
            'selectedAffiliate' => $selectedAffiliate,
            'payableCommissions' => $payableCommissions,
            'balances' => $selectedAffiliate ? $service->deriveBalances($selectedAffiliate) : null,
        ]);
    }

    public function store(Request $request, AffiliateCommissionService $service): RedirectResponse
    {
        $validated = $request->validate([
            'affiliate_account_id' => ['required', 'exists:affiliate_accounts,id'],
            'currency' => ['required', 'string', 'size:3'],
            'commission_ids' => ['nullable', 'array'],
            'commission_ids.*' => ['integer', 'exists:affiliate_commissions,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $affiliate = AffiliateAccount::query()->findOrFail($validated['affiliate_account_id']);
        $actor = $request->user();
        abort_unless($actor !== null, 403);

        $payout = $service->createPayout(
            affiliate: $affiliate,
            currency: $validated['currency'],
            commissionIds: $validated['commission_ids'] ?? null,
            actor: $actor,
            notes: $validated['notes'] ?? null,
        );

        return redirect()
            ->route('admin.affiliate-payouts.show', $payout)
            ->with('status', __("Payout draft {$payout->payout_reference} created successfully."));
    }

    public function show(AffiliatePayout $payout): View
    {
        $payout->load([
            'affiliateAccount.user',
            'admin',
            'createdBy',
            'approvedBy',
            'paidBy',
            'cancelledBy',
            'items.commission.organization',
        ]);

        return view('admin.affiliate-payouts.show', [
            'payout' => $payout,
        ]);
    }

    public function approve(
        Request $request,
        AffiliatePayout $payout,
        AffiliateCommissionService $service,
    ): RedirectResponse {
        $actor = $request->user();
        abort_unless($actor !== null, 403);

        $service->approvePayout($payout, $actor);

        return back()->with('status', __('Payout batch approved.'));
    }

    public function markPaid(
        Request $request,
        AffiliatePayout $payout,
        AffiliateCommissionService $service,
    ): RedirectResponse {
        $actor = $request->user();
        abort_unless($actor !== null, 403);

        $notes = $request->input('notes');
        $service->markPayoutPaid($payout, $actor, is_string($notes) ? $notes : null);

        return back()->with('status', __('Payout batch marked as paid and commissions finalized.'));
    }

    public function cancel(
        Request $request,
        AffiliatePayout $payout,
        AffiliateCommissionService $service,
    ): RedirectResponse {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $actor = $request->user();
        abort_unless($actor !== null, 403);

        $service->cancelPayout($payout, $actor, $validated['reason']);

        return back()->with('status', __('Payout batch cancelled and commissions released back to payable.'));
    }

    public function batchStore(Request $request, AffiliateCommissionService $service): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor !== null, 403);

        $affiliateIds = $request->input('affiliate_ids', []);

        $query = AffiliateAccount::query()
            ->whereNotNull('payout_qualified_at');

        if (! empty($affiliateIds) && is_array($affiliateIds)) {
            $query->whereIn('id', $affiliateIds);
        } else {
            $payableAffiliateIds = AffiliateCommission::query()
                ->where('status', AffiliateCommissionStatus::Payable->value)
                ->whereNull('affiliate_payout_id')
                ->distinct('affiliate_account_id')
                ->pluck('affiliate_account_id');
            $query->whereIn('id', $payableAffiliateIds);
        }

        $affiliates = $query->get();
        $createdCount = 0;

        foreach ($affiliates as $affiliate) {
            try {
                $service->createPayout(
                    affiliate: $affiliate,
                    currency: 'NGN',
                    actor: $actor,
                    notes: 'Batch-generated payout draft',
                );
                $createdCount++;
            } catch (\Exception $e) {
                continue;
            }
        }

        return redirect()
            ->route('admin.affiliate-payouts.index')
            ->with('status', __("Batch generated {$createdCount} payout draft(s)."));
    }

    public function batchApprove(Request $request, AffiliateCommissionService $service): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor !== null, 403);

        $payoutIds = $request->validate([
            'payout_ids' => ['required', 'array'],
            'payout_ids.*' => ['integer', 'exists:affiliate_payouts,id'],
        ])['payout_ids'];

        $payouts = AffiliatePayout::query()
            ->whereIn('id', $payoutIds)
            ->where('status', AffiliatePayoutStatus::Draft)
            ->get();

        $approvedCount = 0;
        foreach ($payouts as $payout) {
            $service->approvePayout($payout, $actor);
            $approvedCount++;
        }

        return redirect()
            ->route('admin.affiliate-payouts.index')
            ->with('status', __("Successfully approved {$approvedCount} payout batch(es)."));
    }

    public function batchMarkPaid(Request $request, AffiliateCommissionService $service): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor !== null, 403);

        $validated = $request->validate([
            'payout_ids' => ['required', 'array'],
            'payout_ids.*' => ['integer', 'exists:affiliate_payouts,id'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
        ]);

        $payouts = AffiliatePayout::query()
            ->whereIn('id', $validated['payout_ids'])
            ->whereIn('status', [AffiliatePayoutStatus::Draft, AffiliatePayoutStatus::Approved])
            ->get();

        $paidCount = 0;
        foreach ($payouts as $payout) {
            $service->markPayoutPaid($payout, $actor, $validated['payment_reference'] ?? 'Batch manual settlement');
            $paidCount++;
        }

        return redirect()
            ->route('admin.affiliate-payouts.index')
            ->with('status', __("Successfully marked {$paidCount} payout batch(es) as paid."));
    }
}

