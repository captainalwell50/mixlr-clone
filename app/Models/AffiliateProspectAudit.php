<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateProspectAudit extends Model
{
    protected $fillable = [
        'affiliate_prospect_id',
        'action',
        'reason',
        'actor_id',
    ];

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(AffiliateProspect::class, 'affiliate_prospect_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
