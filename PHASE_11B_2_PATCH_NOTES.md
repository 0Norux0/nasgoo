# Phase 11B.2 — Patch Notes

## Summary

Deterministic product recommendation engine: Similar Products + Frequently Bought Together + Customers Also Bought + admin curation + privacy-safe analytics. No AI APIs. No personalization. No vendor intelligence.

## New backend (4 migrations, 4 models, 5 services, 1 command, 2 controllers)

- **Migrations**: `product_pair_stats` (canonical a<b co-occurrence), `product_recommendations` (precomputed cache), `admin_product_relationships` (pinned/hidden/complementary/excluded), `recommendation_events` (privacy-safe analytics)
- **Models**: `ProductPairStat` (+`canonical()`), `ProductRecommendation` (+4 type constants), `AdminProductRelationship` (+4 type constants), `RecommendationEvent` (+`hashSession()`)
- **Services**:
  - `RecommendationEligibility` — shared filter (published / vendor-approved / in-stock / not-source)
  - `SimilarProductService` — single-SQL weighted scoring (subcategory 50 / parent 25 / price ±10% 30 / ±25% 15 / ±50% 5 / vendor 5 / stock 2) with admin pinned/excluded
  - `FrequentlyBoughtTogetherService` — reads pair_stats with min_pair/confidence/support thresholds; complementary fallback
  - `CustomersAlsoBoughtService` — distinct-customer aggregation with privacy threshold (default 3)
  - `RecommendationManager` — top-level API with `Cache::remember` per (type/product/locale/limit)
- **Command** `recommendations:generate`: chunked, idempotent upsert, `--product=` / `--since=` / `--truncate` flags. Scheduled daily at 03:30 with `--since=2`.
- **Controllers**:
  - `CartController::addBatch()` — FBT "Add Selected" with server-side eligibility recheck + variant gate
  - `RecommendationEventsController::record()` — analytics ingestion with hashed session, rate-limited 60/min
- **Observer**: `AppServiceProvider` Product `saved()` now also invalidates recommendation cache when status/stock/price/category/vendor change.

## New Filament admin

- `AdminProductRelationshipResource` — pinned/hidden/complementary/excluded relationships with 3 page classes
- `RecommendationAnalytics` page — aggregated impressions/clicks/CTR/A2C per type + top 10 products by add-to-cart

## New frontend (3 components + types.ts)

- `Components/recommendations/types.ts` — typed payload contracts + `trackRecommendationEvent()` fire-and-forget analytics + `formatPrice()`
- `SimilarProducts.tsx` — responsive grid (2/3/4 cols), impression+click tracking
- `FrequentlyBoughtTogether.tsx` — checkboxes + combined total + Add Selected button (source locked, unavailable disabled)
- `CustomersAlsoBought.tsx` — grid (privacy-respecting; hidden if 0 items)
- `Catalog/Show.tsx` — 3 sections rendered in dev §29 order: FBT → Similar → Also Bought → Reviews

## Configuration + feature flags

`config/marketplace_recommendations.php` — feature flags (similar / fbt / also_bought / similar_services / analytics / admin_curated / cart_recommendations), scoring weights, FBT thresholds, privacy threshold (min_distinct_customers=3), cache TTL (24h), analytics attribution window (7 days).

## Routes added

- `POST /cart/items/batch` — FBT "Add Selected" with server-side recheck
- `POST /recommendations/events` — analytics ingestion (rate-limited 60/min)

## Translation keys

`lang/en.json` + `lang/ar.json` — 361 → 378 keys (+17 `recommendations.*`) in full en/ar parity. Modern Standard Arabic.

## Tests + CI

- `tests/Feature/Phase11B2RecommendationsTest.php` — 50 Pest scenarios across 4 groups
- `.github/workflows/ci.yml` — +14 v11B.2 sub-checks
- `VERSION`: `Phase 11B.1 v11B.1.2` → `Phase 11B.2`

## Required-result mapping (per dev directive)

| Required result | Status |
|---|---|
| Similar Products module | ✅ |
| Frequently Bought Together module | ✅ |
| Customers Also Bought module | ✅ |
| Similar Services (where schema supports it) | ✅ (services share Product table; SimilarProductService implicitly covers them) |
| Cold-start fallback logic | ✅ (similar uses parent-category broadening; FBT uses admin complementary; also-bought hides below privacy threshold) |
| Admin recommendation configuration | ✅ (config + Filament `AdminProductRelationshipResource`) |
| Recommendation analytics | ✅ (privacy-safe ingestion + Filament dashboard) |
| Deterministic scoring (no AI) | ✅ (all weights configurable; transparent CASE expressions in SQL) |
| Multi-vendor handling | ✅ (existing CartService routes per-vendor; no new cart mechanism) |
| Variant handling | ✅ (FBT batch rejects variable products without variant_id) |
| Privacy threshold for also-bought | ✅ (default 3 distinct customers; SQL HAVING enforces) |
| Server-side eligibility recheck for batch add | ✅ (`CartController::addBatch` re-checks every product) |
| Cache strategy with observer invalidation | ✅ (`Cache::remember` + Product saved observer) |
| Scheduled offline aggregation (no sync on customer request) | ✅ (daily 03:30 with `--since=2`) |
| Idempotent generation command | ✅ (canonical pair upsert; verified Pest §35.29) |
| No paid AI APIs | ✅ (deterministic SQL + PHP only) |
| No regression to existing subsystems | ✅ (Pest §37.49-§37.50 + all v10/v11A/v11B.1 markers preserved) |

## Counts

| Metric | v11B.1.2 | v11B.2 | Δ |
|---|---|---|---|
| CI sub-checks | 133 | **147** | +14 |
| Pest scenarios | 542 | **592** | +50 |
| Unique Pest helpers | 129 | **136** | +7 (p11b2_*) |
| Translation keys (each lang) | 361 | **378** | +17 |
| Migrations | (cumulative) | +4 | +4 |
| Models | (cumulative) | +4 | +4 |
| Services | (cumulative) | +5 | +5 |
| Filament resources | (cumulative) | +1 (3 pages) +1 page | +1 + 1 |
| Frontend recommendation components | — | **3** + types.ts | +3+1 |

## Deploy commands

```bash
php artisan optimize:clear
rm -rf public/build/ node_modules/.vite/

php artisan migrate:status | grep 2026_07_01
php artisan migrate                          # creates 4 v11B.2 tables

# Initial aggregation (post-deploy one-shot)
php artisan recommendations:generate --truncate

# Verify schedule
php artisan schedule:list | grep recommendations

# Build + test
npm ci && npm run typecheck && npm run build
php artisan test --filter=Phase11B2          # 50 v11B.2 scenarios
php artisan test                              # 592 total
```

## Rollback

See `PHASE_11B_2_ROLLBACK.md` for 3-tier rollback (config flags / partial revert / full v11B.1.2 restore).

## Phase 11B.2 STOPS HERE

No personalized homepage. No vendor intelligence. No pricing recommendations. No support assistant. No risk scoring. Pending dev verification per §43 commands + §44 evidence + §38 manual dataset.
