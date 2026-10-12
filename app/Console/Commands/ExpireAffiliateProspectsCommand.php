<?php

namespace App\Console\Commands;

use App\Services\AffiliateProspectService;
use Illuminate\Console\Command;

class ExpireAffiliateProspectsCommand extends Command
{
    protected $signature = 'affiliates:expire-prospects';

    protected $description = 'Label prospect plans whose reservation window has ended';

    public function handle(AffiliateProspectService $prospects): int
    {
        $count = $prospects->expireDue();
        $this->info("Marked {$count} prospect plan(s) expired.");

        return self::SUCCESS;
    }
}
