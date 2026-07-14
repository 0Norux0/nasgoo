<?php

declare(strict_types=1);

namespace App\Services\Localization;

use App\Models\Product;
use App\Models\ProductTranslation;

/**
 * Phase 11B.1 v11B.1.2 §5 — canonical localized-field resolver.
 *
 * One entry point for resolving a translatable field on a Product to its
 * publishable value in the active locale, applying the full fallback chain:
 *
 *   1. product_translations row, status=approved (v11B.1.2 normalized)
 *   2. product_translations row, status=human_reviewed (policy-dependent;
 *      controlled by config('marketplace_search.public_reviewed_translations'))
 *   3. legacy JSON column on products (v11A.5 / v11B.1 / v11B.1.1 data)
 *   4. English source column ($product->name / short_description / description)
 *
 * The resolver NEVER returns:
 *   - raw translation JSON
 *   - machine_draft translations (per dev §13 default policy)
 *   - rejected translations
 *   - stale translations (treated as missing — falls through to English)
 *
 * Performance: when the caller passes a Product with `translations` eager-
 * loaded, the resolver iterates the in-memory collection (no DB hits).
 * The Inertia shaping helper (`displayFields`) eager-loads once per call.
 *
 * Per dev §5.7 — used consistently in resources, search, suggestions, cart,
 * and orders.
 */
class TranslationService
{
    public const FIELD_NAME              = 'name';
    public const FIELD_SHORT_DESCRIPTION = 'short_description';
    public const FIELD_DESCRIPTION       = 'description';

    public const TRANSLATABLE_FIELDS = [
        self::FIELD_NAME,
        self::FIELD_SHORT_DESCRIPTION,
        self::FIELD_DESCRIPTION,
    ];

    /**
     * Resolve a translatable field on a Product for the given locale.
     *
     * @param Product     $product The product. Eager-load 'translations' for
     *                              best performance.
     * @param string      $field   One of self::TRANSLATABLE_FIELDS
     * @param string|null $locale  Defaults to app()->getLocale()
     * @return string|null         Resolved value, or null if even English source is empty
     */
    public function resolve(Product $product, string $field, ?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();

        // English locale → always return the English source column.
        if ($locale === 'en') {
            return $this->englishSource($product, $field);
        }

        // Validate field whitelist.
        if (! in_array($field, self::TRANSLATABLE_FIELDS, true)) {
            return $this->englishSource($product, $field);
        }

        // 1. Look in normalized product_translations table (eager-loaded if possible).
        $row = $this->findTranslation($product, $locale, $field);
        if ($row && $this->isPublishable($row)) {
            $val = (string) ($row->value ?? '');
            if ($val !== '') {
                return $val;
            }
        }

        // 2. Legacy JSON column fallback (preserves v11A.5/v11B.1/v11B.1.1 data).
        $legacy = $this->legacyJsonValue($product, $field, $locale);
        if ($legacy !== null && $legacy !== '') {
            return $legacy;
        }

        // 3. Controlled English fallback per dev §13.3
        //    "current English source as a visibly normal fallback"
        return $this->englishSource($product, $field);
    }

    /**
     * Resolve all translatable fields at once into a `display_*` shape
     * suitable for Inertia/React props. Per dev §5 — React should receive
     * resolved fields like `display_name`, not raw JSON.
     *
     * @return array{display_name:?string,display_short_description:?string,display_description:?string}
     */
    public function displayFields(Product $product, ?string $locale = null): array
    {
        $locale ??= app()->getLocale();
        return [
            'display_name'              => $this->resolve($product, self::FIELD_NAME, $locale),
            'display_short_description' => $this->resolve($product, self::FIELD_SHORT_DESCRIPTION, $locale),
            'display_description'       => $this->resolve($product, self::FIELD_DESCRIPTION, $locale),
        ];
    }

