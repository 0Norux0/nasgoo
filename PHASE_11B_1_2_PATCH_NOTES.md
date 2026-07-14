# Phase 11B.1 v11B.1.2 — Patch Notes

## Summary

Two surgical defect fixes. No Phase 11B.2, no recommendations, no vendor intelligence.

## Defects fixed

1. **Arabic localization architecture lacked a real translation status workflow** — v11B.1.1's JSON-column approach treated every entry as immediately published. No approval gating, no stale detection, no source provenance, no reviewer audit, no admin moderation workspace.
2. **Search suggestions failed on the Products page** — `Catalog/Index.tsx` had its own plain `<form><input>` toolbar that never called `/search/suggestions`. The third search entry point bypassed the SearchBar component entirely.

## Changes

### Backend — translation status architecture

- **NEW migration** `2026_06_28_000001_create_product_translations_table.php` — normalized table with full status workflow, source_checksum, source_provenance, reviewed_by audit, FK cascade. Idempotent.
- **NEW model** `ProductTranslation` — 7 status constants (missing/pending/machine_draft/human_reviewed/approved/rejected/stale), 3 provenance constants, 5 query scopes, `checksum()` SHA-256 helper.
- **NEW service** `TranslationService` (`app/Services/Localization/`) — canonical 4-step resolver: normalized table (approved) → JSON column (legacy) → English source. Includes `displayFields()` for Inertia shaping, `setTranslation()` upsert, `markStaleIfSourceChanged()` for source-change detection. Eager-load fast path (zero per-row queries when `with('translations')` is used).
- **NEW interface** `TranslationProviderInterface` — provider abstraction so the marketplace functions WITHOUT any external translation API per dev §7.
- **NEW provider** `ManualTranslationProvider` — default zero-network binding. No paid API, no silent network calls.
- **NEW job** `QueueProductTranslation` (ShouldQueue, tries=3, timeout=60) — async per dev §8; creates pending row by default; never overwrites approved/human_reviewed content; failures logged, never thrown.
- **Modified** `AppServiceProvider` — binds provider interface, registers `Product::saved()` observer for automatic stale detection when English source columns change.
- **Modified** `Product` model — all 3 translation accessors (translatedName/Short/Description) now delegate to TranslationService for unified resolution. Added `translations()` HasMany relation.
- **Modified** `VendorProductController` — new private `persistTranslations()` helper writes vendor-entered Arabic into the normalized table with status=approved + source_checksum + reviewer audit. Called from both store() and update().
- **NEW seeder** `BackfillProductTranslationsSeeder` — idempotent migration of v11A.5/v11B.1/v11B.1.1 JSON-column translations into the normalized table with status=approved + computed source_checksum. Wired into DatabaseSeeder.
- **Modified** `TranslationsAuditCommand` — extended to report product_translations workflow status counts (approved/pending/machine_draft/human_reviewed/stale/rejected).

### Backend — Filament admin translation workspace

- **NEW resource** `ProductTranslationResource` (navigation group: Localization, icon: language)
  - Source English value shown side-by-side with editable Arabic textarea (`dir="rtl" lang="ar"`)
  - All 7 status filters
  - Bulk approve action (sets status=approved + reviewed_by + reviewed_at)
  - Bulk reject action
  - Status badges (green/yellow/red/gray)
  - Default sort: translated_at desc (newest moderation queue first)
- **NEW pages** `ListProductTranslations` + `EditProductTranslation`

### Frontend — Products-page search fix (defect 2)

- **Modified** `Catalog/Index.tsx`:
  - Replaced `<form onSubmit={submitSearch}><input data-testid="catalog-search-input">` with `<SearchBar variant="desktop" instanceId="catalog-toolbar" initialQuery={q} />`
  - Removed dead `submitSearch` handler
  - Removed `[q, setQ]` useState (SearchBar manages own input; we just pass `filters.q ?? ''` as initial)
  - Added `import SearchBar from '@/Components/common/SearchBar'`

### Frontend — SearchBar multi-instance accessibility (§23+§24)

- **Modified** `SearchBar.tsx`:
  - Added `instanceId?: string` prop
  - Added `useId()` auto-default for anonymous mounts
  - Namespaced `listboxId = ` `` `search-suggestions-listbox-${namespace}` ``
  - Namespaced `itemId(idx)` helper
  - All 6 hardcoded ID references replaced (aria-controls, aria-activedescendant, listbox id, 3× option ids)
  - **Closes a latent ARIA bug**: pre-v11B.1.2, header + drawer + catalog SearchBar mounts would share the same DOM id "search-suggestions-listbox" — duplicate IDs in HTML and broken screen-reader attachment

### Tests + CI + VERSION

- **NEW** `tests/Feature/Phase11B12LocalizationTest.php` — 37 Pest scenarios (translation workflow / resolver / stale detection / Products-page search / multi-instance isolation / regression)
- `.github/workflows/ci.yml` — +11 v11B.1.2 sub-checks
- `VERSION`: `Phase 11B.1 v11B.1.1` → `Phase 11B.1 v11B.1.2`

