<?php

namespace App\Services;

use App\Enums\AffiliateCommissionStatus;
use App\Enums\AffiliatePayoutStatus;
use App\Enums\SubscriptionStatus;
use App\Models\AffiliateCommission;
use App\Models\AffiliatePayout;
use App\Models\DigitalLibraryPurchase;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\PaymentCheckout;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Support\LocalTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PlatformFinanceService
{
    /**
     * Get authoritative time boundaries in platform display timezone.
     *
     * @return array{now: Carbon, startOfToday: Carbon, endOfToday: Carbon, startOfYesterday: Carbon, endOfYesterday: Carbon, startOfThisMonth: Carbon, endOfThisMonth: Carbon, startOfLastMonth: Carbon, endOfLastMonth: Carbon}
     */
    public function getBoundaries(): array
    {
        $now = LocalTime::now();

        return [
            'now' => $now,
            'startOfToday' => $now->copy()->startOfDay()->utc(),
            'endOfToday' => $now->copy()->endOfDay()->utc(),
            'startOfYesterday' => $now->copy()->subDay()->startOfDay()->utc(),
            'endOfYesterday' => $now->copy()->subDay()->endOfDay()->utc(),
            'startOfThisMonth' => $now->copy()->startOfMonth()->utc(),
            'endOfThisMonth' => $now->copy()->endOfMonth()->utc(),
            'startOfLastMonth' => $now->copy()->subMonthNoOverflow()->startOfMonth()->utc(),
            'endOfLastMonth' => $now->copy()->subMonthNoOverflow()->endOfMonth()->utc(),
        ];
    }

    /**
     * Summary KPIs for commercial overview.
     *
     * @return array<string, mixed>
     */
    public function getOverviewMetrics(): array
    {
        $bounds = $this->getBoundaries();
        $startOfToday = $bounds['startOfToday'];
        $startOfThisMonth = $bounds['startOfThisMonth'];
        $startOfLastMonth = $bounds['startOfLastMonth'];
        $endOfLastMonth = $bounds['endOfLastMonth'];

        // 1. Confirmed Creator Subscription Revenue (Gross, Refunds, Net)
        $succeededCheckouts = PaymentCheckout::query()
            ->where('status', PaymentCheckout::STATUS_SUCCEEDED);

        $subscriptionGrossThisMonthMinor = (int) (clone $succeededCheckouts)
            ->where(function (Builder $q) use ($startOfThisMonth): void {
                $q->where('verified_at', '>=', $startOfThisMonth)
                    ->orWhere(function (Builder $sub) use ($startOfThisMonth): void {
                        $sub->whereNull('verified_at')
                            ->where('created_at', '>=', $startOfThisMonth);
                    });
            })
            ->sum('amount');

        $subscriptionGrossLastMonthMinor = (int) (clone $succeededCheckouts)
            ->where(function (Builder $q) use ($startOfLastMonth, $endOfLastMonth): void {
                $q->whereBetween('verified_at', [$startOfLastMonth, $endOfLastMonth])
                    ->orWhere(function (Builder $sub) use ($startOfLastMonth, $endOfLastMonth): void {
                        $sub->whereNull('verified_at')
                            ->whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth]);
                    });
            })
            ->sum('amount');

        $subscriptionGrossTodayMinor = (int) (clone $succeededCheckouts)
            ->where(function (Builder $q) use ($startOfToday): void {
                $q->where('verified_at', '>=', $startOfToday)
                    ->orWhere(function (Builder $sub) use ($startOfToday): void {
                        $sub->whereNull('verified_at')
                            ->where('created_at', '>=', $startOfToday);
                    });
            })
            ->sum('amount');

        $subscriptionGrossTotalMinor = (int) (clone $succeededCheckouts)->sum('amount');

        // Creator subscription refunds this month
        $subscriptionRefundsThisMonthMinor = (int) PaymentCheckout::query()
            ->where('status', 'refunded')
            ->where(function (Builder $q) use ($startOfThisMonth): void {
                $q->where('verified_at', '>=', $startOfThisMonth)
                    ->orWhere(function (Builder $sub) use ($startOfThisMonth): void {
                        $sub->whereNull('verified_at')
                            ->where('updated_at', '>=', $startOfThisMonth);
                    });
            })
            ->sum('amount');

        $subscriptionNetThisMonthMinor = $subscriptionGrossThisMonthMinor - $subscriptionRefundsThisMonthMinor;

        // Revenue MoM change (creator subscription gross)
        $revenueMoMChangeMinor = $subscriptionGrossThisMonthMinor - $subscriptionGrossLastMonthMinor;
        $revenueMoMPercent = $subscriptionGrossLastMonthMinor > 0
            ? round(($revenueMoMChangeMinor / $subscriptionGrossLastMonthMinor) * 100, 1)
            : ($subscriptionGrossThisMonthMinor > 0 ? 100.0 : 0.0);

        // 2. Active Paid Subscriptions & MRR
        // Authoritative policy: Plans with amount > 0 and slug != 'free' are included in MRR (including custom plans with defined pricing > 0).
        // Unpriced custom plans (amount = 0) contribute ₦0 to MRR.
        $activePaidSubs = Subscription::query()
            ->with('plan')
            ->where('status', SubscriptionStatus::Active)
            ->whereHas('plan', function (Builder $q): void {
                $q->where('amount', '>', 0)
                    ->where('slug', '!=', 'free');
            })
            ->get();

        $activePaidCount = $activePaidSubs->count();

        $currentMrrMinor = 0;
        foreach ($activePaidSubs as $sub) {
            $plan = $sub->plan;
            if ($plan && $plan->amount > 0 && $plan->slug !== 'free') {
                $currentMrrMinor += $this->normalizeToMonthlyMinor($plan->amount, $plan->interval);
            }
        }

        // 3. Outstanding Invoices & Collection Risk
        $pendingInvoicesQuery = Invoice::query()
            ->whereIn('status', [Invoice::STATUS_PENDING, Invoice::STATUS_PAST_DUE]);

        $outstandingInvoicesCount = (clone $pendingInvoicesQuery)->count();
        $outstandingInvoicesMinor = (int) (clone $pendingInvoicesQuery)->sum('amount');

        $pastDueSubsCount = Subscription::query()
            ->where('status', SubscriptionStatus::PastDue)
            ->count();

        // Failed renewal payments this month
        $failedCheckoutsCount = PaymentCheckout::query()
            ->where('status', PaymentCheckout::STATUS_FAILED)
            ->where('created_at', '>=', $startOfThisMonth)
            ->count();

        // 4. Digital Library Revenue (Gross, Refunds, Net)
        $dlGrossThisMonthMinor = (int) DigitalLibraryPurchase::query()
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', $startOfThisMonth)
            ->sum('amount_collected_minor');

        $dlRefundsThisMonthMinor = (int) DigitalLibraryPurchase::query()
            ->whereNotNull('refunded_at')
            ->where('refunded_at', '>=', $startOfThisMonth)
            ->sum('refunded_amount_minor');

        $dlNetThisMonthMinor = $dlGrossThisMonthMinor - $dlRefundsThisMonthMinor;

        // Platform Net Collected: Explicit sum of authoritative net streams
        // Each refund type is subtracted exactly once in its respective net stream
        $platformNetCollectedThisMonthMinor = $subscriptionNetThisMonthMinor + $dlNetThisMonthMinor;
        $totalRefundsThisMonthMinor = $subscriptionRefundsThisMonthMinor + $dlRefundsThisMonthMinor;

        // 5. Affiliate Financial Liabilities (14-day hold period governed by hold_until and status)
        $affiliateHeldMinor = (int) AffiliateCommission::query()
            ->where('status', AffiliateCommissionStatus::Held)
            ->sum('commission_amount_minor');

        $affiliatePayableMinor = (int) AffiliateCommission::query()
            ->where('status', AffiliateCommissionStatus::Payable)
            ->sum('commission_amount_minor');

        $affiliatePaidThisMonthMinor = (int) AffiliatePayout::query()
            ->where('status', AffiliatePayoutStatus::Paid)
            ->where('paid_at', '>=', $startOfThisMonth)
            ->sum('amount_minor');

        return [
            'currency' => 'NGN',
            // Creator Subscription Revenue Streams
            'subscription_gross_this_month_minor' => $subscriptionGrossThisMonthMinor,
            'subscription_refunds_this_month_minor' => $subscriptionRefundsThisMonthMinor,
            'subscription_net_this_month_minor' => $subscriptionNetThisMonthMinor,
            // Digital Library Revenue Streams
            'dl_gross_this_month_minor' => $dlGrossThisMonthMinor,
            'dl_refunds_this_month_minor' => $dlRefundsThisMonthMinor,
            'dl_net_this_month_minor' => $dlNetThisMonthMinor,
            // Platform Aggregate Net Collected
            'platform_net_collected_this_month_minor' => $platformNetCollectedThisMonthMinor,
            'net_revenue_this_month_minor' => $platformNetCollectedThisMonthMinor, // Backwards-compatible alias
            'refunds_this_month_minor' => $totalRefundsThisMonthMinor,
            // Historical & Period comparisons
            'revenue_this_month_minor' => $subscriptionGrossThisMonthMinor,
            'revenue_last_month_minor' => $subscriptionGrossLastMonthMinor,
            'revenue_mom_change_minor' => $revenueMoMChangeMinor,
            'revenue_mom_percent' => $revenueMoMPercent,
            'revenue_today_minor' => $subscriptionGrossTodayMinor,
            'revenue_total_minor' => $subscriptionGrossTotalMinor,
            // Subscriptions & MRR
            'active_paid_subscriptions_count' => $activePaidCount,
            'current_mrr_minor' => $currentMrrMinor,
            'outstanding_invoices_count' => $outstandingInvoicesCount,
            'outstanding_invoices_minor' => $outstandingInvoicesMinor,
            'past_due_subscriptions_count' => $pastDueSubsCount,
            'failed_checkouts_count' => $failedCheckoutsCount,
            // Affiliate Liabilities
            'affiliate_held_minor' => $affiliateHeldMinor,
            'affiliate_payable_minor' => $affiliatePayableMinor,
            'affiliate_paid_this_month_minor' => $affiliatePaidThisMonthMinor,
        ];
    }

    /**
     * Subscription status intelligence and performance counts.
     *
     * @return array<string, mixed>
     */
    public function getSubscriptionPerformance(): array
    {
        $bounds = $this->getBoundaries();
        $startOfThisMonth = $bounds['startOfThisMonth'];

        $totalSubscriptions = Subscription::query()->count();
        $activeCount = Subscription::query()->where('status', SubscriptionStatus::Active)->count();
        $trialingCount = Subscription::query()->where('status', SubscriptionStatus::Trialing)->count();
        $pastDueCount = Subscription::query()->where('status', SubscriptionStatus::PastDue)->count();
        $cancelledCount = Subscription::query()->where('status', SubscriptionStatus::Cancelled)->count();

        // New subscriptions created this month
        $newThisMonth = Subscription::query()
            ->where('created_at', '>=', $startOfThisMonth)
            ->whereHas('plan', fn (Builder $q) => $q->where('amount', '>', 0))
            ->count();

        // Successful payments count this month
        $successfulPaymentsCount = PaymentCheckout::query()
            ->where('status', PaymentCheckout::STATUS_SUCCEEDED)
            ->where(function (Builder $q) use ($startOfThisMonth): void {
                $q->where('verified_at', '>=', $startOfThisMonth)
                    ->orWhere(function (Builder $sub) use ($startOfThisMonth): void {
                        $sub->whereNull('verified_at')
                            ->where('created_at', '>=', $startOfThisMonth);
                    });
            })
            ->count();

        // First time vs renewals
        $firstTimePaymentsCount = PaymentCheckout::query()
            ->where('status', PaymentCheckout::STATUS_SUCCEEDED)
            ->where('created_at', '>=', $startOfThisMonth)
            ->where('attempts', '<=', 1)
            ->count();

        $renewalsCount = max(0, $successfulPaymentsCount - $firstTimePaymentsCount);

        // Cancellations this month
        $cancelledThisMonth = Subscription::query()
            ->where('status', SubscriptionStatus::Cancelled)
            ->where('updated_at', '>=', $startOfThisMonth)
            ->count();

        return [
            'total' => $totalSubscriptions,
            'active' => $activeCount,
            'trialing' => $trialingCount,
            'past_due' => $pastDueCount,
            'cancelled' => $cancelledCount,
            'new_this_month' => $newThisMonth,
            'successful_payments' => $successfulPaymentsCount,
            'first_time_payments' => $firstTimePaymentsCount,
            'renewals' => $renewalsCount,
            'cancelled_this_month' => $cancelledThisMonth,
        ];
    }

    /**
     * Breakdown of plans with active organization subscriptions and MRR contribution.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getPlanDistribution(): Collection
    {
        $plans = Plan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $totalActivePaid = Subscription::query()
            ->where('status', SubscriptionStatus::Active)
            ->whereHas('plan', fn (Builder $q) => $q->where('amount', '>', 0)->where('slug', '!=', 'free'))
            ->count();

        return $plans->map(function (Plan $plan) use ($totalActivePaid): array {
            $activeCount = Subscription::query()
                ->where('plan_id', $plan->id)
                ->where('status', SubscriptionStatus::Active)
                ->count();

            $isPaidPlan = ($plan->amount > 0 && $plan->slug !== 'free');

            $percentage = $totalActivePaid > 0 && $isPaidPlan
                ? round(($activeCount / $totalActivePaid) * 100, 1)
                : 0.0;

            $mrrMinor = $isPaidPlan
                ? $activeCount * $this->normalizeToMonthlyMinor($plan->amount, $plan->interval)
                : 0;

            return [
                'plan' => $plan,
                'active_count' => $activeCount,
                'percentage' => $percentage,
                'mrr_minor' => $mrrMinor,
            ];
        });
    }

    /**
     * Confirmed creator subscription revenue trend over time.
     *
     * @param  string  $range  '7d'|'30d'|'90d'|'12m'
     * @return array{labels: list<string>, data_minor: list<int>, data_formatted: list<float>, total_minor: int}
     */
    public function getRevenueTrend(string $range = '30d'): array
    {
        $now = LocalTime::now();

        if ($range === '12m') {
            $months = 12;
            $labels = [];
            $dataMinor = [];
            $totalMinor = 0;

            for ($i = $months - 1; $i >= 0; $i--) {
                $monthDate = $now->copy()->subMonthsNoOverflow($i);
                $start = $monthDate->copy()->startOfMonth()->utc();
                $end = $monthDate->copy()->endOfMonth()->utc();
                $label = $monthDate->format('M Y');

                $sum = (int) PaymentCheckout::query()
                    ->where('status', PaymentCheckout::STATUS_SUCCEEDED)
                    ->where(function (Builder $q) use ($start, $end): void {
                        $q->whereBetween('verified_at', [$start, $end])
                            ->orWhere(function (Builder $sub) use ($start, $end): void {
                                $sub->whereNull('verified_at')
                                    ->whereBetween('created_at', [$start, $end]);
                            });
                    })
                    ->sum('amount');

                $labels[] = $label;
                $dataMinor[] = $sum;
                $totalMinor += $sum;
            }

            return [
                'labels' => $labels,
                'data_minor' => $dataMinor,
                'data_formatted' => array_map(fn (int $m): float => round($m / 100, 2), $dataMinor),
                'total_minor' => $totalMinor,
            ];
        }

        $days = match ($range) {
            '7d' => 7,
            '90d' => 90,
            default => 30,
        };

        $labels = [];
        $dataMinor = [];
        $totalMinor = 0;

        for ($i = $days - 1; $i >= 0; $i--) {
            $dayDate = $now->copy()->subDays($i);
            $start = $dayDate->copy()->startOfDay()->utc();
            $end = $dayDate->copy()->endOfDay()->utc();
            $label = $dayDate->format('M j');

            $sum = (int) PaymentCheckout::query()
                ->where('status', PaymentCheckout::STATUS_SUCCEEDED)
                ->where(function (Builder $q) use ($start, $end): void {
                    $q->whereBetween('verified_at', [$start, $end])
                        ->orWhere(function (Builder $sub) use ($start, $end): void {
                            $sub->whereNull('verified_at')
                                ->whereBetween('created_at', [$start, $end]);
                        });
                })
                ->sum('amount');

            $labels[] = $label;
            $dataMinor[] = $sum;
            $totalMinor += $sum;
        }

        return [
            'labels' => $labels,
            'data_minor' => $dataMinor,
            'data_formatted' => array_map(fn (int $m): float => round($m / 100, 2), $dataMinor),
            'total_minor' => $totalMinor,
        ];
    }

    /**
     * Paginated creator subscriptions query with filters.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getSubscriptionsPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Subscription::query()
            ->with(['organization', 'plan']);

        if (! empty($filters['plan_id'])) {
            $query->where('plan_id', (int) $filters['plan_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['health'])) {
            if ($filters['health'] === 'past_due') {
                $query->where('status', SubscriptionStatus::PastDue);
            } elseif ($filters['health'] === 'healthy') {
                $query->where('status', SubscriptionStatus::Active);
            } elseif ($filters['health'] === 'trialing') {
                $query->where('status', SubscriptionStatus::Trialing);
            } elseif ($filters['health'] === 'cancelled') {
                $query->where('status', SubscriptionStatus::Cancelled);
            }
        }

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->whereHas('organization', function (Builder $q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        return $query->latest('updated_at')->paginate($perPage)->withQueryString();
    }

    /**
     * Paginated confirmed payments query with filters.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getPaymentsPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = PaymentCheckout::query()
            ->with(['organization', 'plan']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['plan_id'])) {
            $query->where('plan_id', (int) $filters['plan_id']);
        }

        if (! empty($filters['organization_id'])) {
            $query->where('organization_id', (int) $filters['organization_id']);
        }

        if (! empty($filters['currency'])) {
            $query->where('currency', strtoupper((string) $filters['currency']));
        }

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function (Builder $q) use ($search): void {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhereHas('organization', function (Builder $orgQ) use ($search): void {
                        $orgQ->where('name', 'like', "%{$search}%");
                    });
            });
        }

        return $query->latest('id')->paginate($perPage)->withQueryString();
    }

    /**
     * Paginated invoices query with filters.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getInvoicesPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Invoice::query()
            ->with(['organization', 'subscription.plan']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['organization_id'])) {
            $query->where('organization_id', (int) $filters['organization_id']);
        }

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function (Builder $q) use ($search): void {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('payment_reference', 'like', "%{$search}%")
                    ->orWhereHas('organization', function (Builder $orgQ) use ($search): void {
                        $orgQ->where('name', 'like', "%{$search}%");
                    });
            });
        }

        return $query->latest('due_date')->paginate($perPage)->withQueryString();
    }

    /**
     * Paginated refunds query.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getRefundsPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = DigitalLibraryPurchase::query()
            ->with(['user', 'series', 'seller.organization'])
            ->where(function (Builder $q): void {
                $q->where('refunded_amount_minor', '>', 0)
                    ->orWhereIn('status', [
                        DigitalLibraryPurchase::STATUS_REFUNDED,
                        DigitalLibraryPurchase::STATUS_PARTIALLY_REFUNDED,
                        DigitalLibraryPurchase::STATUS_REVERSED,
                    ]);
            });

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function (Builder $q) use ($search): void {
                $q->where('provider_reference', 'like', "%{$search}%")
                    ->orWhereHas('user', fn (Builder $uq) => $uq->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            });
        }

        return $query->latest('refunded_at')->paginate($perPage)->withQueryString();
    }

    /**
     * Normalize plan amount to monthly minor.
     */
    public function normalizeToMonthlyMinor(int $amount, ?string $interval): int
    {
        return match (strtolower((string) $interval)) {
            'yearly', 'annually' => (int) round($amount / 12),
            'biannually' => (int) round($amount / 6),
            'quarterly' => (int) round($amount / 3),
            'weekly' => (int) round(($amount * 52) / 12),
            default => $amount,
        };
    }

    /**
     * Growth and Acquisition metrics.
     *
     * @return array<string, mixed>
     */
    public function getGrowthMetrics(): array
    {
        $bounds = $this->getBoundaries();
        $startOfToday = $bounds['startOfToday'];
        $startOfYesterday = $bounds['startOfYesterday'];
        $endOfYesterday = $bounds['endOfYesterday'];
        $startOfThisMonth = $bounds['startOfThisMonth'];
        $startOfLastMonth = $bounds['startOfLastMonth'];
        $endOfLastMonth = $bounds['endOfLastMonth'];

        // 1. User Signups
        $signupsToday = User::query()
            ->where('created_at', '>=', $startOfToday)
            ->count();

        $signupsYesterday = User::query()
            ->whereBetween('created_at', [$startOfYesterday, $endOfYesterday])
            ->count();

        $signupsTodayChange = $signupsToday - $signupsYesterday;
        $signupsTodayPercent = $signupsYesterday > 0
            ? round(($signupsTodayChange / $signupsYesterday) * 100, 1)
            : ($signupsToday > 0 ? 100.0 : 0.0);

        $signupsThisMonth = User::query()
            ->where('created_at', '>=', $startOfThisMonth)
            ->count();

        $signupsLastMonth = User::query()
            ->whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])
            ->count();

        $signupsMoMChange = $signupsThisMonth - $signupsLastMonth;
        $signupsMoMPercent = $signupsLastMonth > 0
            ? round(($signupsMoMChange / $signupsLastMonth) * 100, 1)
            : ($signupsThisMonth > 0 ? 100.0 : 0.0);

        $totalUsers = User::query()->count();

        // 2. Organisation Signups
        $orgsThisMonth = Organization::query()
            ->where('created_at', '>=', $startOfThisMonth)
            ->count();

        $orgsLastMonth = Organization::query()
            ->whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])
            ->count();

        $orgsMoMChange = $orgsThisMonth - $orgsLastMonth;
        $orgsMoMPercent = $orgsLastMonth > 0
            ? round(($orgsMoMChange / $orgsLastMonth) * 100, 1)
            : ($orgsThisMonth > 0 ? 100.0 : 0.0);

        $totalOrgs = Organization::query()->count();

        // 3. First Paid Creator Conversions
        // An organisation reaching its first confirmed paid checkout
        $firstPayments = PaymentCheckout::query()
            ->where('status', PaymentCheckout::STATUS_SUCCEEDED)
            ->where('amount', '>', 0)
            ->whereNotNull('organization_id')
            ->groupBy('organization_id')
            ->selectRaw('organization_id, MIN(COALESCE(verified_at, created_at)) as first_paid_at')
            ->get();

        $paidCreatorsThisMonth = $firstPayments->filter(function ($row) use ($startOfThisMonth): bool {
            return Carbon::parse($row->first_paid_at)->gte($startOfThisMonth);
        })->count();

        $paidCreatorsLastMonth = $firstPayments->filter(function ($row) use ($startOfLastMonth, $endOfLastMonth): bool {
            return Carbon::parse($row->first_paid_at)->between($startOfLastMonth, $endOfLastMonth);
        })->count();

        $paidCreatorsMoMChange = $paidCreatorsThisMonth - $paidCreatorsLastMonth;
        $paidCreatorsMoMPercent = $paidCreatorsLastMonth > 0
            ? round(($paidCreatorsMoMChange / $paidCreatorsLastMonth) * 100, 1)
            : ($paidCreatorsThisMonth > 0 ? 100.0 : 0.0);

        $totalPaidCreators = $firstPayments->count();

        // 4. Conversion Rates & Funnel
        $signupToPaidRate = $totalUsers > 0
            ? round(($totalPaidCreators / $totalUsers) * 100, 1)
            : 0.0;

        $signupToPaidThisMonthRate = $signupsThisMonth > 0
            ? round(($paidCreatorsThisMonth / $signupsThisMonth) * 100, 1)
            : 0.0;

        $funnelUsersToOrgsRate = $totalUsers > 0
            ? round(($totalOrgs / $totalUsers) * 100, 1)
            : 0.0;

        $funnelOrgsToPaidRate = $totalOrgs > 0
            ? round(($totalPaidCreators / $totalOrgs) * 100, 1)
            : 0.0;

        return [
            // KPI Cards
            'signups_today' => $signupsToday,
            'signups_yesterday' => $signupsYesterday,
            'signups_today_change' => $signupsTodayChange,
            'signups_today_percent' => $signupsTodayPercent,

            'signups_this_month' => $signupsThisMonth,
            'signups_last_month' => $signupsLastMonth,
            'signups_mom_change' => $signupsMoMChange,
            'signups_mom_percent' => $signupsMoMPercent,

            'new_organizations_this_month' => $orgsThisMonth,
            'new_organizations_last_month' => $orgsLastMonth,
            'new_organizations_mom_change' => $orgsMoMChange,
            'new_organizations_mom_percent' => $orgsMoMPercent,

            'new_paid_creators_this_month' => $paidCreatorsThisMonth,
            'new_paid_creators_last_month' => $paidCreatorsLastMonth,
            'new_paid_creators_mom_change' => $paidCreatorsMoMChange,
            'new_paid_creators_mom_percent' => $paidCreatorsMoMPercent,

            'signup_to_paid_conversion_rate' => $signupToPaidRate,
            'signup_to_paid_this_month_rate' => $signupToPaidThisMonthRate,

            // Conversion Funnel
            'funnel' => [
                'users' => [
                    'label' => 'Registered Users',
                    'count' => $totalUsers,
                    'this_month' => $signupsThisMonth,
                    'conversion_rate' => 100.0,
                ],
                'organizations' => [
                    'label' => 'Organisations Created',
                    'count' => $totalOrgs,
                    'this_month' => $orgsThisMonth,
                    'conversion_rate' => $funnelUsersToOrgsRate,
                ],
                'paid_creators' => [
                    'label' => 'Paid Creator Subscriptions',
                    'count' => $totalPaidCreators,
                    'this_month' => $paidCreatorsThisMonth,
                    'conversion_rate' => $signupToPaidRate,
                    'org_conversion_rate' => $funnelOrgsToPaidRate,
                ],
            ],
        ];
    }

    /**
     * Growth Curve trend over time with 3 series:
     * - User signups
     * - Organisation signups
     * - First-time paid creator conversions
     *
     * Ranges: '7d', '30d', '90d' (daily buckets), '12m' (monthly buckets).
     *
     * @param  string  $range  '7d'|'30d'|'90d'|'12m'
     * @return array{range: string, labels: list<string>, series: array{user_signups: list<int>, organizations_created: list<int>, first_paid_conversions: list<int>}, totals: array{user_signups: int, organizations_created: int, first_paid_conversions: int}}
     */
    public function getGrowthCurve(string $range = '30d'): array
    {
        $now = LocalTime::now();

        if ($range === '12m') {
            $months = 12;
            $rangeStart = $now->copy()->subMonthsNoOverflow($months - 1)->startOfMonth()->utc();
            $rangeEnd = $now->copy()->endOfMonth()->utc();

            $userDates = User::query()
                ->whereBetween('created_at', [$rangeStart, $rangeEnd])
                ->pluck('created_at');

            $orgDates = Organization::query()
                ->whereBetween('created_at', [$rangeStart, $rangeEnd])
                ->pluck('created_at');

            $firstPaidDates = PaymentCheckout::query()
                ->where('status', PaymentCheckout::STATUS_SUCCEEDED)
                ->where('amount', '>', 0)
                ->whereNotNull('organization_id')
                ->groupBy('organization_id')
                ->selectRaw('organization_id, MIN(COALESCE(verified_at, created_at)) as first_paid_at')
                ->get()
                ->pluck('first_paid_at');

            $labels = [];
            $userCounts = [];
            $orgCounts = [];
            $firstPaidCounts = [];

            for ($i = $months - 1; $i >= 0; $i--) {
                $monthDate = $now->copy()->subMonthsNoOverflow($i);
                $start = $monthDate->copy()->startOfMonth()->utc();
                $end = $monthDate->copy()->endOfMonth()->utc();
                $labels[] = $monthDate->format('M Y');

                $userCounts[] = $userDates->filter(fn ($d) => Carbon::parse($d)->between($start, $end))->count();
                $orgCounts[] = $orgDates->filter(fn ($d) => Carbon::parse($d)->between($start, $end))->count();
                $firstPaidCounts[] = $firstPaidDates->filter(fn ($d) => Carbon::parse($d)->between($start, $end))->count();
            }

            return [
                'range' => '12m',
                'labels' => $labels,
                'series' => [
                    'user_signups' => $userCounts,
                    'organizations_created' => $orgCounts,
                    'first_paid_conversions' => $firstPaidCounts,
                ],
                'totals' => [
                    'user_signups' => array_sum($userCounts),
                    'organizations_created' => array_sum($orgCounts),
                    'first_paid_conversions' => array_sum($firstPaidCounts),
                ],
            ];
        }

        $days = match ($range) {
            '7d' => 7,
            '90d' => 90,
            default => 30,
        };

        $rangeStart = $now->copy()->subDays($days - 1)->startOfDay()->utc();
        $rangeEnd = $now->copy()->endOfDay()->utc();

        $userDates = User::query()
            ->whereBetween('created_at', [$rangeStart, $rangeEnd])
            ->pluck('created_at');

        $orgDates = Organization::query()
            ->whereBetween('created_at', [$rangeStart, $rangeEnd])
            ->pluck('created_at');

        $firstPaidDates = PaymentCheckout::query()
            ->where('status', PaymentCheckout::STATUS_SUCCEEDED)
            ->where('amount', '>', 0)
            ->whereNotNull('organization_id')
            ->groupBy('organization_id')
            ->selectRaw('organization_id, MIN(COALESCE(verified_at, created_at)) as first_paid_at')
            ->get()
            ->pluck('first_paid_at');

        $labels = [];
        $userCounts = [];
        $orgCounts = [];
        $firstPaidCounts = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $dayDate = $now->copy()->subDays($i);
            $start = $dayDate->copy()->startOfDay()->utc();
            $end = $dayDate->copy()->endOfDay()->utc();
            $labels[] = $dayDate->format('M j');

            $userCounts[] = $userDates->filter(fn ($d) => Carbon::parse($d)->between($start, $end))->count();
            $orgCounts[] = $orgDates->filter(fn ($d) => Carbon::parse($d)->between($start, $end))->count();
            $firstPaidCounts[] = $firstPaidDates->filter(fn ($d) => Carbon::parse($d)->between($start, $end))->count();
        }

        return [
            'range' => $range,
            'labels' => $labels,
            'series' => [
                'user_signups' => $userCounts,
                'organizations_created' => $orgCounts,
                'first_paid_conversions' => $firstPaidCounts,
            ],
            'totals' => [
                'user_signups' => array_sum($userCounts),
                'organizations_created' => array_sum($orgCounts),
                'first_paid_conversions' => array_sum($firstPaidCounts),
            ],
        ];
    }
}
