<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('tagline', 255)->nullable()->after('slug');
            $table->string('logo_path')->nullable()->after('tagline');
            $table->string('artwork_path')->nullable()->after('logo_path');
            $table->string('theme_color', 32)->nullable()->after('artwork_path');
            $table->string('support_url')->nullable()->after('theme_color');
            $table->boolean('is_public')->default(true)->after('support_url');
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stream_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('title');
            $table->string('description', 2000)->nullable();
            $table->string('artwork_path')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->string('status', 32)->default('scheduled'); // scheduled|live|ended
            $table->string('access', 32)->default('public'); // public|unlisted|private
            $table->string('access_password')->nullable();
            $table->boolean('chat_enabled')->default(true);
            $table->boolean('show_listener_count')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['status', 'scheduled_at']);
        });

        Schema::create('channel_follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'organization_id']);
        });

        Schema::create('event_hearts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['event_id', 'user_id']);
        });

        Schema::create('listener_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('session_key', 64);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('last_seen_at');
            $table->string('country', 8)->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'session_key']);
            $table->index(['event_id', 'last_seen_at']);
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->foreignId('event_id')->nullable()->after('stream_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('event_id');
        });
        Schema::dropIfExists('listener_sessions');
        Schema::dropIfExists('event_hearts');
        Schema::dropIfExists('channel_follows');
        Schema::dropIfExists('events');
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn([
                'tagline', 'logo_path', 'artwork_path', 'theme_color', 'support_url', 'is_public',
            ]);
        });
    }
};
