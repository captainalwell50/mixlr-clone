<?php

namespace App\Exceptions;

use App\Enums\InvoiceStatus;
use DomainException;

class InvalidInvoiceStateTransitionException extends DomainException
{
    public function __construct(
        public readonly InvoiceStatus|string $from,
        public readonly InvoiceStatus|string $to,
        string $message = '',
    ) {
        $fromStr = $from instanceof InvoiceStatus ? $from->value : (string) $from;
        $toStr = $to instanceof InvoiceStatus ? $to->value : (string) $to;

        $msg = $message !== ''
            ? $message
            : "Cannot transition invoice from '{$fromStr}' to '{$toStr}'.";

        parent::__construct($msg);
    }
}
