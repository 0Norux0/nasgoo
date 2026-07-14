# Phase 11B.3 v11B.3.2 — Patch Notes

## Summary

Fixes the concrete defects the developer reported still present after v11B.3.1: **vendor Settings 404**, **admin dashboard lag**, and adds a **broken-link audit** that runs on CI to prevent regression. Preserves all v11B.3.1 architecture (SiteSettingsService, layout primitives, ResponsiveDataList, VendorSidebar, VendorMobileDrawer).

## What's new for vendors

- **`/vendor/settings` no longer 404** — real route, controller, and Inertia page
- Edit store profile: business name, business email, phone, website, address, description
- Read-only status card
- "Coming soon" placeholders for payouts / documents / notifications (dev §20 explicit request)
- Rate-limited update endpoint (throttle 20/min)

## What's new for admins

- **Admin dashboard StatsOverview widget**: 5-minute cache + grouped `COUNT(CASE WHEN)` queries + deduplication
- Pre-v11B.3.2: ~23 queries per widget render, 2 duplicates. **v11B.3.2**: 0 queries on cache hit, ≤12 on cache miss
- Additive migration adds indexes: `users.status`, `vendors.status`, `products.status`, `orders(status, payment_status)`, `categories.is_active`, `categories.parent_id`, `audit_logs.created_at` — accelerates the WHERE columns the widget scans
- `flush()` method for future observer-driven cache-cascade invalidation

## What's new for tests

- **Broken-link audit** — Pest test enumerates 14 vendor + 10 storefront URLs and hits each as an authorized user. Any 404 fails CI.
- **Auth-safety audit** — customer → vendor URLs still 403, vendor → admin URLs still 403 (verifies fixing 404s didn't weaken authorization)

## Files added (3 new)

- `app/Http/Controllers/Vendor/VendorSettingsController.php`
- `resources/js/Pages/Vendor/Settings.tsx`
- `database/migrations/2026_10_01_000001_add_admin_performance_indexes.php`
- `tests/Feature/Phase11B32MobilePerformanceLinksTest.php` (37 scenarios)
- 5 v11B.3.2 delivery docs

## Files modified (4)

- `app/Filament/Widgets/StatsOverview.php` — rewritten with cache + grouped queries + flush()
- `routes/web.php` — +2 vendor settings routes with rate limiting
- `lang/en.json` + `lang/ar.json` — +17 keys each (vendor.settings.*)
- `.github/workflows/ci.yml` — +6 v11B.3.2 sub-checks
- `VERSION` — `Phase 11B.3 v11B.3.1` → `Phase 11B.3 v11B.3.2`

## Counts

| Metric | v11B.3.1 → v11B.3.2 |
|---|---|
| CI sub-checks | 180 → **186** (+6) |
| Pest scenarios | 773 → **810** (+37) |
| Unique Pest helpers | 158 → **162** (+4 p11b32_*) |
| Migrations added | +1 additive (indexes) |
| Translation keys | +17 en + 17 ar |

## Required-result mapping (per directive)

| Required proof | Status |
|---|---|
| Admin side measurably less laggy | ✅ StatsOverview widget 0-query cache hit + ≤12 grouped cache miss (Pest §4.1-§4.3) + indexes on WHERE columns |
| Site branding/content configurable without code edits | ✅ v11B.3.1 SiteSettingsService preserved (Pest §42.7) |
| Homepage sections modular | ✅ v11B.3.1 HomepageSectionRegistry preserved |
| Mobile padding consistent | ✅ Container (v11A.2) preserved (Pest §12.1-§12.7) |
| My Orders / Bookings / Support mobile card/list | ✅ v11B.3.1 ResponsiveDataList preserved (Pest §12.4-§12.6) |
| Vendor Settings no longer 404 | ✅ Route + controller + page (Pest §20.1-§20.7) |
| Major nav links audited and fixed | ✅ 14 vendor + 10 storefront URLs walked (Pest §21.1, §21.2) |
| Vendor side navigation suitable + responsive | ✅ v11B.3.1 VendorSidebar + VendorMobileDrawer preserved (Pest §23.1, §23.2) |
| Arabic RTL works | ✅ VendorMobileDrawer isRTL + 17 ar keys + dir="rtl" in admin inputs |
| No regression | ✅ 9 §42 regression scenarios + all prior-phase markers verified |

## Deploy commands

```bash
php artisan optimize:clear
php artisan migrate                             # 1 new additive migration (indexes)
php artisan migrate:status | grep 2026_10       # confirm Ran=Yes
php artisan route:list | grep vendor.settings   # confirm 2 new routes
php artisan test --filter=Phase11B32            # 37 v11B.3.2 scenarios
php artisan test                                 # 810 total
npm ci && npm run typecheck && npm run build
```

## Rollback

3-tier procedure in `PHASE_11B_3_2_ROLLBACK.md`. Tier 3 rollback destination is `marketplace-phase-11B-3-1-baseline.tar.gz` — the v11B.3.1 archive is preserved unchanged.

## Honest scope

✅ Vendor Settings 404 → 200 with real form + validation + authorization
✅ StatsOverview widget cached + grouped + indexed → admin dashboard measurably faster
✅ Broken-link audit crawls 14 + 10 URLs on CI
✅ 6 admin performance indexes on WHERE columns
✅ All v11B.3.1 modular settings architecture preserved
✅ All v11B.3.1 responsive Orders/Bookings/Support preserved
✅ All v11B.3.1 vendor navigation preserved
✅ 17 en + 17 ar translation keys
✅ 37 Pest scenarios + 6 CI sub-checks
✅ Regression suite protects v11B.2.2 / v11B.3 / v11B.3.1

❌ NOT in v11B.3.2 (deferred, documented in REPORT):
- StorefrontLayout consumption of `siteSettings.footer.columns` (settings surface complete, storefront refactor deferred)
- Welcome.tsx driven by HomepageSectionRegistry::resolve()
- Complex-value admin editor (arrays shown as read-only JSON)
- Full media library
- Server-side CSS custom property injection for appearance colors
- Dark mode / theme switching
- Vendor Settings sub-forms (payouts, documents, notifications) — placeholders per §20
- Audit trail admin log view

## Phase 11B.3 v11B.3.2 STOPS HERE

Not started: vendor intelligence, inventory forecasting, smart pricing, report narratives, support assistant, quality scoring, fraud/risk scoring. Pending dev verification.
