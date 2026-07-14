# Phase 4 v5.7 — Multi-product Checkout Lazy-Load Fix

**Targeted fix only.** Same Phase 4 scope. Phase 5 is still not started.

Single-product Place Order has worked since v5.5. v5.7 fixes the lazy-load crash that fires the moment a second item enters the cart.

---

## The exact error

```
Attempted to lazy load [vendor] on model [App\Models\Product] but lazy loading is disabled.
```

Only triggered with **two or more** cart items. Single-item checkout was fine.

## Why "only with multiple items"?

`Model::shouldBeStrict()` (which Phase 0 enables outside production) wires up Laravel's **N+1 lazy-load detector** — specifically `Model::preventLazyLoading()`. It only fires when a relation would be lazy-loaded for **multiple parents in a collection**, because that's the symptom of N+1. A single-parent lazy-load silently succeeds. So a one-item cart slipped past, but a two-item cart immediately hit the detector at the second iteration of the order-items loop.

## Root cause (line-precise)

`app/Domain/Order/CheckoutService.php` line 124 (pre-v5.7):

```php
$cart->loadMissing(['items.product', 'items.variant']);
```

…loaded `items.product` but NOT `items.product.vendor`. The loop right below it accessed:

```php
$product = $cartItem->product;
$vendor  = $product->vendor;                                     // ← lazy, line 129
…
$rule = $this->commissions->forProduct($product);                // ← forProduct() does:
//      └─ resolve($product->vendor, …)                          // ← lazy
//      └─ 'package_id' => $product->vendor?->currentPackage()?->id   // ← lazy x2
//             └─ vendor->activeSubscription                     // ← lazy
//             └─ activeSubscription->package                    // ← lazy
…
$commissionPercent = … ?? $vendor->currentPackage()->default_admin_commission_percent;
```

Five lazy-load sites per item. With 1 item, none fire. With 2+, all fire.

The same class of bug also lurked in:
- `CartController::index()` — `$i->product?->primaryImage?->url` + `$i->vendor?->business_name` per item
- `CheckoutController::show()` — same pattern when rendering the checkout summary
- `OrderLifecycleService::cancel()` — `$item->variant` + `$item->product` in the restock loop

---

## Fix

### Primary site — eager-load the full chain

```php
// app/Domain/Order/CheckoutService.php — place()
$cart->loadMissing([
    'items.product.vendor.activeSubscription.package',  // covers $product->vendor + ->currentPackage()
    'items.product.category',                            // covers commission resolver category_id path
    'items.variant',
]);
```

### Cart + checkout summary pages

```php
// CartController::index() and CheckoutController::show()
$cart->load([
    'items.product.primaryImage',  // for the thumb URL
    'items.vendor',                 // CartItem's vendor BelongsTo (business_name in summary)
    'items.variant',
]);
```

### Cancel restock

```php
// OrderLifecycleService::cancel()
$order->loadMissing(['items.variant', 'items.product']);
```

(`markShipped` and `markDelivered` already had `loadMissing(['items'])` from v5.6; they don't access `$item->product` so that's sufficient.)

---

## Files changed

| File | Change |
|---|---|
| `app/Domain/Order/CheckoutService.php` | Eager-load `items.product.vendor.activeSubscription.package` + `items.product.category` in `place()`. |
| `app/Http/Controllers/CartController.php` | `index()` now `$cart->load(['items.product.primaryImage', 'items.vendor', 'items.variant'])` before presenting. |
| `app/Http/Controllers/CheckoutController.php` | `show()` does the same eager-load before rendering the summary. |
| `app/Domain/Order/OrderLifecycleService.php` | `cancel()` `loadMissing` now includes `items.variant` + `items.product` for the restock loop. |
| `database/seeders/DemoSeeder.php` | New `seedSecondApprovedVendor()` creates `vendor2@marketplace.test` → **Coastal Goods** (approved, Basic subscription, 1 published product with image) so the developer can test multi-vendor checkout from real demo data. |
| `tests/Feature/MultiProductCheckoutTest.php` (**new, 9 scenarios**) | The regression guard. |

No models, migrations, React pages, or other controllers changed.

---

## What the brief listed vs what I changed

The brief listed several potential lazy-load risks. Honest mapping:

| Listed risk | Status |
|---|---|
| `$product->vendor` | **Fixed** — main bug. |
| `$product->images` | Not accessed in the checkout/order-creation loop. `primaryImage` (subset of `images`) is used in cart/checkout display — covered by the v5.7 CartController + CheckoutController eager-loads. |
| `$product->category` | Eager-loaded defensively in CheckoutService (commission resolver reads `$product->category_id` which is a column, so no lazy-load — but eager-loading is cheap insurance). |
| `$cartItem->product` | Already eager-loaded via `items.product`. |
| `$order->items` | Eager-loaded by `OrderResource::getEloquentQuery` + ViewOrder/EditOrder `resolveRecord` override (v5.6) + controllers' `with()` queries. |
| `$orderItem->product` | Not used in display — order_items snapshot `product_name`, `product_sku`, `variant_name`, `variant_attributes` onto the row (Phase 4 design). |
| `$vendorOrder->vendor` / `$vendorOrder->items` | **Doesn't apply.** Phase 4 uses a single `orders` table with `order_items.vendor_id` for vendor scoping — no separate `vendor_orders` table or model. |
| `$payment->method` | Not accessed in the checkout/order flow. |

