<?php

namespace App\Http\Controllers;

use App\Enums\CreatorType;
use App\Enums\OrgRole;
use App\Enums\StreamStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Organization;
use App\Models\Stream;
use App\Models\Subscription;
use App\Notifications\FreePlanStartedNotification;
use App\Services\AffiliateAttributionService;
use App\Services\FreePeriodService;
use App\Support\ChurchIdentity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        if ($user->isAdmin()) {
            return redirect()->route('admin.streams.index');
        }

        if ($user->organizations()->exists()) {
            return redirect()->route('creator.home');
        }

        return view('onboarding.show', [
            'types' => CreatorType::cases(),
            'selectedType' => old('creator_type', session('onboarding.creator_type')),
        ]);
    }

    public function storeType(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'creator_type' => ['required', Rule::enum(CreatorType::class)],
        ]);

        session(['onboarding.creator_type' => $validated['creator_type']]);

        return redirect()->route('onboarding.channel');
    }

    public function channel(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        if ($user->organizations()->exists()) {
            return redirect()->route('creator.home');
        }

        $type = session('onboarding.creator_type');
        if ($type === null) {
            return redirect()->route('onboarding.show');
        }

        return view('onboarding.channel', [
            'creatorType' => CreatorType::from($type),
        ]);
    }

    public function storeChannel(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        if ($user->organizations()->wherePivot('role', OrgRole::Owner->value)->exists()) {
            return redirect()->route('creator.home')
                ->with('error', __('You already own a channel. Invite teammates from channel settings.'));
        }

        $type = session('onboarding.creator_type');
        if ($type === null) {
            return redirect()->route('onboarding.show');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', 'unique:organizations,slug'],
            'theme_color' => ['nullable', 'string', 'max:32'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'country_code' => ['nullable', 'string', 'max:2'],
            'state_region' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
        ]);

        $country = ChurchIdentity::normalizeCountry((string) ($validated['country_code'] ?? ''));
        $state = ChurchIdentity::displayPlace((string) ($validated['state_region'] ?? ''));
        $city = ChurchIdentity::displayPlace((string) ($validated['city'] ?? ''));
        $hasLocation = strlen($country) === 2 && $state !== '' && $city !== '';

        $slug = $validated['slug'] ?? Str::slug($validated['name']);
        if ($slug === '') {
            $slug = 'channel-'.Str::lower(Str::random(6));
        }
        if (Organization::query()->where('slug', $slug)->exists()) {
            $slug = $slug.'-'.Str::lower(Str::random(4));
        }

        $theme = $validated['theme_color'] ?? '#3d9b7a';

        $freePeriod = app(FreePeriodService::class);
        $freePlan = $freePeriod->ensureFreePlan();
        $trialEndsAt = $freePeriod->endsAtForNewSignup();

        DB::transaction(function () use ($user, $validated, $slug, $theme, $type, $freePlan, $trialEndsAt, $request, $hasLocation, $country, $state, $city): void {
            $organization = Organization::query()->create([
                'name' => $validated['name'],
                'country_code' => $hasLocation ? $country : null,
                'state_region' => $hasLocation ? $state : null,
                'city' => $hasLocation ? $city : null,
                'slug' => $slug,
                'creator_type' => $type,
                'tagline' => $validated['tagline'] ?? null,
                'theme_color' => $theme,
                'is_public' => true,
                'branding_config' => ['accent' => $theme],
            ]);

            $organization->users()->attach($user->id, [
                'role' => OrgRole::Owner->value,
            ]);

            Stream::query()->create([
                'organization_id' => $organization->id,
                'uuid' => (string) Str::uuid(),
                'title' => $validated['name'],
                'description' => $validated['tagline'] ?? null,
                'is_public' => true,
                'chat_enabled' => true,
                'status' => StreamStatus::Offline,
            ]);

            Subscription::query()->create([
                'organization_id' => $organization->id,
                'plan_id' => $freePlan->id,
                'status' => SubscriptionStatus::Active,
                'trial_ends_at' => $trialEndsAt,
            ]);

            app(AffiliateAttributionService::class)->attachNewOrganization($organization, $request);
        });

        session()->forget('onboarding.creator_type');

        $organization = $user->organizations()->first();
        if ($organization !== null) {
            $user->notify(new FreePlanStartedNotification($organization));
        }

        $days = $freePeriod->freeDays();

        return redirect()->route('creator.home')
            ->with('status', __('Channel created on Free for :days days. Open Studio to test, or upgrade anytime from billing.', [
                'days' => $days,
            ]));
    }
}
