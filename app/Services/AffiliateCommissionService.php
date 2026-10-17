<?php

namespace App\Services;

use App\Enums\AffiliateCommissionStatus;
use App\Enums\AffiliatePayoutStatus;
use App\Enums\OrgRole;
use App\Events\AffiliateCommissionPayable;
use App\Events\AffiliateCommissionReversed;
use App\Events\AffiliatePayoutApproved;
use App\Events\AffiliatePayoutPaid;
use App\Events\AffiliateQualificationAchieved;
use App\Models\AffiliateAccount;
use App\Models\AffiliateCommission;
use App\Models\AffiliateCommissionAudit;
use App\Models\AffiliatePayout;
use App\Models\AffiliatePayoutItem;
use App\Models\Organization;
use App\Models\PaymentCheckout;
use App\Models\Plan;
use App\Models\User;
use App\Support\AffiliateQualificationPolicy;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Authoritative affiliate commission engine for recurring church subscription revenue.
 * Operates an immutable affiliate commission ledger with adjustment entries.
 */
class AffiliateCommissionService
{
    /**
     * Canonical consumer for confirmed subscription payments.
     * Decoupled from provider-specific payment gateways.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function recordConfirmedSubscriptionPayment(
        Organization $organization,
        ?Plan $plan,
        string $paymentReference,
        int $grossAmountMinor,
        string $currency,
        DateTimeInterface $confirmedAt,
        bool $isRenewal = false,
        ?string $providerReference = null,
        ?int $paymentCheckoutId = null,
        array $metadata = [],
    ): ?AffiliateCommission {
        if (! AffiliateProgrammeSettings::enabled()) {
            return null;
        }

        if ($plan === null || $plan->isFree()) {
            return null;
        }

        $organization->loadMissing('affiliateAttribution.affiliateAccount');
        $attribution = $organization->affiliateAttribution;
        if ($attribution === null) {
            return null;
        }

        if (! $attribution->source->isEarningEligible()) {
            return null;
        }

        $affiliate = $attribution->affiliateAccount;
        if ($affiliate === null || ! $affiliate->canRefer()) {
            return null;
        }

        if ($this->isDisallowedRelationship($affiliate, $organization)) {
            Log::warning('Affiliate commission blocked: disallowed relationship / self-referral', [
                'affiliate_id' => $affiliate->id,
                'organization_id' => $organization->id,
            ]);

            return null;
        }

        $reference = trim($paymentReference);
        if ($reference === '') {
            return null;
        }

        $idempotencyKey = "sub_payment:{$reference}";
        $existing = AffiliateCommission::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return $existing;
        }

        if ($grossAmountMinor <= 0) {
            return null;
        }

        $currency = strtoupper(trim($currency));
        if (strlen($currency) !== 3) {
            $currency = 'NGN';
        }

        $rateBps = (int) (AffiliateProgrammeSettings::all()['default_commission_bps'] ?? 1000);
        $commissionAmountMinor = intdiv($grossAmountMinor * $rateBps, 10000);
        if ($commissionAmountMinor <= 0) {
            return null;
        }

        $holdDays = AffiliateQualificationPolicy::holdDays();
        $confirmedCarbon = Carbon::instance($confirmedAt);
        $holdUntil = $confirmedCarbon->copy()->addDays(max(1, $holdDays));
        $providerRef = trim((string) ($providerReference ?? ''));

        return DB::transaction(function () use (
            $affiliate,
            $organization,
            $reference,
            $providerRef,
            $idempotencyKey,
            $grossAmountMinor,
            $rateBps,
            $commissionAmountMinor,
            $isRenewal,
            $currency,
            $attribution,
            $holdUntil,
            $confirmedCarbon,
        ): AffiliateCommission {
            $existing = AffiliateCommission::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing !== null) {
                return $existing;
            }

            $commission = AffiliateCommission::query()->create([
                'affiliate_account_id' => $affiliate->id,
                'organization_id' => $organization->id,
                'subscription_id' => $organization->subscription?->id,
                'payment_reference' => $reference,
                'provider_reference' => $providerRef !== '' ? $providerRef : null,
                'idempotency_key' => $idempotencyKey,
                'gross_amount_minor' => $grossAmountMinor,
                'commission_rate_bps' => $rateBps,
                'commission_amount_minor' => $commissionAmountMinor,
                'is_renewal' => $isRenewal,
                'currency' => $currency,
                'attribution_source' => $attribution->source->value,
                'status' => AffiliateCommissionStatus::Held,
                'hold_until' => $holdUntil,
                'confirmed_at' => $confirmedCarbon,
            ]);

            $this->recordAudit(
                commission: $commission,
                account: $affiliate,
                action: 'commission_created',
                toStatus: AffiliateCommissionStatus::Held->value,
                amountMinor: $commissionAmountMinor,
                currency: $currency,
                notes: "Commission accrued from payment {$reference} (held for 14 days under Policy A).",
            );

            $this->checkAndRecordQualification($affiliate);

            return $commission;
        });
    }

    /**
     * Backward-compatible delegation wrapper for legacy callers.
     *
     * @param  array<string, mixed>  $data
     */
    public function recordSubscriptionPayment(
        Organization $organization,
        Plan $plan,
        array $data,
        ?PaymentCheckout $checkout = null,
    ): ?AffiliateCommission {
        $reference = trim((string) ($data['reference'] ?? $checkout?->reference ?? ''));
        $grossAmountMinor = (int) ($data['amount'] ?? $checkout?->amount ?? $plan->amount);
        $currency = strtoupper(trim((string) ($data['currency'] ?? $checkout?->currency ?? $plan->currency ?? 'NGN')));
        $isRenewal = ! empty(data_get($data, 'subscription.subscription_code')) || ($checkout === null);
        $providerRef = trim((string) (data_get($data, 'id') ?? data_get($data, 'transaction_id') ?? ''));

        return $this->recordConfirmedSubscriptionPayment(
            organization: $organization,
            plan: $plan,
            paymentReference: $reference,
            grossAmountMinor: $grossAmountMinor,
            currency: $currency,
            confirmedAt: now(),
            isRenewal: $isRenewal,
            providerReference: $providerRef !== '' ? $providerRef : null,
            paymentCheckoutId: $checkout?->id,
            metadata: $data,
        );
    }

