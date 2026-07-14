<?php

declare(strict_types=1);

namespace App\Services\VendorIntelligence;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Vendor;
use App\Models\VendorIntelligenceAlert as Alert;
use App\Services\Settings\SiteSettingsService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Phase 11B.4 §7 §8 §28 §29 — inventory alert generation.
 *
 * Alert types produced:
 *   - out_of_stock            (track_stock=1, stock=0)
 *   - low_stock               (track_stock=1, 0 < stock ≤ threshold)
 *   - fast_moving_low_stock   (low_stock AND recent orders ≥ min_orders)
 *   - slow_moving             (published, in stock, older than min_age, 0 recent sales)
 *   - no_stock_tracking       (physical product with track_stock=0)
 *
 * Phase 11B.4 v11B.4.2 Defect 6 fix — variant alerts.
 *   Additional types (only when the product has active variants — the
 *   `product_variants` table has its own `stock` + `is_active` columns
 *   so variants can independently go OOS while the parent looks OK):
 *   - variant_out_of_stock
 *   - variant_low_stock
 *   - variant_fast_moving_low_stock
 *
 *   IMPORTANT: when a product has ≥1 active variant, we SKIP the
 *   product-level stock alerts and produce variant-level alerts
 *   instead. This prevents double-counting.
 *
 * §29 sales evidence uses only completed/delivered/paid orders — NOT
 * pending_payment / cancelled / refunded / failed.
 *
 * §28 §7 caveat: "Do not treat unlimited-stock products as low stock".
 * Digital + service types + `track_stock=0` skip stock alerts.
 */
class InventoryAlertService
{
    public function __construct(
        private readonly SiteSettingsService $settings,
    ) {}

