<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductTranslation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 11B.1 v11B.1.2 §3 + §11 — backfill product_translations from
 * existing JSON columns.
 *
 * Existing Arabic content shipped via:
 *   - v11A.5 backfill migration for default categories (categories table)
 *   - v11B.1.1 ArabicProductContentSeeder (4 demo products into JSON cols)
 *   - vendor-entered Arabic via v11B.1.1 forms (stored in JSON cols)
 *   - admin-entered Arabic via v11B.1.1 Filament fields (stored in JSON cols)
 *
 * All of those wrote into the JSON columns `name_translations`,
 * `short_description_translations`, `description_translations`. v11B.1.2
 * introduces the normalized `product_translations` table.
 *
 * This seeder copies each JSON entry into a normalized row with
 * status='approved' (since the data was manually entered, no review queue
 * was crossed — preserving the user-visible behavior of pre-v11B.1.2).
 *
 * IDEMPOTENT:
 *   - `updateOrCreate` on (product_id, locale, field) — re-runs are no-ops
 *   - Never overwrites a non-'missing' status that already exists
 *   - Never alters the JSON columns themselves (kept for backward compat)
 *
 * Safe to chain into DatabaseSeeder or run standalone:
 *   php artisan db:seed --class=BackfillProductTranslationsSeeder
 */
class BackfillProductTranslationsSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('product_translations')) {
            $this->command?->warn('product_translations table missing — run migrate first.');
            return;
        }

        $imported = 0;
        $skipped  = 0;

        // Stream products in chunks so we don't blow memory on large catalogs.
        Product::query()
            ->select(['id', 'name', 'short_description', 'description',
                'name_translations', 'short_description_translations',
                'description_translations'])
            ->orderBy('id')
            ->chunk(200, function ($products) use (&$imported, &$skipped) {
                foreach ($products as $p) {
                    $fieldMaps = [
                        'name'              => [$p->name_translations,              $p->name],
                        'short_description' => [$p->short_description_translations, $p->short_description],
                        'description'       => [$p->description_translations,       $p->description],
                    ];

                    foreach ($fieldMaps as $field => [$json, $sourceEn]) {
                        if (! is_array($json)) {
                            continue;
                        }
                        foreach ($json as $locale => $value) {
                            if (! is_string($value) || trim($value) === '') {
                                continue;
                            }

                            // Don't displace a translation row that already
                            // has a real status (admin may have rejected /
                            // marked stale a row we'd overwrite).
                            $existing = ProductTranslation::query()
                                ->where('product_id', $p->id)
                                ->where('locale', $locale)
                                ->where('field', $field)
                                ->first();
                            if ($existing && $existing->status !== ProductTranslation::STATUS_MISSING) {
                                $skipped++;
                                continue;
                            }

                            ProductTranslation::updateOrCreate(
                                [
                                    'product_id' => $p->id,
                                    'locale'     => $locale,
                                    'field'      => $field,
                                ],
                                [
                                    'value'             => $value,
                                    'status'            => ProductTranslation::STATUS_APPROVED,
                                    'source_provenance' => ProductTranslation::SOURCE_MANUAL,
                                    'source_checksum'   => ProductTranslation::checksum($sourceEn),
                                    'translated_at'     => now(),
                                    'reviewed_at'       => now(),
                                    // reviewed_by left null — pre-v11B.1.2 had no reviewer concept
                                ]
                            );
                            $imported++;
                        }
                    }
                }
            });

        $this->command?->info(sprintf(
            'product_translations backfill: %d imported, %d skipped (already had status).',
            $imported,
            $skipped
        ));
    }
}
