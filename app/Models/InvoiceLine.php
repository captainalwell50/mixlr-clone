<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'description',
        'quantity',
        'unit_amount',
        'subtotal',
        'total',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_amount' => 'integer',
            'subtotal' => 'integer',
            'total' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Calculate and sync line subtotal and total from quantity and unit amount.
     */
    public function calculateTotals(): self
    {
        $this->quantity = max(1, (int) ($this->quantity ?: 1));
        $this->unit_amount = max(0, (int) $this->unit_amount);
        $this->subtotal = $this->quantity * $this->unit_amount;
        $this->total = $this->subtotal;

        return $this;
    }

    protected static function booted(): void
    {
        static::saving(function (InvoiceLine $line): void {
            if ($line->subtotal === null || $line->subtotal === 0) {
                $line->calculateTotals();
            }
        });
    }
}
