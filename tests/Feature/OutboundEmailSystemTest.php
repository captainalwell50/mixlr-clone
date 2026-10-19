<?php

namespace Tests\Feature;

use App\Enums\DigitalLibrarySellerStatus;
use App\Enums\DigitalLibrarySeriesStatus;
use App\Enums\EmailCategory;
use App\Enums\OrgRole;
use App\Enums\SubscriptionStatus;
use App\Mail\DigitalLibraryPurchaseReceiptMail;
use App\Mail\InvoiceMail;
use App\Mail\InvoiceReminderMail;
use App\Mail\PasswordChangedMail;
use App\Mail\PasswordSetupMail;
use App\Mail\PaymentFailedMail;
use App\Mail\PaymentReceiptMail;
use App\Mail\SubscriptionActivatedMail;
use App\Mail\SubscriptionCancelledMail;
use App\Mail\VerifyEmailMail;
use App\Mail\WelcomeOnboardingMail;
use App\Models\DigitalLibraryCategory;
use App\Models\DigitalLibraryPurchase;
use App\Models\DigitalLibrarySeller;
use App\Models\DigitalLibrarySeries;
use App\Models\DigitalLibraryTeacher;
use App\Models\EmailDelivery;
use App\Models\Event;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\PaymentCheckout;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\ChannelWentLiveNotification;
use App\Notifications\FreePlanStartedNotification;
use App\Services\OutboundEmailService;
use App\Services\PaystackPaymentConfirmationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class OutboundEmailSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.registration_enabled' => true]);
        config(['registration.min_form_seconds' => 0]);
        config(['registration.turnstile.enabled' => false]);
    }

    public function test_signup_queues_welcome_email(): void
    {
        Mail::fake();

        $response = $this->post(route('register'), [
            'name' => 'Alice Pastor',
            'email' => 'alice@church.example',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
        ]);

        $response->assertRedirect(route('onboarding.show'));

        $user = User::query()->where('email', 'alice@church.example')->firstOrFail();

        Mail::assertQueued(WelcomeOnboardingMail::class, function (WelcomeOnboardingMail $mail) use ($user) {
            return $mail->user->id === $user->id;
        });

        $delivery = EmailDelivery::query()->where('recipient', 'alice@church.example')->firstOrFail();
        $this->assertSame('welcome', $delivery->type);
        $this->assertSame(EmailCategory::Transactional, $delivery->category);
        $this->assertSame(EmailDelivery::STATUS_SENT, $delivery->status);
        $this->assertSame("onboarding:welcome:{$user->id}", $delivery->event_key);
    }

    public function test_username_and_display_name_appear_correctly_and_plaintext_password_never_appears(): void
    {
        $user = User::factory()->create([
            'name' => 'Pastor John Doe',
            'email' => 'john@church.example',
            'password' => 'MyTopSecretPassword123!',
        ]);

        $mailable = new WelcomeOnboardingMail(
            user: $user,
            channelHandle: 'grace-assembly',
            organizationName: 'Grace Assembly Church'
        );

        $html = $mailable->render();

        $this->assertStringContainsString('Pastor John Doe', $html);
        $this->assertStringContainsString('grace-assembly', $html);
        $this->assertStringContainsString('Grace Assembly Church', $html);

        // Security requirement: plaintext password must never appear
        $this->assertStringNotContainsString('MyTopSecretPassword123!', $html);
        $this->assertStringNotContainsString('password', strtolower($user->password)); // hashed
    }

    public function test_duplicate_signup_event_does_not_duplicate_email(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'repeat@church.example']);
        $outbound = app(OutboundEmailService::class);

        $delivery1 = $outbound->sendTransactional(
            mailable: new WelcomeOnboardingMail($user),
            recipient: $user->email,
            type: 'welcome',
            eventKey: "onboarding:welcome:{$user->id}",
            user: $user
        );

        $delivery2 = $outbound->sendTransactional(
            mailable: new WelcomeOnboardingMail($user),
            recipient: $user->email,
            type: 'welcome',
            eventKey: "onboarding:welcome:{$user->id}",
            user: $user
        );

        $this->assertSame($delivery1->id, $delivery2->id);
        $this->assertSame(1, EmailDelivery::query()->where('event_key', "onboarding:welcome:{$user->id}")->count());
        Mail::assertQueued(WelcomeOnboardingMail::class, 1);
    }

    public function test_password_setup_uses_tokenized_url_and_never_contains_password(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'needspass@church.example']);

        $response = $this->post(route('password.email'), [
            'email' => 'needspass@church.example',
        ]);

        $response->assertSessionHas('status');

        Mail::assertQueued(PasswordSetupMail::class, function (PasswordSetupMail $mail) use ($user) {
            $html = $mail->render();
            $this->assertStringContainsString(url('/reset-password/'), $mail->actionUrl);
            $this->assertMatchesRegularExpression('#/reset-password/[a-f0-9]{64}#', $mail->actionUrl);
            $this->assertStringNotContainsString('password=', $mail->actionUrl);

            return $mail->user->id === $user->id;
        });
    }

    public function test_payment_confirmation_sends_one_receipt_and_is_idempotent(): void
    {
        Mail::fake();

        $org = Organization::query()->create(['name' => 'Faith Tabernacle', 'slug' => 'faith-tab-'.uniqid()]);
        $owner = User::factory()->create(['email' => 'owner@faith.example']);
        $org->users()->attach($owner->id, ['role' => OrgRole::Owner->value]);

        $plan = Plan::query()->create([
            'name' => 'Pro Broadcaster',
            'slug' => 'pro-'.uniqid(),
            'amount' => 50000,
            'currency' => 'NGN',
            'interval' => 'monthly',
            'is_active' => true,
        ]);

        $reference = 'PAY_TEST_'.uniqid();

        PaymentCheckout::query()->create([
            'reference' => $reference,
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'status' => PaymentCheckout::STATUS_PENDING,
            'amount' => 50000,
            'currency' => 'NGN',
        ]);

        $confirmation = app(PaystackPaymentConfirmationService::class);

        $payload = [
            'reference' => $reference,
            'status' => 'success',
            'amount' => 5000000, // 50,000 NGN in kobo
            'currency' => 'NGN',
            'metadata' => [
                'organization_id' => $org->id,
                'plan_id' => $plan->id,
            ],
            'customer' => ['customer_code' => 'CUS_123'],
        ];

        // 1. Initial confirmation
        $result = $confirmation->confirmFromPaystackData($payload);
        $this->assertTrue($result);

        Mail::assertQueued(PaymentReceiptMail::class, 1);

        $receiptDelivery = EmailDelivery::query()->where('event_key', "payment-receipt:{$reference}:{$owner->id}")->firstOrFail();
        $this->assertSame('payment_receipt', $receiptDelivery->type);
        $this->assertSame(EmailDelivery::STATUS_SENT, $receiptDelivery->status);

        // 2. Duplicate webhook payload with same reference must NOT dispatch another receipt
        $result2 = $confirmation->confirmFromPaystackData($payload);
        $this->assertTrue($result2);

        Mail::assertQueued(PaymentReceiptMail::class, 1); // Still 1, not 2
    }

    public function test_unpaid_invoice_reminder_stages_and_paid_invoice_stops_reminders(): void
    {
        Mail::fake();

        $org = Organization::query()->create(['name' => 'Grace Chapel', 'slug' => 'grace-chapel-'.uniqid()]);
        $owner = User::factory()->create(['email' => 'billing@grace.example']);
        $org->users()->attach($owner->id, ['role' => OrgRole::Owner->value]);

        // Invoice due in 7 days
        $invoice7 = Invoice::query()->create([
            'organization_id' => $org->id,
            'invoice_number' => 'INV-2026-7DAYS',
            'amount' => 2500000,
            'currency' => 'NGN',
            'due_date' => now()->addDays(7)->startOfDay(),
            'status' => Invoice::STATUS_PENDING,
        ]);

        // Invoice due in 3 days
        $invoice3 = Invoice::query()->create([
            'organization_id' => $org->id,
            'invoice_number' => 'INV-2026-3DAYS',
            'amount' => 2500000,
            'currency' => 'NGN',
            'due_date' => now()->addDays(3)->startOfDay(),
            'status' => Invoice::STATUS_PENDING,
        ]);

        // Invoice already paid
        $invoicePaid = Invoice::query()->create([
            'organization_id' => $org->id,
            'invoice_number' => 'INV-2026-PAID',
            'amount' => 2500000,
            'currency' => 'NGN',
            'due_date' => now()->addDays(7)->startOfDay(),
            'paid_at' => now(),
            'status' => Invoice::STATUS_PAID,
        ]);

        $this->artisan('billing:send-invoice-reminders')->assertSuccessful();

        Mail::assertQueued(InvoiceReminderMail::class, 2);

        // Check event keys prevent duplicate reminders
        $this->assertTrue(EmailDelivery::query()->where('event_key', "invoice-reminder:{$invoice7->id}:7_days_before:{$owner->id}")->exists());
        $this->assertTrue(EmailDelivery::query()->where('event_key', "invoice-reminder:{$invoice3->id}:3_days_before:{$owner->id}")->exists());
        $this->assertFalse(EmailDelivery::query()->where('event_key', 'like', "invoice-reminder:{$invoicePaid->id}:%")->exists());

        // Running a second time on the same day sends 0 extra emails
        $this->artisan('billing:send-invoice-reminders')->assertSuccessful();
        Mail::assertQueued(InvoiceReminderMail::class, 2); // Still 2
    }

    public function test_failed_payment_notification(): void
    {
        Mail::fake();

        $org = Organization::query()->create(['name' => 'Victory Center', 'slug' => 'victory-'.uniqid()]);
        $owner = User::factory()->create(['email' => 'pastor@victory.example']);
        $org->users()->attach($owner->id, ['role' => OrgRole::Owner->value]);

        $plan = Plan::query()->create([
            'name' => 'Starter',
            'slug' => 'starter-'.uniqid(),
            'amount' => 15000,
            'currency' => 'NGN',
            'interval' => 'monthly',
            'is_active' => true,
        ]);

        Subscription::query()->create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'paystack_customer_code' => 'CUS_VICTORY',
        ]);

        // Trigger webhook invoice.payment_failed
        $payload = [
            'event' => 'invoice.payment_failed',
            'data' => [
                'customer' => ['customer_code' => 'CUS_VICTORY'],
                'invoice_code' => 'INV_FAIL_123',
            ],
        ];

        // Sign with paystack secret
        config(['services.paystack.secret_key' => 'sk_test_123']);
        $signature = hash_hmac('sha512', json_encode($payload), 'sk_test_123');

        $this->postJson('/webhooks/paystack', $payload, ['x-paystack-signature' => $signature])
            ->assertOk();

        Mail::assertQueued(PaymentFailedMail::class, function (PaymentFailedMail $mail) use ($org) {
            $html = $mail->render();
            $this->assertStringContainsString('Victory Center', $html);
            $this->assertStringContainsString(route('billing.plans'), $html);
            // No technical stack trace
            $this->assertStringNotContainsString('Exception', $html);

            return $mail->organization->id === $org->id;
        });
    }

    public function test_marketing_opt_out_does_not_suppress_transactional_email(): void
    {
        Mail::fake();

        $user = User::factory()->marketingUnsubscribed()->create([
            'email' => 'optedout@church.example',
        ]);

        $this->assertFalse($user->allowsMarketingEmail());

        $outbound = app(OutboundEmailService::class);

        // Transactional password reset
        $delivery = $outbound->sendTransactional(
            mailable: new PasswordSetupMail($user, 'https://soundmix.live/reset', false),
            recipient: $user->email,
            type: 'password_reset',
            eventKey: "pwd:test:{$user->id}",
            user: $user
        );

        $this->assertNotNull($delivery);
        $this->assertSame(EmailDelivery::STATUS_SENT, $delivery->status);
        Mail::assertQueued(PasswordSetupMail::class, 1);
    }

    public function test_marketing_opt_out_suppresses_promotional_email(): void
    {
        Mail::fake();

        $user = User::factory()->marketingUnsubscribed()->create([
            'email' => 'optedout@church.example',
        ]);

        $outbound = app(OutboundEmailService::class);

        // Promotional email
        $delivery = $outbound->sendMarketing(
            mailable: new WelcomeOnboardingMail($user),
            user: $user,
            type: 'promo_announcement',
            eventKey: "promo:october:{$user->id}"
        );

        $this->assertNull($delivery);
        Mail::assertNothingQueued();

        $ledger = EmailDelivery::query()->where('event_key', "promo:october:{$user->id}")->firstOrFail();
        $this->assertSame('suppressed', $ledger->status);
    }

    public function test_unauthorized_email_webhook_rejected(): void
    {
        config(['services.email_webhook.secret' => 'top-secret-webhook-key']);

        $response = $this->postJson('/api/webhooks/email', [
            'event' => 'delivered',
            'email' => 'someone@example.com',
        ], ['X-Email-Webhook-Secret' => 'wrong-secret']);

        $response->assertStatus(401);
    }

    public function test_authorized_email_webhook_records_delivery_and_bounce(): void
    {
        config(['services.email_webhook.secret' => 'top-secret-webhook-key']);

        $user = User::factory()->marketingOptedIn()->create(['email' => 'bouncee@church.example']);
        $this->assertTrue($user->allowsMarketingEmail());

        $delivery = EmailDelivery::query()->create([
            'user_id' => $user->id,
            'recipient' => 'bouncee@church.example',
            'type' => 'welcome',
            'category' => EmailCategory::Transactional,
            'event_key' => 'test-delivery-key',
            'provider_message_id' => 'msg_abc_123',
            'status' => EmailDelivery::STATUS_SENT,
        ]);

        $response = $this->postJson('/api/webhooks/email', [
            'event' => 'bounced',
            'provider_message_id' => 'msg_abc_123',
            'recipient' => 'bouncee@church.example',
            'reason' => 'Mailbox unavailable: 550 User not found',
        ], ['X-Email-Webhook-Secret' => 'top-secret-webhook-key']);

        $response->assertOk();

        $delivery->refresh();
        $this->assertSame(EmailDelivery::STATUS_BOUNCED, $delivery->status);
        $this->assertStringContainsString('Mailbox unavailable', $delivery->metadata['bounce_reason']);

        // Marketing should be auto-suppressed for bounced user
        $user->refresh();
        $this->assertFalse($user->allowsMarketingEmail());
    }

    public function test_email_delivery_failures_recorded_safely(): void
    {
        $delivery = EmailDelivery::query()->create([
            'recipient' => 'fail@church.example',
            'type' => 'test',
            'category' => EmailCategory::Transactional,
            'status' => EmailDelivery::STATUS_PENDING,
        ]);

        $delivery->markFailed('SMTP Error: secret_token=sk_live_123456789 and Bearer abcdef12345 failed on card 4111 2222 3333 4444');

        $this->assertSame(EmailDelivery::STATUS_FAILED, $delivery->status);
        // Sensitive data must be sanitized
        $this->assertStringNotContainsString('sk_live_123456789', $delivery->last_error);
        $this->assertStringNotContainsString('abcdef12345', $delivery->last_error);
        $this->assertStringNotContainsString('4111 2222 3333 4444', $delivery->last_error);
        $this->assertStringContainsString('[REDACTED]', $delivery->last_error);
    }

    public function test_password_changed_email_dispatched_after_password_reset(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'resetuser@church.example',
            'password' => Hash::make('OldPassword123!'),
        ]);

        $token = Password::broker()->createToken($user);

        $response = $this->post(route('password.store'), [
            'token' => $token,
            'email' => 'resetuser@church.example',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('NewPassword123!', $user->fresh()->password));

        Mail::assertQueued(PasswordChangedMail::class, function (PasswordChangedMail $mail) use ($user) {
            $html = $mail->render();
            $this->assertStringContainsString('Password Changed', $html);
            $this->assertStringContainsString('Security Notice', $html);
            $this->assertStringNotContainsString('NewPassword123!', $html);
            $this->assertStringNotContainsString('OldPassword123!', $html);

            return $mail->user->id === $user->id;
        });

        $delivery = EmailDelivery::query()->where('type', 'password_changed')
            ->where('recipient', 'resetuser@church.example')
            ->firstOrFail();

        $this->assertSame(EmailCategory::Transactional, $delivery->category);
        $this->assertSame(EmailDelivery::STATUS_SENT, $delivery->status);
        $this->assertStringStartsWith("password-changed:{$user->id}:", $delivery->event_key);
    }

    public function test_password_changed_email_dispatched_after_account_settings_update(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'accountuser@church.example',
            'password' => Hash::make('CurrentPassword123!'),
        ]);

        $response = $this->actingAs($user)->put(route('account.update'), [
            'name' => 'Updated Name',
            'current_password' => 'CurrentPassword123!',
            'password' => 'NewAccountPassword123!',
            'password_confirmation' => 'NewAccountPassword123!',
        ]);

        $response->assertRedirect(route('account.edit'));
        $this->assertTrue(Hash::check('NewAccountPassword123!', $user->fresh()->password));

        Mail::assertQueued(PasswordChangedMail::class, function (PasswordChangedMail $mail) use ($user) {
            $html = $mail->render();
            $this->assertStringContainsString('Updated Name', $html);
            $this->assertStringContainsString('support@soundmix.live', $html);
            $this->assertStringNotContainsString('NewAccountPassword123!', $html);

            return $mail->user->id === $user->id;
        });

        $this->assertTrue(EmailDelivery::query()
            ->where('type', 'password_changed')
            ->where('recipient', 'accountuser@church.example')
            ->exists());
    }

    public function test_password_changed_email_renders_branded_html_and_text(): void
    {
        $user = User::factory()->create([
            'name' => 'Bishop Sarah',
            'email' => 'sarah@cathedral.example',
        ]);

        $mail = new PasswordChangedMail($user, now(), '198.51.100.42');
        $html = $mail->render();
        $text = view('emails.password-changed-text', [
            'user' => $user,
            'changedAtFormatted' => \App\Support\LocalTime::format(now(), 'F j, Y, g:i A T'),
            'ipAddress' => '198.51.100.42',
            'supportEmail' => 'support@soundmix.live',
            'replyTo' => 'support@soundmix.live',
            'accountUrl' => config('app.url').'/account',
        ])->render();

        $this->assertStringContainsString('Bishop Sarah', $html);
        $this->assertStringContainsString('198.51.100.42', $html);
        $this->assertStringContainsString('support@soundmix.live', $html);
        $this->assertStringContainsString('Security Notice', $html);

        $this->assertStringContainsString('Bishop Sarah', $text);
        $this->assertStringContainsString('198.51.100.42', $text);
        $this->assertStringContainsString('support@soundmix.live', $text);
    }

    public function test_email_verification_notice_verify_and_resend_flow(): void
    {
        Mail::fake();

        $user = User::factory()->unverified()->create([
            'email' => 'unverified@church.example',
        ]);

        $this->actingAs($user)->get(route('verification.notice'))
            ->assertOk()
            ->assertViewIs('auth.verify-email');

        $this->actingAs($user)->post(route('verification.send'))
            ->assertRedirect();

        Mail::assertQueued(VerifyEmailMail::class, function (VerifyEmailMail $mail) use ($user) {
            $this->assertStringContainsString('verify-email', $mail->verificationUrl);

            return $mail->user->id === $user->id;
        });

        $this->assertTrue(EmailDelivery::query()
            ->where('type', 'verification')
            ->where('recipient', 'unverified@church.example')
            ->exists());

        // Signed verification link
        $signedUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
            ]
        );

        $this->actingAs($user)->get($signedUrl)
            ->assertRedirect(route('dashboard'));

        $this->assertTrue($user->fresh()->hasVerifiedEmail());

        // Visiting notice when already verified redirects to dashboard
        $this->actingAs($user->fresh())->get(route('verification.notice'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_subscription_lifecycle_mails_dispatched_and_rendered(): void
    {
        Mail::fake();

        $org = Organization::query()->create(['name' => 'Mount Zion', 'slug' => 'mt-zion-'.uniqid()]);
        $owner = User::factory()->create(['email' => 'pastor@zion.example']);
        $org->users()->attach($owner->id, ['role' => OrgRole::Owner->value]);

        $plan = Plan::query()->create([
            'name' => 'Enterprise Live',
            'slug' => 'enterprise-'.uniqid(),
            'amount' => 120000,
            'currency' => 'NGN',
            'interval' => 'monthly',
            'is_active' => true,
        ]);

        $assigner = app(\App\Services\SubscriptionAssignmentService::class);
        $assigner->assignPaid($org, $plan);

        Mail::assertQueued(SubscriptionActivatedMail::class, function (SubscriptionActivatedMail $mail) use ($org, $plan) {
            $html = $mail->render();
            $this->assertStringContainsString('Enterprise Live', $html);
            $this->assertStringContainsString('Mount Zion', $html);
            $this->assertStringContainsString(route('dashboard'), $html);

            return $mail->organization->id === $org->id && $mail->plan->id === $plan->id;
        });

        $this->assertTrue(EmailDelivery::query()
            ->where('type', 'subscription_activated')
            ->where('recipient', 'pastor@zion.example')
            ->exists());

        // Cancellation mail render
        $cancelMail = new SubscriptionCancelledMail($org);
        $cancelHtml = $cancelMail->render();
        $this->assertStringContainsString('Mount Zion', $cancelHtml);
        $this->assertStringContainsString(route('billing.plans'), $cancelHtml);
        $this->assertStringContainsString('billing@soundmix.live', $cancelHtml);
    }

    public function test_admin_delivery_ledger_view_filtering_and_pagination(): void
    {
        $admin = User::factory()->admin()->create();
        $nonAdmin = User::factory()->create();
        $org = Organization::query()->create(['name' => 'Member Org', 'slug' => 'member-org-'.uniqid()]);
        $nonAdmin->organizations()->attach($org->id, ['role' => OrgRole::Member->value]);

        // Non-admin cannot view ledger
        $this->actingAs($nonAdmin)->get(route('admin.email-deliveries.index'))->assertForbidden();

        // Seed some deliveries
        EmailDelivery::query()->create([
            'recipient' => 'filter-sent@example.com',
            'type' => 'welcome',
            'category' => EmailCategory::Transactional,
            'status' => EmailDelivery::STATUS_SENT,
            'event_key' => 'test:ledger:sent',
        ]);
        EmailDelivery::query()->create([
            'recipient' => 'filter-failed@example.com',
            'type' => 'password_reset',
            'category' => EmailCategory::Transactional,
            'status' => EmailDelivery::STATUS_FAILED,
            'event_key' => 'test:ledger:failed',
            'last_error' => 'Connection timed out',
        ]);

        // Super admin can view
        $response = $this->actingAs($admin)->get(route('admin.email-deliveries.index'));
        $response->assertOk()
            ->assertSee('filter-sent@example.com')
            ->assertSee('filter-failed@example.com');

        // Status filter
        $responseStatus = $this->actingAs($admin)->get(route('admin.email-deliveries.index', ['status' => 'failed']));
        $responseStatus->assertOk()
            ->assertSee('filter-failed@example.com')
            ->assertDontSee('filter-sent@example.com');

        // Search query
        $responseSearch = $this->actingAs($admin)->get(route('admin.email-deliveries.index', ['q' => 'filter-sent']));
        $responseSearch->assertOk()
            ->assertSee('filter-sent@example.com')
            ->assertDontSee('filter-failed@example.com');
    }

    public function test_marketing_unsubscribe_signed_route_and_opt_in(): void
    {
        $user = User::factory()->marketingOptedIn()->create();
        $this->assertTrue($user->allowsMarketingEmail());

        // Unsigned request returns 403
        $this->get('/email/unsubscribe/'.$user->id)->assertForbidden();

        // Valid signed request displays unsubscribe view
        $signedShowUrl = URL::signedRoute('email.unsubscribe', $user);
        $this->get($signedShowUrl)
            ->assertOk()
            ->assertViewIs('emails.unsubscribe')
            ->assertSee($user->email);

        // Valid signed POST to unsubscribe
        $signedPostUrl = URL::signedRoute('email.unsubscribe.update', $user);
        $this->post($signedPostUrl, ['action' => 'unsubscribe'])
            ->assertRedirect();

        $this->assertFalse($user->fresh()->allowsMarketingEmail());

        // Valid signed POST to re-subscribe
        $this->post($signedPostUrl, ['action' => 'resubscribe'])
            ->assertRedirect();

        $this->assertTrue($user->fresh()->allowsMarketingEmail());
    }

    public function test_digital_library_purchase_receipt_mail_dispatch_and_idempotency(): void
    {
        Mail::fake();

        $buyer = User::factory()->create(['email' => 'buyer@library.example']);
        $org = Organization::query()->create(['name' => 'Grace Publishing', 'slug' => 'grace-pub-'.uniqid()]);
        $seller = DigitalLibrarySeller::query()->create([
            'organization_id' => $org->id,
            'status' => DigitalLibrarySellerStatus::Approved,
        ]);
        $teacher = DigitalLibraryTeacher::query()->create([
            'seller_id' => $seller->id,
            'organization_id' => $org->id,
            'display_name' => 'Pastor Emmanuel',
            'slug' => 'pastor-emmanuel-'.uniqid(),
            'public_handle' => 'pemmanuel'.uniqid(),
            'status' => 'active',
        ]);
        $category = DigitalLibraryCategory::query()->create([
            'name' => 'Theology',
            'slug' => 'theology-'.uniqid(),
            'position' => 1,
            'is_visible' => true,
        ]);
        $series = DigitalLibrarySeries::query()->create([
            'organization_id' => $org->id,
            'seller_id' => $seller->id,
            'teacher_id' => $teacher->id,
            'category_id' => $category->id,
            'title' => 'Walking in Dominion',
            'slug' => 'walking-in-dominion-'.uniqid(),
            'description' => 'A master series on spiritual authority.',
            'price_minor' => 150000,
            'currency' => 'NGN',
            'status' => DigitalLibrarySeriesStatus::Published,
            'sales_enabled' => true,
            'position' => 1,
        ]);

        $purchase = DigitalLibraryPurchase::query()->create([
            'user_id' => $buyer->id,
            'series_id' => $series->id,
            'seller_id' => $seller->id,
            'provider' => 'paystack',
            'provider_reference' => 'SMDL_TEST_REF_'.uniqid(),
            'listed_price_minor' => 150000,
            'amount_collected_minor' => 150000,
            'currency' => 'NGN',
            'platform_fee_bps' => 0,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $service = app(\App\Services\DigitalLibraryPurchaseService::class);
        $service->dispatchPurchaseReceipt($purchase);

        Mail::assertQueued(DigitalLibraryPurchaseReceiptMail::class, 1);

        Mail::assertQueued(DigitalLibraryPurchaseReceiptMail::class, function (DigitalLibraryPurchaseReceiptMail $mail) use ($series) {
            $html = $mail->render();
            $this->assertStringContainsString('Walking in Dominion', $html);
            $this->assertStringContainsString('1,500.00', $html);
            $this->assertStringContainsString('permanent on-demand streaming access', $html);

            return $mail->series->id === $series->id;
        });

        $this->assertTrue(EmailDelivery::query()
            ->where('event_key', "digital-library-receipt:{$purchase->provider_reference}:{$buyer->id}")
            ->exists());

        // Duplicate call is idempotent and does not send again
        $service->dispatchPurchaseReceipt($purchase);
        Mail::assertQueued(DigitalLibraryPurchaseReceiptMail::class, 1);
    }

    public function test_modernized_notifications_render_branded_views_and_record_ledger(): void
    {
        $org = Organization::query()->create(['name' => 'Grace Cathedral', 'slug' => 'grace-cath-'.uniqid()]);
        $owner = User::factory()->create(['email' => 'member@grace.example']);
        $org->users()->attach($owner->id, ['role' => OrgRole::Owner->value]);

        $event = Event::query()->create([
            'organization_id' => $org->id,
            'title' => 'Sunday Morning Glory',
            'slug' => 'sunday-glory-'.uniqid(),
            'is_live' => true,
            'starts_at' => now(),
        ]);

        // ChannelWentLiveNotification
        $notif = new ChannelWentLiveNotification($event);
        $mailMessage = $notif->toMail($owner);

        $this->assertSame($org->name.' is live: '.$event->title, $mailMessage->subject);
        $this->assertSame('emails.channel-went-live', is_array($mailMessage->view) ? $mailMessage->view['html'] : $mailMessage->view);
        $this->assertSame('emails.channel-went-live-text', is_array($mailMessage->view) ? $mailMessage->view['text'] : $mailMessage->textView);

        $this->assertTrue(EmailDelivery::query()
            ->where('event_key', "channel-went-live:{$event->id}:{$owner->id}")
            ->where('type', 'channel_went_live')
            ->exists());

        // FreePlanStartedNotification
        $freeNotif = new FreePlanStartedNotification($org);
        $freeMailMessage = $freeNotif->toMail($owner);

        $this->assertStringContainsString('Free access has started', $freeMailMessage->subject);
        $this->assertSame('emails.free-plan-started', is_array($freeMailMessage->view) ? $freeMailMessage->view['html'] : $freeMailMessage->view);
        $this->assertSame('emails.free-plan-started-text', is_array($freeMailMessage->view) ? $freeMailMessage->view['text'] : $freeMailMessage->textView);

        $this->assertTrue(EmailDelivery::query()
            ->where('event_key', "free-plan-started:{$org->id}:{$owner->id}")
            ->where('type', 'free_plan_started')
            ->exists());
    }
}
