<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Plan;
use App\Models\Subscription;
use DateTimeInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InvoiceGenerationService
{
    /**
     * Calculate period boundaries adhering to SoundMix subscription anniversary semantics.
     *
     * For example, for monthly billing starting 2026-10-19:
     * period: 2026-10-19 -> 2026-11-18
     * next period: 2026-11-19 -> 2026-12-18
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function calculatePeriod(
        Subscription $subscription,
        ?Plan $plan = null,
        ?DateTimeInterface $startDate = null,
    ): array {
        $plan = $plan ?? $subscription->plan;
        if ($plan === null) {
            throw new \InvalidArgumentException('A plan must be provided or associated with the subscription.');
        }

        $start = $startDate
            ? Carbon::parse($startDate)->startOfDay()
            : ($subscription->renews_at && ! $subscription->isOnFreePeriod() && $subscription->renews_at->isFuture()
                ? $subscription->renews_at->copy()->startOfDay()
                : now()->startOfDay());

        $renewsAt = $plan->periodEndFrom($start);
        $end = Carbon::parse($renewsAt)->subDay()->startOfDay();

        return [$start, $end];
    }

    /**
     * Generate or retrieve an authoritative OPEN invoice for a subscription checkout.
     *
     * Responsibilities:
     * - enforce one invoice per subscription period
     * - return existing invoice if called idempotently for same plan
     * - if plan changed while an unpaid invoice is open, void obsolete invoice and create new one
     * - create plan line item in integer minor units
     * - set due_at = issued_at (immediate pay strategy)
     * - transition draft -> open and emit InvoiceIssued
     */
    public function generateForCheckout(
        Subscription $subscription,
        Plan $plan,
        ?DateTimeInterface $startDate = null,
    ): Invoice {
        if ($plan->isFree()) {
            throw new \InvalidArgumentException('Paid checkout cannot generate invoice for free plan.');
        }

        [$start, $end] = $this->calculatePeriod($subscription, $plan, $startDate);

        return DB::transaction(function () use ($subscription, $plan, $start, $end) {
            // Find any existing invoice for this exact subscription period
            $existing = Invoice::query()
                ->where('subscription_id', $subscription->id)
                ->whereDate('billing_period_start', $start)
                ->whereDate('billing_period_end', $end)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                // If invoice is already paid or terminal, reuse it
                if ($existing->isPaid() || $existing->isTerminal()) {
                    return $existing;
                }

                // If existing open invoice is for the exact same plan, reuse it idempotently
                if ((int) $existing->plan_id === (int) $plan->id) {
                    return $existing;
                }

                // Section 10: Creator changed plan during open unpaid invoice
                // Void obsolete invoice for old plan and preserve audit history
                $existing->void("Superseded by plan change to {$plan->name}");
                $meta = $existing->metadata ?? [];
                $meta['superseded_by_plan_id'] = $plan->id;
                $meta['superseded_by_plan_name'] = $plan->name;
                $meta['superseded_billing_period_start'] = $existing->billing_period_start?->toDateString();
                $meta['superseded_billing_period_end'] = $existing->billing_period_end?->toDateString();
                $existing->metadata = $meta;
                // Clear period dates on voided record so unique constraint is released for replacement invoice
                $existing->billing_period_start = null;
                $existing->billing_period_end = null;
                $existing->save();
            }

            try {
                $issuedAt = now();
                $dueAt = $issuedAt; // Immediate checkout due date strategy

                $invoice = Invoice::query()->create([
                    'organization_id' => $subscription->organization_id,
                    'subscription_id' => $subscription->id,
                    'plan_id' => $plan->id,
                    'currency' => $plan->currency ?? 'NGN',
                    'billing_period_start' => $start,
                    'billing_period_end' => $end,
                    'subtotal' => $plan->amount,
                    'discount_total' => 0,
                    'tax_total' => 0,
                    'total' => $plan->amount,
                    'amount_paid' => 0,
                    'amount_due' => $plan->amount,
                    'status' => InvoiceStatus::Draft->value,
                    'due_at' => $dueAt,
                    'due_date' => $dueAt,
                ]);

                InvoiceLine::query()->create([
                    'invoice_id' => $invoice->id,
                    'description' => "{$plan->name} subscription",
                    'quantity' => 1,
                    'unit_amount' => $plan->amount,
                    'subtotal' => $plan->amount,
                    'total' => $plan->amount,
                ]);

                $invoice->recalculateTotals();
                $invoice->save();

                // Transition Draft -> Open (triggers InvoiceIssued domain event)
                $invoice->issue();

                return $invoice->fresh(['lines']);
            } catch (QueryException $e) {
                // If a concurrent process created an invoice for the same period, fetch and return it
                $concurrent = Invoice::query()
                    ->where('subscription_id', $subscription->id)
                    ->whereDate('billing_period_start', $start)
                    ->whereDate('billing_period_end', $end)
                    ->first();

                if ($concurrent) {
                    return $concurrent;
                }

                throw $e;
            }
        });
    }

    /**
     * Generate renewal invoice for an upcoming subscription period.
     *
     * Window: typically 7 days before subscription renews_at.
     * Due date strategy: due_at = subscription renews_at.
     * Skips: cancelled, free/trial-only subscriptions, or already invoiced cycles.
     */
    public function generateRenewal(
        Subscription $subscription,
        ?DateTimeInterface $asOf = null,
    ): ?Invoice {
        $subscription->loadMissing(['plan', 'organization']);

        // Must not be cancelled
        if ($subscription->status === SubscriptionStatus::Cancelled) {
            return null;
        }

        // Must not be free or trialing without paid plan
        if ($subscription->isOnFreePeriod() || $subscription->status === SubscriptionStatus::Trialing) {
            return null;
        }

        $plan = $subscription->plan;
        if ($plan === null || $plan->isFree() || $plan->isCustom()) {
            return null;
        }

        if ($subscription->renews_at === null) {
            return null;
        }

        $now = $asOf ? Carbon::parse($asOf) : now();

        // 7-day lookahead window: generate only when renews_at <= now + 7 days
        if ($subscription->renews_at->gt($now->copy()->addDays(7)->endOfDay())) {
            return null;
        }

        $start = $subscription->renews_at->copy()->startOfDay();
        $renewsAt = $plan->periodEndFrom($start);
        $end = Carbon::parse($renewsAt)->subDay()->startOfDay();

        return DB::transaction(function () use ($subscription, $plan, $start, $end) {
            $existing = Invoice::query()
                ->where('subscription_id', $subscription->id)
                ->whereDate('billing_period_start', $start)
                ->whereDate('billing_period_end', $end)
                ->first();

            if ($existing !== null) {
                return $existing; // Idempotent: already generated
            }

            try {
                $dueAt = $subscription->renews_at; // Recurring due date strategy

                $invoice = Invoice::query()->create([
                    'organization_id' => $subscription->organization_id,
                    'subscription_id' => $subscription->id,
                    'plan_id' => $plan->id,
                    'currency' => $plan->currency ?? 'NGN',
                    'billing_period_start' => $start,
                    'billing_period_end' => $end,
                    'subtotal' => $plan->amount,
                    'discount_total' => 0,
                    'tax_total' => 0,
                    'total' => $plan->amount,
                    'amount_paid' => 0,
                    'amount_due' => $plan->amount,
                    'status' => InvoiceStatus::Draft->value,
                    'due_at' => $dueAt,
                    'due_date' => $dueAt,
                ]);

                InvoiceLine::query()->create([
                    'invoice_id' => $invoice->id,
                    'description' => "{$plan->name} subscription",
                    'quantity' => 1,
                    'unit_amount' => $plan->amount,
                    'subtotal' => $plan->amount,
                    'total' => $plan->amount,
                ]);

                $invoice->recalculateTotals();
                $invoice->save();

                // Transition Draft -> Open (triggers InvoiceIssued domain event)
                $invoice->issue();

                return $invoice->fresh(['lines']);
            } catch (QueryException $e) {
                $concurrent = Invoice::query()
                    ->where('subscription_id', $subscription->id)
                    ->whereDate('billing_period_start', $start)
                    ->whereDate('billing_period_end', $end)
                    ->first();

                if ($concurrent) {
                    return $concurrent;
                }

                throw $e;
            }
        });
    }

    /**
     * Scan and generate renewal invoices for all subscriptions renewing within 7 days.
     *
     * @return Collection<int, Invoice>
     */
    public function generateUpcomingRenewals(?DateTimeInterface $asOf = null): Collection
    {
        $now = $asOf ? Carbon::parse($asOf) : now();
        $targetWindow = $now->copy()->addDays(7)->endOfDay();

        $subscriptions = Subscription::query()
            ->with(['plan', 'organization'])
            ->where('status', '!=', SubscriptionStatus::Cancelled->value)
            ->whereNotNull('renews_at')
            ->where('renews_at', '<=', $targetWindow)
            ->get();

        $generated = collect();

        foreach ($subscriptions as $subscription) {
            try {
                $invoice = $this->generateRenewal($subscription, $now);
                if ($invoice !== null) {
                    $generated->push($invoice);
                }
            } catch (\Throwable $e) {
                Log::error('Failed to generate renewal invoice for subscription', [
                    'subscription_id' => $subscription->id,
                    'organization_id' => $subscription->organization_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $generated;
    }
}
