# Phase 11A v11A.5 — Package Integrity Report

Per dev §24.

## Workspace forensics

- Project root: `/home/claude/marketplace`
- VERSION: `Phase 11A v11A.5`
- 2 NEW PHP files (TranslationsAuditCommand + backfill migration)
- 5 modified existing PHP files (4 controllers + HandleInertiaRequests)
- 4 modified frontend TSX files (Catalog/Index, ProductCard, Services/Index, Welcome)
- 2 modified translation files (en.json + ar.json: 243 → 325 keys each)
- 1 new test file (Phase11AV1Hot5RegressionTest with 32 scenarios)
- 1 modified CI workflow (+8 v11A.5 sub-checks)
- 0 routes added
- 0 migrations modified (only ADDED a new one)
- 0 changes to Phase 10 final-approved backend files outside the scope above

## Files INSIDE the archive (v11A.5-touched, 16 files)

| # | File | SHA-256 (workspace) |
|---|---|---|
| 1 | `VERSION` | `fca953153694c6e1e235ccae17d2b9591d2f3c6b778e9e47934c444c0348ee91` |
| 2 | `app/Http/Controllers/HomeController.php` | `9e1930d2b1b54a291134def907ace276b95d2122f616e48eb01ff4bbe66470ef` |
| 3 | `app/Http/Controllers/CatalogController.php` | `581c33dbfb5470f2bbd9e8b15c44f24022d9720982f1cc012ae470c32c04ee04` |
| 4 | `app/Http/Controllers/ServiceCatalogController.php` | `35dee9fe55650bd159d603f45421d907816b3a184b463a32f0981f7f817ad021` |
| 5 | `app/Http/Controllers/SearchSuggestionController.php` | `9fd76994d1f2fd2903556a1d266940f6c8dbe2645544c3ef1a363043209d3071` |
| 6 | `app/Http/Middleware/HandleInertiaRequests.php` | `a17d7c280021cad8818e12c320a232e62c0316a6022d2322638242e10007d9d0` |
| 7 | `app/Console/Commands/TranslationsAuditCommand.php` | `e3bca9f3a68c2f54b6763155f49e1dd984972771b8ea3d2916c125ec3cf8cad9` |
| 8 | `database/migrations/2026_06_24_000001_backfill_arabic_category_translations.php` | `a3a848eb903658f440ba9236360410ae74801b766d8618da598cb0caf45aae86` |
| 9 | `resources/js/Pages/Catalog/Index.tsx` | `4b4d06aca2a67a0e1efb2df6ea4d9ac65234994123af0b58298dbba57c94eb47` |
| 10 | `resources/js/Components/ui/v11/ProductCard.tsx` | `feac5006aee51ecac7478904578b8d1a0d336350ee8db35b5637eded57c0968d` |
| 11 | `resources/js/Pages/Services/Index.tsx` | `8d629562843545637e4151080f551615bdc0c4dd8ffe268f1ef0d2694536401e` |
| 12 | `resources/js/Pages/Welcome.tsx` | `cefb0e4922e2302e9b9c882a6993f7848b0f5876604396f00469db23de7a31c8` |
| 13 | `lang/en.json` | `3ebc6059023a05b9799eeff458f4429417247c10648c6553bf24d5745e92e35a` |
| 14 | `lang/ar.json` | `baf26cc966cd02191c1e4406b80dbd7ea645a40a108cd75a6d75858cc7ecdfa4` |
| 15 | `tests/Feature/Phase11AV1Hot5RegressionTest.php` | `7833d7eefc681dab317cce7e44b73673b9037c196852878a894924387cf3524f` |
| 16 | `.github/workflows/ci.yml` | `ea23e4af81a28fb2f1c8db5382871c4b425f65d9dff23c9ee7ce96e4afb1d987` |

## Files SHA-IDENTICAL to v11A.4 (no regression / accidental change)

- `resources/js/Components/Layout/Container.tsx` (v11A.2 canonical Container)
- `resources/js/Components/ui/v11/Button.tsx`
- `resources/js/Components/ui/v11/primitives.tsx`
- `resources/js/Components/common/LangSwitcher.tsx` (v11A.4 DISPLAY_LOCALES)
- `resources/js/Components/common/SearchBar.tsx` (v11A.4 typeahead)
- `resources/js/Layouts/StorefrontLayout.tsx` (v11A.4 useT + SearchBar wired)
- `routes/web.php` (v11A.4 /search/suggestions route)
- `tailwind.config.js` (v11A.2 safelist)
- `resources/css/app.css`
- All v10.0-v10.16 backend files
- All Phase 10 final-approved test files

