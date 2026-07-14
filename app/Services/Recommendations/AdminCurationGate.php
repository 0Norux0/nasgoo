<?php

declare(strict_types=1);

namespace App\Services\Recommendations;

use App\Models\AdminProductRelationship;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

/**
 * Phase 11B.2 v11B.2.1 §5 — single canonical helper for admin-curated
 * relationship lookups.
 *
 * Before v11B.2.1, each recommendation service queried
 * AdminProductRelationship directly and the
 * `RECOMMENDATIONS_ADMIN_CURATED_ENABLED` feature flag was not enforced
 * anywhere. This class centralizes the flag check so it cannot be
 * forgotten in a new service.
 *
 * When the flag is OFF, every method returns an empty array — no DB
 * query is issued. Algorithmic ranking applies unchanged.
 *
 * When the flag is ON, pinned/hidden/excluded/complementary relationships
 * apply according to dev §15.
 */
class AdminCurationGate
{
    /**
     * Convenience: is the master admin-curated flag on?
     */
    public function isEnabled(): bool
    {
        return (bool) config('marketplace_recommendations.features.admin_curated', true);
    }

    /**
     * Excluded + Hidden product IDs for the given source. When the flag is
     * off, returns []. When on, includes both direct entries and reciprocal
     * relationships where the source is the OTHER side.
     *
     * @return list<int>
     */
    public function excludedIdsFor(Product $source): array
    {
        if (! $this->isEnabled()) {
            return [];
        }
        return AdminProductRelationship::query()
            ->where(function (Builder $q) use ($source) {
                $q->where('product_id', $source->id)
                  ->orWhere(function (Builder $q2) use ($source) {
                      $q2->where('related_product_id', $source->id)->where('reciprocal', true);
                  });
            })
            ->whereIn('relationship_type', [
                AdminProductRelationship::TYPE_HIDDEN,
                AdminProductRelationship::TYPE_EXCLUDED,
            ])
            ->pluck('related_product_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * Pinned product IDs for the given source (preserves admin's insertion
     * order so first-pinned ranks first). Empty when flag off.
     *
     * @return list<int>
     */
    public function pinnedIdsFor(Product $source): array
    {
        if (! $this->isEnabled()) {
            return [];
        }
        return AdminProductRelationship::query()
            ->where('product_id', $source->id)
            ->where('relationship_type', AdminProductRelationship::TYPE_PINNED)
            ->orderBy('id')
            ->pluck('related_product_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * Complementary product IDs (FBT fallback per dev §14). Empty when flag off.
     *
     * @return list<int>
     */
    public function complementaryIdsFor(Product $source): array
    {
        if (! $this->isEnabled()) {
            return [];
        }
        return AdminProductRelationship::query()
            ->where('product_id', $source->id)
            ->where('relationship_type', AdminProductRelationship::TYPE_COMPLEMENTARY)
            ->orderBy('id')
            ->pluck('related_product_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }
}
