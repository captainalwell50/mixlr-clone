<?php

namespace App\Services;

use App\Models\DigitalLibraryCheckout;
use App\Models\DigitalLibraryEntitlement;
use App\Models\DigitalLibraryPurchase;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * One Paystack charge creates at most one purchase.
 * The fee rate is snapshotted for a later earnings phase. No earning is created here.
 */
final class DigitalLibraryPurchaseService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function confirmFromPaystackData(array $data): bool
    {
        $reference = (string) ($data['reference'] ?? '');
        $checkout = $this->checkoutFor($data, $reference);
        if ($checkout === null || $reference === '') {
            return false;
        }

        try {
            return DB::transaction(function () use ($checkout, $data, $reference): bool {
                $locked = DigitalLibraryCheckout::query()->whereKey($checkout->id)->lockForUpdate()->first();
                if ($locked === null) {
                    return false;
                }

                $existing = DigitalLibraryPurchase::query()
                    ->where('provider', 'paystack')
                    ->where('provider_reference', $reference)
                    ->first();
                if ($existing !== null) {
                    if ($locked->status !== DigitalLibraryCheckout::STATUS_PAID) {
                        $locked->status = DigitalLibraryCheckout::STATUS_PAID;
                        $locked->save();
                    }

                    return true;
                }

                $purpose = data_get($data, 'metadata.purpose');
                if ($locked->provider_reference !== null && $reference !== $locked->provider_reference) {
                    $this->flag($locked, 'Paystack reference did not match the checkout.');

                    return false;
                }
                if ($purpose !== null && $purpose !== 'digital_library') {
                    $this->flag($locked, 'Paystack purpose was not digital_library.');

                    return false;
                }
                $checkoutId = data_get($data, 'metadata.checkout_id');
                if ($checkoutId !== null && (string) $checkoutId !== (string) $locked->public_id) {
                    $this->flag($locked, 'Paystack checkout id did not match.');

                    return false;
                }
                if (strtolower((string) ($data['status'] ?? '')) !== 'success') {
                    $locked->status = DigitalLibraryCheckout::STATUS_FAILED;
                    $locked->save();

                    return false;
                }

                $amount = (int) ($data['amount'] ?? -1);
                $currency = strtoupper((string) ($data['currency'] ?? ''));
                if ($amount !== (int) $locked->expected_amount_minor || $currency !== strtoupper((string) $locked->currency)) {
                    $this->flag($locked, 'Collected amount or currency did not match the checkout.');

                    return false;
                }

                $purchase = DigitalLibraryPurchase::query()->create([
                    'user_id' => $locked->user_id,
                    'series_id' => $locked->series_id,
                    'seller_id' => $locked->seller_id,
                    'checkout_id' => $locked->id,
                    'provider' => 'paystack',
                    'provider_reference' => $reference,
                    'listed_price_minor' => $locked->listed_price_minor,
                    'amount_collected_minor' => $amount,
                    'currency' => $currency,
                    'platform_fee_bps' => (int) DigitalLibrarySettings::all()['platform_fee_bps'],
                    'status' => DigitalLibraryPurchase::STATUS_PAID,
                    'paid_at' => now(),
                    'refunded_amount_minor' => 0,
                ]);
                $this->grant($purchase);
                $locked->status = DigitalLibraryCheckout::STATUS_PAID;
                $locked->review_reason = null;
                $locked->save();

                $this->dispatchPurchaseReceipt($purchase);

                return true;
            });
        } catch (QueryException $exception) {
            Log::info('Digital Library purchase already recorded', ['reference' => $reference]);

            return DigitalLibraryPurchase::query()
                ->where('provider', 'paystack')
                ->where('provider_reference', $reference)
                ->exists();
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function checkoutFor(array $data, string $reference): ?DigitalLibraryCheckout
    {
        if ($reference !== '') {
            $byReference = DigitalLibraryCheckout::query()->where('provider_reference', $reference)->first();
            if ($byReference !== null) {
                return $byReference;
            }
        }
        $publicId = data_get($data, 'metadata.checkout_id');
        if (! is_string($publicId) || $publicId === '') {
            return null;
        }

        return DigitalLibraryCheckout::query()->where('public_id', $publicId)->first();
    }

    private function grant(DigitalLibraryPurchase $purchase): void
    {
        $entitlement = DigitalLibraryEntitlement::query()->firstOrNew([
            'user_id' => $purchase->user_id,
            'series_id' => $purchase->series_id,
        ]);
        $entitlement->purchase_id = $purchase->id;
        $entitlement->status = DigitalLibraryEntitlement::STATUS_ACTIVE;
        $entitlement->granted_at = now();
        $entitlement->revoked_at = null;
        $entitlement->revoke_reason = null;
        $entitlement->save();
    }

    private function flag(DigitalLibraryCheckout $checkout, string $reason): void
    {
        $checkout->status = DigitalLibraryCheckout::STATUS_REVIEW;
        $checkout->review_reason = $reason;
        $checkout->save();
    }

    public function dispatchPurchaseReceipt(DigitalLibraryPurchase $purchase): void
    {
        try {
            $purchase->loadMissing(['user', 'series']);
            $user = $purchase->user;
            if ($user === null || empty($user->email)) {
                return;
            }

            $eventKey = "digital-library-receipt:{$purchase->provider_reference}:{$user->id}";
            app(OutboundEmailService::class)->sendTransactional(
                mailable: \App\Mail\DigitalLibraryPurchaseReceiptMail::fromPurchase($purchase),
                recipient: $user->email,
                type: 'digital_library_receipt',
                eventKey: $eventKey,
                user: $user,
                metadata: [
                    'purchase_id' => $purchase->id,
                    'series_id' => $purchase->series_id,
                    'reference' => $purchase->provider_reference,
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('Digital library purchase receipt email failed', [
                'purchase_id' => $purchase->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
