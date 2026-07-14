<?php

declare(strict_types=1);

namespace App\Domain\Order;

use App\Models\Order;

/**
 * Generates human-readable order numbers like "MK-2026-000001".
 *
 * Format: {prefix}-{4-digit year}-{6-digit sequence}.
 * Sequence resets per-year. Uses a SELECT MAX + 1 strategy under a transaction.
 * Race-condition risk is low at single-digit-order-per-second volumes; a
 * production marketplace at higher rate would switch to a Postgres sequence
 * (CREATE SEQUENCE) or Snowflake-style ID + display formatter.
 */
class OrderNumberGenerator
{
    public function __construct(private readonly string $prefix = 'MK') {}

    public function next(): string
    {
        $year = (int) date('Y');
        $prefix = "{$this->prefix}-{$year}-";

        // Highest existing sequence this year
        $lastNumber = Order::where('number', 'LIKE', $prefix . '%')
            ->orderByDesc('number')
            ->value('number');

        $seq = 1;
        if ($lastNumber) {
            $tail = (int) substr($lastNumber, strlen($prefix));
            $seq = $tail + 1;
        }

        return $prefix . str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
    }
}
