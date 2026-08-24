<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\ChannelCustomiseController;
use App\Http\Controllers\Admin\EventController as AdminEventController;
use App\Http\Controllers\Admin\OrganizationController;
use App\Http\Controllers\Admin\OrganizationMemberController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\RecordingDestroyController;
use App\Http\Controllers\Admin\RecordingDownloadController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\StreamController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\CaddyOnDemandController;
use App\Http\Controllers\ChannelController;
use App\Http\Controllers\ChannelFollowController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\CreatorHomeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiscoverController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventEngageController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\GoogleDriveController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\ListenController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PaystackWebhookController;
use App\Http\Controllers\RecordingController;
use App\Http\Controllers\RecordingPlayController;
use App\Http\Controllers\ScriptureController;
use App\Http\Controllers\SongController;
use App\Http\Controllers\StreamEngageController;
use App\Http\Controllers\StudioAudioLibraryController;
use App\Http\Controllers\StudioController;
use App\Http\Controllers\StudioDesktopMixerController;
use App\Http\Controllers\StudioSessionController;
use App\Models\Organization;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/how-it-works', function () {
    return view('how-it-works');
})->name('how-it-works');

Route::get('/downloads', function () {
    return view('downloads', [
        'platforms' => config('downloads'),
    ]);
})->name('downloads');

// Unlisted store-listing kit (URL-only; not in public nav/footer).
Route::get('/listing', function () {
    $title = 'Sound Mix Live';
    $shortDescription = 'Live audio for creators and listeners — Studio mic publish + listen.';
    $fullDescription = <<<'TXT'
Sound Mix Live brings creators and listeners together over live audio — not another muted video player.

• Discover live channels and keep listening with background audio
• Creators go live from Studio with mic publish
• Share one link for events, chat, and hearts on the web
• Gallery and scripture tools for gatherings (where enabled)

Privacy: https://soundmix.live/privacy
Terms: https://soundmix.live/terms
Support: https://soundmix.live/support
TXT;

    $base = asset('listing/play-store');
    $assets = [
        [
            'label' => 'App icon',
            'filename' => 'icon-512.png',
            'url' => $base.'/icon-512.png',
            'width' => 512,
            'height' => 512,
            'note' => 'Play Store high-res icon',
        ],
        [
            'label' => 'Feature graphic',
            'filename' => 'feature-graphic-1024x500.png',
            'url' => $base.'/feature-graphic-1024x500.png',
            'width' => 1024,
            'height' => 500,
            'note' => 'Play Console feature banner',
        ],
        [
            'label' => 'Screenshot 1 — Welcome (“Broadcast. Listen close.”)',
            'filename' => 'screenshot-1.png',
            'url' => $base.'/screenshot-1.png',
            'width' => 1080,
            'height' => 1920,
            'note' => 'Phone welcome — matches app Get started screen (1080×1920)',
            'tall' => true,
        ],
        [
            'label' => 'Screenshot 2 — Discover',
            'filename' => 'screenshot-2.png',
            'url' => $base.'/screenshot-2.png',
            'width' => 1080,
            'height' => 1920,
            'note' => 'Phone (1080×1920)',
            'tall' => true,
        ],
        [
            'label' => 'Screenshot 3 — Listen live',
            'filename' => 'screenshot-3.png',
            'url' => $base.'/screenshot-3.png',
            'width' => 1080,
            'height' => 1920,
            'note' => 'Phone (1080×1920)',
            'tall' => true,
        ],
        [
            'label' => 'Screenshot 4 — Studio',
            'filename' => 'screenshot-4.png',
            'url' => $base.'/screenshot-4.png',
            'width' => 1080,
            'height' => 1920,
            'note' => 'Phone (1080×1920)',
            'tall' => true,
        ],
        [
            'label' => 'Screenshot — Scripture board',
            'filename' => 'screenshot-scripture.png',
            'url' => $base.'/screenshot-scripture.png',
            'width' => 1080,
            'height' => 1920,
            'note' => 'Phone (1080×1920)',
            'tall' => true,
        ],
        [
            'label' => 'Screenshot — Live Gallery',
            'filename' => 'screenshot-gallery.png',
            'url' => $base.'/screenshot-gallery.png',
            'width' => 1080,
            'height' => 1920,
            'note' => 'Phone (1080×1920)',
            'tall' => true,
        ],
    ];

    $apk = config('downloads.android_apk');

    return view('listing', [
        'title' => $title,
        'shortDescription' => $shortDescription,
        'fullDescription' => trim($fullDescription),
        'assets' => $assets,
        'appVersion' => '1.2.17',
        'supportEmail' => config('app.support_email', 'support@soundmix.live'),
        'apkUrl' => ($apk['available'] ?? false) ? ($apk['url'] ?? null) : null,
    ]);
})->name('listing');

