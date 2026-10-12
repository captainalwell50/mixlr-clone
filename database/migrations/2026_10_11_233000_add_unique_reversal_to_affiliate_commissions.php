<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliate_commissions', function (Blueprint $table) {
            $table->unique('reversal_of_commission_id', 'aff_comm_reversal_unique');
        });
    }

    public function down(): void
    {
        Schema::table('affiliate_commissions', function (Blueprint $table) {
            $table->dropUnique('aff_comm_reversal_unique');
        });
    }
};
