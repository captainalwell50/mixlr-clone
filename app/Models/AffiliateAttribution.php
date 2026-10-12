<?php

namespace App\Models;

use App\Enums\AffiliateAttributionSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateAttribution extends Model
{
    protected $fillable = [
        'organization_id',
        'affiliate_account_id',
        'referral_visit_id',
        'source',
        'attributed_at',
    ];

    protected function casts(): array
    {
        return [
            'source' => AffiliateAttributionSource::class,
            'attributed_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(AffiliateAccount::class, 'affiliate_account_id');
    }

    public function affiliateAccount(): BelongsTo
    {
        return $this->belongsTo(AffiliateAccount::class, 'affiliate_account_id');
    }

    public function referralVisit(): BelongsTo
    {
        return $this->belongsTo(AffiliateReferralVisit::class, 'referral_visit_id');
    }

    public function isEarningEligible(): bool
    {
        return $this->affiliate_account_id !== null && $this->source->isEarningEligible();
    }
}
