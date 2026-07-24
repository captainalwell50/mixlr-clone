<?php

namespace Database\Seeders;

use App\Enums\CreatorType;
use App\Enums\StreamStatus;
use App\Models\Organization;
use App\Models\Stream;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Restores admin + creator accounts after accidental DB wipe.
 */
class RecoverAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $adminPassword = env('RECOVERY_ADMIN_PASSWORD');
        $creatorPassword = env('RECOVERY_CREATOR_PASSWORD');

        if (! is_string($adminPassword) || strlen($adminPassword) < 8) {
            throw new \RuntimeException('Set RECOVERY_ADMIN_PASSWORD (min 8 chars).');
        }
        if (! is_string($creatorPassword) || strlen($creatorPassword) < 8) {
            throw new \RuntimeException('Set RECOVERY_CREATOR_PASSWORD (min 8 chars).');
        }

        $this->call(PlanSeeder::class);

        $admin = User::query()->updateOrCreate(
            ['email' => env('RECOVERY_ADMIN_EMAIL', 'admin@example.com')],
            [
                'name' => 'Church admin',
                'password' => Hash::make($adminPassword),
                'is_admin' => true,
                'email_verified_at' => now(),
            ],
        );

        $creator = User::query()->updateOrCreate(
            ['email' => env('RECOVERY_CREATOR_EMAIL', 'clementalwell.ca@gmail.com')],
            [
                'name' => env('RECOVERY_CREATOR_NAME', 'Clement Alwell Ohamara'),
                'password' => Hash::make($creatorPassword),
                'is_admin' => false,
                'email_verified_at' => now(),
            ],
        );

        $org = Organization::query()->updateOrCreate(
            ['slug' => env('RECOVERY_ORG_SLUG', 'clement-alwell')],
            [
                'name' => env('RECOVERY_ORG_NAME', 'CLEMENT ALWELL OHAMARA'),
                'creator_type' => CreatorType::EventOrganizer->value,
                'is_public' => true,
                'theme_color' => '#14B8A6',
            ],
        );

        $org->users()->syncWithoutDetaching([
            $creator->id => ['role' => 'owner'],
        ]);
        $org->users()->detach($admin->id);

        Stream::query()->updateOrCreate(
            ['uuid' => env('RECOVERY_STREAM_UUID', 'c048402f-4ccf-49b9-b05f-e633ec727eb8')],
            [
                'organization_id' => $org->id,
                'title' => env('RECOVERY_STREAM_TITLE', 'CLEMENT ALWELL OHAMARA'),
                'description' => 'Restored creator channel.',
                'is_public' => true,
                'chat_enabled' => true,
                'status' => StreamStatus::Offline,
                'stream_key' => Str::random(40),
            ],
        );

        $libraryUuid = 'd3a84265-02c6-4e7d-887d-4f97fc4599bc';
        Stream::query()->updateOrCreate(
            ['uuid' => $libraryUuid],
            [
                'organization_id' => $org->id,
                'title' => 'Library archive',
                'is_public' => false,
                'chat_enabled' => false,
                'status' => StreamStatus::Offline,
                'stream_key' => Str::random(40),
            ],
        );

        $this->command?->info("Admin: {$admin->email} (is_admin=true)");
        $this->command?->info("Creator: {$creator->email} (is_admin=false, org={$org->slug})");
    }
}
