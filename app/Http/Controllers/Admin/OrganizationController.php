<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AffiliateStatus;
use App\Enums\OrgRole;
use App\Http\Controllers\Controller;
use App\Models\AffiliateAccount;
use App\Models\AffiliateAttributionChange;
use App\Models\Organization;
use App\Models\Plan;
use App\Services\AffiliateAttributionService;
use App\Services\SubscriptionAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $organizations = $user->isAdmin()
            ? Organization::query()->with('subscription.plan')->orderBy('name')->paginate(20)
            : $user->organizations()->with('subscription.plan')->orderBy('name')->paginate(20);

        return view('admin.organizations.index', [
            'organizations' => $organizations,
            'plans' => $user->isAdmin()
                ? Plan::query()->orderBy('sort_order')->orderBy('id')->get()
                : collect(),
        ]);
    }

    public function create(): View
    {
        abort_unless(request()->user()?->isAdmin(), 403);

        return view('admin.organizations.create');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:organizations,slug'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'theme_color' => ['nullable', 'string', 'max:32'],
            'support_url' => ['nullable', 'url', 'max:500'],
            'is_public' => ['sometimes', 'boolean'],
        ]);

        $slug = $validated['slug'] ?? Str::slug($validated['name']);
        $slug = Organization::query()->where('slug', $slug)->exists()
            ? $slug.'-'.Str::lower(Str::random(4))
            : $slug;

        $organization = DB::transaction(function () use ($request, $validated, $slug): Organization {
            $organization = Organization::query()->create([
                'name' => $validated['name'],
                'slug' => $slug,
                'tagline' => $validated['tagline'] ?? null,
                'theme_color' => $validated['theme_color'] ?? '#3d9b7a',
                'support_url' => $validated['support_url'] ?? null,
                'is_public' => $request->boolean('is_public', true),
            ]);

            $organization->users()->attach($request->user()->id, [
                'role' => OrgRole::Owner->value,
            ]);

            app(AffiliateAttributionService::class)->attachNewOrganization($organization, $request);

            return $organization;
        });

        return redirect()->route('admin.organizations.edit', $organization)
            ->with('status', __('Channel created.'));
    }

    public function edit(Request $request, Organization $organization): View
    {
        abort_unless($request->user()?->canManageOrganization($organization), 403);

        $organization->load('subscription.plan');
        $isPlatformAdmin = (bool) $request->user()?->isAdmin();

        return view('admin.organizations.edit', [
            'organization' => $organization,
            'plans' => $isPlatformAdmin
                ? Plan::query()->orderBy('sort_order')->orderBy('id')->get()
                : collect(),
            'attribution' => $isPlatformAdmin
                ? $organization->affiliateAttribution()->with('affiliate.user')->first()
                : null,
            'affiliates' => $isPlatformAdmin
                ? AffiliateAccount::query()->with('user')->where('status', AffiliateStatus::Approved->value)->orderBy('referral_code')->get()
                : collect(),
            'attributionChanges' => $isPlatformAdmin
                ? AffiliateAttributionChange::query()
                    ->with(['previousAffiliate.user', 'newAffiliate.user', 'changedBy'])
                    ->where('organization_id', $organization->id)
                    ->latest()
                    ->limit(20)
                    ->get()
                : collect(),
        ]);
    }

    public function update(Request $request, Organization $organization): RedirectResponse
    {
        abort_unless($request->user()?->canManageOrganization($organization), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:organizations,slug,'.$organization->id],
            'tagline' => ['nullable', 'string', 'max:255'],
            'theme_color' => ['nullable', 'string', 'max:32'],
            'support_url' => ['nullable', 'url', 'max:500'],
            'social_feed_url' => ['nullable', 'url', 'max:500'],
            'is_public' => ['sometimes', 'boolean'],
        ]);

        $organization->update([
            ...$validated,
            'is_public' => $request->boolean('is_public'),
            'branding_config' => array_merge($organization->branding_config ?? [], [
                'accent' => $validated['theme_color'] ?? $organization->theme_color,
            ]),
        ]);

        return redirect()->route('admin.organizations.edit', $organization)
            ->with('status', __('Channel updated.'));
    }

    public function assignPlan(
        Request $request,
        Organization $organization,
        SubscriptionAssignmentService $assignment,
    ): RedirectResponse {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
        ]);

        $plan = Plan::query()->findOrFail($validated['plan_id']);
        $assignment->assign($organization, $plan, resetFreeWindow: $plan->isFree());

        $organization->unsetRelation('subscription');

        return back()->with('status', __('Assigned :plan to :channel.', [
            'plan' => $plan->name,
            'channel' => $organization->name,
        ]));
    }
}
