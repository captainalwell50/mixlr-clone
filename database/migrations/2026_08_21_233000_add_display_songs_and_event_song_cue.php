<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('display_songs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->json('slides');
            $table->timestamps();

            $table->index(['organization_id', 'updated_at']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('song_id')->nullable()->after('scripture_updated_at')
                ->constrained('display_songs')->nullOnDelete();
            $table->string('song_title')->nullable()->after('song_id');
            $table->text('song_text')->nullable()->after('song_title');
            $table->unsignedSmallInteger('song_slide_index')->nullable()->after('song_text');
            $table->unsignedSmallInteger('song_slide_count')->nullable()->after('song_slide_index');
            $table->timestamp('song_updated_at')->nullable()->after('song_slide_count');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('song_id');
            $table->dropColumn([
                'song_title',
                'song_text',
                'song_slide_index',
                'song_slide_count',
                'song_updated_at',
            ]);
        });

        Schema::dropIfExists('display_songs');
    }
};
