<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->char('country_code', 2)->nullable()->after('name');
            $table->string('state_region', 120)->nullable()->after('country_code');
            $table->string('city', 120)->nullable()->after('state_region');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['country_code', 'state_region', 'city']);
        });
    }
};
