<?php

namespace App\Enums;

enum AffiliateAttributionSource: string
{
    case FirstTouch = 'first_touch';
    case ProspectReservation = 'prospect_reservation';
    case Organic = 'organic';
    case Admin = 'admin';
    case BlockedSelfReferral = 'blocked_self_referral';

    public function label(): string
    {
        return match ($this) {
            self::FirstTouch => 'First-touch referral',
            self::ProspectReservation => 'Prospect reservation',
            self::Organic => 'Organic',
            self::Admin => 'Admin assignment',
            self::BlockedSelfReferral => 'Blocked self-referral',
        };
    }

    /**
     * Sources a later commission ledger may pay on.
     * Blocked self-referrals and organic signups are never earning-eligible.
     */
    public function isEarningEligible(): bool
    {
        return $this === self::FirstTouch
            || $this === self::ProspectReservation
            || $this === self::Admin;
    }
}
