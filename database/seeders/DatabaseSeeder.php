<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $adminEmail = config('app.admin_email') ?: 'admin@example.com';

        User::factory()->create([
            'name' => 'Church admin',
            'email' => $adminEmail,
            'is_admin' => true,
        ]);

        $this->call(ChurchDemoSeeder::class);
    }
}
