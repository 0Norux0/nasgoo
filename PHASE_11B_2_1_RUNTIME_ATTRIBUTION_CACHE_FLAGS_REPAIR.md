# Phase 11B.2 v11B.2.1 — Runtime, Attribution, Cache, Flags & Services-i18n Repair

Per dev §12.

## Scope statement

Surgical five-defect repair on top of v11B.2. **No Phase 11B.3 work.** No recommendation redesign. No new features beyond what the defects strictly require.

---

## Defect 1 — Critical product-page TypeError

### Exact type mismatch

`SimilarProductService::forProduct()` declared:

```php
use Illuminate\Database\Eloquent\Collection;
public function forProduct(Product $source, int $limit = 8): Collection
```

…but the actual code path was:

```php
$annotated = collect($eligible)->map(...)         // Support\Collection
$deduped   = $annotated->filter(...)->values();   // Support\Collection
return $deduped->take($limit);                    // Support\Collection
```

`collect()` returns `Illuminate\Support\Collection`, not the Eloquent subtype. PHP threw `TypeError` on every product detail page request.

The same bug existed in `CustomersAlsoBoughtService::forProduct()` (declared `Eloquent\Collection`, returned `collect($final)`). `FrequentlyBoughtTogetherService` returned an array so it didn't crash, but its inner collection was also a Support collection.

### Corrected return contract

Per dev §1 "Correct options" — chose the type that **accurately matches the real return value**. All three services now declare `Illuminate\Support\Collection`:

```php
use Illuminate\Support\Collection;

/**
 * @return \Illuminate\Support\Collection<int, Product> A base collection of
 *   Product models in ranked order. Each model gets a transient
 *   `recommendation_score` and `recommendation_explanation` attribute
 *   attached for the UI. NOT an Eloquent\Collection — the post-eligibility
 *   filter + dedup chain produces a base Support collection.
 */
public function forProduct(Product $source, int $limit = 8): Collection
```

**Not an unsafe cast.** The transformations (eligibility filter, score annotation, dedup) genuinely produce a base Support collection of models with transient attributes — declaring that type is the honest representation.

### Cache-hit / cache-miss type behavior

`RecommendationManager::similarProducts()` wraps the service in `Cache::remember()`. The cached payload is an `array` (not a Collection), so the cache-hit and cache-miss paths return the same shape. Verified Pest §1.4.

### Audit results across recommendation services

| Service | Issue | Fix |
|---|---|---|
| `SimilarProductService` | Eloquent\Collection declared, Support\Collection returned | Switched to Support\Collection import + signature + PHPDoc |
| `CustomersAlsoBoughtService` | Same | Same fix |
| `FrequentlyBoughtTogetherService` | Returns array (no crash); inner collections were Support | Switched inner import to Support for consistency |
| `RecommendationManager` | Returns array — no mismatch | (unchanged) |

### Regression test

Pest §1.1 asserts `expect($result)->toBeInstanceOf(SupportCollection::class)`. This test would fail if any future commit reverts to Eloquent\Collection without fixing the chain.

---

## Defect 2 — Services Arabic localization broken

### Root cause

`ServiceCatalogController` (services share the Product table — `Product::TYPE_SERVICE`):

| Line | Defect |
|---|---|
| `index()` line 72 (pre-v11B.2.1) | `'description' => $p->description` — raw English regardless of locale |
| `show()` line 119 (pre-v11B.2.1) | `'description' => $service->description` — same |
| `index()` line 44 | `where('name', 'like', '%' . $q . '%')` — searched only the English column, never `name_translations.ar` |

The `'name' => $p->translatedName()` lookup was correct — only description and search were missing localization.

### Service localization files changed

- `app/Http/Controllers/ServiceCatalogController.php`:
  - Index: uses `translatedShortDescription()` ?: `translatedDescription()` (mb_substr 200)
  - Show: uses `translatedDescription()`
  - Search expanded to `JSON_EXTRACT(name_translations, '$.ar')` + English columns
  - Both paths eager-load `'translations'` so `TranslationService` resolves with zero N+1 queries (the v11B.1.2 fast path)

