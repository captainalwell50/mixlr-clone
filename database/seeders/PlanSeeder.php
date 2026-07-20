<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
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
                ],
                'is_active' => true,
                'sort_order' => 2,
            ],
        );
    }
}
