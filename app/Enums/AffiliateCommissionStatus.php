<?php

namespace App\Enums;

enum AffiliateCommissionStatus: string
{
    case Held = 'held';
    case Payable = 'payable';
    case Paid = 'paid';
    case Reversed = 'reversed';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Held => 'Held (14-day hold)',
            self::Payable => 'Payable',
            self::Paid => 'Paid',
            self::Reversed => 'Reversed',
            self::Voided => 'Voided',
        };
    }

    public function isPendingOrHeld(): bool
    {
        return $this === self::Held;
    }

    public function isPayable(): bool
    {
        return $this === self::Payable;
    }

    public function isPaid(): bool
    {
        return $this === self::Paid;
    }

    public function isReversed(): bool
    {
        return $this === self::Reversed;
    }

    public function isVoided(): bool
    {
        return $this === self::Voided;
    }
}
