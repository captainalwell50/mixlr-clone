<?php

namespace App\Providers;

use Illuminate\Support\Facades\Log;
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
        if ($this->app->environment('production')) {
            URL::forceScheme('https');

            $secret = config('streaming.mediamtx.webhook_secret');
            if (! is_string($secret) || $secret === '') {
                Log::error('MEDIAMTX_WEBHOOK_SECRET is not set; MediaMTX webhooks will return 503.');
            }
        }
    }
}
