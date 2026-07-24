<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recordings', function (Blueprint $table) {
            $table->string('source', 32)->default('mediamtx')->after('stream_id');
            $table->boolean('is_public')->default(false)->after('source');
        });

        // Preserve every existing capture in the public archive. New auto segments
        // are no longer ingested; only Studio uploads are marked public going forward.
        DB::table('recordings')->update([
            'source' => 'mediamtx',
            'is_public' => true,
        ]);
    }

    public function down(): void
    {
        Schema::table('recordings', function (Blueprint $table) {
            $table->dropColumn(['source', 'is_public']);
        });
    }
};