Route::get('/privacy', [LegalController::class, 'privacy'])->name('legal.privacy');
Route::get('/terms', [LegalController::class, 'terms'])->name('legal.terms');
Route::get('/support', [LegalController::class, 'support'])->name('legal.support');

Route::get('/discover', [DiscoverController::class, 'index'])->name('discover');

// Caddy on-demand TLS ask (localhost only in practice).
Route::get('/internal/caddy-ask', CaddyOnDemandController::class)
    ->middleware('throttle:120,1')
    ->name('internal.caddy-ask');

if (config('app.channel_subdomains') && filled(config('app.channel_domain'))) {
    Route::domain('{organization}.'.config('app.channel_domain'))
        ->middleware(\App\Http\Middleware\EnsureChannelSubdomain::class)
        ->group(function (): void {
            Route::get('/', [ChannelController::class, 'show'])->name('channels.show');
            Route::post('/follow', [ChannelFollowController::class, 'store'])
                ->middleware('auth')
                ->name('channels.follow');
            Route::delete('/follow', [ChannelFollowController::class, 'destroy'])
                ->middleware('auth')
                ->name('channels.unfollow');
        });

    // Legacy Mixlr-clone path → subdomain (e.g. /c/alwell → https://alwell.soundmix.live)
    Route::get('/c/{organization}', function (Organization $organization) {
        return redirect()->away($organization->channelUrl(), 301);
    })->name('channels.show.path');
} else {
    Route::get('/c/{organization}', [ChannelController::class, 'show'])->name('channels.show');
    Route::post('/c/{organization}/follow', [ChannelFollowController::class, 'store'])
        ->middleware('auth')
        ->name('channels.follow');
    Route::delete('/c/{organization}/follow', [ChannelFollowController::class, 'destroy'])
        ->middleware('auth')
        ->name('channels.unfollow');
}

Route::get('/e/{event}', [EventController::class, 'show'])->name('events.show');
Route::get('/e/{event}/status', [EventController::class, 'status'])
    ->middleware('throttle:listen-poll')
    ->name('events.status');
Route::post('/e/{event}/unlock', [EventController::class, 'unlock'])->name('events.unlock');
Route::get('/embed/e/{event}', [EventController::class, 'embed'])->name('events.embed');

Route::post('/e/{event}/presence', [EventEngageController::class, 'presence'])
    ->middleware('throttle:listen-poll')
    ->name('events.presence');
Route::post('/e/{event}/heart', [EventEngageController::class, 'heart'])
    ->middleware(['auth', 'throttle:60,1'])
    ->name('events.heart');

Route::get('/e/{event}/chat', [ChatController::class, 'indexForEvent'])
    ->middleware('throttle:listen-poll')
    ->name('events.chat.index');
Route::post('/e/{event}/chat', [ChatController::class, 'storeForEvent'])
    ->middleware(['auth', 'throttle:30,1'])
    ->name('events.chat.store');

Route::get('/archive', [ArchiveController::class, 'index'])->name('archive.index');
Route::get('/archive/c/{organization}', [ArchiveController::class, 'channel'])->name('archive.channel');
Route::get('/archive/{recording}/play', [RecordingPlayController::class, 'show'])->name('archive.play');
Route::get('/archive/{recording}/file', [RecordingPlayController::class, 'file'])->name('archive.file');

Route::get('/listen/{stream}', [ListenController::class, 'show'])->name('listen.stream');
Route::get('/listen/{stream}/status', [ListenController::class, 'status'])
    ->middleware('throttle:listen-poll')
    ->name('listen.status');
Route::get('/embed/{stream}', [ListenController::class, 'embed'])->name('embed.stream');

