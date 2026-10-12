<?php

namespace App\Services;

use App\Enums\AffiliateOutreachMethod;
use App\Enums\AffiliateProspectStatus;
use App\Models\AffiliateAccount;
use App\Models\AffiliateProspect;
use App\Models\AffiliateProspectAudit;
use App\Models\Organization;
use App\Models\User;
use App\Support\ChurchIdentity;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Prospect plans and the 30-day reservation that each plan holds.
 *
 * The active reservation identity is a hash of country, state, city, and
 * the normalized church name, stored in active_reservation_key. That column
 * is unique while a reservation is held and is cleared when the plan is
 * converted, cancelled, declined, or past reserved_until. Clearing an
 * expired key happens inside the create transaction, so another affiliate
 * can reserve the church without waiting for a cleanup command.
 *
 * Two creates for the same key cannot both commit: the unique index rejects
 * the second insert, and that failure is returned as a reservation message.
 */
class AffiliateProspectService
{
    public const CAP_MESSAGE = 'You currently have the maximum number of active church plans. Complete, cancel, convert, or allow a plan to expire before creating another.';

    public const JUST_RESERVED_MESSAGE = 'This church has just been reserved by another Sound Mix affiliate.';

    public const RESERVED_MESSAGE = 'This church is currently being handled by another Sound Mix affiliate.';

