<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gallery_images', function (Blueprint $table) {
            $table->string('media_type', 16)->default('image')->after('path');
            $table->unsignedSmallInteger('duration_seconds')->nullable()->after('media_type');
            $table->string('poster_path')->nullable()->after('duration_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('gallery_images', function (Blueprint $table) {
            $table->dropColumn(['media_type', 'duration_seconds', 'poster_path']);
        });
    }
};
