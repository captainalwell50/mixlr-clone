<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gallery_images', function (Blueprint $table) {
            $table->index(['stream_id', 'event_id'], 'gallery_images_stream_event_index');
        });
    }

    public function down(): void
    {
        Schema::table('gallery_images', function (Blueprint $table) {
            $table->dropIndex('gallery_images_stream_event_index');
        });
    }
};
