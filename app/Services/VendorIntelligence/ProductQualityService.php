<?php

declare(strict_types=1);

namespace App\Services\VendorIntelligence;

use App\Models\Product;
use App\Services\Settings\SiteSettingsService;

/**
 * Phase 11B.4 §9 §30 — deterministic per-product quality score.
 *
 * TRANSPARENT weighted score (0-100) across 6 groups:
 *   core       (default 30): title, category, price, active status
 *   media      (default 20): 1+ images, 3+ images
 *   i18n       (default 20): Arabic title, Arabic description
 *   inventory  (default 15): track_stock configured, stock > 0 or unlimited
 *   seo        (default 10): SEO slug meaningful, short_description
 *   policy     (default  5): full description present
 *
 * §30 caveat "Do not penalize products for fields the system does not
 * support" — every check here reads a field that DOES exist in the schema.
 *
 * Weights are read from siteSettings.vendor_intelligence.quality_weights
 * at compute time so admin can rebalance without a redeploy.
 */
class ProductQualityService
{
    public function __construct(
        private readonly SiteSettingsService $settings,
    ) {}

    /**
     * @return array{score:int, missing_fields:list<string>, breakdown:array<string,int>}
     */
    public function scoreProduct(Product $p): array
    {
        // Load configurable weights; validate they sum to 100 or fall back to defaults.
        $weights = (array) $this->settings->get('vendor_intelligence.quality_weights', [
            'core' => 30, 'media' => 20, 'i18n' => 20,
            'inventory' => 15, 'seo' => 10, 'policy' => 5,
        ]);
        if (array_sum($weights) !== 100) {
            $weights = [
                'core' => 30, 'media' => 20, 'i18n' => 20,
                'inventory' => 15, 'seo' => 10, 'policy' => 5,
            ];
        }

        $missing = [];

        // ─── Core (§30) ─────────────────────────────────────────────
        // 4 sub-criteria, each worth 25% of the core weight
        $coreChecks = [
            'title'    => !empty($p->name),
            'category' => !is_null($p->category_id),
            'price'    => !is_null($p->price_minor) && $p->price_minor > 0,
            'active'   => $p->status === 'published',
        ];
        $corePct = collect($coreChecks)->filter()->count() / count($coreChecks);
        foreach ($coreChecks as $k => $v) if (!$v) $missing[] = "core.{$k}";

        // ─── Media (§30) ────────────────────────────────────────────
        //
        // Phase 11B.4 v11B.5 BUG FIX:
        //   Pre-v11B.5 the code read `$p->images` and did is_array().
        //   `images` on Product is a HasMany relationship to product_images
        //   (see Product::images() line 169) — NOT a JSON attribute. The
        //   boot() method actively strips 'images' from mass-assignment.
        //   Result: is_array($p->images) is FALSE for every product, so
        //   media_score was ALWAYS 0 and every product got missing_images
        //   in the alert list. This broke the summary counters + polluted
        //   the dashboard.
        //
        // Correct: query the images() relationship via count().
        //   Use lazy loading; caller may or may not have eager-loaded.
        $imgCount = $p->images()->count();
        $mediaChecks = [
            'has_image'      => $imgCount >= 1,
            'multiple_imgs'  => $imgCount >= 3,
        ];
        $mediaPct = collect($mediaChecks)->filter()->count() / count($mediaChecks);
        if (!$mediaChecks['has_image']) $missing[] = 'media.no_image';
        if (!$mediaChecks['multiple_imgs']) $missing[] = 'media.additional_images';

        // ─── i18n (§30) — Arabic content ────────────────────────────
        $nameTr = (array) ($p->name_translations ?? []);
        $descTr = (array) ($p->description_translations ?? []);
        $i18nChecks = [
            'ar_title'       => !empty($nameTr['ar'] ?? null),
            'ar_description' => !empty($descTr['ar'] ?? null),
        ];
        $i18nPct = collect($i18nChecks)->filter()->count() / count($i18nChecks);
        if (!$i18nChecks['ar_title']) $missing[] = 'i18n.arabic_title';
        if (!$i18nChecks['ar_description']) $missing[] = 'i18n.arabic_description';

        // ─── Inventory (§30) ────────────────────────────────────────
        $invChecks = [
            'stock_configured' => (bool) $p->track_stock || $p->type === 'digital' || $p->type === 'service',
            'has_stock_or_unlimited' => !$p->track_stock || ($p->stock !== null && $p->stock > 0),
        ];
        $invPct = collect($invChecks)->filter()->count() / count($invChecks);
        if (!$invChecks['stock_configured']) $missing[] = 'inventory.stock_tracking';
        if (!$invChecks['has_stock_or_unlimited']) $missing[] = 'inventory.out_of_stock';

        // ─── SEO (§30) ──────────────────────────────────────────────
        $seoChecks = [
            'slug' => !empty($p->slug) && strlen($p->slug) >= 3,
            'short_description' => !empty($p->short_description),
        ];
        $seoPct = collect($seoChecks)->filter()->count() / count($seoChecks);
        if (!$seoChecks['slug']) $missing[] = 'seo.slug';
        if (!$seoChecks['short_description']) $missing[] = 'seo.short_description';

        // ─── Policy (§30) — full description ───────────────────────
        $policyChecks = [
            'full_description' => !empty($p->description) && strlen(strip_tags($p->description)) >= 50,
        ];
        $policyPct = collect($policyChecks)->filter()->count() / count($policyChecks);
        if (!$policyChecks['full_description']) $missing[] = 'policy.description';

        // ─── Composite score ───────────────────────────────────────
        $breakdown = [
            'core'      => (int) round($corePct    * $weights['core']),
            'media'     => (int) round($mediaPct   * $weights['media']),
            'i18n'      => (int) round($i18nPct    * $weights['i18n']),
            'inventory' => (int) round($invPct     * $weights['inventory']),
            'seo'       => (int) round($seoPct     * $weights['seo']),
            'policy'    => (int) round($policyPct  * $weights['policy']),
        ];

        $score = min(100, max(0, array_sum($breakdown)));

        return [
            'score' => $score,
            'missing_fields' => array_values($missing),
            'breakdown' => $breakdown,
        ];
    }
}
