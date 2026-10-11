<?php

namespace App\Http\Controllers;

use App\Enums\DigitalLibrarySeriesStatus;
use App\Enums\EventStatus;
use App\Enums\OrgRole;
use App\Enums\StreamStatus;
use App\Enums\SubscriptionStatus;
use App\Models\DigitalLibraryPurchase;
use App\Models\Event;
use App\Models\ListenerSession;
use App\Models\Recording;
use App\Services\PaystackPaymentConfirmationService;
use App\Support\StudioExpiry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class CreatorHomeController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        if ($user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        $userOrganizations = $user->organizations()
            ->with(['subscription.plan', 'streams', 'digitalLibrarySeller'])
            ->orderByPivot('role')
            ->get();

        if ($userOrganizations->isEmpty()) {
            return redirect()->route('onboarding.show');
        }

        // Multi-organization selection & session persistence
        $requestedOrg = $request->query('org');
        $organization = null;

        if ($requestedOrg) {
            $organization = $userOrganizations->first(function ($o) use ($requestedOrg) {
                return $o->slug === $requestedOrg || (string) $o->id === (string) $requestedOrg;
            });
            if ($organization) {
                session(['active_org_id' => $organization->id]);
            }
        }

        if (! $organization && session('active_org_id')) {
            $organization = $userOrganizations->firstWhere('id', session('active_org_id'));
        }

        if (! $organization) {
            $organization = $userOrganizations->first();
            if ($organization) {
                session(['active_org_id' => $organization->id]);
            }
        }

        app(PaystackPaymentConfirmationService::class)->confirmPending($organization, immediate: true);
        $organization->loadMissing(['subscription.plan', 'streams', 'digitalLibrarySeller.series']);

        $stream = $organization->defaultStream();
        $studioUrl = $stream
            ? route('admin.streams.studio', $stream)
            : null;
        $signedStudioUrl = $stream
            ? URL::temporarySignedRoute('studio.stream', StudioExpiry::at(), ['stream' => $stream])
            : null;

        $userRole = $user->orgRole($organization);
        $isOwner = $userRole === OrgRole::Owner;
        $canManage = $userRole?->canManage() ?? false;
        $subscription = $organization->subscription;
        $canBroadcast = $organization->allowsBroadcast();
        $freeDaysRemaining = $subscription?->freeDaysRemaining();

        // 1. Live status & current / upcoming event
        $isLive = $stream?->status === StreamStatus::Live;
        $activeListeners = $isLive ? $stream->activeListenerCount() : 0;
        $openEvent = $stream?->openServiceEvent();

        $nextScheduledEvent = Event::query()
            ->where('organization_id', $organization->id)
            ->whereIn('status', [EventStatus::Scheduled, EventStatus::Paused])
            ->where('scheduled_at', '>=', now()->subMinutes(30))
            ->orderBy('scheduled_at')
            ->first();

        // 2. Authoritative KPI metrics
        $broadcastsThisMonth = Event::query()
            ->where('organization_id', $organization->id)
            ->where('started_at', '>=', now()->startOfMonth())
            ->count();

        $upcomingEventsCount = Event::query()
            ->where('organization_id', $organization->id)
            ->where('status', EventStatus::Scheduled)
            ->where('scheduled_at', '>=', now())
            ->count();

        $streamIds = $organization->streams()->pluck('id');
        $publishedPodcastsCount = Recording::query()
            ->whereIn('stream_id', $streamIds)
            ->where('is_public', true)
            ->count();

        $totalRecordingsCount = Recording::query()
            ->whereIn('stream_id', $streamIds)
            ->count();

        $hasAnalytics = $organization->hasFeature('listener_analytics');
        $uniqueListeners30d = null;
        if ($hasAnalytics) {
            $orgEventIds = $organization->events()->pluck('id');
            $uniqueListeners30d = (int) ListenerSession::query()
                ->whereIn('event_id', $orgEventIds)
                ->where('started_at', '>=', now()->subDays(30))
                ->selectRaw('COUNT(DISTINCT session_key) as aggregate')
                ->value('aggregate');
        }

        // 3. Upcoming schedule & recent broadcasts
        $upcomingEvents = Event::query()
            ->where('organization_id', $organization->id)
            ->whereIn('status', [EventStatus::Scheduled, EventStatus::Paused])
            ->where('scheduled_at', '>=', now()->subHours(2))
            ->orderBy('scheduled_at')
            ->limit(4)
            ->get();

        $recentBroadcasts = Event::query()
            ->where('organization_id', $organization->id)
            ->where('status', EventStatus::Ended)
            ->latest('ended_at')
            ->limit(4)
            ->get();

        // 4. Digital Library seller state (authoritative completed purchase records)
        $seller = $organization->digitalLibrarySeller;
        $sellerStatus = $seller?->status?->value ?? 'none';
        $sellerSeriesCount = 0;
        $sellerPublishedSeriesCount = 0;
        if ($seller) {
            if ($seller->relationLoaded('series')) {
                $sellerSeriesCount = $seller->series->count();
                $sellerPublishedSeriesCount = $seller->series->where('status', DigitalLibrarySeriesStatus::Published->value)->count();
            } else {
                $sellerSeriesCount = $seller->series()->count();
                $sellerPublishedSeriesCount = $seller->series()->where('status', DigitalLibrarySeriesStatus::Published->value)->count();
            }
        }

        $sellerTotalPurchases = 0;
        $sellerGrossRevenueNgnMinor = 0;
        $sellerRefundedNgnMinor = 0;
        $sellerNetRevenueNgnMinor = 0;

        if ($seller) {
            $smdlAggregate = DigitalLibraryPurchase::query()
                ->where('seller_id', $seller->id)
                ->whereIn('status', [
                    DigitalLibraryPurchase::STATUS_PAID,
                    DigitalLibraryPurchase::STATUS_PARTIALLY_REFUNDED,
                    DigitalLibraryPurchase::STATUS_REFUNDED,
                ])
                ->where('currency', 'NGN')
                ->selectRaw('COUNT(*) as total_orders, COALESCE(SUM(amount_collected_minor), 0) as gross_minor, COALESCE(SUM(refunded_amount_minor), 0) as refunded_minor')
                ->first();

            if ($smdlAggregate) {
                $sellerTotalPurchases = (int) $smdlAggregate->total_orders;
                $sellerGrossRevenueNgnMinor = (int) $smdlAggregate->gross_minor;
                $sellerRefundedNgnMinor = (int) $smdlAggregate->refunded_minor;
                $sellerNetRevenueNgnMinor = max(0, $sellerGrossRevenueNgnMinor - $sellerRefundedNgnMinor);
            }
        }

        // 5. Team snapshot
        $teamMembers = $organization->users()->orderByPivot('role')->get();
        $teamCount = $teamMembers->count();
        $ownersCount = $teamMembers->where('pivot.role', OrgRole::Owner->value)->count();
        $adminsCount = $teamMembers->where('pivot.role', OrgRole::Admin->value)->count();
        $membersCount = $teamMembers->where('pivot.role', OrgRole::Member->value)->count();

        // 6. Setup checklist
        $hasStream = $organization->relationLoaded('streams')
            ? $organization->streams->isNotEmpty()
            : $organization->streams()->exists();

        $setupChecklist = [
            'has_logo' => filled($organization->logo_path),
            'has_stream' => $hasStream,
            'has_scheduled_event' => $upcomingEventsCount > 0 || $organization->events()->exists(),
            'has_broadcasted' => $broadcastsThisMonth > 0 || $totalRecordingsCount > 0 || $organization->events()->whereNotNull('started_at')->exists(),
            'has_team' => $teamCount > 1,
        ];
        $completedSetupSteps = count(array_filter($setupChecklist));
        $totalSetupSteps = count($setupChecklist);
        $isSetupComplete = $completedSetupSteps === $totalSetupSteps;

        // 7. Attention queue items
        $attentionItems = [];
        if ($subscription && $subscription->status === SubscriptionStatus::PastDue) {
            $attentionItems[] = [
                'type' => 'past_due',
                'level' => 'danger',
                'title' => 'Subscription Past Due',
                'message' => 'Your subscription payment is overdue. Update your billing details to maintain uninterrupted broadcasting.',
                'action_label' => 'Manage Billing',
                'action_url' => route('billing.plans'),
            ];
        } elseif ($subscription && $subscription->isOnFreePeriod() && $freeDaysRemaining !== null && $freeDaysRemaining <= 5) {
            $attentionItems[] = [
                'type' => 'trial_expiring',
                'level' => 'warning',
                'title' => 'Free Trial Ending Soon',
                'message' => $freeDaysRemaining === 0 ? 'Your free trial has ended today.' : "Only {$freeDaysRemaining} day(s) remaining on your free trial.",
                'action_label' => 'Choose a Plan',
                'action_url' => route('billing.plans'),
            ];
        }

        if ($openEvent && $openEvent->status === EventStatus::Paused) {
            $attentionItems[] = [
                'type' => 'event_paused',
                'level' => 'warning',
                'title' => 'Broadcast Paused',
                'message' => 'Your service is currently in a paused state. Resume or end the service from Studio.',
                'action_label' => 'Open Studio',
                'action_url' => $studioUrl,
            ];
        }

        if ($nextScheduledEvent && $nextScheduledEvent->scheduled_at && ! $nextScheduledEvent->scheduled_at->isPast() && $nextScheduledEvent->scheduled_at->diffInMinutes(now()) <= 120) {
            $mins = $nextScheduledEvent->scheduled_at->diffInMinutes(now());
            $attentionItems[] = [
                'type' => 'event_imminent',
                'level' => 'info',
                'title' => 'Upcoming Service Starting Soon',
                'message' => "'{$nextScheduledEvent->title}' is scheduled to start in {$mins} minutes.",
                'action_label' => 'Open Studio',
                'action_url' => $studioUrl,
            ];
        }

        return view('creator.home', [
            'organization' => $organization,
            'userOrganizations' => $userOrganizations,
            'stream' => $stream,
            'studioUrl' => $studioUrl,
            'signedStudioUrl' => $signedStudioUrl,
            'subscription' => $subscription,
            'canBroadcast' => $canBroadcast,
            'isOwner' => $isOwner,
            'canManage' => $canManage,
            'canSellLibrary' => $canManage,
            'freeDaysRemaining' => $freeDaysRemaining,
            'isLive' => $isLive,
            'activeListeners' => $activeListeners,
            'openEvent' => $openEvent,
            'nextScheduledEvent' => $nextScheduledEvent,
            'broadcastsThisMonth' => $broadcastsThisMonth,
            'upcomingEventsCount' => $upcomingEventsCount,
            'publishedPodcastsCount' => $publishedPodcastsCount,
            'totalRecordingsCount' => $totalRecordingsCount,
            'hasAnalytics' => $hasAnalytics,
            'uniqueListeners30d' => $uniqueListeners30d,
            'upcomingEvents' => $upcomingEvents,
            'recentBroadcasts' => $recentBroadcasts,
            'seller' => $seller,
            'sellerStatus' => $sellerStatus,
            'sellerSeriesCount' => $sellerSeriesCount,
            'sellerPublishedSeriesCount' => $sellerPublishedSeriesCount,
            'sellerTotalPurchases' => $sellerTotalPurchases,
            'sellerGrossRevenueNgnMinor' => $sellerGrossRevenueNgnMinor,
            'sellerRefundedNgnMinor' => $sellerRefundedNgnMinor,
            'sellerNetRevenueNgnMinor' => $sellerNetRevenueNgnMinor,
            'teamMembers' => $teamMembers,
            'teamCount' => $teamCount,
            'ownersCount' => $ownersCount,
            'adminsCount' => $adminsCount,
            'membersCount' => $membersCount,
            'setupChecklist' => $setupChecklist,
            'completedSetupSteps' => $completedSetupSteps,
            'totalSetupSteps' => $totalSetupSteps,
            'isSetupComplete' => $isSetupComplete,
            'attentionItems' => $attentionItems,
        ]);
    }
}
