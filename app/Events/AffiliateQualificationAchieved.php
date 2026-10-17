<?php

namespace App\Events;

use App\Models\AffiliateAccount;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AffiliateQualificationAchieved
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly AffiliateAccount $affiliateAccount,
    ) {}
}