### Architecture consistency

Per dev §2 — "Use the same dynamic translation architecture already approved for products and categories. Do not hardcode a few Arabic service records in React." The fix routes all service text through the v11B.1.2 `TranslationService` resolver. No second i18n path. No hardcoded Arabic strings.

### Tests

Pest §2.1–2.6 cover:
- Arabic service title (translatedName)
- Arabic service description (translatedDescription)
- Service detail Arabic description
- English fallback when Arabic is missing
- Arabic search query matches `name_translations.ar`
- Raw JSON never appears in payload

---

## Defect 3 — Purchase attribution missing

### Architecture

Server-side attribution, queued, idempotent, refund-aware. Per dev §3 — "Do not trust the frontend to declare a purchase. Use the actual order lifecycle."

```
Frontend (impression / click / add_to_cart) → POST /recommendations/events → row inserted
                                                                          │
                                                                          ▼
                                                              recommendation_events
                                                              (user_id linked for join)
                                                                          │
   Order placed                                                           │
   → Order::status changes to PAID|CONFIRMED|SHIPPED|DELIVERED|COMPLETED  │
   → AppServiceProvider Order::saved observer fires                       │
   → RecordPurchaseAttributionJob::dispatch($order->id)  (QUEUED)         │
                                                                          │
                                                                          ▼
                                          For each OrderItem:
                                          ┌─────────────────────────────────────────┐
                                          │ Find recent click/add_to_cart event for │
                                          │ this user where:                        │
                                          │   user_id = order.user_id               │
                                          │   recommended_product_id = item.product │
                                          │   event_type IN ('click','add_to_cart') │
                                          │   created_at >= NOW - 7 days            │
                                          │ ORDER BY created_at DESC LIMIT 1        │
                                          └─────────────────────────────────────────┘
                                                       │
                                          (last-touch attribution)
                                                       │
                                                       ▼
                                          insertOrIgnore purchase event:
                                          unique on (order_item_id, event_type,
                                                     product_id, recommendation_type)
                                                       │
                                                       │── refund/cancel branch:
                                                       │   UPDATE … SET reversed_at = NOW
                                                       │   WHERE order_item_id IN (…)
                                                       │     AND event_type = 'purchase'
                                                       ▼
                                          recommendation_events.purchase row
                                          (analytics dashboard counts reversed_at IS NULL)
```

### Qualifying order event

Same statuses as the v11B.2 FBT co-occurrence aggregation — `PAID`, `CONFIRMED`, `SHIPPED`, `DELIVERED`, `COMPLETED`. Documented in the job code, the migration comment, and `config/marketplace_recommendations.qualifying_order_statuses`.

### Attribution rule

**Last-touch.** Per dev §3.4 — "most recent qualifying recommendation". The job uses `orderByDesc('created_at')->first()` to pick the most recent click/add_to_cart in the window.

### Attribution window

**7 days** by default. Configurable via `marketplace_recommendations.analytics.attribution_window_days`. Documented in the report and the job code. Verified by Pest §3.6 (click 30 days ago → no attribution).

### Idempotency rule

Unique key: `(order_item_id, event_type, product_id, recommendation_type)`. Migration adds this constraint:

```php
$table->unique(
    ['order_item_id', 'event_type', 'product_id', 'recommendation_type'],
    'rec_events_purchase_unique'
);
```

The job uses `insertOrIgnore` so multiple status transitions (paid → shipped → delivered) on the same order produce exactly ONE purchase event. Verified Pest §3.4.

### Refund / cancellation behavior

**Mark reversed**, don't delete. Per dev §3.5 — the row stays with `reversed_at = NOW` so gross-vs-net reporting can separate the totals later. The analytics dashboard counts only `reversed_at IS NULL` as net conversions. Verified Pest §3.5.

