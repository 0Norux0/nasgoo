# Phase 11B.1 — Patch Notes

## Summary

Smart search, dynamic filters, and weighted relevance ranking. The first sub-release of Phase 11B (Smart Marketplace Intelligence). Deterministic algorithms only — no external AI APIs.

## Changes

### Backend — 5 new search services in `app/Services/Search/`

- **QueryNormalizer.php** — static utility: trim, mb_lowercase, Arabic diacritic stripping (U+064B–U+065F), alef variant normalization (أ إ آ → ا), alef-maqsura → ya, LIKE escaping, tokenization. No I/O.
- **SynonymService.php** — bidirectional pair expansion from `search_synonyms` table. Per-locale cache (key prefix `marketplace:search:synonyms:v1:`, TTL 3600s). `expand($term, $locale)` returns `[original, ...synonyms]`.
- **DidYouMeanService.php** — bounded Levenshtein. Per-locale dictionary capped at 1000 entries (categories + popular queries + top sales products). Cached 1 hour. Length-prune before distance calculation.
- **SearchAnalyticsService.php** — privacy-conscious analytics. Writes to `search_queries` (aggregate, PII-free) + `user_recent_searches` (user-scoped). All wrapped in try/catch.
- **MarketplaceSearchService.php** — weighted relevance Eloquent Builder. Single SQL pass per page. Excludes products with zero text-relevance signal (popularity alone can't pull unrelated products into results).

### Backend — 3 new models

- `App\Models\SearchSynonym` — normalized storage (lowercase on save via `booted()`), `is_active` flag
- `App\Models\SearchQuery` — PII-free aggregate, `scopePopular()` for blocklist/threshold filtering
- `App\Models\UserRecentSearch` — `scopeForUser()` enforces user-scoping, FK cascadeDelete

### Backend — 4 new migrations

- `2026_06_25_000001_create_search_synonyms_table.php` — locale + term + synonym + is_active + audit fields. Unique `(locale, term, synonym)`.
- `2026_06_25_000002_create_search_queries_table.php` — **PII-FREE**: no user_id, no ip_address, no session_id. Unique `(query, locale)` for atomic UPSERT. Popular index `(locale, is_blocked, search_count)`.
- `2026_06_25_000003_create_user_recent_searches_table.php` — strict user-scope via composite unique + FK cascadeDelete. Index `(user_id, locale, searched_at)`.
- `2026_06_25_000004_add_search_performance_indexes_to_products.php` — 4 composite indexes (`status×rating_avg`, `status×sales_count`, `status×views_count`, `status×price_minor`) + raw `name(64)` prefix index. All additive, idempotent.

### Backend — config + 2 controllers

- `config/marketplace_search.php` — weights (12 entries) + features (8 flags) + limits (16 thresholds) + cache TTLs (4 windows). All env-overridable via `SEARCH_*` variables.
- `app/Http/Controllers/SearchSuggestionController.php` — REWRITTEN. Uses 3 services via DI. Returns 6 groups (products, categories, services, popular, recent, did_you_mean) with strict caps. Popular + recent shown even when query is too short.
- `app/Http/Controllers/SearchRecentController.php` — NEW. `DELETE /search/recent` for authenticated users to clear their own history. Auth + throttle:30,1.

### Backend — CatalogController::index modified

- New filters: `vendor`, `price_min`, `price_max`, `rating_min` (0..5), `in_stock`, `on_sale`
- New sort options: `relevance` (default when q set), `rating`, `popular`, `best_selling` (plus existing newest/price/featured)
- Uses MarketplaceSearchService when smart_search ON + q set; falls back to v11A.5 LIKE search otherwise
- Returns `facets` dict (cached 60s) with in_stock / out_of_stock / on_sale / rating_4plus / rating_3plus counts
- Returns `did_you_mean` when total < 3
- Records search analytics (fire-and-forget; failures don't break the catalog response)

### Frontend — SearchBar.tsx

- Extended `SuggestionsPayload` with `popular?`, `recent?`, `did_you_mean?` fields
- `showDropdown` predicate now also opens on standing-data (popular/recent without query)
- New `onFocus` handler fetches `/search/suggestions?q=` when empty to show popular + recent before typing
- New dropdown sections (each with data-testid):
  - `search-did-you-mean` banner with click-to-fill-input
  - `search-recent-item` list (per-user)
  - `search-popular-item` list (anonymous)

### Frontend — Catalog/Index.tsx

- `Props` interface expanded with vendor/price/rating/in_stock/on_sale filters + active_vendor + facets + did_you_mean
- New sidebar sections:
  - Price range form (min/max inputs + Apply)
  - Rating filter (4+ / 3+ buttons with facet counts)
  - In-stock toggle button with facet count
  - On-sale toggle button with facet count
- Active filter chips (`data-testid="catalog-active-chips"`) — one chip per active filter, each with × remove
- Clear All link (`data-testid="catalog-clear-all"`)
- Did-you-mean banner (`data-testid="catalog-did-you-mean"`) — Link to `/products?q={did_you_mean}`
- Expanded no-results state (`data-testid="catalog-no-results"`) — shows query echo + try-different message + clear-all CTA
- New sort options in dropdown: relevance (only when q set), rating_desc, best_selling
- Fixed v11A.5 string-literal bug `'{t('catalog.title_all')}'` → `{t('catalog.title_all')}`
- Fixed v11A.5 ProductCardView scope bug — `useT()` now called inside the component
- New **Chip** component definition at bottom of file (was referenced 7× but never defined)

### Translation files

- `lang/en.json` — 325 → 352 keys (+27)
- `lang/ar.json` — 325 → 352 keys (+27 Modern Standard Arabic)

New translation groups:
- `search.suggestions.popular`, `search.suggestions.recent`, `search.suggestions.vendors`
- `search.did_you_mean`, `search.no_results_for`, `search.try_different`
- `catalog.filters_title`, `catalog.filters_clear_all`, `catalog.active_filters`
- `catalog.filter.{price, rating, in_stock, on_sale, vendor, category}`
- `catalog.rating.{4_plus, 3_plus, any}`
- `catalog.price.{min, max}_placeholder`
- `catalog.facet.count`
- `catalog.sort.{relevance, rating_desc, best_selling}`
- `catalog.no_results_{title, body}`, `catalog.popular_alternatives`

### Tests + CI

- **NEW** `tests/Feature/Phase11B1SmartSearchTest.php` — 53 Pest scenarios per dev §29
- `.github/workflows/ci.yml` — +11 v11B.1 sub-checks (services, models, migrations, config, controllers, route, frontend wiring, translation parity, Pest filter)

### VERSION

`Phase 11A v11A.5` → `Phase 11B.1`

## Acceptance against dev's required final result

| Requirement | Status |
|---|---|
| Weighted search relevance | ✅ 12-weight scoring formula, configurable, transparent |
| Live suggestions (6 groups) | ✅ products + categories + services + popular + recent + did_you_mean |
| Multilingual search | ✅ Arabic title via JSON_EXTRACT path; localized labels |
| Dynamic filters | ✅ category + vendor + price + rating + in_stock + on_sale |
| Filter result counts (facets) | ✅ 5 facets, single query each, cached 60s |
| Useful sorting | ✅ 8 sort options, relevance default for searches |
| Spelling normalization | ✅ trim/lowercase/Arabic-diacritic/alef-variant |
| Synonyms | ✅ bidirectional, admin-managed table, cached |
| Recent searches | ✅ user-scoped, retention-capped, user-clearable |
| Popular searches | ✅ aggregated (PII-free), threshold-gated, blocklist-respected |
| No-result alternatives | ✅ query echo + try-different message + Clear All |
| Did you mean? | ✅ bounded Levenshtein, never auto-replaces user query |
| Explainable ranking | ✅ all weights documented in config + report |
| Active filter chips | ✅ one per filter + × remove + Clear All |
| Privacy-conscious analytics | ✅ schema-level PII absence verified by Pest §29.39 |
| Feature flags | ✅ 8 flags for controlled rollback |
| MySQL-compatible indexes | ✅ 4 composite + 1 prefix, additive, idempotent |
| WCAG keyboard navigation | ✅ aria-pressed on toggles, focus rings, accessible labels |
| RTL support | ✅ inherits v11A.4 logical CSS infrastructure |

## Honest gaps (deferred to later 11B modules per dev directive)

- Admin Filament panels for synonyms / blocked-terms / search-analytics dashboard (data layer ready; UI deferred to v11B.2)
- Vendor-facing aggregate search terms (privacy-thresholded; UI deferred)
- Service search via MarketplaceSearchService (uses direct LIKE for MVP; ranking deferred)
- Tags/brands matching (placeholder weight present; column not yet added)
- Click-through tracking from suggestions (no event endpoint yet)
- Personalized recommendations (NOT in v11B.1 per directive)
- Vendor intelligence / risk scoring / pricing suggestions / reporting narratives (NOT in v11B.1 per directive)

## Counts

| Metric | v11A.5 | v11B.1 | Delta |
|---|---|---|---|
| CI sub-checks | 100 | **112** | +12 |
| Pest scenarios | 406 | **459** | +53 |
| Unique Pest helpers | 113 | **118** | +5 (p11b1_*) |
| Translation keys (en/ar) | 325 | **352** | +27 |
| New PHP files | — | **10** | search services (5) + models (3) + controller (1) + config (1) |
| New migrations | — | **4** | |
| New routes | — | **1** | DELETE /search/recent |
| Sort options | 4 | **8** | +relevance, +rating, +popular, +best_selling |
| Filter params | 3 | **9** | +vendor, +price_min, +price_max, +rating_min, +in_stock, +on_sale |
| Search service feature flags | 0 | **8** | controlled rollback |

## Deploy commands

```bash
php artisan optimize:clear
rm -rf public/build/
rm -rf node_modules/.vite/

# Apply 4 new v11B.1 migrations (all additive, MySQL-compatible)
php artisan migrate:status
php artisan migrate

# Verify routes
php artisan route:list | grep -i search   # → search.suggestions, search.recent.destroy

# Build
npm ci && npm run typecheck && npm run build

# Test
php artisan test --filter=Phase11B1   # 53 v11B.1 scenarios
php artisan test                       # 459 total
```

## Rollback

Per `PHASE_11B_1_ROLLBACK.md`:

1. Set `SEARCH_FEATURE_SMART=false` in .env → reverts catalog to v11A.5 LIKE search
2. Other features flag off similarly without code removal
3. Full revert via tar extraction from `marketplace-phase-11A-final-approved.tar.gz`
4. Down() of migrations is reversible

## Phase 11B.1 STOPS HERE

No further 11B modules begun. Pending dev verification.
