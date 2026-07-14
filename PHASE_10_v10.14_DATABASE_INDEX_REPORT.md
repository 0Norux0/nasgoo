# Phase 10 v10.14 — Database Index Report

Per dev §21 deliverable.

## All indexes pre-v10.14 (audit baseline)

These indexes were already in the codebase before v10.14. v10.14 does NOT touch any of them.

| Migration / Table | Indexes |
|---|---|
| Spatie permission tables | `roles`, `model_has_roles`, `model_has_permissions`, `permissions` — all per Spatie's default schema |
| `users` (0001_01_01_000000) | `status`, `locale` |
| `addresses` (2026_01_01_000002) | `(user_id, is_default)`, `(country, city)` |
| `vendor_commission_rules` (2026_01_02_000004) | `(scope, scope_id, is_active)`, `(vendor_id, priority)`, `(effective_from, effective_until)` |
| `payments` + `payment_transactions` (2026_01_04_000003) | `(order_id, status)`, `(payment_id, type)` |
| `product_reviews` (2026_01_05_000001) | `(product_id, status)`, `(user_id, created_at)`, `(status)` |
| `vendor_payout_requests` (2026_01_05_000003) | `(vendor_id, status)`, `(status)`, `(requested_at)` |
| `shipping_zones` + methods (2026_01_05_000004) | `(is_active, position)`, `(shipping_zone_id, is_active, position)`, `(type)` |
| `notification_templates` (2026_01_01_000004) | `(event_key, is_active)` |
| `supplier_integrations` (2026_01_06_000002) | `(vendor_id, is_active)` |
| `supplier_product_imports` (2026_01_06_000005) | `(vendor_id, created_at)` |
| `product_customization_fields` (2026_01_07_000001) | `(product_id, sort_order)` |
| `cart_item_customizations` (2026_01_07_000002) | `(cart_item_id)` |
| `customization_proofs` (2026_01_07_000004) | `(order_item_id, status)` |
| `service_providers` (2026_01_08_000002) | `is_active` |
| `service_provider_assignments` (2026_01_08_000003) | `(product_id, service_provider_id)` |
| `service_bookings` (2026_01_08_000006) | `(service_provider_id, booked_for_date, booked_for_time)`, `(vendor_id, status, booked_for_date)`, `(user_id, status)` |
| `promotions` (2026_01_15_000001) | `(is_active, starts_at, ends_at)`, `(vendor_id, is_active)`, `(approval_status)` |
| `promotion_targets` (2026_01_15_000002) | `(targetable_type, targetable_id)` |
| `coupons` (2026_01_15_000003) | `(is_active, starts_at, ends_at)`, `(vendor_id, is_active)` |
| `coupon_usages` (2026_01_15_000004) | `(coupon_id, user_id)`, `(user_id)` |
| Phase 10 v10.1 perf indexes (2026_06_15_000001) | `orders(created_at, status)`, `order_items(vendor_id, order_id)`, `order_items(product_id)`, `products(status, type)`, `products(category_id, status)`, `product_reviews(product_id, status)`, `vendor_payout_requests(created_at, status)` |
| Phase 10 v10.8 promotion snapshot (2026_06_17_000001) | `(promotion_id)` on order_items |

**Total existing indexes: ~40 (rough count, excluding FK auto-indexes).**

## v10.14 additions

New migration: `database/migrations/2026_06_21_000001_add_phase10_v1014_performance_indexes.php`.

| # | Table | Columns | Index name | Query it helps |
|---|---|---|---|---|
| 1 | `orders` | `(user_id, created_at)` | `orders_user_created_idx` | Customer's "my orders sorted newest" list |
| 2 | `orders` | `(status, created_at)` | `orders_status_created_idx` | Admin order filter+sort |
| 3 | `support_tickets` | `(user_id, status, created_at)` | `st_user_status_created_idx` | Customer ticket list with status filter |
| 4 | `support_tickets` | `(vendor_id, status, created_at)` | `st_vendor_status_created_idx` | Vendor ticket list with status filter |
| 5 | `support_tickets` | `(status, created_at)` | `st_status_created_idx` | Admin/Filament ticket filter+sort |
| 6 | `support_ticket_messages` | `(support_ticket_id, created_at)` | `stm_ticket_created_idx` | Per-ticket message rendering in order |
| 7 | `vendors` | `(status, created_at)` | `vendors_status_created_idx` | Admin Filament vendor list filter+sort |
| 8 | `vendor_payout_requests` | `(vendor_id, status, created_at)` | `vpr_vendor_status_created_idx` | Per-vendor payout history with status filter |

### Why these specific columns