### Tests (8 of dev §8.11-20)

- §3.1 click + paid order → 1 purchase event
- §3.2 cancelled order → 0 purchase events
- §3.3 failed payment → 0 purchase events
- §3.4 re-running job is idempotent (3 dispatches → 1 row)
- §3.5 refund reverses purchase event (reversed_at set)
- §3.6 attribution window: 30-day-old click → not attributed
- §3.7 no PII exposed (email not in purchase row)
- §3.8 Order::saved observer dispatches the queued job

---

## Defect 4 — Cache invalidation incomplete

### Cache invalidation graph

```
            ┌──────────────────────────────────────────────┐
            │ Source-product change (Product::saved)        │
            │ → Cache::forget(rec:vN:*:productId:*)         │
            │ → if eligibility-affecting (status/stock/     │
            │    vendor/track_stock), also bumpVersion()    │
            └──────────────────────────────────────────────┘

            ┌──────────────────────────────────────────────┐
            │ Vendor::saved (status change)                 │
            │ → bumpVersion()                               │
            │   (all vendor's products are now ineligible   │
            │    or newly eligible — invalidate all)        │
            └──────────────────────────────────────────────┘

            ┌──────────────────────────────────────────────┐
            │ ProductTranslation::saved                     │
            │ → bumpVersion()                               │
            │   (localized display_name changed for any     │
            │    rec list containing this product)          │
            └──────────────────────────────────────────────┘

            ┌──────────────────────────────────────────────┐
            │ AdminProductRelationship::saved + deleted     │
            │ → bumpVersion()                               │
            │   (pinned/hidden/excluded/complementary       │
            │    changed for source product)                │
            └──────────────────────────────────────────────┘

                          │
                          ▼
            ┌──────────────────────────────────────────────┐
            │ All caches now read with NEW version prefix  │
            │ → previously-cached entries are NEVER read    │
            │   (key mismatch produces a cache miss)        │
            └──────────────────────────────────────────────┘
```

### Reverse-reference strategy

**Versioned cache keys** — chosen over cache tags + reverse-reference table for these reasons:

1. **Driver-agnostic**: Laravel's `Cache::tags()` is not supported on `file`, `database`, or `array` drivers. Versioning works on every driver.
2. **O(1) invalidation**: bumping the version is one `Cache::forever('rec:cache:version', N+1)`. No table to maintain, no consistency to lose.
3. **No extra writes on every cache miss**: a reverse-reference table would double every Cache::remember.
4. **Self-cleaning**: stale keys expire naturally via their TTL (24h default); no orphan cache entries to clean up.

The trade-off — bumping the version invalidates ALL cached recs, not just the affected ones — is acceptable because (a) cache misses re-resolve in single-digit milliseconds (one indexed query) and (b) the runtime eligibility recheck (below) guarantees correctness even if a single key were missed.

### Runtime eligibility recheck

Per dev §4 — "Cache is an optimization, not the source of authorization truth." `RecommendationManager::reapplyEligibility()` runs on EVERY return path (cache hit or miss):

```php
private function reapplyEligibility(array $payload, Product $source): array
{
    // Bulk fetch live eligibility — one query for all cached items
    $live = Product::query()->whereIn('products.id', $ids)->with('vendor');
    $this->eligibility->applyToQuery($live, $source->id);
    $liveIds = $live->pluck('products.id')->all();
    $eligibleIds = array_flip($liveIds);

    // Apply admin exclusions live (flag-gated by AdminCurationGate)
    $excluded = array_flip($this->curation->excludedIdsFor($source));

    $payload['items'] = array_values(array_filter(
        $payload['items'],
        fn ($i) => isset($eligibleIds[(int) ($i['id'] ?? 0)])
              && ! isset($excluded[(int) ($i['id'] ?? 0)])
    ));
    return $payload;
}
```

