<?php

namespace App\Support;

use App\Enums\AffiliateCommissionStatus;
use App\Models\AffiliateAccount;
use App\Models\AffiliateCommission;
use App\Services\AffiliateProgrammeSettings;
use DateTimeInterface;

/**
 * Authoritative Pre-Qualification and Payout Policy (Policy A).
 *
 * Rules:
 * 1. Accrual before qualification:
 *    Affiliates may accrue commissions in HELD status before reaching payout qualification.
 * 2. Maturation requirements:
 *    Commissions remain non-payable until BOTH:
 *    - The affiliate achieves payout qualification (5 distinct paying referred organisations).
 *    - The 14-day hold period has elapsed (hold_until <= now).
 * 3. Maturation release:
 *    Once qualification is achieved, any matured pre-qualification commissions immediately become PAYABLE.
 * 4. Permanent qualification:
 *    Once earned, payout_qualified_at is permanent and is never revoked by subsequent churn or refunds.
 * 5. Pre-qualification organisation validity:
 *    Before permanent qualification, an organisation counts toward the 5-church threshold only if it
 *    has at least one valid, confirmed, unreversed eligible subscription payment. If its only eligible
 *    payment is fully reversed before qualification, it stops counting.
 */
final class AffiliateQualificationPolicy
{
    /**
     * Whether pre-qualification commission accrual (Policy A) is enabled.
     */
    public static function allowsPreQualificationAccrual(): bool
    {
        return true;
    }

    /**
     * The number of distinct paying organisations required to qualify for payouts.
     */
    public static function requiredDistinctPayingOrganisations(): int
    {
        return (int) (AffiliateProgrammeSettings::all()['payout_qualification_target'] ?? 5);
    }

    /**
     * The mandatory hold duration in days before a commission may mature.
     */
    public static function holdDays(): int
    {
        return (int) (AffiliateProgrammeSettings::all()['commission_hold_days'] ?? 14);
    }

    /**
     * Check whether a held commission is eligible to transition to PAYABLE.
     */
    public static function canTransitionToPayable(
        AffiliateCommission $commission,
        AffiliateAccount $affiliate,
        ?DateTimeInterface $asOf = null,
    ): bool {
        if ($commission->status !== AffiliateCommissionStatus::Held) {
            return false;
        }

        if (! $affiliate->isQualified()) {
            return false;
        }

        $asOf ??= now();

        return $commission->hold_until->lessThanOrEqualTo($asOf);
    }
}
