<?php

namespace App\Console\Commands;

use App\Services\AffiliateCommissionService;
use Illuminate\Console\Command;

class ProcessAffiliateHoldsCommand extends Command
{
    protected $signature = 'affiliates:process-holds';

    protected $description = 'Release matured 14-day affiliate commission holds into payable status for qualified affiliates';

    public function handle(AffiliateCommissionService $commissions): int
    {
        $transitioned = $commissions->processMatureHolds();
        $this->info("Released {$transitioned} matured affiliate commission hold(s) to payable.");

        return self::SUCCESS;
    }
}
