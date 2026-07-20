<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CreatorApiController;
use App\Http\Controllers\Api\ListenApiController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\StreamEngageController;
use App\Http\Controllers\Webhooks\MediaMtxAuthController;
use App\Http\Controllers\Webhooks\MediaMtxWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/mediamtx', MediaMtxWebhookController::class)
    ->middleware('throttle:240,1');

Route::post('/mediamtx/auth', MediaMtxAuthController::class)
    ->middleware('throttle:600,1');

Route::prefix('v1')->group(function (): void {
    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:20,1');

    Route::get('/discover', [ListenApiController::class, 'discover'])
        ->middleware('throttle:60,1');
    Route::get('/listen/{stream}', [ListenApiController::class, 'show'])
        ->middleware('throttle:120,1');
    Route::get('/listen/{stream}/status', [ListenApiController::class, 'status'])
        ->middleware('throttle:120,1');
    Route::post('/listen/{stream}/presence', [StreamEngageController::class, 'presence'])
        ->middleware('throttle:120,1');
    Route::get('/listen/{stream}/chat', [ChatController::class, 'index'])
        ->middleware('throttle:120,1');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        Route::get('/creator/home', [CreatorApiController::class, 'home']);
        Route::get('/streams/{stream}/publish', [CreatorApiController::class, 'publish']);
        Route::post('/streams/{stream}/go-live', [CreatorApiController::class, 'goLive']);
        Route::post('/streams/{stream}/end', [CreatorApiController::class, 'end']);

        Route::post('/listen/{stream}/like', [StreamEngageController::class, 'like'])
            ->middleware('throttle:60,1');
        Route::post('/listen/{stream}/chat', [ChatController::class, 'store'])
            ->middleware('throttle:30,1');
    });
});
