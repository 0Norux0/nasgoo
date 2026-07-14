<?php

declare(strict_types=1);

namespace App\Services\VendorIntelligence;

use App\Models\Order;
use App\Models\Product;
use App\Models\Vendor;
use App\Models\VendorIntelligenceAlert as Alert;
use App\Services\Settings\SiteSettingsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 11B.4 §11 §12 §15 — deterministic sales/promotion opportunity rules.
 *
 * Signals used (§3):
 *   - customer_product_views (v11B.3 table)
 *   - wishlists (Phase 4)
 *   - cart_items (Phase 4)
 *   - completed orders (v11B.2.2 snapshot pricing)
 *
 * §11 minimum evidence: don't create noise from tiny data volumes.
 *   min_views_for_conversion       (default 100)
 *   min_wishlist_interest          (default 10)
 *   min_cart_abandonment           (default 10)
 *   high_view_conversion_ceil      (default 0.01 = 1%)
 *
 * §15 caveat: promotion suggestions do NOT include exact discount %
 *   — pricing recommendations are a later phase.
 */
class VendorOpportunityService
{
    public function __construct(
        private readonly SiteSettingsService $settings,
    ) {}

    /**
     * @return list<array{alert_type:string, entity_type:string, entity_id:int, priority:string, evidence:array<string,mixed>}>
     */
    public function computeForVendor(Vendor $vendor): array
    {
        if ($vendor->status !== Vendor::STATUS_APPROVED) return [];

        $minViews     = (int)   $this->settings->get('vendor_intelligence.min_views_for_conversion', 100);
        $convCeil     = (float) $this->settings->get('vendor_intelligence.high_view_conversion_ceil', 0.01);
        $minWishlist  = (int)   $this->settings->get('vendor_intelligence.min_wishlist_interest', 10);
        $minAbandon   = (int)   $this->settings->get('vendor_intelligence.min_cart_abandonment', 10);
        $windowDays   = (int)   $this->settings->get('vendor_intelligence.fast_moving_days', 30);

        $products = Product::where('vendor_id', $vendor->id)
            ->where('status', 'published')
            ->get(['id', 'name', 'stock', 'track_stock', 'type']);

        if ($products->isEmpty()) return [];

        $productIds = $products->pluck('id')->all();
        $viewsMap    = $this->viewsPerProduct($productIds, $windowDays);
        $wishlistMap = $this->wishlistPerProduct($productIds);
        $cartAddsMap = $this->cartAddsPerProduct($productIds);
        $ordersMap   = $this->completedOrderCountsPerProduct($productIds, $windowDays);

        $out = [];

        foreach ($products as $p) {
            $views     = (int) ($viewsMap[$p->id] ?? 0);
            $wishlist  = (int) ($wishlistMap[$p->id] ?? 0);
            $cartAdds  = (int) ($cartAddsMap[$p->id] ?? 0);
            $orders    = (int) ($ordersMap[$p->id] ?? 0);
            $conv      = $views > 0 ? $orders / $views : 0.0;

            if ($views >= $minViews && $conv < $convCeil) {
                $out[] = [
                    'alert_type'  => Alert::TYPE_HIGH_VIEW_LOW_CONVERSION,
                    'entity_type' => 'product',
                    'entity_id'   => $p->id,
                    'priority'    => Alert::PRIORITY_MEDIUM,
                    'evidence'    => [
                        'product_name'    => $p->name,
                        'views'           => $views,
                        'purchases'       => $orders,
                        'conversion_rate' => round($conv, 4),
                        'window_days'     => $windowDays,
                    ],
                ];
            }

            if ($wishlist >= $minWishlist && $orders < ($wishlist / 4)) {
                $out[] = [
                    'alert_type'  => Alert::TYPE_WISHLIST_INTEREST,
                    'entity_type' => 'product',
                    'entity_id'   => $p->id,
                    'priority'    => Alert::PRIORITY_LOW,
                    'evidence'    => [
                        'product_name' => $p->name,
                        'wishlist_adds' => $wishlist,
                        'purchases'    => $orders,
                    ],
                ];
            }

            $abandonment = max(0, $cartAdds - $orders);
            if ($abandonment >= $minAbandon) {
                $out[] = [
                    'alert_type'  => Alert::TYPE_CART_ABANDONMENT,
                    'entity_type' => 'product',
                    'entity_id'   => $p->id,
                    'priority'    => Alert::PRIORITY_MEDIUM,
                    'evidence'    => [
                        'product_name' => $p->name,
                        'cart_adds'    => $cartAdds,
                        'purchases'    => $orders,
                        'abandonment'  => $abandonment,
                    ],
                ];
            }

            if ($views >= $minViews && $conv < $convCeil && $orders === 0) {
                $out[] = [
                    'alert_type'  => Alert::TYPE_PROMOTION_OPPORTUNITY,
                    'entity_type' => 'product',
                    'entity_id'   => $p->id,
                    'priority'    => Alert::PRIORITY_LOW,
                    'evidence'    => [
                        'product_name' => $p->name,
                        'views'        => $views,
                        'purchases'    => 0,
                        'reason'       => 'high_views_no_sales',
                    ],
                ];
            }
        }

        // Phase 11B.4 v11B.4.2 Defect 7 fix — search-demand suggestions.
        // Uses the real Phase 6 `search_queries` table (aggregated only,
        // no customer identity). For each popular search term in the
        // vendor's locale, check if the vendor has any product whose
        // name contains the term. If not, and search_count is high, the
        // vendor has an opportunity to fill the demand.
        //
        // Limits: max 3 suggestions per vendor per run to avoid noise;
        // dedupe by search term (entity_type='search_term', entity_id
        // hashed from term).
        $out = array_merge($out, $this->searchDemandSuggestions($vendor, $products));

        return $out;
    }

