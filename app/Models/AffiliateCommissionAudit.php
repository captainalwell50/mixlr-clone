<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateCommissionAudit extends Model
{
    protected $fillable = [
        'affiliate_commission_id',
        'affiliate_payout_id',
        'affiliate_account_id',
        'action',
        'actor_id',
        'from_status',
        'to_status',
        'amount_minor',
        'currency',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
        ];
    }

    public function commission(): BelongsTo
    {
        return $this->belongsTo(AffiliateCommission::class, 'affiliate_commission_id');
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(AffiliatePayout::class, 'affiliate_payout_id');
    }

    public function affiliateAccount(): BelongsTo
    {
        return $this->belongsTo(AffiliateAccount::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
