<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case PastDue = 'past_due';
    case Void = 'void';
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Paid, self::Void, self::Cancelled => true,
            default => false,
        };
    }

    public function allowsPayment(): bool
    {
        return match ($this) {
            self::Open, self::PartiallyPaid, self::PastDue => true,
            default => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Open => 'Open',
            self::PartiallyPaid => 'Partially Paid',
            self::Paid => 'Paid',
            self::PastDue => 'Past Due',
            self::Void => 'Void',
            self::Cancelled => 'Cancelled',
        };
    }

    public static function fromLegacy(string|self|null $status): self
    {
        if ($status instanceof self) {
            return $status;
        }

        if ($status === null || $status === '') {
            return self::Open;
        }

        return match (strtolower(trim($status))) {
            'draft' => self::Draft,
            'open', 'pending' => self::Open,
            'partially_paid', 'partial' => self::PartiallyPaid,
            'paid' => self::Paid,
            'past_due', 'overdue' => self::PastDue,
            'void', 'voided' => self::Void,
            'cancelled', 'canceled' => self::Cancelled,
            default => self::Open,
        };
    }
}