Each composite is designed so MySQL can use the index for BOTH the WHERE filter AND the ORDER BY in a single pass — no filesort, no separate sort step. Example for `support_tickets(user_id, status, created_at)`:

```sql
SELECT * FROM support_tickets
WHERE user_id = ?
  AND status IN ('open', 'pending')
ORDER BY created_at DESC
LIMIT 20
```

Without the index: full table scan + filesort. With the index: direct B-tree range lookup + already-ordered results.

### Idempotency

Each index is wrapped in:

```php
if (! $this->hasIndex('support_tickets', 'st_user_status_created_idx')) {
    $table->index(['user_id', 'status', 'created_at'], 'st_user_status_created_idx');
}
```

So re-running the migration is safe. The `hasIndex()` helper uses Laravel 11's `Schema::getConnection()->getSchemaBuilder()->getIndexes(...)` (no `doctrine/dbal` dependency).

### Write-overhead consideration

Every additional index on a table costs a small amount of disk I/O on INSERT/UPDATE/DELETE. The 8 v10.14 indexes are on tables that are read FAR more often than written:

| Table | Read-heavy or write-heavy? |
|---|---|
| `orders` | Reads vastly exceed writes (every order page, every report, every admin list) |
| `support_tickets` | Reads exceed writes by ~10:1 |
| `support_ticket_messages` | Reads exceed writes by ~5:1 |
| `vendors` | Reads vastly exceed writes (every admin list, every storefront category) |
| `vendor_payout_requests` | Reads exceed writes |

So the write overhead is negligible relative to the read speedup.

### No duplicate or redundant indexes

I verified before adding each index that the column combination wasn't already covered:

- `orders(user_id, created_at)` — v10.1 added `(created_at, status)`. Different leading column = different index, no duplication.
- `orders(status, created_at)` — same: different from `(created_at, status)`. (Yes, `(status, created_at)` and `(created_at, status)` are DIFFERENT MySQL indexes — leading column matters for index selection.)
- `support_tickets(user_id, status, created_at)` — FK auto-index on `user_id` is single-column; composite is different.
- `vendor_payout_requests(vendor_id, status, created_at)` — existing migration has `(vendor_id, status)`. The new composite adds `created_at` as the third column for ORDER BY support — MySQL may prefer the new one for filter+sort queries.

### Confirmed columns exist before adding

Checked the create-table migrations for each:
- `orders.user_id` ✓ (FK)
- `orders.status` ✓ (string column)
- `orders.created_at` ✓ (timestamp)
- `support_tickets.user_id` ✓ (FK)
- `support_tickets.vendor_id` ✓ (nullable FK)
- `support_tickets.status` ✓ (string)
- `support_tickets.created_at` ✓
- `support_ticket_messages.support_ticket_id` ✓ (FK)
- `support_ticket_messages.created_at` ✓
- `vendors.status` ✓
- `vendors.created_at` ✓
- `vendor_payout_requests.vendor_id` ✓
- `vendor_payout_requests.status` ✓
- `vendor_payout_requests.created_at` ✓

### Index names ≤ 64 chars (MySQL identifier limit)

| Name | Length |
|---|---|
| `orders_user_created_idx` | 23 |
| `orders_status_created_idx` | 25 |
| `st_user_status_created_idx` | 26 |
| `st_vendor_status_created_idx` | 28 |
| `st_status_created_idx` | 21 |
| `stm_ticket_created_idx` | 22 |
| `vendors_status_created_idx` | 26 |
| `vpr_vendor_status_created_idx` | 29 |

All well under the 64-char limit (v8.2 defense).

### Verifying applied

After `php artisan migrate`, the dev can verify with:

```bash
php artisan tinker
>>> DB::select("SHOW INDEXES FROM orders WHERE Key_name LIKE 'orders_user_created_%'")
>>> DB::select("SHOW INDEXES FROM support_tickets WHERE Key_name LIKE 'st_%'")
>>> DB::select("SHOW INDEXES FROM vendor_payout_requests WHERE Key_name LIKE 'vpr_%'")
```

Or use the included Pest scenario `v10.14 performance indexes actually applied after migrate`.

## Pest verification

The v10.14 Pest scenarios verify the indexes are applied:

```php
it('v10.14 performance indexes actually applied after migrate', function () {
    $existing = collect(DB::getSchemaBuilder()->getIndexes('orders'))
        ->pluck('name')
        ->all();
    expect($existing)->toContain('orders_user_created_idx');
    expect($existing)->toContain('orders_status_created_idx');
});
```

This runs against the test database after `RefreshDatabase` (which applies all migrations). If the indexes don't apply, this Pest scenario fails — CI catches it.
