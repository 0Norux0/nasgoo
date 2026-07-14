<?php

declare(strict_types=1);

namespace App\Domain\Supplier;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SupplierOrder;
use App\Models\SupplierOrderEvent;

/**
 * Phase 6 — DropshipOrderCreator.
 *
 * Called from CheckoutService after the Order + OrderItems are persisted.
 * For every order line that's a dropshipping product, creates a SupplierOrder
 * in `pending` status with a snapshotted supplier cost. The vendor or admin
 * then manually marks the supplier order placed → shipped → delivered as
 * they work with the upstream supplier.
 *
 * One supplier_order per (vendor, supplier_platform, order_id) combination —
 * multiple line items going to the same supplier roll into one supplier_order.
 */
class DropshipOrderCreator
{
    public function createFromOrder(Order $order): array
    {
        $created = [];
        $order->loadMissing(['items.product.supplierPlatform', 'items.customizations']);

        // Group dropship items by (vendor_id, supplier_platform_id)
        $groups = [];
        foreach ($order->items as $item) {
            $product = $item->product;
            if (! $product || ! $product->isDropship()) {
                continue;
            }
            $key = $product->vendor_id . ':' . ($product->supplier_platform_id ?? '0');
            $groups[$key][] = $item;
        }

        foreach ($groups as $items) {
            /** @var OrderItem $firstItem */
            $firstItem = $items[0];
            $product = $firstItem->product;

            $costMinor = 0;
            $shippingMinor = 0;
            foreach ($items as $it) {
                $costMinor += (int) ($it->supplier_cost_minor ?? ($it->product?->supplier_cost_minor ?? 0)) * (int) $it->quantity;
            }

            $supplierOrder = SupplierOrder::create([
                'number'                  => $this->generateNumber(),
                'vendor_id'               => $product->vendor_id,
                'supplier_platform_id'    => $product->supplier_platform_id,
                'order_id'                => $order->id,
                'supplier_product_id'     => $product->supplier_product_id,
                'status'                  => SupplierOrder::STATUS_PENDING,
                'supplier_cost_minor'     => $costMinor,
                'supplier_shipping_minor' => $shippingMinor,
                'total_minor'             => $costMinor + $shippingMinor,
                'currency'                => $order->currency,
            ]);

            // Link each item back to the supplier order.
            foreach ($items as $it) {
                $it->update(['supplier_order_id' => $supplierOrder->id]);
            }

            SupplierOrderEvent::create([
                'supplier_order_id' => $supplierOrder->id,
                'actor_role'        => 'system',
                'event_type'        => 'supplier_order.created',
                'message'           => "Auto-created from order #{$order->number}.",
            ]);

            $created[] = $supplierOrder;
        }

        return $created;
    }

    public function transition(SupplierOrder $so, string $newStatus, ?int $actorId = null, ?string $actorRole = null, ?string $note = null): SupplierOrder
    {
        if (! in_array($newStatus, SupplierOrder::ALL_STATUSES, true)) {
            throw new \InvalidArgumentException("Unknown supplier order status: {$newStatus}");
        }
        $updates = ['status' => $newStatus];
        $now = now();
        if ($newStatus === SupplierOrder::STATUS_PLACED && ! $so->placed_at)      { $updates['placed_at'] = $now; }
        if ($newStatus === SupplierOrder::STATUS_SHIPPED && ! $so->shipped_at)    { $updates['shipped_at'] = $now; }
        if ($newStatus === SupplierOrder::STATUS_DELIVERED && ! $so->delivered_at){ $updates['delivered_at'] = $now; }
        if ($newStatus === SupplierOrder::STATUS_CANCELLED && ! $so->cancelled_at){ $updates['cancelled_at'] = $now; }
        $so->update($updates);

        SupplierOrderEvent::create([
            'supplier_order_id' => $so->id,
            'actor_id'          => $actorId,
            'actor_role'        => $actorRole,
            'event_type'        => 'status.' . $newStatus,
            'message'           => $note,
        ]);

        return $so->fresh();
    }

    private function generateNumber(): string
    {
        return 'SUP-' . now()->format('Ym') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    }
}