Route::post('/listen/{stream}/presence', [StreamEngageController::class, 'presence'])
    ->middleware('throttle:listen-poll')
    ->name('listen.presence');
Route::post('/listen/{stream}/like', [StreamEngageController::class, 'like'])
    ->middleware(['auth', 'throttle:60,1'])
    ->name('listen.like');

Route::get('/listen/{stream}/gallery', [GalleryController::class, 'index'])
    ->middleware('throttle:listen-poll')
    ->name('gallery.index');
Route::get('/listen/{stream}/scripture', [ScriptureController::class, 'show'])
    ->middleware('throttle:listen-poll')
    ->name('scripture.show');
Route::get('/listen/{stream}/song', [SongController::class, 'show'])
    ->middleware('throttle:listen-poll')
    ->name('song.show');
Route::post('/listen/{stream}/gallery', [GalleryController::class, 'store'])
    ->middleware('throttle:30,1')
    ->name('gallery.store');
Route::post('/listen/{stream}/background', [GalleryController::class, 'storeBackground'])
    ->middleware('throttle:20,1')
    ->name('gallery.background');
Route::delete('/listen/{stream}/gallery/{image}', [GalleryController::class, 'destroy'])
    ->middleware(['auth', 'throttle:30,1'])
    ->name('gallery.destroy');

Route::get('/listen/{stream}/chat', [ChatController::class, 'index'])
    ->middleware('throttle:listen-poll')
    ->name('chat.index');
Route::post('/listen/{stream}/chat', [ChatController::class, 'store'])
    ->middleware(['auth', 'throttle:30,1'])
    ->name('chat.store');

Route::post('/listen/{stream}/recordings', [RecordingController::class, 'store'])
    ->middleware('throttle:20,1')
    ->name('recordings.store');
Route::patch('/listen/{stream}/recordings/{recording}', [RecordingController::class, 'update'])
    ->middleware('throttle:30,1')
    ->name('recordings.update');
Route::delete('/listen/{stream}/recordings/{recording}', [RecordingController::class, 'destroy'])
    ->middleware('throttle:30,1')
    ->name('recordings.destroy');

Route::get('/studio/{stream}', [StudioController::class, 'show'])
    ->middleware('signed')
    ->name('studio.stream');

Route::get('/studio/{stream}/session', [StudioSessionController::class, 'show'])
    ->middleware('throttle:120,1')
    ->name('studio.session.show');
Route::post('/studio/{stream}/session/events', [StudioSessionController::class, 'createEvent'])
    ->middleware('throttle:30,1')
    ->name('studio.session.create-event');
Route::post('/studio/{stream}/session/go-live', [StudioSessionController::class, 'goLive'])
    ->middleware('throttle:30,1')
    ->name('studio.session.go-live');
Route::patch('/studio/{stream}/session/event', [StudioSessionController::class, 'renameEvent'])
    ->middleware('throttle:30,1')
    ->name('studio.session.rename-event');
Route::post('/studio/{stream}/session/pause', [StudioSessionController::class, 'pause'])
    ->middleware('throttle:30,1')
    ->name('studio.session.pause');
Route::post('/studio/{stream}/session/resume', [StudioSessionController::class, 'resume'])
    ->middleware('throttle:30,1')
    ->name('studio.session.resume');
Route::post('/studio/{stream}/session/end', [StudioSessionController::class, 'end'])
    ->middleware('throttle:30,1')
    ->name('studio.session.end');

Route::get('/studio/{stream}/scripture', [ScriptureController::class, 'show'])
    ->middleware('throttle:listen-poll')
    ->name('studio.scripture.show');
Route::get('/studio/{stream}/scripture/suggest', [ScriptureController::class, 'suggest'])
    ->middleware('throttle:60,1')
    ->name('studio.scripture.suggest');
Route::post('/studio/{stream}/scripture', [ScriptureController::class, 'store'])
    ->middleware('throttle:60,1')
    ->name('studio.scripture.store');
Route::delete('/studio/{stream}/scripture', [ScriptureController::class, 'destroy'])
    ->middleware('throttle:60,1')
    ->name('studio.scripture.destroy');

Route::get('/studio/{stream}/songs', [SongController::class, 'index'])
    ->middleware('throttle:120,1')
    ->name('studio.songs.index');
