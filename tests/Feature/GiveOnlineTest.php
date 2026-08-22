<?php

namespace Tests\Feature;

use App\Enums\EventAccess;
use App\Enums\EventStatus;
use App\Enums\OrgRole;
use App\Enums\StreamStatus;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Stream;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GiveOnlineTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_enable_giving_with_url(): void
    {
        [$org, $manager] = $this->orgWithManager();

        $this->actingAs($manager)
            ->put(route('admin.organizations.customise.giving', $org), [
                'giving_enabled' => '1',
                'giving_url' => 'https://paystack.com/pay/grace',
                'giving_note' => 'Sunday offering',
            ])
            ->assertRedirect(route('admin.organizations.customise', $org));

        $org->refresh();
        $this->assertTrue($org->giving_enabled);
        $this->assertSame('https://paystack.com/pay/grace', $org->giving_url);
        $this->assertSame('Sunday offering', $org->giving_note);
        $this->assertTrue($org->givingIsPublic());
    }

    public function test_manager_can_enable_giving_with_account_details(): void
    {
        [$org, $manager] = $this->orgWithManager();

        $this->actingAs($manager)
            ->put(route('admin.organizations.customise.giving', $org), [
                'giving_enabled' => '1',
                'giving_account_name' => 'Grace Chapel',
                'giving_bank_name' => 'GTBank',
                'giving_account_number' => '0123456789',
            ])
            ->assertRedirect(route('admin.organizations.customise', $org));

        $org->refresh();
        $this->assertTrue($org->givingIsPublic());
        $this->assertSame('0123456789', $org->publicGiving()['account_number']);
    }

    public function test_cannot_enable_giving_without_a_destination(): void
    {
        [$org, $manager] = $this->orgWithManager();

        $this->actingAs($manager)
            ->from(route('admin.organizations.customise', $org))
            ->put(route('admin.organizations.customise.giving', $org), [
                'giving_enabled' => '1',
            ])
            ->assertRedirect(route('admin.organizations.customise', $org))
            ->assertSessionHasErrors('giving_url');

        $this->assertFalse($org->fresh()->giving_enabled);
    }

    public function test_enabling_giving_can_reuse_existing_support_url(): void
    {
        [$org, $manager] = $this->orgWithManager();
        $org->forceFill(['support_url' => 'https://flutterwave.com/pay/grace'])->save();

        $this->actingAs($manager)
            ->put(route('admin.organizations.customise.giving', $org), [
                'giving_enabled' => '1',
            ])
            ->assertRedirect(route('admin.organizations.customise', $org));

        $this->assertTrue($org->fresh()->givingIsPublic());
        $this->assertSame('https://flutterwave.com/pay/grace', $org->fresh()->givingUrl());
    }

    public function test_outsider_and_member_cannot_edit_giving(): void
    {
        [$org] = $this->orgWithManager();
        $other = Organization::query()->create(['name' => 'Other', 'slug' => 'other-give']);
        $outsider = User::factory()->create(['is_admin' => false]);
        $other->users()->attach($outsider->id, ['role' => OrgRole::Owner->value]);

        $member = User::factory()->create(['is_admin' => false]);
        $org->users()->attach($member->id, ['role' => OrgRole::Member->value]);

        $payload = [
            'giving_enabled' => '1',
            'giving_url' => 'https://example.com/give',
        ];

        $this->actingAs($outsider)
            ->put(route('admin.organizations.customise.giving', $org), $payload)
            ->assertForbidden();

        $this->actingAs($member)
            ->put(route('admin.organizations.customise.giving', $org), $payload)
            ->assertForbidden();
    }

    public function test_listen_page_shows_give_button_for_guests_when_configured(): void
    {
        [$org, , $stream] = $this->orgWithManager(withStream: true);
        $org->forceFill([
            'is_public' => true,
            'giving_enabled' => true,
            'giving_url' => 'https://paystack.com/pay/grace',
            'giving_account_name' => 'Grace Chapel',
            'giving_bank_name' => 'GTBank',
            'giving_account_number' => '0123456789',
            'giving_note' => 'Sunday offering',
        ])->save();
        $stream->forceFill(['is_public' => true, 'status' => StreamStatus::Live])->save();

        $this->get(route('listen.stream', $stream))
            ->assertOk()
            ->assertSee('Give online', false)
            ->assertSee('Sunday offering', false)
            ->assertSee('0123456789', false)
            ->assertSee('https://paystack.com/pay/grace', false)
            ->assertSee('Open giving page', false);
    }

    public function test_listen_page_hides_give_button_when_not_configured(): void
    {
        [$org, , $stream] = $this->orgWithManager(withStream: true);
        $org->forceFill(['is_public' => true])->save();
        $stream->forceFill(['is_public' => true, 'status' => StreamStatus::Live])->save();

        $this->get(route('listen.stream', $stream))
            ->assertOk()
            ->assertDontSee('Give online', false)
            ->assertDontSee('Open giving page', false);
    }

    public function test_event_and_embed_pages_show_give_when_configured(): void
    {
        [$org] = $this->orgWithManager();
        $org->forceFill([
            'is_public' => true,
            'giving_enabled' => true,
            'giving_url' => 'https://paystack.com/pay/event',
        ])->save();

        $event = Event::query()->create([
            'organization_id' => $org->id,
            'title' => 'Morning Service',
            'status' => EventStatus::Live,
            'access' => EventAccess::Public,
            'started_at' => now(),
        ]);

        $this->get(route('events.show', $event))
            ->assertOk()
            ->assertSee('Give online', false)
            ->assertSee('https://paystack.com/pay/event', false);

        $this->get(route('events.embed', $event))
            ->assertOk()
            ->assertSee('Give online', false);
    }

    public function test_listen_api_includes_giving_payload_only_when_public(): void
    {
        [$org, , $stream] = $this->orgWithManager(withStream: true);
        $org->forceFill(['is_public' => true])->save();
        $stream->forceFill([
            'is_public' => true,
            'status' => StreamStatus::Live,
            'uuid' => $stream->uuid,
        ])->save();

        $this->getJson('/api/v1/listen/'.$stream->uuid)
            ->assertOk()
            ->assertJsonPath('organization.giving', null);

        $org->forceFill([
            'giving_enabled' => true,
            'giving_url' => 'https://paystack.com/pay/api',
            'giving_note' => 'Building fund',
        ])->save();

        $this->getJson('/api/v1/listen/'.$stream->uuid)
            ->assertOk()
            ->assertJsonPath('organization.giving.enabled', true)
            ->assertJsonPath('organization.giving.url', 'https://paystack.com/pay/api')
            ->assertJsonPath('organization.giving.note', 'Building fund');
    }

    /**
     * @return array{0: Organization, 1: User, 2?: Stream}
     */
    private function orgWithManager(bool $withStream = false): array
    {
        $org = Organization::query()->create([
            'name' => 'Grace Chapel',
            'slug' => 'grace-give-'.uniqid(),
            'is_public' => true,
        ]);
        $manager = User::factory()->create(['is_admin' => false]);
        $org->users()->attach($manager->id, ['role' => OrgRole::Admin->value]);

        if (! $withStream) {
            return [$org, $manager];
        }

        $stream = Stream::query()->create([
            'organization_id' => $org->id,
            'uuid' => fake()->uuid(),
            'title' => 'Main',
            'status' => StreamStatus::Offline,
            'is_public' => true,
        ]);

        return [$org, $manager, $stream];
    }
}
