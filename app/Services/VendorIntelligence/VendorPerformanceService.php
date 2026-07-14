<?php

declare(strict_types=1);

namespace App\Services\VendorIntelligence;

use App\Models\Order;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 11B.4 §13 §29 — vendor performance highlights.
 *
 * All queries scoped to vendor's own data. Never joins other vendors'
 * data. §29 uses order-item snapshots (line_total_minor) — never derives
 * revenue from current product price.
 *
 * §13 time windows: 7 / 30 / 90 days + all-time.
 */
class VendorPerformanceService
{
    /**
     * Top-selling products by units in the last N days.
     *
     * @return list<array{product_id:int, name:string, units_sold:int, revenue_minor:int}>
     */
    public function topSellingProducts(Vendor $vendor, int $days = 30, int $limit = 5): array
    {
        $rows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('products.vendor_id', $vendor->id)   // vendor isolation
            ->whereIn('orders.status', [
                Order::STATUS_PAID, Order::STATUS_CONFIRMED, Order::STATUS_SHIPPED,
                Order::STATUS_DELIVERED, Order::STATUS_COMPLETED,
            ])
            ->where('orders.created_at', '>=', now()->subDays($days))
            ->groupBy('order_items.product_id', 'products.name')
            ->selectRaw('
                order_items.product_id AS product_id,
                products.name AS name,
                SUM(order_items.quantity) AS units_sold,
                SUM(order_items.line_total_minor) AS revenue_minor
            ')
            ->orderByDesc('units_sold')
            ->limit($limit)
            ->get();

        return $rows->map(fn ($r) => [
            'product_id'    => (int) $r->product_id,
            'name'          => (string) $r->name,
            'units_sold'    => (int) $r->units_sold,
            'revenue_minor' => (int) $r->revenue_minor,
        ])->all();
    }

    /**
     * Most viewed products in the last N days.
     *
     * @return list<array{product_id:int, name:string, views:int}>
     */
    public function mostViewedProducts(Vendor $vendor, int $days = 30, int $limit = 5): array
    {
        if (! Schema::hasTable('customer_product_views')) return [];

        $rows = DB::table('customer_product_views')
            ->join('products', 'products.id', '=', 'customer_product_views.product_id')
            ->where('products.vendor_id', $vendor->id)
            ->where('customer_product_views.viewed_at', '>=', now()->subDays($days))
            ->groupBy('customer_product_views.product_id', 'products.name')
            ->selectRaw('
                customer_product_views.product_id AS product_id,
                products.name AS name,
                COUNT(*) AS views
            ')
            ->orderByDesc('views')
            ->limit($limit)
            ->get();

        return $rows->map(fn ($r) => [
            'product_id' => (int) $r->product_id,
            'name'       => (string) $r->name,
            'views'      => (int) $r->views,
        ])->all();
    }

    /**
     * Most wishlisted products (all-time).
     *
     * @return list<array{product_id:int, name:string, wishlist_count:int}>
     */
    public function mostWishlistedProducts(Vendor $vendor, int $limit = 5): array
    {
        if (! Schema::hasTable('wishlists')) return [];

        $rows = DB::table('wishlists')
            ->join('products', 'products.id', '=', 'wishlists.product_id')
            ->where('products.vendor_id', $vendor->id)
            ->groupBy('wishlists.product_id', 'products.name')
            ->selectRaw('
                wishlists.product_id AS product_id,
                products.name AS name,
                COUNT(*) AS wishlist_count
            ')
            ->orderByDesc('wishlist_count')
            ->limit($limit)
            ->get();

        return $rows->map(fn ($r) => [
            'product_id'     => (int) $r->product_id,
            'name'           => (string) $r->name,
            'wishlist_count' => (int) $r->wishlist_count,
        ])->all();
    }
}