    /**
     * Produce (but don't persist) the raw alerts for a vendor.
     * Returns descriptors ready to be materialized into `vendor_intelligence_alerts`.
     *
     * @return list<array{alert_type:string, entity_type:string, entity_id:int, priority:string, evidence:array<string,mixed>}>
     */
    public function computeForVendor(Vendor $vendor): array
    {
        // §7 excluded vendor states
        if ($vendor->status !== Vendor::STATUS_APPROVED) return [];

        $lowThreshold = (int) $this->settings->get('vendor_intelligence.low_stock_threshold', 5);
        $fastDays     = (int) $this->settings->get('vendor_intelligence.fast_moving_days', 30);
        $fastMinOrd   = (int) $this->settings->get('vendor_intelligence.fast_moving_min_orders', 5);
        $slowDays     = (int) $this->settings->get('vendor_intelligence.slow_moving_days', 60);
        $slowMinAge   = (int) $this->settings->get('vendor_intelligence.slow_moving_min_age_days', 30);

        $out = [];

        // Load vendor's products in one query, no N+1
        $products = Product::where('vendor_id', $vendor->id)
            ->whereIn('status', ['published', 'pending_review'])
            ->get(['id', 'name', 'type', 'track_stock', 'stock', 'status', 'created_at']);

        // Precompute recent-orders map per product for fast-moving evidence.
        $productIds = $products->pluck('id')->all();
        $recentOrderCounts = $this->recentOrderCountsPerProduct($productIds, $fastDays);
        $lastOrderAt = $this->lastOrderAtPerProduct($productIds);

        // Phase 11B.4 v11B.4.2 Defect 6 — preload active variant map
        // per product so we can decide product-level vs variant-level
        // alerts without N+1.
        $variantsByProduct = ProductVariant::whereIn('product_id', $productIds)
            ->where('is_active', true)
            ->get(['id', 'product_id', 'name', 'sku', 'stock'])
            ->groupBy('product_id');

        foreach ($products as $p) {
            $isPhysical = ! in_array($p->type, ['digital', 'service'], true);
            $tracksStock = (bool) $p->track_stock;
            $productVariants = $variantsByProduct->get($p->id, collect());
            $hasVariants = $productVariants->isNotEmpty();

            // ─── VARIANT-LEVEL alerts (§14 §28) ─────────────────────
            // When the product has active variants, product-level stock
            // alerts are misleading — the product-level `stock` column
            // is either a rollup or a placeholder; the ground truth is
            // the variant `stock` column.
            if ($hasVariants && $tracksStock) {
                foreach ($productVariants as $var) {
                    $variantLabel = trim((string) ($var->name ?? $var->sku ?? "#{$var->id}"));

                    // Variant OOS
                    if ((int) $var->stock === 0) {
                        $out[] = [
                            'alert_type'  => Alert::TYPE_VARIANT_OUT_OF_STOCK,
                            'entity_type' => 'variant',
                            'entity_id'   => (int) $var->id,
                            'priority'    => Alert::PRIORITY_CRITICAL,
                            'evidence'    => [
                                'product_id'    => $p->id,
                                'product_name'  => $p->name,
                                'variant_id'    => (int) $var->id,
                                'variant_label' => $variantLabel,
                                'stock'         => 0,
                            ],
                        ];
                        continue;
                    }

                    // Variant low stock (+ fast-moving upgrade)
                    if ((int) $var->stock > 0 && (int) $var->stock <= $lowThreshold) {
                        $recent = (int) ($recentOrderCounts[$p->id] ?? 0);
                        $isFast = $recent >= $fastMinOrd;
                        $out[] = [
                            'alert_type'  => $isFast ? Alert::TYPE_VARIANT_FAST_MOVING_LOW_STOCK
                                                     : Alert::TYPE_VARIANT_LOW_STOCK,
                            'entity_type' => 'variant',
                            'entity_id'   => (int) $var->id,
                            'priority'    => $isFast ? Alert::PRIORITY_HIGH : Alert::PRIORITY_MEDIUM,
                            'evidence'    => [
                                'product_id'    => $p->id,
                                'product_name'  => $p->name,
                                'variant_id'    => (int) $var->id,
                                'variant_label' => $variantLabel,
                                'stock'         => (int) $var->stock,
                                'threshold'     => $lowThreshold,
                                'recent_orders' => $recent,
                            ],
                        ];
                    }
                }
                // Skip product-level stock check for variant products (§28
                // "avoid double counting parent and variant alerts").
            } else {
                // ─── PRODUCT-LEVEL alerts (no variants) ─────────────
                if ($tracksStock && (int) $p->stock === 0) {
                    $out[] = [
                        'alert_type'  => Alert::TYPE_OUT_OF_STOCK,
                        'entity_type' => 'product',
                        'entity_id'   => $p->id,
                        'priority'    => Alert::PRIORITY_CRITICAL,
                        'evidence'    => [
                            'product_name' => $p->name,
                            'stock'        => 0,
                            'recent_orders' => (int) ($recentOrderCounts[$p->id] ?? 0),
                        ],
                    ];
                    // fall through to no_stock_tracking + slow_moving checks
                }

                if ($tracksStock && (int) $p->stock > 0 && (int) $p->stock <= $lowThreshold) {
                    $recentOrders = (int) ($recentOrderCounts[$p->id] ?? 0);
                    $isFastMoving = $recentOrders >= $fastMinOrd;

                    $out[] = [
                        'alert_type'  => $isFastMoving ? Alert::TYPE_FAST_MOVING_LOW_STOCK : Alert::TYPE_LOW_STOCK,
                        'entity_type' => 'product',
                        'entity_id'   => $p->id,
                        'priority'    => $isFastMoving ? Alert::PRIORITY_HIGH : Alert::PRIORITY_MEDIUM,
                        'evidence'    => [
                            'product_name' => $p->name,
                            'stock'        => (int) $p->stock,
                            'threshold'    => $lowThreshold,
                            'recent_orders' => $recentOrders,
                            'window_days'  => $fastDays,
                        ],
                    ];
                }
            }

            // ─── No stock tracking (physical product, tracking off) ─
            // Applies to products with or without variants — tracking is
            // a product-level flag.
            if ($isPhysical && ! $tracksStock) {
                $out[] = [
                    'alert_type'  => Alert::TYPE_NO_STOCK_TRACKING,
                    'entity_type' => 'product',
                    'entity_id'   => $p->id,
                    'priority'    => Alert::PRIORITY_LOW,
                    'evidence'    => [
                        'product_name' => $p->name,
                        'suggestion'   => 'enable_stock_tracking',
                    ],
                ];
            }

            // ─── Slow moving ─────────────────────────────────────
            $ageDays = $p->created_at ? $p->created_at->diffInDays(now()) : 0;
            $stockOk = ! $tracksStock || (int) $p->stock > 0 || $hasVariants;  // variants may still be in stock
            $lastOrder = $lastOrderAt[$p->id] ?? null;
            $daysSinceOrder = $lastOrder ? \Carbon\Carbon::parse($lastOrder)->diffInDays(now()) : PHP_INT_MAX;

            if ($p->status === 'published'
                && $stockOk
                && $ageDays >= $slowMinAge
                && $daysSinceOrder >= $slowDays
            ) {
                $out[] = [
                    'alert_type'  => Alert::TYPE_SLOW_MOVING,
                    'entity_type' => 'product',
                    'entity_id'   => $p->id,
                    'priority'    => Alert::PRIORITY_MEDIUM,
                    'evidence'    => [
                        'product_name' => $p->name,
                        'age_days'     => $ageDays,
                        'days_since_last_order' => $lastOrder ? $daysSinceOrder : null,
                    ],
                ];
            }
        }

        return $out;
    }

    /**
     * Count completed/delivered/paid orders per product in the last N days.
     * One grouped query — no N+1.
     *
     * @param list<int> $productIds
     * @return array<int,int>
     */
    private function recentOrderCountsPerProduct(array $productIds, int $days): array
    {
        if (empty($productIds)) return [];
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('order_items.product_id', $productIds)
            ->whereIn('orders.status', [
                Order::STATUS_PAID,
                Order::STATUS_CONFIRMED,
                Order::STATUS_SHIPPED,
                Order::STATUS_DELIVERED,
                Order::STATUS_COMPLETED,
            ])
            ->where('orders.created_at', '>=', now()->subDays($days))
            ->groupBy('order_items.product_id')
            ->selectRaw('order_items.product_id AS pid, COUNT(DISTINCT orders.id) AS cnt')
            ->pluck('cnt', 'pid')
            ->all();
    }

    /**
     * Last completed order date per product.
     *
     * @param list<int> $productIds
     * @return array<int,string>
     */
    private function lastOrderAtPerProduct(array $productIds): array
    {
        if (empty($productIds)) return [];
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('order_items.product_id', $productIds)
            ->whereIn('orders.status', [
                Order::STATUS_PAID,
                Order::STATUS_CONFIRMED,
                Order::STATUS_SHIPPED,
                Order::STATUS_DELIVERED,
                Order::STATUS_COMPLETED,
            ])
            ->groupBy('order_items.product_id')
            ->selectRaw('order_items.product_id AS pid, MAX(orders.created_at) AS ts')
            ->pluck('ts', 'pid')
            ->all();
    }
}
