<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('creator_type', 32)->nullable()->after('slug');
            $table->string('paystack_customer_code', 64)->nullable()->after('social_feed_url');
        });

        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('paystack_plan_code')->nullable();
            $table->unsignedInteger('amount');
            $table->string('currency', 8)->default('NGN');
            $table->string('interval', 16)->default('monthly');
            $table->json('limits')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 32)->default('trialing');
            $table->string('paystack_subscription_code')->nullable();
            $table->string('paystack_email_token')->nullable();
            $table->string('paystack_customer_code')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['creator_type', 'paystack_customer_code']);
        });
    }
};
