<?php

namespace App\Services;

use App\Enums\AffiliateAttributionSource;
use App\Enums\OrgRole;
use App\Models\AffiliateAccount;
use App\Models\AffiliateAttribution;
use App\Models\AffiliateAttributionChange;
use App\Models\AffiliateProspect;
use App\Models\AffiliateReferralVisit;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Authoritative path for referral visits and organization attribution.
 *
 * For a new organization the order is:
 * 1. an attribution row that already exists
 * 2. the earlier affiliate claim that can actually be tied to this signup
 * 3. organic
 *
 * A first-touch visit is a claim on the person creating the channel. Its
 * time is first_seen_at. It does not name a church. A prospect reservation
 * is a claim on a church name plus country, state, and city. Its time is
 * reserved_at. The reservation is used only when the new channel has that
 * same name and location. A cookie alone is not treated as proof that the
 * visit was for that church, and a reservation is not treated as proof
 * about a channel that has no location.
 *
 * When both claims match this channel, the strictly earlier timestamp wins.
 * If the timestamps are equal, the referral visit is kept. Neither source
 * always beats the other. Self-referral blocking still applies to the winner.
 * This does not create commissions.
 */
class AffiliateAttributionService
{
    public function captureVisit(Request $request, string $code): void
    {
        if ($this->activeVisit($request) !== null) {
            return;
        }

        if (! AffiliateProgrammeSettings::enabled()) {
            return;
        }

        $affiliate = AffiliateAccount::query()
            ->where('referral_code', AffiliateAccount::normalizeCode($code))
            ->first();

        if ($affiliate === null || ! $affiliate->canRefer()) {
            return;
        }

        $days = max(1, AffiliateProgrammeSettings::attributionDays());
        $visit = AffiliateReferralVisit::query()->create([
            'affiliate_account_id' => $affiliate->id,
            'referral_code' => $affiliate->referral_code,
            'token' => Str::random(48),
            'first_seen_at' => now(),
            'expires_at' => now()->addDays($days),
            'ip_hash' => $this->ipHash($request),
            'user_agent' => $this->userAgent($request),
        ]);

        Cookie::queue(cookie(
            AffiliateReferralVisit::COOKIE,
            $visit->token,
            $days * 24 * 60,
            '/',
            null,
            null,
            true,
            false,
            'lax',
        ));
    }

    public function attachNewOrganization(Organization $organization, Request $request): AffiliateAttribution
    {
        return DB::transaction(function () use ($organization, $request): AffiliateAttribution {
            $existing = AffiliateAttribution::query()
                ->where('organization_id', $organization->id)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $visit = $this->activeVisit($request);
            $prospects = app(AffiliateProspectService::class);
            $prospect = $prospects->matchingActiveProspect($organization);
            $prospectWins = $prospect !== null
                && ($visit === null || $prospect->reserved_at->lt($visit->first_seen_at));

            if ($prospectWins) {
                return $this->attributeProspect($organization, $prospect, $visit, $prospects);
            }

            $affiliate = $visit?->affiliateAccount;
            if ($visit === null || $affiliate === null) {
                return $this->storeAttribution(
                    $organization,
                    null,
                    null,
                    AffiliateAttributionSource::Organic,
                    'Resolved as organic at channel creation.',
                );
            }

            $source = $this->controlsOrganization($affiliate, $organization)
                ? AffiliateAttributionSource::BlockedSelfReferral
                : AffiliateAttributionSource::FirstTouch;

            $visit->forceFill([
                'organization_id' => $organization->id,
                'converted_at' => now(),
            ])->save();

            $reason = $source === AffiliateAttributionSource::BlockedSelfReferral
                ? 'Blocked self-referral at channel creation.'
                : 'Resolved from first-touch referral at channel creation.';

            return $this->storeAttribution($organization, $affiliate, $visit, $source, $reason);
        });
    }

