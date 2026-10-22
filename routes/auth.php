<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisteredUserController;
use Illuminate\Support\Facades\Route;

Route::get('auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google');
Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');

Route::middleware('guest')->group(function (): void {
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:5,1');

    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [PasswordResetController::class, 'create'])
        ->name('password.request');
    Route::post('forgot-password', [PasswordResetController::class, 'store'])
        ->name('password.email')
        ->middleware('throttle:5,1');

    Route::get('reset-password/{token}', [PasswordResetController::class, 'edit'])
        ->name('password.reset');
    Route::post('reset-password', [PasswordResetController::class, 'update'])
        ->name('password.store')
        ->middleware('throttle:5,1');
});

Route::middleware('auth')->group(function (): void {
    Route::get('verify-email', [EmailVerificationController::class, 'notice'])
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
