# Phase 11B.4 — Patch Notes

## Summary

Vendor Intelligence: deterministic, database-driven suggestions and alerts for vendors. Reads real signals from v11B.2.2 orders, v11B.3 personalization views, v11B.3.1 settings, and Phase 4 wishlists/carts. Everything auditable, no ML, no external APIs, no fake trends.

## What's new for vendors

- **Vendor Dashboard Intelligence panel** — summary counters (OOS / low-stock / slow-moving / store %), action alerts ranked by priority, action checklist, top-selling highlights
- **Inventory alerts**: `out_of_stock` (critical), `low_stock`, `fast_moving_low_stock` (HIGH), `slow_moving`, `no_stock_tracking`
- **Opportunity suggestions**: `high_view_low_conversion`, `wishlist_interest`, `cart_abandonment`, `promotion_opportunity`
- **Product quality score** (0-100) with visible breakdown across 6 groups (core / media / i18n / inventory / seo / policy)
- **Dismiss / snooze** any suggestion — except critical types (`out_of_stock`, `fast_moving_low_stock`) which cannot be permanently dismissed
- Store completion score with actionable checklist

## What's new for admins

- `/admin/vendor-intelligence` — aggregate overview of all vendors' signals, filterable by low_stock / incomplete_stores / missing_arabic / many_pending
- All thresholds (`low_stock_threshold`, `fast_moving_days`, `min_views_for_conversion`, quality weights, etc.) editable via existing `/admin/site-settings` (v11B.3.1) — no code changes to tune the engine
- 4 additive migration tables — safe to roll back

## What's new for CI

- 7 new sub-checks that verify: services + migration exist, vendor isolation enforced, quality weights configurable, commands chunked + `--vendor=` supported, UI wired to live pages, localization keys present, Pest suite ≥45 scenarios

## Files added (14 new)

**Models (4)**:
- `app/Models/VendorIntelligenceSummary.php`
- `app/Models/VendorIntelligenceAlert.php`
- `app/Models/VendorIntelligenceFeedback.php`
- `app/Models/VendorProductQualityScore.php`

**Services (7)**:
- `app/Services/VendorIntelligence/VendorIntelligenceManager.php`
- `app/Services/VendorIntelligence/InventoryAlertService.php`
- `app/Services/VendorIntelligence/ProductQualityService.php`
- `app/Services/VendorIntelligence/VendorOpportunityService.php`
- `app/Services/VendorIntelligence/VendorPerformanceService.php`
- `app/Services/VendorIntelligence/VendorActionChecklistService.php`
- `app/Services/VendorIntelligence/VendorIntelligenceCacheService.php`

**Controllers (2)**:
- `app/Http/Controllers/Vendor/VendorIntelligenceController.php`
- `app/Http/Controllers/Admin/VendorIntelligenceController.php`

**Commands (2)**:
- `app/Console/Commands/GenerateVendorIntelligence.php`
- `app/Console/Commands/PruneVendorIntelligence.php`

**Migration (1)**:
- `database/migrations/2026_11_01_000001_create_vendor_intelligence_tables.php`

**React (2)**:
- `resources/js/Components/VendorIntelligence/VendorIntelligencePanel.tsx`
- `resources/js/Pages/Admin/VendorIntelligence/Overview.tsx`

**Test + docs (6)**:
- `tests/Feature/Phase11B4VendorIntelligenceTest.php` (53 scenarios)
- 5 v11B.4 delivery docs

## Files modified (5)

- `config/site.php` — +vendor_intelligence group (12 keys)
- `resources/js/Pages/Vendor/Dashboard.tsx` — panel wired for approved vendors
- `routes/web.php` — +4 routes with rate limiting
- `lang/en.json` + `lang/ar.json` — +34 keys each
- `.github/workflows/ci.yml` — +7 v11B.4 sub-checks
- `VERSION` — `Phase 11B.3 v11B.3.3` → `Phase 11B.4`

## Counts

| Metric | v11B.3.3 → v11B.4 |
|---|---|
| CI sub-checks | 193 → **200** (+7) |
| Pest scenarios | 843 → **896** (+53) |
| Unique Pest helpers | 164 → **169** (+5 p11b4_*) |
| Migrations added | +1 (4 tables) |
| Localization keys | +34 en + 34 ar |
| New services | +7 |
| New models | +4 |
| New controllers | +2 |
| New commands | +2 |
| New React components/pages | +2 |
| New routes | +4 |

