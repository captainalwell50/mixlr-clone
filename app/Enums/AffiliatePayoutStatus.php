<?php

namespace App\Enums;

enum AffiliatePayoutStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Approved => 'Approved',
            self::Paid => 'Paid',
            self::Cancelled => 'Cancelled',
        };
    }

    public function canBeApproved(): bool
    {
        return $this === self::Draft;
    }

    public function canBePaid(): bool
    {
        return $this === self::Approved;
    }

    public function canBeCancelled(): bool
    {
        return $this === self::Draft || $this === self::Approved;
    }
}
