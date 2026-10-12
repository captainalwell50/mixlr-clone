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
        'approved_at',
        'suspended_at',
        'payout_qualified_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => AffiliateStatus::class,
            'approved_at' => 'datetime',
            'suspended_at' => 'datetime',
            'payout_qualified_at' => 'datetime',
        ];
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
