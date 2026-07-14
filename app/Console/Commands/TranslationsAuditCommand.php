<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Phase 11A v11A.5 §12 — translation audit.
 *
 * Identifies missing translations across:
 *   - Interface JSON keys (lang/en.json vs lang/{locale}.json)
 *   - Category name_translations
 *   - Product name_translations
 *
 * Admin-only — runs only via artisan (not exposed via HTTP). Output is a
 * human-readable summary suitable for tickets/audits, not a JSON payload.
 *
 * Usage:
 *   php artisan translations:audit ar
 *   php artisan translations:audit en   (sanity check; should show ~0 missing for en)
 *
 * Exit codes:
 *   0 — audit completed (regardless of coverage)
 *   1 — locale not supported / lang files missing
 */
class TranslationsAuditCommand extends Command
{
    protected $signature   = 'translations:audit {locale=ar : Locale to audit, e.g. ar or ur}';
    protected $description = 'Audit translation coverage for interface keys, categories, and products';

    public function handle(): int
    {
        $locale = (string) $this->argument('locale');

        // Validate locale against the marketplace's supported list
        $supported = config('marketplace.supported_locales', ['en']);
        if (! in_array($locale, $supported, true)) {
            $this->error("Locale '{$locale}' is not in supported_locales: " . implode(', ', $supported));
            return self::FAILURE;
        }

        $this->info("Phase 11A v11A.5 — translation audit for locale: {$locale}");
        $this->line(str_repeat('─', 60));

        $totalMissing = 0;
        $totalChecked = 0;

        // ─── Interface JSON keys ─────────────────────────────────────
        $this->section('Interface translation keys (lang/*.json)');
        $enPath = lang_path('en.json');
        $arPath = lang_path("{$locale}.json");

        if (! File::exists($enPath)) {
            $this->error("Missing baseline file: {$enPath}");
            return self::FAILURE;
        }
        if (! File::exists($arPath)) {
            $this->warn("No translation file for {$locale} (lang/{$locale}.json).");
            $en = json_decode(File::get($enPath), true) ?? [];
            $this->line("  Baseline {$enPath}: " . count($en) . ' keys');
            $totalMissing += count($en);
        } else {
            $en = json_decode(File::get($enPath), true) ?? [];
            $ar = json_decode(File::get($arPath), true) ?? [];
            $missing = array_diff(array_keys($en), array_keys($ar));
            $extra   = array_diff(array_keys($ar), array_keys($en));
            $this->line("  en.json:       " . count($en) . ' keys');
            $this->line("  {$locale}.json:       " . count($ar) . ' keys');
            $this->line("  Missing in {$locale}: " . count($missing) . ' keys');
            if (count($missing) > 0 && $this->getOutput()->isVerbose()) {
                foreach (array_slice($missing, 0, 20) as $k) {
                    $this->line("    - {$k}");
                }
                if (count($missing) > 20) {
                    $this->line('    ... and ' . (count($missing) - 20) . ' more (run with -v to see all)');
                }
            }
            if (count($extra) > 0) {
                $this->warn("  ⚠ {$locale}.json has " . count($extra) . " keys NOT in en.json (orphaned)");
            }
            $totalMissing += count($missing);
            $totalChecked += count($en);
        }

        // ─── Categories ─────────────────────────────────────────────
        $this->section('Category name translations');
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('categories')) {
                $totalCats = Category::count();
                $missingCats = Category::whereNotNull('name')
                    ->where(function ($q) use ($locale) {
                        $q->whereNull('name_translations')
                          ->orWhereJsonDoesntContain('name_translations', [$locale => null])
                          ->orWhere(fn ($q) => $q->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(name_translations, '$.{$locale}')) IS NULL"));
                    })
                    ->get(['id', 'slug', 'name', 'name_translations'])
                    ->filter(fn ($c) => empty($c->name_translations[$locale] ?? null))
                    ->values();

                $this->line("  Total categories: {$totalCats}");
                $this->line("  Missing {$locale}: " . $missingCats->count());

                if ($missingCats->count() > 0 && $this->getOutput()->isVerbose()) {
                    foreach ($missingCats->take(15) as $cat) {
                        $this->line("    - [{$cat->slug}] {$cat->name}");
                    }
                    if ($missingCats->count() > 15) {
                        $this->line('    ... and ' . ($missingCats->count() - 15) . ' more');
                    }
                }
                $totalMissing += $missingCats->count();
                $totalChecked += $totalCats;
            } else {
                $this->warn('  (categories table does not exist yet)');
            }
        } catch (\Throwable $e) {
            $this->warn('  ⚠ Category audit failed: ' . $e->getMessage());
        }

        // ─── Products ───────────────────────────────────────────────
        $this->section('Product name translations (published only)');
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('products')) {
                $totalProds = Product::published()->count();
                $missingProds = Product::published()
                    ->select(['id', 'slug', 'name', 'name_translations'])
                    ->get()
                    ->filter(fn ($p) => empty($p->name_translations[$locale] ?? null))
                    ->count();

                $this->line("  Total published products: {$totalProds}");
                $this->line("  Missing {$locale}:               {$missingProds}");
                $totalMissing += $missingProds;
                $totalChecked += $totalProds;
            } else {
                $this->warn('  (products table does not exist yet)');
            }
        } catch (\Throwable $e) {
            $this->warn('  ⚠ Product audit failed: ' . $e->getMessage());
        }

        // ─── Phase 11B.1 v11B.1.2 §11 — Translation workflow status ─────
        // Reports the counts in the normalized product_translations table.
        // Distinct from the JSON-column "missing" count above — this shows
        // the moderation queue depth.
        $this->section('Product translation workflow status (product_translations table)');
        try {
            if (\Schema::hasTable('product_translations')) {
                $statuses = [
                    \App\Models\ProductTranslation::STATUS_APPROVED,
                    \App\Models\ProductTranslation::STATUS_PENDING,
                    \App\Models\ProductTranslation::STATUS_MACHINE_DRAFT,
                    \App\Models\ProductTranslation::STATUS_HUMAN_REVIEWED,
                    \App\Models\ProductTranslation::STATUS_STALE,
                    \App\Models\ProductTranslation::STATUS_REJECTED,
                ];
                $rows = [];
                foreach ($statuses as $status) {
                    $count = \App\Models\ProductTranslation::query()
                        ->where('locale', $locale)
                        ->where('status', $status)
                        ->count();
                    $rows[] = [$status, $count];
                    $this->line(sprintf('  %-18s %d', $status, $count));
                }
                $totalRows = array_sum(array_column($rows, 1));
                $this->line(sprintf('  %-18s %d', 'TOTAL ROWS', $totalRows));
            } else {
                $this->warn('  (product_translations table not yet created — run migrate)');
            }
        } catch (\Throwable $e) {
            $this->warn('  ⚠ workflow audit failed: ' . $e->getMessage());
        }

        // ─── Summary ───────────────────────────────────────────────
        $this->line(str_repeat('─', 60));
        $coverage = $totalChecked > 0
            ? number_format((1 - ($totalMissing / max($totalChecked, 1))) * 100, 1)
            : 'N/A';
        $this->info("Audit summary for {$locale}:");
        $this->line("  Total items checked:  {$totalChecked}");
        $this->line("  Missing translations: {$totalMissing}");
        $this->line("  Coverage:             {$coverage}%");

        if ($totalMissing > 0) {
            $this->line('');
            $this->comment('Run with -v to see the first 20 missing keys/items.');
            $this->comment('Untranslated vendor-entered content (product names) will gracefully fall back to English via Product::translatedName().');
        }

        return self::SUCCESS;
    }

    private function section(string $title): void
    {
        $this->line('');
        $this->line("<fg=cyan>{$title}</>");
    }
}
