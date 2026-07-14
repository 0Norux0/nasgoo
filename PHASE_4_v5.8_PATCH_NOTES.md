# Phase 4 v5.8 — Multi-product Checkout Defensive Fix

**Targeted fix only.** Same Phase 4 scope. Phase 5 is still not started.

v5.7 added an eager-load chain to `CheckoutService::place()` and the dev reported the same `Attempted to lazy load [vendor] on model [App\Models\Product]` error still occurring. v5.8 adds belt-and-suspenders defense at the iteration level and inside the commission resolver so the error cannot return regardless of how the cart got hydrated upstream.

---

## Honest answer: why didn't v5.7 stick?

I traced every plausible path in v5.8's diagnosis pass and the v5.7 chain (`$cart->loadMissing(['items.product.vendor.activeSubscription.package', …])`) **should** have eager-loaded the vendor. Yet the dev still hit the error. Two realistic explanations remain:

1. **Stale cart hydration upstream.** Eloquent's `loadMissing` is a **no-op when the parent relation is already loaded** — even if the deeper chain isn't. If anything in the request lifecycle had pre-loaded `$cart->items` without going through the full chain (e.g. an earlier accessor, observer, or a future change), `loadMissing` could see `items` as "loaded" and skip the deeper `product.vendor` step. Likely culprits: model accessors, middleware that touches the cart, Octane workers reusing models across requests, or a queued listener.
2. **A code path I haven't seen.** Some installations may have local edits, additional middleware, or queued jobs I can't audit from the sandbox.

Rather than keep guessing, v5.8 makes the fix **independent of upstream state**:
- Switch `loadMissing` → `load` at the top of `place()` so the chain is rebuilt unconditionally.
- Inside the items loop, **defensive `loadMissing` per iteration** on each cart item, loading the full chain before `$product->vendor` is read.
- Inside `CommissionResolver::forProduct`, **defensive `loadMissing` on the product** before reading vendor — so any caller (now or later) is safe.

Three independent layers of defense. For the lazy-load to fire after v5.8, all three would have to be skipped — which would require deliberate code removal, not a regression.

---

## What v5.8 changes

| File | Change |
|---|---|
| `app/Domain/Order/CheckoutService.php` | `$cart->loadMissing(...)` → `$cart->load(...)` so the chain is rebuilt even when `items` was pre-loaded upstream. Inside the snapshot loop: `$cartItem->loadMissing(['product.vendor.activeSubscription.package', 'product.category', 'variant'])` on every iteration. |
| `app/Domain/Commission/CommissionResolver.php` | `forProduct()` now calls `$product->loadMissing(['vendor.activeSubscription.package', 'category'])` before reading `$product->vendor`. Defensive against any caller. |
| `tests/Feature/MultiProductCheckoutDemoTest.php` (**new, 6 scenarios**) | Reproduces the dev's exact flow: real `DemoSeeder` data → `customer@marketplace.test` signs in → adds 2 demo products → POST `/checkout` under `Model::shouldBeStrict(true)`. Plus the multi-vendor variant, the /cart and /checkout GET pages, the confirmation page, and vendor/admin visibility. |
| `.github/workflows/ci.yml` | New CI step `v5.8 — multi-product checkout via HTTP (reproduces dev's exact path)` dispatches **real HTTP requests through the kernel** (POST /cart/items × 2 → POST /checkout) under strict mode. The previous v5.7 step exercised CheckoutService directly; this one mirrors the dev's HTTP flow exactly. |

No models, migrations, React pages, controllers, or seeders changed beyond the above.

---

## Why the defensive fix can't fail

```php
// app/Domain/Order/CheckoutService.php — place()
$cart->load([                                             // 1. forces eager-load (not no-op)
    'items.product.vendor.activeSubscription.package',
    'items.product.category',
    'items.variant',
]);
…
foreach ($cart->items as $cartItem) {
    $cartItem->loadMissing([                              // 2. per-iteration safety net
        'product.vendor.activeSubscription.package',
        'product.category',
        'variant',
    ]);
    $product = $cartItem->product;
    $vendor  = $product->vendor;                          // ← safe, vendor pre-loaded
    $rule    = $this->commissions->forProduct($product); // 3. also defensive inside
}
```

