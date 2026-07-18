<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recordings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stream_id')->constrained()->cascadeOnDelete();
            $table->string('relative_path', 2048);
            $table->string('duration_raw', 64)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamp('completed_at')->useCurrent();
            $table->timestamps();

            $table->unique(['stream_id', 'relative_path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recordings');
    }
};
