<?php

declare(strict_types=1);

namespace App\Services\Recommendations;

use App\Models\Product;
use App\Services\Localization\TranslationService;
use Illuminate\Support\Facades\Cache;

/**
 * Phase 11B.2 §4 — top-level API for the controllers.
 *
 * Controllers call this manager; it dispatches to the appropriate
 * specialized service, applies caching, and shapes the response for
 * Inertia. Per dev §4 — "Controllers should not contain large scoring
 * formulas."
 *
 * Phase 11B.2 v11B.2.1 §4 changes:
 *   - Cache keys now include a global VERSION counter (`rec:cache:version`).
 *     Bumping the version invalidates all cached recommendations in O(1)
 *     without depending on cache-driver tag support (file/database drivers
 *     don't support tags). Used by the Vendor/Translation/Relationship
 *     observers for cascade invalidation.
 *   - Cache HITS are re-validated through RecommendationEligibility before
 *     return — even a stale cache entry can no longer leak a suspended-
 *     vendor, unpublished, OOS, or admin-hidden product. The cache is an
 *     optimization, not the authorization boundary.
 */
class RecommendationManager
{
    public function __construct(
        private SimilarProductService           $similar,
        private FrequentlyBoughtTogetherService $fbt,
        private CustomersAlsoBoughtService      $alsoBought,
        private TranslationService              $i18n,
        private RecommendationEligibility       $eligibility,  // v11B.2.1 §4
        private AdminCurationGate               $curation,     // v11B.2.1 §4 (for cache-hit excluded recheck)
    ) {}

    public function similarProducts(Product $source, int $limit = 8): array
    {
        $enabled = (bool) config('marketplace_recommendations.features.similar_products', true);
        if (! $enabled || ! $this->globallyEnabled()) {
            return $this->disabledPayload();
        }

        $cacheKey = $this->cacheKey('similar', $source->id, app()->getLocale(), $limit);
        $payload = Cache::remember($cacheKey, $this->ttl(), function () use ($source, $limit) {
            $items = $this->similar->forProduct($source, $limit);
            return [
                'enabled'  => true,
                'evidence' => 'algorithmic',
                'items'    => $items->map(fn ($p) => $this->shapeItem($p))->all(),
                'metrics'  => [],
            ];
        });
        // v11B.2.1 §4 — runtime eligibility recheck on EVERY return path.
        // Even a stale cache entry must not expose suspended-vendor, unpublished,
        // OOS, or admin-hidden products. Filter ids that fail the live check.
        return $this->reapplyEligibility($payload, $source);
    }

    public function frequentlyBoughtTogether(Product $source, int $limit = 4): array
    {
        $enabled = (bool) config('marketplace_recommendations.features.frequently_bought', true);
        if (! $enabled || ! $this->globallyEnabled()) {
            return $this->disabledPayload();
        }
        $cacheKey = $this->cacheKey('fbt', $source->id, app()->getLocale(), $limit);
        $payload = Cache::remember($cacheKey, $this->ttl(), function () use ($source, $limit) {
            $res = $this->fbt->forProduct($source, $limit);
            return [
                'enabled'  => true,
                'evidence' => $res['evidence'],
                'items'    => $res['products']->map(fn ($p) => $this->shapeItem($p))->all(),
                'metrics'  => $res['metrics'],
            ];
        });
        return $this->reapplyEligibility($payload, $source);
    }

    public function customersAlsoBought(Product $source, int $limit = 8): array
    {
        $enabled = (bool) config('marketplace_recommendations.features.customers_also_bought', true);
        if (! $enabled || ! $this->globallyEnabled()) {
            return $this->disabledPayload();
        }
        $cacheKey = $this->cacheKey('also_bought', $source->id, app()->getLocale(), $limit);
        $payload = Cache::remember($cacheKey, $this->ttl(), function () use ($source, $limit) {
            $items = $this->alsoBought->forProduct($source, $limit);
            return [
                'enabled'  => true,
                'evidence' => $items->isEmpty() ? 'none' : 'algorithmic',
                'items'    => $items->map(fn ($p) => $this->shapeItem($p))->all(),
                'metrics'  => [],
            ];
        });
        return $this->reapplyEligibility($payload, $source);
    }

