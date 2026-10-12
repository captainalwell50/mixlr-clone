<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\AffiliateController;
use App\Http\Controllers\Admin\AffiliateProgrammeController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DigitalLibraryCategoryController;
use App\Http\Controllers\Admin\DigitalLibraryController;
use App\Http\Controllers\Admin\DigitalLibraryPurchaseController;
use App\Http\Controllers\Admin\DigitalLibrarySellerController;
use App\Http\Controllers\Admin\DigitalLibrarySeriesController;
use App\Http\Controllers\Admin\DigitalLibraryTeacherController;
use App\Http\Controllers\DigitalLibrary\DiscoverController as LibraryDiscoverController;
use App\Http\Controllers\DigitalLibrary\LibraryController;
use App\Http\Controllers\DigitalLibrary\PlaybackController;
use App\Http\Controllers\DigitalLibrary\SellController;
use App\Http\Controllers\DigitalLibrary\SeriesController as PublicSeriesController;
use App\Http\Controllers\Admin\AffiliateCommissionController;
use App\Http\Controllers\Admin\AffiliatePayoutController;
use App\Http\Controllers\Admin\AffiliateProspectController;
use App\Http\Controllers\Affiliate\CommissionController as AffiliateCommissionStatementController;
use App\Http\Controllers\Affiliate\ProspectController;
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
use App\Http\Controllers\ArchiveManageController;
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
use App\Http\Controllers\NdiController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PaystackWebhookController;
use App\Http\Controllers\RecordingController;
use App\Http\Controllers\RecordingPlayController;
use App\Http\Controllers\ReferralController;
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

Route::get('/r/{code}', [ReferralController::class, 'show'])
    ->where('code', '[A-Za-z0-9]{4,16}')
    ->middleware('throttle:60,1')
    ->name('referrals.show');

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
Route::get('/archive/{recording}/edit', [ArchiveManageController::class, 'edit'])
    ->middleware('auth')
    ->name('archive.edit');
Route::get('/archive/{recording}/source', [ArchiveManageController::class, 'source'])
    ->middleware('auth')
    ->name('archive.source');
Route::post('/archive/{recording}/publish', [ArchiveManageController::class, 'publish'])
    ->middleware('auth')
    ->name('archive.publish');
Route::post('/archive/{recording}/trim', [ArchiveManageController::class, 'trim'])
    ->middleware('auth')
    ->name('archive.trim');
Route::post('/archive/{recording}/restore', [ArchiveManageController::class, 'restore'])
    ->middleware('auth')
    ->name('archive.restore');
Route::get('/archive/{recording}/file', [RecordingPlayController::class, 'file'])
    ->withoutMiddleware([
        \Illuminate\Cookie\Middleware\EncryptCookies::class,
        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ])
    ->name('archive.file');

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
Route::post('/studio/{stream}/session/recording', [StudioSessionController::class, 'latestRecording'])
    ->middleware('throttle:120,1')
    ->name('studio.session.recording');
Route::post('/studio/{stream}/session/publisher-claim', [StudioSessionController::class, 'publisherClaim'])
    ->middleware('throttle:60,1')
    ->name('studio.session.publisher-claim');

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

Route::post('/studio/{stream}/ndi', [NdiController::class, 'store'])
    ->middleware('throttle:180,1')
    ->name('studio.ndi.store');
