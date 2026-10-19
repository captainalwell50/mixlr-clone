<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recipient')->index();
            $table->string('type', 64)->index();
            $table->string('category', 32)->index()->default('transactional');
            $table->string('event_key', 191)->nullable()->unique();
            $table->string('provider_message_id', 191)->nullable()->index();
            $table->string('status', 32)->index()->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->string('invoice_number', 64)->unique();
            $table->date('billing_period_start')->nullable();
            $table->date('billing_period_end')->nullable();
            $table->unsignedInteger('amount');
            $table->string('currency', 8)->default('NGN');
            $table->timestamp('due_date');
            $table->timestamp('paid_at')->nullable();
            $table->string('status', 32)->index()->default('pending');
            $table->string('payment_reference', 191)->nullable()->index();
            $table->string('payment_link', 512)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('marketing_email_opt_in')->default(false)->after('email_verified_at');
            $table->timestamp('marketing_unsubscribed_at')->nullable()->after('marketing_email_opt_in');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['marketing_email_opt_in', 'marketing_unsubscribed_at']);
        });
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('email_deliveries');
    }
};
