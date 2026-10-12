<?php

namespace App\Enums;

enum AffiliateStatus: string
{
    case Invited = 'invited';
    case Pending = 'pending';
    case Approved = 'approved';
    case Suspended = 'suspended';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Invited => 'Invited',
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Suspended => 'Suspended',
            self::Rejected => 'Rejected',
        };
    }

    public function canRefer(): bool
    {
        return $this === self::Approved;
    }
}
