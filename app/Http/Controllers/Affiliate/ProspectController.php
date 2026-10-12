<?php

namespace App\Http\Controllers\Affiliate;

use App\Enums\AffiliateOutreachMethod;
use App\Enums\AffiliateProspectStatus;
use App\Http\Controllers\Controller;
use App\Models\AffiliateProspect;
use App\Services\AffiliateProgrammeSettings;
use App\Services\AffiliateProspectService;
use App\Support\LocalTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProspectController extends Controller
{
    public function index(Request $request): View
    {
        $affiliate = $request->user()->affiliateAccount;
        $filter = (string) $request->query('filter', 'active');
        $filters = ['active', 'follow_up', 'interested', 'converted', 'expired', 'cancelled'];
        if (! in_array($filter, $filters, true)) {
            $filter = 'active';
        }

        $prospects = AffiliateProspect::query()
            ->where('affiliate_account_id', $affiliate->id)
            ->when($filter === 'active', fn ($query) => $query->holdingReservation())
            ->when($filter === 'follow_up', fn ($query) => $query->where('status', AffiliateProspectStatus::FollowUp->value)->where('reserved_until', '>', now()))
            ->when($filter === 'interested', fn ($query) => $query->where('status', AffiliateProspectStatus::Interested->value)->where('reserved_until', '>', now()))
            ->when($filter === 'converted', fn ($query) => $query->where('status', AffiliateProspectStatus::Converted->value))
            ->when($filter === 'cancelled', fn ($query) => $query->where('status', AffiliateProspectStatus::Cancelled->value))
            ->when($filter === 'expired', function ($query): void {
                $query->where(function ($inner): void {
                    $inner->where('status', AffiliateProspectStatus::Expired->value)
                        ->orWhere(function ($stale): void {
                            $stale->whereIn('status', AffiliateProspectStatus::reservationBearingValues())
                                ->where('reserved_until', '<=', now());
                        });
                });
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $base = AffiliateProspect::query()->where('affiliate_account_id', $affiliate->id);
        $cancelled = (clone $base)->where('status', AffiliateProspectStatus::Cancelled->value)->count();
        $total = (clone $base)->count();
        $converted = (clone $base)->where('status', AffiliateProspectStatus::Converted->value)->count();
        $denominator = $total - $cancelled;

        return view('affiliate.prospects.index', [
            'showProspectStatistics' => AffiliateProgrammeSettings::shows('show_prospect_statistics'),
            'showConversionRate' => AffiliateProgrammeSettings::shows('show_conversion_rate'),
            'prospects' => $prospects,
            'filter' => $filter,
            'metrics' => [
                'active' => (clone $base)->holdingReservation()->count(),
                'visits' => (clone $base)->holdingReservation()->where('outreach_method', AffiliateOutreachMethod::Visit->value)->count(),
                'emails' => (clone $base)->holdingReservation()->where('outreach_method', AffiliateOutreachMethod::DirectEmail->value)->count(),
                'calls' => (clone $base)->holdingReservation()->where('outreach_method', AffiliateOutreachMethod::PhoneCall->value)->count(),
                'converted' => $converted,
                'expired' => (clone $base)->where(function ($query): void {
                    $query->where('status', AffiliateProspectStatus::Expired->value)
                        ->orWhere(function ($stale): void {
                            $stale->whereIn('status', AffiliateProspectStatus::reservationBearingValues())
                                ->where('reserved_until', '<=', now());
                        });
                })->count(),
                'rate' => $denominator > 0 ? (int) round(($converted / $denominator) * 100) : null,
            ],
        ]);
    }

    public function create(): View
    {
        return view('affiliate.prospects.create', [
            'methods' => array_values(array_filter(
                AffiliateOutreachMethod::cases(),
                fn (AffiliateOutreachMethod $method): bool => AffiliateProgrammeSettings::outreachEnabled($method),
            )),
        ]);
    }

    public function search(Request $request, AffiliateProspectService $prospects): JsonResponse
    {
        $validated = $request->validate([
            'church_name' => ['nullable', 'string', 'max:255'],
            'country_code' => ['nullable', 'string', 'max:2'],
            'state_region' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json($prospects->search($request->user()->affiliateAccount, $validated));
    }

    public function store(Request $request, AffiliateProspectService $prospects): RedirectResponse
    {
        $validated = $request->validate($this->planRules());
        $prospect = $prospects->create($request->user()->affiliateAccount, $validated);

        return redirect()
            ->route('affiliate.prospects.show', $prospect)
            ->with('status', __('Church plan saved. It is reserved until :date.', [
                'date' => LocalTime::format($prospect->reserved_until, 'M j, Y'),
            ]));
    }

    public function show(Request $request, AffiliateProspect $prospect): View
    {
        $this->owns($request, $prospect);
        $prospect->load('convertedOrganization');

        $next = array_values(array_filter(
            AffiliateProspectStatus::cases(),
            fn (AffiliateProspectStatus $status): bool => $prospect->status->canMoveTo($status),
        ));

        return view('affiliate.prospects.show', [
            'prospect' => $prospect,
            'nextStatuses' => $next,
        ]);
    }

    public function updateStatus(Request $request, AffiliateProspect $prospect, AffiliateProspectService $prospects): RedirectResponse
    {
        $this->owns($request, $prospect);
        $validated = $request->validate([
            'status' => ['required', Rule::enum(AffiliateProspectStatus::class)],
        ]);

        $prospects->transition(
            $prospect,
            $request->user()->affiliateAccount,
            AffiliateProspectStatus::from($validated['status']),
        );

        return back()->with('status', __('Plan updated.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function planRules(): array
    {
        return [
            'church_name' => ['required', 'string', 'max:255'],
            'country_code' => ['required', 'string', 'size:2', 'regex:/^[A-Za-z]{2}$/'],
            'state_region' => ['required', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:120'],
            'outreach_method' => ['required', Rule::enum(AffiliateOutreachMethod::class)],
            'planned_for' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_role' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:'.AffiliateProgrammeSettings::noteMaxLength()],
            'different_church' => ['sometimes', 'boolean'],
        ];
    }

    private function owns(Request $request, AffiliateProspect $prospect): void
    {
        abort_unless($prospect->affiliate_account_id === $request->user()->affiliateAccount?->id, 403);
    }
}
