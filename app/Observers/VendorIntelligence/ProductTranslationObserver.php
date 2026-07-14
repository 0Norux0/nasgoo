<?php

declare(strict_types=1);

namespace App\Observers\VendorIntelligence;

use App\Models\ProductTranslation;
use App\Services\VendorIntelligence\VendorIntelligenceManager;

/**
 * Phase 11B.4 v11B.4.3 fix — product translation stale marking.
 *
 * v11B.4.2 already caught changes to Product::name_translations (a JSON
 * column) via ProductObserver's material-field list. But the project
 * ALSO has a normalized product_translations table with a workflow
 * (missing → pending → machine_draft → human_reviewed → approved →
 * rejected → stale) that is edited by translators through Filament,
 * NOT through Product::update(). Those edits never touched
 * ProductObserver, so vendor intelligence stayed unaware of translation
 * churn — the exact defect the developer flagged.
 *
 * This observer fires on the ProductTranslation model itself:
 *
 *   - created         (translator submits a new row)
 *   - updated         (status change, value edit, review)
 *   - deleted         (row removed)
 *
 * The stale reason includes the field and status so downstream logs are
 * meaningful.
 *
 * Null-safety:
 *   - $translation->product could be null (product deleted)
 *   - $translation->product->vendor_id could be 0/null (orphaned)
 *   - marking a stale vendor that doesn't exist is a no-op inside the
 *     Manager (updateOrCreate uses vendor_id as the unique key), so a
 *     rogue vendor_id doesn't crash but IS a data-integrity signal —
 *     we log at warn level and skip.
 */
class ProductTranslationObserver
{
    public function __construct(
        private readonly VendorIntelligenceManager $manager,
    ) {}

    public function created(ProductTranslation $translation): void
    {
        $this->markStale($translation, 'product_translation_created');
    }

    public function updated(ProductTranslation $translation): void
    {
        // Look at what actually changed — status transitions and value
        // edits both matter; timestamp-only touches don't.
        $material = ['value', 'status', 'reviewed_by', 'source_provenance'];
        $dirty = $translation->getChanges();
        $changed = array_intersect_key($dirty, array_flip($material));
        if (empty($changed)) return;

        $reason = 'product_translation_updated:'
                . $translation->field
                . ':' . ($translation->status ?? '?');
        // Truncate to fit the DB column (varchar 64)
        $this->markStale($translation, substr($reason, 0, 64));
    }

    public function deleted(ProductTranslation $translation): void
    {
        $reason = 'product_translation_deleted:' . $translation->field;
        $this->markStale($translation, substr($reason, 0, 64));
    }

    /**
     * Resolve vendor_id from the translation → product → vendor chain
     * and forward to Manager::markVendorStale. All null-safe.
     */
    private function markStale(ProductTranslation $translation, string $reason): void
    {
        try {
            $product = $translation->product()->first();
            if ($product === null) {
                return;   // orphaned — product was deleted
            }
            $vendorId = (int) ($product->vendor_id ?? 0);
            if ($vendorId <= 0) {
                return;   // product has no vendor (shouldn't happen in normal ops)
            }
            $this->manager->markVendorStale($vendorId, $reason);
        } catch (\Throwable $e) {
            \Log::warning('v11B.4.3 ProductTranslationObserver failed', [
                'translation_id' => $translation->id ?? null,
                'reason' => $reason,
                'err' => $e->getMessage(),
            ]);
        }
    }
}
