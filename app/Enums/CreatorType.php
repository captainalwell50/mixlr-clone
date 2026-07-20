<?php

namespace App\Enums;

enum CreatorType: string
{
    case Church = 'church';
    case Radio = 'radio';
    case EventOrganizer = 'event_organizer';

    public function label(): string
    {
        return match ($this) {
            self::Church => 'Church',
            self::Radio => 'Radio station',
            self::EventOrganizer => 'Event organizer',
        };
    }

    public function blurb(): string
    {
        return match ($this) {
            self::Church => 'Sunday services, midweek gatherings, and prayer meetings.',
            self::Radio => 'Live shows, talk hours, and continuous audio channels.',
            self::EventOrganizer => 'Conferences, concerts, and ticketed live audio.',
        };
    }
}
