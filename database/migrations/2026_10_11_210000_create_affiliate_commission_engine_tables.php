<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_account_id')->constrained()->cascadeOnDelete();
            $table->string('payout_reference', 64)->unique();
            $table->string('currency', 3)->default('NGN');
            $table->bigInteger('amount_minor');
            $table->string('status', 32)->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(['affiliate_account_id', 'status']);
            $table->index('status');
        });

        Schema::create('affiliate_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('affiliate_payout_id')->nullable()->constrained('affiliate_payouts')->nullOnDelete();
            $table->string('payment_reference', 120);
            $table->string('provider_reference', 120)->nullable();
            $table->string('idempotency_key', 160)->unique();
            $table->bigInteger('gross_amount_minor');
            $table->integer('commission_rate_bps');
            $table->bigInteger('commission_amount_minor');
            $table->string('currency', 3)->default('NGN');
            $table->string('attribution_source', 32);
            $table->string('status', 32);
            $table->timestamp('hold_until');
            $table->timestamp('confirmed_at');
            $table->timestamp('payable_at')->nullable();
            $table->foreignId('reversal_of_commission_id')->nullable()->constrained('affiliate_commissions')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->string('reversal_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['affiliate_account_id', 'status']);
            $table->index('organization_id');
            $table->index('payment_reference');
            $table->index(['status', 'hold_until']);
            $table->index('affiliate_payout_id');
        });

        Schema::create('affiliate_payout_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_payout_id')->constrained('affiliate_payouts')->cascadeOnDelete();
            $table->foreignId('affiliate_commission_id')->unique()->constrained('affiliate_commissions')->cascadeOnDelete();
            $table->bigInteger('amount_minor');
            $table->timestamps();

            $table->index('affiliate_payout_id');
        });

        Schema::create('affiliate_commission_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_commission_id')->nullable()->constrained('affiliate_commissions')->nullOnDelete();
            $table->foreignId('affiliate_payout_id')->nullable()->constrained('affiliate_payouts')->nullOnDelete();
            $table->foreignId('affiliate_account_id')->constrained('affiliate_accounts')->cascadeOnDelete();
            $table->string('action', 50);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();
            $table->bigInteger('amount_minor')->nullable();
            $table->string('currency', 3)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['affiliate_account_id', 'created_at']);
            $table->index('affiliate_commission_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_commission_audits');
        Schema::dropIfExists('affiliate_payout_items');
        Schema::dropIfExists('affiliate_commissions');
        Schema::dropIfExists('affiliate_payouts');
    }
};