    public const REGISTERED_MESSAGE = 'Already registered on Sound Mix Live.';

    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     state: string,
     *     message: string,
     *     can_plan: bool,
     *     requires_disambiguation: bool,
     *     matches: list<array<string, mixed>>
     * }
     */
    public function search(AffiliateAccount $affiliate, array $input): array
    {
        $key = ChurchIdentity::reservationKey(
            (string) ($input['country_code'] ?? ''),
            (string) ($input['state_region'] ?? ''),
            (string) ($input['city'] ?? ''),
            (string) ($input['church_name'] ?? ''),
        );
        $normalized = ChurchIdentity::normalizeName((string) ($input['church_name'] ?? ''));
        $country = ChurchIdentity::normalizeCountry((string) ($input['country_code'] ?? ''));
        $state = ChurchIdentity::normalizePlace((string) ($input['state_region'] ?? ''));
        $city = ChurchIdentity::normalizePlace((string) ($input['city'] ?? ''));

        if ($key === null || $normalized === '') {
            return [
                'state' => 'incomplete',
                'message' => 'Enter the country, state, city, and church name to search.',
                'can_plan' => false,
                'requires_disambiguation' => false,
                'matches' => [],
            ];
        }

        $matches = [];
        $exactRegistered = false;
        $possibleRegistered = false;
        $exactReserved = false;
        $own = null;
        $fuzzy = false;
        $strongContact = false;

        foreach ($this->candidateOrganizations($country, $state, $city) as $organization) {
            $orgName = ChurchIdentity::normalizeName($organization->name);
            $sameName = $orgName === $normalized;
            $samePlace = $this->organizationPlaceMatches($organization, $country, $state, $city);
            $fuzzyName = ChurchIdentity::likelySameName($orgName, $normalized);

            if ($sameName && $samePlace) {
                $exactRegistered = true;
                $matches[] = $this->organizationMatch($organization, true);
            } elseif ($sameName && ! $this->organizationHasPlace($organization)) {
                $possibleRegistered = true;
                $matches[] = $this->organizationMatch($organization, false);
            } elseif ($fuzzyName && ($samePlace || ! $this->organizationHasPlace($organization))) {
                $fuzzy = true;
                $matches[] = $this->organizationMatch($organization, false);
            }
        }

        $prospects = AffiliateProspect::query()
            ->where('country_code', $country)
            ->whereRaw('lower(state_region) = ?', [$state])
            ->whereRaw('lower(city) = ?', [$city])
            ->get();

        foreach ($prospects as $prospect) {
            $sameName = $prospect->normalized_church_name === $normalized
                || $prospect->active_reservation_key === $key;
            $fuzzyName = ChurchIdentity::likelySameName($prospect->normalized_church_name, $normalized);
            $contact = $this->sharesStrongContact($prospect, $input);

            if (! $prospect->holdsReservation()) {
                if ($fuzzyName && ! $sameName) {
                    $fuzzy = true;
                    $matches[] = [
                        'type' => 'past_plan',
                        'church_name' => $prospect->church_name,
                        'city' => $prospect->city,
                        'state' => $prospect->state_region,
                        'exact' => false,
                    ];
                }

                continue;
            }

            if ($contact && ! $sameName) {
                $strongContact = true;
            }

            if ($sameName || $contact) {
                if ($prospect->affiliate_account_id === $affiliate->id) {
                    $own = $prospect;
                    $matches[] = $this->ownMatch($prospect);
                } else {
                    $exactReserved = true;
                    $matches[] = $this->reservedMatch($prospect);
                }

                continue;
            }

            if ($fuzzyName) {
                $fuzzy = true;
                $matches[] = [
                    'type' => 'possible_plan',
                    'church_name' => $prospect->church_name,
                    'city' => $prospect->city,
                    'state' => $prospect->state_region,
                    'exact' => false,
                ];
            }
        }

        if ($exactRegistered) {
            return $this->result('registered', self::REGISTERED_MESSAGE, false, false, $matches);
        }

        if ($exactReserved || $strongContact) {
            return $this->result('reserved', self::RESERVED_MESSAGE, false, false, $matches);
        }

        if ($own !== null) {
            return $this->result('yours', 'You already have a plan for this church.', false, false, $matches);
        }

        if ($possibleRegistered || $fuzzy) {
            return $this->result(
                'review',
                'Possible matches were found. Confirm this is a different church and add an address, website, or phone before saving.',
                true,
                true,
                $matches,
            );
        }

        return $this->result('available', 'Available to plan.', true, false, $matches);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(AffiliateAccount $affiliate, array $input): AffiliateProspect
    {
        if (! AffiliateProgrammeSettings::enabled()) {
            throw ValidationException::withMessages([
                'church_name' => 'The affiliate programme is not accepting new church plans.',
            ]);
        }

        if (! $affiliate->canRefer()) {
            throw ValidationException::withMessages([
                'church_name' => 'Only an approved affiliate can plan a church.',
            ]);
        }

        $method = $input['outreach_method'] instanceof AffiliateOutreachMethod
            ? $input['outreach_method']
            : AffiliateOutreachMethod::from((string) $input['outreach_method']);
        if (! AffiliateProgrammeSettings::outreachEnabled($method)) {
            throw ValidationException::withMessages([
                'outreach_method' => 'That outreach method is turned off.',
            ]);
        }

        if (! AffiliateProgrammeSettings::notesEnabled()) {
            $input['notes'] = null;
        }

        $search = $this->search($affiliate, $input);
        if ($search['state'] === 'yours') {
            throw ValidationException::withMessages([
                'church_name' => 'You already have a plan for this church. Open that plan instead of creating another.',
            ]);
        }

        if (! $search['can_plan']) {
            throw ValidationException::withMessages([
                'church_name' => $search['message'],
            ]);
        }

        if ($search['requires_disambiguation'] && ! $this->confirmedDifferent($input)) {
            throw ValidationException::withMessages([
                'church_name' => 'This looks like an existing church or plan. Mark it as a different church and add an address, website, or phone.',
            ]);
        }

        $key = ChurchIdentity::reservationKey(
            (string) $input['country_code'],
            (string) $input['state_region'],
            (string) $input['city'],
            (string) $input['church_name'],
        );
        if ($key === null) {
            throw ValidationException::withMessages([
                'church_name' => 'Country, state, city, and church name are required.',
            ]);
        }

        try {
            return DB::transaction(function () use ($affiliate, $input, $key): AffiliateProspect {
                AffiliateAccount::query()->whereKey($affiliate->id)->lockForUpdate()->first();
                $this->releaseExpiredHold($key);

                $mine = AffiliateProspect::query()
                    ->where('affiliate_account_id', $affiliate->id)
                    ->holdingReservation()
                    ->count();

                if ($mine >= AffiliateProgrammeSettings::prospectActiveLimit()) {
                    throw ValidationException::withMessages([
                        'church_name' => self::CAP_MESSAGE,
                    ]);
                }

                $this->assertNoStrongContact($input);

                $conflict = AffiliateProspect::query()
                    ->where('active_reservation_key', $key)
                    ->lockForUpdate()
                    ->first();

                if ($conflict !== null) {
                    $message = $conflict->affiliate_account_id === $affiliate->id
                        ? 'You already have a plan for this church. Open that plan instead of creating another.'
                        : self::RESERVED_MESSAGE;

                    throw ValidationException::withMessages([
                        'church_name' => $message,
                    ]);
                }

                $days = AffiliateProgrammeSettings::prospectReservationDays();

                return AffiliateProspect::query()->create([
                    'affiliate_account_id' => $affiliate->id,
                    'outreach_method' => $input['outreach_method'],
                    'church_name' => trim((string) $input['church_name']),
                    'normalized_church_name' => ChurchIdentity::normalizeName((string) $input['church_name']),
                    'country_code' => ChurchIdentity::normalizeCountry((string) $input['country_code']),
                    'state_region' => ChurchIdentity::displayPlace((string) $input['state_region']),
                    'city' => ChurchIdentity::displayPlace((string) $input['city']),
                    'address' => $this->nullableString($input['address'] ?? null),
                    'email' => $this->nullableString($input['email'] ?? null),
                    'phone' => $this->nullableString($input['phone'] ?? null),
                    'website' => $this->nullableString($input['website'] ?? null),
                    'contact_name' => $this->nullableString($input['contact_name'] ?? null),
                    'contact_role' => $this->nullableString($input['contact_role'] ?? null),
                    'notes' => $this->nullableString($input['notes'] ?? null),
                    'status' => AffiliateProspectStatus::Planned,
                    'planned_for' => $input['planned_for'] ?? null,
                    'reserved_at' => now(),
                    'reserved_until' => now()->addDays($days),
                    'active_reservation_key' => $key,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'church_name' => self::JUST_RESERVED_MESSAGE,
            ]);
        }
    }

    public function transition(AffiliateProspect $prospect, AffiliateAccount $affiliate, AffiliateProspectStatus $next): AffiliateProspect
    {
        if ($prospect->affiliate_account_id !== $affiliate->id) {
            abort(403);
        }

        if (! $prospect->status->canMoveTo($next)) {
            throw ValidationException::withMessages([
                'status' => 'That status change is not available for this plan.',
            ]);
        }

        return DB::transaction(function () use ($prospect, $next): AffiliateProspect {
            $locked = AffiliateProspect::query()->whereKey($prospect->id)->lockForUpdate()->firstOrFail();
            $updates = ['status' => $next];

            if ($next === AffiliateProspectStatus::Contacted && $locked->contacted_at === null) {
                $updates['contacted_at'] = now();
            }

            if (in_array($next, [AffiliateProspectStatus::Cancelled, AffiliateProspectStatus::NotInterested], true)) {
                $updates['active_reservation_key'] = null;
                $updates['cancelled_at'] = now();
            }

            $locked->forceFill($updates)->save();

            return $locked->refresh();
        });
    }

    public function release(AffiliateProspect $prospect, User $actor, string $reason): AffiliateProspect
    {
        if (! AffiliateProgrammeSettings::adminCanReleaseProspects()) {
            throw ValidationException::withMessages([
                'reason' => 'Administrative release is turned off.',
            ]);
        }

        $reason = trim($reason);
        if ($reason === '' && AffiliateProgrammeSettings::releaseReasonRequired()) {
            throw ValidationException::withMessages([
                'reason' => 'A reason is required to release a church plan.',
            ]);
        }

        if ($reason === '') {
            $reason = 'Released by a platform admin.';
        }

        if ($prospect->status === AffiliateProspectStatus::Converted) {
            throw ValidationException::withMessages([
                'reason' => 'A converted plan stays on the record. Change the channel attribution from the channel page.',
            ]);
        }

        return DB::transaction(function () use ($prospect, $actor, $reason): AffiliateProspect {
            $locked = AffiliateProspect::query()->whereKey($prospect->id)->lockForUpdate()->firstOrFail();
            $locked->forceFill([
                'status' => AffiliateProspectStatus::Cancelled,
                'cancelled_at' => now(),
                'active_reservation_key' => null,
            ])->save();

            AffiliateProspectAudit::query()->create([
                'affiliate_prospect_id' => $locked->id,
                'action' => 'released',
                'reason' => $reason,
                'actor_id' => $actor->id,
            ]);

            return $locked->refresh();
        });
    }

    public function matchingActiveProspect(Organization $organization): ?AffiliateProspect
    {
        $key = ChurchIdentity::keyFromOrganization($organization);
        if ($key === null) {
            return null;
        }

        return AffiliateProspect::query()
            ->holdingReservation()
            ->where('active_reservation_key', $key)
            ->lockForUpdate()
            ->first();
    }

    public function markConverted(AffiliateProspect $prospect, Organization $organization): void
    {
        $prospect->forceFill([
            'status' => AffiliateProspectStatus::Converted,
            'converted_organization_id' => $organization->id,
            'converted_at' => now(),
            'active_reservation_key' => null,
        ])->save();
    }

    public function expireDue(): int
    {
        return AffiliateProspect::query()
            ->whereIn('status', AffiliateProspectStatus::reservationBearingValues())
            ->where('reserved_until', '<=', now())
            ->update([
                'status' => AffiliateProspectStatus::Expired->value,
                'active_reservation_key' => null,
                'updated_at' => now(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function assertNoStrongContact(array $input): void
    {
        $country = ChurchIdentity::normalizeCountry((string) $input['country_code']);
        $state = ChurchIdentity::normalizePlace((string) $input['state_region']);
        $city = ChurchIdentity::normalizePlace((string) $input['city']);

        $holding = AffiliateProspect::query()
            ->holdingReservation()
            ->where('country_code', $country)
            ->whereRaw('lower(state_region) = ?', [$state])
            ->whereRaw('lower(city) = ?', [$city])
            ->lockForUpdate()
            ->get();

        foreach ($holding as $prospect) {
            if ($this->sharesStrongContact($prospect, $input)) {
                throw ValidationException::withMessages([
                    'church_name' => self::RESERVED_MESSAGE,
                ]);
            }
        }
    }

    private function releaseExpiredHold(string $key): void
    {
        AffiliateProspect::query()
            ->where('active_reservation_key', $key)
            ->where('reserved_until', '<=', now())
            ->update([
                'active_reservation_key' => null,
                'status' => AffiliateProspectStatus::Expired->value,
                'updated_at' => now(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function confirmedDifferent(array $input): bool
    {
        if (! filter_var($input['different_church'] ?? false, FILTER_VALIDATE_BOOL)) {
            return false;
        }

        return $this->nullableString($input['address'] ?? null) !== null
            || $this->nullableString($input['website'] ?? null) !== null
            || $this->nullableString($input['phone'] ?? null) !== null;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function sharesStrongContact(AffiliateProspect $prospect, array $input): bool
    {
        $phone = ChurchIdentity::phoneDigits($input['phone'] ?? null);
        $otherPhone = ChurchIdentity::phoneDigits($prospect->phone);
        if (strlen($phone) >= 7 && $phone === $otherPhone) {
            return true;
        }

        $host = ChurchIdentity::websiteHost($input['website'] ?? null);
        $otherHost = ChurchIdentity::websiteHost($prospect->website);

        return $host !== '' && $host === $otherHost;
    }

    /**
     * @return list<Organization>
     */
    private function candidateOrganizations(string $country, string $state, string $city): array
    {
        return Organization::query()
            ->where(function ($query) use ($country, $state, $city): void {
                $query->where(function ($samePlace) use ($country, $state, $city): void {
                    $samePlace->where('country_code', $country)
                        ->whereRaw('lower(state_region) = ?', [$state])
                        ->whereRaw('lower(city) = ?', [$city]);
                })->orWhereNull('city');
            })
            ->orderBy('name')
            ->limit(50)
            ->get()
            ->all();
    }

    private function organizationHasPlace(Organization $organization): bool
    {
        return is_string($organization->country_code)
            && $organization->country_code !== ''
            && is_string($organization->state_region)
            && trim($organization->state_region) !== ''
            && is_string($organization->city)
            && trim($organization->city) !== '';
    }

    private function organizationPlaceMatches(Organization $organization, string $country, string $state, string $city): bool
    {
        if (! $this->organizationHasPlace($organization)) {
            return false;
        }

        return ChurchIdentity::normalizeCountry((string) $organization->country_code) === $country
            && ChurchIdentity::normalizePlace((string) $organization->state_region) === $state
            && ChurchIdentity::normalizePlace((string) $organization->city) === $city;
    }

    /**
     * @return array<string, mixed>
     */
    private function organizationMatch(Organization $organization, bool $exact): array
    {
        return [
            'type' => 'organization',
            'name' => $organization->name,
            'city' => $organization->city,
            'state' => $organization->state_region,
            'exact' => $exact,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reservedMatch(AffiliateProspect $prospect): array
    {
        return [
            'type' => 'reserved',
            'church_name' => $prospect->church_name,
            'city' => $prospect->city,
            'state' => $prospect->state_region,
            'planned_for' => $prospect->planned_for?->toIso8601String(),
            'reserved_until' => $prospect->reserved_until->toIso8601String(),
            'exact' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ownMatch(AffiliateProspect $prospect): array
    {
        return [
            'type' => 'own',
            'id' => $prospect->id,
            'church_name' => $prospect->church_name,
            'outreach_method' => $prospect->outreach_method->value,
            'outreach_label' => $prospect->outreach_method->label(),
            'status' => $prospect->status->value,
            'status_label' => $prospect->status->label(),
            'planned_for' => $prospect->planned_for?->toIso8601String(),
            'reserved_until' => $prospect->reserved_until->toIso8601String(),
            'exact' => true,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $matches
     * @return array{state: string, message: string, can_plan: bool, requires_disambiguation: bool, matches: list<array<string, mixed>>}
     */
    private function result(string $state, string $message, bool $canPlan, bool $requiresDisambiguation, array $matches): array
    {
        return [
            'state' => $state,
            'message' => $message,
            'can_plan' => $canPlan,
            'requires_disambiguation' => $requiresDisambiguation,
            'matches' => $matches,
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
