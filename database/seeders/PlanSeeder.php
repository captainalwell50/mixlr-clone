<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Support\PlanFeatureCatalog;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $base = PlanFeatureCatalog::defaultLimits();

        Plan::query()->updateOrCreate(
            ['slug' => 'free'],
            [
                'name' => 'Free',
                'paystack_plan_code' => null,
                'amount' => 0,
                'currency' => env('PAYSTACK_CURRENCY', 'NGN'),
                'interval' => 'monthly',
                'limits' => array_merge($base, [
                    'max_streams' => 1,
                    'max_recordings' => 20,
                    'storage_bytes' => 2 * 1024 * 1024 * 1024,
                    'live_streaming' => true,
                    'channel_page' => true,
                    'followable_profile' => true,
                    'listener_notifications' => false,
                    'gallery' => true,
                    'embed_player' => true,
                    'embed_whitelist' => false,
                    'listener_analytics' => false,
                    'private_streams' => false,
                    'donations' => false,
                    'stream_fallback' => false,
                    'advanced_scheduling' => false,
                    'rtmp_ingest' => false,
                    'radio_directory' => false,
                    'priority_support' => false,
                    'multi_channel' => false,
                    'api_access' => false,
                    'branded_apps' => false,
                    'account_manager' => false,
                ]),
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
                'limits' => array_merge($base, [
                    'max_streams' => 1,
                    'max_recordings' => 120,
                    'storage_bytes' => 25 * 1024 * 1024 * 1024,
                    'live_streaming' => true,
                    'channel_page' => true,
                    'followable_profile' => true,
                    'listener_notifications' => true,
                    'gallery' => true,
                    'embed_player' => true,
                    'embed_whitelist' => false,
                    'listener_analytics' => true,
                    'private_streams' => true,
                    'donations' => true,
                    'stream_fallback' => false,
                    'advanced_scheduling' => false,
                    'rtmp_ingest' => false,
                    'radio_directory' => false,
                    'priority_support' => false,
                    'multi_channel' => false,
                    'api_access' => false,
                    'branded_apps' => false,
                    'account_manager' => false,
                ]),
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
                'limits' => array_merge($base, [
                    'max_streams' => 5,
                    'max_recordings' => 240,
                    'storage_bytes' => 100 * 1024 * 1024 * 1024,
                    'live_streaming' => true,
                    'channel_page' => true,
                    'followable_profile' => true,
                    'listener_notifications' => true,
                    'gallery' => true,
                    'embed_player' => true,
                    'embed_whitelist' => true,
                    'listener_analytics' => true,
                    'private_streams' => true,
                    'donations' => true,
                    'stream_fallback' => true,
                    'advanced_scheduling' => true,
                    'rtmp_ingest' => true,
                    'radio_directory' => true,
                    'priority_support' => true,
                    'multi_channel' => true,
                    'api_access' => false,
                    'branded_apps' => false,
                    'account_manager' => false,
                ]),
                'is_active' => true,
                'sort_order' => 2,
            ],
        );
    }
}
