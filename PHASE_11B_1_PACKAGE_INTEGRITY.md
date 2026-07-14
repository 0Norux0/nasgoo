# Phase 11B.1 — Package Integrity Report

Per dev §35.

## Workspace forensics

- Project root: `/home/claude/marketplace`
- VERSION: `Phase 11B.1`
- 10 NEW PHP files (5 search services + 3 models + 2 controllers — though SearchSuggestionController is a REWRITE)
- 1 NEW config file (config/marketplace_search.php)
- 4 NEW migration files
- 1 modified existing controller (CatalogController)
- 1 modified middleware (none — HandleInertiaRequests untouched in v11B.1)
- 2 modified frontend TSX files (SearchBar, Catalog/Index)
- 2 modified translation files (en.json + ar.json: 325 → 352 keys each)
- 1 new test file (Phase11B1SmartSearchTest with 53 scenarios)
- 1 modified CI workflow (+11 v11B.1 sub-checks)
- 1 modified routes/web.php (+1 DELETE /search/recent)
- 0 schema changes to existing v10.x tables (all v11B.1 work is in NEW tables + additive indexes)

## Files INSIDE the archive (v11B.1-touched, 24 files)

| # | File | SHA-256 (workspace) |
|---|---|---|
| 1 | `VERSION` | `f25842523af0d834f4b3d37c8d8ca85a83426c150598fe7ff0de64dc792dfaf2` |
| 2 | `app/Services/Search/QueryNormalizer.php` | `ea1f5b1a3297736e4075a3509e56aefc371a5827efb33ac7c42643f1cd2e2a71` |
| 3 | `app/Services/Search/SynonymService.php` | `d30077a25cd619ba1ac532c0753626e3a7a5bc9df320124ec0aa9bffffbfd83b` |
| 4 | `app/Services/Search/DidYouMeanService.php` | `5e4d6689cebdc081ff97980e61cad8681b33833a1faf795ee6a0cdfbb69617bb` |
| 5 | `app/Services/Search/SearchAnalyticsService.php` | `3b63cd0168c0b56476d433309c7c4973bcf5a1065c8f30880f9791835de8eec2` |
| 6 | `app/Services/Search/MarketplaceSearchService.php` | `49b3eb05da7136fe671c9cf9493b258ce5b24453deb56c928ba56248eef22174` |
| 7 | `app/Models/SearchSynonym.php` | `f31365260502f6507b49c1b7e4b049ba0db81d1657534fb5c460af44c34b2a09` |
| 8 | `app/Models/SearchQuery.php` | `4f07ca6d68b71465729027742a4bf1082e0f6531f9d798e5daad51ae27dc7e88` |
| 9 | `app/Models/UserRecentSearch.php` | `137b4785f585592504a4f898e42b09cbc560a9da74e2187fe8ae1cdd08e0acc0` |
| 10 | `app/Http/Controllers/SearchSuggestionController.php` | `6cdf5c660d072d9e62bb031761c54b8acf7d93c9d686339e4c2809f3f9db7a8f` |
| 11 | `app/Http/Controllers/SearchRecentController.php` | `72103fc9b0a93bdbf6ccd5e201b09da138afcbbe29ea95c548b7a179ba38f403` |
| 12 | `app/Http/Controllers/CatalogController.php` | `ef5622b44c388c7fa636d6604d207b7bead3f45f053c1e0e0c4ad07d1b681ed1` |
| 13 | `config/marketplace_search.php` | `92b9d0f9ec2d448d1b898ced3b30836bcff0cabf12ca0a1af74ee661aefdadb3` |
| 14 | `database/migrations/2026_06_25_000001_create_search_synonyms_table.php` | `5c77a16755f456d54980c8f2e17230a237048303803190ed78d82a7279b2c718` |
| 15 | `database/migrations/2026_06_25_000002_create_search_queries_table.php` | `7b418a90ff08a054b661931a06ab1dd009c6629551b9c455b42a00cb9cac99e2` |
| 16 | `database/migrations/2026_06_25_000003_create_user_recent_searches_table.php` | `d971fcdcdea141464e94d2f323350c23f9a1273efec2b5b0d90e661a860e661b` |
| 17 | `database/migrations/2026_06_25_000004_add_search_performance_indexes_to_products.php` | `c8f1797f035298026a44bb51f97d1c2ae95f22ff41af78abec3a010cd34806eb` |
| 18 | `routes/web.php` | `3b1600d4635f3277eae246ce863a05e8f2b76f5365a7e4cf71805027fe0e116a` |
| 19 | `resources/js/Components/common/SearchBar.tsx` | `ac4e478ace228ed1b66a208356de51245f32e086388088802e67b4f1b89a1472` |
| 20 | `resources/js/Pages/Catalog/Index.tsx` | `edc839ff816464cf917752b485423fd166e0ec4b0711c54260e18bbfebca6d11` |
| 21 | `lang/en.json` | `311d2db0c74ee9b5dceb6d64a98cf872cbf118b66ae03fad2d99909d2f06c2ca` |
| 22 | `lang/ar.json` | `46cbe383a7bb26d170ebc0e36fd69b965c59d880d5ed94df3825a81b95d2c8d8` |
| 23 | `tests/Feature/Phase11B1SmartSearchTest.php` | `7f9e1d3e62440fed750749203c024ff644dfab1e22693bbd5b6f39a40e7b94ba` |
| 24 | `.github/workflows/ci.yml` | `ed0df4ab6186daf07d3da74b8d1c95938a429175d7b6e5c045fe7f0e4020d38c` |

