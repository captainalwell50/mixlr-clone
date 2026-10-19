<?php

namespace App\Console\Commands;

use App\Services\InvoiceGenerationService;
use Illuminate\Console\Command;

class GenerateRenewalInvoicesCommand extends Command
{
    protected $signature = 'billing:generate-renewal-invoices {--dry-run : Only log subscriptions that would be invoiced}';
    protected $description = 'Generate renewal invoices for upcoming subscription billing periods (7 days before renews_at)';

    public function handle(InvoiceGenerationService $invoiceGenerator): int
    {
        $this->info('Scanning for upcoming subscription renewals...');

        $generated = $invoiceGenerator->generateUpcomingRenewals();

        $this->info("Generated or verified {$generated->count()} renewal invoice(s).");

        return self::SUCCESS;
    }
}
