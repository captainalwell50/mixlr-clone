<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Dedicated invoice sequence counter for concurrency-safe SM-YYYY-NNNNNN generation
        Schema::create('invoice_sequences', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
        });

        // 2. Extend invoices table with full domain fields while preserving legacy columns
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->after('subscription_id')->constrained('plans')->nullOnDelete();

            $table->unsignedInteger('subtotal')->default(0)->after('currency');
            $table->unsignedInteger('discount_total')->default(0)->after('subtotal');
            $table->unsignedInteger('tax_total')->default(0)->after('discount_total');
            $table->unsignedInteger('total')->default(0)->after('tax_total');
            $table->unsignedInteger('amount_paid')->default(0)->after('total');
            $table->unsignedInteger('amount_due')->default(0)->after('amount_paid');

            $table->timestamp('issued_at')->nullable()->after('status');
            $table->timestamp('due_at')->nullable()->after('due_date');
            $table->timestamp('voided_at')->nullable()->after('paid_at');
            $table->text('notes')->nullable()->after('payment_link');

            $table->unique(['subscription_id', 'billing_period_start', 'billing_period_end'], 'invoices_subscription_period_unique');
            $table->index(['organization_id', 'status'], 'invoices_org_status_index');
            $table->index(['status', 'due_at'], 'invoices_status_due_at_index');
        });

        // 3. Invoice line items
        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('description');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('unit_amount')->default(0);
            $table->unsignedInteger('subtotal')->default(0);
            $table->unsignedInteger('total')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('invoice_id');
        });

        // 4. Payment attempts (individual transactions against invoices)
        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('reference', 191)->unique();
            $table->string('provider', 32)->default('paystack');
            $table->string('provider_reference', 191)->nullable()->index();
            $table->unsignedInteger('amount');
            $table->string('currency', 3)->default('NGN');
            $table->string('status', 32)->default('initiated');
            $table->string('idempotency_key', 191)->nullable()->unique();
            $table->string('failure_reason', 255)->nullable();
            $table->json('provider_payload')->nullable();
            $table->timestamp('initiated_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['invoice_id', 'status']);
            $table->index(['organization_id', 'status']);
        });

        // 5. Payment allocations (ledger entries crediting confirmed attempts to invoices)
        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('payment_attempt_id')->constrained('payment_attempts')->restrictOnDelete();
            $table->unsignedInteger('amount');
            $table->timestamp('allocated_at');
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->unique(['invoice_id', 'payment_attempt_id'], 'allocations_invoice_attempt_unique');
            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payment_attempts');
        Schema::dropIfExists('invoice_lines');

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_status_due_at_index');
            $table->dropIndex('invoices_org_status_index');
            $table->dropUnique('invoices_subscription_period_unique');
            $table->dropForeign(['plan_id']);
            $table->dropColumn([
                'plan_id',
                'subtotal',
                'discount_total',
                'tax_total',
                'total',
                'amount_paid',
                'amount_due',
                'issued_at',
                'due_at',
                'voided_at',
                'notes',
            ]);
        });

        Schema::dropIfExists('invoice_sequences');
    }
};