## Required-result mapping (per directive)

| Required proof | Status |
|---|---|
| Vendors get inventory alerts | ✅ 5 types with correct priority (Pest §34.1-§34.11) |
| Priority levels applied correctly | ✅ critical/high/medium enforced (Pest §34.1, §34.5) |
| Fast-selling low-stock upgraded to HIGH | ✅ (Pest §34.5) |
| Slow-moving surfaced only for eligible products | ✅ (Pest §34.6, §34.7) |
| Product quality score 0-100 with missing fields | ✅ (Pest §35.13-§35.23) |
| Sales opportunity suggestions rule-based | ✅ HVLC/wishlist/cart (Pest §36.24-§36.27) |
| Suggestions do NOT include fake demand or ML | ✅ deterministic; grep for `min_views` in code |
| Vendor sees only own alerts | ✅ Pest §35.23, §37.36 |
| Critical alerts cannot be permanently dismissed | ✅ (Pest §36.29) |
| Suggestions can be dismissed/snoozed | ✅ (Pest §34.12, §36.28) |
| Store completion + checklist | ✅ (Pest §37.30 verifies structure) |
| Admin overview exists | ✅ (Pest §37.33, §37.34) |
| Thresholds admin-configurable via settings | ✅ 12 keys under vendor_intelligence group |
| Vendor A cannot dismiss vendor B's alert | ✅ Pest §37.36 sends hostile entity_id, vB unaffected |
| Cache key vendor-isolated | ✅ Pest §37.35 |
| Dashboard payload ≤ query budget | ✅ ≤25 queries Pest §38.39 |
| Batch command idempotent, chunked | ✅ chunkById + Pest §38.40, §38.41 |
| Localization complete en + ar | ✅ 34 + 34 keys |
| Preserves v11B.3.x / v11B.2.x / v10.13 | ✅ 9 regression scenarios (Pest §38.45-§38.53) |

## Deploy commands

```bash
php artisan optimize:clear
php artisan migrate                               # 1 new migration (4 tables)
php artisan migrate:status | grep 2026_11         # confirm Ran=Yes
php artisan vendor-intelligence:generate          # populate initial data (all approved vendors)
php artisan test --filter=Phase11B4               # 53 v11B.4 scenarios
php artisan test --filter=Phase11B33              # 33 v11B.3.3 regression
php artisan test                                   # 896 total
npm ci && npm run typecheck && npm run build
```

## After deploy — manual verification

1. Log in as an approved vendor with a product whose stock is 0
2. Load `/vendor` — expect the intelligence panel to render with an `Out of stock` alert marked **critical**
3. Try to dismiss it — dismiss button is not visible for critical types (only Snooze + Review)
4. Change the product's stock to 3 → run `php artisan vendor-intelligence:generate --vendor=X` → refresh → Out-of-stock is now Resolved; Low stock is Active
5. Snooze the low-stock alert → refresh → it disappears from the panel until snooze expires
6. Log in as super_admin → visit `/admin/vendor-intelligence` — see aggregate table with filter chips
7. Filter by `low_stock` — table sorts by low_stock_count desc

## Rollback

3-tier procedure in `PHASE_11B_4_ROLLBACK.md`. Tier 3 destination is `marketplace-phase-11B-3-3-final-approved.tar.gz`.

## Honest scope

✅ Deterministic scoring — no ML, no LLM, no external API
✅ Vendor isolation enforced server-side (never trusts request body vendor_id)
✅ Critical alerts cannot be permanently dismissed
✅ All thresholds admin-tunable via existing SiteSettingsService
✅ Vendor-isolated cache with defensive fallback
✅ Chunked generation command with per-vendor error isolation
✅ v11B.3.3 CSS root-cause fix preserved (no letter-by-letter wrap)
✅ All prior phase preservation markers intact
✅ 53 Pest scenarios covering §34-§38 test groups

❌ NOT in v11B.4 (deferred, documented in REPORT):
- Variant-level stock alerts (parent product only)
- Email notifications (dashboard-only)
- Product-detail-page quality badge
- Vendor reports page embed
- Search-demand suggestions (no analytics table yet)
- Category benchmarking
- Dedicated admin threshold editor UI (edit via /admin/site-settings raw JSON)
- Scheduled auto-generation (manual command only)

## Phase 11B.4 STOPS HERE

Not started: smart pricing, demand forecasting, plain-language narratives, support assistant, risk scoring, credit scoring. Pending dev verification.
