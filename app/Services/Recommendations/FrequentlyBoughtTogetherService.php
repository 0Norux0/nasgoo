<?php

declare(strict_types=1);

namespace App\Services\Recommendations;

use App\Models\AdminProductRelationship;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductPairStat;
use Illuminate\Database\Eloquent\Builder;
// Phase 11B.2 v11B.2.1 §1 — uses Support\Collection consistently with sister services
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Phase 11B.2 §8+§9+§10 — Frequently Bought Together.
 *
 * Definition (per dev §8): Products A and B are frequently bought together
 * when they appear in the SAME completed customer order above a minimum
 * threshold. Uses pre-aggregated product_pair_stats.
 *
 * Metrics (per dev §9):
 *   - pair_count(A,B)       = stored on row
 *   - confidence(A→B)       = pair_count / orders_containing(A)
 *   - support(A,B)          = pair_count / total_completed_orders
 *
 * Thresholds (from config marketplace_recommendations.frequently_bought):
 *   - min_pair_orders       (default 2)
 *   - min_confidence        (default 0.10)
 *   - min_support           (default 0.001)
 *   - lookback_days         (default 180)
 *
 * Fallback per dev §14 — if real co-occurrence is below threshold, fall
 * back to admin-configured COMPLEMENTARY relationships ONLY (never claim
 * "frequently bought" if evidence doesn't support it; the UI re-labels
 * the section accordingly).
 */
class FrequentlyBoughtTogetherService
{
    public function __construct(
        private RecommendationEligibility $eligibility,
        private AdminCurationGate $curation,  // v11B.2.1 §5
    ) {}

    /**
     * @return array{
     *   products: \Illuminate\Database\Eloquent\Collection<int, Product>,
     *   evidence: string,         // 'co_occurrence' | 'complementary' | 'none'
     *   metrics:  array<int,array{pair_count:int,confidence:float}>
     * }
     */
    public function forProduct(Product $source, int $limit = 4): array
    {
        if (! (bool) config('marketplace_recommendations.features.frequently_bought', true)) {
            return ['products' => new Collection(), 'evidence' => 'none', 'metrics' => []];
        }

        $cfg = (array) config('marketplace_recommendations.frequently_bought');
        $minPair   = (int)   ($cfg['min_pair_orders'] ?? 2);
        $minConf   = (float) ($cfg['min_confidence']  ?? 0.10);
        $minSupp   = (float) ($cfg['min_support']     ?? 0.001);
        $lookback  = (int)   ($cfg['lookback_days']   ?? 180);

        // 1. ordersContainingSource — denominator for confidence(A→B)
        $ordersContainingSource = $this->ordersContaining($source->id, $lookback);
        $totalCompletedOrders   = $this->totalCompletedOrdersInLookback($lookback);

        // 2. Pull pair rows where source is on either side; map "other" id + count + recency
        $pairs = ProductPairStat::query()
            ->where(function (Builder $q) use ($source) {
                $q->where('product_a_id', $source->id)->orWhere('product_b_id', $source->id);
            })
            ->where('pair_count', '>=', $minPair)
            ->when($lookback > 0, fn ($q) => $q->where('last_seen_at', '>=', now()->subDays($lookback)))
            ->orderByDesc('pair_count')
            ->orderByDesc('last_seen_at')
            ->limit($limit * 5)  // wider pool for eligibility filtering
            ->get();

        $excluded = $this->excludedIdsFor($source);

        $candidates = [];  // [otherId => ['pair_count' => N, 'confidence' => f, 'support' => f]]
        foreach ($pairs as $p) {
            $otherId = $p->product_a_id === $source->id ? $p->product_b_id : $p->product_a_id;
            if (in_array($otherId, $excluded, true)) continue;
            $confidence = $ordersContainingSource > 0 ? $p->pair_count / $ordersContainingSource : 0;
            $support    = $totalCompletedOrders   > 0 ? $p->pair_count / $totalCompletedOrders   : 0;
            if ($confidence < $minConf) continue;
            if ($support    < $minSupp) continue;
            $candidates[$otherId] = [
                'pair_count' => (int) $p->pair_count,
                'confidence' => $confidence,
                'support'    => $support,
            ];
        }

        if (empty($candidates)) {
            return $this->complementaryFallback($source, $limit);
        }

        // 3. Load eligible Products in bulk + apply eligibility post-filter
        $products = Product::query()
            ->whereIn('products.id', array_keys($candidates))
            ->with(['vendor', 'category', 'images', 'translations']);
        $this->eligibility->applyToQuery($products, $source->id);
        $loaded = $products->get();

        $eligible = $this->eligibility->filterCollection($loaded, $source->id);

        // 4. Sort by pair_count desc (stored in $candidates), take limit
        usort($eligible, fn ($a, $b) =>
            ($candidates[$b->id]['pair_count'] ?? 0) <=> ($candidates[$a->id]['pair_count'] ?? 0)
        );
        $final = array_slice($eligible, 0, $limit);

        // Attach evidence metadata for the UI
        $metrics = [];
        foreach ($final as $p) {
            $p->setAttribute('recommendation_explanation', 'frequently_bought');
            $p->setAttribute('recommendation_score', (float) ($candidates[$p->id]['pair_count'] ?? 0));
            $metrics[$p->id] = [
                'pair_count' => $candidates[$p->id]['pair_count'] ?? 0,
                'confidence' => $candidates[$p->id]['confidence'] ?? 0,
            ];
        }

        return [
            'products' => collect($final),
            'evidence' => 'co_occurrence',
            'metrics'  => $metrics,
        ];
    }

