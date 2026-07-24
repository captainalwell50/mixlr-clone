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
 * Rebuilds the primary creator account after accidental DB wipe.
 * Safe to re-run: updateOrCreate on email / slug / stream uuid.
 */
class RecoverCreatorSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('RECOVERY_EMAIL', 'clementalwell.ca@gmail.com');
        $password = env('RECOVERY_PASSWORD');
        if (! is_string($password) || strlen($password) < 8) {
            throw new \RuntimeException('Set RECOVERY_PASSWORD (min 8 chars) in the environment before seeding.');
        }

        $this->call(PlanSeeder::class);

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => env('RECOVERY_NAME', 'Clement Alwell Ohamara'),
                'password' => Hash::make($password),
                'is_admin' => true,
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

        if (! $org->users()->where('users.id', $user->id)->exists()) {
            $org->users()->attach($user->id, ['role' => 'owner']);
        }

        $primaryUuid = env('RECOVERY_STREAM_UUID', 'c048402f-4ccf-49b9-b05f-e633ec727eb8');
        Stream::query()->updateOrCreate(
            ['uuid' => $primaryUuid],
            [
                'organization_id' => $org->id,
                'title' => env('RECOVERY_STREAM_TITLE', 'CLEMENT ALWELL OHAMARA'),
                'description' => 'Restored channel after deploy recovery.',
                'is_public' => true,
                'chat_enabled' => true,
                'status' => StreamStatus::Offline,
                'stream_key' => Str::random(40),
            ],
        );

        // Preserve library folder UUID if present on disk.
        $libraryUuid = 'd3a84265-02c6-4e7d-887d-4f97fc4599bc';
        if ($libraryUuid !== $primaryUuid) {
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
        }
    }
}
