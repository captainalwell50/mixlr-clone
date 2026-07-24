<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->string('scripture_ref')->nullable()->after('show_listener_count');
            $table->text('scripture_text')->nullable()->after('scripture_ref');
            $table->timestamp('scripture_updated_at')->nullable()->after('scripture_text');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn(['scripture_ref', 'scripture_text', 'scripture_updated_at']);
        });
    }
};
