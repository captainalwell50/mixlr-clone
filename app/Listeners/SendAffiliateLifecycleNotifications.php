<?php

namespace App\Listeners;

use App\Events\AffiliateCommissionReversed;
use App\Events\AffiliatePayoutApproved;
use App\Events\AffiliatePayoutPaid;
use App\Events\AffiliateQualificationAchieved;
use App\Mail\AffiliateCommissionReversedMail;
use App\Mail\AffiliatePayoutApprovedMail;
use App\Mail\AffiliatePayoutPaidMail;
use App\Mail\AffiliateQualifiedMail;
use App\Models\AffiliateCommissionAudit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Events\Dispatcher;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendAffiliateLifecycleNotifications
{
    public function handleQualification(AffiliateQualificationAchieved $event): void
    {
        $affiliate = $event->affiliateAccount;
        $user = $affiliate->user;
        if ($user === null || ! $user->email) {
            return;
        }

        $this->dispatchIdempotent(
            mailable: new AffiliateQualifiedMail($affiliate),
            recipient: $user->email,
            eventKey: "affiliate_qualified:{$affiliate->id}",
            affiliateAccountId: $affiliate->id,
        );
    }

    public function handlePayoutApproved(AffiliatePayoutApproved $event): void
    {
        $payout = $event->payout;
        $affiliate = $payout->affiliateAccount;
        $user = $affiliate?->user;
        if ($user === null || ! $user->email) {
            return;
        }

        $this->dispatchIdempotent(
            mailable: new AffiliatePayoutApprovedMail($payout),
            recipient: $user->email,
            eventKey: "affiliate_payout_approved:{$payout->id}",
            affiliateAccountId: $affiliate->id,
            affiliatePayoutId: $payout->id,
        );
    }

    public function handlePayoutPaid(AffiliatePayoutPaid $event): void
    {
        $payout = $event->payout;
        $affiliate = $payout->affiliateAccount;
        $user = $affiliate?->user;
        if ($user === null || ! $user->email) {
            return;
        }

        $this->dispatchIdempotent(
            mailable: new AffiliatePayoutPaidMail($payout),
            recipient: $user->email,
            eventKey: "affiliate_payout_paid:{$payout->id}",
            affiliateAccountId: $affiliate->id,
            affiliatePayoutId: $payout->id,
        );
    }

    public function handleCommissionReversed(AffiliateCommissionReversed $event): void
    {
        $commission = $event->commission;
        $affiliate = $commission->affiliateAccount;
        $user = $affiliate?->user;
        if ($user === null || ! $user->email) {
            return;
        }

        $this->dispatchIdempotent(
            mailable: new AffiliateCommissionReversedMail($commission, $event->reason),
            recipient: $user->email,
            eventKey: "affiliate_comm_reversed:{$commission->id}",
            affiliateAccountId: $affiliate->id,
            affiliateCommissionId: $commission->id,
        );
    }

    protected function dispatchIdempotent(
        Mailable $mailable,
        string $recipient,
        string $eventKey,
        int $affiliateAccountId,
        ?int $affiliatePayoutId = null,
        ?int $affiliateCommissionId = null,
    ): void {
        $alreadySent = AffiliateCommissionAudit::query()
            ->where('affiliate_account_id', $affiliateAccountId)
            ->where('action', 'notification_sent')
            ->where('notes', $eventKey)
            ->exists();

        if ($alreadySent) {
            return;
        }

        try {
            if ($mailable instanceof ShouldQueue) {
                Mail::to($recipient)->queue($mailable);
            } else {
                Mail::to($recipient)->send($mailable);
            }

            AffiliateCommissionAudit::query()->create([
                'affiliate_account_id' => $affiliateAccountId,
                'affiliate_payout_id' => $affiliatePayoutId,
                'affiliate_commission_id' => $affiliateCommissionId,
                'action' => 'notification_sent',
                'notes' => $eventKey,
            ]);
        } catch (Throwable $e) {
            Log::error('Failed sending affiliate lifecycle email', [
                'event_key' => $eventKey,
                'affiliate_account_id' => $affiliateAccountId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            AffiliateQualificationAchieved::class => 'handleQualification',
            AffiliatePayoutApproved::class => 'handlePayoutApproved',
            AffiliatePayoutPaid::class => 'handlePayoutPaid',
            AffiliateCommissionReversed::class => 'handleCommissionReversed',
        ];
    }
}
