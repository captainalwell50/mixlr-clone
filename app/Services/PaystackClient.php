<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PaystackClient
{
    public function enabled(): bool
    {
        return filled($this->secretKey());
    }

    public function secretKey(): string
    {
        return (string) (SiteSetting::paystackSecretKey() ?? '');
    }

    public function publicKey(): string
    {
        return (string) (SiteSetting::paystackPublicKey() ?? '');
    }

    public function currency(): string
    {
        return SiteSetting::paystackCurrency();
    }

    public function createCustomer(string $email, string $name): array
    {
        return $this->post('customer', [
            'email' => $email,
            'first_name' => $name,
        ]);
    }

    public function initializeTransaction(array $payload): array
    {
        return $this->post('transaction/initialize', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createSubscription(array $payload): array
    {
        return $this->post('subscription', $payload);
    }

    public function verifyTransaction(string $reference): array
    {
        return $this->get('transaction/verify/'.rawurlencode($reference));
    }

    public function verifyWebhookSignature(string $payload, ?string $signature): bool
    {
        $secret = $this->secretKey();
        if ($secret === '' || $signature === null || $signature === '') {
            return false;
        }

        $computed = hash_hmac('sha512', $payload, $secret);

        return hash_equals($computed, $signature);
    }

    protected function get(string $path): array
    {
        $response = $this->http()->get($this->url($path));

        if (! $response->successful() || ! $response->json('status')) {
            throw new RuntimeException($response->json('message') ?? 'Paystack request failed.');
        }

        return $response->json('data') ?? [];
    }

    protected function post(string $path, array $payload): array
    {
        $response = $this->http()->post($this->url($path), $payload);

        if (! $response->successful() || ! $response->json('status')) {
            throw new RuntimeException($response->json('message') ?? 'Paystack request failed.');
        }

        return $response->json('data') ?? [];
    }

    protected function http(): PendingRequest
    {
        return Http::withToken($this->secretKey())
            ->acceptJson()
            ->asJson()
            ->timeout(20)
            ->retry(3, 400);
    }

    protected function url(string $path): string
    {
        return rtrim((string) config('services.paystack.base_url', 'https://api.paystack.co'), '/').'/'.ltrim($path, '/');
    }
}