    /**
     * Check if the affiliate has a disallowed relationship (self-referral or controlled organization).
     */
    public function isDisallowedRelationship(AffiliateAccount $affiliate, Organization $organization): bool
    {
        // 1. Direct membership with management role or any user attachment
        $membership = $organization->users()->where('users.id', $affiliate->user_id)->first();
        if ($membership !== null) {
            $role = OrgRole::tryFrom((string) $membership->pivot->role);
            if ($role?->canManage() ?? true) {
                return true;
            }
        }

        // 2. Organization owner is the affiliate's user
        if ($organization->owners()->where('users.id', $affiliate->user_id)->exists()) {
            return true;
        }

        // 3. Email matching affiliate user
        $affiliateUser = $affiliate->user;
        if ($affiliateUser !== null && $organization->owners()->where('users.email', $affiliateUser->email)->exists()) {
            return true;
        }

        return false;
    }

    /**
     * Check whether an affiliate qualifies for payouts based on distinct paying organizations.
     * Qualification is permanent once earned.
     */
    public function checkAndRecordQualification(AffiliateAccount $affiliate): bool
    {
        if ($affiliate->isQualified()) {
            return true;
        }

        return DB::transaction(function () use ($affiliate): bool {
            $locked = AffiliateAccount::query()
                ->where('id', $affiliate->id)
                ->lockForUpdate()
                ->first();

            if (! $locked || $locked->isQualified()) {
                if ($locked) {
                    $affiliate->payout_qualified_at = $locked->payout_qualified_at;
                }

                return true;
            }

            $target = AffiliateQualificationPolicy::requiredDistinctPayingOrganisations();
            $count = $locked->qualifyingOrganizationsCount();

            if ($count >= $target) {
                $locked->update(['payout_qualified_at' => now()]);
                $affiliate->payout_qualified_at = $locked->payout_qualified_at;

                $this->recordAudit(
                    account: $locked,
                    action: 'qualification_achieved',
                    notes: "Permanent payout qualification achieved with {$count} distinct paying churches.",
                );

                event(new AffiliateQualificationAchieved($locked));

                // Once qualified, release any matured held commissions for this affiliate immediately
                $this->processMatureHolds($locked);

                return true;
            }

            return false;
        });
    }

