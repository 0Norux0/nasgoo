<?php

declare(strict_types=1);

use App\Models\Category;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 11A v11A.5 — backfill Arabic translations for default platform categories.
 *
 * The CategoriesSeeder has shipped with Arabic name_translations for the
 * default tree since Phase 3. However, databases seeded before that addition
 * have categories with NULL or partial name_translations. v11A.5 wires
 * Category::translatedName() into all storefront controllers — but on those
 * older databases the translation lookup falls back to English for default
 * categories that should already have Arabic.
 *
 * This migration backfills Arabic for the default tree IF AND ONLY IF the
 * row's name_translations.ar slot is empty. Admin-edited Arabic values are
 * preserved (never overwritten).
 *
 * Safe to re-run; idempotent. Operates only on rows the seeder created
 * (matched by canonical slug). Other categories (vendor-suggested, admin
 * custom) are untouched.
 *
 * NB: This does NOT add the column — name_translations already exists as a
 * JSON column on the categories table since the initial schema in
 * 2026_01_03_000001_create_categories_table.php.
 */
return new class extends Migration {

    /**
     * Canonical Arabic for the default category tree, keyed by English slug.
     * Mirror of CategoriesSeeder.php; kept here so the migration is
     * self-contained and survives seeder edits.
     */
    private array $arabicByEnglishSlug = [
        // Top-level
        'electronics'   => 'إلكترونيات',
        'fashion'       => 'أزياء',
        'home-living'   => 'المنزل',
        'beauty'        => 'الجمال',
        'sports'        => 'رياضة',
        // Electronics children (slug pattern: parent-slug-child-name)
        'electronics-phones'      => 'هواتف',
        'electronics-laptops'     => 'حواسيب محمولة',
        'electronics-accessories' => 'إكسسوارات',
        // Fashion children
        'fashion-men'   => 'رجال',
        'fashion-women' => 'نساء',
        'fashion-kids'  => 'أطفال',
        // Home & Living children
        'home-living-kitchen'   => 'المطبخ',
        'home-living-furniture' => 'أثاث',
    ];

    public function up(): void
    {
        // Schema guard — if categories table doesn't exist yet, this is a
        // fresh DB and the seeder will populate translations directly.
        if (! \Illuminate\Support\Facades\Schema::hasTable('categories')) {
            return;
        }

        // Resilient against running before the table has the column
        if (! \Illuminate\Support\Facades\Schema::hasColumn('categories', 'name_translations')) {
            return;
        }

        $updated = 0;
        $skipped = 0;

        foreach ($this->arabicByEnglishSlug as $slug => $arName) {
            $cat = Category::where('slug', $slug)->first();
            if (! $cat) {
                continue; // category not in DB (custom install / different seed)
            }

            $existing = is_array($cat->name_translations) ? $cat->name_translations : [];

            // Preserve admin edits — only backfill if 'ar' is missing or empty
            if (! empty($existing['ar'])) {
                $skipped++;
                continue;
            }

            $existing['ar'] = $arName;
            $cat->name_translations = $existing;
            $cat->saveQuietly();
            $updated++;
        }

        // Bust the homepage top_categories cache so next request hits the DB
        try {
            \Illuminate\Support\Facades\Cache::forget('marketplace:top_categories:v2');
        } catch (\Throwable) {
            // Cache may not be configured in some envs — non-fatal
        }
    }

    public function down(): void
    {
        // Rollback is intentionally a no-op. Removing Arabic translations
        // would degrade the user experience and re-running the migration
        // would simply restore them. If you need to clear all Arabic
        // translations for a specific category, do so via the Filament
        // admin (which respects the audit trail).
    }
};
