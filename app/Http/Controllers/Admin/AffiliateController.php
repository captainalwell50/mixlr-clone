<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AffiliateStatus;
use App\Http\Controllers\Controller;
use App\Models\AffiliateAccount;
use App\Models\Organization;
use App\Models\User;
use App\Services\AffiliateAttributionService;
use App\Services\AffiliateProgrammeSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AffiliateController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $affiliates = AffiliateAccount::query()
            ->with('user')
            ->withCount('attributions')
            ->when($q !== '', function ($query) use ($q): void {
                $query->where(function ($inner) use ($q): void {
                    $inner->where('referral_code', 'like', "%{$q}%")
                        ->orWhere('city', 'like', "%{$q}%")
                        ->orWhere('state_region', 'like', "%{$q}%")
                        ->orWhere('country_code', 'like', "%{$q}%")
                        ->orWhereHas('user', function ($users) use ($q): void {
                            $users->where('name', 'like', "%{$q}%")
                                ->orWhere('email', 'like', "%{$q}%");
                        });
                });
            })
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.affiliates.index', [
            'affiliates' => $affiliates,
            'q' => $q,
        ]);
    }

    public function create(): View
    {
        return view('admin.affiliates.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'status' => ['required', Rule::in([
                AffiliateStatus::Invited->value,
                AffiliateStatus::Pending->value,
                AffiliateStatus::Approved->value,
            ])],
            ...$this->locationRules(),
        ]);

        $user = User::query()->where('email', $validated['email'])->first();
        if ($user === null) {
            return back()->withErrors(['email' => __('No account uses that email.')])->withInput();
        }

        if (AffiliateAccount::query()->where('user_id', $user->id)->exists()) {
            return back()->withErrors([
                'email' => __('That person already has an affiliate account.'),
            ])->withInput();
        }

        if (! AffiliateProgrammeSettings::registrationIsOpen()) {
            return back()->withErrors([
                'email' => __('Affiliate registration is closed. People who are already approved stay approved.'),
            ])->withInput();
        }

        if (! AffiliateProgrammeSettings::canAcceptApplication()) {
            return back()->withErrors([
                'email' => __('The monthly limit for new affiliate applications has been reached.'),
            ])->withInput();
        }

        $status = AffiliateStatus::from($validated['status']);

        if ($status === AffiliateStatus::Approved && ! AffiliateProgrammeSettings::canApproveAnother()) {
            return back()->withErrors([
                'status' => __('The maximum number of approved affiliates has been reached. Existing approved affiliates are unchanged.'),
            ])->withInput();
        }

        $affiliate = DB::transaction(function () use ($user, $validated, $status): AffiliateAccount {
            return AffiliateAccount::query()->create([
                'user_id' => $user->id,
                'referral_code' => AffiliateAccount::generateReferralCode(),
                'status' => $status,
                'country_code' => strtoupper($validated['country_code']),
                'state_region' => trim($validated['state_region']),
                'city' => trim($validated['city']),
                'approved_at' => $status === AffiliateStatus::Approved ? now() : null,
            ]);
        });

        return redirect()
            ->route('admin.affiliates.show', $affiliate)
            ->with('status', __('Affiliate account created.'));
    }

    public function show(AffiliateAccount $affiliate, \App\Services\AffiliateCommissionService $commissionService): View
    {
        $affiliate->load('user');
        $affiliate->loadCount(['attributions', 'referralVisits', 'commissions', 'payouts']);

        $attributions = $affiliate->attributions()
            ->with('organization')
            ->latest('attributed_at')
            ->limit(20)
            ->get();

        $recentCommissions = $affiliate->commissions()
            ->with('organization')
            ->latest('id')
            ->limit(10)
            ->get();

        return view('admin.affiliates.show', [
            'affiliate' => $affiliate,
            'attributions' => $attributions,
            'earningCount' => $affiliate->attributions()
                ->whereIn('source', ['first_touch', 'prospect_reservation', 'admin'])
                ->whereNotNull('affiliate_account_id')
                ->count(),
            'blockedCount' => $affiliate->attributions()
                ->where('source', 'blocked_self_referral')
                ->count(),
            'balances' => $commissionService->deriveBalances($affiliate),
            'recentCommissions' => $recentCommissions,
            'level' => $affiliate->currentLevel(),
        ]);
    }

    public function updateLocation(Request $request, AffiliateAccount $affiliate): RedirectResponse
    {
        $validated = $request->validate($this->locationRules());

        $affiliate->update([
            'country_code' => strtoupper($validated['country_code']),
            'state_region' => trim($validated['state_region']),
            'city' => trim($validated['city']),
        ]);

        return back()->with('status', __('Affiliate location updated.'));
    }

    public function approve(AffiliateAccount $affiliate): RedirectResponse
    {
        if ($affiliate->status === AffiliateStatus::Approved) {
            return back()->with('status', __('This affiliate is already approved.'));
        }

        if ($affiliate->status === AffiliateStatus::Suspended) {
            return back()->with('error', __('Restore a suspended affiliate instead of approving them.'));
        }

        if (! in_array($affiliate->status, [AffiliateStatus::Invited, AffiliateStatus::Pending, AffiliateStatus::Rejected], true)) {
            return back()->with('error', __('This affiliate cannot be approved from its current status.'));
        }

        if (! AffiliateProgrammeSettings::canApproveAnother()) {
            return back()->with('error', __('The maximum number of approved affiliates has been reached. Existing approved affiliates are unchanged.'));
        }

        $affiliate->forceFill([
            'status' => AffiliateStatus::Approved,
            'approved_at' => $affiliate->approved_at ?? now(),
            'suspended_at' => null,
        ])->save();

        return back()->with('status', __('Affiliate approved. Their referral code can now attribute new visits.'));
    }

    public function reject(AffiliateAccount $affiliate): RedirectResponse
    {
        if ($affiliate->status === AffiliateStatus::Rejected) {
            return back()->with('status', __('This affiliate is already rejected.'));
        }

        if (in_array($affiliate->status, [AffiliateStatus::Approved, AffiliateStatus::Suspended], true)) {
            return back()->with('error', __('Suspend an approved affiliate. Reject is for an application that is not approved.'));
        }

        $affiliate->update([
            'status' => AffiliateStatus::Rejected,
        ]);

        return back()->with('status', __('Affiliate application rejected.'));
    }

    public function suspend(AffiliateAccount $affiliate): RedirectResponse
    {
        if ($affiliate->status !== AffiliateStatus::Approved) {
            return back()->with('error', __('Only an approved affiliate can be suspended.'));
        }

        $affiliate->forceFill([
            'status' => AffiliateStatus::Suspended,
            'suspended_at' => now(),
        ])->save();

        return back()->with('status', __('Affiliate suspended. New referral attribution is stopped. Existing history is unchanged.'));
    }

    public function restore(AffiliateAccount $affiliate): RedirectResponse
    {
        if ($affiliate->status !== AffiliateStatus::Suspended) {
            return back()->with('error', __('Only a suspended affiliate can be restored.'));
        }

        if (! AffiliateProgrammeSettings::canApproveAnother()) {
            return back()->with('error', __('The maximum number of approved affiliates has been reached. Existing approved affiliates are unchanged.'));
        }

        $affiliate->forceFill([
            'status' => AffiliateStatus::Approved,
            'suspended_at' => null,
            'approved_at' => $affiliate->approved_at ?? now(),
        ])->save();

        return back()->with('status', __('Affiliate restored. Their referral code can attribute new visits again.'));
    }

    public function updateOrganizationAttribution(
        Request $request,
        Organization $organization,
        AffiliateAttributionService $attribution,
    ): RedirectResponse {
        $validated = $request->validate([
            'affiliate_account_id' => ['required', 'integer', 'exists:affiliate_accounts,id'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $affiliate = AffiliateAccount::query()->findOrFail($validated['affiliate_account_id']);
        $actor = $request->user();
        abort_unless($actor !== null, 403);

        $record = $attribution->reassign($organization, $affiliate, $actor, $validated['reason']);

        $message = $record->isEarningEligible()
            ? __('Affiliate attribution updated.')
            : __('Attribution saved as a blocked self-referral. It is not earning-eligible.');

        return redirect()
            ->route('admin.organizations.edit', $organization)
            ->with('status', $message);
    }

    /**
     * @return array<string, list<string>>
     */
    private function locationRules(): array
    {
        return [
            'country_code' => ['required', 'string', 'size:2', 'regex:/^[A-Za-z]{2}$/'],
            'state_region' => ['required', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:120'],
        ];
    }
}
