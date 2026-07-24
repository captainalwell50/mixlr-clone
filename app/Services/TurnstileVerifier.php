<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TurnstileVerifier
{
    public static function enabled(): bool
    {
        return filled(config('registration.turnstile.site_key'))
            && filled(config('registration.turnstile.secret_key'));
    }

    public function verify(?string $token, ?string $remoteIp = null): bool
    {
        if (! self::enabled()) {
            return true;
        }

        if (! filled($token)) {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(5)
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', array_filter([
                    'secret' => config('registration.turnstile.secret_key'),
                    'response' => $token,
                    'remoteip' => $remoteIp,
                ]));

            if (! $response->successful()) {
                Log::warning('Turnstile siteverify HTTP failure', [
                    'status' => $response->status(),
                ]);

                return false;
            }

            return (bool) $response->json('success');
        } catch (\Throwable $e) {
            Log::warning('Turnstile siteverify exception', [
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