    /**
     * Process commissions whose hold has matured.
     * Only transitions commissions to payable if the affiliate is payout-qualified.
     */
    public function processMatureHolds(?AffiliateAccount $affiliate = null): int
    {
        $query = AffiliateCommission::query()
            ->held()
            ->where('hold_until', '<=', now())
            ->with('affiliateAccount');

        if ($affiliate !== null) {
            $query->where('affiliate_account_id', $affiliate->id);
        }

        $commissions = $query->get();
        $transitioned = 0;

        foreach ($commissions as $commission) {
            $account = $commission->affiliateAccount;
            if ($account === null || ! AffiliateQualificationPolicy::canTransitionToPayable($commission, $account)) {
                // Pre-qualification policy (Policy A): remain held until affiliate qualifies
                continue;
            }

            DB::transaction(function () use ($commission, $account): void {
                $commission->update([
                    'status' => AffiliateCommissionStatus::Payable,
                    'payable_at' => now(),
                ]);

                $this->recordAudit(
                    commission: $commission,
                    account: $account,
                    action: 'commission_payable',
                    fromStatus: AffiliateCommissionStatus::Held->value,
                    toStatus: AffiliateCommissionStatus::Payable->value,
                    amountMinor: $commission->commission_amount_minor,
                    currency: $commission->currency,
                    notes: '14-day hold period completed and affiliate is qualified; commission is now payable.',
                );

                event(new AffiliateCommissionPayable($commission));
            });

            $transitioned++;
        }

        return $transitioned;
    }

    /**
     * Canonical consumer for subscription payment reversals/refunds/disputes.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function handlePaymentReversal(
        string $paymentReference,
        ?string $reversalReference = null,
        ?int $amountMinor = null,
        string $reason = 'Payment reversed',
        ?DateTimeInterface $reversedAt = null,
        ?int $actorId = null,
        array $metadata = [],
    ): int {
        return $this->reverseForPayment(
            paymentReference: $paymentReference,
            reason: $reason,
            actorId: $actorId,
            reversalReference: $reversalReference,
            amountMinor: $amountMinor,
        );
    }

    /**
     * Reverse commissions associated with a payment reference.
     */
    public function reverseForPayment(
        string $paymentReference,
        string $reason,
        ?int $actorId = null,
        ?string $reversalReference = null,
        ?int $amountMinor = null,
    ): int {
        $commissions = AffiliateCommission::query()
            ->where('payment_reference', $paymentReference)
            ->whereNull('reversal_of_commission_id')
            ->whereNotIn('status', [
                AffiliateCommissionStatus::Reversed->value,
                AffiliateCommissionStatus::Voided->value,
            ])
            ->get();

        $count = 0;
        foreach ($commissions as $commission) {
            // Partial refund check: Subscription domain does not represent partial refunds.
            if ($amountMinor !== null && $amountMinor > 0 && $amountMinor < $commission->gross_amount_minor) {
                Log::warning('Partial subscription refund flagged: unsupported by subscription payment domain', [
                    'payment_reference' => $paymentReference,
                    'commission_id' => $commission->id,
                    'refund_amount_minor' => $amountMinor,
                    'gross_amount_minor' => $commission->gross_amount_minor,
                ]);

                $this->recordAudit(
                    commission: $commission,
                    account: $commission->affiliateAccount,
                    action: 'partial_reversal_flagged',
                    actorId: $actorId,
                    amountMinor: $amountMinor,
                    currency: $commission->currency,
                    notes: "Partial subscription refund of {$commission->currency} ".number_format($amountMinor / 100, 2)." flagged. The subscription payment domain does not support partial refunds; flagged for manual review.",
                );

                throw new InvalidArgumentException(
                    "Partial subscription refunds are not supported by the payment domain; ambiguous partial reversal for reference {$paymentReference} flagged for manual review."
                );
            }

            $this->reverseCommission($commission, $reason, $actorId, $reversalReference);
            $count++;
        }

        return $count;
    }

