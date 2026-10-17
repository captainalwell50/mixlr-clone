<?php

namespace App\Events;

use App\Models\AffiliatePayout;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AffiliatePayoutApproved
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly AffiliatePayout $payout,
    ) {}
}
