<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateReferralVisit extends Model
{
    public const COOKIE = 'sm_aff_ref';

    protected $fillable = [
        'affiliate_account_id',
        'referral_code',
        'token',
        'first_seen_at',
        'expires_at',
        'organization_id',
        'converted_at',
        'ip_hash',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'expires_at' => 'datetime',
            'converted_at' => 'datetime',
        ];
    }

    public function affiliateAccount(): BelongsTo
    {
        return $this->belongsTo(AffiliateAccount::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function isOpen(): bool
    {
        return $this->converted_at === null
            && $this->organization_id === null
            && $this->expires_at->isFuture();
    }
}
