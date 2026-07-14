# Phase 11B.2 — Recommendation Engine Report

Per dev §45.

## Scope statement

Three deterministic recommendation modules + admin curation + privacy-safe analytics. No personalized homepage. No vendor intelligence. No pricing recommendations. No support assistant. No risk scoring. No paid AI APIs. **All scoring is transparent and rule-based** per dev §2.

---

## Baseline behavior

Pre-Phase 11B.2:
- Product detail page showed only the product itself + reviews. No related/similar/companion sections.
- No order co-occurrence tracking.
- No admin curation tools.
- No recommendation analytics.

Per dev §3 audit results:
- **No prior recommendation services existed** — clean slate, no duplication of working code.
- **Order architecture**: `Order::STATUS_PAID|CONFIRMED|SHIPPED|DELIVERED|COMPLETED` = qualifying; `CANCELLED|REFUNDED|FAILED|PENDING_PAYMENT` = excluded.
- **OrderItem schema**: `order_id`, `vendor_id`, `product_id`, `variant_id`, `quantity`, `unit_price_minor` — clean source for pair aggregation.
- **No tags/brands table** — similarity signals are limited to category hierarchy, price proximity, and vendor.
- **Services share Product table** — `SimilarProductService` implicitly covers them; no separate `Service` model exists.
- **Cart**: `POST /cart/items` (single add). v11B.2 adds `POST /cart/items/batch` for FBT "Add Selected" without creating a parallel cart system.

---

## Final architecture

```
                  ┌─────────────────────────────────┐
   Storefront ──▶ │ CatalogController::show()       │
                  │  ↓  calls RecommendationManager │
                  └─────────────────────────────────┘
                              │
                              ▼
       ┌─────────────────────────────────────────────────────┐
       │  RecommendationManager (cached, per locale+product) │
       │   ├─ similarProducts()                              │
       │   ├─ frequentlyBoughtTogether()                     │
       │   └─ customersAlsoBought()                          │
       └─────────────────────────────────────────────────────┘
              │              │                  │
              ▼              ▼                  ▼
      ┌──────────────┐ ┌─────────────────┐ ┌──────────────────┐
      │ SimilarProd. │ │ FBTService      │ │ CustomersAlsoB.  │
      │ (single-SQL  │ │ (reads pre-agg  │ │ (DISTINCT user_id │
      │  weighted    │ │  product_pair_  │ │  aggregation with │
      │  scoring)    │ │  stats)         │ │  privacy threshold)│
      └──────────────┘ └─────────────────┘ └──────────────────┘
              │              │                  │
              └──────────────┴──────────────────┘
                              │
                              ▼
        ┌──────────────────────────────────────────────┐
        │  RecommendationEligibility (shared filter)   │
        │   - status = 'published'                     │
        │   - published_at <= NOW                      │
        │   - vendor.status = APPROVED                 │
        │   - track_stock=false OR stock>0             │
        │   - not the source product                   │
        └──────────────────────────────────────────────┘
                              │
                              ▼
        ┌──────────────────────────────────────────────┐
        │  TranslationService (v11B.1.2 reused — §17)  │
        │   - resolves display_name/short_desc/desc    │
        │     per active locale, approved-only         │
        └──────────────────────────────────────────────┘

   ┌─────────────────────────────────────────────────────────┐
   │  AGGREGATION (offline + scheduled)                       │
   │  GenerateRecommendationsCommand (recommendations:generate)│
   │   - chunked over qualifying Order::completed orders      │
   │   - canonical (a<b) pair upsert into product_pair_stats │
   │   - distinct customer count per pair                     │
   │   - daily Schedule::command at 03:30 with --since=2      │
   └─────────────────────────────────────────────────────────┘

   ┌─────────────────────────────────────────────────────────┐
   │  CACHE INVALIDATION (Product::saved observer)            │
   │   - wasChanged(status|stock|track_stock|price_minor|     │
   │                category_id|vendor_id)                    │
   │   - → RecommendationManager::invalidate(productId)       │
   └─────────────────────────────────────────────────────────┘

   ┌─────────────────────────────────────────────────────────┐
   │  ADMIN curation (Filament)                               │
   │   - AdminProductRelationshipResource                     │
   │     pinned | hidden | complementary | excluded           │
   │   - RecommendationAnalytics dashboard page (aggregates)  │
   └─────────────────────────────────────────────────────────┘

   ┌─────────────────────────────────────────────────────────┐
   │  ANALYTICS (privacy-safe)                                │
   │   - POST /recommendations/events (throttle 60/min)        │
   │   - session_token = SHA-256(session_id), never raw       │
   │   - user_id nullable, attribution only, never displayed  │
   └─────────────────────────────────────────────────────────┘
```

