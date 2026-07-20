<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $adminEmail = config('app.admin_email') ?: 'admin@example.com';

        // Avoid UserFactory/fake() so production (`composer --no-dev`) can seed.
        User::query()->updateOrCreate(
            ['email' => $adminEmail],
            [
                'name' => 'Church admin',
                'password' => Hash::make('password'),
                'is_admin' => true,
                'email_verified_at' => now(),
            ],
        );

        $this->call(PlanSeeder::class);
        $this->call(ChurchDemoSeeder::class);
    }
}