Route::post('/studio/{stream}/songs', [SongController::class, 'store'])
    ->middleware('throttle:60,1')
    ->name('studio.songs.store');
Route::get('/studio/{stream}/songs/cue', [SongController::class, 'show'])
    ->middleware('throttle:listen-poll')
    ->name('studio.songs.show');
Route::post('/studio/{stream}/songs/cue/next', [SongController::class, 'next'])
    ->middleware('throttle:60,1')
    ->name('studio.songs.next');
Route::post('/studio/{stream}/songs/cue/previous', [SongController::class, 'previous'])
    ->middleware('throttle:60,1')
    ->name('studio.songs.previous');
Route::delete('/studio/{stream}/songs/cue', [SongController::class, 'clear'])
    ->middleware('throttle:60,1')
    ->name('studio.songs.clear');
Route::put('/studio/{stream}/songs/{song}', [SongController::class, 'update'])
    ->middleware('throttle:60,1')
    ->name('studio.songs.update');
Route::delete('/studio/{stream}/songs/{song}', [SongController::class, 'destroy'])
    ->middleware('throttle:60,1')
    ->name('studio.songs.destroy');
Route::post('/studio/{stream}/songs/{song}/cue', [SongController::class, 'cue'])
    ->middleware('throttle:60,1')
    ->name('studio.songs.cue');

Route::get('/studio/{stream}/desktop-mixer', [StudioDesktopMixerController::class, 'show'])
    ->middleware('throttle:60,1')
    ->name('studio.desktop-mixer');

Route::get('/studio/{stream}/library', [StudioAudioLibraryController::class, 'index'])
    ->middleware('throttle:120,1')
    ->name('studio.library.index');
Route::post('/studio/{stream}/library', [StudioAudioLibraryController::class, 'store'])
    ->middleware('throttle:30,1')
    ->name('studio.library.store');
Route::post('/studio/{stream}/library/import-drive', [StudioAudioLibraryController::class, 'importDrive'])
    ->middleware('throttle:30,1')
    ->name('studio.library.import-drive');
Route::get('/studio/{stream}/library/{asset}/file', [StudioAudioLibraryController::class, 'file'])
    ->middleware('throttle:120,1')
    ->name('studio.library.file');
Route::delete('/studio/{stream}/library/{asset}', [StudioAudioLibraryController::class, 'destroy'])
    ->middleware('throttle:30,1')
    ->name('studio.library.destroy');
Route::delete('/studio/{stream}/gallery', [GalleryController::class, 'destroySelected'])
    ->middleware('throttle:30,1')
    ->name('studio.gallery.destroy');

Route::post('/webhooks/paystack', PaystackWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('webhooks.paystack');

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/account', [AccountController::class, 'edit'])->name('account.edit');
    Route::put('/account', [AccountController::class, 'update'])->name('account.update');
    Route::delete('/account', [AccountController::class, 'destroy'])
        ->middleware('throttle:5,1')
        ->name('account.destroy');

    Route::get('/onboarding', [OnboardingController::class, 'show'])->name('onboarding.show');
    Route::post('/onboarding/type', [OnboardingController::class, 'storeType'])->name('onboarding.type');
    Route::get('/onboarding/channel', [OnboardingController::class, 'channel'])->name('onboarding.channel');
    Route::post('/onboarding/channel', [OnboardingController::class, 'storeChannel'])->name('onboarding.channel.store');

    Route::middleware('onboarded')->group(function (): void {
        Route::get('/home', CreatorHomeController::class)->name('creator.home');
        Route::get('/billing', [BillingController::class, 'plans'])->name('billing.plans');
        Route::post('/billing/trial', [BillingController::class, 'startTrial'])->name('billing.trial');
        Route::post('/billing/checkout/{plan}', [BillingController::class, 'checkout'])->name('billing.checkout');
        Route::get('/billing/callback', [BillingController::class, 'callback'])->name('billing.callback');

        Route::get('/integrations/google-drive/callback', [GoogleDriveController::class, 'callback'])
            ->name('integrations.google-drive.callback');
        Route::get('/integrations/google-drive/{organization}/connect', [GoogleDriveController::class, 'redirect'])
            ->name('integrations.google-drive.redirect');
        Route::get('/integrations/google-drive/{organization}/status', [GoogleDriveController::class, 'status'])
            ->name('integrations.google-drive.status');
        Route::delete('/integrations/google-drive/{organization}', [GoogleDriveController::class, 'disconnect'])
            ->name('integrations.google-drive.disconnect');
        Route::get('/integrations/google-drive/{organization}/files', [GoogleDriveController::class, 'files'])
            ->name('integrations.google-drive.files');
    });
});

