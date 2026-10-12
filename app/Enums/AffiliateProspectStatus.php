<?php

namespace App\Enums;

enum AffiliateProspectStatus: string
{
    case Planned = 'planned';
    case Contacted = 'contacted';
    case FollowUp = 'follow_up';
    case Interested = 'interested';
    case Converted = 'converted';
    case NotInterested = 'not_interested';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Planned',
            self::Contacted => 'Contacted',
            self::FollowUp => 'Follow-up',
            self::Interested => 'Interested',
            self::Converted => 'Converted',
            self::NotInterested => 'Not interested',
            self::Expired => 'Expired',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isReservationBearing(): bool
    {
        return in_array($this, self::reservationBearing(), true);
    }

    /**
     * @return list<self>
     */
    public static function reservationBearing(): array
    {
        return [
            self::Planned,
            self::Contacted,
            self::FollowUp,
            self::Interested,
        ];
    }

    /**
     * @return list<string>
     */
    public static function reservationBearingValues(): array
    {
        return array_map(fn (self $status): string => $status->value, self::reservationBearing());
    }

    public function canMoveTo(self $next): bool
    {
        if (! $this->isReservationBearing()) {
            return false;
        }

        return in_array($next, [
            self::Contacted,
            self::FollowUp,
            self::Interested,
            self::NotInterested,
            self::Cancelled,
        ], true);
    }
}