## Files SHA-IDENTICAL to v11A.5 (no regression / accidental change)

- `resources/js/Components/Layout/Container.tsx` (v11A.2 canonical)
- `resources/js/Components/ui/v11/Button.tsx`
- `resources/js/Components/ui/v11/primitives.tsx`
- `resources/js/Components/ui/v11/ProductCard.tsx` (v11A.5 useT)
- `resources/js/Components/common/LangSwitcher.tsx` (v11A.4 DISPLAY_LOCALES)
- `resources/js/Layouts/StorefrontLayout.tsx` (v11A.4 SearchBar wired)
- `resources/js/Pages/Welcome.tsx` (v11A.5 deals subtitle fix)
- `resources/js/Pages/Services/Index.tsx` (v11A.5 useT)
- `tailwind.config.js` (v11A.2 safelist)
- `app/Console/Commands/TranslationsAuditCommand.php` (v11A.5)
- `app/Http/Middleware/HandleInertiaRequests.php` (v11A.5 top_categories:v2 cache)
- `app/Http/Controllers/HomeController.php` (v11A.5 translatedName)
- `app/Http/Controllers/ServiceCatalogController.php` (v11A.5 translatedName)
- `app/Models/Product.php` (Phase 3 translatedName method intact)
- `app/Models/Category.php` (Phase 3 translatedName method intact)
- All v10.0-v10.16 backend files
- All Phase 10 final-approved test files

## All v10.0-v10.16 PHP files SHA-identical to Phase 10 final-approved

Verified by source diff. The v11B.1 modifications are limited to:
- 1 controller modification (CatalogController::index, additive new filters)
- 1 controller rewrite (SearchSuggestionController, same route, expanded payload)
- 1 controller creation (SearchRecentController, new route)
- 5 service creations
- 3 model creations
- 4 migration creations
- 1 config creation
- 2 frontend modifications
- 2 translation file expansions

None of these modifications touch:
- Financial calculations (cart, checkout, payment, refund, escrow, PricingService)
- Authentication / authorization
- Permissions / roles
- Vendor/admin Reports
- Order / Booking / Transaction logic
- v10.0-v10.16 defensive markers (5+ preserved, verified by Pest)
- v11A.5 translatedName() wiring (preserved)
- v11A.5 backfill migration (preserved)

## Migration safety (per dev §21 + §23)

All 4 new v11B.1 migrations:

