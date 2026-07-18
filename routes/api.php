<?php

use App\Http\Controllers\Webhooks\MediaMtxAuthController;
use App\Http\Controllers\Webhooks\MediaMtxWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/mediamtx', MediaMtxWebhookController::class)
    ->middleware('throttle:240,1');

Route::post('/mediamtx/auth', MediaMtxAuthController::class)
    ->middleware('throttle:600,1');