## Required-result mapping (per dev directive)

| Required result | Status |
|---|---|
| Resource-based localization architecture comparable to mature systems | ✅ normalized product_translations table + status workflow + source_checksum |
| Translation status workflow (missing/pending/machine_draft/human_reviewed/approved/rejected/stale) | ✅ all 7 states in ProductTranslation model |
| Controlled fallback | ✅ TranslationService 4-step chain (approved → human_reviewed if policy → JSON legacy → English) |
| Translation status + workflow | ✅ TranslationService + admin Filament workspace |
| RTL layout for Arabic | ✅ preserved from v11A.4; new Filament textarea uses `dir="rtl" lang="ar"` |
| Locale-aware formatting | ✅ via existing Laravel + JS Intl APIs (unchanged) |
| Search of translated fields | ✅ unchanged from v11B.1 (JSON_EXTRACT on name_translations.ar) |
| No paid translation dependency | ✅ TranslationProviderInterface + ManualTranslationProvider default |
| Async translation workflow | ✅ QueueProductTranslation job (ShouldQueue) |
| Admin moderation workspace | ✅ Filament ProductTranslationResource with bulk approve/reject + 7 filters |
| Stale detection when source changes | ✅ AppServiceProvider Product::saved() observer + markStaleIfSourceChanged() |
| Source-content checksum tracking | ✅ source_checksum (SHA-256) column |
| Vendor bilingual form | ✅ preserved from v11B.1.1; now also writes to normalized table |
| Provider abstraction | ✅ TranslationProviderInterface |
| Products-page mobile suggestions | ✅ SearchBar instance with instanceId="catalog-toolbar" |
| One shared autocomplete engine | ✅ SearchBar used by all 3 mounts (header / drawer / catalog) |
| Multi-instance accessibility | ✅ instanceId + useId() — unique listbox/option DOM IDs per mount |
| No regression to working subsystems | ✅ 6 regression scenarios pass; all v10.x/v11A.x/v11B.1.x markers preserved |

## Counts

| Metric | v11B.1.1 | v11B.1.2 | Delta |
|---|---|---|---|
| CI sub-checks | 122 | **133** | +11 |
| Pest scenarios | 505 | **542** | +37 |
| Unique Pest helpers | 123 | **129** | +6 (p11b12_*) |
| Migrations | (cumulative) | +1 | +1 product_translations table |
| Seeders | (cumulative) | +1 | +1 backfill seeder |
| Models | (cumulative) | +1 | +1 ProductTranslation |
| Services | (cumulative) | +1 | +1 TranslationService |
| Interfaces + Providers | — | +2 | +1 interface, +1 manual impl |
| Queue jobs | (cumulative) | +1 | +1 QueueProductTranslation |
| Filament resources | (cumulative) | +1+2 pages | ProductTranslationResource + List/Edit pages |
| Concurrent safe SearchBar instances | 2 | **3** | header + drawer + catalog (all unique IDs) |

## Deploy commands

```bash
php artisan optimize:clear
rm -rf public/build/ node_modules/.vite/

php artisan migrate:status | grep 2026_06_28
php artisan migrate                                          # creates product_translations
php artisan db:seed --class=BackfillProductTranslationsSeeder   # migrates JSON → normalized

php artisan translations:audit ar   # now reports workflow status counts
php artisan route:list | grep -i search

npm ci && npm run typecheck && npm run build
php artisan test --filter=Phase11B12   # 37 v11B.1.2 scenarios
php artisan test                        # 542 total
```

## Rollback

### Tier 1 — config-level
- `MARKETPLACE_PUBLIC_REVIEWED=true` enables human_reviewed translations on the storefront
- All v11B.1 SEARCH_FEATURE_* flags remain available

### Tier 2 — partial revert (keeps table for data preservation)
```bash
tar -xzf marketplace-phase-11B-1-1-arabic-mobile-search.tar.gz \
    --strip-components=1 --overwrite \
    marketplace/app/Services/Localization/TranslationService.php \
    marketplace/app/Models/Product.php \
    marketplace/app/Providers/AppServiceProvider.php \
    marketplace/resources/js/Components/common/SearchBar.tsx \
    marketplace/resources/js/Pages/Catalog/Index.tsx
php artisan optimize:clear && npm run build
```

### Tier 3 — full revert to v11B.1.1
```bash
php artisan migrate:rollback --step=1            # drops product_translations
tar -xzf marketplace-phase-11B-1-1-arabic-mobile-search.tar.gz \
    --strip-components=1 --overwrite
php artisan optimize:clear && npm ci && npm run build
cat VERSION   # → Phase 11B.1 v11B.1.1
```

The legacy JSON columns remain populated, so v11B.1.1's resolver still reads them — full Arabic content visible after revert.

## Phase 11B.1 v11B.1.2 STOPS HERE

No Phase 11B.2. Pending dev verification per §27 + §28 + §31.
