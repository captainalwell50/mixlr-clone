<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        Plan::query()->updateOrCreate(
            ['slug' => 'free'],
            [
                'name' => 'Free',
                'paystack_plan_code' => null,
                'amount' => 0,
                'currency' => env('PAYSTACK_CURRENCY', 'NGN'),
                'interval' => 'monthly',
                'limits' => [
                    'max_streams' => 1,
                    'gallery' => true,
                    // Platform-hosted storage (recordings + library). Drive BYO does not count.
                    'storage_bytes' => 2 * 1024 * 1024 * 1024, // 2 GB
                ],
                'is_active' => true,
                'sort_order' => 0,
            ],
        );

        Plan::query()->updateOrCreate(
            ['slug' => 'starter'],
            [
                'name' => 'Starter',
                'paystack_plan_code' => env('PAYSTACK_PLAN_STARTER'),
                'amount' => (int) env('PAYSTACK_PLAN_STARTER_AMOUNT', 500000),
                'currency' => env('PAYSTACK_CURRENCY', 'NGN'),
                'interval' => 'monthly',
                'limits' => [
                    'max_streams' => 1,
                    'gallery' => true,
                    'storage_bytes' => 25 * 1024 * 1024 * 1024, // 25 GB
                ],
                'is_active' => true,
                'sort_order' => 1,
            ],
        );

        Plan::query()->updateOrCreate(
            ['slug' => 'pro'],
            [
                'name' => 'Pro',
                'paystack_plan_code' => env('PAYSTACK_PLAN_PRO'),
                'amount' => (int) env('PAYSTACK_PLAN_PRO_AMOUNT', 1500000),
                'currency' => env('PAYSTACK_CURRENCY', 'NGN'),
                'interval' => 'monthly',
                'limits' => [
                    'max_streams' => 5,
                    'gallery' => true,
                    'storage_bytes' => 100 * 1024 * 1024 * 1024, // 100 GB
                ],
                'is_active' => true,
                'sort_order' => 2,
            ],
        );
    }
}
