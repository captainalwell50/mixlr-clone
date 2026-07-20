<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'stream.manage' => \App\Http\Middleware\EnsureCanManageStreams::class,
            'onboarded' => \App\Http\Middleware\EnsureOnboarded::class,
            'subscribed' => \App\Http\Middleware\EnsureSubscribed::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'webhooks/paystack',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
