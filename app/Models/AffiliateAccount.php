<?php

namespace App\Models;

use App\Enums\AffiliateStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AffiliateAccount extends Model
{
    protected $fillable = [
        'user_id',
        'referral_code',
        'status',
        'country_code',
        'state_region',
        'city',
        'bank_name',
        'account_name',
        'account_number',
        'bank_code',
        'approved_at',
        'suspended_at',
        'payout_qualified_at',
    ];

    /**
     * Attributes hidden from serialization to prevent leaking encrypted credentials.
     *
     * @var list<string>
     */
    protected $hidden = [
        'account_number',
        'account_name',
    ];

    protected function casts(): array
    {
        return [
            'status' => AffiliateStatus::class,
            'approved_at' => 'datetime',
            'suspended_at' => 'datetime',
            'payout_qualified_at' => 'datetime',
            'account_name' => 'encrypted',
            'account_number' => 'encrypted',
        ];
    }

    public function hasPayoutDestination(): bool
    {
        return ! empty($this->bank_name) && ! empty($this->account_number);
    }

    public function maskedAccountNumber(): ?string
    {
        if (empty($this->account_number)) {
            return null;
        }

        $raw = (string) $this->account_number;
        $len = strlen($raw);
        if ($len <= 4) {
            return str_repeat('•', max(0, $len - 1)) . substr($raw, -1);
        }

        return str_repeat('•', 4) . substr($raw, -4);
    }

    public function payoutDestinationSummary(): ?string
    {
        if (! $this->hasPayoutDestination()) {
            return null;
        }

        $masked = $this->maskedAccountNumber();
        $summary = "{$this->bank_name} ({$masked})";
        if (! empty($this->account_name)) {
            $summary .= " - {$this->account_name}";
        }

        return $summary;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function referralVisits(): HasMany
    {
        return $this->hasMany(AffiliateReferralVisit::class);
    }

    public function attributions(): HasMany
    {
        return $this->hasMany(AffiliateAttribution::class);
    }

    public function prospects(): HasMany
    {
        return $this->hasMany(AffiliateProspect::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(AffiliateCommission::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(AffiliatePayout::class);
    }

    public function commissionAudits(): HasMany
    {
        return $this->hasMany(AffiliateCommissionAudit::class);
    }

    public function isQualified(): bool
    {
        return $this->payout_qualified_at !== null;
    }

    public function qualifyingOrganizationsCount(): int
    {
        return (int) $this->commissions()
            ->whereIn('status', [
                \App\Enums\AffiliateCommissionStatus::Held->value,
                \App\Enums\AffiliateCommissionStatus::Payable->value,
                \App\Enums\AffiliateCommissionStatus::Paid->value,
            ])
            ->where('commission_amount_minor', '>', 0)
            ->whereNull('reversal_of_commission_id')
            ->whereDoesntHave('reversals')
            ->distinct('organization_id')
            ->count('organization_id');
    }

    /**
     * @return array{name: string, min_active_paying_churches: int, display_order: int, enabled: bool}
     */
    public function currentLevel(): array
    {
        $count = $this->qualifyingOrganizationsCount();
        $levels = \App\Services\AffiliateProgrammeSettings::levels();
        $matched = $levels[0] ?? ['name' => 'Starter', 'min_active_paying_churches' => 0, 'display_order' => 1, 'enabled' => true];

        foreach ($levels as $level) {
            if (! ($level['enabled'] ?? true)) {
                continue;
            }
            if ($count >= ($level['min_active_paying_churches'] ?? 0)) {
                $matched = $level;
            }
        }

        return $matched;
    }

    /**
     * @return array{
     *     current: array{name: string, min_active_paying_churches: int, display_order: int, enabled: bool},
     *     next: ?array{name: string, min_active_paying_churches: int, display_order: int, enabled: bool},
     *     current_count: int,
     *     target_count: ?int,
     *     remaining: int,
     *     progress_percent: int
     * }
     */
    public function tierProgress(): array
    {
        $count = $this->qualifyingOrganizationsCount();
        $levels = \App\Services\AffiliateProgrammeSettings::levels();
        $current = $this->currentLevel();
        $next = null;

        foreach ($levels as $level) {
            if (! ($level['enabled'] ?? true)) {
                continue;
            }
            if ($level['min_active_paying_churches'] > ($current['min_active_paying_churches'] ?? 0)) {
                $next = $level;
                break;
            }
        }

        if ($next === null) {
            return [
                'current' => $current,
                'next' => null,
                'current_count' => $count,
                'target_count' => null,
                'remaining' => 0,
                'progress_percent' => 100,
            ];
        }

        $base = (int) ($current['min_active_paying_churches'] ?? 0);
        $target = (int) $next['min_active_paying_churches'];
        $span = max(1, $target - $base);
        $achieved = max(0, min($span, $count - $base));
        $percent = (int) round(($achieved / $span) * 100);

        return [
            'current' => $current,
            'next' => $next,
            'current_count' => $count,
            'target_count' => $target,
            'remaining' => max(0, $target - $count),
            'progress_percent' => $percent,
        ];
    }

    /**
     * Risk and review indicator flags derived authoritatively from attribution and ledger states.
     *
     * @return list<array{type: string, severity: 'warning'|'danger'|'info', message: string}>
     */
    public function riskFlags(): array
    {
        $flags = [];

        // 1. Blocked self-referral attempts recorded in attributions
        $blockedCount = $this->attributions()
            ->where('source', 'blocked_self_referral')
            ->count();
        if ($blockedCount > 0) {
            $flags[] = [
                'type' => 'self_referral_attempt',
                'severity' => 'warning',
                'message' => "{$blockedCount} self-referral attempt(s) blocked by system.",
            ];
        }

        // 2. Same controlled organisation check
        if ($this->user_id) {
            $controlledOrgsCount = \App\Models\Organization::query()
                ->whereHas('users', fn ($u) => $u->where('users.id', $this->user_id))
                ->whereHas('affiliateAttribution', fn ($a) => $a->where('affiliate_account_id', $this->id))
                ->count();
            if ($controlledOrgsCount > 0) {
                $flags[] = [
                    'type' => 'controlled_org_referral',
                    'severity' => 'danger',
                    'message' => "Affiliate has role/membership in {$controlledOrgsCount} attributed organisation(s).",
                ];
            }
        }

        // 3. Reversed qualifying payments
        $reversedCommsCount = $this->commissions()
            ->whereIn('status', [
                \App\Enums\AffiliateCommissionStatus::Reversed->value,
                \App\Enums\AffiliateCommissionStatus::Voided->value,
            ])
            ->where('commission_amount_minor', '>', 0)
            ->count();
        if ($reversedCommsCount > 0) {
            $flags[] = [
                'type' => 'reversed_payments',
                'severity' => 'warning',
                'message' => "{$reversedCommsCount} commission(s) were reversed or voided due to refunds/disputes.",
            ];
        }

        // 4. Unusual concentration of referrals
        $totalComms = $this->commissions()->count();
        if ($totalComms >= 5) {
            $topOrgCount = $this->commissions()
                ->selectRaw('organization_id, count(*) as cnt')
                ->groupBy('organization_id')
                ->orderByDesc('cnt')
                ->value('cnt');
            if ($topOrgCount && ($topOrgCount / $totalComms) > 0.85) {
                $flags[] = [
                    'type' => 'referral_concentration',
                    'severity' => 'info',
                    'message' => 'High concentration: '.round(($topOrgCount / $totalComms) * 100).'% of commissions derive from a single organisation.',
                ];
            }
        }

        return $flags;
    }

    public function canRefer(): bool
    {
        return $this->status->canRefer();
    }

    public static function normalizeCode(string $code): string
    {
        return strtolower(trim($code));
    }

    public static function generateReferralCode(): string
    {
        $alphabet = 'abcdefghjkmnpqrstuvwxyz23456789';
        $last = strlen($alphabet) - 1;

        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= $alphabet[random_int(0, $last)];
            }
        } while (static::query()->where('referral_code', $code)->exists());

        return $code;
    }
}