- **Create NEW tables only** (search_synonyms, search_queries, user_recent_searches) — no ALTER on existing v10.x tables
- **Add indexes only on products** — additive, idempotent (existence-guarded), MySQL-compatible
- **All reversible** via documented down() — drops only what up() created
- **Schema-guarded** where needed (the indexes migration checks `Schema::hasTable('products')` and `SHOW INDEX` before any DDL)
- **NEVER modify existing column data**
- **NEVER modify slugs, FKs, or relationships**
- **Use cascadeOnDelete on user_recent_searches.user_id** for privacy (deleting a user purges their search history)

## Privacy verification (per dev §11 §12 §21)

Schema-level proof that the analytics table cannot store PII:

```bash
grep "$table->" database/migrations/2026_06_25_000002_create_search_queries_table.php
```

The only column declarations are:
- `id`
- `query` (string 100)
- `locale` (string 8)
- `search_count` (unsignedBigInteger)
- `last_result_count` (unsignedInteger)
- `last_searched_at` (timestamp)
- `is_blocked` (boolean)
- `timestamps()`

**Zero PII columns.** This is verified by Pest §29.39 which asserts `Schema::getColumnListing('search_queries')` does NOT contain `user_id`, `ip_address`, or `session_id`.

## §35 Extract-verify procedure

After build, verified that:

1. ✅ Extract into `/tmp/v11b1/` — clean
2. ✅ `VERSION` = `Phase 11B.1`
3. ✅ All 5 search services present in `app/Services/Search/`
4. ✅ All 3 search models present in `app/Models/`
5. ✅ All 4 migrations present (privacy contract intact)
6. ✅ `app/Console/Commands/TranslationsAuditCommand.php` preserved (v11A.5)
7. ✅ `lang/en.json` and `lang/ar.json` both have 352 keys with identical key sets
8. ✅ `config/marketplace_search.php` has weights + features + limits
9. ✅ Catalog/Index.tsx has Chip COMPONENT DEFINED (was the critical gap)
10. ✅ Catalog/Index.tsx has all v11B.1 testids
11. ✅ 24/24 v11B.1-touched files SHA-identical workspace ↔ archive
12. ✅ All v11A.5 markers preserved (translatedName in 5 sites, backfill migration, audit command, top_categories:v2)
13. ✅ All v11A.4 markers preserved (LangSwitcher dedup, Tailwind safelist, SearchBar.tsx, /search/suggestions route)
14. ✅ All v11A.3, v11A, v10.x markers preserved
15. ✅ 53 v11B.1 Pest scenarios present
16. ✅ 118 unique global helpers (113 v11A.5 + 5 p11b1_*), 0 duplicates
17. ✅ CI YAML valid with 11 new v11B.1 sub-checks
18. ✅ No `node_modules` / `vendor` / `.git` / `tsconfig.verify.json` / `MARKETPLACE_PLATFORM_PLAN.md` in archive

## Performance commitment (per dev §31)

- 5 grouped facet queries cached for 60s — no per-option N+1
- MarketplaceSearchService produces a single SQL pass (text-relevance WHERE + score CASE + ORDER BY)
- Synonym map cached per-locale for 1 hour
- Typo dictionary cached per-locale for 1 hour (bounded at 1000 entries)
- Indexes on `(status, rating_avg)`, `(status, sales_count)`, `(status, views_count)`, `(status, price_minor)`, `name(64)` for v11B.1 sort/filter patterns
- Analytics writes are fire-and-forget (try/catch wrapped)
- Pagination remains server-side (`paginate(24)->withQueryString()`)
- Result payload contains only minimum columns
- No external API calls

## Performance limitations (acknowledged)

- I cannot run live profiling in this sandbox; per-request timings need dev verification per §31.
- The dictionary build for did-you-mean is in-line on first request after cache expiry; could be moved to a background command if dictionary size or query volume justifies it (deferred to v11B.x).
- Facet cache is short (60s) to keep counts fresh; high-traffic catalogs may benefit from per-product-write invalidation (deferred).
