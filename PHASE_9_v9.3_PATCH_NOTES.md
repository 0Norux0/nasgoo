# Phase 9 v9.3 — Correction Package

**Status:** Targeted correction on top of Phase 9 v9.1. Pending CI verification.
**Scope:** 3 real bugs from the developer report fixed at their root cause. 10 new Pest scenarios, 5 new CI sub-checks, 1 new migration (per-line coupon allocation).

---

## The 3 bugs reported by your developer

| # | Symptom | Root cause | Fix |
|---|---|---|---|
| 1 | "Coupon doesn't persist beyond cart; not visible on checkout/order; not in vendor breakdown; vendor earnings inconsistent with discount" | Multiple gaps: (a) `CheckoutController::show` didn't include `coupon` in its props, so the checkout summary showed subtotal as if no coupon was applied; (b) `OrderController::present` didn't expose a coupon block to the customer's order detail page; (c) `VendorOrderController` exposed no coupon info at all; (d) `CheckoutService` computed commission and vendor earnings on the GROSS line total, ignoring the cart-level coupon discount — so vendor earned commission-on-gross while customer paid less, meaning platform paid out more than it collected. | Five coordinated changes: (i) new migration adds `coupon_allocation_minor` to `order_items`; (ii) `CheckoutService` allocates the order-level coupon discount across lines proportionally (`floor(coupon × line_total / subtotal)` with remainder on last line for exact reconciliation); (iii) commission computed on NET (post-allocation) line total; (iv) `CheckoutController/OrderController/VendorOrderController` all expose coupon blocks; (v) `Cart/Show.tsx`, `Checkout/Show.tsx`, `Orders/Show.tsx`, `Vendor/Orders/Show.tsx` render coupon lines + per-vendor allocation summary. |
| 2 | "No Write a Review button even after delivery" | The v9.1 implementation was correct in concept but had a hidden N+1 — `OrderController::present` did `Product::where('id', $i->product_id)->value('slug')` for each item to render the link. With `Model::shouldBeStrict()` enabled, the per-item subquery worked but item.product (used elsewhere) lazy-loaded; AND the test cases that "passed" weren't exercising the actual page render. | `OrderController::show` + `confirm` now eager-load `items.product:id,slug,name`; `present()` uses `$i->product?->slug` instead of the inline subquery. The Write Review link renders reliably for delivered items where the product still exists. |
| 3 | "Admin support-ticket page still throws `LazyLoadingViolationException: Attempted to lazy load [user] on model [App\Models\SupportTicketMessage]`" | My v9.1 `ViewSupportTicket` page used an Infolist with `RepeatableEntry::make('messages')` containing `TextEntry::make('user.name')`. I never called `->load()` / `->with()` to eager-load `messages.user` before Filament resolved the record. The Infolist accessed `$message->user->name` per row, strict mode threw on lazy load, and the page crashed. | `ViewSupportTicket::resolveRecord()` now overrides Filament's default resolver to fetch the ticket WITH `messages.user`, `user`, `vendor`, `order`, `assignee` eager-loaded in a single query. Additionally `SupportTicketResource::getEloquentQuery()` is overridden so the list page (which renders `user.email`, `vendor.business_name`, `order.number` columns) also gets eager-loading. |

---

## The allocation algorithm (financially critical)

Documented in `app/Domain/Order/CheckoutService.php` and asserted by 4 Pest scenarios + 1 CI invariant check. Given:

- `subtotal` = sum of all `line_total[i]`
- `couponDiscount` = the order-level coupon discount (in minor units, KWD has 3 decimals so minor units are thousandths)
- `n` = number of order items

For lines 0..n-2:
```
allocated[i] = floor(couponDiscount × line_total[i] / subtotal)
```

For the last line (the remainder, to guarantee exact reconciliation):
```
allocated[n-1] = couponDiscount − sum(allocated[0..n-2])
```

Then per line:
```
net_line[i] = line_total[i] − allocated[i]
commission[i] = round(net_line[i] × commission_percent / 100)
vendor_earning[i] = net_line[i] − commission[i]
```

### Invariants (asserted in tests + CI)

```
sum(allocated)            === couponDiscount
sum(line_total)           === subtotal
sum(net_line)             === subtotal − couponDiscount
sum(earning + commission) === subtotal − couponDiscount
```

The last invariant is the financial reconciliation: platform commission + vendor earnings together equal what the customer actually paid for the items (gross minus coupon, before shipping/tax). Without this, marketplace pays vendors more (or less) than the customer paid → over-payout or vendor cheated.

### Worked example (multi-vendor)

Cart: vendor A has a 70 KWD item, vendor B has a 30 KWD item. Subtotal 100 KWD. Coupon `SAVE10`: 10 KWD fixed.

```
allocated[A] = floor(10000 × 70000 / 100000) = 7000   (7 KWD)
allocated[B] = 10000 − 7000                  = 3000   (3 KWD)

net[A] = 70000 − 7000  = 63000   (63 KWD)
net[B] = 30000 − 3000  = 27000   (27 KWD)
```

