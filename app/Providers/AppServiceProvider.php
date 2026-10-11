<?php

namespace App\Providers;

use App\Contracts\AudioSegmentConcatenator;
use App\Enums\OrgRole;
use App\Enums\StreamStatus;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\FfmpegAudioConcatenator;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewInstance;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AudioSegmentConcatenator::class, FfmpegAudioConcatenator::class);
        $this->app->singleton(\App\Contracts\DigitalLibraryPreviewClipper::class, \App\Services\FfmpegPreviewClipper::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->composeCreatorNav();
        $this->composeCreatorLayout();
        $this->applyAdminConfigOverrides();

        // Platform-operator permission. Organization membership and an affiliate
        // account do not grant this, and this permission does not grant Studio,
        // billing, or channel management.
        Gate::define('manageAffiliates', fn (User $user): bool => $user->isAdmin());
        Gate::define('manageDigitalLibrary', fn (User $user): bool => $user->isAdmin());

        if ($this->app->environment('production')) {
            URL::forceScheme('https');

            $secret = config('streaming.mediamtx.webhook_secret');
            if (! is_string($secret) || $secret === '') {
                Log::error('MEDIAMTX_WEBHOOK_SECRET is not set; MediaMTX webhooks will return 503.');
            }
        }
    }

    protected function applyAdminConfigOverrides(): void
    {
        try {
            if (! Schema::hasTable('site_settings')) {
                return;
            }

            if (config('app.support_email_fallback') === null) {
                config(['app.support_email_fallback' => config('app.support_email')]);
            }

            config(['app.support_email' => SiteSetting::supportEmail()]);
            SiteSetting::applyMailConfig();
        } catch (\Throwable) {
            // First migrate / missing table.
        }
    }

    protected function composeCreatorNav(): void
    {
        View::composer('layouts.app', function (ViewInstance $view): void {
            $user = auth()->user();
            $showUpgrade = false;
            if ($user && ! $user->isAdmin()) {
                $organization = $user->organizations()->with('subscription.plan')->first();
                $showUpgrade = $organization?->needsUpgradeCta() ?? false;
            }

            $view->with('showUpgradeCta', $showUpgrade);
        });
    }

    protected function composeCreatorLayout(): void
    {
        View::composer('layouts.creator', function (ViewInstance $view): void {
            $user = auth()->user();
            if (! $user) {
                return;
            }

            $viewData = $view->getData();
            $userOrganizations = $viewData['userOrganizations'] ?? null;

            if ($userOrganizations === null) {
                $userOrganizations = $user->organizations()
                    ->with(['subscription.plan', 'streams'])
                    ->orderByPivot('role')
                    ->get();
            }

            $activeOrg = $viewData['organization'] ?? $viewData['creatorOrg'] ?? null;

            if (! $activeOrg && $userOrganizations->isNotEmpty()) {
                $requestedSlug = request()->query('org');
                $sessionOrgId = session('active_org_id');

                if ($requestedSlug) {
                    $activeOrg = $userOrganizations->first(function ($o) use ($requestedSlug) {
                        return $o->slug === $requestedSlug || (string) $o->id === (string) $requestedSlug;
                    });
                    if ($activeOrg) {
                        session(['active_org_id' => $activeOrg->id]);
                    }
                }

                if (! $activeOrg && $sessionOrgId) {
                    $activeOrg = $userOrganizations->firstWhere('id', $sessionOrgId);
                }

                if (! $activeOrg) {
                    $activeOrg = $userOrganizations->first();
                    if ($activeOrg) {
                        session(['active_org_id' => $activeOrg->id]);
                    }
                }
            }

            if ($activeOrg) {
                $activeOrg->loadMissing(['subscription.plan', 'streams']);
            }

            $creatorStream = $activeOrg?->defaultStream();
            $creatorRole = $activeOrg ? $user->orgRole($activeOrg) : null;
            $creatorIsOwner = $creatorRole === OrgRole::Owner;
            $creatorCanManage = $creatorRole?->canManage() ?? false;
            $creatorCanBroadcast = $activeOrg?->allowsBroadcast() ?? false;

            $creatorIsLive = $creatorStream?->status === StreamStatus::Live;
            $creatorActiveListeners = $creatorIsLive ? $creatorStream->activeListenerCount() : 0;

            $creatorStudioUrl = $creatorStream ? route('admin.streams.studio', $creatorStream) : null;

            $view->with([
                'currentUser' => $user,
                'creatorOrg' => $activeOrg,
                'userOrganizations' => $userOrganizations,
                'creatorStream' => $creatorStream,
                'creatorRole' => $creatorRole,
                'creatorIsOwner' => $creatorIsOwner,
                'creatorCanManage' => $creatorCanManage,
                'creatorCanBroadcast' => $creatorCanBroadcast,
                'creatorIsLive' => $creatorIsLive,
                'creatorActiveListeners' => $creatorActiveListeners,
                'creatorStudioUrl' => $creatorStudioUrl,
            ]);
        });
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
