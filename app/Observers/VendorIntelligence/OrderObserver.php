<?php

declare(strict_types=1);

namespace App\Observers\VendorIntelligence;

use App\Models\Order;
use App\Services\VendorIntelligence\VendorIntelligenceManager;

/**
 * Phase 11B.4 v11B.4.2 Defect 11 fix — mark vendor intelligence stale
 * when an order transitions through completion / cancellation /
 * refund. All three affect the "recent orders" signal used by the
 * fast-moving and slow-moving alerts.
 *
 * Orders touch multiple vendors (one per order item), so we mark
 * ALL affected vendors stale, not just one.
 */
class OrderObserver
{
    public function __construct(
        private readonly VendorIntelligenceManager $manager,
    ) {}

    public function updated(Order $order): void
    {
        // Only care about status transitions
        $dirty = $order->getChanges();
        if (! isset($dirty['status']) && ! isset($dirty['payment_status'])) {
            return;
        }

        // Find every vendor whose products were in this order
        $vendorIds = $order->items()->pluck('vendor_id')->unique()->all();
        $reason = 'order_status:' . ($order->status ?? '?');
        foreach ($vendorIds as $vid) {
            $this->manager->markVendorStale((int) $vid, $reason);
        }
    }
}
