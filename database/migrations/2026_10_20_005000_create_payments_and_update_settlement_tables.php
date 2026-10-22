<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Link payment_checkout_id on payment_attempts
        Schema::table('payment_attempts', function (Blueprint $table) {
            $table->foreignId('payment_checkout_id')
                ->nullable()
                ->after('organization_id')
                ->constrained('payment_checkouts')
                ->nullOnDelete();
        });

        // 2. Authoritative payments table representing provider-confirmed money
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('payment_attempt_id')->nullable()->constrained('payment_attempts')->nullOnDelete();
            $table->string('reference', 191)->unique();
            $table->string('provider', 32)->default('paystack');
            $table->string('provider_reference', 191)->nullable()->index();
            $table->unsignedInteger('amount');
            $table->string('currency', 3)->default('NGN');
            $table->string('status', 32)->default('succeeded');
            $table->timestamp('confirmed_at');
            $table->json('provider_payload')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });

        // 3. Update payment_allocations to link authoritative payments
        Schema::table('payment_allocations', function (Blueprint $table) {
            $table->foreignId('payment_id')
                ->nullable()
                ->after('invoice_id')
                ->constrained('payments')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('payment_attempt_id')->nullable()->change();

            $table->unique(['payment_id', 'invoice_id'], 'allocations_payment_invoice_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payment_allocations', function (Blueprint $table) {
            $table->dropUnique('allocations_payment_invoice_unique');
            $table->dropForeign(['payment_id']);
            $table->dropColumn('payment_id');
        });

        Schema::dropIfExists('payments');

        Schema::table('payment_attempts', function (Blueprint $table) {
            $table->dropForeign(['payment_checkout_id']);
            $table->dropColumn('payment_checkout_id');
        });
    }
};
