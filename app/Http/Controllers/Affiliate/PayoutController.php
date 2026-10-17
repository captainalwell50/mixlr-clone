<?php

namespace App\Http\Controllers\Affiliate;

use App\Http\Controllers\Controller;
use App\Services\AffiliateCommissionService;
use App\Services\AffiliateProgrammeSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PayoutController extends Controller
{
    public function index(Request $request, AffiliateCommissionService $service): View
    {
        $affiliate = $request->user()?->affiliateAccount;
        abort_unless($affiliate !== null && $affiliate->canRefer(), 403);

        $balances = $service->deriveBalances($affiliate);
        $payouts = $affiliate->payouts()
            ->withCount('commissions')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $settings = AffiliateProgrammeSettings::all();
        $minPayoutMinor = (int) ($settings['minimum_payout_minor'] ?? 0);

        return view('affiliate.payouts.index', [
            'affiliate' => $affiliate,
            'balances' => $balances,
            'payouts' => $payouts,
            'minPayoutMinor' => $minPayoutMinor,
            'currency' => $balances['currency'],
        ]);
    }

    public function updateDestination(Request $request): RedirectResponse
    {
        $affiliate = $request->user()?->affiliateAccount;
        abort_unless($affiliate !== null && $affiliate->canRefer(), 403);

        $validated = $request->validate([
            'bank_name' => ['required', 'string', 'max:120'],
            'account_name' => ['required', 'string', 'max:150'],
            'account_number' => ['required', 'string', 'min:8', 'max:30'],
            'bank_code' => ['nullable', 'string', 'max:30'],
        ]);

        $affiliate->update([
            'bank_name' => trim($validated['bank_name']),
            'account_name' => trim($validated['account_name']),
            'account_number' => trim($validated['account_number']),
            'bank_code' => ! empty($validated['bank_code']) ? trim($validated['bank_code']) : null,
        ]);

        return redirect()
            ->route('affiliate.payouts.index')
            ->with('status', __('Payout destination saved securely.'));
    }

    public function requestPayout(Request $request, AffiliateCommissionService $service): RedirectResponse
    {
        $affiliate = $request->user()?->affiliateAccount;
        abort_unless($affiliate !== null && $affiliate->canRefer(), 403);

        $hasSaved = $affiliate->hasPayoutDestination();

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
            'bank_name' => [$hasSaved ? 'nullable' : 'required', 'string', 'max:120'],
            'bank_code' => ['nullable', 'string', 'max:30'],
            'account_name' => [$hasSaved ? 'nullable' : 'required', 'string', 'max:150'],
            'account_number' => [$hasSaved ? 'nullable' : 'required', 'string', 'min:8', 'max:30'],
        ]);

        if (! empty($validated['bank_name']) && ! empty($validated['account_number'])) {
            $affiliate->update([
                'bank_name' => trim($validated['bank_name']),
                'bank_code' => array_key_exists('bank_code', $validated) && ! empty($validated['bank_code'])
                    ? trim($validated['bank_code'])
                    : $affiliate->bank_code,
                'account_name' => trim($validated['account_name'] ?? $affiliate->account_name ?? ''),
                'account_number' => trim($validated['account_number']),
            ]);
        }

        if (! $affiliate->fresh()->hasPayoutDestination()) {
            throw ValidationException::withMessages([
                'bank_name' => 'A valid settlement bank destination is required before requesting a payout.',
            ]);
        }

        $payout = $service->requestPayout(
            affiliate: $affiliate->fresh(),
            currency: 'NGN',
            notes: $validated['notes'] ?? null,
        );

        return redirect()
            ->route('affiliate.payouts.index')
            ->with('status', __("Payout request {$payout->payout_reference} submitted successfully and is queued for administrator review."));
    }
}
