<?php

declare(strict_types=1);

namespace App\Services\Personalization;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Phase 11B.3 §17 — Buy Again.
 *
 * Eligible products = distinct products from the customer's completed
 * orders within the config-configured window, with:
 *   - order status IN qualifying set (excludes cancelled/failed/refunded)
 *   - product still published + vendor still approved
 *   - min_days_since_purchase reached (avoids "just bought yesterday" annoyance)
 *   - max_days_since_purchase not exceeded
 *
 * Pricing: NO price snapshot from the historical order. Uses CURRENT
 * PricingService::priceForProduct so the customer sees today's price
 * (per dev §17 "Do not imply the previous price still applies").
 */
class BuyAgainService
{
    /**
     * @return Collection<int, Product>
     */
    public function forUser(User $user, int $limit = 8): Collection
    {
        $minDays = (int) config('marketplace_personalization.buy_again.min_days_since_purchase', 7);
        $maxDays = (int) config('marketplace_personalization.buy_again.max_days_since_purchase', 180);
        $windowStart = now()->subDays(max(1, $maxDays));
        $windowEnd   = now()->subDays(max(0, $minDays));

        $qualifying = [
            Order::STATUS_PAID, Order::STATUS_CONFIRMED, Order::STATUS_SHIPPED,
            Order::STATUS_DELIVERED, Order::STATUS_COMPLETED,
        ];

        // Grab distinct product_ids the user completed in the window,
        // most-recent-purchase-first.
        $rows = DB::table('orders')
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.user_id', $user->id)
            ->whereIn('orders.status', $qualifying)
            ->whereBetween('orders.created_at', [$windowStart, $windowEnd])
            ->select('order_items.product_id', DB::raw('MAX(orders.created_at) as last_purchased'))
            ->groupBy('order_items.product_id')
            ->orderByDesc('last_purchased')
            ->limit($limit * 3)  // over-fetch for eligibility loss
            ->pluck('order_items.product_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        if (empty($rows)) return collect();

        $products = Product::query()
            ->whereIn('products.id', $rows)
            ->where('products.status', Product::STATUS_PUBLISHED)
            ->join('vendors', 'vendors.id', '=', 'products.vendor_id')
            ->where('vendors.status', Vendor::STATUS_APPROVED)
            ->with(['vendor:id,business_name', 'primaryImage', 'translations'])
            ->select('products.*')
            ->get();

        $order = array_flip($rows);
        return $products->sortBy(fn ($p) => $order[$p->id] ?? PHP_INT_MAX)
                        ->take($limit)
                        ->values();
    }
}
