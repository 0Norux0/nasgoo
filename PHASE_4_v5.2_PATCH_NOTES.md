# Phase 4 v5.2 — Address Schema Mismatch Fix

**Targeted bug fix only.** Same Phase 4 scope as v5.0/v5.1. Phase 5 is still not started.

---

## What broke in v5.1

Clicking **Proceed to Checkout** as a logged-in customer raised:

```
SQLSTATE[42S22]: Column not found: 1054 Unknown column 'full_name' in 'SELECT'
select id, label, full_name, phone, line1, line2, city, region, postal_code, country, is_default
from addresses where addresses.user_id = 4 and addresses.deleted_at is null
order by is_default desc
```

## Root cause

When I wrote the Phase 4 `CheckoutController`, `CheckoutService`, `OrderAddress` model, the `order_addresses` migration, the Checkout React page, the Orders/Vendor order detail pages, and the related tests, I assumed a Western-style address schema with `full_name` / `line1` / `line2` / `region`. The actual Phase 1 `addresses` table is **Gulf-style** — appropriate for the Kuwait market the platform targets:

```
label, type, country, state (governorate), city, area, block, street,
building, floor, apartment, postal_code, phone, latitude, longitude, is_default
```

No `full_name`. No `line1` / `line2`. No `region`. My Phase 4 code referenced columns that simply weren't there. Every checkout-page load hit the SQL error before any UI could render.

This was my mistake — I should have read the Phase 1 migration before writing the Phase 4 address handling. I missed it, the structural validation sweep at end of v5.0 didn't catch it (a schema-vs-query check would have, and I've added one now — see below), and the v5.1 audit didn't catch it because every test that called the checkout flow used the same wrong field names, so they failed consistently in the same way and looked internally coherent.

---

## Approach taken — Option A (preferred)

Updated Phase 4 to use the existing Phase 1 schema. **No changes to the `addresses` table.** The Gulf schema is more accurate for Kuwait deliveries (block/street/building matters for last-mile) and the user is the platform's primary market — adding Western-style columns would force a permanent schema split.

