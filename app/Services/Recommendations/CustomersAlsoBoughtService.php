<?php

declare(strict_types=1);

namespace App\Services\Recommendations;

use App\Models\AdminProductRelationship;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
// Phase 11B.2 v11B.2.1 §1 — switched from Eloquent\Collection to Support\Collection
// because `collect($final)` returns a base Support collection. See SimilarProductService.
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Phase 11B.2 §11+§12 — Customers Also Bought.
 *
 * Definition (per dev §11): customers who purchased the SOURCE product
 * also purchased these other products in separate OR same completed
 * orders. Aggregated by DISTINCT customer count — never by individual.
 *
 * Privacy threshold (per dev §12): never publish a recommendation that
 * comes from fewer than N distinct customers (default 3, configurable).
 * If a marketplace is too low-volume to satisfy this, the service returns
 * an empty result and the UI is expected to fall back to Similar Products
 * (handled by RecommendationManager).
 */
class CustomersAlsoBoughtService
{
    public function __construct(
        private RecommendationEligibility $eligibility,
        private AdminCurationGate $curation,  // v11B.2.1 §5
    ) {}

    /**
     * @return \Illuminate\Support\Collection<int, Product> ranked, eligible
     *   products. NOT an Eloquent\Collection — see SimilarProductService for
     *   rationale. Empty collection returned when below privacy threshold or
     *   when the feature flag is off.
     */
    public function forProduct(Product $source, int $limit = 8): Collection
    {
        if (! (bool) config('marketplace_recommendations.features.customers_also_bought', true)) {
            return new Collection();
        }

        $cfg            = (array) config('marketplace_recommendations.customers_also_bought');
        $minCustomers   = (int) ($cfg['min_distinct_customers'] ?? 3);
        $lookback       = (int) ($cfg['lookback_days'] ?? 365);

        // Find: distinct customers who bought $source's product → which OTHER
        // products did they buy (in any qualifying order in lookback)? Group
        // by other_product_id and count distinct customers; filter by minimum.
        //
        // The query is a single SQL pass: a subquery enumerates customer_ids
        // who bought source; the outer query joins orders+order_items for
        // those customers, excludes source, groups, and counts distinct customers.
        $statuses = $this->qualifyingStatuses();

        $sourceCustomers = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.product_id', $source->id)
            ->whereIn('orders.status', $statuses)
            ->when($lookback > 0, fn ($q) =>
                $q->where('orders.created_at', '>=', now()->subDays($lookback))
            )
            ->whereNotNull('orders.user_id')
            ->distinct()
            ->pluck('orders.user_id')
            ->all();

        if (empty($sourceCustomers)) {
            return new Collection();
        }

        // Aggregate: other_product_id → distinct_customer_count
        $excluded = $this->excludedIdsFor($source);

        $aggRows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->select(
                'order_items.product_id',
                DB::raw('COUNT(DISTINCT orders.user_id) AS distinct_customer_count'),
                DB::raw('MAX(orders.created_at) AS last_seen_at')
            )
            ->whereIn('orders.user_id', $sourceCustomers)
            ->whereIn('orders.status', $statuses)
            ->where('order_items.product_id', '!=', $source->id)
            ->when(! empty($excluded), fn ($q) => $q->whereNotIn('order_items.product_id', $excluded))
            ->when($lookback > 0, fn ($q) =>
                $q->where('orders.created_at', '>=', now()->subDays($lookback))
            )
            ->groupBy('order_items.product_id')
            ->having('distinct_customer_count', '>=', $minCustomers)
            ->orderByDesc('distinct_customer_count')
            ->orderByDesc('last_seen_at')
            ->limit($limit * 3)
            ->get();

        if ($aggRows->isEmpty()) {
            return new Collection();
        }

        $ids = $aggRows->pluck('product_id')->all();

        $products = Product::query()
            ->whereIn('products.id', $ids)
            ->with(['vendor', 'category', 'images', 'translations']);
        $this->eligibility->applyToQuery($products, $source->id);
        $loaded = $products->get();

        $eligible = $this->eligibility->filterCollection($loaded, $source->id);

        // Re-sort by distinct customer count (from $aggRows) preserved order
        $countById = $aggRows->keyBy('product_id');
        usort($eligible, fn ($a, $b) =>
            ($countById[$b->id]->distinct_customer_count ?? 0) <=>
            ($countById[$a->id]->distinct_customer_count ?? 0)
        );

        $final = array_slice($eligible, 0, $limit);
        foreach ($final as $p) {
            $p->setAttribute('recommendation_explanation', 'popular_with_buyers');
            $p->setAttribute('recommendation_score', (float) ($countById[$p->id]->distinct_customer_count ?? 0));
        }

        return collect($final);
    }

    private function qualifyingStatuses(): array
    {
        return [
            Order::STATUS_PAID, Order::STATUS_CONFIRMED,
            Order::STATUS_SHIPPED, Order::STATUS_DELIVERED, Order::STATUS_COMPLETED,
        ];
    }

    private function excludedIdsFor(Product $source): array
    {
        // v11B.2.1 §5 — gate enforces admin_curated flag (returns [] when off)
        return $this->curation->excludedIdsFor($source);
    }
}
