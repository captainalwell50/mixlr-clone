<?php

use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\EventController as AdminEventController;
use App\Http\Controllers\Admin\OrganizationController;
use App\Http\Controllers\Admin\OrganizationMemberController;
use App\Http\Controllers\Admin\RecordingDownloadController;
use App\Http\Controllers\Admin\StreamController;
use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\ChannelController;
use App\Http\Controllers\ChannelFollowController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiscoverController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventEngageController;
use App\Http\Controllers\ListenController;
use App\Http\Controllers\RecordingPlayController;
use App\Http\Controllers\StudioController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/discover', [DiscoverController::class, 'index'])->name('discover');

Route::get('/c/{organization}', [ChannelController::class, 'show'])->name('channels.show');
Route::post('/c/{organization}/follow', [ChannelFollowController::class, 'store'])
    ->middleware('auth')
    ->name('channels.follow');
Route::delete('/c/{organization}/follow', [ChannelFollowController::class, 'destroy'])
    ->middleware('auth')
    ->name('channels.unfollow');

Route::get('/e/{event}', [EventController::class, 'show'])->name('events.show');
Route::post('/e/{event}/unlock', [EventController::class, 'unlock'])->name('events.unlock');
Route::get('/embed/e/{event}', [EventController::class, 'embed'])->name('events.embed');

Route::post('/e/{event}/presence', [EventEngageController::class, 'presence'])
    ->middleware('throttle:120,1')
    ->name('events.presence');
Route::post('/e/{event}/heart', [EventEngageController::class, 'heart'])
    ->middleware(['auth', 'throttle:60,1'])
    ->name('events.heart');

Route::get('/e/{event}/chat', [ChatController::class, 'indexForEvent'])
    ->middleware('throttle:120,1')
    ->name('events.chat.index');
Route::post('/e/{event}/chat', [ChatController::class, 'storeForEvent'])
    ->middleware(['auth', 'throttle:30,1'])
    ->name('events.chat.store');

Route::get('/archive', [ArchiveController::class, 'index'])->name('archive.index');
Route::get('/archive/{recording}/play', [RecordingPlayController::class, 'show'])->name('archive.play');
Route::get('/archive/{recording}/file', [RecordingPlayController::class, 'file'])->name('archive.file');

Route::get('/listen/{stream}', [ListenController::class, 'show'])->name('listen.stream');
Route::get('/embed/{stream}', [ListenController::class, 'embed'])->name('embed.stream');

Route::get('/listen/{stream}/chat', [ChatController::class, 'index'])
    ->middleware('throttle:120,1')
    ->name('chat.index');
Route::post('/listen/{stream}/chat', [ChatController::class, 'store'])
    ->middleware(['auth', 'throttle:30,1'])
    ->name('chat.store');

Route::get('/studio/{stream}', [StudioController::class, 'show'])
    ->middleware('signed')
    ->name('studio.stream');

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
});

Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('organizations', [OrganizationController::class, 'index'])->name('organizations.index');
    Route::get('organizations/create', [OrganizationController::class, 'create'])->name('organizations.create');
    Route::post('organizations', [OrganizationController::class, 'store'])->name('organizations.store');
    Route::get('organizations/{organization}/edit', [OrganizationController::class, 'edit'])->name('organizations.edit');
    Route::put('organizations/{organization}', [OrganizationController::class, 'update'])->name('organizations.update');

    Route::get('organizations/{organization}/members', [OrganizationMemberController::class, 'index'])->name('organizations.members');
    Route::post('organizations/{organization}/members', [OrganizationMemberController::class, 'store'])->name('organizations.members.store');
    Route::put('organizations/{organization}/members/{user}', [OrganizationMemberController::class, 'update'])->name('organizations.members.update');
    Route::delete('organizations/{organization}/members/{user}', [OrganizationMemberController::class, 'destroy'])->name('organizations.members.destroy');

    Route::middleware('stream.manage')->group(function (): void {
        Route::get('events', [AdminEventController::class, 'index'])->name('events.index');
        Route::get('events/create', [AdminEventController::class, 'create'])->name('events.create');
        Route::post('events', [AdminEventController::class, 'store'])->name('events.store');
        Route::get('events/{event}/edit', [AdminEventController::class, 'edit'])->name('events.edit');
        Route::put('events/{event}', [AdminEventController::class, 'update'])->name('events.update');
        Route::delete('events/{event}', [AdminEventController::class, 'destroy'])->name('events.destroy');
        Route::post('events/{event}/go-live', [AdminEventController::class, 'goLive'])->name('events.go-live');
        Route::post('events/{event}/end', [AdminEventController::class, 'end'])->name('events.end');

        Route::get('analytics', [AnalyticsController::class, 'index'])->name('analytics.index');

        Route::get('streams', [StreamController::class, 'index'])->name('streams.index');
        Route::get('streams/create', [StreamController::class, 'create'])->name('streams.create');
        Route::post('streams', [StreamController::class, 'store'])->name('streams.store');
        Route::get('streams/{stream}/edit', [StreamController::class, 'edit'])->name('streams.edit');
        Route::put('streams/{stream}', [StreamController::class, 'update'])->name('streams.update');
        Route::delete('streams/{stream}', [StreamController::class, 'destroy'])->name('streams.destroy');
        Route::post('streams/{stream}/regenerate-key', [StreamController::class, 'regenerateKey'])->name('streams.regenerate-key');
        Route::get('streams/{stream}/studio', [StudioController::class, 'show'])->name('streams.studio');
        Route::get('recordings/{recording}/download', [RecordingDownloadController::class, 'show'])->name('recordings.download');
    });
});

require __DIR__.'/auth.php';
