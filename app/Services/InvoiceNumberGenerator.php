<?php

namespace App\Services;

use App\Models\InvoiceSequence;
use Illuminate\Support\Facades\DB;

class InvoiceNumberGenerator
{
    /**
     * Generate the next sequential invoice number in the format SM-YYYY-NNNNNN.
     *
     * Concurrency-safe across SQLite and client-server databases by executing
     * an atomic sequence increment inside an exclusive transaction.
     */
    public static function next(?int $year = null): string
    {
        $targetYear = $year ?? (int) now()->format('Y');

        return DB::transaction(function () use ($targetYear): string {
            // Ensure sequence row exists for the target year
            InvoiceSequence::query()->firstOrCreate(
                ['year' => $targetYear],
                ['last_number' => 0]
            );

            // Atomically increment the sequence counter
            DB::table('invoice_sequences')
                ->where('year', $targetYear)
                ->increment('last_number');

            $currentNumber = (int) DB::table('invoice_sequences')
                ->where('year', $targetYear)
                ->value('last_number');

            return sprintf('SM-%04d-%06d', $targetYear, $currentNumber);
        });
    }
}