Route::middleware(['auth', 'onboarded'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');

    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::get('users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('users', [UserController::class, 'store'])->name('users.store');
    Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

    Route::get('plans', [PlanController::class, 'index'])->name('plans.index');
    Route::get('plans/create', [PlanController::class, 'create'])->name('plans.create');
    Route::post('plans', [PlanController::class, 'store'])->name('plans.store');
    Route::get('plans/{plan}/edit', [PlanController::class, 'edit'])->name('plans.edit');
    Route::put('plans/{plan}', [PlanController::class, 'update'])->name('plans.update');
    Route::delete('plans/{plan}', [PlanController::class, 'destroy'])->name('plans.destroy');

    Route::get('organizations', [OrganizationController::class, 'index'])->name('organizations.index');
    Route::get('organizations/create', [OrganizationController::class, 'create'])->name('organizations.create');
    Route::post('organizations', [OrganizationController::class, 'store'])->name('organizations.store');
    Route::get('organizations/{organization}/edit', [OrganizationController::class, 'edit'])->name('organizations.edit');
    Route::put('organizations/{organization}', [OrganizationController::class, 'update'])->name('organizations.update');

    Route::get('organizations/{organization}/customise', [ChannelCustomiseController::class, 'edit'])->name('organizations.customise');
    Route::post('organizations/{organization}/customise/logo', [ChannelCustomiseController::class, 'updateLogo'])->name('organizations.customise.logo');
    Route::delete('organizations/{organization}/customise/logo', [ChannelCustomiseController::class, 'destroyLogo'])->name('organizations.customise.logo.destroy');
    Route::post('organizations/{organization}/customise/artwork', [ChannelCustomiseController::class, 'updateArtwork'])->name('organizations.customise.artwork');
    Route::delete('organizations/{organization}/customise/artwork', [ChannelCustomiseController::class, 'destroyArtwork'])->name('organizations.customise.artwork.destroy');
    Route::post('organizations/{organization}/customise/background', [ChannelCustomiseController::class, 'updateBackground'])->name('organizations.customise.background');
    Route::delete('organizations/{organization}/customise/background', [ChannelCustomiseController::class, 'destroyBackground'])->name('organizations.customise.background.destroy');
    Route::put('organizations/{organization}/customise/giving', [ChannelCustomiseController::class, 'updateGiving'])->name('organizations.customise.giving');

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
        Route::post('events/{event}/go-live', [AdminEventController::class, 'goLive'])
            ->middleware('subscribed')
            ->name('events.go-live');
        Route::post('events/{event}/end', [AdminEventController::class, 'end'])->name('events.end');

        Route::get('analytics', [AnalyticsController::class, 'index'])->name('analytics.index');

        Route::get('streams', [StreamController::class, 'index'])->name('streams.index');
        Route::get('streams/create', [StreamController::class, 'create'])->name('streams.create');
        Route::post('streams', [StreamController::class, 'store'])
            ->middleware('subscribed')
            ->name('streams.store');
        Route::get('streams/{stream}/edit', [StreamController::class, 'edit'])->name('streams.edit');
        Route::put('streams/{stream}', [StreamController::class, 'update'])->name('streams.update');
        Route::delete('streams/{stream}', [StreamController::class, 'destroy'])->name('streams.destroy');
        Route::post('streams/{stream}/regenerate-key', [StreamController::class, 'regenerateKey'])->name('streams.regenerate-key');
        Route::get('streams/{stream}/studio', [StudioController::class, 'show'])->name('streams.studio');
        Route::get('recordings/{recording}/download', [RecordingDownloadController::class, 'show'])->name('recordings.download');
        Route::delete('recordings/{recording}', RecordingDestroyController::class)->name('recordings.destroy');
    });
});

require __DIR__.'/auth.php';