    /**
     * Authoritatively reverse an individual commission.
     * Operates on the immutable affiliate commission ledger with adjustment entries.
     * - Held -> Voided
     * - Payable -> Reversed
     * - Paid -> Historical row stays untouched; a negative adjustment row is appended with status = Reversed
     */
    public function reverseCommission(
        AffiliateCommission $commission,
        string $reason,
        ?int $actorId = null,
        ?string $reversalReference = null,
    ): AffiliateCommission {
        return DB::transaction(function () use ($commission, $reason, $actorId, $reversalReference): AffiliateCommission {
            $commission = AffiliateCommission::query()->where('id', $commission->id)->lockForUpdate()->firstOrFail();
            $account = $commission->affiliateAccount;

            // Idempotency guard: already reversed or voided
            if ($commission->isVoided() || $commission->isReversed()) {
                return $commission;
            }

            if ($commission->isHeld()) {
                $commission->update([
                    'status' => AffiliateCommissionStatus::Voided,
                    'reversed_at' => now(),
                    'reversal_reason' => $reason,
                ]);

                $this->recordAudit(
                    commission: $commission,
                    account: $account,
                    action: 'commission_voided',
                    actorId: $actorId,
                    fromStatus: AffiliateCommissionStatus::Held->value,
                    toStatus: AffiliateCommissionStatus::Voided->value,
                    amountMinor: $commission->commission_amount_minor,
                    currency: $commission->currency,
                    notes: "Held commission voided due to payment reversal: {$reason}",
                );

                event(new AffiliateCommissionReversed($commission, $reason));

                return $commission;
            }

            if ($commission->isPayable()) {
                $commission->update([
                    'status' => AffiliateCommissionStatus::Reversed,
                    'reversed_at' => now(),
                    'reversal_reason' => $reason,
                ]);

                $this->recordAudit(
                    commission: $commission,
                    account: $account,
                    action: 'commission_reversed',
                    actorId: $actorId,
                    fromStatus: AffiliateCommissionStatus::Payable->value,
                    toStatus: AffiliateCommissionStatus::Reversed->value,
                    amountMinor: $commission->commission_amount_minor,
                    currency: $commission->currency,
                    notes: "Payable commission reversed due to payment reversal: {$reason}",
                );

                event(new AffiliateCommissionReversed($commission, $reason));

                return $commission;
            }

            if ($commission->isPaid()) {
                // Check if negative adjustment already exists for this commission
                $existingAdj = AffiliateCommission::query()
                    ->where('reversal_of_commission_id', $commission->id)
                    ->first();

                if ($existingAdj !== null) {
                    return $existingAdj;
                }

                $suffix = $reversalReference !== null && $reversalReference !== '' ? $reversalReference : $commission->payment_reference;
                $idempotencyKey = "reversal:{$commission->id}:{$suffix}";

                $existingByKey = AffiliateCommission::query()->where('idempotency_key', $idempotencyKey)->first();
                if ($existingByKey !== null) {
                    return $existingByKey;
                }

                // Never mutate historical paid row. Append negative adjustment row on the immutable ledger.
                $adjustment = AffiliateCommission::query()->create([
                    'affiliate_account_id' => $commission->affiliate_account_id,
                    'organization_id' => $commission->organization_id,
                    'subscription_id' => $commission->subscription_id,
                    'affiliate_payout_id' => null,
                    'payment_reference' => $commission->payment_reference,
                    'provider_reference' => $commission->provider_reference,
                    'idempotency_key' => $idempotencyKey,
                    'gross_amount_minor' => -$commission->gross_amount_minor,
                    'commission_rate_bps' => $commission->commission_rate_bps,
                    'commission_amount_minor' => -$commission->commission_amount_minor,
                    'currency' => $commission->currency,
                    'attribution_source' => $commission->attribution_source,
                    'status' => AffiliateCommissionStatus::Reversed,
                    'hold_until' => now(),
                    'confirmed_at' => now(),
                    'payable_at' => null,
                    'reversal_of_commission_id' => $commission->id,
                    'reversed_at' => now(),
                    'reversal_reason' => $reason,
                ]);

                $this->recordAudit(
                    commission: $adjustment,
                    account: $account,
                    action: 'commission_reversed',
                    actorId: $actorId,
                    fromStatus: null,
                    toStatus: AffiliateCommissionStatus::Reversed->value,
                    amountMinor: -$commission->commission_amount_minor,
                    currency: $commission->currency,
                    notes: "Negative adjustment created for paid commission #{$commission->id}: {$reason}",
                );

                event(new AffiliateCommissionReversed($adjustment, $reason));

                return $adjustment;
            }

            return $commission;
        });
    }