    public function reassign(
        Organization $organization,
        AffiliateAccount $affiliate,
        User $actor,
        string $reason,
    ): AffiliateAttribution {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'A reason is required to change affiliate attribution.',
            ]);
        }

        if (! $affiliate->canRefer()) {
            throw ValidationException::withMessages([
                'affiliate_account_id' => 'Only an approved affiliate can be attributed.',
            ]);
        }

        return DB::transaction(function () use ($organization, $affiliate, $actor, $reason): AffiliateAttribution {
            $existing = AffiliateAttribution::query()
                ->where('organization_id', $organization->id)
                ->lockForUpdate()
                ->first();

            $source = $this->controlsOrganization($affiliate, $organization)
                ? AffiliateAttributionSource::BlockedSelfReferral
                : AffiliateAttributionSource::Admin;

            if ($existing !== null
                && $existing->affiliate_account_id === $affiliate->id
                && $existing->source === $source) {
                throw ValidationException::withMessages([
                    'affiliate_account_id' => 'This channel already has that affiliate attribution.',
                ]);
            }

            $previousAffiliateId = $existing?->affiliate_account_id;
            $previousSource = $existing?->source;

            if ($existing === null) {
                $attribution = AffiliateAttribution::query()->create([
                    'organization_id' => $organization->id,
                    'affiliate_account_id' => $affiliate->id,
                    'referral_visit_id' => null,
                    'source' => $source,
                    'attributed_at' => now(),
                ]);
            } else {
                $existing->forceFill([
                    'affiliate_account_id' => $affiliate->id,
                    'referral_visit_id' => null,
                    'source' => $source,
                    'attributed_at' => now(),
                ])->save();
                $attribution = $existing->refresh();
            }

            $this->recordChange(
                $organization,
                $previousAffiliateId,
                $affiliate->id,
                $previousSource,
                $source,
                $actor->id,
                $reason,
            );

            return $attribution;
        });
    }

    private function attributeProspect(
        Organization $organization,
        AffiliateProspect $prospect,
        ?AffiliateReferralVisit $visit,
        AffiliateProspectService $prospects,
    ): AffiliateAttribution {
        $affiliate = $prospect->affiliateAccount;
        $blocked = $affiliate !== null && $this->controlsOrganization($affiliate, $organization);
        $source = $blocked
            ? AffiliateAttributionSource::BlockedSelfReferral
            : AffiliateAttributionSource::ProspectReservation;

        $prospects->markConverted($prospect, $organization);

        if ($visit !== null) {
            $visit->forceFill([
                'organization_id' => $organization->id,
                'converted_at' => now(),
            ])->save();
        }

        $reason = $blocked
            ? 'Blocked self-referral from a prospect reservation at channel creation.'
            : 'Resolved from an earlier prospect reservation at channel creation.';

        return $this->storeAttribution($organization, $affiliate, null, $source, $reason);
    }

    public function activeVisit(Request $request): ?AffiliateReferralVisit
    {
        $token = $request->cookie(AffiliateReferralVisit::COOKIE);
        if (! is_string($token) || $token === '') {
            return null;
        }

        $visit = AffiliateReferralVisit::query()->where('token', $token)->first();
        if ($visit === null || ! $visit->isOpen()) {
            return null;
        }

        return $visit;
    }

    private function controlsOrganization(AffiliateAccount $affiliate, Organization $organization): bool
    {
        $role = $organization->users()
            ->where('users.id', $affiliate->user_id)
            ->first()
            ?->pivot
            ?->role;

        $orgRole = is_string($role) ? OrgRole::tryFrom($role) : null;

        return $orgRole?->canManage() ?? false;
    }

    private function storeAttribution(
        Organization $organization,
        ?AffiliateAccount $affiliate,
        ?AffiliateReferralVisit $visit,
        AffiliateAttributionSource $source,
        string $reason,
    ): AffiliateAttribution {
        $attribution = AffiliateAttribution::query()->create([
            'organization_id' => $organization->id,
            'affiliate_account_id' => $affiliate?->id,
            'referral_visit_id' => $visit?->id,
            'source' => $source,
            'attributed_at' => now(),
        ]);

        $this->recordChange(
            $organization,
            null,
            $affiliate?->id,
            null,
            $source,
            null,
            $reason,
        );

        return $attribution;
    }

    private function recordChange(
        Organization $organization,
        ?int $previousAffiliateId,
        ?int $newAffiliateId,
        ?AffiliateAttributionSource $previousSource,
        AffiliateAttributionSource $newSource,
        ?int $changedBy,
        string $reason,
    ): void {
        AffiliateAttributionChange::query()->create([
            'organization_id' => $organization->id,
            'previous_affiliate_account_id' => $previousAffiliateId,
            'new_affiliate_account_id' => $newAffiliateId,
            'previous_source' => $previousSource,
            'new_source' => $newSource,
            'changed_by' => $changedBy,
            'reason' => $reason,
        ]);
    }

    private function ipHash(Request $request): ?string
    {
        $ip = $request->ip();
        if (! is_string($ip) || $ip === '') {
            return null;
        }

        return hash_hmac('sha256', $ip, (string) config('app.key'));
    }

    private function userAgent(Request $request): ?string
    {
        $agent = $request->userAgent();
        if (! is_string($agent) || trim($agent) === '') {
            return null;
        }

        return Str::limit(trim($agent), 255, '');
    }
}