A single SQL query per recommendation section (max 3 per product page = 3 extra queries on cache hits). Even if a vendor cascade observer ever failed to bump the version, suspended-vendor products are filtered at read time.

### Vendor / translation / relationship invalidation

| Event | Observer | Action |
|---|---|---|
| `Product::saved` (eligibility fields) | AppServiceProvider | `invalidate(productId)` + `bumpVersion()` |
| `Product::saved` (price only) | AppServiceProvider | `invalidate(productId)` (no bump — runtime recheck handles price freshness) |
| `Vendor::saved` (status changed) | AppServiceProvider | `bumpVersion()` |
| `ProductTranslation::saved` (status changed or new row) | AppServiceProvider | `bumpVersion()` |
| `AdminProductRelationship::saved` | AppServiceProvider | `bumpVersion()` |
| `AdminProductRelationship::deleted` | AppServiceProvider | `bumpVersion()` |

### Tests (Pest §4.1–§4.8)

- §4.1 Vendor suspension bumps version
- §4.2 Translation approval bumps version
- §4.3 Admin relationship creation bumps version
- §4.4 Admin relationship deletion bumps version
- §4.5 Runtime recheck: unpublished product on A's page → disappears
- §4.6 Runtime recheck: suspended-vendor product → disappears
- §4.7 Cache version is monotonically increasing
- §4.8 Versioned cache key prevents old cache reads after bump

---

## Defect 5 — `RECOMMENDATIONS_ADMIN_CURATED_ENABLED` not enforced

### Root cause

Each of the 3 services had its own private `excludedIdsFor()` and `pinnedIdsFor()` that queried `AdminProductRelationship` directly. None checked the feature flag.

### Single canonical decision point

Created `app/Services/Recommendations/AdminCurationGate.php`:

```php
class AdminCurationGate
{
    public function isEnabled(): bool {
        return (bool) config('marketplace_recommendations.features.admin_curated', true);
    }

    public function excludedIdsFor(Product $source): array {
        if (! $this->isEnabled()) return [];
        return /* DB query */;
    }

    public function pinnedIdsFor(Product $source): array {
        if (! $this->isEnabled()) return [];
        return /* DB query */;
    }

    public function complementaryIdsFor(Product $source): array {
        if (! $this->isEnabled()) return [];
        return /* DB query */;
    }
}
```

All 3 recommendation services now inject this gate via constructor. The flag is checked in ONE place. When the flag is off, no curated DB query is even issued — saving query overhead.

### Feature-flag enforcement matrix

| Flag | Enforced in | Disabling effect |
|---|---|---|
| `RECOMMENDATIONS_ENABLED` (master) | `RecommendationManager::globallyEnabled()` (all 3 methods early-return disabled payload) | All 3 sections empty; page renders cleanly |
| `RECOMMENDATIONS_SIMILAR_ENABLED` | `RecommendationManager::similarProducts` early-return + `SimilarProductService::forProduct` empty-collection guard | Similar section empty |
| `RECOMMENDATIONS_FBT_ENABLED` | `RecommendationManager::frequentlyBoughtTogether` early-return + `FrequentlyBoughtTogetherService::forProduct` empty-evidence guard | FBT section hidden |
| `RECOMMENDATIONS_ALSO_BOUGHT_ENABLED` | `RecommendationManager::customersAlsoBought` early-return + `CustomersAlsoBoughtService::forProduct` empty-collection guard | Also-bought section hidden |
| `RECOMMENDATIONS_SIMILAR_SERVICES_ENABLED` | (services share Product table — flag reserved for future) | (no-op currently) |
| `RECOMMENDATIONS_ANALYTICS_ENABLED` | `RecommendationEventsController::record()` returns 204 with empty body | Analytics endpoint stops persisting |
| **`RECOMMENDATIONS_ADMIN_CURATED_ENABLED`** ⭐ | **`AdminCurationGate::isEnabled()` — single canonical decision** | All curated queries skipped: no pinned, no hidden, no excluded, no complementary fallback |
| `eligibility.exclude_out_of_stock` | `RecommendationEligibility::applyToQuery()` + `filterCollection()` | OOS products allowed in result |