    /**
     * Per dev §14 fallback chain: admin-configured complementary items only.
     * UI relabels the section to "You May Also Like" / "Related Products"
     * because real co-occurrence evidence is missing.
     *
     * v11B.2.1 §5 — gate-aware: when admin_curated flag is off, the gate
     * returns [] so this method returns "none" evidence without issuing
     * a query.
     */
    private function complementaryFallback(Product $source, int $limit): array
    {
        $compIds = $this->curation->complementaryIdsFor($source);

        if (empty($compIds)) {
            return ['products' => new Collection(), 'evidence' => 'none', 'metrics' => []];
        }

        $products = Product::query()
            ->whereIn('products.id', $compIds)
            ->with(['vendor', 'category', 'images', 'translations']);
        $this->eligibility->applyToQuery($products, $source->id);
        $loaded = $products->get();

        $eligible = collect($this->eligibility->filterCollection($loaded, $source->id))
            ->take($limit)
            ->each(function (Product $p) {
                $p->setAttribute('recommendation_explanation', 'related');
            });

        return [
            'products' => $eligible,
            'evidence' => 'complementary',
            'metrics'  => [],
        ];
    }

    /**
     * Count of qualifying completed orders that contained the given product
     * in the lookback window. Used as the denominator for confidence(A→B).
     */
    private function ordersContaining(int $productId, int $lookbackDays): int
    {
        return (int) DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.product_id', $productId)
            ->whereIn('orders.status', $this->qualifyingStatuses())
            ->when($lookbackDays > 0, fn ($q) =>
                $q->where('orders.created_at', '>=', now()->subDays($lookbackDays))
            )
            ->distinct('orders.id')
            ->count('orders.id');
    }

    private function totalCompletedOrdersInLookback(int $lookbackDays): int
    {
        return (int) DB::table('orders')
            ->whereIn('status', $this->qualifyingStatuses())
            ->when($lookbackDays > 0, fn ($q) =>
                $q->where('created_at', '>=', now()->subDays($lookbackDays))
            )
            ->count();
    }

    /** Qualifying order statuses per dev §8. */
    private function qualifyingStatuses(): array
    {
        return [
            Order::STATUS_PAID,
            Order::STATUS_CONFIRMED,
            Order::STATUS_SHIPPED,
            Order::STATUS_DELIVERED,
            Order::STATUS_COMPLETED,
        ];
    }

    private function excludedIdsFor(Product $source): array
    {
        // v11B.2.1 §5 — gate enforces admin_curated flag (returns [] when off)
        return $this->curation->excludedIdsFor($source);
    }
}
