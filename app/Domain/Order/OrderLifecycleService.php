<?php

declare(strict_types=1);

namespace App\Domain\Order;

use App\Domain\Audit\AuditLogger;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * State transitions on orders after placement. These methods are the ONLY
 * code paths that may change order.status / payment_status / fulfillment_status —
 * controllers and Filament resources must call this service, not write
 * the columns directly. Keeps the audit trail authoritative and lets us
 * enforce transition rules in one place.
 */
class OrderLifecycleService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Called by PaymentService when a payment is captured. Moves the order
     * to paid + sets paid_at, and starts the earnings-release 7-day clock
     * (the actual release happens after delivery — see markDelivered).
     */
    public function markPaid(Order $order, ?User $actor = null): Order
    {
        return DB::transaction(function () use ($order, $actor) {
            if ($order->payment_status === Order::PAY_PAID) {
                return $order; // idempotent
            }
            $order->forceFill([
                'payment_status' => Order::PAY_PAID,
                'paid_at'        => now(),
                // If we were sitting in pending_payment, advance to paid.
                // If admin had already confirmed for some reason, leave that.
                'status' => $order->status === Order::STATUS_PENDING_PAYMENT
                    ? Order::STATUS_PAID
                    : $order->status,
            ])->save();

            $order->events()->create([
                'event_type' => 'paid',
                'message'    => 'Payment captured.',
                'actor_id'   => $actor?->id,
                'actor_role' => $actor ? 'system' : 'system',
            ]);

            $this->audit->log('order.paid', $order, after: ['payment_status' => 'paid']);
            return $order->fresh();
        });
    }

    public function confirm(Order $order, ?User $actor = null): Order
    {
        $order->forceFill([
            'status'        => Order::STATUS_CONFIRMED,
            'confirmed_at'  => now(),
        ])->save();

        $order->events()->create([
            'event_type' => 'confirmed',
            'message'    => 'Order confirmed by admin/vendor.',
            'actor_id'   => $actor?->id,
            'actor_role' => $actor?->hasRole('vendor') ? 'vendor' : 'admin',
        ]);
        $this->audit->log('order.confirmed', $order, after: ['status' => 'confirmed']);
        return $order->fresh();
    }

    /**
     * Marks all items shipped (or a specific vendor's items if $vendorId given).
     * Updates the order-level fulfillment_status by aggregating line states.
     */
    public function markShipped(Order $order, ?int $vendorId = null, ?User $actor = null): Order
    {
        // v5.6 — defensively eager-load items so we never trigger a lazy-load
        // under Model::shouldBeStrict (which is on outside production).
        $order->loadMissing(['items']);

        return DB::transaction(function () use ($order, $vendorId, $actor) {
            $q = $order->items()->where('fulfillment_status', OrderItem::FUL_UNFULFILLED);
            if ($vendorId) {
                $q->where('vendor_id', $vendorId);
            }
            $q->update(['fulfillment_status' => OrderItem::FUL_FULFILLED]);

            $this->refreshFulfillment($order);

            if ($order->fulfillment_status === Order::FUL_FULFILLED && ! $order->shipped_at) {
                $order->forceFill([
                    'status'     => Order::STATUS_SHIPPED,
                    'shipped_at' => now(),
                ])->save();
            }

            $order->events()->create([
                'event_type' => 'shipped',
                'message'    => $vendorId ? "Items for vendor #{$vendorId} shipped." : 'Order shipped.',
                'actor_id'   => $actor?->id,
                'actor_role' => $actor?->hasRole('vendor') ? 'vendor' : 'admin',
            ]);

            $this->audit->log('order.shipped', $order, after: ['status' => $order->status]);
            return $order->fresh();
        });
    }

    /**
     * Marks the order delivered and starts the 7-day earnings-release clock.
     * After this date, the platform releases vendor_earnings to vendor balance.
     * (The actual release run is a future Phase 5 scheduled job — Phase 4 just
     * records the release_at timestamp so the data is ready when that job lands.)
     */
    public function markDelivered(Order $order, ?User $actor = null): Order
    {
        // v5.6 — defensively eager-load items so we never trigger a lazy-load
        // under Model::shouldBeStrict (which is on outside production).
        $order->loadMissing(['items']);

        $order->forceFill([
            'status'              => Order::STATUS_DELIVERED,
            'delivered_at'        => now(),
            'earnings_release_at' => now()->addDays(7),  // Phase 2 locked decision
        ])->save();

        $order->events()->create([
            'event_type' => 'delivered',
            'message'    => 'Order delivered. Vendor earnings release in 7 days.',
            'actor_id'   => $actor?->id,
            'actor_role' => $actor?->hasRole('vendor') ? 'vendor' : 'admin',
        ]);
        $this->audit->log('order.delivered', $order, after: ['status' => 'delivered']);
        return $order->fresh();
    }

    public function complete(Order $order, ?User $actor = null): Order
    {
        $order->forceFill([
            'status'       => Order::STATUS_COMPLETED,
            'completed_at' => now(),
        ])->save();

        $order->events()->create([
            'event_type' => 'completed',
            'message'    => 'Order completed.',
            'actor_id'   => $actor?->id,
            'actor_role' => $actor?->hasRole('vendor') ? 'vendor' : 'admin',
        ]);
        $this->audit->log('order.completed', $order, after: ['status' => 'completed']);
        return $order->fresh();
    }

    public function cancel(Order $order, string $reason, ?User $actor = null): Order
    {
        // v5.6 — defensively eager-load items so we never trigger a lazy-load
        // under Model::shouldBeStrict (which is on outside production).
        // v5.7 — also eager-load items.variant + items.product because the
        // restock loop below accesses both (multi-item cancellations would
        // otherwise crash with the N+1 detector).
        $order->loadMissing(['items.variant', 'items.product']);

        if (in_array($order->status, [Order::STATUS_DELIVERED, Order::STATUS_COMPLETED], true)) {
            throw new RuntimeException('Cannot cancel a delivered/completed order — issue a refund instead.');
        }

        return DB::transaction(function () use ($order, $reason, $actor) {
            // Restock anything we'd decremented at place time
            foreach ($order->items as $item) {
                if ($item->variant_id && $item->variant) {
                    $item->variant->increment('stock', $item->quantity);
                } elseif ($item->product && $item->product->track_stock) {
                    $item->product->increment('stock', $item->quantity);
                }
            }

            $order->forceFill([
                'status'              => Order::STATUS_CANCELLED,
                'cancelled_at'        => now(),
                'cancellation_reason' => $reason,
            ])->save();

            $order->events()->create([
                'event_type' => 'cancelled',
                'message'    => 'Order cancelled: ' . $reason,
                'actor_id'   => $actor?->id,
                'actor_role' => $actor
                    ? ($actor->hasRole('vendor') ? 'vendor' : ($actor->id === $order->user_id ? 'customer' : 'admin'))
                    : 'system',
            ]);

            $this->audit->log('order.cancelled', $order, after: ['status' => 'cancelled', 'reason' => $reason]);
            return $order->fresh();
        });
    }

    private function refreshFulfillment(Order $order): void
    {
        // Phase 9 v9.4 — force-reload, not loadMissing.
        //
        // CHANGED FROM `loadMissing` to `load` because the caller chain
        // (markShipped / markDelivered / markPaid) eager-loads items at
        // the top, then runs an in-place DB mass-update like:
        //   $order->items()->where(...)->update(['fulfillment_status' => ...]);
        // After that mass-update, $order->items in memory still has the
        // OLD statuses. loadMissing() is a no-op (the relation is already
        // loaded), so pluck('fulfillment_status') below would read stale
        // values → the aggregate fulfillment_status would lag one transition
        // behind reality on multi-item orders.
        //
        // load() (without "Missing") forces a re-query and replaces the
        // collection with fresh rows.
        $order->load('items');
        $statuses = $order->items->pluck('fulfillment_status')->unique();

        $fulfillment = match (true) {
            $statuses->every(fn ($s) => $s === OrderItem::FUL_FULFILLED) => Order::FUL_FULFILLED,
            $statuses->contains(OrderItem::FUL_FULFILLED)                => Order::FUL_PARTIAL,
            default                                                       => Order::FUL_UNFULFILLED,
        };

        $order->forceFill(['fulfillment_status' => $fulfillment])->save();
    }
}
