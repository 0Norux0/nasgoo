# Phase 11B.2 v11B.2.1 — Patch Notes

## Summary

Surgical five-defect repair on Phase 11B.2. No Phase 11B.3 work. No recommendation redesign.

## Defects fixed

1. **Runtime TypeError on every product page** — `SimilarProductService::forProduct()` and `CustomersAlsoBoughtService::forProduct()` declared `Eloquent\Collection` return type but `collect()->take()` produces `Support\Collection`. All three rec services now declare `Support\Collection` honestly.
2. **Services page Arabic localization broken** — `ServiceCatalogController` used raw `$p->description` (English) and searched only the English `name` column. Index + show now use `translatedDescription()` / `translatedShortDescription()`; search expanded with `JSON_EXTRACT(name_translations, '$.ar')`; both paths eager-load `translations` for zero N+1.
3. **Purchase attribution missing** — `TYPE_PURCHASE` event type existed but no listener/job emitted it. NEW `RecordPurchaseAttributionJob` (queued), idempotent via unique constraint on (order_item_id, event_type, product_id, recommendation_type), last-touch within 7-day window, refund/cancel marks `reversed_at` instead of deleting.
4. **Cache invalidation incomplete** — Product observer only invalidated source-of cache, never reverse-reference. NEW versioned cache keys (Cache::forever rec:cache:version) bumped by Vendor/ProductTranslation/AdminProductRelationship cascade observers; PLUS runtime eligibility recheck on every cache hit so suspended-vendor/unpublished/OOS products are filtered out even if a key were missed.
5. **`RECOMMENDATIONS_ADMIN_CURATED_ENABLED` flag not enforced** — services queried `AdminProductRelationship` directly with no flag check. NEW `AdminCurationGate` is the single canonical decision point — when flag off, returns `[]` without DB query.

## Files changed (14)

**NEW (5)**:
- `database/migrations/2026_07_05_000001_extend_recommendation_events_for_purchase_attribution.php` — +order_item_id +reversed_at +unique idempotency constraint
- `app/Services/Recommendations/AdminCurationGate.php` — single canonical flag gate
- `app/Jobs/RecordPurchaseAttributionJob.php` — queued attribution + refund reversal
- `tests/Feature/Phase11B21RecommendationRepairTest.php` — 38 Pest scenarios
- `PHASE_11B_2_1_RUNTIME_ATTRIBUTION_CACHE_FLAGS_REPAIR.md` — full report

**MODIFIED (9)**:
- `app/Services/Recommendations/SimilarProductService.php` — Support\Collection + uses gate
- `app/Services/Recommendations/CustomersAlsoBoughtService.php` — Support\Collection + uses gate
- `app/Services/Recommendations/FrequentlyBoughtTogetherService.php` — Support\Collection + uses gate
- `app/Services/Recommendations/RecommendationManager.php` — +bumpVersion +currentVersion +reapplyEligibility +versioned keys; deps include AdminCurationGate + RecommendationEligibility
- `app/Models/RecommendationEvent.php` — +order_item_id +reversed_at fillable + cast
- `app/Providers/AppServiceProvider.php` — +Order::saved observer (dispatch job); +Vendor::saved / +ProductTranslation::saved / +AdminProductRelationship::saved+deleted cascades (all call bumpVersion); extended Product observer for eligibility-affecting cascade
- `app/Filament/Pages/RecommendationAnalytics.php` — filter `reversed_at IS NULL` for net conversions
- `app/Http/Controllers/ServiceCatalogController.php` — translatedDescription + Arabic JSON search + eager-load translations (index + show)
- `.github/workflows/ci.yml` — +7 v11B.2.1 sub-checks
- `VERSION` — `Phase 11B.2` → `Phase 11B.2 v11B.2.1`

## Required-result mapping (per dev directive)

| Required result | Status |
|---|---|
| Product pages open without TypeError | ✅ §1 — Support\Collection in 3 services |
| Services page translates correctly into Arabic | ✅ §2 — translatedDescription + JSON search + eager-load |
| Qualifying purchases create purchase events | ✅ §3 — queued job, last-touch, idempotent |
| Conversion analytics reflects actual purchases | ✅ §3 — `reversed_at IS NULL` net filter in dashboard |
| Cached recs cannot retain stale invalid products | ✅ §4 — version bump cascade + runtime recheck |
| Vendor/translation/relationship changes invalidate | ✅ §4 — 4 cascade observers bump global version |
| `RECOMMENDATIONS_ADMIN_CURATED_ENABLED=false` genuinely disables curated behavior | ✅ §5 — AdminCurationGate isEnabled() gate |
| No marketplace regression introduced | ✅ — all v11B.2/v11B.1.2/v11B.1.1/v11B.1/v11A.5/v10.x markers preserved (18/18) |

