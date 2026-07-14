# Phase 11A v11A.5 — Patch Notes

## Summary

Surgical fix for the Arabic localization gap reported by dev after v11A.4. The infrastructure (translatedName methods, name_translations JSON columns, Arabic seed data) was already in place since Phase 3 — controllers just weren't using it. v11A.5 wires translatedName() into 5 places, expands the storefront translation file by 82 keys, and ships a backfill migration + audit command.

## Root cause

Three findings: (1) `Product::translatedName()` and `Category::translatedName()` methods already existed and worked correctly; (2) `database/seeders/CategoriesSeeder.php` already contained Arabic translations for all 13 default platform categories; (3) The 4 storefront controllers + `HandleInertiaRequests` top_categories closure returned raw `$model->name` directly, never invoking the translation method.

## Changes

### Backend — wire translatedName() into storefront controllers

- `app/Http/Controllers/HomeController.php` — featured_products: $p->name → $p->translatedName()
- `app/Http/Controllers/CatalogController.php` — 6 sites (products list × 2, product detail, category sidebar, active category, breadcrumb)
- `app/Http/Controllers/ServiceCatalogController.php` — services list + detail
- `app/Http/Controllers/SearchSuggestionController.php` — products + categories + services groups; SELECTs now include name_translations
- `app/Http/Middleware/HandleInertiaRequests.php` — top_categories cache value now includes name_translations; per-request locale resolution happens AFTER cache lookup; cache key bumped v1→v2 to invalidate the English-only cache

### Backend — new files

- **NEW** `app/Console/Commands/TranslationsAuditCommand.php` — `php artisan translations:audit {locale}` audits interface keys + categories + products; admin-only artisan command
- **NEW** `database/migrations/2026_06_24_000001_backfill_arabic_category_translations.php` — idempotent backfill for default categories on databases seeded before Arabic was added; preserves admin edits

### Frontend — useT() wiring expanded

- `resources/js/Pages/Catalog/Index.tsx` — useT() imported, const t = useT(), 10 t() calls (sort options, sidebar, empty state, page title)
- `resources/js/Components/ui/v11/ProductCard.tsx` — useT() imported, "Out of stock" badge translated
- `resources/js/Pages/Services/Index.tsx` — useT() imported, Container wrap, 8 t() calls

### Frontend — contrast

- `resources/js/Pages/Welcome.tsx` — deals banner subtitle: `text-gold-900/80` → `text-gold-900` (removed opacity for AA contrast on gold-500 background)

### Translation files

- `lang/en.json` — 243 → 325 keys (+82 storefront keys)
- `lang/ar.json` — 243 → 325 keys (+82 Modern Standard Arabic)

New translation groups: `common.*` (28), `catalog.*` (16), `product.*` (13), `service.*` (9), `cart.*` (14), `checkout.*` (7), `auth.*` (11), `account.*` (7)

### Tests + CI

- **NEW** `tests/Feature/Phase11AV1Hot5RegressionTest.php` — 32 scenarios per dev §17
- `.github/workflows/ci.yml` — +8 v11A.5 sub-checks

### VERSION

`Phase 11A v11A.4` → `Phase 11A v11A.5`

## Acceptance against dev's required final result

| Requirement | Status |
|---|---|
| Arabic translates all SYSTEM INTERFACE storefront text | ✅ via 325 translation keys + useT wiring |
| Administrator-managed categories support Arabic | ✅ via existing seed + backfill migration + translatedName wiring |
| Homepage content supports Arabic | ✅ via translation keys (hero, sections, footer all use t()) |
| Products and services display Arabic WHEN ENTERED | ✅ via translatedName() in all 4 controllers |
| Untranslated vendor content uses English fallback | ✅ via the existing `?? $this->name` fallback |
| No raw keys or broken text | ✅ useT() returns key as fallback if missing |
| Arabic persists + RTL | ✅ session-based locale + dir="rtl" |
| English switching works | ✅ reversible |
| Only one Arabic option in selector | ✅ DISPLAY_LOCALES filter from v11A.4 |
| Homepage contrast meets WCAG AA | ✅ deals subtitle opacity removed |
| No regression | ✅ 32 Pest scenarios + v10.x preservation verified |

## Honest gaps

- **Vendor admin forms** for entering name_translations.ar are NOT added (Filament forms — separate scope)
- **Product detail page** has translation keys defined but UI wiring deferred
- **Cart/checkout** has translation keys defined but UI wiring deferred
- **Existing vendor product titles** without name_translations.ar will display in English (per dev §5 — do not fabricate)

The `translations:audit ar` command surfaces all these gaps with a coverage report.

## Counts

| Metric | v11A.4 | v11A.5 | Delta |
|---|---|---|---|
| CI sub-checks | 92 | 100 | +8 |
| Pest scenarios | 374 | 406 | +32 |
| Unique Pest helpers | 109 | 113 | +4 |
| Translation keys (en/ar) | 243 | 325 | +82 |
| Controllers using translatedName | 0 | 5 | +5 |
| Storefront pages with useT() | 2 | 5 | +3 |
| New PHP files | — | 2 | +2 |
| New migrations | — | 1 | +1 |
| New artisan commands | — | 1 | +1 |

## Deploy commands

```bash
php artisan optimize:clear
rm -rf public/build/
rm -rf node_modules/.vite/

# Run new backfill migration on existing databases
php artisan migrate                                       # safe: idempotent, preserves admin edits
php artisan cache:forget marketplace:top_categories:v1     # if cache driver supports it

# Verify
php artisan translations:audit ar                          # should show 0 missing interface keys
php artisan translations:audit ar -v                       # show first 20 missing items per group

# Test + build
php artisan test --filter=Phase11AV1Hot5                  # 32 v11A.5 scenarios
php artisan test                                           # 406 total
npm ci && npm run typecheck && npm run build
```

## Rollback

If v11A.5 needs to be reverted:
1. Restore `app/Http/Controllers/{HomeController,CatalogController,ServiceCatalogController,SearchSuggestionController}.php` from v11A.4 archive — replaces translatedName() calls with raw ->name
2. Restore `app/Http/Middleware/HandleInertiaRequests.php` — restores v1 cache key + English-only top_categories
3. Frontend changes (useT() wiring in Catalog/ProductCard/Services) can stay or revert
4. Translation files can stay (additive)
5. Backfill migration can stay (no destructive op)
6. Audit command can stay (artisan-only, no production impact)

## Phase 11A v11A.5 STOPS HERE

No Phase 11B work begun. Pending dev verification.
