<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\StreamStatus;
use App\Models\Event;
use App\Models\Organization;
use App\Models\SiteSetting;
use App\Models\Stream;
use App\Models\User;
use App\Support\WhatsAppSupport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppSupportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SiteSetting::clearRuntimeCache();
        \Illuminate\Support\Facades\Cache::flush();

        // Default to a known test WhatsApp number
        config(['services.whatsapp.enabled' => true]);
        config(['services.whatsapp.number' => '2348000000000']);
        config(['services.whatsapp.default_message' => "Hello SoundMix Support,\nI need help with SoundMix."]);
    }

    protected function tearDown(): void
    {
        SiteSetting::clearRuntimeCache();
        \Illuminate\Support\Facades\Cache::flush();
        parent::tearDown();
    }

    public function test_number_normalization(): void
    {
        // Null and blank / empty values
        $this->assertNull(SiteSetting::normalizeWhatsAppNumber(null));
        $this->assertNull(SiteSetting::normalizeWhatsAppNumber(''));
        $this->assertNull(SiteSetting::normalizeWhatsAppNumber('   '));

        // Preferred format: 234XXXXXXXXXX
        $this->assertSame('2348012345678', SiteSetting::normalizeWhatsAppNumber('2348012345678'));

        // Also accept: +234...
        $this->assertSame('2348012345678', SiteSetting::normalizeWhatsAppNumber('+2348012345678'));

        // Also accept: 00234...
        $this->assertSame('2348012345678', SiteSetting::normalizeWhatsAppNumber('002348012345678'));

        // Spaces and hyphens
        $this->assertSame('2348012345678', SiteSetting::normalizeWhatsAppNumber('234 801 234 5678'));
        $this->assertSame('2348012345678', SiteSetting::normalizeWhatsAppNumber('+234 801 234 5678'));
        $this->assertSame('2348012345678', SiteSetting::normalizeWhatsAppNumber('+234-801-234-5678'));
        $this->assertSame('2348012345678', SiteSetting::normalizeWhatsAppNumber('00234 801 234 5678'));
        $this->assertSame('15551234567', SiteSetting::normalizeWhatsAppNumber('+1 (555) 123-4567'));

        // Nigerian local format: 080XXXXXXXX -> explicitly 23480XXXXXXXX
        // MUST NOT silently convert to 80XXXXXXXX
        $this->assertSame('2348012345678', SiteSetting::normalizeWhatsAppNumber('08012345678'));
        $this->assertNotSame('8012345678', SiteSetting::normalizeWhatsAppNumber('08012345678'));
        $this->assertSame('2348012345678', SiteSetting::normalizeWhatsAppNumber('080-1234-5678'));
        $this->assertSame('2348012345678', SiteSetting::normalizeWhatsAppNumber('080 1234 5678'));
        $this->assertSame('2347012345678', SiteSetting::normalizeWhatsAppNumber('07012345678'));
        $this->assertSame('2348123456789', SiteSetting::normalizeWhatsAppNumber('08123456789'));
        $this->assertSame('2349012345678', SiteSetting::normalizeWhatsAppNumber('09012345678'));
        $this->assertSame('2349112345678', SiteSetting::normalizeWhatsAppNumber('09112345678'));

        // Reject clearly invalid short numbers
        $this->assertNull(SiteSetting::normalizeWhatsAppNumber('080'));
        $this->assertNull(SiteSetting::normalizeWhatsAppNumber('+234'));
        $this->assertNull(SiteSetting::normalizeWhatsAppNumber('12345'));
        $this->assertNull(SiteSetting::normalizeWhatsAppNumber('080123'));
        $this->assertNull(SiteSetting::normalizeWhatsAppNumber('0801234567')); // 10 digits starting with 0 (not 11)

        // Reject letters and non-phone characters
        $this->assertNull(SiteSetting::normalizeWhatsAppNumber('23480000000abc'));
        $this->assertNull(SiteSetting::normalizeWhatsAppNumber('callme'));
        $this->assertNull(SiteSetting::normalizeWhatsAppNumber('+234 800 HELP'));
        $this->assertNull(SiteSetting::normalizeWhatsAppNumber('234@8012345678'));

        // Reject too long numbers (> 15 digits)
        $this->assertNull(SiteSetting::normalizeWhatsAppNumber('+12345678901234567')); // 17 digits

        // Valid boundary lengths (8 to 15 digits)
        $this->assertSame('12345678', SiteSetting::normalizeWhatsAppNumber('12345678'));
        $this->assertSame('123456789012345', SiteSetting::normalizeWhatsAppNumber('123456789012345'));
    }

    public function test_explicit_settings_precedence_admin_disabled_overrides_env_true(): void
    {
        // 1. Env / config is explicitly TRUE
        config(['services.whatsapp.enabled' => true]);
        config(['services.whatsapp.number' => '2348000000000']);

        // 2. But Admin SiteSetting is explicitly FALSE in database
        SiteSetting::putWhatsAppSupport('2348000000000', enabled: false);

        // SiteSetting MUST win over env
        $this->assertFalse(SiteSetting::whatsappSupportEnabled());
        $this->assertFalse(WhatsAppSupport::isEnabled());
        $this->assertNull(WhatsAppSupport::url('public'));

        // Public page must not render WhatsApp link
        $response = $this->get(route('legal.support'));
        $response->assertOk();
        $response->assertDontSee('https://wa.me');

        // 3. When Admin setting is removed, env fallback applies
        SiteSetting::query()->where('key', SiteSetting::KEY_WHATSAPP_SUPPORT_ENABLED)->delete();
        SiteSetting::clearRuntimeCache();

        $this->assertTrue(SiteSetting::whatsappSupportEnabled());
        $this->assertTrue(WhatsAppSupport::isEnabled());
    }

    public function test_admin_settings_ui_helper_text_and_no_env_exposure(): void
    {
        $admin = User::factory()->admin()->create();

        // Env has a configured number
        config(['services.whatsapp.number' => '2348999999999']);

        // No SiteSetting saved in database
        SiteSetting::query()->whereIn('key', [
            SiteSetting::KEY_WHATSAPP_SUPPORT_NUMBER,
            SiteSetting::KEY_WHATSAPP_SUPPORT_ENABLED,
        ])->delete();
        SiteSetting::clearRuntimeCache();

        // whatsappSupportNumberRaw MUST be empty (never leak env value to form)
        $this->assertSame('', SiteSetting::whatsappSupportNumberRaw());

        $response = $this->actingAs($admin)->get(route('admin.settings.edit'));
        $response->assertOk();

        // Verify preferred helper text is present
        $response->assertSee('Use an international WhatsApp number, e.g. 2348012345678.', false);

        // Verify the input does NOT expose the .env number
        $response->assertDontSee('value="2348999999999"', false);
    }

    public function test_is_enabled_logic_and_env_fallback(): void
    {
        // Configured in env
        $this->assertTrue(WhatsAppSupport::isEnabled());
        $this->assertSame('2348000000000', WhatsAppSupport::number());

        // When disabled via config
        config(['services.whatsapp.enabled' => false]);
        $this->assertFalse(WhatsAppSupport::isEnabled());

        // When re-enabled via SiteSetting in database
        SiteSetting::putWhatsAppSupport('2348123456789', enabled: true);
        $this->assertTrue(WhatsAppSupport::isEnabled());
        $this->assertSame('2348123456789', WhatsAppSupport::number());

        // When explicitly disabled via SiteSetting
        SiteSetting::putWhatsAppSupport('2348123456789', enabled: false);
        $this->assertFalse(WhatsAppSupport::isEnabled());

        // When number is cleared and no config number
        config(['services.whatsapp.number' => null]);
        SiteSetting::putWhatsAppSupport(null, enabled: true);
        $this->assertFalse(WhatsAppSupport::isEnabled());
        $this->assertNull(WhatsAppSupport::number());
    }

    public function test_prefilled_message_and_url_generation(): void
    {
        SiteSetting::putWhatsAppSupport('2348000000000', enabled: true);

        // 1. Public context
        $publicUrl = WhatsAppSupport::url('public', pageLabel: 'Pricing');
        $this->assertNotNull($publicUrl);
        $this->assertStringStartsWith('https://wa.me/2348000000000?text=', $publicUrl);
        $this->assertStringContainsString('Pricing', rawurldecode($publicUrl));

        // 2. Listener context
        $listenerUrl = WhatsAppSupport::url('listener', organisation: 'Grace Baptist Church', eventTitle: 'Sunday Worship');
        $this->assertNotNull($listenerUrl);
        $decodedListener = rawurldecode($listenerUrl);
        $this->assertStringContainsString('Grace Baptist Church', $decodedListener);
        $this->assertStringContainsString('Sunday Worship', $decodedListener);
        $this->assertStringContainsString('listening page', $decodedListener);

        // 3. Creator context
        $creatorUrl = WhatsAppSupport::url('creator', pageLabel: 'Billing', organisation: 'Faith Tabernacle');
        $this->assertNotNull($creatorUrl);
        $decodedCreator = rawurldecode($creatorUrl);
        $this->assertStringContainsString('Faith Tabernacle', $decodedCreator);
        $this->assertStringContainsString('Creator account', $decodedCreator);
        $this->assertStringContainsString('Page: Billing', $decodedCreator);

        // 4. Admin context
        $adminUrl = WhatsAppSupport::url('admin', pageLabel: 'Finance Overview');
        $this->assertNotNull($adminUrl);
        $decodedAdmin = rawurldecode($adminUrl);
        $this->assertStringContainsString('Admin Console', $decodedAdmin);
        $this->assertStringContainsString('Section: Finance Overview', $decodedAdmin);

        // 5. Studio context
        $studioUrl = WhatsAppSupport::url('studio', organisation: 'Grace Church', eventTitle: 'Midweek Service', status: 'On Air');
        $this->assertNotNull($studioUrl);
        $decodedStudio = rawurldecode($studioUrl);
        $this->assertStringContainsString('Web Studio', $decodedStudio);
        $this->assertStringContainsString('Grace Church', $decodedStudio);
        $this->assertStringContainsString('Broadcast status: On Air', $decodedStudio);
        $this->assertStringContainsString('Midweek Service', $decodedStudio);

        // 6. Security Check: ensure no sensitive data is leaked
        $this->assertStringNotContainsString('password', $decodedStudio);
        $this->assertStringNotContainsString('token', $decodedStudio);
        $this->assertStringNotContainsString('stream_key', $decodedStudio);
        $this->assertStringNotContainsString('secret', $decodedStudio);
    }

    public function test_admin_can_update_whatsapp_settings(): void
    {
        $admin = User::factory()->admin()->create();

        // 1. Initial view displays form fields
        $this->actingAs($admin)
            ->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertSee('WhatsApp Support Widget', false)
            ->assertSee('whatsapp_support_number', false)
            ->assertSee('whatsapp_support_enabled', false);

        // 2. Admin saves new number and custom message
        $this->actingAs($admin)
            ->put(route('admin.settings.contact'), [
                'support_email' => 'admin@soundmix.live',
                'whatsapp_support_enabled' => '1',
                'whatsapp_support_number' => '+234 812 345 6789',
                'whatsapp_default_message' => 'Custom greeting for WhatsApp.',
            ])
            ->assertRedirect(route('admin.settings.edit'))
            ->assertSessionHas('status');

        $this->assertTrue(SiteSetting::whatsappSupportEnabled());
        $this->assertSame('2348123456789', SiteSetting::whatsappSupportNumber());
        $this->assertTrue(SiteSetting::whatsappSupportIsFromAdmin());
        $this->assertSame('Custom greeting for WhatsApp.', SiteSetting::whatsappDefaultMessage());

        // 3. Admin can disable WhatsApp support
        $this->actingAs($admin)
            ->put(route('admin.settings.contact'), [
                'support_email' => 'admin@soundmix.live',
                'whatsapp_support_enabled' => '0',
                'whatsapp_support_number' => '2348123456789',
            ])
            ->assertRedirect(route('admin.settings.edit'));

        $this->assertFalse(SiteSetting::whatsappSupportEnabled());
    }

    public function test_floating_widget_renders_on_public_and_listener_pages(): void
    {
        SiteSetting::putWhatsAppSupport('2348000000000', enabled: true);

        // Public page (e.g. legal support or welcome)
        $response = $this->get(route('legal.support'));
        $response->assertOk();
        $response->assertSee('https://wa.me/2348000000000', false);
        $response->assertSee('target="_blank"', false);
        $response->assertSee('rel="noopener noreferrer"', false);
        $response->assertSee('aria-label="Chat with SoundMix Support on WhatsApp"', false);

        // Listener page with stream
        $org = Organization::query()->create(['name' => 'Living Faith', 'slug' => 'living-faith-'.uniqid()]);
        $stream = Stream::query()->create([
            'organization_id' => $org->id,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'title' => 'Main Sunday Service',
            'status' => StreamStatus::Live,
            'mount_name' => 'living-faith-stream-'.uniqid(),
            'stream_key' => 'secret-stream-key-12345',
        ]);

        $listenerResponse = $this->get(route('listen.stream', $stream));
        $listenerResponse->assertOk();
        $listenerResponse->assertSee('https://wa.me/2348000000000', false);
        // Ensure stream key is NEVER leaked in html
        $listenerResponse->assertDontSee('secret-stream-key-12345');
        // Ensure elevation CSS for listener pages is applied
        $listenerResponse->assertSee('soundmix-wa-float--listener', false);
    }

    public function test_creator_and_admin_layouts_render_authenticated_whatsapp_widget(): void
    {
        SiteSetting::putWhatsAppSupport('2348000000000', enabled: true);

        $user = User::factory()->create();
        $org = Organization::query()->create(['name' => 'Victory Chapel', 'slug' => 'victory-'.uniqid()]);
        $org->users()->attach($user->id, ['role' => 'admin']);

        // Creator dashboard
        $creatorResponse = $this->actingAs($user)->get(route('creator.home'));
        $creatorResponse->assertOk();
        $creatorResponse->assertSee('https://wa.me/2348000000000', false);
        $creatorResponse->assertSee('data-soundmix-wa-widget="creator"', false);

        // Super Admin dashboard
        $admin = User::factory()->admin()->create();
        $adminResponse = $this->actingAs($admin)->get(route('admin.dashboard'));
        $adminResponse->assertOk();
        $adminResponse->assertSee('https://wa.me/2348000000000', false);
        $adminResponse->assertSee('data-soundmix-wa-widget="admin"', false);
    }

    public function test_studio_renders_floating_widget_and_excludes_topbar_action(): void
    {
        SiteSetting::putWhatsAppSupport('2348000000000', enabled: true);

        $user = User::factory()->create();
        $org = Organization::query()->create(['name' => 'Grace Studio Church', 'slug' => 'grace-studio-'.uniqid()]);
        $org->users()->attach($user->id, ['role' => 'admin']);
        $stream = Stream::query()->create([
            'organization_id' => $org->id,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'title' => 'Grace Live',
            'status' => StreamStatus::Offline,
        ]);
        $event = Event::query()->create([
            'organization_id' => $org->id,
            'stream_id' => $stream->id,
            'title' => 'Sunday Service',
            'status' => EventStatus::Scheduled,
        ]);

        $url = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'studio.stream',
            now()->addHour(),
            ['stream' => $stream]
        );

        $studioResponse = $this->actingAs($user)->get($url);
        $studioResponse->assertOk();

        // Must render floating widget container for studio with proper elevated class
        $studioResponse->assertSee('data-soundmix-wa-widget="studio"', false);
        $studioResponse->assertSee('soundmix-wa-float--studio', false);
        $studioResponse->assertSee('id="btn-studio-whatsapp"', false);
        $studioResponse->assertSee('data-wa-base="https://wa.me/2348000000000"', false);
        $studioResponse->assertSee('Grace Studio Church', false);

        // Topbar must NOT contain WhatsApp support button
        $studioResponse->assertDontSee('studio-top-wa', false);
    }

    public function test_widget_fails_closed_when_disabled_or_no_number(): void
    {
        // 1. Explicitly disabled
        SiteSetting::putWhatsAppSupport('2348000000000', enabled: false);

        $response = $this->get(route('legal.support'));
        $response->assertOk();
        $response->assertDontSee('https://wa.me/2348000000000');
        $response->assertDontSee('aria-label="Chat with SoundMix Support on WhatsApp"');

        // 2. Number null
        config(['services.whatsapp.number' => null]);
        SiteSetting::putWhatsAppSupport(null, enabled: true);

        $response2 = $this->get(route('legal.support'));
        $response2->assertOk();
        $response2->assertDontSee('https://wa.me/');
        $response2->assertDontSee('data-soundmix-wa-widget=');
    }
}
