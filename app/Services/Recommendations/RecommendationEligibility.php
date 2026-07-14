<?php

declare(strict_types=1);

namespace App\Services\Recommendations;

use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Builder;

/**
 * Phase 11B.2 §18 — eligibility rules applied uniformly across ALL
 * recommendation types. Centralized so the four recommendation services
 * cannot drift.
 *
 * A product is eligible to be RECOMMENDED when:
 *   - status = 'published'
 *   - published_at IS NOT NULL AND published_at <= NOW
 *   - vendor is APPROVED (not suspended, not pending, not rejected)
 *   - if track_stock = true → stock > 0 (unless exclude_out_of_stock=false in config)
 *   - the product is not the source product itself
 */
class RecommendationEligibility
{
    /**
     * Apply all eligibility filters to a Product query builder.
     * The caller should chain its own ORDER BY / LIMIT after this.
     */
    public function applyToQuery(Builder $query, ?int $excludeProductId = null): Builder
    {
        $query->where('products.status', Product::STATUS_PUBLISHED)
              ->whereNotNull('products.published_at')
              ->where('products.published_at', '<=', now());

        // Exclude the source product (we never recommend a product to itself).
        if ($excludeProductId !== null) {
            $query->where('products.id', '!=', $excludeProductId);
        }

        // Vendor must be approved. We use whereHas('vendor') with an active
        // vendor; this issues one SQL join, not N+1.
        $query->whereHas('vendor', function (Builder $v) {
            $v->where('status', Vendor::STATUS_APPROVED);
        });

        // Stock filter — only when the marketplace says exclude_out_of_stock=true
        if ((bool) config('marketplace_recommendations.eligibility.exclude_out_of_stock', true)) {
            $query->where(function (Builder $q) {
                $q->where('products.track_stock', false)
                  ->orWhere('products.stock', '>', 0);
            });
        }

        return $query;
    }

    /**
     * Cheap post-filter for an already-loaded collection of Products.
     * Useful when scores come from a precomputed table but we want to
     * defensively re-check at read time (per dev §33 — "Never trust
     * recommendation IDs submitted by the frontend").
     *
     * @param  iterable<Product> $products
     * @return list<Product>
     */
    public function filterCollection(iterable $products, ?int $excludeProductId = null): array
    {
        $excludeOos = (bool) config('marketplace_recommendations.eligibility.exclude_out_of_stock', true);
        $out = [];
        foreach ($products as $p) {
            if (! $p instanceof Product) continue;
            if ($excludeProductId !== null && $p->id === $excludeProductId) continue;
            if ($p->status !== Product::STATUS_PUBLISHED) continue;
            if ($p->published_at === null || $p->published_at > now()) continue;
            if (! $p->vendor || $p->vendor->status !== Vendor::STATUS_APPROVED) continue;
            if ($excludeOos && $p->track_stock && (int) $p->stock <= 0) continue;
            $out[] = $p;
        }
        return $out;
    }
}
