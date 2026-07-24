<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CreatorApiController;
use App\Http\Controllers\Api\ListenApiController;
use App\Http\Controllers\Api\GalleryApiController;
use App\Http\Controllers\Api\ScriptureApiController;
use App\Http\Controllers\Api\StudioLibraryApiController;
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
        ->middleware('throttle:listen-open')
        ->name('api.discover');
    Route::get('/listen/{stream}', [ListenApiController::class, 'show'])
        ->middleware('throttle:listen-open')
        ->name('api.listen.show');
    Route::get('/listen/{stream}/status', [ListenApiController::class, 'status'])
        ->middleware('throttle:listen-poll')
        ->name('api.listen.status');
    Route::post('/listen/{stream}/presence', [StreamEngageController::class, 'presence'])
        ->middleware('throttle:listen-poll')
        ->name('api.listen.presence');
    Route::get('/listen/{stream}/chat', [ChatController::class, 'index'])
        ->middleware('throttle:listen-poll')
        ->name('api.listen.chat');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        Route::get('/creator/home', [CreatorApiController::class, 'home']);
        Route::get('/streams/{stream}/publish', [CreatorApiController::class, 'publish']);
        Route::post('/streams/{stream}/go-live', [CreatorApiController::class, 'goLive']);
        Route::post('/streams/{stream}/pause', [CreatorApiController::class, 'pause']);
        Route::post('/streams/{stream}/end', [CreatorApiController::class, 'end']);

        Route::get('/streams/{stream}/desktop-mixer', [StudioLibraryApiController::class, 'mixerEmbed'])
            ->middleware('throttle:60,1');
        Route::get('/streams/{stream}/library', [StudioLibraryApiController::class, 'index'])
            ->middleware('throttle:120,1');
        Route::post('/streams/{stream}/library', [StudioLibraryApiController::class, 'store'])
            ->middleware('throttle:30,1');
        Route::get('/streams/{stream}/library/{asset}/file', [StudioLibraryApiController::class, 'file'])
            ->middleware('throttle:120,1');
        Route::delete('/streams/{stream}/library/{asset}', [StudioLibraryApiController::class, 'destroy'])
            ->middleware('throttle:30,1');

        Route::get('/streams/{stream}/gallery', [GalleryApiController::class, 'index'])
            ->middleware('throttle:listen-poll')
            ->name('api.gallery.index');
        Route::post('/streams/{stream}/gallery', [GalleryApiController::class, 'store'])
            ->middleware('throttle:30,1');
        Route::delete('/streams/{stream}/gallery/{image}', [GalleryApiController::class, 'destroy'])
            ->middleware('throttle:30,1');
        Route::post('/streams/{stream}/gallery/background', [GalleryApiController::class, 'background'])
            ->middleware('throttle:20,1');

        Route::get('/streams/{stream}/scripture', [ScriptureApiController::class, 'show'])
            ->middleware('throttle:listen-poll')
            ->name('api.scripture.show');
        Route::get('/streams/{stream}/scripture/suggest', [ScriptureApiController::class, 'suggest'])
            ->middleware('throttle:60,1');
        Route::post('/streams/{stream}/scripture', [ScriptureApiController::class, 'store'])
            ->middleware('throttle:60,1');
        Route::delete('/streams/{stream}/scripture', [ScriptureApiController::class, 'destroy'])
            ->middleware('throttle:60,1');

        Route::post('/listen/{stream}/like', [StreamEngageController::class, 'like'])
            ->middleware('throttle:60,1');
        Route::post('/listen/{stream}/chat', [ChatController::class, 'store'])
            ->middleware('throttle:30,1');
    });
});