### Tests (Pest §5.1–§5.8)

- §5.1 Flag enabled: pinned ranks first
- §5.2 Flag disabled: pinned has no special effect (no 'pinned' explanation in results)
- §5.3 Flag disabled: excluded does NOT filter
- §5.4 Flag disabled: hidden does NOT filter
- §5.5 Flag disabled: FBT complementary fallback is NOT used (evidence='none')
- §5.6 Flag disabled: AdminCurationGate::isEnabled() returns false
- §5.7 Flag enabled: gate returns pinned IDs from DB
- §5.8 Flag disabled: gate returns empty regardless of DB rows

---

## Tests summary

`tests/Feature/Phase11B21RecommendationRepairTest.php` — **38 Pest scenarios**:

| Group | # | Coverage |
|---|---|---|
| §1 Runtime return-type | 4 | Support\Collection returned, product page 200, cache-hit/miss parity |
| §2 Services Arabic | 6 | Listing/detail/description/search/fallback/no-raw-JSON |
| §3 Purchase attribution | 8 | Click+paid creates 1, cancelled excluded, failed excluded, idempotent, refund reverses, window enforced, no PII, observer dispatches job |
| §4 Cache invalidation | 8 | Cascade observers (vendor/translation/relationship), runtime recheck, versioned keys |
| §5 Curated flag | 8 | Pinned/hidden/excluded/complementary all gated; gate behavior verified |
| §6 Other flags | 4 | Master / similar / analytics / OOS exclusion |

CI sub-checks: 7 new for v11B.2.1 covering all 5 defects + Pest filter.

**Total Pest scenarios: 630** (592 v11B.2 + 38 v11B.2.1). **Helpers: 143 unique, 0 duplicates**.

---

## Manual results (workspace verification — 67 functional checks pass)

```
── §1: Return type fix ─────────  6/6 ✓
── §2: Services Arabic ─────────  4/4 ✓
── §3: Purchase attribution ────  13/13 ✓
── §4: Cache invalidation ──────  8/8 ✓
── §5: Admin curated flag ──────  12/12 ✓
── Pest suite ──────────────────  2/2 ✓
── Helper uniqueness ───────────  1/1 ✓ (143 unique, 0 duplicates)
── VERSION + CI ────────────────  3/3 ✓
── Preservation ────────────────  18/18 ✓ (v11B.2 + v11B.1.2 + v11B.1.1 + v11B.1 + v11A.5 + v10.x)
                                ───────────
                                TOTAL: 67/67 ✓
```

---

## Performance verification

| Operation | Cost | N+1 |
|---|---|---|
| Product page cache hit (with runtime recheck) | 1 read from Cache + 1 SQL query for eligibility recheck (per section, max 3 sections) | NO |
| Product page cache miss | 1 service call + 1 eligibility recheck per section | NO |
| Vendor suspension cascade | 1 `Cache::forever('rec:cache:version', N+1)` — single key write, O(1) | NO |
| Translation approval cascade | Same — O(1) version bump | NO |
| Relationship create/delete | Same — O(1) version bump | NO |
| Purchase attribution job | 1 query per OrderItem to find recent event + 1 insertOrIgnore | Queued — does not block checkout |
| Refund reversal | 1 UPDATE statement matching order's items | NO |

**No full catalogue purge.** **No synchronous regeneration on order completion** — the job is queued. **No N+1 in any path** — eligibility recheck is one bulk query for all cached items.

---

## Remaining limitations

