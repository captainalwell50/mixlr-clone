<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Cancelled = 'cancelled';

    public function allowsBroadcast(): bool
    {
        return $this === self::Trialing || $this === self::Active;
    }

    public function label(): string
    {
        return match ($this) {
            self::Trialing => 'Trial',
            self::Active => 'Active',
            self::PastDue => 'Past due',
            self::Cancelled => 'Cancelled',
        };
    }
}
