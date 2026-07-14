# Phase 11B.5 — Patch Notes

## Summary

v11B.4 shipped a vendor intelligence module that failed at runtime. This release fixes 6 concrete bugs found via static schema audit + rewrites the Pest suite to use real factories that respect FK constraints. No new features.

## Files modified (2)

- `app/Services/VendorIntelligence/ProductQualityService.php` — media check now uses `$p->images()->count()` via the HasMany relation. Comment documents the pre-v11B.5 bug (Product::boot() strips 'images' from mass assignment).
- `app/Services/VendorIntelligence/VendorIntelligenceManager.php` — `activeSupportTicketCount()` now uses the correct `support_tickets` table.

## Files rewritten (1)

- `tests/Feature/Phase11B4VendorIntelligenceTest.php` — 56 scenarios using real factories:
  - New helper `p11b4_order_for_product(Product, User, status, when)` uses `Order::factory()` which respects unique `number` + user_id FK, then creates OrderItem with required `vendor_id` + `product_name` snapshot.
  - Rewritten `p11b4_product()` accepts `__image_count` override → inserts real product_images rows.
  - `customer_product_views` inserts use `session_key` + REQUIRED `locale`.
  - `wishlists` inserts create real Users via factory each iteration.
  - `cart_items` inserts create real Cart via CartFactory + include vendor_id.
  - §38.45-47 end-to-end scenarios simulate the real browser flow.

## Files added (0)

No new services, models, migrations, controllers, or React components. Pure repair.

## What was in v11B.4 and is preserved

All 4 migration tables, all 7 services, both controllers, both commands, both React components (VendorIntelligencePanel, Admin/VendorIntelligence/Overview), 4 routes, config extension, 34 en + 34 ar localization keys.

## Deploy commands the dev must run

```bash
php artisan optimize:clear
php artisan migrate:status                       # confirm 4 vendor_intelligence tables from v11B.4
php artisan migrate                              # applies v11B.4 migration if not already
php artisan vendor-intelligence:generate         # populate summaries + alerts for all approved vendors
php artisan test --filter=Phase11B4              # 56 scenarios — this is what proves correctness
npm ci && npm run typecheck && npm run build
```

## Manual browser verification after deploy

1. Load `/vendor` as an approved vendor account with at least one 0-stock product. Expect: intelligence panel renders below status banner with a "critical" priority "Out of stock" alert.
2. Check summary counters show real numbers (not "N/A"). If they show 0, run `php artisan vendor-intelligence:generate --vendor={vendor_id}` first.
3. Click "Snooze" on a non-critical alert. Expect: alert vanishes from the panel. DB row's `status` = `snoozed`, `expires_at` set to now + default snooze days.
4. Try to click "Dismiss" on the "Out of stock" alert. Expect: button not visible (critical alerts are non-dismissable per §17 §32).
5. Load `/admin/vendor-intelligence` as super_admin. Expect: aggregate table with filter chips. All values populated.
6. Test vendor-isolation: log in as vendor A, POST to `/vendor/intelligence/dismiss` with vendor B's product_id in the body. Expect: nothing on vendor B's data changes (controller uses `$request->user()->vendor` for identity).

## Counts

- CI sub-checks: 200 → **~206** (+6 v11B.5 verification block)
- Pest scenarios: 896 → **899** (net +3: 53 rewritten and expanded to 56)
- Unique Pest helpers: 169 → **170** (+1 net for `p11b4_order_for_product`)
- Migrations added: 0 (bug fix only)
- New models/services/controllers/commands: 0

## Rollback

- Tier 1: revert only ProductQualityService.php + VendorIntelligenceManager.php from `marketplace-phase-11B-4-baseline.tar.gz` (keeps schema)
- Tier 2: full revert to v11B.4 baseline
- Tier 3: chain back to v11B.3.3 approved baseline (`marketplace-phase-11B-3-3-final-approved.tar.gz`)

See PHASE_11B_5_ROLLBACK.md for exact commands.

## Honest scope

**Yes:**
- 6 real runtime bugs found and fixed at source
- 56 Pest scenarios using real factories that respect all schema constraints
- End-to-end scenarios (§38.45-47) that simulate the actual browser flow
- All v11B.4 architecture preserved
- All prior-phase (v11B.3.x, v11B.2.2, v10.13) markers intact

**No:**
- Cannot run migrations in this sandbox — dev must run `php artisan migrate`
- Cannot run Pest in this sandbox — dev must run `php artisan test --filter=Phase11B4`
- Cannot browser-test in this sandbox — dev must load pages
- No new features added in v11B.5
- No changes to personalized homepage (audit confirmed it wasn't broken by v11B.4)
