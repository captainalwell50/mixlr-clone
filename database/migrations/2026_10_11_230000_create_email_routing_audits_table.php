<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_routing_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action'); // destination_proposed, verification_requested, verification_completed, routing_switched, sync_failed, pending_cancelled
            $table->string('previous_destination')->nullable();
            $table->string('proposed_destination')->nullable();
            $table->string('status')->default('success'); // success, failed, pending, verified
            $table->json('details')->nullable();
            $table->timestamps();

            $table->index('action');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_routing_audits');
    }
};