If both vendors are on Std package (20% commission):

```
commission[A] = round(63000 × 0.20) = 12600
earning[A]    = 63000 − 12600       = 50400

commission[B] = round(27000 × 0.20) = 5400
earning[B]    = 27000 − 5400        = 21600
```

Customer paid: 100000 − 10000 = 90000.
Sum of platform commission: 12600 + 5400 = 18000.
Sum of vendor earnings: 50400 + 21600 = 72000.
Reconciliation: 18000 + 72000 = 90000 ✓.

### Multi-vendor visibility

Vendor A on `/vendor/orders/{id}` sees:
- Gross subtotal: 70.00 KWD
- Coupon (SAVE10) — your share: −7.00 KWD
- Net subtotal (what customer paid): 63.00 KWD
- Platform commission: −12.60 KWD
- Your earnings: 50.40 KWD

Vendor A does NOT see vendor B's lines (the items relation is scoped via `Order::forVendor`).

---

## Lazy-load fix (the real one this time)

The mistake in v9.1: I assumed Filament's default resolver would respect any eager-loading I configured elsewhere. It doesn't — Filament's `ViewRecord::resolveRecord()` does a plain `findOrFail($key)` and Filament's `Infolist` then accesses relations directly when rendering.

The fix: override `resolveRecord()` on the View page itself:

```php
public function resolveRecord(int | string $key): Model
{
    return SupportTicket::query()
        ->with([
            'messages' => fn ($q) => $q->orderBy('created_at'),
            'messages.user:id,name,email',
            'user:id,name,email',
            'vendor:id,business_name',
            'order:id,number',
            'booking:id',
            'product:id,name',
            'assignee:id,name,email',
        ])
        ->findOrFail($key);
}
```

The select clauses keep the payload small but MUST include `id` because Eloquent's relation hydration uses it as the foreign-key reference.

For the LIST page (table columns access `user.email`, `vendor.business_name`, `order.number`), the resource overrides `getEloquentQuery`:

```php
public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()->with([
        'user:id,name,email',
        'vendor:id,business_name',
        'order:id,number',
    ]);
}
```

The v9.3 Pest scenarios for this bug explicitly enable `Model::preventLazyLoading(true)` before traversing the loaded record — so if a future change re-introduces a lazy-load, the test crashes with the exact same exception the developer saw.

---

## Files touched in v9.3

```
database/migrations/2026_01_20_000001_add_coupon_allocation_to_order_items.php       NEW
app/Models/OrderItem.php                                                              fillable + casts
app/Domain/Order/CheckoutService.php                                                  allocation algorithm + net-base commission
app/Http/Controllers/CheckoutController.php                                           coupon block + server-side revalidation
app/Http/Controllers/OrderController.php                                              coupon block + items.product eager-load + per-item allocation
app/Http/Controllers/Vendor/VendorOrderController.php                                 coupon block + vendor_summary breakdown + per-item allocation
app/Filament/Resources/SupportTicketResource.php                                      getEloquentQuery for list-page eager-load
app/Filament/Resources/SupportTicketResource/Pages/ViewSupportTicket.php              resolveRecord override eager-loads messages.user
resources/js/Pages/Cart/Show.tsx                                                      (unchanged — v9.1's CartCouponForm preserved)
resources/js/Pages/Checkout/Show.tsx                                                  coupon line + payable
resources/js/Pages/Orders/Show.tsx                                                    coupon line + per-item allocation
resources/js/Pages/Vendor/Orders/Show.tsx                                             vendor coupon allocation + financial summary
tests/Feature/Phase9V93RegressionTest.php                                             NEW (10 scenarios)
.github/workflows/ci.yml                                                              5 new sub-checks
VERSION                                                                                Phase 9 v9.1 → Phase 9 v9.3
```

---

## v8.x defenses still pass on v9.3

| Defense | Result |
|---|---|
| v8.2 identifier length | 98 names checked, all ≤ 60 chars |
| v8.5 duplicate global helpers | 36 unique helpers, 0 duplicates (3 new `p93_` helpers) |
| v8.7 controller return type | 58 `Inertia::render`-returning methods, all compatible |
| v9.1 Filament closure injection | 0 untyped non-injectable params |

### 5 new Phase 9 v9.3 CI sub-checks

1. **`order_items.coupon_allocation_minor` column exists** — fails CI if the v9.3 migration didn't run.
2. **Customer + vendor + checkout all expose coupon block** — static grep that `OrderController::present` includes `'coupon'`, `VendorOrderController` includes `vendor_summary`, `CheckoutController` includes `'coupon'`.
3. **Filament SupportTicket pages eager-load every relation the Infolist + list table touch** — static check that `ViewSupportTicket` overrides `resolveRecord` with `messages.user` in the with-list AND that `SupportTicketResource` overrides `getEloquentQuery` with `user/vendor/order` eager-loaded.
4. **Phase 9 v9.3 Pest scenarios pass** — `php artisan test --filter='Phase9V93'`.
5. **Coupon allocation reconciliation invariant** — runs after seed, queries every order with a coupon, asserts both `sum(allocated) === coupon_discount` AND `sum(earning + commission) === subtotal − coupon_discount`. **This is the financial reconciliation check.** If any future change breaks the allocation math, CI fails with the offending order number.

