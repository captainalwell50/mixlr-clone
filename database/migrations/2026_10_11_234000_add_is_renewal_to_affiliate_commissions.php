<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliate_commissions', function (Blueprint $table) {
            $table->boolean('is_renewal')->default(false)->after('commission_amount_minor');
        });
    }

    public function down(): void
    {
        Schema::table('affiliate_commissions', function (Blueprint $table) {
            $table->dropColumn('is_renewal');
        });
    }
};