For the `order_addresses` snapshot table (Phase 4's own table, only freshly created — no production data to migrate), I changed the schema **in place** in the original migration file. Anyone who applied v5.0 or v5.1 will need to run `php artisan migrate:fresh --seed` once to pick up the schema change. This is safe because no orders have been placed yet (the checkout flow has been broken since v5.0).

The one new column added to `order_addresses` is `recipient_name` — Phase 1 addresses don't store a recipient name (deliveries default to the account holder), so we capture it at order placement time. It defaults to `$user->name` when not explicitly supplied.

---

## Files changed (10 production + 5 tests + 1 new test + 1 CI + this doc)

### Production code

| File | What changed |
|---|---|
| `database/migrations/2026_01_04_000002_create_orders_tables.php` | `order_addresses` columns rewritten to mirror Phase 1 schema (drops full_name/line1/line2/region, adds recipient_name/state/area/block/street/building/floor/apartment/latitude/longitude) |
| `app/Models/OrderAddress.php` | `$fillable` matches new columns; new `singleLine()` formatter; lat/lng casts added |
| `app/Http/Controllers/CheckoutController.php` | `show()` selects the **real** columns from addresses, maps to frontend payload via `Address::fullAddressLine()`. `place()` validation rules match Phase 1 schema. New `has_addresses` + `user_name` props for the empty-state UI. |
| `app/Domain/Order/CheckoutService.php` | `resolveAddress()` reads from saved Phase 1 Address or inline payload using real fields. `snapshotAddress()` writes real fields into order_addresses. Defaults `recipient_name` to `$user->name`. |
| `app/Http/Controllers/OrderController.php` | `present()` `shipping_address` shape switched to Phase 1 fields |
| `app/Http/Controllers/Vendor/VendorOrderController.php` | Same |
| `resources/js/Pages/Checkout/Show.tsx` | Inline form replaced: recipient_name + country/state/city/area/block/street/building/floor/apartment/postal_code/phone. New empty-state for customers with no saved address — fills the form instead of crashing. |
| `resources/js/Pages/Orders/Show.tsx` | `ShippingAddress` interface + render block updated to multi-line Phase 1 layout |
| `resources/js/Pages/Vendor/Orders/Show.tsx` | Same |
| `database/factories/AddressFactory.php` | **New** — Gulf-style address factory using real columns (Kuwait governorates, KW phone format, block/street/building) |

### Tests

| File | What changed |
|---|---|
| `tests/Feature/CheckoutTest.php` | 4 occurrences of old payload replaced |
| `tests/Feature/CommissionSnapshotTest.php` | 3 occurrences replaced |
| `tests/Feature/Phase4CsrfTest.php` | 1 occurrence replaced |
| `tests/Feature/Phase4HttpFlowTest.php` | 5 occurrences replaced |
| `tests/Feature/CheckoutAddressSchemaTest.php` | **New — 9 scenarios** (the v5.2 regression guard) |

### CI

- `.github/workflows/ci.yml` verdict job title + pass message bumped to `Phase 4 v5.2 PASSES — ready to approve Phase 5`
- New row in audit-coverage map for the v5.2 fix
- New row in per-file results table pointing to `CheckoutAddressSchemaTest`

---

## The new regression test — `CheckoutAddressSchemaTest`

9 scenarios specifically designed to fail if this bug ever returns:

1. **`/checkout opens without SQL error for a customer with NO saved address`** — directly catches the original bug.
2. **`/checkout opens without SQL error for a customer WITH a saved address`** — proves the column list works on populated tables too.
3. **`address fields shared to the React page exist in the actual table`** — uses `Schema::getColumnListing('addresses')` to assert every column the controller selects actually exists. **Also asserts the phantom columns (`full_name`, `line1`, `line2`, `region`) do NOT appear** — so if a future PR re-introduces them, this fails immediately.
4. **`order_addresses schema mirrors Phase 1 addresses (no Western fields)`** — same schema pin for the snapshot table.
5. **`customer with saved address can place a COD order without error`** — end-to-end with a real Phase 1 address, verifies the snapshot row has the right fields populated.
6. **`customer with NO saved address can place an order via inline address`** — exercises the inline-form path.
7. **`checkout works end-to-end with manual_transfer`** — exercises the second provider.
8. **`checkout works end-to-end with online_mock`** — exercises the third provider.
9. **`/checkout returns no 419 on a real POST round-trip`** — pins CSRF on the new payload.

If this test passes, the bug cannot return without someone explicitly disabling the assertions.

---

## How to apply v5.2 to an existing v5.0/v5.1 deployment

The schema migration was modified in place. You need to re-run migrations:

```bash
# 1. Extract v5.2 over your current checkout
tar -xzf marketplace-phase-4-v5.2.tar.gz

# 2. Rebuild the Docker image (composer/npm caches change with the new test files)
docker compose down
docker compose build app
docker compose up -d

# 3. Re-run migrations from scratch (safe — no orders placed yet via the broken flow)
docker compose exec app php artisan migrate:fresh --seed
```

If you skip step 3, your `order_addresses` table will still have the wrong columns and orders will fail to save.

---

## Manual checklist for the developer

Same 14-step checklist from `PHASE_4_REPORT.md` still applies — every step should now pass without SQL error. Specifically verify:

| # | Walk this step | Expected with v5.2 |
|---|---|---|
| 1 | After `migrate:fresh --seed`, run `psql -c "\d order_addresses"` (or equivalent) | Columns include `recipient_name`, `state`, `area`, `block`, `street`, `building`, `floor`, `apartment`, `latitude`, `longitude`. Should NOT contain `full_name`, `line1`, `line2`, `region`. |
| 2 | Sign in as `customer@marketplace.test`, add a product, click "Proceed to checkout" | Page renders, no SQL error. If the customer has no saved address yet, the inline address form is shown by default. |
| 3 | Fill block 7, street "Beach Road", building 20, city "Kuwait City", country "KW" — pick COD — place order | Lands on `/orders/{id}/confirm`. The snapshot in `order_addresses` should show those exact values (check via admin order detail). |
| 4 | Repeat with Manual Bank Transfer | Confirm page shows `BT-{number}-{hash}` reference. |
| 5 | Repeat with Card / Online (Demo) | Order shows `payment_status = paid` immediately, external_id starts with `MOCK-`. |
| 6 | View the order detail at `/orders/{id}` | Shipping address renders multi-line with building/floor/apt on first line, street/block on next, then city/state, then country. No fields show as `undefined`. |
| 7 | Sign in as vendor, view `/vendor/orders/{id}` | Same address render as above, with the recipient_name + phone visible. |
| 8 | Open GitHub Actions for this branch | Verdict: **`✅ Phase 4 v5.2 PASSES — ready to approve Phase 5`**, with `CheckoutAddressSchemaTest` listed in the per-file table. |

---

## Stop discipline

**Phase 5 is not started.** v5.2 is purely a fix for the v5.0/v5.1 checkout SQL error. Reply **"approve Phase 5"** only after:
1. CI is green with the v5.2 verdict visible
2. The 8-step v5.2 manual checklist above passes
3. The 14-step Phase 4 checklist in `PHASE_4_REPORT.md` still works end-to-end

I'll wait for explicit approval before starting Phase 5.
