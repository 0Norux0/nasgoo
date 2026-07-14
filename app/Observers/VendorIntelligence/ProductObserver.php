<?php

declare(strict_types=1);

namespace App\Observers\VendorIntelligence;

use App\Models\Product;
use App\Services\VendorIntelligence\VendorIntelligenceManager;

/**
 * Phase 11B.4 v11B.4.2 Defect 11 fix — mark vendor intelligence stale
 * whenever a product's material data changes.
 *
 * Pre-v11B.4.2 the dashboard could go many hours between updates. Now:
 *   1. This observer flips `vendor_intelligence_summaries.stale_at`
 *   2. Hourly `vendor-intelligence:generate --stale-only` regenerates
 *      only those vendors
 *   3. The panel shows a "Last generated: X" indicator so vendors
 *      know if their data is fresh
 *
 * Why NOT synchronously regenerate here? Because a bulk product import
 * could trigger 10k regenerations. Marking stale is O(1); regeneration
 * happens on a scheduled bounded cadence.
 */
class ProductObserver
{
    public function __construct(
        private readonly VendorIntelligenceManager $manager,
    ) {}

    public function created(Product $product): void
    {
        $this->manager->markVendorStale($product->vendor_id, 'product_created');
    }

    public function updated(Product $product): void
    {
        // Only mark stale if a MATERIAL field changed — cosmetic edits
        // (touched at, view counter) shouldn't queue regeneration.
        $material = ['name', 'name_translations', 'description_translations',
                     'stock', 'track_stock', 'status', 'price_minor',
                     'category_id', 'short_description', 'description'];
        $dirty = $product->getChanges();
        if (empty(array_intersect_key($dirty, array_flip($material)))) {
            return;
        }
        $this->manager->markVendorStale($product->vendor_id,
            'product_updated:' . implode(',', array_keys(array_intersect_key($dirty, array_flip($material)))));
    }

    public function deleted(Product $product): void
    {
        $this->manager->markVendorStale($product->vendor_id, 'product_deleted');
    }
}