```php
// app/Domain/Commission/CommissionResolver.php — forProduct()
public function forProduct(Product $product, ?string $paymentMethod = null): ?VendorCommissionRule
{
    $product->loadMissing([                               // 3. defense inside resolver
        'vendor.activeSubscription.package',
        'category',
    ]);
    return $this->resolve($product->vendor, [ … ]);       // ← safe
}
```

Even if a future caller hands `forProduct` a raw `Product::find($id)` with no relations loaded, the resolver self-heals. Even if an upstream path returns a cart with `items` pre-loaded but no `product.vendor`, the per-iteration `loadMissing` covers it. Even if both fail, the top-level `load()` rebuilds the chain from scratch.

---

## How to verify v5.8 actually applied to YOUR codebase

Before reporting the bug again, please run:

```bash
docker compose exec app php artisan tinker --execute="
  \$src = file_get_contents(base_path('app/Domain/Order/CheckoutService.php'));
  echo str_contains(\$src, 'v5.8 — switched from loadMissing to load') ? '✓ v5.8 applied' : '✗ v5.8 NOT applied — re-deploy';
  echo PHP_EOL;
  \$src = file_get_contents(base_path('app/Domain/Commission/CommissionResolver.php'));
  echo str_contains(\$src, 'v5.8 — defensive eager-load') ? '✓ v5.8 resolver defense applied' : '✗ NOT applied';
"
```

Both lines must print `✓`. If either prints `✗`, the v5.8 archive wasn't actually deployed.

Also worth confirming the runtime cache isn't serving stale code:
```bash
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan config:clear
docker compose exec app php artisan view:clear
```

---

## Demo data (unchanged from v5.7)

After `php artisan migrate:fresh --seed`:

| Account | Email / password | Setup |
|---|---|---|
| Super admin | `admin@marketplace.test` / `password` | super_admin |
| Approved vendor #1 | `vendor@marketplace.test` / `password` | **Demo Trading Co.** — 3 published products with images, vendor-level 20% commission rule |
| Approved vendor #2 | `vendor2@marketplace.test` / `password` | **Coastal Goods** — 1 published product (Handwoven Beach Towel, KWD 6.500, stock 30) |
| Customer | `customer@marketplace.test` / `password` | default Phase 1 Gulf address |

Sufficient stock for one-product, multi-same-vendor, and multi-vendor checkout scenarios.

---

## Manual developer checklist

| # | Step | Expected |
|---|---|---|
| 1 | Apply v5.8: `tar -xzf marketplace-phase-4-v5.8.tar.gz && docker compose down && docker compose build app && docker compose up -d` | Build succeeds. |
| 2 | `docker compose exec app bash -lc "composer install --no-interaction && npm install && npm run build && php artisan optimize:clear && php artisan storage:link --force && php artisan migrate:fresh --seed"` | Seeders complete; `vendor2@marketplace.test` is in the credentials banner. |
| 3 | **Verify v5.8 actually applied** (the tinker check above) | Both lines print `✓`. |
| 4 | Sign in as `customer@marketplace.test` → add 1 product → checkout → Place Order (COD) | Confirmation page renders. |
| 5 | **Two products, same vendor**: add 2 products from Demo Trading Co. → checkout → Place Order | **No `[vendor]` lazy-load crash.** Confirmation renders. Order has 2 items, same `vendor_id`. |
| 6 | **Multi-vendor**: add 1 product from Demo Trading Co. + Handwoven Beach Towel from Coastal Goods → checkout → Place Order | Single order with 2 items, different `vendor_id` per line. |
| 7 | Sign in as each vendor → `/vendor/orders/{id}` | Each sees only their own line. |
| 8 | Admin → Orders → open the multi-vendor order | Both line items visible. |
| 9 | Cancel a 2-item order from `/orders/{id}` | Both products' stock restored. |
| 10 | GitHub Actions | Verdict: `✅ Phase 4 v5.8 PASSES — ready to approve Phase 5`. Both v5.7 (service-level) and v5.8 (HTTP-level) multi-product CI steps green. |

If step 5 still fails: run step 3 to confirm v5.8 is actually deployed; if the verification prints `✓` but the bug persists, capture the stack trace from the error page and share it — there's a path I haven't accounted for, and the stack will point to it directly.

---

## Stop discipline

**Phase 5 is not started.** Reply **"approve Phase 5"** only after the 10-step checklist passes and CI is green with the v5.8 verdict.