    /**
     * @param Vendor $vendor
     * @param \Illuminate\Support\Collection<int,Product> $products
     * @return list<array{alert_type:string, entity_type:string, entity_id:int, priority:string, evidence:array<string,mixed>}>
     */
    private function searchDemandSuggestions(Vendor $vendor, $products): array
    {
        if (! Schema::hasTable('search_queries')) return [];

        $locale = app()->getLocale();
        $minSearchCount = 20;   // conservative threshold — real demand only
        $maxSuggestions = 3;

        try {
            $popularTerms = DB::table('search_queries')
                ->where('locale', $locale)
                ->where('is_blocked', false)
                ->where('search_count', '>=', $minSearchCount)
                ->orderByDesc('search_count')
                ->limit(50)
                ->get(['query', 'search_count']);
        } catch (\Throwable) {
            return [];
        }

        if ($popularTerms->isEmpty()) return [];

        $productNamesLower = $products->pluck('name')
            ->map(fn ($n) => mb_strtolower((string) $n))
            ->all();

        $out = [];
        $emitted = 0;

        foreach ($popularTerms as $term) {
            if ($emitted >= $maxSuggestions) break;
            $termLower = mb_strtolower((string) $term->query);
            $matched = false;
            foreach ($productNamesLower as $name) {
                if (str_contains($name, $termLower)) { $matched = true; break; }
            }
            if ($matched) continue;   // vendor already covers this demand

            // Emit suggestion — hash the term to a stable int for entity_id
            $entityId = (int) (hexdec(substr(hash('crc32b', $termLower), 0, 7)));
            $out[] = [
                'alert_type'  => Alert::TYPE_SEARCH_DEMAND,
                'entity_type' => 'search_term',
                'entity_id'   => $entityId,
                'priority'    => Alert::PRIORITY_INFO,
                'evidence'    => [
                    'search_term'  => (string) $term->query,
                    'search_count' => (int) $term->search_count,
                    'locale'       => $locale,
                    'reason'       => 'high_demand_no_vendor_coverage',
                ],
            ];
            $emitted++;
        }

        return $out;
    }

    /**
     * @param list<int> $productIds
     * @return array<int,int>
     */
    private function viewsPerProduct(array $productIds, int $windowDays): array
    {
        if (empty($productIds) || ! Schema::hasTable('customer_product_views')) return [];
        return DB::table('customer_product_views')
            ->whereIn('product_id', $productIds)
            ->where('viewed_at', '>=', now()->subDays($windowDays))
            ->groupBy('product_id')
            ->selectRaw('product_id AS pid, COUNT(*) AS cnt')
            ->pluck('cnt', 'pid')
            ->all();
    }

    /** @param list<int> $productIds  @return array<int,int> */
    private function wishlistPerProduct(array $productIds): array
    {
        if (empty($productIds) || ! Schema::hasTable('wishlists')) return [];
        return DB::table('wishlists')
            ->whereIn('product_id', $productIds)
            ->groupBy('product_id')
            ->selectRaw('product_id AS pid, COUNT(*) AS cnt')
            ->pluck('cnt', 'pid')
            ->all();
    }

    /** @param list<int> $productIds  @return array<int,int> */
    private function cartAddsPerProduct(array $productIds): array
    {
        if (empty($productIds) || ! Schema::hasTable('cart_items')) return [];
        return DB::table('cart_items')
            ->whereIn('product_id', $productIds)
            ->groupBy('product_id')
            ->selectRaw('product_id AS pid, COUNT(*) AS cnt')
            ->pluck('cnt', 'pid')
            ->all();
    }

    /** @param list<int> $productIds  @return array<int,int> */
    private function completedOrderCountsPerProduct(array $productIds, int $windowDays): array
    {
        if (empty($productIds)) return [];
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('order_items.product_id', $productIds)
            ->whereIn('orders.status', [
                Order::STATUS_PAID, Order::STATUS_CONFIRMED, Order::STATUS_SHIPPED,
                Order::STATUS_DELIVERED, Order::STATUS_COMPLETED,
            ])
            ->where('orders.created_at', '>=', now()->subDays($windowDays))
            ->groupBy('order_items.product_id')
            ->selectRaw('order_items.product_id AS pid, COUNT(DISTINCT orders.id) AS cnt')
            ->pluck('cnt', 'pid')
            ->all();
    }
}
