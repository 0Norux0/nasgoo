<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RecommendationEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Phase 11B.2 v11B.2.1 §3 — purchase-attribution job.
 *
 * Server-side attribution per dev §3 — "Do not trust the frontend to declare
 * a purchase. Use the actual order lifecycle."
 *
 * Triggered by AppServiceProvider's Order::saved observer when the order
 * status transitions INTO a qualifying status. Runs as a queued job so it
 * never blocks the order-confirmation request (dev §10 — "purchase attribution
 * should be queued or lightweight").
 *
 * Algorithm:
 *   For each OrderItem in the order:
 *     Find the most recent matching pre-purchase event for the user where:
 *       event_type IN ('click','add_to_cart')
 *       recommended_product_id = item.product_id
 *       user_id = order.user_id
 *       created_at >= NOW - attribution_window_days
 *     If found, insertOrIgnore a 'purchase' event with the SAME (product_id,
 *     recommendation_type, locale) as the matched pre-purchase event, and
 *     order_item_id = item.id.
 *
 *   The (order_item_id, event_type, product_id, recommendation_type)
 *   uniqueness constraint guarantees idempotency: re-dispatching this job
 *   on a subsequent status transition produces zero additional rows.
 *
 * Attribution rule (per dev §3.4): "most recent qualifying recommendation"
 *   — last-touch attribution. Documented in
 *   PHASE_11B_2_1_RUNTIME_ATTRIBUTION_CACHE_FLAGS_REPAIR.md.
 *
 * Refund / cancellation rule (per dev §3.5): when the order transitions to
 * STATUS_REFUNDED or STATUS_CANCELLED, the existing purchase events for that
 * order's items are MARKED reversed (reversed_at = NOW) — they remain in the
 * table for gross-vs-net reporting. The analytics dashboard counts only
 * `reversed_at IS NULL` as "net conversions".
 */
class RecordPurchaseAttributionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $orderId) {}

    public function handle(): void
    {
        $order = Order::with('items')->find($this->orderId);
        if (! $order) {
            return;
        }

        // Qualifying statuses: order has reached a state where revenue is
        // attributable. These match Phase 11B.2 §8's qualifying statuses
        // EXACTLY so analytics behaves consistently with FBT/co-occurrence.
        $qualifying = [
            Order::STATUS_PAID,
            Order::STATUS_CONFIRMED,
            Order::STATUS_SHIPPED,
            Order::STATUS_DELIVERED,
            Order::STATUS_COMPLETED,
        ];
        // Reversal states
        $reversing  = [Order::STATUS_REFUNDED, Order::STATUS_CANCELLED];

        if (in_array($order->status, $reversing, true)) {
            $this->reverseAttributions($order);
            return;
        }

        if (! in_array($order->status, $qualifying, true)) {
            return;
        }

        $windowDays = (int) config('marketplace_recommendations.analytics.attribution_window_days', 7);
        $since = now()->subDays(max(1, $windowDays));

        foreach ($order->items as $item) {
            /** @var OrderItem $item */
            if (! $order->user_id) {
                // Guest checkout — cannot link to a recommendation_event.
                // Skip silently per dev §3.6 ("no customer PII").
                continue;
            }

            // Find the most recent pre-purchase event for this user that
            // matches this product. "Most recent qualifying recommendation"
            // = last-touch attribution.
            $match = RecommendationEvent::query()
                ->where('user_id', $order->user_id)
                ->where('recommended_product_id', $item->product_id)
                ->whereIn('event_type', [
                    RecommendationEvent::TYPE_CLICK,
                    RecommendationEvent::TYPE_ADD_TO_CART,
                ])
                ->where('created_at', '>=', $since)
                ->orderByDesc('created_at')
                ->first();

            if (! $match) {
                continue;  // not attributed — no pre-purchase event in window
            }

            // Idempotency: unique key on (order_item_id, event_type='purchase',
            // product_id, recommendation_type) prevents double-counting.
            try {
                RecommendationEvent::query()->insertOrIgnore([
                    'event_type'             => RecommendationEvent::TYPE_PURCHASE,
                    'product_id'             => $match->product_id,
                    'recommended_product_id' => $item->product_id,
                    'recommendation_type'    => $match->recommendation_type,
                    'locale'                 => $match->locale,
                    'device_category'        => $match->device_category,
                    'session_token'          => $match->session_token,
                    'user_id'                => $order->user_id,
                    'order_item_id'          => $item->id,
                    'created_at'             => now(),
                    'updated_at'             => now(),
                ]);
            } catch (\Throwable $e) {
                Log::warning('v11B.2.1 purchase attribution insert failed', [
                    'order'   => $order->id,
                    'item'    => $item->id,
                    'product' => $item->product_id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Mark all purchase events for this order's items as reversed. Per dev
     * §3.5 — the rows are preserved for gross-vs-net reporting; the analytics
     * dashboard filters `reversed_at IS NULL` for net conversions.
     */
    private function reverseAttributions(Order $order): void
    {
        $itemIds = $order->items->pluck('id')->all();
        if (empty($itemIds)) {
            return;
        }
        RecommendationEvent::query()
            ->whereIn('order_item_id', $itemIds)
            ->where('event_type', RecommendationEvent::TYPE_PURCHASE)
            ->whereNull('reversed_at')
            ->update(['reversed_at' => now()]);
    }
}