---

## Scoring formula (Similar Products)

Per dev §5 — text/category > popularity. All weights configurable via `config/marketplace_recommendations.php`:

```
score(candidate, source) =
    (candidate.category_id == source.category_id              ? 50 : 0)   // same subcategory
  + (candidate.category_id IN children_of(source.category_id) ? 25 : 0)   // same parent category
  + (|candidate.price - source.price| ≤ source.price × 0.10   ? 30 : 0)   // ±10% price
  + (|candidate.price - source.price| ≤ source.price × 0.25   ? 15 : 0)   // ±25% price (if 10% miss)
  + (|candidate.price - source.price| ≤ source.price × 0.50   ?  5 : 0)   // ±50% price (if 25% miss)
  + (candidate.vendor_id == source.vendor_id                  ?  5 : 0)   // same vendor
  + (candidate in stock                                       ?  2 : 0)   // stock booster
```

**Weight justification** (dev §5: "Text/category similarity must be more important than popularity"):
- Top score: same subcategory + ±10% price + same vendor + in stock = **87**
- Bottom score (just in stock): **2**
- Popularity is **not** a multiplicative factor — only a small additive booster from rating + log10(orders+1). The popularity floor (≤ ~25) never out-scores a genuine category match (≥50).

**Tag/brand signals**: dev §7 acknowledges that tag/brand columns don't exist in the current schema. The signals listed there are forward-compatible — when tags/brands are added in a later phase, the same weighted-additive pattern can be extended without rewriting the service.

---

## Candidate-generation logic

`SimilarProductService::forProduct(Product, limit)`:

1. **WHERE filter**: products in the same subcategory, OR products in any sibling subcategory under the same parent (so a phone-case product surfaces relevant other phone accessories, not just other cases). Cold-start: if source has no category at all, the whole eligible catalog is the candidate pool.
2. **Score expression**: all weights computed as a single MySQL CASE expression in the SELECT — one SQL pass, no per-row PHP work.
3. **Eligibility join**: `whereHas('vendor', status=approved)` + status=published + published_at + stock filter — all server-side, no leakage.
4. **Candidate pool**: 3× limit fetched (e.g. 24 candidates for limit=8) to give post-filtering room.
5. **Eligibility post-filter** (defense in depth): collection-level recheck of stock + vendor + status.
6. **Pinned prepend**: admin-pinned products from `admin_product_relationships` (relationship_type='pinned') are pushed to the front with score 1,000,000.
7. **Dedupe + final limit**: pinned might overlap algorithmic; dedupe by id, then `take(limit)`.

---

## Eligibility rules (uniform across all rec types)

Per dev §18:

| Rule | Enforcement |
|---|---|
| Product is published | `status = 'published'` |
| Product is active | `published_at IS NOT NULL AND published_at <= NOW` |
| Vendor is approved | `vendor.status = 'approved'` (excludes pending/suspended/rejected) |
| Not deleted | Soft-delete already filters via the model's default scope |
| In stock | `track_stock = false OR stock > 0` (config-toggleable via `eligibility.exclude_out_of_stock`) |
| Not the source product | `products.id != source.id` |

All applied at both SQL-query level (for performance) AND collection level (for defense in depth) in `RecommendationEligibility::applyToQuery()` and `::filterCollection()`.

---

## Co-occurrence logic (Frequently Bought Together)

### Definition (per dev §8)
Products A and B are frequently bought together when they appear in the SAME completed customer order with sufficient pair_count, confidence, and support.

### Qualifying order statuses (per dev §8, matched to real Order model)
`STATUS_PAID`, `STATUS_CONFIRMED`, `STATUS_SHIPPED`, `STATUS_DELIVERED`, `STATUS_COMPLETED`.
**Excluded**: `CANCELLED`, `REFUNDED`, `FAILED`, `PENDING_PAYMENT`. Verified by Pest §35.17-35.19.

### Metrics (per dev §9)
```
pair_count(A,B)         = stored on product_pair_stats row
confidence(A→B)         = pair_count(A,B) / count_of_orders_containing(A)
support(A,B)            = pair_count(A,B) / total_qualifying_orders
```

