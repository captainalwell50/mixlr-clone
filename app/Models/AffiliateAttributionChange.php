<?php

namespace App\Models;

use App\Enums\AffiliateAttributionSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateAttributionChange extends Model
{
    protected $fillable = [
        'organization_id',
        'previous_affiliate_account_id',
        'new_affiliate_account_id',
        'previous_source',
        'new_source',
        'changed_by',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'previous_source' => AffiliateAttributionSource::class,
            'new_source' => AffiliateAttributionSource::class,
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function previousAffiliate(): BelongsTo
    {
        return $this->belongsTo(AffiliateAccount::class, 'previous_affiliate_account_id');
    }

    public function newAffiliate(): BelongsTo
    {
        return $this->belongsTo(AffiliateAccount::class, 'new_affiliate_account_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