---

## The new test — `MultiProductCheckoutTest` (9 scenarios)

Every test runs with `Model::shouldBeStrict(true)` enabled per-test (mirroring AppServiceProvider's non-production behaviour). Any lazy-load anywhere in cart/checkout/cancel would fail the test.

1. Single-product cart → COD checkout (regression check that v5.5 didn't break)
2. **Two products, same vendor** → COD checkout (the screenshot's case)
3. **Three products, same vendor** → COD checkout
4. **Products from two different vendors** → single order, items split with correct vendor_id per line
5. `/checkout` GET renders with 3-item cart (CheckoutController::show eager-loads)
6. `/cart` GET renders with 3-item cart (CartController::index eager-loads)
7. Cancelling a 2-item order restocks both lines (no lazy-load in restock loop)
8. Vendor sees only their own item on a multi-vendor order detail page
9. Customer order detail renders all items from a multi-vendor order

---

## Demo data — second approved vendor

After `php artisan migrate:fresh --seed`:

| Account | Email / password | Setup |
|---|---|---|
| Super admin | `admin@marketplace.test` / `password` | unchanged |
| Admin staff | `staff@marketplace.test` / `password` | unchanged |
| Approved vendor #1 | `vendor@marketplace.test` / `password` | **Demo Trading Co.** — 3 published products with images, vendor-level 20% commission rule |
| **Approved vendor #2 (new)** | **`vendor2@marketplace.test` / `password`** | **Coastal Goods** — 1 published product (Handwoven Beach Towel, KWD 6.500, stock 30, with image) |
| Pending vendor | `pending-vendor@marketplace.test` / `password` | unchanged |
| Rejected vendor | `rejected-vendor@marketplace.test` / `password` | unchanged |
| Customer | `customer@marketplace.test` / `password` | default Phase 1 Gulf address |

The developer can now test, in order:

1. **Single-product checkout** — add 1 demo product → place order → confirmation
2. **Multi-product, same vendor** — add 2 products from Demo Trading Co. → checkout
3. **Multi-vendor checkout** — add 1 product from Demo Trading Co. + the Handwoven Beach Towel → checkout → see 2 line items in the order, each with the correct `vendor_id`; each vendor sees only their own line in `/vendor/orders/{id}`

---

## CI

- Verdict: `✅ Phase 4 v5.7 PASSES — ready to approve Phase 5`
- **New CI step `v5.7 — multi-product checkout places an order without lazy-load crash`** sets `Model::shouldBeStrict(true)`, places a real 2-product COD order via `CheckoutService` as the demo customer, and asserts 2 order_items were created. Catches this exact bug class end-to-end during seed verification.
- The `migrate:fresh --seed` verification step now also asserts the second demo vendor exists with ≥1 published product.
- `MultiProductCheckoutTest` added to per-file audit map and verdict coverage table.

---

## How to apply v5.7

```bash
tar -xzf marketplace-phase-4-v5.7.tar.gz
docker compose down && docker compose build app && docker compose up -d
docker compose exec app bash -lc "
  composer install --no-interaction --no-progress &&
  npm install && npm run build &&
  php artisan optimize:clear &&
  php artisan storage:link --force &&
  php artisan migrate:fresh --seed
"
```

No DB migration required — pure code change + a new demo vendor (created by `migrate:fresh --seed`).

---

## Manual developer checklist

| # | Step | Expected |
|---|---|---|
| 1 | Sign in as `customer@marketplace.test` → add 1 product → checkout → Place Order (COD) | Confirmation page renders. |
| 2 | **Multi-same-vendor**: add 2 products from Demo Trading Co. → checkout → Place Order | **No `[vendor]` lazy-load crash.** Confirmation page renders. Order has 2 line items, same `vendor_id`. |
| 3 | **Multi-vendor**: add 1 product from Demo Trading Co. + the Handwoven Beach Towel from Coastal Goods → checkout → Place Order | Single order with 2 line items, different `vendor_id` per line. |
| 4 | Sign in as `vendor@marketplace.test` → `/vendor/orders/{id}` for the multi-vendor order | Sees only their own line item. |
| 5 | Sign in as `vendor2@marketplace.test` → `/vendor/orders/{id}` for the same order | Sees only the Handwoven Beach Towel line. |
| 6 | Sign in as admin → Admin → Orders → open the multi-vendor order | Shows all line items, both vendor_ids visible. |
| 7 | As customer, `/orders/{id}` for the multi-vendor order | Shows both line items. |
| 8 | Add 3 items → checkout page renders | `/checkout` opens without 500. |
| 9 | Place a 2-item COD order, then cancel from `/orders/{id}` | Both products' stock restored. |
| 10 | GitHub Actions | Verdict: `✅ Phase 4 v5.7 PASSES — ready to approve Phase 5`. `MultiProductCheckoutTest` ✅ in the per-file table. The `v5.7 — multi-product checkout` CI step is green. |

---

## Stop discipline

**Phase 5 is not started.** Reply **"approve Phase 5"** only after the 10-step checklist passes and CI is green with the v5.7 verdict.
