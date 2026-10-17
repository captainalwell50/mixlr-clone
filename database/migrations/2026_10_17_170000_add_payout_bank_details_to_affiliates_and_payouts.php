<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliate_accounts', function (Blueprint $table) {
            $table->string('bank_name', 120)->nullable()->after('city');
            $table->string('bank_code', 30)->nullable()->after('bank_name');
            $table->text('account_name')->nullable()->after('bank_code');
            $table->text('account_number')->nullable()->after('account_name');
        });

        Schema::table('affiliate_payouts', function (Blueprint $table) {
            $table->string('bank_name', 120)->nullable()->after('amount_minor');
            $table->string('bank_code', 30)->nullable()->after('bank_name');
            $table->text('account_name')->nullable()->after('bank_code');
            $table->text('account_number')->nullable()->after('account_name');
        });
    }

    public function down(): void
    {
        Schema::table('affiliate_payouts', function (Blueprint $table) {
            $table->dropColumn(['bank_name', 'bank_code', 'account_name', 'account_number']);
        });

        Schema::table('affiliate_accounts', function (Blueprint $table) {
            $table->dropColumn(['bank_name', 'bank_code', 'account_name', 'account_number']);
        });
    }
};
