<?php

namespace Tests\Feature;

use App\Enums\OrgRole;
use App\Enums\SubscriptionStatus;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaystackWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejects_invalid_signature(): void
    {
        config(['services.paystack.secret_key' => 'sk_test_secret']);

        $this->postJson('/webhooks/paystack', [
            'event' => 'charge.success',
            'data' => [],
        ], [
            'x-paystack-signature' => 'invalid',
        ])->assertStatus(400);
    }

    public function test_activates_subscription_on_charge_success(): void
    {
        config(['services.paystack.secret_key' => 'sk_test_secret']);

        $org = Organization::query()->create([
            'name' => 'Paid Org',
            'slug' => 'paid-org',
            'paystack_customer_code' => 'CUS_test123',
        ]);
        $user = User::factory()->create();
        $org->users()->attach($user->id, ['role' => OrgRole::Owner->value]);

        $plan = Plan::query()->create([
            'name' => 'Starter',
            'slug' => 'starter',
            'paystack_plan_code' => 'PLN_starter',
            'amount' => 500000,
            'currency' => 'NGN',
            'interval' => 'monthly',
            'limits' => ['max_streams' => 1],
            'is_active' => true,
        ]);

        Subscription::query()->create([
            'organization_id' => $org->id,
            'status' => SubscriptionStatus::Trialing,
            'trial_ends_at' => now()->addDays(7),
        ]);

        $payload = json_encode([
            'event' => 'charge.success',
            'data' => [
                'status' => 'success',
                'customer' => ['customer_code' => 'CUS_test123'],
                'metadata' => [
                    'organization_id' => $org->id,
                    'plan_id' => $plan->id,
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $signature = hash_hmac('sha512', $payload, 'sk_test_secret');

        $this->call(
            'POST',
            '/webhooks/paystack',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_PAYSTACK_SIGNATURE' => $signature,
            ],
            $payload,
        )->assertOk();

        $this->assertDatabaseHas('subscriptions', [
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active->value,
        ]);
    }
}
