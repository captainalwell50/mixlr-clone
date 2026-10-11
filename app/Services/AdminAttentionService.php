<?php

namespace App\Services;

use App\Enums\AffiliateStatus;
use App\Enums\DigitalLibrarySellerStatus;
use App\Enums\DigitalLibrarySeriesStatus;
use App\Enums\SubscriptionStatus;
use App\Models\AffiliateAccount;
use App\Models\DigitalLibrarySeller;
use App\Models\DigitalLibrarySeries;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminAttentionService
{
    /**
     * @return array<int, array{
     *     key: string,
     *     label: string,
     *     count: int,
     *     severity: 'critical'|'warning'|'info',
     *     action_label: string,
     *     url: string
     * }>
     */
    public function getActionableItems(): array
    {
        $items = [];

        // 1. Pending Digital Library Seller applications
        try {
            $pendingSellers = DigitalLibrarySeller::query()
                ->where('status', DigitalLibrarySellerStatus::Pending)
                ->count();

            if ($pendingSellers > 0) {
                $items[] = [
                    'key' => 'pending_sellers',
                    'label' => $pendingSellers === 1
                        ? '1 seller application pending review'
                        : "{$pendingSellers} seller applications pending review",
                    'count' => $pendingSellers,
                    'severity' => 'warning',
                    'action_label' => 'Review',
                    'url' => route('admin.digital-library.sellers.index'),
                ];
            }
        } catch (\Throwable) {
        }

        // 2. Pending Affiliate applications
        try {
            $pendingAffiliates = AffiliateAccount::query()
                ->where('status', AffiliateStatus::Pending)
                ->count();

            if ($pendingAffiliates > 0) {
                $items[] = [
                    'key' => 'pending_affiliates',
                    'label' => $pendingAffiliates === 1
                        ? '1 affiliate application pending review'
                        : "{$pendingAffiliates} affiliate applications pending review",
                    'count' => $pendingAffiliates,
                    'severity' => 'warning',
                    'action_label' => 'Review',
                    'url' => route('admin.affiliates.index'),
                ];
            }
        } catch (\Throwable) {
        }

        // 3. Digital Library Series submitted for publication review
        try {
            $reviewSeries = DigitalLibrarySeries::query()
                ->where('status', DigitalLibrarySeriesStatus::Review)
                ->count();

            if ($reviewSeries > 0) {
                $items[] = [
                    'key' => 'review_series',
                    'label' => $reviewSeries === 1
                        ? '1 digital library series submitted for review'
                        : "{$reviewSeries} digital library series submitted for review",
                    'count' => $reviewSeries,
                    'severity' => 'warning',
                    'action_label' => 'Review',
                    'url' => route('admin.digital-library.series.index'),
                ];
            }
        } catch (\Throwable) {
        }

        // 4. Past-due Subscriptions (Failed Renewals)
        try {
            $pastDueSubs = Subscription::query()
                ->where('status', SubscriptionStatus::PastDue)
                ->count();

            if ($pastDueSubs > 0) {
                $items[] = [
                    'key' => 'past_due_subscriptions',
                    'label' => $pastDueSubs === 1
                        ? '1 subscription renewal is past due / failed'
                        : "{$pastDueSubs} subscription renewals are past due / failed",
                    'count' => $pastDueSubs,
                    'severity' => 'critical',
                    'action_label' => 'Inspect',
                    'url' => route('admin.organizations.index'),
                ];
            }
        } catch (\Throwable) {
        }

        // 5. Failed queue jobs
        try {
            if (Schema::hasTable('failed_jobs')) {
                $failedJobs = DB::table('failed_jobs')->count();
                if ($failedJobs > 0) {
                    $items[] = [
                        'key' => 'failed_jobs',
                        'label' => $failedJobs === 1
                            ? '1 background queue job has failed'
                            : "{$failedJobs} background queue jobs have failed",
                        'count' => $failedJobs,
                        'severity' => 'critical',
                        'action_label' => 'Inspect',
                        'url' => route('admin.dashboard') . '#platform-health',
                    ];
                }
            }
        } catch (\Throwable) {
        }

        return $items;
    }

    public function totalAttentionCount(): int
    {
        $items = $this->getActionableItems();

        return array_sum(array_column($items, 'count'));
    }
}
