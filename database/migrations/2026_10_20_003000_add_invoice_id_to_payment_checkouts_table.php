<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_checkouts', function (Blueprint $table) {
            $table->foreignId('invoice_id')->nullable()->after('plan_id')->constrained('invoices')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payment_checkouts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invoice_id');
        });
    }
};
