# Phase 11B.1 v11B.1.1 — Patch Notes

## Summary

Two surgical defect fixes from v11B.1 testing. No redesign.

## Defects fixed

1. **Arabic product search didn't work** because no products had Arabic content — vendors/admins had no UI to enter Arabic, the `short_description_translations` column was missing, and accessor methods existed only for `name`.
2. **Mobile suggestions didn't appear** because the mobile drawer used a plain `<input>` form, not the `<SearchBar>` component — `/search/suggestions` was never called on mobile.

## Changes

### Backend

- **NEW migration** `2026_06_27_000001_add_short_description_translations_to_products.php` — additive JSON column, idempotent (`Schema::hasColumn` guard), reversible
- **Product model** — added `translatedShortDescription()`, `translatedDescription()`, `translationStatus()` accessors; added `short_description_translations` to `$fillable` + cast as `array`
- **VendorProductController** — accepts `name_ar` / `short_description_ar` / `description_ar` in both `store()` and `update()`; new private `foldTranslationFields()` helper folds flat form fields → JSON columns while preserving other locales; `edit()` surfaces flat fields + `translation_status` to the Inertia view
- **CatalogController::show** — passes `translatedShortDescription()` and `translatedDescription()` instead of raw English columns
- **MarketplaceSearchService** — extended scoring expression + eligibility-WHERE to match `short_description_translations.ar` via JSON_EXTRACT when locale=ar (now 3 Arabic JSON sites total: title score, title eligibility, short_description for both)
- **Filament admin ProductResource** — added collapsed "Arabic translations (optional)" section with 3 dot-keyed fields (`name_translations.ar`, `short_description_translations.ar`, `description_translations.ar`); RTL via `extraInputAttributes(['dir' => 'rtl', 'lang' => 'ar'])`

### Frontend

- **Vendor/Products/Create.tsx** — added 3 Arabic input fields with `dir="rtl"` `lang="ar"`; testids `vendor-product-name-ar`, `vendor-product-short-desc-ar`, `vendor-product-desc-ar`; Form type extended
- **Vendor/Products/Edit.tsx** — same 3 Arabic input fields + a translation completeness indicator panel (`vendor-product-translation-status`) with ✅/⚠️ per field; Props type expanded with translation_status
- **StorefrontLayout.tsx** — replaced the mobile drawer's plain `<form><input type="search">` with `<div data-testid="mobile-drawer-search"><SearchBar variant="mobile" /></div>`; removed now-dead `searchQuery` state, `handleSearch` handler, and unused `FormEvent` import (−17 net lines)

### Seeders

- **NEW** `database/seeders/ArabicProductContentSeeder.php` — idempotent Arabic backfill for 4 known demo products (`wireless-bluetooth-headphones`, `cotton-t-shirt-classic-fit`, `stainless-steel-water-bottle`, `handwoven-beach-towel`); Modern Standard Arabic translations of name + short_description + description; `empty($existing['ar'])` guard preserves admin/vendor edits; uses `saveQuietly()`
- **DatabaseSeeder** — calls `ArabicProductContentSeeder` AFTER `DemoSeeder` so slugs exist

### Translation files

- `lang/en.json` — 352 → 361 keys (+9 vendor form labels)
- `lang/ar.json` — 352 → 361 keys (+9 Modern Standard Arabic)

### Tests + CI

- **NEW** `tests/Feature/Phase11B11ArabicMobileTest.php` — 46 Pest scenarios across 6 groups (Arabic product content / Arabic display / Arabic search / suggestions / mobile / regression)
- `.github/workflows/ci.yml` — +10 v11B.1.1 sub-checks (migration, model accessors, controller, vendor forms, Filament, search service, catalog, mobile drawer, seeder, Pest filter)

### VERSION

`Phase 11B.1` → `Phase 11B.1 v11B.1.1`

## Required result mapping (per dev directive)

| Required result | Status |
|---|---|
| Products can store Arabic names and descriptions | ✅ via name_translations / short_description_translations / description_translations JSON columns |
| Arabic product values display when Arabic is selected | ✅ via Product::translatedName / translatedShortDescription / translatedDescription (5 controllers + Show.tsx) |
| English fallback works when Arabic is absent | ✅ controlled `?? $this->name` pattern; no auto-translation |
| Arabic product search finds Arabic titles | ✅ MarketplaceSearchService matches name_translations.ar via JSON_EXTRACT |
| Arabic suggestions show localized product names | ✅ SearchSuggestionController returns translatedName($locale) |
| Mobile suggestions appear and work at all supported mobile widths | ✅ mobile drawer now uses SearchBar variant=mobile (same endpoint as desktop) |
| Desktop suggestions remain working | ✅ desktop variant unchanged; Pest §18.38 regression |
| No existing marketplace function regresses | ✅ 8 regression scenarios pass; all v10.x/v11A.x markers preserved |
| Performance remains acceptable | ✅ 1 query per search operation; no N+1; design analysis in REPORT.md §31 |

## Counts

| Metric | v11B.1 | v11B.1.1 | Delta |
|---|---|---|---|
| CI sub-checks | 112 | **122** | +10 |
| Pest scenarios | 459 | **505** | +46 |
| Unique Pest helpers | 118 | **123** | +5 (p11b11_*) |
| Translation keys (en/ar) | 352 | **361** | +9 |
| Migrations | (v11B.1 added 4) | +1 | +1 |
| Product model accessors | 1 | **4** | +3 (translatedShortDescription, translatedDescription, translationStatus) |

## Deploy commands

```bash
php artisan optimize:clear
rm -rf public/build/ node_modules/.vite/

# Apply the additive migration
php artisan migrate:status | grep 2026_06_27
php artisan migrate

# Backfill 4 demo products with Arabic (idempotent)
php artisan db:seed --class=ArabicProductContentSeeder

# Build + test
npm ci && npm run typecheck && npm run build
php artisan test --filter=Phase11B11   # 46 v11B.1.1 scenarios
php artisan test                        # 505 total
php artisan translations:audit ar
```

## Rollback

The v11B.1.1 migration is reversible; the seeder is idempotent. To revert:

```bash
# 1. Drop the v11B.1.1 column (existing data unaffected — column nullable)
php artisan migrate:rollback --step=1

# 2. Restore v11B.1 archive over current workspace
tar -xzf marketplace-phase-11B-1-smart-search.tar.gz --strip-components=1 --overwrite

# 3. Clear caches + rebuild
php artisan optimize:clear
rm -rf public/build/ node_modules/.vite/
npm ci && npm run build

# Verify
cat VERSION   # → Phase 11B.1
```

The v11B.1.1 Arabic content stored in `name_translations.ar` / `description_translations.ar` will REMAIN in v11B.1's existing columns — v11B.1 already supported those JSON paths but had no UI to populate them. Reverting v11B.1.1 hides the UI but keeps the data accessible to v11B.1's Arabic search.

## Phase 11B.1 v11B.1.1 STOPS HERE

No Phase 11B.2 work begun.
