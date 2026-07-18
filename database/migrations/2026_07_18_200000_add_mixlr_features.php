<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 32)->default('member'); // owner|admin|member
            $table->timestamps();
            $table->unique(['organization_id', 'user_id']);
        });

        Schema::table('streams', function (Blueprint $table) {
            $table->string('description', 1000)->nullable()->after('title');
            $table->boolean('is_public')->default(true)->after('description');
            $table->string('stream_key', 64)->nullable()->after('uuid');
            $table->boolean('chat_enabled')->default(true)->after('is_public');
        });

        foreach (DB::table('streams')->whereNull('stream_key')->pluck('id') as $id) {
            DB::table('streams')->where('id', $id)->update([
                'stream_key' => Str::random(40),
            ]);
        }

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stream_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('display_name', 80);
            $table->string('body', 500);
            $table->timestamps();
            $table->index(['stream_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');

        Schema::table('streams', function (Blueprint $table) {
            $table->dropColumn(['description', 'is_public', 'stream_key', 'chat_enabled']);
        });

        Schema::dropIfExists('organization_user');
    }
};
