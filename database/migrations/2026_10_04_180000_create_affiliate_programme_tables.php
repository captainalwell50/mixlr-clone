<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('referral_code', 16)->unique();
            $table->string('status', 32);
            $table->char('country_code', 2);
            $table->string('state_region', 120);
            $table->string('city', 120);
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('payout_qualified_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('country_code');
        });

        Schema::create('affiliate_referral_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_account_id')->constrained()->cascadeOnDelete();
            $table->string('referral_code', 16);
            $table->string('token', 80)->unique();
            $table->timestamp('first_seen_at');
            $table->timestamp('expires_at');
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('converted_at')->nullable();
            $table->char('ip_hash', 64)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();

            $table->index(['affiliate_account_id', 'expires_at']);
        });

        Schema::create('affiliate_attributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('affiliate_account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('referral_visit_id')->nullable()->constrained('affiliate_referral_visits')->nullOnDelete();
            $table->string('source', 32);
            $table->timestamp('attributed_at');
            $table->timestamps();

            $table->index('affiliate_account_id');
            $table->index('source');
        });

        Schema::create('affiliate_attribution_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('previous_affiliate_account_id')->nullable()->constrained('affiliate_accounts')->nullOnDelete();
            $table->foreignId('new_affiliate_account_id')->nullable()->constrained('affiliate_accounts')->nullOnDelete();
            $table->string('previous_source', 32)->nullable();
            $table->string('new_source', 32);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason');
            $table->timestamps();

            $table->index(['organization_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_attribution_changes');
        Schema::dropIfExists('affiliate_attributions');
        Schema::dropIfExists('affiliate_referral_visits');
        Schema::dropIfExists('affiliate_accounts');
    }
};