    /**
     * Create a manual payout draft batch for an affiliate.
     * State: DRAFT
     *
     * @param  list<int>|null  $commissionIds
     */
    public function createPayout(
        AffiliateAccount $affiliate,
        string $currency = 'NGN',
        ?array $commissionIds = null,
        ?User $actor = null,
        ?string $notes = null,
    ): AffiliatePayout {
        if (! $affiliate->isQualified()) {
            throw ValidationException::withMessages([
                'affiliate_id' => 'Affiliate has not qualified for payouts yet (minimum 5 distinct paying churches required).',
            ]);
        }

        return DB::transaction(function () use ($affiliate, $currency, $commissionIds, $actor, $notes): AffiliatePayout {
            $currency = strtoupper(trim($currency));

            $query = AffiliateCommission::query()
                ->where('affiliate_account_id', $affiliate->id)
                ->where('status', AffiliateCommissionStatus::Payable->value)
                ->where('currency', $currency)
                ->whereNull('affiliate_payout_id')
                ->lockForUpdate();

            if ($commissionIds !== null && count($commissionIds) > 0) {
                $uniqueRequestedIds = array_values(array_unique($commissionIds));
                $query->whereIn('id', $uniqueRequestedIds);
            }

            $commissions = $query->get();
            if ($commissions->isEmpty()) {
                throw ValidationException::withMessages([
                    'commissions' => 'No payable commissions found matching the criteria.',
                ]);
            }

            if ($commissionIds !== null && count($commissionIds) > 0) {
                if ($commissions->count() !== count(array_unique($commissionIds))) {
                    throw ValidationException::withMessages([
                        'commissions' => 'One or more requested commissions are not in payable status or are already allocated to a payout.',
                    ]);
                }
            }

            $totalAmountMinor = (int) $commissions->sum('commission_amount_minor');
            if ($totalAmountMinor <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Payout total amount must be greater than zero.',
                ]);
            }

            $minPayoutMinor = (int) (AffiliateProgrammeSettings::all()['minimum_payout_minor'] ?? 0);
            if ($minPayoutMinor > 0 && $totalAmountMinor < $minPayoutMinor) {
                throw ValidationException::withMessages([
                    'amount' => "Payout amount ({$currency} ".number_format($totalAmountMinor / 100, 2).") is below minimum payout threshold ({$currency} ".number_format($minPayoutMinor / 100, 2).').',
                ]);
            }

            $payout = AffiliatePayout::query()->create([
                'affiliate_account_id' => $affiliate->id,
                'payout_reference' => AffiliatePayout::generateReference(),
                'currency' => $currency,
                'amount_minor' => $totalAmountMinor,
                'bank_name' => $affiliate->bank_name,
                'bank_code' => $affiliate->bank_code,
                'account_name' => $affiliate->account_name,
                'account_number' => $affiliate->account_number,
                'status' => AffiliatePayoutStatus::Draft,
                'notes' => $notes,
                'admin_id' => $actor?->id,
                'approved_by' => null,
                'approved_at' => null,
            ]);

            foreach ($commissions as $comm) {
                AffiliatePayoutItem::query()->create([
                    'affiliate_payout_id' => $payout->id,
                    'affiliate_commission_id' => $comm->id,
                    'amount_minor' => $comm->commission_amount_minor,
                ]);

                $comm->update(['affiliate_payout_id' => $payout->id]);
            }

            $this->recordAudit(
                payout: $payout,
                account: $affiliate,
                action: 'payout_created',
                actorId: $actor?->id,
                toStatus: AffiliatePayoutStatus::Draft->value,
                amountMinor: $totalAmountMinor,
                currency: $currency,
                notes: "Payout draft {$payout->payout_reference} created with {$commissions->count()} commissions.",
            );

            return $payout;
        });
    }

    /**
     * Approve a draft payout batch.
     * State transition: DRAFT -> APPROVED
     */
    public function approvePayout(AffiliatePayout $payout, User $actor): AffiliatePayout
    {
        return DB::transaction(function () use ($payout, $actor): AffiliatePayout {
            $payout = AffiliatePayout::query()->where('id', $payout->id)->lockForUpdate()->firstOrFail();

            if (! $payout->canBeApproved()) {
                throw ValidationException::withMessages([
                    'payout' => "Payout cannot be approved from its current status ({$payout->status->label()}).",
                ]);
            }

            $payout->update([
                'status' => AffiliatePayoutStatus::Approved,
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ]);

            $this->recordAudit(
                payout: $payout,
                account: $payout->affiliateAccount,
                action: 'payout_approved',
                actorId: $actor->id,
                fromStatus: AffiliatePayoutStatus::Draft->value,
                toStatus: AffiliatePayoutStatus::Approved->value,
                amountMinor: $payout->amount_minor,
                currency: $payout->currency,
                notes: "Payout batch {$payout->payout_reference} approved.",
            );

            $fresh = $payout->fresh();
            event(new AffiliatePayoutApproved($fresh));

            return $fresh;
        });
    }

    /**
     * Mark an approved payout as paid/settled.
     * State transition: APPROVED -> PAID
     * (Draft payouts cannot be paid directly; must be approved first)
     */
    public function markPayoutPaid(AffiliatePayout $payout, User $actor, ?string $notes = null): AffiliatePayout
    {
        return DB::transaction(function () use ($payout, $actor, $notes): AffiliatePayout {
            $payout = AffiliatePayout::query()->where('id', $payout->id)->lockForUpdate()->firstOrFail();

            if ($payout->status === AffiliatePayoutStatus::Draft) {
                throw ValidationException::withMessages([
                    'payout' => 'Draft payouts must be approved before they can be marked paid.',
                ]);
            }

            if (! $payout->canBePaid()) {
                throw ValidationException::withMessages([
                    'payout' => "Payout cannot be marked paid from its current status ({$payout->status->label()}).",
                ]);
            }

            $payout->update([
                'status' => AffiliatePayoutStatus::Paid,
                'paid_by' => $actor->id,
                'paid_at' => now(),
                'notes' => $notes ?? $payout->notes,
            ]);

            $commissions = $payout->commissions()->lockForUpdate()->get();
            foreach ($commissions as $commission) {
                $commission->update([
                    'status' => AffiliateCommissionStatus::Paid,
                    'paid_at' => now(),
                ]);

                $this->recordAudit(
                    commission: $commission,
                    payout: $payout,
                    account: $payout->affiliateAccount,
                    action: 'commission_paid',
                    actorId: $actor->id,
                    fromStatus: AffiliateCommissionStatus::Payable->value,
                    toStatus: AffiliateCommissionStatus::Paid->value,
                    amountMinor: $commission->commission_amount_minor,
                    currency: $commission->currency,
                    notes: "Commission paid via payout batch {$payout->payout_reference}.",
                );
            }

            $this->recordAudit(
                payout: $payout,
                account: $payout->affiliateAccount,
                action: 'payout_paid',
                actorId: $actor->id,
                fromStatus: AffiliatePayoutStatus::Approved->value,
                toStatus: AffiliatePayoutStatus::Paid->value,
                amountMinor: $payout->amount_minor,
                currency: $payout->currency,
                notes: "Payout batch {$payout->payout_reference} marked as paid.",
            );

            $fresh = $payout->fresh();
            event(new AffiliatePayoutPaid($fresh));

            return $fresh;
        });
    }

    /**
     * Affiliate-initiated payout request workflow.
     * State transition: Creates a Draft payout batch awaiting Admin review.
     * Only uses PAYABLE commissions. Never uses HELD or reversed commissions.
     * Prevents duplicate allocation by locking commissions to this payout.
     */
    public function requestPayout(
        AffiliateAccount $affiliate,
        string $currency = 'NGN',
        ?string $notes = null,
    ): AffiliatePayout {
        if (! $affiliate->canRefer()) {
            throw ValidationException::withMessages([
                'affiliate' => 'Your affiliate account is not eligible to request payouts.',
            ]);
        }

        if (! $affiliate->isQualified()) {
            throw ValidationException::withMessages([
                'qualification' => 'You must reach payout qualification (minimum 5 distinct paying referred organisations) before requesting a payout.',
            ]);
        }

        return DB::transaction(function () use ($affiliate, $currency, $notes): AffiliatePayout {
            $currency = strtoupper(trim($currency));

            // Lock eligible payable commissions for this affiliate that have not been assigned to a payout
            $commissions = AffiliateCommission::query()
                ->where('affiliate_account_id', $affiliate->id)
                ->where('status', AffiliateCommissionStatus::Payable->value)
                ->where('currency', $currency)
                ->whereNull('affiliate_payout_id')
                ->lockForUpdate()
                ->get();

            if ($commissions->isEmpty()) {
                throw ValidationException::withMessages([
                    'amount' => 'You do not have any payable commissions available for payout.',
                ]);
            }

            $totalAmountMinor = (int) $commissions->sum('commission_amount_minor');
            if ($totalAmountMinor <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Available payout amount must be greater than zero.',
                ]);
            }

            $minPayoutMinor = (int) (AffiliateProgrammeSettings::all()['minimum_payout_minor'] ?? 0);
            if ($minPayoutMinor > 0 && $totalAmountMinor < $minPayoutMinor) {
                throw ValidationException::withMessages([
                    'amount' => "Your payable balance ({$currency} ".number_format($totalAmountMinor / 100, 2).") is below the minimum payout threshold of {$currency} ".number_format($minPayoutMinor / 100, 2).'.',
                ]);
            }

            $payout = AffiliatePayout::query()->create([
                'affiliate_account_id' => $affiliate->id,
                'payout_reference' => AffiliatePayout::generateReference(),
                'currency' => $currency,
                'amount_minor' => $totalAmountMinor,
                'bank_name' => $affiliate->bank_name,
                'bank_code' => $affiliate->bank_code,
                'account_name' => $affiliate->account_name,
                'account_number' => $affiliate->account_number,
                'status' => AffiliatePayoutStatus::Draft,
                'notes' => $notes ? 'Affiliate request: '.trim($notes) : 'Requested by affiliate',
                'admin_id' => null,
                'approved_by' => null,
                'approved_at' => null,
            ]);

            foreach ($commissions as $comm) {
                AffiliatePayoutItem::query()->create([
                    'affiliate_payout_id' => $payout->id,
                    'affiliate_commission_id' => $comm->id,
                    'amount_minor' => $comm->commission_amount_minor,
                ]);

                $comm->update(['affiliate_payout_id' => $payout->id]);
            }

            $this->recordAudit(
                payout: $payout,
                account: $affiliate,
                action: 'payout_requested',
                actorId: $affiliate->user_id,
                toStatus: AffiliatePayoutStatus::Draft->value,
                amountMinor: $totalAmountMinor,
                currency: $currency,
                notes: "Payout request {$payout->payout_reference} submitted by affiliate.",
            );

            return $payout;
        });
    }

    /**
     * Cancel an unpaid (draft or approved) payout batch.
     * State transition: DRAFT / APPROVED -> CANCELLED
     * (Paid payouts cannot be cancelled)
     */
    public function cancelPayout(AffiliatePayout $payout, User $actor, string $reason): AffiliatePayout
    {
        return DB::transaction(function () use ($payout, $actor, $reason): AffiliatePayout {
            $payout = AffiliatePayout::query()->where('id', $payout->id)->lockForUpdate()->firstOrFail();

            if (! $payout->canBeCancelled()) {
                throw ValidationException::withMessages([
                    'payout' => 'Paid payouts cannot be cancelled.',
                ]);
            }

            $commissions = $payout->commissions()->lockForUpdate()->get();
            foreach ($commissions as $commission) {
                $commission->update(['affiliate_payout_id' => null]);
            }

            $payout->update([
                'status' => AffiliatePayoutStatus::Cancelled,
                'cancelled_by' => $actor->id,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            $this->recordAudit(
                payout: $payout,
                account: $payout->affiliateAccount,
                action: 'payout_cancelled',
                actorId: $actor->id,
                fromStatus: $payout->getOriginal('status'),
                toStatus: AffiliatePayoutStatus::Cancelled->value,
                amountMinor: $payout->amount_minor,
                currency: $payout->currency,
                notes: "Payout batch cancelled: {$reason}",
            );

            return $payout->fresh();
        });
    }

    /**
     * Derive authoritative balance totals for an affiliate.
     *
     * @return array{
     *     held_minor: int,
     *     payable_minor: int,
     *     paid_minor: int,
     *     reversed_minor: int,
     *     total_unpaid_minor: int,
     *     currency: string
     * }
     */
    public function deriveBalances(AffiliateAccount $affiliate, string $currency = 'NGN'): array
    {
        $currency = strtoupper(trim($currency));

        $commissions = AffiliateCommission::query()
            ->where('affiliate_account_id', $affiliate->id)
            ->where('currency', $currency)
            ->get();

        $held = (int) $commissions->where('status', AffiliateCommissionStatus::Held)->sum('commission_amount_minor');
        $payable = (int) $commissions
            ->where('status', AffiliateCommissionStatus::Payable)
            ->whereNull('affiliate_payout_id')
            ->sum('commission_amount_minor');
        $paid = (int) $commissions->where('status', AffiliateCommissionStatus::Paid)->sum('commission_amount_minor');
        $reversed = (int) $commissions->whereIn('status', [
            AffiliateCommissionStatus::Reversed,
            AffiliateCommissionStatus::Voided,
        ])->sum('commission_amount_minor');

        return [
            'held_minor' => max(0, $held),
            'payable_minor' => max(0, $payable),
            'paid_minor' => max(0, $paid),
            'reversed_minor' => $reversed,
            'total_unpaid_minor' => max(0, $held + $payable),
            'current_unpaid_minor' => max(0, $held + $payable),
            'is_qualified' => $affiliate->isQualified(),
            'qualifying_orgs_count' => $affiliate->qualifyingOrganizationsCount(),
            'qualification_target' => AffiliateQualificationPolicy::requiredDistinctPayingOrganisations(),
            'currency' => $currency,
        ];
    }

    /**
     * Helper to write a structured audit record.
     */
    protected function recordAudit(
        ?AffiliateCommission $commission = null,
        ?AffiliatePayout $payout = null,
        ?AffiliateAccount $account = null,
        string $action = '',
        ?int $actorId = null,
        string|\BackedEnum|null $fromStatus = null,
        string|\BackedEnum|null $toStatus = null,
        ?int $amountMinor = null,
        ?string $currency = null,
        ?string $notes = null,
        array $metadata = [],
    ): AffiliateCommissionAudit {
        $fromStatus = $fromStatus instanceof \BackedEnum ? $fromStatus->value : $fromStatus;
        $toStatus = $toStatus instanceof \BackedEnum ? $toStatus->value : $toStatus;

        return AffiliateCommissionAudit::query()->create([
            'affiliate_commission_id' => $commission?->id,
            'affiliate_payout_id' => $payout?->id,
            'affiliate_account_id' => $account?->id ?? $commission?->affiliate_account_id ?? $payout?->affiliate_account_id,
            'actor_id' => $actorId,
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'notes' => $notes,
            'metadata' => $metadata,
        ]);
    }
}
