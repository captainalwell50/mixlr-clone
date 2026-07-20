<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PaystackClient
{
    public function enabled(): bool
    {
        return filled(config('services.paystack.secret_key'));
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

    public function verifyTransaction(string $reference): array
    {
        return $this->get('transaction/verify/'.rawurlencode($reference));
    }

    public function verifyWebhookSignature(string $payload, ?string $signature): bool
    {
        $secret = (string) config('services.paystack.secret_key');
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
        return Http::withToken((string) config('services.paystack.secret_key'))
            ->acceptJson()
            ->asJson()
            ->timeout(30);
    }

    protected function url(string $path): string
    {
        return rtrim((string) config('services.paystack.base_url', 'https://api.paystack.co'), '/').'/'.ltrim($path, '/');
    }
}