### 10 new Pest scenarios (`Phase9V93RegressionTest.php`)

- Checkout page shows the cart's applied coupon
- Order snapshot stores `coupon_id`/`coupon_code`/`coupon_discount_minor` after checkout
- **Single-vendor allocation: sums to coupon discount and reconciles**
- **Multi-vendor allocation: splits proportionally and reconciles**
- Cart without coupon: every line has `coupon_allocation_minor = 0`
- Customer order detail exposes coupon block + per-item allocation
- Delivered order item exposes `can_review=true` via OrderController::present
- Customer can submit a review through the existing endpoint, linked to the order item
- **`ViewSupportTicket::resolveRecord` eager-loads messages.user (with `preventLazyLoading(true)` to mirror runtime strict mode)**
- Admin reply via SupportTicketService creates message + flips status, traversed without lazy-load

The two bold scenarios are the most important — the allocation reconciliation is a financial invariant, and the lazy-load test runs with strict mode enabled (matching the production runtime).

### Counts

| | v9.1 → v9.3 |
|---|---|
| Phase 9 CI sub-checks | 9 → **14** (3 v9.0 + 6 v9.1 + 5 v9.3) |
| Phase 9 Pest scenarios | 35 → **45** (24 v9.0 + 11 v9.1 + 10 v9.3) |
| Grand total phase-specific CI sub-checks | 43 → **48** |
| Unique global test helpers | 33 → **36** (3 new `p93_`-prefixed) |

---

## Honest accountability

v9.1's lazy-load "fix" was wrong. I overrode the `Infolist::infolist()` schema definition and added eager-loading hints in places that don't affect resolution, but I never overrode `resolveRecord` — which is the actual choke point Filament runs before rendering. The Pest test for v9.1 didn't enable strict mode, so it passed even though the runtime would crash. v9.3's test fixes both: it enables `Model::preventLazyLoading(true)` BEFORE traversing the record, and it traverses the exact path the Infolist's RepeatableEntry walks (`$message->user->name`). This is the test I should have written in v9.1.

v9.1's coupon "fix" was incomplete. I added the snapshot columns to the order at checkout, but I didn't surface them anywhere (`OrderController::present` returned no coupon block, `VendorOrderController` returned no coupon block, `CheckoutController` returned no coupon block). The cart had the UI, the order had the data, but everything in between was opaque. I also computed commission on the gross line total, which is correct only when there's no coupon — with a coupon, it over-pays vendors. v9.3 fixes both: every view that touches an order now exposes the coupon snapshot, AND commission is correctly computed on the net (post-allocation) line total.

The pattern of mistakes: I built features and tests but didn't walk the end-to-end flow with strict mode + a real coupon + multiple vendors. The v9.3 reconciliation invariant check (CI sub-check #5) is the lesson codified — every future change touching the allocation math will be caught by the invariant, even if the human writing the change forgets the test.

---

## Sandbox verification (the v8.6 lesson)

- ✅ `VERSION` = `Phase 9 v9.3`
- ✅ All v8.x defenses still pass
- ✅ ViewSupportTicket overrides `resolveRecord` with `messages.user` in the eager-load
- ✅ Resource overrides `getEloquentQuery` with `user/vendor/order` eager-loaded
- ✅ Migration adds `coupon_allocation_minor` to order_items
- ✅ CheckoutService allocates per-line + uses net for commission
- ✅ Customer + vendor + checkout all expose coupon
- ✅ 3 React pages render coupon lines
- ✅ 10 new Pest scenarios; 36 unique global helpers (0 duplicates)
- ✅ 5 new CI sub-checks
- ✅ Real tsc: TS6133=0, TS6196=0 on v9.3-touched files. **3 pre-existing tsc errors surfaced** (`Checkout/Show.tsx(102, 126)` SharedProps variance + useForm transform; `Vendor/Supplier/Orders/Show.tsx(31)` SharedProps variance — all in v9.1's archive too; not v9.3 regressions; documented for a future patch.)

---

## v9.3 STOPS HERE — do not start Phase 10

Approval requires:
1. `cat VERSION` prints `Phase 9 v9.3` on the deployed system
2. `php artisan marketplace:version` prints all 4 ✓
3. `php artisan migrate:fresh --seed` succeeds (the new migration runs)
4. `php artisan test --filter='Phase9V93'` runs the 10 scenarios green
5. The 13-step manual flow in `PHASE_9_v9.3_DEVELOPER_CHECKLIST.md` passes end-to-end
6. CI shows `✅ Phase 9 v9.3 PASSES — ready to approve Phase 10`
