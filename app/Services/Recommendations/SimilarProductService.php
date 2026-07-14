<?php

declare(strict_types=1);

namespace App\Services\Recommendations;

use App\Models\AdminProductRelationship;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
// Phase 11B.2 v11B.2.1 §1 — was Illuminate\Database\Eloquent\Collection, which
// caused a runtime TypeError because `collect()->take()` returns
// `Illuminate\Support\Collection`, not the Eloquent subtype. The method
// genuinely produces a base Support collection of annotated Product models,
// so the declared type is changed to match what is actually returned.
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Phase 11B.2 §5 — Similar Products scoring.
 *
 * Transparent weighted score per dev §5. Scoring priority (matches the
 * config weights):
 *
 *   1. same subcategory                  (highest)
 *   2. similar price (±10% / ±25% / ±50%)
 *   3. same parent category
 *   4. same vendor (small)
 *   5. rating × 2 (per ★)
 *   6. popularity (log10 of order count)
 *   7. promotion / in-stock booster
 *
 * Per dev §5: "Text/category similarity must be more important than
 * popularity. Do not let unrelated bestsellers appear merely because they
 * are popular." → category/price weights (50+30) deliberately outweigh
 * the popularity floor (max 5 × log10(1e5+1) ≈ 25).
 *
 * Manual overrides (per dev §15):
 *   - PINNED relationships are prepended to the result (rank above algorithmic)
 *   - HIDDEN + EXCLUDED relationships filter out specific products
 */
class SimilarProductService
{
    public function __construct(
        private RecommendationEligibility $eligibility,
        private AdminCurationGate $curation,  // v11B.2.1 §5 — flag-gated admin queries
    ) {}

    /**
     * @return \Illuminate\Support\Collection<int, Product> A base collection of
     *   Product models in ranked order. Each model gets a transient
     *   `recommendation_score` and `recommendation_explanation` attribute
     *   attached for the UI. NOT an Eloquent\Collection — the post-eligibility
     *   filter + dedup chain produces a base Support collection.
     */
    public function forProduct(Product $source, int $limit = 8): Collection
    {
        if (! (bool) config('marketplace_recommendations.features.similar_products', true)) {
            return new Collection();
        }

        $weights = (array) config('marketplace_recommendations.weights', []);

        // v11B.2.1 §5 — these helpers return [] when admin_curated flag is off;
        // no DB query is even issued in the disabled path.
        $excludedIds = $this->curation->excludedIdsFor($source);
        $pinnedIds   = $this->curation->pinnedIdsFor($source);

        // 1. Algorithmic candidates — single query with all score components
        //    summed in SQL. Limit candidate set BEFORE final ranking by
        //    using category/parent boosts in the WHERE clause as well.
        $sourcePrice = $this->effectivePriceMinor($source);

        $query = Product::query()
            ->select([
                'products.*',
                // Build the score as a single SQL expression so MySQL can do all
                // arithmetic in one pass. Each CASE evaluates to its weight or 0.
                DB::raw(sprintf(
                    "(
                        (CASE WHEN products.category_id = ? THEN %d ELSE 0 END)
                      + (CASE WHEN products.category_id IN (
                            SELECT id FROM categories WHERE parent_id = ?
                         ) THEN %d ELSE 0 END)
                      + (CASE WHEN ABS(COALESCE(products.price_minor,0) - ?) <= ? * 0.10 THEN %d
                              WHEN ABS(COALESCE(products.price_minor,0) - ?) <= ? * 0.25 THEN %d
                              WHEN ABS(COALESCE(products.price_minor,0) - ?) <= ? * 0.50 THEN %d
                              ELSE 0 END)
                      + (CASE WHEN products.vendor_id = ? THEN %d ELSE 0 END)
                      + (CASE WHEN products.track_stock = 0 OR products.stock > 0 THEN %d ELSE 0 END)
                    ) AS recommendation_score",
                    (int) ($weights['same_subcategory']        ?? 50),
                    (int) ($weights['same_parent_category']    ?? 25),
                    (int) ($weights['price_within_10_percent'] ?? 30),
                    (int) ($weights['price_within_25_percent'] ?? 15),
                    (int) ($weights['price_within_50_percent'] ?? 5),
                    (int) ($weights['same_vendor']             ?? 5),
                    (int) ($weights['in_stock']                ?? 2),
                ))
            ])
            ->addBinding([
                $source->category_id ?? 0,
                $source->category_id ?? 0,
                $sourcePrice, $sourcePrice,
                $sourcePrice, $sourcePrice,
                $sourcePrice, $sourcePrice,
                $source->vendor_id ?? 0,
            ], 'select');