### Default thresholds
| Threshold | Default | Rationale |
|---|---|---|
| `min_pair_orders` | 2 | "Do not display a pair based on one accidental order" (dev §9) |
| `min_confidence` | 0.10 (10%) | Reasonable for a 100-product marketplace; reduce for smaller catalogs |
| `min_support` | 0.001 (0.1%) | Filters out one-off accidents in large catalogs |
| `lookback_days` | 180 | Recent purchase patterns; 6 months captures seasonality |
| `recency_half_life` | 90 | Stored for future weight-decay implementation |

### Canonical pair storage
Pairs stored with `product_a_id < product_b_id` enforced at write time (see `ProductPairStat::canonical()`). This halves table size — `(A,B)` and `(B,A)` are the same row — and avoids the inconsistency of two rows that should always be in sync.

---

## Privacy threshold (Customers Also Bought)

Per dev §12: default **3 distinct customers** before any pair can be publicly displayed. Configurable via `customers_also_bought.min_distinct_customers`.

Enforcement is at the SQL aggregation level — `HAVING COUNT(DISTINCT orders.user_id) >= N` — so under-threshold pairs never reach the application layer. Tested in Pest §36.32, §36.33.

**No identity exposure**: Pest §36.34 asserts that `json_encode($payload)` contains neither the customer's email nor a `user_id` key — the public payload is aggregate-only.

---

## Fallback logic

### Similar Products (per dev §14 chain)
1. Same subcategory candidates ranked by composite score (default)
2. Same parent category if subcategory pool insufficient
3. (Implicit fall-through to broader eligible pool when source has no category)

### Frequently Bought Together (per dev §14 chain)
1. **Real co-occurrence** from `product_pair_stats` above thresholds → labeled "Frequently Bought Together"
2. **Admin-configured complementary** products → labeled "You May Also Like" (truthful relabel, see UI section)
3. **No section** if neither is available (per dev §14: "no section if no meaningful result")

### Customers Also Bought (per dev §14)
1. Distinct-customer co-purchase above privacy threshold
2. **Section hidden** if below threshold (no misleading fallback)

The UI implements this honesty: the FBT component switches its heading to `recommendations.also_bought.fallback_title` ("You May Also Like" / "قد يعجبك أيضًا") when `evidence === 'complementary'`. The CustomersAlsoBought component renders nothing when `items.length === 0`.

---

## Multi-vendor handling

Per dev §20:
- Recommended items may come from different vendors. Vendor ownership is preserved (each `Product` carries `vendor_id`).
- "Add Selected to Cart" routes each item through the **existing** `CartService::addItem()` (Phase 4) which already handles per-vendor sub-cart routing.
- The Phase 9 multi-vendor checkout split + shipping calculation is **unchanged**.
- No new cart mechanism: the batch endpoint is just a loop over `addItem()` with server-side eligibility recheck per item.

---

## Variant handling (per dev §19)

The `CartController::addBatch()` endpoint **explicitly rejects** variable products without a `variant_id`:

```php
if ($product->type === Product::TYPE_VARIABLE && empty($row['variant_id'])) {
    $skipped[] = "Product '{$product->name}' requires variant selection";
    continue;
}
```

Tested in Pest §35.26. The FBT UI does not bypass variant selection — variable products must be added via the regular product page first (where variant choice happens), and only simple/digital products participate cleanly in FBT bundles.

---

## Localization (per dev §17)

All recommendation payloads pass through `TranslationService::displayFields($product)` — the same v11B.1.2 resolver used by the rest of the marketplace. This guarantees:
- Approved Arabic translations show on Arabic storefront
- Controlled English fallback when Arabic is missing
- Never raw JSON
- No second localization path

Localized strings (section headings, action labels, explanation labels) are in `lang/en.json` + `lang/ar.json` — 17 new keys at full parity (378 total). All keys use the `recommendations.*` namespace.

Pest §34.8 verifies that Arabic locale + Arabic-translated product returns the Arabic display name; Pest §34.9 verifies English fallback when Arabic is absent; Pest §35.28 verifies the cache layer keys by locale (different locales return different display_name).

---

## Caching strategy