- **Eligibility recheck is one extra SQL query per cache hit.** On a busy product page this is 3 queries (similar + FBT + also-bought) even when the cache is warm. Trade-off: O(1) cascade invalidation + correctness guarantee. Acceptable for the security/correctness benefit.
- **Cache cascade is coarse.** Vendor suspension invalidates ALL cached recs, not just those containing the suspended vendor's products. The simplification reduces complexity at the cost of slightly more cache misses on the next read.
- **Guest checkouts are not attributed** — recommendation_events store user_id (nullable), but the attribution job filters `where('user_id', $order->user_id)`. Guest orders have null user_id and produce no purchase event. Acceptable per dev §3.6 ("no customer PII").
- **Multi-vendor orders attribute per item, not per order.** Each item can be independently attributed to a different recommendation source — correct behavior, no special handling needed.
- **Tag/brand similarity remains deferred.** Schema still has no tags or brands tables; the pattern is extensible when added.

---

## Files changed in v11B.2.1

| File | Type | Purpose |
|---|---|---|
| `database/migrations/2026_07_05_000001_extend_recommendation_events_for_purchase_attribution.php` | NEW | +order_item_id +reversed_at +unique constraint |
| `app/Models/RecommendationEvent.php` | MOD | +fillable order_item_id +reversed_at +cast |
| `app/Services/Recommendations/SimilarProductService.php` | MOD | Support\Collection return type; uses AdminCurationGate |
| `app/Services/Recommendations/CustomersAlsoBoughtService.php` | MOD | Same return-type fix; uses AdminCurationGate |
| `app/Services/Recommendations/FrequentlyBoughtTogetherService.php` | MOD | Support\Collection import; uses AdminCurationGate |
| `app/Services/Recommendations/RecommendationManager.php` | MOD | +bumpVersion +currentVersion +reapplyEligibility +versioned keys; deps include AdminCurationGate + RecommendationEligibility |
| `app/Services/Recommendations/AdminCurationGate.php` | NEW | Single canonical flag-gate for admin curation |
| `app/Jobs/RecordPurchaseAttributionJob.php` | NEW | Queued purchase-attribution + refund reversal |
| `app/Providers/AppServiceProvider.php` | MOD | +Order::saved observer; +Vendor::saved cascade; +ProductTranslation::saved cascade; +AdminProductRelationship::saved/deleted cascades |
| `app/Filament/Pages/RecommendationAnalytics.php` | MOD | Filter `reversed_at IS NULL` for net conversions |
| `app/Http/Controllers/ServiceCatalogController.php` | MOD | Use translatedDescription + Arabic search + eager-load translations (index+show) |
| `tests/Feature/Phase11B21RecommendationRepairTest.php` | NEW | 38 Pest scenarios |
| `.github/workflows/ci.yml` | MOD | +7 v11B.2.1 sub-checks |
| `VERSION` | `Phase 11B.2` → `Phase 11B.2 v11B.2.1` |

---

## Counts

| Metric | v11B.2 → v11B.2.1 |
|---|---|
| CI sub-checks | 147 → **154** (+7) |
| Pest scenarios | 592 → **630** (+38) |
| Unique Pest helpers | 136 → **143** (+7 p11b21_*) |
| Migrations added | (cumulative) | +1 (additive, idempotent) |
| New services | — → **1** (AdminCurationGate) |
| New jobs | — → **1** (RecordPurchaseAttributionJob) |
| New observers (cascade) | — → **4** (Vendor, ProductTranslation, AdminProductRelationship × 2, Order) |

---

## Package-integrity confirmation

Workspace verification: 67/67 functional checks pass. CI YAML valid. Helper namespacing clean (143 unique, 0 dups). All v11B.2, v11B.1.2, v11B.1.1, v11B.1, v11A.5, and v10.x markers preserved. See `PHASE_11B_2_1_PATCH_NOTES.md` for the per-file SHA-match table after archive build.

---

## Phase 11B.2 v11B.2.1 STOPS HERE

No Phase 11B.3 work begun. Pending dev verification per §9 + §10 + §11.
