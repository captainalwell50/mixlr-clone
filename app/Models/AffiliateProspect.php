<?php

namespace App\Models;

use App\Enums\AffiliateOutreachMethod;
use App\Enums\AffiliateProspectStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AffiliateProspect extends Model
{
    protected $fillable = [
        'affiliate_account_id',
        'outreach_method',
        'church_name',
        'normalized_church_name',
        'country_code',
        'state_region',
        'city',
        'address',
        'email',
        'phone',
        'website',
        'contact_name',
        'contact_role',
        'notes',
        'status',
        'planned_for',
        'reserved_at',
        'reserved_until',
        'active_reservation_key',
        'converted_organization_id',
        'contacted_at',
        'converted_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'outreach_method' => AffiliateOutreachMethod::class,
            'status' => AffiliateProspectStatus::class,
            'planned_for' => 'datetime',
            'reserved_at' => 'datetime',
            'reserved_until' => 'datetime',
            'contacted_at' => 'datetime',
            'converted_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function affiliateAccount(): BelongsTo
    {
        return $this->belongsTo(AffiliateAccount::class);
    }

    public function convertedOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'converted_organization_id');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(AffiliateProspectAudit::class);
    }

    public function holdsReservation(): bool
    {
        return $this->status->isReservationBearing() && $this->reserved_until->isFuture();
    }

    public function daysRemaining(): int
    {
        $seconds = $this->reserved_until->getTimestamp() - now()->getTimestamp();
        if ($seconds <= 0) {
            return 0;
        }

        return (int) ceil($seconds / 86400);
    }

    /**
     * @param  Builder<AffiliateProspect>  $query
     * @return Builder<AffiliateProspect>
     */
    public function scopeHoldingReservation(Builder $query): Builder
    {
        return $query
            ->whereIn('status', AffiliateProspectStatus::reservationBearingValues())
            ->where('reserved_until', '>', now());
    }
}
