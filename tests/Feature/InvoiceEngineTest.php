<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\OrgRole;
use App\Exceptions\InvalidInvoiceStateTransitionException;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Organization;
use App\Models\PaymentAllocation;
use App\Models\PaymentAttempt;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\InvoiceNumberGenerator;
use App\Services\PlatformFinanceService;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class InvoiceEngineTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private Plan $plan;
    private Subscription $sub;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->org = Organization::query()->create([
            'name' => 'Grace Sound Org',
            'slug' => 'grace-sound-'.uniqid(),
        ]);
        $this->org->users()->attach($user->id, ['role' => OrgRole::Owner->value]);

        $this->plan = Plan::query()->create([
            'name' => 'Starter',
            'slug' => 'starter-test-'.uniqid(),
            'amount' => 700000, // 7000.00 NGN in minor units
            'currency' => 'NGN',
            'interval' => 'monthly',
            'is_active' => true,
        ]);

        $this->sub = Subscription::query()->create([
            'organization_id' => $this->org->id,
            'plan_id' => $this->plan->id,
            'status' => 'active',
            'renews_at' => now()->addMonth(),
        ]);
    }

    /** 1. invoice can be created */
    public function test_invoice_can_be_created(): void
    {
        $invoice = Invoice::query()->create([
            'organization_id' => $this->org->id,
            'subscription_id' => $this->sub->id,
            'plan_id' => $this->plan->id,
            'billing_period_start' => now()->startOfDay(),
            'billing_period_end' => now()->addMonth()->subDay()->endOfDay(),
            'currency' => 'NGN',
            'subtotal' => 700000,
            'total' => 700000,
            'status' => InvoiceStatus::Draft,
            'due_at' => now()->addDays(7),
        ]);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'organization_id' => $this->org->id,
            'subtotal' => 700000,
            'total' => 700000,
            'status' => 'draft',
        ]);
        $this->assertSame($this->org->id, $invoice->organization->id);
        $this->assertSame($this->sub->id, $invoice->subscription->id);
        $this->assertSame($this->plan->id, $invoice->plan->id);
    }

    /** 2. invoice number generated correctly */
    public function test_invoice_number_generated_correctly(): void
    {
        $year = (int) now()->format('Y');
        $number = InvoiceNumberGenerator::next($year);

        $this->assertMatchesRegularExpression("/^SM-{$year}-\d{6}$/", $number);

        // Auto-generation on empty invoice_number
        $invoice = Invoice::query()->create([
            'organization_id' => $this->org->id,
            'total' => 500000,
            'status' => InvoiceStatus::Draft,
        ]);

        $this->assertNotEmpty($invoice->invoice_number);
        $this->assertMatchesRegularExpression("/^SM-{$year}-\d{6}$/", $invoice->invoice_number);
    }

    /** 3. invoice number unique */
    public function test_invoice_number_unique(): void
    {
        $number = 'SM-2026-000999';

        Invoice::query()->create([
            'organization_id' => $this->org->id,
            'invoice_number' => $number,
            'total' => 100000,
        ]);

        $this->expectException(QueryException::class);

        Invoice::query()->create([
            'organization_id' => $this->org->id,
            'invoice_number' => $number,
            'total' => 200000,
        ]);
    }

    /** 4. line items work */
    public function test_line_items_work(): void
    {
        $invoice = Invoice::query()->create([
            'organization_id' => $this->org->id,
            'currency' => 'NGN',
            'status' => InvoiceStatus::Draft,
        ]);

        $line1 = InvoiceLine::query()->create([
            'invoice_id' => $invoice->id,
            'description' => 'Starter Plan Monthly Access',
            'quantity' => 1,
            'unit_amount' => 700000,
        ]);

        $line2 = InvoiceLine::query()->create([
            'invoice_id' => $invoice->id,
            'description' => 'Additional 50GB Storage',
            'quantity' => 2,
            'unit_amount' => 50000,
        ]);

        $this->assertSame(700000, $line1->subtotal);
        $this->assertSame(100000, $line2->subtotal);

        $invoice->recalculateTotals()->save();

        $this->assertSame(800000, $invoice->subtotal);
        $this->assertSame(800000, $invoice->total);
        $this->assertSame(800000, $invoice->amount_due);
        $this->assertCount(2, $invoice->lines);
        $this->assertSame($invoice->id, $line1->invoice->id);
    }

    /** 5. integer money calculations correct */
    public function test_integer_money_calculations_correct(): void
    {
        $invoice = new Invoice([
            'subtotal' => 700000,
            'discount_total' => 50000,
            'tax_total' => 35000,
            'amount_paid' => 200000,
        ]);

        $invoice->recalculateTotals();

        // total = 700000 - 50000 + 35000 = 685000
        $this->assertSame(685000, $invoice->total);
        // amount_due = 685000 - 200000 = 485000
        $this->assertSame(485000, $invoice->amount_due);
        $this->assertIsInt($invoice->total);
        $this->assertIsInt($invoice->amount_due);

        $invoice->assertBalanceInvariants();
    }

    /** 6. discount calculation correct */
    public function test_discount_calculation_correct(): void
    {
        $invoice = new Invoice([
            'subtotal' => 1000000,
            'discount_total' => 300000,
            'tax_total' => 0,
        ]);
        $invoice->recalculateTotals();

        $this->assertSame(700000, $invoice->total);
        $this->assertSame(700000, $invoice->amount_due);

        // Discount exceeds subtotal caps total at 0
        $invoice->discount_total = 1500000;
        $invoice->recalculateTotals();
        $this->assertSame(0, $invoice->total);
        $this->assertSame(0, $invoice->amount_due);
    }

    /** 7. tax calculation correct */
    public function test_tax_calculation_correct(): void
    {
        $invoice = new Invoice([
            'subtotal' => 500000,
            'discount_total' => 0,
            'tax_total' => 37500, // 7.5% VAT in integer kobo
        ]);
        $invoice->recalculateTotals();

        $this->assertSame(537500, $invoice->total);
        $this->assertSame(537500, $invoice->amount_due);
    }

    /** 8. amount_due calculation correct */
    public function test_amount_due_calculation_correct(): void
    {
        $invoice = new Invoice([
            'subtotal' => 500000,
            'total' => 500000,
            'amount_paid' => 300000,
        ]);
        $invoice->recalculateTotals();

        $this->assertSame(200000, $invoice->amount_due);

        // Overpayment bounds due to 0
        $invoice->amount_paid = 600000;
        $invoice->recalculateTotals();
        $this->assertSame(0, $invoice->amount_due);
    }

    /** 9. payment attempt relationships */
    public function test_payment_attempt_relationships(): void
    {
        $invoice = Invoice::query()->create([
            'organization_id' => $this->org->id,
            'total' => 700000,
            'status' => InvoiceStatus::Open,
        ]);

        $attempt = PaymentAttempt::query()->create([
            'invoice_id' => $invoice->id,
            'organization_id' => $this->org->id,
            'reference' => 'lma_att_'.uniqid(),
            'amount' => 700000,
            'currency' => 'NGN',
            'status' => PaymentAttempt::STATUS_INITIATED,
            'initiated_at' => now(),
        ]);

        $this->assertSame($invoice->id, $attempt->invoice->id);
        $this->assertSame($this->org->id, $attempt->organization->id);
        $this->assertTrue($invoice->paymentAttempts->contains($attempt));

        $attempt->markSuccessful('PAY_REF_123', ['channel' => 'card']);
        $this->assertTrue($attempt->fresh()->isSuccessful());
        $this->assertSame('PAY_REF_123', $attempt->fresh()->provider_reference);
    }

    /** 10. allocation relationships */
    public function test_allocation_relationships(): void
    {
        $invoice = Invoice::query()->create([
            'organization_id' => $this->org->id,
            'subtotal' => 700000,
            'total' => 700000,
            'status' => InvoiceStatus::Open,
        ]);

        $attempt = PaymentAttempt::query()->create([
            'invoice_id' => $invoice->id,
            'organization_id' => $this->org->id,
            'reference' => 'lma_att_'.uniqid(),
            'amount' => 700000,
            'status' => PaymentAttempt::STATUS_SUCCESSFUL,
            'initiated_at' => now(),
            'confirmed_at' => now(),
        ]);

        $allocation = PaymentAllocation::query()->create([
            'invoice_id' => $invoice->id,
            'payment_attempt_id' => $attempt->id,
            'amount' => 700000,
            'allocated_at' => now(),
            'notes' => 'Full card settlement',
        ]);

        $this->assertSame($invoice->id, $allocation->invoice->id);
        $this->assertSame($attempt->id, $allocation->paymentAttempt->id);
        $this->assertTrue($invoice->allocations->contains($allocation));

        $invoice->recalculateTotals()->save();
        $this->assertSame(700000, $invoice->amount_paid);
        $this->assertSame(0, $invoice->amount_due);
    }

    /** 11. duplicate payment reference rejected */
    public function test_duplicate_payment_reference_rejected(): void
    {
        $invoice = Invoice::query()->create([
            'organization_id' => $this->org->id,
            'total' => 700000,
        ]);

        $ref = 'lma_unique_ref_12345';

        PaymentAttempt::query()->create([
            'invoice_id' => $invoice->id,
            'organization_id' => $this->org->id,
            'reference' => $ref,
            'amount' => 700000,
            'status' => PaymentAttempt::STATUS_INITIATED,
            'initiated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        PaymentAttempt::query()->create([
            'invoice_id' => $invoice->id,
            'organization_id' => $this->org->id,
            'reference' => $ref,
            'amount' => 700000,
            'status' => PaymentAttempt::STATUS_INITIATED,
            'initiated_at' => now(),
        ]);
    }

    /** 12. duplicate allocation rejected */
    public function test_duplicate_allocation_rejected(): void
    {
        $invoice = Invoice::query()->create([
            'organization_id' => $this->org->id,
            'total' => 700000,
        ]);

        $attempt = PaymentAttempt::query()->create([
            'invoice_id' => $invoice->id,
            'organization_id' => $this->org->id,
            'reference' => 'lma_unique_'.uniqid(),
            'amount' => 700000,
            'status' => PaymentAttempt::STATUS_SUCCESSFUL,
            'initiated_at' => now(),
        ]);

        PaymentAllocation::query()->create([
            'invoice_id' => $invoice->id,
            'payment_attempt_id' => $attempt->id,
            'amount' => 700000,
            'allocated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        // Attempting to credit the exact same attempt to the same invoice again must fail
        PaymentAllocation::query()->create([
            'invoice_id' => $invoice->id,
            'payment_attempt_id' => $attempt->id,
            'amount' => 700000,
            'allocated_at' => now(),
        ]);
    }

    /** 13. one invoice per subscription billing period */
    public function test_one_invoice_per_subscription_billing_period(): void
    {
        $start = Carbon::parse('2026-10-19')->startOfDay();
        $end = Carbon::parse('2026-11-18')->endOfDay();

        Invoice::query()->create([
            'organization_id' => $this->org->id,
            'subscription_id' => $this->sub->id,
            'billing_period_start' => $start,
            'billing_period_end' => $end,
            'total' => 700000,
        ]);

        $this->expectException(QueryException::class);

        // Attempting to create a second invoice for the exact same subscription cycle must violate uniqueness
        Invoice::query()->create([
            'organization_id' => $this->org->id,
            'subscription_id' => $this->sub->id,
            'billing_period_start' => $start,
            'billing_period_end' => $end,
            'total' => 700000,
        ]);
    }

    /** 14. allowed state transitions */
    public function test_allowed_state_transitions(): void
    {
        // draft -> open -> partially_paid -> paid
        $inv1 = Invoice::query()->create([
            'organization_id' => $this->org->id,
            'total' => 700000,
            'status' => InvoiceStatus::Draft,
        ]);
        $this->assertTrue($inv1->canTransitionTo(InvoiceStatus::Open));
        $inv1->issue();
        $this->assertSame('open', $inv1->status);
        $this->assertNotNull($inv1->issued_at);

        $this->assertTrue($inv1->canTransitionTo(InvoiceStatus::PartiallyPaid));
        $inv1->markPartiallyPaid();
        $this->assertSame('partially_paid', $inv1->status);

        $this->assertTrue($inv1->canTransitionTo(InvoiceStatus::Paid));
        $inv1->markPaid('REF_PAID');
        $this->assertSame('paid', $inv1->status);
        $this->assertNotNull($inv1->paid_at);

        // open -> past_due -> void
        $inv2 = Invoice::query()->create([
            'organization_id' => $this->org->id,
            'total' => 700000,
            'status' => InvoiceStatus::Open,
        ]);
        $inv2->markPastDue();
        $this->assertSame('past_due', $inv2->status);

        $inv2->void('Admin override');
        $this->assertSame('void', $inv2->status);
        $this->assertNotNull($inv2->voided_at);
        $this->assertSame('Admin override', $inv2->metadata['void_reason'] ?? null);

        // open -> cancelled
        $inv3 = Invoice::query()->create([
            'organization_id' => $this->org->id,
            'total' => 700000,
            'status' => InvoiceStatus::Open,
        ]);
        $inv3->cancel('Plan changed');
        $this->assertSame('cancelled', $inv3->status);
    }

    /** 15. forbidden state transitions */
    public function test_forbidden_state_transitions(): void
    {
        $inv = Invoice::query()->create([
            'organization_id' => $this->org->id,
            'total' => 700000,
            'status' => InvoiceStatus::Draft,
        ]);

        $this->assertFalse($inv->canTransitionTo(InvoiceStatus::Paid));

        $this->expectException(InvalidInvoiceStateTransitionException::class);
        $inv->transitionTo(InvoiceStatus::Paid);
    }

    /** 16. paid invoice terminal */
    public function test_paid_invoice_terminal(): void
    {
        $inv = Invoice::query()->create([
            'organization_id' => $this->org->id,
            'total' => 700000,
            'status' => InvoiceStatus::Open,
        ]);
        $inv->markPaid();

        $this->assertTrue($inv->isTerminal());
        $this->assertFalse($inv->canTransitionTo(InvoiceStatus::Open));
        $this->assertFalse($inv->canTransitionTo(InvoiceStatus::Void));

        $this->expectException(InvalidInvoiceStateTransitionException::class);
        $inv->transitionTo(InvoiceStatus::Open);
    }

    /** 17. void invoice terminal */
    public function test_void_invoice_terminal(): void
    {
        $inv = Invoice::query()->create([
            'organization_id' => $this->org->id,
            'total' => 700000,
            'status' => InvoiceStatus::Open,
        ]);
        $inv->void('Cancelled plan');

        $this->assertTrue($inv->isTerminal());
        $this->assertFalse($inv->canTransitionTo(InvoiceStatus::Paid));

        $this->expectException(InvalidInvoiceStateTransitionException::class);
        $inv->transitionTo(InvoiceStatus::Paid);
    }

    /** 18. cancelled invoice terminal */
    public function test_cancelled_invoice_terminal(): void
    {
        $inv = Invoice::query()->create([
            'organization_id' => $this->org->id,
            'total' => 700000,
            'status' => InvoiceStatus::Open,
        ]);
        $inv->cancel('Subscription terminated');

        $this->assertTrue($inv->isTerminal());
        $this->assertFalse($inv->canTransitionTo(InvoiceStatus::Paid));

        $this->expectException(InvalidInvoiceStateTransitionException::class);
        $inv->transitionTo(InvoiceStatus::Paid);
    }

    /** 19. legacy invoice access remains compatible */
    public function test_legacy_invoice_access_remains_compatible(): void
    {
        $now = now()->startOfMinute();

        // Creating with legacy 'amount' and 'due_date'
        $inv = Invoice::query()->create([
            'organization_id' => $this->org->id,
            'amount' => 700000,
            'currency' => 'NGN',
            'due_date' => $now->copy()->addDays(5),
            'status' => 'pending',
            'payment_reference' => 'legacy_ref_123',
        ]);

        $this->assertSame(700000, $inv->amount);
        $this->assertSame(700000, $inv->total);
        $this->assertTrue($inv->due_date->equalTo($now->copy()->addDays(5)));
        $this->assertTrue($inv->due_at->equalTo($now->copy()->addDays(5)));
        $this->assertSame('legacy_ref_123', $inv->payment_reference);

        // Legacy markPaid call
        $inv->markPaid('new_legacy_ref_456');
        $this->assertTrue($inv->isPaid());
        $this->assertSame('paid', $inv->status);
        $this->assertSame('new_legacy_ref_456', $inv->payment_reference);
        $this->assertNotNull($inv->paid_at);
    }

    /** 20. existing invoice reminder code still works */
    public function test_existing_invoice_reminder_code_still_works(): void
    {
        // Create an open invoice due in 7 days
        $inv7 = Invoice::query()->create([
            'organization_id' => $this->org->id,
            'amount' => 700000,
            'status' => Invoice::STATUS_PENDING,
            'due_date' => now()->startOfDay()->addDays(7),
        ]);

        $this->artisan('billing:send-invoice-reminders', ['--dry-run' => true])
            ->expectsOutputToContain("Would send 7_days_before reminder for Invoice {$inv7->invoice_number}")
            ->assertSuccessful();
    }

    /** 21. existing Admin finance queries still work */
    public function test_existing_admin_finance_queries_still_work(): void
    {
        $inv = Invoice::query()->create([
            'organization_id' => $this->org->id,
            'amount' => 700000,
            'status' => 'paid',
            'due_date' => now()->subDay(),
            'paid_at' => now(),
        ]);

        $finance = app(PlatformFinanceService::class);
        $paginated = $finance->getInvoicesPaginated(['status' => 'paid']);

        $this->assertGreaterThanOrEqual(1, $paginated->total());
        $first = $paginated->items()[0];
        $this->assertSame('paid', $first->status);
        $this->assertSame(700000, $first->amount);
    }
}
