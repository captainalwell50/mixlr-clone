<?php

namespace App\Events;

use App\Models\AffiliateCommission;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AffiliateCommissionReversed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly AffiliateCommission $commission,
        public readonly string $reason,
    ) {}
}
