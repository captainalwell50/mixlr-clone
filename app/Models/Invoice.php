<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Exceptions\InvalidInvoiceStateTransitionException;
use App\Services\InvoiceNumberGenerator;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Invoice extends Model
{
    use HasFactory;

    // Legacy status constants preserved for backwards compatibility
    public const STATUS_PENDING = 'pending';
    public const STATUS_OPEN = 'open';
    public const STATUS_PAID = 'paid';
    public const STATUS_PARTIALLY_PAID = 'partially_paid';
    public const STATUS_PAST_DUE = 'past_due';
    public const STATUS_VOID = 'void';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Permitted state transitions between statuses.
     * Terminal states (paid, void, cancelled) have an empty target set.
     */
    public const VALID_TRANSITIONS = [
        InvoiceStatus::Draft->value => [
            InvoiceStatus::Open->value,
        ],
        InvoiceStatus::Open->value => [
            InvoiceStatus::PartiallyPaid->value,
            InvoiceStatus::Paid->value,
            InvoiceStatus::PastDue->value,
            InvoiceStatus::Void->value,
            InvoiceStatus::Cancelled->value,
        ],
        InvoiceStatus::PartiallyPaid->value => [
            InvoiceStatus::Paid->value,
            InvoiceStatus::PastDue->value,
        ],
        InvoiceStatus::PastDue->value => [
            InvoiceStatus::Paid->value,
            InvoiceStatus::Void->value,
            InvoiceStatus::Cancelled->value,
        ],
        InvoiceStatus::Paid->value => [],
        InvoiceStatus::Void->value => [],
        InvoiceStatus::Cancelled->value => [],
    ];

    protected $fillable = [
        'organization_id',
        'subscription_id',
        'plan_id',
        'invoice_number',
        'billing_period_start',
        'billing_period_end',
        'currency',
        'subtotal',
        'discount_total',
        'tax_total',
        'total',
        'amount_paid',
        'amount_due',
        'amount', // legacy alias for total
        'status',
        'issued_at',
        'due_at',
        'due_date', // legacy alias for due_at
        'paid_at',
        'voided_at',
        'payment_reference',
        'payment_link',
        'notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'billing_period_start' => 'date',
            'billing_period_end' => 'date',
            'subtotal' => 'integer',
            'discount_total' => 'integer',
            'tax_total' => 'integer',
            'total' => 'integer',
            'amount_paid' => 'integer',
            'amount_due' => 'integer',
            'amount' => 'integer',
            'issued_at' => 'datetime',
            'due_at' => 'datetime',
            'due_date' => 'datetime',
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    public function invoiceLines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    public function paymentAttempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->whereIn('status', [
            InvoiceStatus::Open->value,
            'pending',
            InvoiceStatus::PartiallyPaid->value,
            InvoiceStatus::PastDue->value,
        ]);
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', InvoiceStatus::Paid->value);
    }

    // -------------------------------------------------------------------------
    // Status & State Machine
    // -------------------------------------------------------------------------

    public function getStatusAttribute(): string
    {
        return (string) ($this->attributes['status'] ?? InvoiceStatus::Open->value);
    }

    public function setStatusAttribute(InvoiceStatus|string $value): void
    {
        $enum = InvoiceStatus::fromLegacy($value);
        $this->attributes['status'] = $enum->value;
    }

    public function getStatusEnumAttribute(): InvoiceStatus
    {
        return InvoiceStatus::fromLegacy($this->attributes['status'] ?? null);
    }

    public function canTransitionTo(InvoiceStatus|string $target): bool
    {
        $to = InvoiceStatus::fromLegacy($target);
        $from = $this->statusEnum;

        if ($from === $to) {
            return true;
        }

        $allowed = self::VALID_TRANSITIONS[$from->value] ?? [];

        return in_array($to->value, $allowed, true);
    }

    public function transitionTo(InvoiceStatus|string $target, ?string $reason = null): self
    {
        $to = InvoiceStatus::fromLegacy($target);
        $from = $this->statusEnum;

        if ($from === $to) {
            return $this;
        }

        if (! $this->canTransitionTo($to)) {
            throw new InvalidInvoiceStateTransitionException($from, $to);
        }

        $this->status = $to;

        if ($to === InvoiceStatus::Open && $this->issued_at === null) {
            $this->issued_at = now();
        } elseif ($to === InvoiceStatus::Paid) {
            $this->paid_at = now();
            if ($this->amount_paid < $this->total) {
                $this->amount_paid = $this->total;
            }
            $this->amount_due = 0;
        } elseif ($to === InvoiceStatus::Void) {
            $this->voided_at = now();
            if ($reason !== null) {
                $meta = $this->metadata ?? [];
                $meta['void_reason'] = $reason;
                $this->metadata = $meta;
            }
        } elseif ($to === InvoiceStatus::Cancelled && $reason !== null) {
            $meta = $this->metadata ?? [];
            $meta['cancellation_reason'] = $reason;
            $this->metadata = $meta;
        }

        $this->save();

        return $this;
    }

    public function issue(): self
    {
        return $this->transitionTo(InvoiceStatus::Open);
    }

    public function markPartiallyPaid(): self
    {
        return $this->transitionTo(InvoiceStatus::PartiallyPaid);
    }

    public function markPastDue(): self
    {
        return $this->transitionTo(InvoiceStatus::PastDue);
    }

    public function void(?string $reason = null): self
    {
        return $this->transitionTo(InvoiceStatus::Void, $reason);
    }

    public function cancel(?string $reason = null): self
    {
        return $this->transitionTo(InvoiceStatus::Cancelled, $reason);
    }

    public function markPaid(?string $reference = null): self
    {
        if ($reference !== null) {
            $this->payment_reference = $reference;
        }

        return $this->transitionTo(InvoiceStatus::Paid);
    }

    public function isPaid(): bool
    {
        return $this->statusEnum === InvoiceStatus::Paid;
    }

    public function isOpen(): bool
    {
        return $this->statusEnum === InvoiceStatus::Open;
    }

    public function isTerminal(): bool
    {
        return $this->statusEnum->isTerminal();
    }

    public function isOverdue(): bool
    {
        if ($this->isPaid() || $this->statusEnum->isTerminal()) {
            return false;
        }

        $due = $this->due_at ?? $this->due_date;

        return $due !== null && $due->isPast();
    }

    // -------------------------------------------------------------------------
    // Balance Invariants & Math
    // -------------------------------------------------------------------------

    public function recalculateTotals(): self
    {
        if ($this->relationLoaded('lines') || $this->lines()->exists()) {
            $linesTotal = (int) $this->lines()->sum('total');
            if ($linesTotal > 0 || $this->lines()->count() > 0) {
                $this->subtotal = $linesTotal;
            }
        }

        $this->subtotal = max(0, (int) $this->subtotal);
        $this->discount_total = max(0, (int) $this->discount_total);
        $this->tax_total = max(0, (int) $this->tax_total);

        $this->total = max(0, $this->subtotal - $this->discount_total + $this->tax_total);
        $this->amount = $this->total;

        if ($this->relationLoaded('allocations') || $this->allocations()->exists()) {
            $allocated = (int) $this->allocations()->sum('amount');
            if ($allocated > 0) {
                $this->amount_paid = $allocated;
            }
        }

        $this->amount_paid = max(0, (int) $this->amount_paid);
        $this->amount_due = max(0, $this->total - $this->amount_paid);

        return $this;
    }

    public function assertBalanceInvariants(): void
    {
        if ($this->subtotal < 0) {
            throw new DomainException("Invoice subtotal cannot be negative ({$this->subtotal}).");
        }
        if ($this->discount_total < 0) {
            throw new DomainException("Invoice discount_total cannot be negative ({$this->discount_total}).");
        }
        if ($this->tax_total < 0) {
            throw new DomainException("Invoice tax_total cannot be negative ({$this->tax_total}).");
        }
        if ($this->total < 0) {
            throw new DomainException("Invoice total cannot be negative ({$this->total}).");
        }
        if ($this->amount_paid < 0) {
            throw new DomainException("Invoice amount_paid cannot be negative ({$this->amount_paid}).");
        }
        if ($this->amount_due < 0) {
            throw new DomainException("Invoice amount_due cannot be negative ({$this->amount_due}).");
        }

        $expectedTotal = max(0, $this->subtotal - $this->discount_total + $this->tax_total);
        if ($this->total !== $expectedTotal) {
            throw new DomainException("Invoice total mismatch: expected {$expectedTotal}, got {$this->total}.");
        }

        $expectedDue = max(0, $this->total - $this->amount_paid);
        if ($this->amount_due !== $expectedDue) {
            throw new DomainException("Invoice amount_due mismatch: expected {$expectedDue}, got {$this->amount_due}.");
        }
    }

    // -------------------------------------------------------------------------
    // Legacy Two-Way Accessors & Mutators
    // -------------------------------------------------------------------------

    public function getAmountAttribute(): int
    {
        return (int) ($this->attributes['total'] ?? $this->attributes['amount'] ?? 0);
    }

    public function setAmountAttribute(int $value): void
    {
        $this->attributes['amount'] = $value;
        if (! isset($this->attributes['total']) || $this->attributes['total'] === 0) {
            $this->attributes['total'] = $value;
        }
    }

    public function getTotalAttribute(): int
    {
        return (int) ($this->attributes['total'] ?? $this->attributes['amount'] ?? 0);
    }

    public function setTotalAttribute(int $value): void
    {
        $this->attributes['total'] = $value;
        $this->attributes['amount'] = $value;
    }

    public function getDueDateAttribute(): ?Carbon
    {
        $val = $this->attributes['due_at'] ?? $this->attributes['due_date'] ?? null;

        return $val ? Carbon::parse($val) : null;
    }

    public function setDueDateAttribute(mixed $value): void
    {
        $parsed = $value ? Carbon::parse($value) : null;
        $this->attributes['due_date'] = $parsed;
        $this->attributes['due_at'] = $parsed;
    }

    public function getDueAtAttribute(): ?Carbon
    {
        $val = $this->attributes['due_at'] ?? $this->attributes['due_date'] ?? null;

        return $val ? Carbon::parse($val) : null;
    }

    public function setDueAtAttribute(mixed $value): void
    {
        $parsed = $value ? Carbon::parse($value) : null;
        $this->attributes['due_at'] = $parsed;
        $this->attributes['due_date'] = $parsed;
    }

    // -------------------------------------------------------------------------
    // Model Lifecycle Events
    // -------------------------------------------------------------------------

    protected static function booted(): void
    {
        static::creating(function (Invoice $invoice): void {
            if (empty($invoice->invoice_number)) {
                $year = $invoice->issued_at?->year
                    ?? $invoice->created_at?->year
                    ?? (int) now()->format('Y');
                $invoice->invoice_number = InvoiceNumberGenerator::next($year);
            }

            if ($invoice->due_at === null && $invoice->due_date !== null) {
                $invoice->due_at = $invoice->due_date;
            } elseif ($invoice->due_date === null && $invoice->due_at !== null) {
                $invoice->due_date = $invoice->due_at;
            } elseif ($invoice->due_at === null && $invoice->due_date === null) {
                $invoice->due_at = now()->addDays(7);
                $invoice->due_date = $invoice->due_at;
            }

            if (! isset($invoice->attributes['total'])) {
                $invoice->attributes['total'] = isset($invoice->attributes['amount']) ? (int) $invoice->attributes['amount'] : 0;
            }
            if (! isset($invoice->attributes['amount'])) {
                $invoice->attributes['amount'] = (int) $invoice->attributes['total'];
            }

            if ($invoice->subtotal === 0 && $invoice->total > 0) {
                $invoice->subtotal = $invoice->total;
            } elseif ($invoice->total === 0 && $invoice->subtotal > 0) {
                $invoice->total = max(0, $invoice->subtotal - $invoice->discount_total + $invoice->tax_total);
                $invoice->amount = $invoice->total;
            }

            if ($invoice->amount_due === 0 && $invoice->total > 0 && $invoice->amount_paid === 0) {
                $invoice->amount_due = $invoice->total;
            }
        });

        static::saving(function (Invoice $invoice): void {
            if ($invoice->isDirty('total') && ! $invoice->isDirty('amount')) {
                $invoice->amount = $invoice->total;
            } elseif ($invoice->isDirty('amount') && ! $invoice->isDirty('total')) {
                $invoice->total = $invoice->amount;
            }

            if ($invoice->isDirty('due_at') && ! $invoice->isDirty('due_date')) {
                $invoice->due_date = $invoice->due_at;
            } elseif ($invoice->isDirty('due_date') && ! $invoice->isDirty('due_at')) {
                $invoice->due_at = $invoice->due_date;
            }
        });
    }
}
