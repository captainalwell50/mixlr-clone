<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Payment extends Model
{
    use HasFactory;

    public const STATUS_SUCCEEDED = 'succeeded';

    protected $fillable = [
        'organization_id',
        'payment_attempt_id',
        'reference',
        'provider',
        'provider_reference',
        'amount',
        'currency',
        'status',
        'confirmed_at',
        'provider_payload',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'confirmed_at' => 'datetime',
            'provider_payload' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function paymentAttempt(): BelongsTo
    {
        return $this->belongsTo(PaymentAttempt::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function invoices(): HasManyThrough
    {
        return $this->hasManyThrough(
            Invoice::class,
            PaymentAllocation::class,
            'payment_id', // Foreign key on payment_allocations table...
            'id',         // Foreign key on invoices table...
            'id',         // Local key on payments table...
            'invoice_id'  // Local key on payment_allocations table...
        );
    }

    public function isSucceeded(): bool
    {
        return $this->status === self::STATUS_SUCCEEDED;
    }

    public function allocatedAmount(): int
    {
        return (int) $this->allocations()->sum('amount');
    }

    public function unallocatedAmount(): int
    {
        return max(0, $this->amount - $this->allocatedAmount());
    }

    public function isFullyAllocated(): bool
    {
        return $this->unallocatedAmount() === 0;
    }
}
