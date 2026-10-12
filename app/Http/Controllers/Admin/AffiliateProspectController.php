<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AffiliateProspect;
use App\Services\AffiliateProgrammeSettings;
use App\Services\AffiliateProspectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AffiliateProspectController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $prospects = AffiliateProspect::query()
            ->with('affiliateAccount.user')
            ->when($q !== '', function ($query) use ($q): void {
                $query->where(function ($inner) use ($q): void {
                    $inner->where('church_name', 'like', "%{$q}%")
                        ->orWhere('city', 'like', "%{$q}%")
                        ->orWhere('state_region', 'like', "%{$q}%")
                        ->orWhereHas('affiliateAccount.user', function ($users) use ($q): void {
                            $users->where('name', 'like', "%{$q}%")
                                ->orWhere('email', 'like', "%{$q}%");
                        });
                });
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.prospects.index', [
            'prospects' => $prospects,
            'q' => $q,
        ]);
    }

    public function show(AffiliateProspect $prospect): View
    {
        $prospect->load([
            'affiliateAccount.user',
            'convertedOrganization',
            'audits.actor',
        ]);

        $duplicates = AffiliateProspect::query()
            ->with('affiliateAccount.user')
            ->where('country_code', $prospect->country_code)
            ->whereRaw('lower(city) = ?', [mb_strtolower($prospect->city)])
            ->where('id', '!=', $prospect->id)
            ->where('normalized_church_name', $prospect->normalized_church_name)
            ->latest('id')
            ->limit(20)
            ->get();

        return view('admin.prospects.show', [
            'prospect' => $prospect,
            'duplicates' => $duplicates,
        ]);
    }

    public function release(Request $request, AffiliateProspect $prospect, AffiliateProspectService $prospects): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => AffiliateProgrammeSettings::releaseReasonRequired()
                ? ['required', 'string', 'min:3', 'max:500']
                : ['nullable', 'string', 'max:500'],
        ]);

        $actor = $request->user();
        abort_unless($actor !== null, 403);
        $prospects->release($prospect, $actor, $validated['reason']);

        return redirect()
            ->route('admin.prospects.show', $prospect)
            ->with('status', __('Reservation released.'));
    }
}