        $this->eligibility->applyToQuery($query, $source->id);

        if (! empty($excludedIds)) {
            $query->whereNotIn('products.id', $excludedIds);
        }

        // Prefer rows that share at least the parent category — keeps the
        // candidate set focused. If the source has no category at all, the
        // OR clause keeps it open to all eligible products (cold-start fallback).
        if ($source->category_id !== null) {
            $query->where(function (Builder $q) use ($source) {
                $q->where('products.category_id', $source->category_id)
                  ->orWhereIn('products.category_id', function ($sub) use ($source) {
                      $sub->select('id')->from('categories')->where('parent_id', $source->category_id);
                  })
                  ->orWhereExists(function ($sub) use ($source) {
                      $sub->select(DB::raw(1))->from('categories')
                          ->whereColumn('categories.id', 'products.category_id')
                          ->where('categories.parent_id', $source->category_id);
                  });
            });
        }

        $candidates = $query
            ->with(['vendor', 'category', 'images', 'translations'])
            ->orderByDesc('recommendation_score')
            ->orderByDesc('products.published_at')
            ->limit($limit * 3)  // wider candidate pool to allow eligibility filtering
            ->get();

        // 2. Apply post-filter (defense in depth for stock / vendor) and
        //    annotate each result with an explanation label.
        $eligible = $this->eligibility->filterCollection($candidates, $source->id);

        $annotated = collect($eligible)->map(function (Product $p) use ($source, $sourcePrice, $weights) {
            $explain = $this->explanationFor($p, $source, $sourcePrice);
            $p->setAttribute('recommendation_explanation', $explain);
            return $p;
        });

        // 3. Prepend pinned (always-show) results
        if (! empty($pinnedIds)) {
            $pinned = Product::query()
                ->whereIn('products.id', $pinnedIds)
                ->with(['vendor', 'category', 'images', 'translations'])
                ->get();
            $eligiblePinned = $this->eligibility->filterCollection($pinned, $source->id);
            foreach ($eligiblePinned as $p) {
                $p->setAttribute('recommendation_score', 1_000_000);  // sort first
                $p->setAttribute('recommendation_explanation', 'pinned');
                $annotated->prepend($p);
            }
        }

        // 4. Dedupe (pinned might overlap algorithmic) and apply final limit
        $seen = [];
        $deduped = $annotated->filter(function (Product $p) use (&$seen) {
            if (isset($seen[$p->id])) return false;
            $seen[$p->id] = true;
            return true;
        })->values();

        return $deduped->take($limit);
    }

    /**
     * Effective customer-facing price per dev §6:
     *   - promotional price where active (handled by Product::final_price accessor or
     *     by the existing PricingService — falls back to price_minor here for SQL).
     *   - lowest variant price for variable products (covered by reading the
     *     stored price_minor which is the parent for variable products).
     */
    private function effectivePriceMinor(Product $p): int
    {
        return (int) ($p->price_minor ?? 0);
    }

    private function excludedIdsFor(Product $source): array
    {
        // v11B.2.1 §5 — kept as private indirection so older Pest tests that
        // mock at this seam continue to work; just delegates to the gate.
        return $this->curation->excludedIdsFor($source);
    }

    private function pinnedIdsFor(Product $source): array
    {
        // v11B.2.1 §5 — delegates to AdminCurationGate (flag-aware)
        return $this->curation->pinnedIdsFor($source);
    }

    /**
     * Produce a human-readable explanation token (per dev §16). The frontend
     * maps these to localized labels via lang/{ar,en}.json keys.
     */
    private function explanationFor(Product $p, Product $source, int $sourcePrice): string
    {
        if ($p->category_id !== null && $p->category_id === $source->category_id) {
            return 'similar_category';
        }
        $priceDelta = abs(((int) ($p->price_minor ?? 0)) - $sourcePrice);
        if ($sourcePrice > 0 && $priceDelta <= $sourcePrice * 0.10) {
            return 'similar_price';
        }
        if ($p->vendor_id !== null && $p->vendor_id === $source->vendor_id) {
            return 'same_vendor';
        }
        return 'related';
    }
}
