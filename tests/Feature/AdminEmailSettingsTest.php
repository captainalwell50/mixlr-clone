<?php

namespace Tests\Feature;

use App\Models\EmailRoutingAudit;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminEmailSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'is_admin' => true,
            'email' => 'admin@soundmix.live',
        ]);

        $this->regularUser = User::factory()->create([
            'is_admin' => false,
            'email' => 'broadcaster@church.org',
        ]);

        $org = \App\Models\Organization::query()->create([
            'name' => 'Grace Church',
            'slug' => 'grace-church-'.uniqid(),
        ]);
        $this->regularUser->organizations()->attach($org->id, [
            'role' => \App\Enums\OrgRole::Owner->value,
        ]);
    }

    public function test_super_admin_can_view_email_settings(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.settings.email'));

        $response->assertOk();
        $response->assertSee('Inbound Email Routing');
        $response->assertSee('soundmix.live');
        $response->assertSee('support@soundmix.live');
        $response->assertSee('hello@soundmix.live');
        $response->assertSee('billing@soundmix.live');
        $response->assertSee('Outbound Application Email');
        $response->assertSee('Open Outbound Delivery Ledger');
    }

    public function test_non_admin_cannot_access_email_settings(): void
    {
        // Unauthenticated
        $this->get(route('admin.settings.email'))
            ->assertRedirect(route('login'));

        // Regular authenticated user
        $this->actingAs($this->regularUser)
            ->get(route('admin.settings.email'))
            ->assertForbidden();

        $this->actingAs($this->regularUser)
            ->put(route('admin.settings.email.destination'), ['destination_email' => 'hacker@example.com'])
            ->assertForbidden();

        $this->actingAs($this->regularUser)
            ->post(route('admin.settings.email.verify'))
            ->assertForbidden();

        $this->actingAs($this->regularUser)
            ->post(route('admin.settings.email.sync'))
            ->assertForbidden();
    }

    public function test_current_destination_renders_and_aliases_inherit_it(): void
    {
        SiteSetting::putValue(SiteSetting::KEY_EMAIL_ROUTING_ACTIVE_DESTINATION, 'inbox@churchops.org');

        $response = $this->actingAs($this->admin)->get(route('admin.settings.email'));

        $response->assertOk();
        $response->assertSee('inbox@churchops.org');
        // Check managed aliases inherit active destination
        $response->assertSee('support@soundmix.live');
        $response->assertSee('hello@soundmix.live');
        $response->assertSee('billing@soundmix.live');
    }

    public function test_new_destination_becomes_pending_and_requests_cloudflare_verification(): void
    {
        config([
            'services.cloudflare.email_api_token' => 'cf_secret_token_123',
            'services.cloudflare.zone_id' => 'zone_test_456',
            'services.cloudflare.account_id' => 'acc_test_789',
            'services.cloudflare.domain' => 'soundmix.live',
        ]);

        Http::fake([
            'https://api.cloudflare.com/client/v4/accounts/acc_test_789/email/routing/addresses' => function ($request) {
                if ($request->method() === 'POST') {
                    return Http::response([
                        'success' => true,
                        'result' => [
                            'id' => 'addr_new_1',
                            'email' => 'newdest@example.com',
                            'verified' => null,
                        ],
                    ], 200);
                }

                return Http::response([
                    'success' => true,
                    'result' => [],
                ], 200);
            },
        ]);

        SiteSetting::putValue(SiteSetting::KEY_EMAIL_ROUTING_ACTIVE_DESTINATION, 'active@soundmix.live');

        $response = $this->actingAs($this->admin)->put(route('admin.settings.email.destination'), [
            'destination_email' => 'newdest@example.com',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $this->assertSame('newdest@example.com', SiteSetting::emailRoutingPendingDestination());
        $this->assertSame('active@soundmix.live', SiteSetting::emailRoutingActiveDestination()); // active untouched!
        $this->assertSame('pending', SiteSetting::emailRoutingDestinationStatus());

        $this->assertDatabaseHas('email_routing_audits', [
            'action' => EmailRoutingAudit::ACTION_DESTINATION_PROPOSED,
            'proposed_destination' => 'newdest@example.com',
            'actor_id' => $this->admin->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('email_routing_audits', [
            'action' => EmailRoutingAudit::ACTION_VERIFICATION_REQUESTED,
            'proposed_destination' => 'newdest@example.com',
            'actor_id' => $this->admin->id,
        ]);
    }

    public function test_existing_destination_remains_active_while_pending(): void
    {
        SiteSetting::putValue(SiteSetting::KEY_EMAIL_ROUTING_ACTIVE_DESTINATION, 'active@example.com');
        SiteSetting::putValue(SiteSetting::KEY_EMAIL_ROUTING_PENDING_DESTINATION, 'pending@example.com');
        SiteSetting::putValue(SiteSetting::KEY_EMAIL_ROUTING_DESTINATION_STATUS, 'pending');

        $response = $this->actingAs($this->admin)->get(route('admin.settings.email'));

        $response->assertOk();
        $response->assertSee('active@example.com');
        $response->assertSee('pending@example.com');
        $response->assertSee('Your current forwarding inbox remains active until the new address is verified');
    }

    public function test_verification_check_updates_status_when_verified(): void
    {
        config([
            'services.cloudflare.email_api_token' => 'cf_secret_token_123',
            'services.cloudflare.zone_id' => 'zone_test_456',
            'services.cloudflare.account_id' => 'acc_test_789',
        ]);

        SiteSetting::putValue(SiteSetting::KEY_EMAIL_ROUTING_ACTIVE_DESTINATION, 'old@example.com');
        SiteSetting::putValue(SiteSetting::KEY_EMAIL_ROUTING_PENDING_DESTINATION, 'verified@example.com');
        SiteSetting::putValue(SiteSetting::KEY_EMAIL_ROUTING_DESTINATION_STATUS, 'pending');

        Http::fake([
            'https://api.cloudflare.com/client/v4/accounts/acc_test_789/email/routing/addresses' => Http::response([
                'success' => true,
                'result' => [
                    [
                        'id' => 'addr_v1',
                        'email' => 'verified@example.com',
                        'verified' => '2026-10-11T20:00:00Z',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.settings.email.verify'));

        $response->assertRedirect();
        $this->assertSame('verified', SiteSetting::emailRoutingDestinationStatus());

        $this->assertDatabaseHas('email_routing_audits', [
            'action' => EmailRoutingAudit::ACTION_VERIFICATION_COMPLETED,
            'proposed_destination' => 'verified@example.com',
            'status' => 'verified',
        ]);
    }

    public function test_verified_destination_can_be_promoted_and_synced(): void
    {
        config([
            'services.cloudflare.email_api_token' => 'cf_secret_token_123',
            'services.cloudflare.zone_id' => 'zone_test_456',
            'services.cloudflare.account_id' => 'acc_test_789',
        ]);

        SiteSetting::putValue(SiteSetting::KEY_EMAIL_ROUTING_ACTIVE_DESTINATION, 'old@example.com');
        SiteSetting::putValue(SiteSetting::KEY_EMAIL_ROUTING_PENDING_DESTINATION, 'promoted@example.com');
        SiteSetting::putValue(SiteSetting::KEY_EMAIL_ROUTING_DESTINATION_STATUS, 'verified');

        Http::fake([
            'https://api.cloudflare.com/client/v4/zones/zone_test_456/email/routing/rules' => Http::response([
                'success' => true,
                'result' => [
                    [
                        'id' => 'rule_support',
                        'name' => 'support rule',
                        'matchers' => [['field' => 'to', 'type' => 'literal', 'value' => 'support@soundmix.live']],
                        'actions' => [['type' => 'forward', 'value' => ['old@example.com']]],
                    ],
                    [
                        'id' => 'rule_hello',
                        'name' => 'hello rule',
                        'matchers' => [['field' => 'to', 'type' => 'literal', 'value' => 'hello@soundmix.live']],
                        'actions' => [['type' => 'forward', 'value' => ['old@example.com']]],
                    ],
                    [
                        'id' => 'rule_billing',
                        'name' => 'billing rule',
                        'matchers' => [['field' => 'to', 'type' => 'literal', 'value' => 'billing@soundmix.live']],
                        'actions' => [['type' => 'forward', 'value' => ['old@example.com']]],
                    ],
                ],
            ], 200),
            'https://api.cloudflare.com/client/v4/zones/zone_test_456/email/routing/rules/*' => Http::response([
                'success' => true,
                'result' => [],
            ], 200),
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.settings.email.sync'));

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $this->assertSame('promoted@example.com', SiteSetting::emailRoutingActiveDestination());
        $this->assertNull(SiteSetting::emailRoutingPendingDestination());
        $this->assertSame('verified', SiteSetting::emailRoutingDestinationStatus());

        $this->assertDatabaseHas('email_routing_audits', [
            'action' => EmailRoutingAudit::ACTION_ROUTING_SWITCHED,
            'previous_destination' => 'old@example.com',
            'proposed_destination' => 'promoted@example.com',
            'status' => 'success',
        ]);
    }

    public function test_cloudflare_api_failure_preserves_old_destination(): void
    {
        config([
            'services.cloudflare.email_api_token' => 'cf_secret_token_123',
            'services.cloudflare.zone_id' => 'zone_test_456',
            'services.cloudflare.account_id' => 'acc_test_789',
        ]);

        SiteSetting::putValue(SiteSetting::KEY_EMAIL_ROUTING_ACTIVE_DESTINATION, 'old@example.com');
        SiteSetting::putValue(SiteSetting::KEY_EMAIL_ROUTING_PENDING_DESTINATION, 'promoted@example.com');
        SiteSetting::putValue(SiteSetting::KEY_EMAIL_ROUTING_DESTINATION_STATUS, 'verified');

        Http::fake([
            'https://api.cloudflare.com/client/v4/zones/zone_test_456/email/routing/rules' => Http::response([
                'success' => true,
                'result' => [
                    [
                        'id' => 'rule_support',
                        'matchers' => [['field' => 'to', 'type' => 'literal', 'value' => 'support@soundmix.live']],
                        'actions' => [['type' => 'forward', 'value' => ['old@example.com']]],
                    ],
                ],
            ], 200),
            'https://api.cloudflare.com/client/v4/zones/zone_test_456/email/routing/rules/*' => Http::response([
                'success' => false,
                'errors' => [['message' => 'Internal Cloudflare Error']],
            ], 500),
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.settings.email.sync'));

        $response->assertRedirect();
        $response->assertSessionHas('error');

        // Preserved!
        $this->assertSame('old@example.com', SiteSetting::emailRoutingActiveDestination());
        $this->assertSame('promoted@example.com', SiteSetting::emailRoutingPendingDestination());

        $this->assertDatabaseHas('email_routing_audits', [
            'action' => EmailRoutingAudit::ACTION_SYNC_FAILED,
            'previous_destination' => 'old@example.com',
            'proposed_destination' => 'promoted@example.com',
            'status' => 'failed',
        ]);
    }

    public function test_partial_sync_cannot_silently_mark_success(): void
    {
        config([
            'services.cloudflare.email_api_token' => 'cf_secret_token_123',
            'services.cloudflare.zone_id' => 'zone_test_456',
            'services.cloudflare.account_id' => 'acc_test_789',
        ]);

        SiteSetting::putValue(SiteSetting::KEY_EMAIL_ROUTING_ACTIVE_DESTINATION, 'old@example.com');
        SiteSetting::putValue(SiteSetting::KEY_EMAIL_ROUTING_PENDING_DESTINATION, 'promoted@example.com');
        SiteSetting::putValue(SiteSetting::KEY_EMAIL_ROUTING_DESTINATION_STATUS, 'verified');

        Http::fake([
            'https://api.cloudflare.com/client/v4/zones/zone_test_456/email/routing/rules' => Http::response([
                'success' => true,
                'result' => [
                    [
                        'id' => 'rule_support',
                        'matchers' => [['field' => 'to', 'type' => 'literal', 'value' => 'support@soundmix.live']],
                        'actions' => [['type' => 'forward', 'value' => ['old@example.com']]],
                    ],
                    [
                        'id' => 'rule_hello',
                        'matchers' => [['field' => 'to', 'type' => 'literal', 'value' => 'hello@soundmix.live']],
                        'actions' => [['type' => 'forward', 'value' => ['old@example.com']]],
                    ],
                ],
            ], 200),
            'https://api.cloudflare.com/client/v4/zones/zone_test_456/email/routing/rules/rule_support' => Http::response([
                'success' => true,
                'result' => [],
            ], 200),
            'https://api.cloudflare.com/client/v4/zones/zone_test_456/email/routing/rules/rule_hello' => Http::response([
                'success' => false,
                'errors' => [['message' => 'Rule limit reached on Cloudflare']],
            ], 400),
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.settings.email.sync'));

        $response->assertRedirect();
        $response->assertSessionHas('error');

        // Old destination preserved locally
        $this->assertSame('old@example.com', SiteSetting::emailRoutingActiveDestination());
        $this->assertDatabaseHas('email_routing_audits', [
            'action' => EmailRoutingAudit::ACTION_SYNC_FAILED,
            'status' => 'failed',
        ]);
    }

    public function test_token_and_secrets_never_rendered_in_html_or_audits(): void
    {
        $secretToken = 'cf_super_secret_token_xyz987';
        config([
            'services.cloudflare.email_api_token' => $secretToken,
            'services.cloudflare.zone_id' => 'zone_test_456',
            'services.cloudflare.account_id' => 'acc_test_789',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.settings.email'));

        $response->assertOk();
        $this->assertStringNotContainsString($secretToken, $response->getContent());
        $this->assertStringNotContainsString('Bearer', $response->getContent());

        foreach (EmailRoutingAudit::all() as $audit) {
            $this->assertStringNotContainsString($secretToken, json_encode($audit->details ?? []));
        }
    }

    public function test_manual_mode_when_cloudflare_credentials_not_configured(): void
    {
        // Credentials null
        config([
            'services.cloudflare.email_api_token' => null,
            'services.cloudflare.zone_id' => null,
            'services.cloudflare.account_id' => null,
        ]);

        SiteSetting::putValue(SiteSetting::KEY_EMAIL_ROUTING_ACTIVE_DESTINATION, 'initial@soundmix.live');

        // Propose new destination
        $this->actingAs($this->admin)->put(route('admin.settings.email.destination'), [
            'destination_email' => 'manual@example.com',
        ])->assertRedirect();

        $this->assertSame('manual@example.com', SiteSetting::emailRoutingPendingDestination());
        $this->assertSame('initial@soundmix.live', SiteSetting::emailRoutingActiveDestination());

        // Confirm manual verification
        $this->actingAs($this->admin)->post(route('admin.settings.email.verify'), [
            'manual_confirm' => '1',
        ])->assertRedirect();

        $this->assertSame('verified', SiteSetting::emailRoutingDestinationStatus());

        // Sync routing in manual mode
        $this->actingAs($this->admin)->post(route('admin.settings.email.sync'))
            ->assertRedirect();

        $this->assertSame('manual@example.com', SiteSetting::emailRoutingActiveDestination());
        $this->assertNull(SiteSetting::emailRoutingPendingDestination());

        $this->assertDatabaseHas('email_routing_audits', [
            'action' => EmailRoutingAudit::ACTION_ROUTING_SWITCHED,
            'proposed_destination' => 'manual@example.com',
            'status' => 'success',
        ]);
    }

    public function test_unconfigured_active_destination_shows_not_configured_locally(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.settings.email'));

        $response->assertOk();
        $response->assertSee('Not configured locally');
        $this->assertNull(SiteSetting::emailRoutingActiveDestination());
    }
}
