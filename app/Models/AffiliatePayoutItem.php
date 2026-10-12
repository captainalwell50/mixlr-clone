<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliatePayoutItem extends Model
{
    protected $fillable = [
        'affiliate_payout_id',
        'affiliate_commission_id',
        'amount_minor',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
        ];
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(AffiliatePayout::class, 'affiliate_payout_id');
    }

    public function commission(): BelongsTo
    {
        return $this->belongsTo(AffiliateCommission::class, 'affiliate_commission_id');
    }
}
