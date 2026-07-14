# Phase 10 v10.14 — Patch Notes

## What's optimized

| Defect | Root cause | Fix |
|---|---|---|
| `cart_summary` Inertia shared prop fired a `User->cart` HasOne query on EVERY Inertia render — admin/vendor pages, which DON'T render the cart badge, paid this cost on every navigation (10-20 wasted queries per session for an admin). | Closure unconditionally ran for any authenticated user. | **`HandleInertiaRequests::share()` cart_summary is now scope-aware**: admin/vendor/api paths return null without touching the cart relation. Storefront paths unchanged. |
| `top_categories` Inertia shared prop ran a cache lookup (or full categories table scan on cold cache) on every Inertia render — same admin/vendor wastage. | Same — closure unconditionally fired. | **Same scope-aware pattern**: admin/vendor/api paths return [] without the cache call. |
| Public homepage `/` ran a 2-second `curl` Meilisearch health probe on every render. If Meilisearch was unreachable (a common dev-env case), the homepage was slow by 2+ seconds per request. | `HomeController::index` fired 4 health probes inline including a `checkMeilisearch()` with `CURLOPT_TIMEOUT=2`. | **Health probe cached 30s** via `Cache::remember('marketplace:homepage_health:v1', addSeconds(30), ...)`. First request per 30s window pays the probe cost; subsequent requests are free. |
| Missing composite indexes for hot list-page filter+sort queries (`orders(user_id, created_at)`, `support_tickets(user_id, status, created_at)`, etc.). | v10.1 covered reports queries; these list-page patterns were never indexed. | **NEW migration** `2026_06_21_000001_add_phase10_v1014_performance_indexes.php` adds 8 composite indexes. Idempotent (each wrapped in `hasIndex()` check). |

## Counts

| | v10.13 → v10.14 |
|---|---|
| Phase 10 CI sub-checks | 55 → 59 |
| Phase 10 Pest scenarios | 189 → 204 |
| PHP source files modified | 2 (`HandleInertiaRequests`, `HomeController`) |
| New files | 2 (migration + Pest test) |
| React files modified | 0 |
| v1-v9 files touched | 0 |
| v10.0-v10.13 fix code reverted | 0 |
| Helpers added | 4 (`p1014_seed`, `p1014_admin`, `p1014_approved_vendor_user`, `p1014_run_with_query_log`) — 81 total unique, 0 duplicates |

## New v10.14 indexes (the gaps after v10.1)

| Table | Index | Purpose |
|---|---|---|
| `orders` | `(user_id, created_at)` | Customer's "my orders sorted newest" |
| `orders` | `(status, created_at)` | Admin order filter+sort |
| `support_tickets` | `(user_id, status, created_at)` | Customer ticket list with status filter |
| `support_tickets` | `(vendor_id, status, created_at)` | Vendor ticket list with status filter |
| `support_tickets` | `(status, created_at)` | Admin/Filament ticket filter+sort |
| `support_ticket_messages` | `(support_ticket_id, created_at)` | Per-ticket ordered message fetch |
| `vendors` | `(status, created_at)` | Admin Filament vendor list filter+sort |
| `vendor_payout_requests` | `(vendor_id, status, created_at)` | Per-vendor payout history with status filter |

All names ≤ 64 chars (MySQL identifier limit, v8.2 defense). All wrapped in `hasIndex()` for idempotency.

## What v10.14 explicitly DOES NOT change

Per dev "Do not add new features. Do not change working business rules unless a change is necessary to eliminate a confirmed performance bottleneck":

- No routes changed
- No business logic changed
- No financial calculations changed
- No authorization changed
- No React components changed
- No new dependencies
- v10.0-v10.13 fix code preserved (verified by Pest regression scenarios)

## What v10.14 does NOT claim

- No runtime timings. I cannot measure response times in this sandbox. The dev's §16 manual walkthrough + Telescope/debugbar measurement against their database is the acceptance gate.
- No promises about production speed. Only patterns identified and code paths reduced.
- No bundle-size analysis. The dev's `npm run build` output is the source of truth.

## Per dev §22 acceptance wording

**Phase 10 v10.14 contains performance improvements but requires developer runtime measurement and verification.**

(The dev's spec explicitly allows this wording when runtime measurements could not be performed.)

Dev runs:
```bash
composer install
npm ci
php artisan optimize:clear
php artisan migrate:status                                    # → v10.14 indexes migration listed
php artisan migrate                                           # → new indexes applied
php artisan test --filter='Phase10V1014'                      # → 15 Pest scenarios pass
php artisan test                                              # → full suite passes
npm run typecheck
npm run build
```

Then manually verifies §16 (homepage, products, cart, checkout, vendor dashboard, vendor orders, vendor reports, support tickets, admin reports, mobile nav, etc.) per `PHASE_10_v10.14_DEVELOPER_CHECKLIST.md`.
