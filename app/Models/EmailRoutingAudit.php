<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailRoutingAudit extends Model
{
    public const ACTION_DESTINATION_PROPOSED = 'destination_proposed';
    public const ACTION_VERIFICATION_REQUESTED = 'verification_requested';
    public const ACTION_VERIFICATION_COMPLETED = 'verification_completed';
    public const ACTION_ROUTING_SWITCHED = 'routing_switched';
    public const ACTION_SYNC_FAILED = 'sync_failed';
    public const ACTION_PENDING_CANCELLED = 'pending_cancelled';

    protected $fillable = [
        'actor_id',
        'action',
        'previous_destination',
        'proposed_destination',
        'status',
        'details',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Safely record an audit event without secrets.
     */
    public static function record(
        string $action,
        ?string $previousDestination,
        ?string $proposedDestination,
        ?int $actorId = null,
        string $status = 'success',
        array $details = []
    ): self {
        return static::create([
            'actor_id' => $actorId,
            'action' => $action,
            'previous_destination' => $previousDestination,
            'proposed_destination' => $proposedDestination,
            'status' => $status,
            'details' => $details,
        ]);
    }
}