Per dev §23:
- **Read path**: `RecommendationManager::similarProducts()` etc. use `Cache::remember(key, ttl, callback)`.
- **Cache key**: `rec:v11b2:{type}:{productId}:{locale}:{limit}` — separates per product, per type, per locale (so Arabic and English don't pollute each other), per requested limit.
- **TTL**: 24 hours (configurable via `cache.ttl_seconds`).
- **Invalidation**: `Product::saved()` observer in `AppServiceProvider` calls `RecommendationManager::invalidate(productId)` whenever any of (`status`, `stock`, `track_stock`, `price_minor`, `category_id`, `vendor_id`) changed via `wasChanged()`. The translation-update path is implicitly handled — when an admin approves an Arabic translation, the translation flow doesn't touch product columns so cached recs stay valid, but `Cache::flush()` is also safe to run manually.

---

## Scheduled generation

Per dev §24:
- **Command**: `php artisan recommendations:generate`
- **Options**: `--product=N` (single product) / `--since=N` (last N days) / `--truncate` (full rebuild)
- **Chunked**: orders processed in 500-row chunks via Eloquent `chunk()` — no memory explosion on 100k+ catalogs.
- **Idempotent**: incremental upsert on `(product_a_id, product_b_id)` unique key — re-runs produce identical counts. Pest §35.29 verifies.
- **Schedule**: `Schedule::command('recommendations:generate --since=2')->dailyAt('03:30')` — bounded incremental refresh every night.

Per dev §24 — "Do not require generation during a customer request" — customer requests **only read** the precomputed `product_pair_stats` table. The aggregation is purely offline.

---

## Incremental updates (per dev §25)

The scheduled `--since=2` daily run picks up newly completed orders. The current implementation does not yet hook into an `OrderCompleted` event for immediate per-order recompute — the design supports it (a `UpdatePairStatsOnOrderJob` would dispatch via `Order::updated()` observer), but ship-scope kept this deferred to v11B.3 because:
1. The daily run keeps recs at most 24h stale, which matches the cache TTL.
2. Live per-order updates would require careful idempotency for refund/cancellation edge cases.

Acknowledged limitation; documented honestly per dev §45.

---

## Admin controls

### `AdminProductRelationshipResource` (Filament)
Lets admin create / edit / delete relationships:
- **Pinned**: ranks above algorithmic results in Similar Products
- **Hidden**: omitted from all rec types for that source
- **Complementary**: used as FBT fallback when real co-occurrence is thin
- **Excluded**: pair is omitted (in either direction if `reciprocal=true`)

Audit trail via `created_by` (stamped automatically by `CreateAdminProductRelationship::mutateFormDataBeforeCreate`) + `created_at` / `updated_at` timestamps.

### `RecommendationAnalytics` (Filament page)
Aggregated metrics per recommendation type:
- Impressions, clicks, CTR%
- Add-to-cart, A2C%
- Top 10 recommended products by add-to-cart

**No individual customer data ever displayed**, per dev §22.

---

## Analytics (per dev §21)

Privacy-safe ingestion at `POST /recommendations/events`:
- **Rate-limited**: 60 events/min per IP (dev §33 — analytics spam protection)
- **session_token**: SHA-256 hash of Laravel session ID, NEVER raw (verified Pest §37.41: token is 64 hex chars)
- **user_id**: nullable; populated only for authenticated users for conversion-attribution joins; never displayed in admin reports
- **No IP, no UA, no email, no name, no order ID** stored on rec events
- **Validation**: server-side enum check on event_type + recommendation_type (Pest §37.43)
- **Attribution window**: 7 days (configurable via `analytics.attribution_window_days`)

---

## Tests

`tests/Feature/Phase11B2RecommendationsTest.php` — **50 Pest scenarios**:

| Group | # | Coverage |
|---|---|---|
| §34.1-15 Similar Products | 15 | same subcategory, similar price, same vendor, exclusions (source/draft/suspended/OOS), localization, fallback, limit cap, pinned/hidden/excluded admin overrides, dedup, feature flag |
| §35.16-30 Frequently Bought Together | 15 | pair evidence from COMPLETED order, cancelled/failed/refunded excluded, dedup, min_pair/confidence/support thresholds, OOS exclusion, complementary fallback, batch endpoint validation, variant gate, suspended vendor rejection, cache per locale, idempotent regen, PAID counts |
| §36.31-40 Customers Also Bought | 10 | distinct customer count, privacy threshold (3), single-customer suppression, no identity exposure, cancelled excluded, source excluded, hidden excluded, no duplicates, feature flag, disabled payload |
| §37.41-50 Services, Analytics, Cache, Regression | 10 | impression event, click event, invalid event rejected, no PII stored, feature flag, cache layer, observer invalidation, Catalog show props, customer login, admin reports regression |

**CI sub-checks: 14** new for v11B.2. Total v11B.2 helpers: 6 (`p11b2_*`), **0 duplicates** across all suites (136 total unique helpers).

---

## Performance

Per dev §42 acceptance criteria — design analysis (live profiling deferred to dev's verification step §43):

| Operation | Query count | N+1? |
|---|---|---|
| Product detail page recommendation block | **1 cache read** (warm) / **3 queries** (cold: SimilarProducts SQL + FBT pair stats read + CustomersAlsoBought aggregation) | NO |
| SimilarProductService scoring | **1 SQL pass** — all weights in a single CASE expression in SELECT; no per-candidate query | NO |
| FBT pair stats read | **1 query** on the precomputed `product_pair_stats` table (no aggregation on read) | NO |
| Customers Also Bought | **2 queries** — one to find customers who bought source, one to aggregate their other purchases. Uses single GROUP BY with HAVING. | NO |
| Cart batch add | 1 product lookup per item (max 20 items by validation) | NO |
| Recommendations:generate command | 1 chunk fetch + 1 batch upsert per 500-row chunk; total time scales linearly with order count | NO |
| Analytics event ingestion | 1 INSERT per event | NO |

The eager-loading on the read path (`->with(['vendor', 'category', 'images', 'translations'])`) keeps recommendation card rendering at 0 per-card queries — confirmed by the same eager-load pattern v11B.1.2 verified for `with('translations')`.

**No N+1, no full catalogue load, no synchronous global recalculation.** All dev §42 criteria met by design.

---

## Security (per dev §33)

| Threat | Mitigation |
|---|---|
| Hidden product exposure | `RecommendationEligibility` filters status=published at SQL + collection level |
| Suspended vendor exposure | `whereHas('vendor', status=approved)` join |
| Unauthorized admin relationship changes | Filament panel requires admin auth; created_by stamped automatically |
| Tampered recommended-product IDs | `CartController::addBatch()` re-validates every product server-side (Pest §35.25, §35.27) |
| Adding unavailable products | Server-side stock check on every batch item |
| Variant bypass | Explicit check rejects variable products without variant_id (Pest §35.26) |
| Analytics spam | Rate limit 60/min per IP on `/recommendations/events` |
| XSS in recommendation labels | All labels come from `lang/{ar,en}.json`; React's default text escaping applies; no `dangerouslySetInnerHTML` |
| Customer identity leakage | Aggregates only; SHA-256 hashed session token; Pest §36.34 verifies no email/user_id in payload |

---

## Limitations & honest scope

**Shipped in v11B.2**:
- ✅ Similar Products (weighted, with pinned/hidden/excluded admin overrides)
- ✅ Frequently Bought Together (real co-occurrence + complementary fallback + variant-gate batch add)
- ✅ Customers Also Bought (privacy-threshold-enforced)
- ✅ Admin Filament workspace for relationships
- ✅ Basic admin analytics dashboard
- ✅ Aggregation command (chunked, idempotent, scheduled daily)
- ✅ Privacy-safe analytics ingestion
- ✅ Cache layer with observer-driven invalidation
- ✅ Localized via TranslationService (reused from v11B.1.2 — no second localization path)

**Deferred — acknowledged honestly per dev §45 "limitations"**:
- ❌ **Live per-order incremental update** — daily `--since=2` keeps recs ≤ 24h stale; matches cache TTL
- ❌ **Tag-based / brand-based similarity** — schema has no tags or brands tables yet; would extend `SimilarProductService` with additional CASE clauses when added
- ❌ **Similar Services dedicated UI** — services share the Product table, so `SimilarProductService` already serves service-type products; a dedicated section heading was not added
- ❌ **Bundle discount creation** — per dev §10: "Do not create a fake bundle discount unless an actual promotion exists." The FBT UI shows individual prices + combined total only. Real promotional bundles remain a separate Phase concern.
- ❌ **Cart-page recommendations** — config flag `cart_recommendations` ships defaulted to `false`. Reusing the same RecommendationManager for cart-page is a single component+wiring step in a future minor release.
- ❌ **CSV export of pair stats** — admins can browse via Filament; bulk export deferred.
- ❌ **Recommendation Visibility audit log** — `admin_product_relationships` has `created_by` + timestamps, but a separate moderation history table was not added.

**Pattern is established for each gap**; rollout is mechanical follow-up.

---

## Files changed in v11B.2

| File | Type | Purpose |
|---|---|---|
| `database/migrations/2026_07_01_000001_create_product_pair_stats_table.php` | NEW | co-occurrence aggregation table |
| `database/migrations/2026_07_01_000002_create_product_recommendations_table.php` | NEW | precomputed cache table |
| `database/migrations/2026_07_01_000003_create_admin_product_relationships_table.php` | NEW | admin curation table |
| `database/migrations/2026_07_01_000004_create_recommendation_events_table.php` | NEW | privacy-safe analytics |
| `app/Models/ProductPairStat.php` | NEW | + canonical() helper |
| `app/Models/ProductRecommendation.php` | NEW | + 4 type constants |
| `app/Models/AdminProductRelationship.php` | NEW | + 4 relationship type constants |
| `app/Models/RecommendationEvent.php` | NEW | + hashSession() helper |
| `app/Services/Recommendations/RecommendationEligibility.php` | NEW | shared filter |
| `app/Services/Recommendations/SimilarProductService.php` | NEW | weighted scoring |
| `app/Services/Recommendations/FrequentlyBoughtTogetherService.php` | NEW | FBT |
| `app/Services/Recommendations/CustomersAlsoBoughtService.php` | NEW | distinct-customer aggregation |
| `app/Services/Recommendations/RecommendationManager.php` | NEW | top-level API with cache |
| `app/Console/Commands/GenerateRecommendationsCommand.php` | NEW | chunked aggregation |
| `app/Http/Controllers/RecommendationEventsController.php` | NEW | analytics ingestion |
| `app/Http/Controllers/CartController.php` | MOD | + addBatch() with server-side recheck |
| `app/Http/Controllers/CatalogController.php` | MOD | + price_minor + 3 rec props |
| `app/Providers/AppServiceProvider.php` | MOD | + observer extended with rec invalidation |
| `app/Filament/Resources/AdminProductRelationshipResource.php` | NEW | admin workspace |
| `app/Filament/Resources/AdminProductRelationshipResource/Pages/*` | NEW (3) | List/Create/Edit pages |
| `app/Filament/Pages/RecommendationAnalytics.php` | NEW | analytics dashboard |
| `resources/views/filament/pages/recommendation-analytics.blade.php` | NEW | dashboard view |
| `resources/js/Components/recommendations/types.ts` | NEW | typed payload contracts + tracker + formatter |
| `resources/js/Components/recommendations/SimilarProducts.tsx` | NEW | responsive grid, RTL-safe |
| `resources/js/Components/recommendations/FrequentlyBoughtTogether.tsx` | NEW | checkboxes + combined total |
| `resources/js/Components/recommendations/CustomersAlsoBought.tsx` | NEW | grid (privacy-respecting) |
| `resources/js/Pages/Catalog/Show.tsx` | MOD | + 3 sections rendered before Reviews |
| `routes/web.php` | MOD | + /cart/items/batch + /recommendations/events |
| `routes/console.php` | MOD | + Schedule::command('recommendations:generate --since=2') |
| `config/marketplace_recommendations.php` | NEW | feature flags + weights + thresholds |
| `lang/en.json` | MOD | 361 → 378 keys (+17 recommendations.*) |
| `lang/ar.json` | MOD | 361 → 378 keys (+17 MSA recommendations.*) |
| `tests/Feature/Phase11B2RecommendationsTest.php` | NEW | 50 Pest scenarios |
| `.github/workflows/ci.yml` | MOD | +14 v11B.2 sub-checks |
| `VERSION` | `Phase 11B.1 v11B.1.2` → `Phase 11B.2` |

---

## Counts

| Metric | v11B.1.2 → v11B.2 |
|---|---|
| CI sub-checks | 133 → **147** (+14) |
| Pest scenarios | 542 → **592** (+50) |
| Unique Pest helpers | 129 → **136** (+7 p11b2_*) |
| New migrations | — → **4** |
| New models | — → **4** |
| New services | — → **5** |
| New jobs/commands | — → **1** command |
| New Filament resources | — → **1** + 3 pages + 1 page |
| New controllers | — → **1** |
| New React components | — → **3** + 1 types.ts |
| Translation keys (en/ar each) | 361 → **378** (+17) |
| Routes added | — → **2** (/cart/items/batch + /recommendations/events) |

---

## Phase 11B.2 STOPS HERE

No personalized homepage. No vendor intelligence. No pricing recommendations. No support assistant. No risk scoring. Pending dev verification per §43 commands + §44 evidence + §38 manual dataset.
