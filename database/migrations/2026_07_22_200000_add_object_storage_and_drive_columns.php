<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recordings', function (Blueprint $table) {
            $table->string('storage_disk', 32)->default('mediamtx_recordings')->after('relative_path');
            $table->string('object_key', 2048)->nullable()->after('storage_disk');
            $table->timestamp('synced_at')->nullable()->after('object_key');
            $table->timestamp('local_deleted_at')->nullable()->after('synced_at');
        });

        Schema::table('studio_audio_assets', function (Blueprint $table) {
            $table->string('storage_provider', 32)->default('local')->after('path');
            $table->string('external_id', 255)->nullable()->after('storage_provider');
        });

        Schema::create('organization_drive_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('connected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('google_email')->nullable();
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->string('root_folder_id')->nullable();
            $table->timestamps();

            $table->unique('organization_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_drive_connections');

        Schema::table('studio_audio_assets', function (Blueprint $table) {
            $table->dropColumn(['storage_provider', 'external_id']);
        });

        Schema::table('recordings', function (Blueprint $table) {
            $table->dropColumn(['storage_disk', 'object_key', 'synced_at', 'local_deleted_at']);
        });
    }
};
