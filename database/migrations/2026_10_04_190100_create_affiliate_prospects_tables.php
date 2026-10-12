<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_prospects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_account_id')->constrained()->cascadeOnDelete();
            $table->string('outreach_method', 32);
            $table->string('church_name');
            $table->string('normalized_church_name');
            $table->char('country_code', 2);
            $table->string('state_region', 120);
            $table->string('city', 120);
            $table->string('address')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('website')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_role', 120)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 32);
            $table->timestamp('planned_for')->nullable();
            $table->timestamp('reserved_at');
            $table->timestamp('reserved_until');
            $table->string('active_reservation_key', 64)->nullable()->unique();
            $table->foreignId('converted_organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->timestamp('contacted_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['affiliate_account_id', 'status']);
            $table->index(['country_code', 'state_region', 'city', 'normalized_church_name']);
            $table->index('reserved_until');
        });

        Schema::create('affiliate_prospect_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_prospect_id')->constrained()->cascadeOnDelete();
            $table->string('action', 32);
            $table->text('reason');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['affiliate_prospect_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_prospect_audits');
        Schema::dropIfExists('affiliate_prospects');
    }
};
