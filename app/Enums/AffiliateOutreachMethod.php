<?php

namespace App\Enums;

enum AffiliateOutreachMethod: string
{
    case Visit = 'visit';
    case DirectEmail = 'direct_email';
    case PhoneCall = 'phone_call';

    public function label(): string
    {
        return match ($this) {
            self::Visit => 'Visit',
            self::DirectEmail => 'Direct email',
            self::PhoneCall => 'Phone call',
        };
    }
}