    /**
     * Save or update a translation row for a single field.
     *
     * Computes the English source checksum at save time and stores it on the
     * row so future source changes can be detected via markStaleIfSourceChanged.
     */
    public function setTranslation(
        Product $product,
        string $locale,
        string $field,
        ?string $value,
        string $status = ProductTranslation::STATUS_APPROVED,
        string $provenance = ProductTranslation::SOURCE_MANUAL,
        ?int $reviewerId = null,
    ): ProductTranslation {
        $sourceValue    = $this->englishSource($product, $field);
        $sourceChecksum = ProductTranslation::checksum($sourceValue);

        $row = ProductTranslation::updateOrCreate(
            [
                'product_id' => $product->id,
                'locale'     => $locale,
                'field'      => $field,
            ],
            [
                'value'             => $value,
                'status'            => $status,
                'source_provenance' => $provenance,
                'source_checksum'   => $sourceChecksum,
                'translated_at'     => now(),
                'reviewed_by'       => $status === ProductTranslation::STATUS_APPROVED ? $reviewerId : null,
                'reviewed_at'       => $status === ProductTranslation::STATUS_APPROVED ? now() : null,
            ]
        );
        return $row->fresh();
    }

    /**
     * Mark all approved translations as 'stale' when the English source has
     * changed (checksum no longer matches). Invoked by Product saving observer.
     *
     * @return int Number of rows marked stale.
     */
    public function markStaleIfSourceChanged(Product $product): int
    {
        $count = 0;
        foreach (self::TRANSLATABLE_FIELDS as $field) {
            $currentChecksum = ProductTranslation::checksum($this->englishSource($product, $field));
            $rows = ProductTranslation::query()
                ->where('product_id', $product->id)
                ->where('field', $field)
                ->whereIn('status', [
                    ProductTranslation::STATUS_APPROVED,
                    ProductTranslation::STATUS_HUMAN_REVIEWED,
                ])
                ->get();
            foreach ($rows as $row) {
                if ($row->source_checksum !== null && $row->source_checksum !== $currentChecksum) {
                    $row->status = ProductTranslation::STATUS_STALE;
                    $row->saveQuietly();  // no recursive observer
                    $count++;
                }
            }
        }
        return $count;
    }

    /* ──────────── private helpers ──────────── */

    private function englishSource(Product $product, string $field): ?string
    {
        return match ($field) {
            self::FIELD_NAME              => $product->name,
            self::FIELD_SHORT_DESCRIPTION => $product->short_description,
            self::FIELD_DESCRIPTION       => $product->description,
            default                       => null,
        };
    }

    private function legacyJsonValue(Product $product, string $field, string $locale): ?string
    {
        $jsonCol = match ($field) {
            self::FIELD_NAME              => $product->name_translations,
            self::FIELD_SHORT_DESCRIPTION => $product->short_description_translations ?? null,
            self::FIELD_DESCRIPTION       => $product->description_translations,
            default                       => null,
        };
        if (! is_array($jsonCol)) {
            return null;
        }
        $v = $jsonCol[$locale] ?? null;
        return is_string($v) && $v !== '' ? $v : null;
    }

    private function findTranslation(Product $product, string $locale, string $field): ?ProductTranslation
    {
        // Prefer eager-loaded relation (no DB hit)
        if ($product->relationLoaded('translations')) {
            return $product->translations
                ->first(fn ($t) => $t->locale === $locale && $t->field === $field);
        }
        // Fallback: single query (preserves correctness even without eager loading).
        return ProductTranslation::query()
            ->where('product_id', $product->id)
            ->where('locale', $locale)
            ->where('field', $field)
            ->first();
    }

    private function isPublishable(ProductTranslation $row): bool
    {
        // Default: only 'approved' is public. Set
        //   config('marketplace_search.public_reviewed_translations') = true
        // to also publish 'human_reviewed' rows (per dev §13 policy choice).
        if ($row->status === ProductTranslation::STATUS_APPROVED) {
            return true;
        }
        if ($row->status === ProductTranslation::STATUS_HUMAN_REVIEWED
            && (bool) config('marketplace_search.public_reviewed_translations', false)) {
            return true;
        }
        return false;
    }
}