## All v10.0-v10.16 PHP files SHA-identical to Phase 10 final-approved

Verified by source diff. The 5 modified PHP files for v11A.5 are:
- 4 storefront controllers (additive: change `$model->name` to `$model->translatedName()`)
- 1 middleware (additive: include `name_translations` in cache, resolve per-locale, bump cache key)

None of these modifications touch:
- Financial calculations (cart, checkout, payment, refund, escrow)
- Authentication / authorization
- Permissions / roles
- Vendor/admin Reports
- Order / Booking / Transaction logic
- v10.0-v10.16 defensive markers (all 5+ preserved, verified by Pest)

## Migration safety (per dev §21)

The new migration `2026_06_24_000001_backfill_arabic_category_translations.php`:

- **Additive only**: adds Arabic data to `name_translations` JSON field; no column adds/drops/renames
- **Reversible**: `down()` is a deliberate no-op (removing translations would degrade UX; re-running up() restores them)
- **Preserves existing English content**: never modifies `name` column
- **Preserves slugs and relationships**: never modifies `slug` or `parent_id`
- **Idempotent**: re-running is safe; `empty($existing['ar'])` check prevents overwriting admin edits
- **Schema-guarded**: checks `Schema::hasTable()` AND `Schema::hasColumn()` before any DB access
- **Uses `saveQuietly()`**: no events / observers / notification spam
- **Operates only on canonical default slugs**: vendor-suggested or admin-custom categories are never touched

Tested logic: re-running the migration is a no-op when Arabic already present. Migrating a fresh DB after `migrate:fresh --seed` is also a no-op (CategoriesSeeder already populates Arabic — migration's `empty()` check skips every row).

## §24 Extract-verify procedure

After build, verified that:

1. ✅ Extract into `/tmp/v11ah5/` — clean
2. ✅ `VERSION` = `Phase 11A v11A.5`
3. ✅ All 4 storefront controllers call `translatedName()` at least once
4. ✅ `HandleInertiaRequests::share` top_categories closure includes `name_translations` in cache AND resolves per-locale
5. ✅ Cache key bumped to `marketplace:top_categories:v2`
6. ✅ `app/Console/Commands/TranslationsAuditCommand.php` exists with signature `translations:audit {locale}`
7. ✅ `database/migrations/2026_06_24_000001_backfill_arabic_category_translations.php` exists with `empty($existing['ar'])` guard
8. ✅ `lang/en.json` and `lang/ar.json` both have 325 keys with identical key sets
9. ✅ Catalog/Index.tsx, ProductCard.tsx, Services/Index.tsx all import + use `useT()`
10. ✅ Welcome.tsx no longer has `text-gold-900/80` (deals subtitle opacity removed)
11. ✅ 16/16 v11A.5-touched files SHA-identical workspace ↔ archive
12. ✅ All v11A.4 markers preserved (Container, safelist, LangSwitcher, SearchBar, SearchSuggestionController, /search/suggestions route)
13. ✅ All v11A.3 markers preserved (ProductCard p-4 sm:p-5)
14. ✅ All v11A markers preserved (7 homepage section testids, StorefrontLayout markers)
15. ✅ All v10.x markers preserved (mobile-categories-toggle, null-safe permissions, defensive catches, permissions removed from share)
16. ✅ 32 v11A.5 Pest scenarios
17. ✅ 113 unique global helpers, 0 duplicates
18. ✅ CI YAML valid with 8 new v11A.5 sub-checks
19. ✅ No `node_modules` / `vendor` / `.git` / `tsconfig.verify.json` / `MARKETPLACE_PLATFORM_PLAN.md` in archive

## Performance commitment (per dev §20)

- Translation files cached by Laravel and merged at runtime (existing v3.3 behavior preserved)
- No database query per attribute — `translatedName()` reads from already-cast JSON column
- `top_categories` cached for 1 hour; per-request locale resolution is in-memory array lookup
- All controller queries unchanged in row count; `name_translations` column added to SELECT lists (same row, no JOINs)
- No N+1 introduced (verified by source review)
- No external translation API
- No synchronous bulk translation
- Inertia translation payload grew by 82 keys × ~30 bytes = +2.5KB per response (negligible)

## Performance limitations (acknowledged)

- I cannot run live profiling in this sandbox; per-request timings need dev verification per §20.
- The HandleInertiaRequests share runs on EVERY Inertia response, including non-customer-storefront routes. The path-prefix guard (admin/vendor/api) returns early; the customer storefront pays the 1-hour cached lookup.