## Counts

| Metric | v11B.2 | v11B.2.1 | Δ |
|---|---|---|---|
| CI sub-checks | 147 | **154** | +7 |
| Pest scenarios | 592 | **630** | +38 |
| Unique Pest helpers | 136 | **143** | +7 (p11b21_*) |
| Migrations | (cumulative) | +1 additive | +1 |
| New services | — | +1 (AdminCurationGate) | +1 |
| New jobs | — | +1 (RecordPurchaseAttributionJob) | +1 |
| New observers | — | +4 cascade (Vendor, ProductTranslation, AdminProductRelationship×2, Order) | +4 |

## Deploy commands

```bash
php artisan optimize:clear
php artisan migrate:status                   # 1 new v11B.2.1 pending
php artisan migrate                          # additive extend recommendation_events
php artisan route:list | grep -i recommendation
php artisan schedule:list | grep recommendations
php artisan test --filter=Phase11B21         # 38 v11B.2.1 scenarios
php artisan test --filter=Phase11B2          # 50 v11B.2 regression scenarios
php artisan test                              # 630 total
php artisan translations:audit ar
npm ci && npm run typecheck && npm run build
```

## Rollback

3-tier:

**Tier 1** — Disable purchase attribution + admin curation flag without code revert:
```bash
RECOMMENDATIONS_ADMIN_CURATED_ENABLED=false
RECOMMENDATIONS_ANALYTICS_ENABLED=false
php artisan optimize:clear
```
The attribution job becomes a no-op (no events to attribute). Curated behavior turns off. Cache version cascade still works.

**Tier 2** — Restore individual files from v11B.2 baseline:
```bash
tar -xzf marketplace-phase-11B-2-recommendations.tar.gz --strip-components=1 --overwrite \
    marketplace/app/Services/Recommendations/SimilarProductService.php \
    marketplace/app/Services/Recommendations/CustomersAlsoBoughtService.php \
    marketplace/app/Services/Recommendations/FrequentlyBoughtTogetherService.php \
    marketplace/app/Services/Recommendations/RecommendationManager.php \
    marketplace/app/Providers/AppServiceProvider.php \
    marketplace/app/Http/Controllers/ServiceCatalogController.php \
    marketplace/app/Filament/Pages/RecommendationAnalytics.php \
    marketplace/app/Models/RecommendationEvent.php
rm -f app/Services/Recommendations/AdminCurationGate.php
rm -f app/Jobs/RecordPurchaseAttributionJob.php
php artisan migrate:rollback --step=1        # drops the v11B.2.1 columns
php artisan optimize:clear
```
**Caveat**: this reintroduces the v11B.2 TypeError on product pages. Tier 1 is preferred.

**Tier 3** — Full revert to v11B.2:
```bash
php artisan migrate:rollback --step=1
tar -xzf marketplace-phase-11B-2-recommendations.tar.gz --strip-components=1 --overwrite
php artisan optimize:clear
rm -rf public/build/ node_modules/.vite/
npm ci && npm run build
cat VERSION                                  # → Phase 11B.2
php artisan test --filter=Phase11B2          # 50 v11B.2 scenarios
```

## Honest scope

✅ Defect 1: 3 services use Support\Collection (truthful types) + regression test
✅ Defect 2: ServiceCatalogController uses translatedDescription/translatedShortDescription + Arabic search via JSON_EXTRACT + eager-load translations
✅ Defect 3: RecordPurchaseAttributionJob queued, last-touch within 7-day window, idempotent via unique constraint, refund branch sets reversed_at
✅ Defect 4: Versioned cache keys (Cache::forever rec:cache:version) + runtime reapplyEligibility on every read + 4 cascade observers
✅ Defect 5: AdminCurationGate single canonical decision point + 3 services constructor-injected + flag-aware
✅ 38 Pest scenarios + 7 CI sub-checks
✅ Analytics dashboard filters `reversed_at IS NULL` for net conversions

❌ NOT in v11B.2.1 (deferred — documented in REPORT "Remaining limitations"):
- Eligibility recheck adds 1 SQL query per cache hit per section (max 3 per product page) — trade-off for O(1) cascade + correctness guarantee
- Cache cascade is coarse (vendor suspension invalidates ALL cached recs not just that vendor's) — simplicity over precision
- Guest checkouts not attributed (null user_id) — privacy-correct
- Tag/brand similarity remains deferred (schema lacks tables)

## Phase 11B.2 v11B.2.1 STOPS HERE

No Phase 11B.3 work begun. Pending dev verification per directive §9 + §10 + §11.
