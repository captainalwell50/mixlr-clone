<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->boolean('giving_enabled')->default(false)->after('social_feed_url');
            $table->string('giving_url', 500)->nullable()->after('giving_enabled');
            $table->string('giving_account_name', 255)->nullable()->after('giving_url');
            $table->string('giving_bank_name', 255)->nullable()->after('giving_account_name');
            $table->string('giving_account_number', 64)->nullable()->after('giving_bank_name');
            $table->string('giving_note', 255)->nullable()->after('giving_account_number');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn([
                'giving_enabled',
                'giving_url',
                'giving_account_name',
                'giving_bank_name',
                'giving_account_number',
                'giving_note',
            ]);
        });
    }
};
