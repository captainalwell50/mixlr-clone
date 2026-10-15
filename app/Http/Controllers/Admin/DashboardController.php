<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AffiliateStatus;
use App\Enums\DigitalLibrarySellerStatus;
use App\Enums\DigitalLibrarySeriesStatus;
use App\Enums\StreamStatus;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\AffiliateAccount;
use App\Models\DigitalLibraryPurchase;
use App\Models\DigitalLibrarySeller;
use App\Models\DigitalLibrarySeries;
use App\Models\DigitalLibraryTeacher;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Stream;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AdminAttentionService;
use App\Services\AzureVmCapacityService;
use App\Services\ListenerPresenceService;
use App\Services\PaystackClient;
use App\Services\PlatformFinanceService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(
        Request $request,
        AdminAttentionService $attentionService,
        ListenerPresenceService $presenceService,
        AzureVmCapacityService $vmCapacity,
        PaystackClient $paystack,
        PlatformFinanceService $finance,
    ): View {
        abort_unless($request->user()?->isAdmin(), 403);

        // 1. Live activity & streams
        $liveStreams = Stream::query()
            ->with('organization')
            ->where('status', StreamStatus::Live)
            ->latest('started_at')
            ->get();

        // Calculate authoritative active listeners across live streams
        $liveStreamIds = $liveStreams->pluck('id');
        $currentListeners = 0;
        foreach ($liveStreams as $liveStream) {
            $currentListeners += $presenceService->countForStream($liveStream);
        }

        // 2. Metrics with authoritative definitions
        $liveNowCount = $liveStreams->count();
        $broadcastsTodayCount = Event::query()
            ->where('started_at', '>=', now()->startOfDay())
            ->count();

        $totalOrganizations = Organization::query()->count();
        $activeOrganizations = Subscription::query()
            ->where('status', SubscriptionStatus::Active)
            ->distinct()
            ->count('organization_id');

        $totalUsers = User::query()->count();

        // Digital Library commerce metrics (explicitly gross collected & net for NGN)
        $dlPurchasesQuery = DigitalLibraryPurchase::query()
            ->whereIn('status', [
                DigitalLibraryPurchase::STATUS_PAID,
                DigitalLibraryPurchase::STATUS_PARTIALLY_REFUNDED,
                DigitalLibraryPurchase::STATUS_REFUNDED,
            ]);
        $dlPurchasesCount = (clone $dlPurchasesQuery)->count();
        $dlNgnPurchasesQuery = (clone $dlPurchasesQuery)->where('currency', 'NGN');
        $dlGrossCollectedMinor = (int) (clone $dlNgnPurchasesQuery)->sum('amount_collected_minor');
        $dlRefundedMinor = (int) (clone $dlNgnPurchasesQuery)->sum('refunded_amount_minor');
        $dlNetCollectedMinor = max(0, $dlGrossCollectedMinor - $dlRefundedMinor);

        // Seller and Affiliate counts
        $approvedSellersCount = DigitalLibrarySeller::query()
            ->where('status', DigitalLibrarySellerStatus::Approved)
            ->count();
        $pendingSellersCount = DigitalLibrarySeller::query()
            ->where('status', DigitalLibrarySellerStatus::Pending)
            ->count();

        $approvedAffiliatesCount = AffiliateAccount::query()
            ->where('status', AffiliateStatus::Approved)
            ->count();
        $pendingAffiliatesCount = AffiliateAccount::query()
            ->where('status', AffiliateStatus::Pending)
            ->count();

        // 3. Attention items
        $attentionItems = $attentionService->getActionableItems();

        // 4. Platform health snapshot
        $listenPlaybackStatus = Stream::listenPlaybackStatus();
        $vmSnapshot = $vmCapacity->snapshot();
        $paystackEnabled = $paystack->enabled();

        // 5. Commercial & Finance Telemetry
        $financeOverview = $finance->getOverviewMetrics();
        $subscriptionPerformance = $finance->getSubscriptionPerformance();
        $revenueTrend = $finance->getRevenueTrend('30d');

        // 6. Growth & Acquisition Telemetry
        $growthRange = in_array($request->query('growth_range'), ['7d', '30d', '90d', '12m'], true)
            ? (string) $request->query('growth_range')
            : '30d';
        $growthMetrics = $finance->getGrowthMetrics();
        $growthCurve = $finance->getGrowthCurve($growthRange);

        return view('admin.dashboard', [
            'metrics' => [
                'live_now' => $liveNowCount,
                'current_listeners' => $currentListeners,
                'broadcasts_today' => $broadcastsTodayCount,
                'total_organizations' => $totalOrganizations,
                'active_organizations' => $activeOrganizations,
                'total_users' => $totalUsers,
                'dl_purchases' => $dlPurchasesCount,
                'dl_gross_collected_minor' => $dlGrossCollectedMinor,
                'dl_net_collected_minor' => $dlNetCollectedMinor,
                'approved_sellers' => $approvedSellersCount,
                'pending_sellers' => $pendingSellersCount,
                'approved_affiliates' => $approvedAffiliatesCount,
                'pending_affiliates' => $pendingAffiliatesCount,
            ],
            'financeOverview' => $financeOverview,
            'subscriptionPerformance' => $subscriptionPerformance,
            'revenueTrend' => $revenueTrend,
            'growthMetrics' => $growthMetrics,
            'growthCurve' => $growthCurve,
            'growthRange' => $growthRange,
            'liveStreams' => $liveStreams,
            'attentionItems' => $attentionItems,
            'listenPlaybackStatus' => $listenPlaybackStatus,
            'vmSnapshot' => $vmSnapshot,
            'paystackEnabled' => $paystackEnabled,
        ]);
    }

    public function search(Request $request): View
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return view('admin.search', [
                'q' => '',
                'streams' => collect(),
                'organizations' => collect(),
                'users' => collect(),
                'teachers' => collect(),
                'series' => collect(),
                'affiliates' => collect(),
                'totalResults' => 0,
            ]);
        }

        $streams = Stream::query()
            ->with('organization')
            ->where('title', 'like', "%{$q}%")
            ->orWhere('uuid', 'like', "%{$q}%")
            ->limit(5)
            ->get();

        $organizations = Organization::query()
            ->where('name', 'like', "%{$q}%")
            ->orWhere('slug', 'like', "%{$q}%")
            ->limit(5)
            ->get();

        $users = User::query()
            ->where('name', 'like', "%{$q}%")
            ->orWhere('email', 'like', "%{$q}%")
            ->limit(5)
            ->get();

        $teachers = DigitalLibraryTeacher::query()
            ->where('display_name', 'like', "%{$q}%")
            ->limit(5)
            ->get();

        $series = DigitalLibrarySeries::query()
            ->with('teacher')
            ->where('title', 'like', "%{$q}%")
            ->limit(5)
            ->get();

        $affiliates = AffiliateAccount::query()
            ->with('user')
            ->where('referral_code', 'like', "%{$q}%")
            ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%"))
            ->limit(5)
            ->get();

        $totalResults = $streams->count()
            + $organizations->count()
            + $users->count()
            + $teachers->count()
            + $series->count()
            + $affiliates->count();

        return view('admin.search', compact(
            'q',
            'streams',
            'organizations',
            'users',
            'teachers',
            'series',
            'affiliates',
            'totalResults',
        ));
    }
}