Route::delete('/studio/{stream}/ndi', [NdiController::class, 'destroy'])
    ->middleware('throttle:60,1')
    ->name('studio.ndi.destroy');

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
    Route::get('/my-library', [LibraryController::class, 'index'])->name('my-library');
    Route::get('/digital-library/checkout/callback', [PublicSeriesController::class, 'callback'])->name('digital-library.checkout.callback');
    Route::post('/digital-library/play/{item}/authorize', [PlaybackController::class, 'authorizePlayback'])->name('digital-library.playback.authorize');
    Route::match(['get', 'head'], '/digital-library/play/{item}/media', [PlaybackController::class, 'media'])->name('digital-library.playback.media');
    Route::post('/digital-library/play/{item}/progress', [PlaybackController::class, 'progress'])->name('digital-library.playback.progress');
    Route::get('/account', [AccountController::class, 'edit'])->name('account.edit');
    Route::put('/account', [AccountController::class, 'update'])->name('account.update');
    Route::delete('/account', [AccountController::class, 'destroy'])
        ->middleware('throttle:5,1')
        ->name('account.destroy');

    Route::get('/onboarding', [OnboardingController::class, 'show'])->name('onboarding.show');
    Route::post('/onboarding/type', [OnboardingController::class, 'storeType'])->name('onboarding.type');
    Route::get('/onboarding/channel', [OnboardingController::class, 'channel'])->name('onboarding.channel');
    Route::post('/onboarding/channel', [OnboardingController::class, 'storeChannel'])->name('onboarding.channel.store');

    Route::middleware('affiliate')->prefix('affiliate')->name('affiliate.')->group(function (): void {
        Route::get('prospects', [ProspectController::class, 'index'])->name('prospects.index');
        Route::get('prospects/create', [ProspectController::class, 'create'])->name('prospects.create');
        Route::get('prospects/search', [ProspectController::class, 'search'])->name('prospects.search');
        Route::post('prospects', [ProspectController::class, 'store'])->name('prospects.store');
        Route::get('prospects/{prospect}', [ProspectController::class, 'show'])->name('prospects.show');
        Route::post('prospects/{prospect}/status', [ProspectController::class, 'updateStatus'])->name('prospects.status');
        Route::get('commissions', [AffiliateCommissionStatementController::class, 'statement'])->name('commissions.index');
    });

    Route::middleware('onboarded')->group(function (): void {
        Route::get('/home', CreatorHomeController::class)->name('creator.home');

        Route::prefix('digital-library/sell/{organization}')->name('digital-library.sell.')->group(function (): void {
            Route::get('/', [SellController::class, 'show'])->name('show');
            Route::post('/apply', [SellController::class, 'apply'])->name('apply');
            Route::post('/teachers', [SellController::class, 'storeTeacher'])->name('teachers.store');
            Route::put('/teachers/{teacher}', [SellController::class, 'updateTeacher'])->name('teachers.update');
            Route::get('/series/create', [SellController::class, 'createSeries'])->name('series.create');
            Route::post('/series', [SellController::class, 'storeSeries'])->name('series.store');
            Route::post('/series/order', [SellController::class, 'reorderSeries'])->name('series.order');
            Route::post('/series/move', [SellController::class, 'moveSeries'])->name('series.move');
            Route::get('/series/{series}/edit', [SellController::class, 'editSeries'])->name('series.edit');
            Route::put('/series/{series}', [SellController::class, 'updateSeries'])->name('series.update');
            Route::post('/series/{series}/archive', [SellController::class, 'archiveSeries'])->name('series.archive');
            Route::get('/series/{series}', [SellController::class, 'showSeries'])->name('series.show');
            Route::post('/series/{series}/items', [SellController::class, 'storeItem'])->name('series.items.store');
            Route::post('/series/{series}/items/order', [SellController::class, 'reorderItems'])->name('series.items.order');
            Route::post('/series/{series}/items/move', [SellController::class, 'moveItem'])->name('series.items.move');
            Route::put('/series/{series}/items/{item}', [SellController::class, 'updateItem'])->name('series.items.update');
            Route::post('/series/{series}/items/{item}/archive', [SellController::class, 'archiveItem'])->name('series.items.archive');
            Route::post('/series/{series}/submit', [SellController::class, 'submit'])->name('series.submit');
        });
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
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('search', [AdminDashboardController::class, 'search'])->name('search');

    // Finance & Commercial Management
    Route::prefix('finance')->name('finance.')->group(function (): void {
        Route::get('/', [\App\Http\Controllers\Admin\AdminFinanceController::class, 'overview'])->name('index');
        Route::get('payments', [\App\Http\Controllers\Admin\AdminFinanceController::class, 'payments'])->name('payments');
        Route::get('subscriptions', [\App\Http\Controllers\Admin\AdminFinanceController::class, 'subscriptions'])->name('subscriptions');
        Route::get('invoices', [\App\Http\Controllers\Admin\AdminFinanceController::class, 'invoices'])->name('invoices');
        Route::get('refunds', [\App\Http\Controllers\Admin\AdminFinanceController::class, 'refunds'])->name('refunds');
        Route::get('affiliate-liabilities', [\App\Http\Controllers\Admin\AdminFinanceController::class, 'affiliateLiabilities'])->name('affiliate-liabilities');
        Route::get('reports', [\App\Http\Controllers\Admin\AdminFinanceController::class, 'reports'])->name('reports');
    });

    Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::put('settings/payments', [SettingsController::class, 'updatePayments'])->name('settings.payments');
    Route::put('settings/contact', [SettingsController::class, 'updateContact'])->name('settings.contact');
    Route::put('settings/mail', [SettingsController::class, 'updateMail'])->name('settings.mail');
    Route::post('settings/mail/test', [SettingsController::class, 'sendTestMail'])->name('settings.mail.test');

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
    Route::put('organizations/{organization}/plan', [OrganizationController::class, 'assignPlan'])->name('organizations.plan');

    Route::middleware('can:manageDigitalLibrary')->group(function (): void {
        Route::get('digital-library', [DigitalLibraryController::class, 'overview'])->name('digital-library.overview');
        Route::get('digital-library/settings', [DigitalLibraryController::class, 'settings'])->name('digital-library.settings');
        Route::put('digital-library/settings', [DigitalLibraryController::class, 'updateSettings'])->name('digital-library.settings.update');
        Route::get('digital-library/audit', [DigitalLibraryController::class, 'audit'])->name('digital-library.audit');

        Route::get('digital-library/sellers', [DigitalLibrarySellerController::class, 'index'])->name('digital-library.sellers.index');
        Route::post('digital-library/sellers', [DigitalLibrarySellerController::class, 'store'])->name('digital-library.sellers.store');
        Route::get('digital-library/sellers/{seller}', [DigitalLibrarySellerController::class, 'show'])->name('digital-library.sellers.show');
        Route::post('digital-library/sellers/{seller}/approve', [DigitalLibrarySellerController::class, 'approve'])->name('digital-library.sellers.approve');
        Route::post('digital-library/sellers/{seller}/reject', [DigitalLibrarySellerController::class, 'reject'])->name('digital-library.sellers.reject');
        Route::post('digital-library/sellers/{seller}/suspend', [DigitalLibrarySellerController::class, 'suspend'])->name('digital-library.sellers.suspend');
        Route::post('digital-library/sellers/{seller}/restore', [DigitalLibrarySellerController::class, 'restore'])->name('digital-library.sellers.restore');

        Route::get('digital-library/teachers', [DigitalLibraryTeacherController::class, 'index'])->name('digital-library.teachers.index');
        Route::get('digital-library/teachers/create', [DigitalLibraryTeacherController::class, 'create'])->name('digital-library.teachers.create');
        Route::post('digital-library/teachers', [DigitalLibraryTeacherController::class, 'store'])->name('digital-library.teachers.store');
        Route::get('digital-library/teachers/{teacher}', [DigitalLibraryTeacherController::class, 'show'])->name('digital-library.teachers.show');
        Route::get('digital-library/teachers/{teacher}/edit', [DigitalLibraryTeacherController::class, 'edit'])->name('digital-library.teachers.edit');
        Route::put('digital-library/teachers/{teacher}', [DigitalLibraryTeacherController::class, 'update'])->name('digital-library.teachers.update');
        Route::post('digital-library/teachers/{teacher}/verify', [DigitalLibraryTeacherController::class, 'verify'])->name('digital-library.teachers.verify');
        Route::post('digital-library/teachers/{teacher}/unverify', [DigitalLibraryTeacherController::class, 'unverify'])->name('digital-library.teachers.unverify');
        Route::post('digital-library/teachers/{teacher}/suspend', [DigitalLibraryTeacherController::class, 'suspend'])->name('digital-library.teachers.suspend');
        Route::post('digital-library/teachers/{teacher}/restore', [DigitalLibraryTeacherController::class, 'restore'])->name('digital-library.teachers.restore');
        Route::post('digital-library/teachers/{teacher}/grant', [DigitalLibraryTeacherController::class, 'grant'])->name('digital-library.teachers.grant');
        Route::post('digital-library/handles/reserve', [DigitalLibraryTeacherController::class, 'reserveHandle'])->name('digital-library.handles.reserve');
        Route::post('digital-library/handles/release', [DigitalLibraryTeacherController::class, 'releaseHandle'])->name('digital-library.handles.release');
        Route::post('digital-library/topics', [DigitalLibraryCategoryController::class, 'storeTopic'])->name('digital-library.topics.store');
        Route::get('digital-library/purchases', [DigitalLibraryPurchaseController::class, 'index'])->name('digital-library.purchases.index');
        Route::get('digital-library/purchases/{purchase}', [DigitalLibraryPurchaseController::class, 'show'])->name('digital-library.purchases.show');
        Route::post('digital-library/purchases/{purchase}/refund', [DigitalLibraryPurchaseController::class, 'refund'])->name('digital-library.purchases.refund');

        Route::get('digital-library/series', [DigitalLibrarySeriesController::class, 'index'])->name('digital-library.series.index');
        Route::get('digital-library/series/{series}', [DigitalLibrarySeriesController::class, 'show'])->name('digital-library.series.show');
        Route::post('digital-library/series/{series}/publish', [DigitalLibrarySeriesController::class, 'publish'])->name('digital-library.series.publish');
        Route::post('digital-library/series/{series}/restore', [DigitalLibrarySeriesController::class, 'restore'])->name('digital-library.series.restore');
        Route::post('digital-library/series/{series}/pause-sales', [DigitalLibrarySeriesController::class, 'pauseSales'])->name('digital-library.series.pause-sales');
        Route::post('digital-library/series/{series}/resume-sales', [DigitalLibrarySeriesController::class, 'resumeSales'])->name('digital-library.series.resume-sales');
        Route::post('digital-library/series/{series}/block-playback', [DigitalLibrarySeriesController::class, 'blockPlayback'])->name('digital-library.series.block-playback');
        Route::post('digital-library/series/{series}/restore-playback', [DigitalLibrarySeriesController::class, 'restorePlayback'])->name('digital-library.series.restore-playback');

        Route::get('digital-library/categories', [DigitalLibraryCategoryController::class, 'index'])->name('digital-library.categories.index');
        Route::post('digital-library/categories', [DigitalLibraryCategoryController::class, 'store'])->name('digital-library.categories.store');
        Route::put('digital-library/categories/{category}', [DigitalLibraryCategoryController::class, 'update'])->name('digital-library.categories.update');
    });

    Route::middleware('can:manageAffiliates')->group(function (): void {
        Route::get('affiliate-programme', [AffiliateProgrammeController::class, 'overview'])->name('affiliate-programme.overview');
        Route::get('affiliate-programme/settings', [AffiliateProgrammeController::class, 'settings'])->name('affiliate-programme.settings');
        Route::put('affiliate-programme/settings', [AffiliateProgrammeController::class, 'updateSettings'])->name('affiliate-programme.settings.update');
        Route::get('affiliate-programme/levels', [AffiliateProgrammeController::class, 'levels'])->name('affiliate-programme.levels');
        Route::put('affiliate-programme/levels', [AffiliateProgrammeController::class, 'updateLevels'])->name('affiliate-programme.levels.update');
        Route::get('affiliate-programme/audit', [AffiliateProgrammeController::class, 'audit'])->name('affiliate-programme.audit');

        Route::get('affiliates', [AffiliateController::class, 'index'])->name('affiliates.index');
        Route::get('affiliates/create', [AffiliateController::class, 'create'])->name('affiliates.create');
        Route::post('affiliates', [AffiliateController::class, 'store'])->name('affiliates.store');
        Route::get('affiliates/{affiliate}', [AffiliateController::class, 'show'])->name('affiliates.show');
        Route::put('affiliates/{affiliate}/location', [AffiliateController::class, 'updateLocation'])->name('affiliates.location');
        Route::post('affiliates/{affiliate}/approve', [AffiliateController::class, 'approve'])->name('affiliates.approve');
        Route::post('affiliates/{affiliate}/reject', [AffiliateController::class, 'reject'])->name('affiliates.reject');
        Route::post('affiliates/{affiliate}/suspend', [AffiliateController::class, 'suspend'])->name('affiliates.suspend');
        Route::post('affiliates/{affiliate}/restore', [AffiliateController::class, 'restore'])->name('affiliates.restore');
        Route::put('organizations/{organization}/attribution', [AffiliateController::class, 'updateOrganizationAttribution'])->name('organizations.attribution');

        Route::get('prospects', [AffiliateProspectController::class, 'index'])->name('prospects.index');
        Route::get('prospects/{prospect}', [AffiliateProspectController::class, 'show'])->name('prospects.show');
        Route::post('prospects/{prospect}/release', [AffiliateProspectController::class, 'release'])->name('prospects.release');

        Route::get('affiliate-commissions', [AffiliateCommissionController::class, 'index'])->name('affiliate-commissions.index');
        Route::post('affiliate-commissions/{commission}/reverse', [AffiliateCommissionController::class, 'reverse'])->name('affiliate-commissions.reverse');

        Route::get('affiliate-payouts', [AffiliatePayoutController::class, 'index'])->name('affiliate-payouts.index');
        Route::get('affiliate-payouts/create', [AffiliatePayoutController::class, 'create'])->name('affiliate-payouts.create');
        Route::post('affiliate-payouts', [AffiliatePayoutController::class, 'store'])->name('affiliate-payouts.store');
        Route::get('affiliate-payouts/{payout}', [AffiliatePayoutController::class, 'show'])->name('affiliate-payouts.show');
        Route::post('affiliate-payouts/{payout}/approve', [AffiliatePayoutController::class, 'approve'])->name('affiliate-payouts.approve');
        Route::post('affiliate-payouts/{payout}/mark-paid', [AffiliatePayoutController::class, 'markPaid'])->name('affiliate-payouts.mark-paid');
        Route::post('affiliate-payouts/{payout}/cancel', [AffiliatePayoutController::class, 'cancel'])->name('affiliate-payouts.cancel');
    });

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

Route::get('/digital-library', [LibraryDiscoverController::class, 'index'])->name('digital-library.home');
Route::get('/digital-library/search', [LibraryDiscoverController::class, 'search'])->name('digital-library.search');
Route::get('/digital-library/series/{series}', [PublicSeriesController::class, 'show'])->name('digital-library.series.public');
Route::match(['get', 'post'], '/digital-library/series/{series}/buy', [PublicSeriesController::class, 'buy'])->name('digital-library.series.buy');
Route::get('/digital-library/preview/{item}', [PlaybackController::class, 'preview'])->name('digital-library.preview');

Route::get('/{handle}', [\App\Http\Controllers\DigitalLibrary\StorefrontController::class, 'showByHandle'])
    ->where('handle', '[a-z0-9][a-z0-9\-]{1,30}')
    ->name('digital-library.storefront');
