<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Drop the composite unique index that blocked voided historical retention
        DB::statement('DROP INDEX IF EXISTS invoices_subscription_period_unique');

        // 2. Create authoritative partial unique index allowing void and cancelled historical invoices
        // Only active/open/paid/draft invoices are constrained to one per subscription period
        DB::statement(
            "CREATE UNIQUE INDEX IF NOT EXISTS invoices_subscription_active_period_unique " .
            "ON invoices (subscription_id, billing_period_start, billing_period_end) " .
            "WHERE status NOT IN ('void', 'cancelled')"
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS invoices_subscription_active_period_unique');

        DB::statement(
            'CREATE UNIQUE INDEX IF NOT EXISTS invoices_subscription_period_unique ' .
            'ON invoices (subscription_id, billing_period_start, billing_period_end)'
        );
    }
};