    /**
     * v11B.2.1 §4 — runtime eligibility recheck. Lightweight: one bulk SELECT
     * to fetch only the eligible product IDs from the cached set, then filter
     * the items array. No per-card query.
     *
     * Also re-applies admin curation exclusions (if the flag is on, hidden/
     * excluded items get filtered out even if the cache was populated when
     * the flag was off or before the admin added the relationship).
     */
    private function reapplyEligibility(array $payload, Product $source): array
    {
        if (empty($payload['items']) || ! is_array($payload['items'])) {
            return $payload;
        }

        $ids = array_values(array_filter(array_map(
            fn ($i) => isset($i['id']) ? (int) $i['id'] : null,
            $payload['items']
        )));
        if (empty($ids)) {
            return $payload;
        }

        // Bulk fetch live eligibility — one query for all cached items
        $live = Product::query()
            ->whereIn('products.id', $ids)
            ->with('vendor');
        $this->eligibility->applyToQuery($live, $source->id);
        $liveIds = $live->pluck('products.id')->map(fn ($v) => (int) $v)->all();
        $eligibleIds = array_flip($liveIds);

        // Apply admin exclusions live as well (flag-gated by AdminCurationGate)
        $excluded = array_flip($this->curation->excludedIdsFor($source));

        $payload['items'] = array_values(array_filter(
            $payload['items'],
            fn ($i) => isset($eligibleIds[(int) ($i['id'] ?? 0)])
                  && ! isset($excluded[(int) ($i['id'] ?? 0)])
        ));

        return $payload;
    }

    /**
     * Build the per-card payload for React. Calls TranslationService
     * (the canonical v11B.1.2 resolver) for localized fields per dev §17.
     */
    private function shapeItem(Product $p): array
    {
        $display = $this->i18n->displayFields($p);

        $img = $p->relationLoaded('images') && $p->images->isNotEmpty()
            ? ($p->images->first()->url ?? null)
            : null;

        return [
            'id'                        => (int) $p->id,
            'slug'                      => (string) $p->slug,
            'display_name'              => $display['display_name']              ?? $p->name,
            'display_short_description' => $display['display_short_description'] ?? null,
            'price_minor'               => (int) $p->price_minor,
            'price'                     => number_format(((int) $p->price_minor) / 1000, 3),
            'currency'                  => (string) ($p->currency ?? 'KWD'),
            'image'                     => $img,
            'in_stock'                  => ! $p->track_stock || ((int) $p->stock > 0),
            'vendor_name'               => $p->vendor->business_name ?? '',
            'explanation'               => (string) ($p->getAttribute('recommendation_explanation') ?? 'related'),
            'score'                     => (float) ($p->getAttribute('recommendation_score') ?? 0),
        ];
    }

    private function disabledPayload(): array
    {
        return ['enabled' => false, 'evidence' => 'none', 'items' => [], 'metrics' => []];
    }

    private function globallyEnabled(): bool
    {
        return (bool) config('marketplace_recommendations.features.enabled', true);
    }

    private function ttl(): int
    {
        return (int) config('marketplace_recommendations.cache.ttl_seconds', 86400);
    }

    private function cacheKey(string $type, int $productId, string $locale, int $limit): string
    {
        // v11B.2.1 §4 — include cache version. When the version is bumped
        // (by Vendor / ProductTranslation / AdminProductRelationship observers,
        // or by config-flag changes), all keys become misses without explicit
        // delete calls. Works on every cache driver — no tag support needed.
        $version = $this->currentVersion();
        return "rec:v11b2:v{$version}:{$type}:{$productId}:{$locale}:{$limit}";
    }

    /**
     * Read the current cache version. Defaults to 1 on first call.
     * The value lives in the cache itself (no DB roundtrip).
     */
    private function currentVersion(): int
    {
        return (int) Cache::rememberForever('rec:cache:version', fn () => 1);
    }

    /**
     * Bump the global version → all cached recommendations become misses.
     * Called by Vendor/Translation/Admin-relationship observers in
     * AppServiceProvider per dev §4 "Required invalidation model".
     */
    public function bumpVersion(): int
    {
        $next = $this->currentVersion() + 1;
        Cache::forever('rec:cache:version', $next);
        return $next;
    }

    /**
     * Invalidate cached recommendations for a SINGLE product (called from the
     * Product observer when status/stock/price changes per dev §23). For
     * cascade invalidation (vendor / translation / admin relationship), call
     * bumpVersion() instead.
     */
    public function invalidate(int $productId): void
    {
        foreach (['similar', 'fbt', 'also_bought'] as $type) {
            foreach (['en', 'ar'] as $locale) {
                foreach ([4, 6, 8, 12] as $limit) {
                    Cache::forget($this->cacheKey($type, $productId, $locale, $limit));
                }
            }
        }
    }
}
