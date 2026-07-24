<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();

        if ($this->app->environment('production')) {
            URL::forceScheme('https');

            $secret = config('streaming.mediamtx.webhook_secret');
            if (! is_string($secret) || $secret === '') {
                Log::error('MEDIAMTX_WEBHOOK_SECRET is not set; MediaMTX webhooks will return 503.');
            }
        }
    }

    /**
     * Public listen clients poll status/scripture/gallery/presence/chat frequently.
     * Laravel's default throttle:N,1 keys guests by domain|IP only (no route), so every
     * empty-prefix throttle middleware shared one counter — a few listeners on church
     * Wi‑Fi / CGNAT could hit "Too Many Attempts" (429) on refresh.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('listen-poll', function (Request $request) {
            return Limit::perMinute(900)->by($this->listenThrottleKey($request));
        });

        RateLimiter::for('listen-open', function (Request $request) {
            return Limit::perMinute(180)->by($this->listenThrottleKey($request));
        });
    }

    protected function listenThrottleKey(Request $request): string
    {
        $identity = (string) ($request->user()?->getAuthIdentifier() ?: $request->ip());
        $route = (string) ($request->route()?->getName() ?: $request->path());

        return $identity.'|'.$route;
    }
}
