# Phase 7 v7.6 — Checkout-time lazy-load defense (`OrderItem->customizations`)

**Status:** Targeted fix + defense-in-depth on top of Phase 7 v7.5. Pending CI verification.
**Scope:** `OrderController::confirm` (the direct bug) + 3 defense-in-depth sites + 7 new Pest scenarios + 2 new CI sub-checks.

---

## Symptom (the developer's report)

After successful checkout payment, the redirect to `/orders/{id}/confirm` crashed with:

```
Attempted to lazy load [customizations] on model [App\Models\OrderItem]
but lazy loading is disabled.
```

This is the strict-mode lazy-load detector (`Model::shouldBeStrict()` enabled in non-production via `AppServiceProvider`) firing on `$orderItem->customizations` access.

---

## Root cause (the actual bug)

`OrderController::confirm()` and `OrderController::show()` both render via the same private `present()` helper. `present()` iterates `$order->items` and for each item reads:

- `$i->customizations->map(...)`  ← Phase 7 addition
- `$i->latestProof` (and its fields) ← Phase 7 addition

Phase 7 v7.0 added the `customizations` + `latestProof` access in `present()` AND added `'items.customizations'` + `'items.latestProof'` to `OrderController::show()`'s eager-load — but **forgot** to add them to `OrderController::confirm()`. Since `confirm()` is only reached after a successful payment redirect (`/orders/{id}/confirm`), and demo flows often skip the live-payment path, this gap stayed hidden through v7.5.

```php
// v7.5 (buggy) — OrderController::confirm
$o = Order::with([
    'items', 'addresses', 'shippingAddress',
    'events.actor:id,name', 'payments',
])->findOrFail($order);
```

`present()` then loops `$o->items->map(fn ($i) => [..., 'customizations' => $i->customizations->map(...), 'latest_proof' => $i->latestProof ? [...] : null])` and the first item triggers strict-mode lazy-load.

---

## Audit results (full sweep before fixing)

| Site | Eager-loads `items.customizations`? | Status |
|---|---|---|
| `OrderController::index` | n/a — uses narrow `items:id,order_id,product_name,quantity,vendor_id`, never accesses customizations in the presenter | ✓ fine |
| `OrderController::show` | yes (v7.0 covered it) | ✓ fine |
| `OrderController::confirm` | **NO** — uses same `present()` helper as `show()` | **✗ BUG** |
| `OrderController::cancel` | n/a — `RedirectResponse`, no presenter | ✓ fine |
| `VendorOrderController::show` | yes — `items.customizations` + `items.proofs` (v7.0 covered it) | ✓ fine |
| `CheckoutController::show` | n/a — checkout page maps `$cart->items` but doesn't access `$i->customizations` in the map (cart-side rows are `CartItemCustomization`, separate model) | ✓ fine (but see defense-in-depth #2 below) |
| `CustomerProofResponseController` | n/a — uses relation methods `$item->proofs()->findOrFail($proofId)`, not the loaded collection | ✓ fine |
| Filament Admin `OrderResource::getEloquentQuery` | NO — currently no admin view accesses customizations, but a future addition would lazy-load | ✓ fine today but fragile |
| `CheckoutService::place` (returns `$order->fresh(['items', 'addresses', 'events'])`) | NO — returned to `CheckoutController::place` which then calls `PaymentService::initiateFor($order)` | ✓ fine today but fragile |
| `DropshipOrderCreator::createFromOrder` (`$order->loadMissing(['items.product.supplierPlatform'])`) | NO | ✓ fine today but fragile |

**Only one concrete bug**, but **three additional sites** that don't crash today but would crash on any future code change that touches `$item->customizations` after they hit. v7.6 fixes all four defensively.

---

## What v7.6 changes

### 1. The actual fix — `OrderController::confirm`

```diff
 public function confirm(Request $request, int $order): Response
 {
     $o = Order::with([
-        'items', 'addresses', 'shippingAddress',
+        'items', 'items.customizations', 'items.latestProof',
+        'addresses', 'shippingAddress',
         'events.actor:id,name', 'payments',
     ])->findOrFail($order);
     ...
 }
```

This alone resolves the developer-reported crash. The other three changes below are defense-in-depth.

### 2. Defense-in-depth — `CheckoutService::place` return

```diff
-return $order->fresh(['items', 'addresses', 'events']);
+return $order->fresh(['items', 'items.customizations', 'items.latestProof', 'addresses', 'events']);
```

The order returned to `CheckoutController::place` is now pre-loaded with customizations and latestProof. Any downstream code (`PaymentService::initiateFor`, future listeners, observer logic) that iterates `$order->items[*]->customizations` won't lazy-load.

### 3. Defense-in-depth — `DropshipOrderCreator::createFromOrder`

```diff
-$order->loadMissing(['items.product.supplierPlatform']);
+$order->loadMissing(['items.product.supplierPlatform', 'items.customizations']);
```

`DropshipOrderCreator` iterates `$order->items` to group by supplier. If it ever needs to inspect customizations (for dropship vendors to forward instructions to suppliers, for example), the relation is already loaded.

### 4. Defense-in-depth — Filament Admin `OrderResource::getEloquentQuery`

```diff
 return parent::getEloquentQuery()->with([
     'user:id,name,email',
     'items',
+    'items.customizations',
+    'items.latestProof',
     'shippingAddress',
     ...
 ]);
```

The admin panel doesn't currently render customization data, but the moment anyone adds a column or infolist that touches `$record->items->first()->customizations`, the same lazy-load class fires. Eager-loading here forecloses it.

---

## What v7.6 adds (tests + CI)

### 7 new Pest scenarios — `tests/Feature/Phase7LazyLoadRegressionTest.php`

1. `/orders/{id}/confirm` renders without lazy-load error for a customized order (the direct v7.5 bug-repro)
2. `/orders/{id}` (show) still renders without lazy-load error (no regression)
3. `OrderController::confirm` eager-loads `items.customizations` + `items.latestProof` (static source check)
4. `CheckoutService::place` returned order has `items.customizations` + `items.latestProof` (static)
5. `DropshipOrderCreator::createFromOrder` includes `items.customizations` (static)
6. Filament `OrderResource::getEloquentQuery` includes both (static)
7. **Negative test**: simulating `present()` WITHOUT the v7.6 eager-loads under strict mode DOES throw `LazyLoadingViolationException` — proves the test suite would have caught the original v7.5 bug

Total Phase 7 Pest scenarios now: **41** (was 34 in v7.5, +7 for v7.6).

### 2 new CI sub-checks

1. **`Phase 7 v7.6 — checkout-time lazy-load defense (static check)`** — Python validator greps each of the 4 fix sites and fails CI loud if any eager-load is removed.
2. **`Phase 7 v7.6 — Pest scenarios for lazy-load + checkout confirm page`** — runs `php artisan test --filter "Phase 7 v7.6"` against the live test DB.

### Verdict bumped

`✅ Phase 7 v7.6 PASSES — ready to approve Phase 8`

---

## Files touched in v7.6

| File | Change |
|---|---|
| `app/Http/Controllers/OrderController.php` | +3 lines in `confirm()` — added `items.customizations` + `items.latestProof` to the eager-load array + 8-line comment block documenting the bug |
| `app/Domain/Order/CheckoutService.php` | +1 line in `place()` return + 5-line comment |
| `app/Domain/Supplier/DropshipOrderCreator.php` | +1 token to `loadMissing` array |
| `app/Filament/Resources/OrderResource.php` | +2 lines in `getEloquentQuery()` + 5-line comment |
| `tests/Feature/Phase7LazyLoadRegressionTest.php` | New file (7 scenarios, ~200 lines) |
| `.github/workflows/ci.yml` | +130 lines: 2 new sub-checks + verdict bumped |
| `PHASE_7_v7.6_PATCH_NOTES.md` | This file (new) |
| `PHASE_7_REPORT.md` | v7.6 update appended |
| `README.md` | v7.6 changelog prepended; status header bumped |

**Files NOT touched in v7.6:** all Phase 7 migrations, all 4 customization models, all 4 services (other than CheckoutService + DropshipOrderCreator above), both Filament customization resources, all 5 customization controllers, all 11 customization routes, both Phase 7 React files, the demo seeder, User model, RegisterController. No business logic, schema, permission, or environment changes.

---

## Verification I ran in the sandbox

I cannot run `php artisan test` or `php artisan serve` in the sandbox (network 403, no PHP runtime). What I verified:

- **Sandbox-state diagnostic again**: detected the working tree had partial state — restored fresh from the shipped v7.5 archive (`/mnt/user-data/outputs/marketplace-phase-7-v7.5.tar.gz`) before applying any v7.6 changes. This is now the standard workflow.
- **Raw brace counts** before/after my edits (working tree vs unmodified v7.5 archive): unchanged in all 4 edited PHP files (33/33 for CheckoutService, 16/16 for DropshipOrderCreator, balanced for OrderController + OrderResource).
- **All 4 fix sites have the expected eager-load strings** (the exact code shipping as the static CI sub-check): grep-confirmed.
- **CI YAML parses**: valid (no quoting issues this time)
- **Final Phase 7 CI step count**: 13 — v7.1 (×2) → v7.2 (×2) → v7.3 (×2) → v7.4 (×2) → v7.5 (×2) → v7.6 (×2) → main
- **Final Phase 7 Pest scenario count**: 41
- **All previous static pre-flights still green**: v7.1 schema-vs-code, v7.2 unique-index lookup, v7.3 null-vs-NOT-NULL, v7.4 model safeguard, v7.5 mail resilience

---

## Developer testing checklist after pulling v7.6

```bash
git pull
composer install
cp .env.example .env       # or keep your existing .env
php artisan key:generate
php artisan optimize:clear
php artisan migrate:fresh --seed
npm ci && npm run typecheck && npm run build
php artisan test --filter "Phase7"   # 41 Phase 7 scenarios across 3 suites
```

**The direct bug-repro** — add a customizable product to your cart, check out (any payment method works; use Cash on Delivery for the fastest local repro):

1. Log in as `customer@marketplace.test / password`
2. Visit `/products/demo-custom-mug`
3. Fill the customization form, click "Add to cart"
4. Go to `/cart` → "Proceed to checkout"
5. Pick a shipping address + COD as payment method
6. Click "Place order" — should redirect to `/orders/{id}/confirm`
7. **v7.5: HTTP 500 with `Attempted to lazy load [customizations]` error**
8. **v7.6: HTTP 200 — confirmation page renders with customizations and "Pending proof" status**

If you still see the lazy-load error after pulling v7.6, your `composer dump-autoload` may be stale: run `composer dump-autoload && php artisan optimize:clear` and retry.

---

## Accountability — seventh Phase 7 release

v7.6 closes the seventh distinct Phase 7 bug class. Cumulative defense stack:

| Version | Bug class | Permanent CI guard |
|---|---|---|
| v7.0 | Wrong column name | (added in v7.1) |
| v7.1 | Duplicate SKU | schema-vs-code pre-flight |
| v7.2 | `file_path = null` for NOT NULL col | unique-index lookup pre-flight + migrate × 2 |
| v7.3 | `Storage::put` returns false silently | null-vs-NOT-NULL pre-flight + runtime file check |
| v7.4 | Code paths bypassing seeder | model-level `LogicException` safeguard + Pest tests |
| v7.5 | Mail transport unreachable | mail resilience defenses (env + User override + RegisterController wrap) |
| **v7.6** | **Confirm page missing eager-load** | **lazy-load defense at 4 sites (static + runtime)** |

Pattern reflection: bugs v7.0–v7.4 were seeder issues (caused by my code), v7.5 was an environment-config issue (caused by my default), and **v7.6 is a true regression I missed** — Phase 7's `present()` helper was expanded to access customizations + latestProof, the `show()` page got the corresponding eager-load, but `confirm()` (which shares the same helper) was overlooked. The post-checkout redirect goes there, and most demo flows don't reach it without a real payment cycle, so it stayed hidden.

The v7.6 negative Pest test (`scenario #7`) explicitly mirrors the `confirm()` eager-load that v7.5 had and asserts that it DOES throw `LazyLoadingViolationException`. This is the test that would have caught the bug pre-shipping — and it's now permanent. Future PRs that touch `present()`, `OrderController`, or any of the 4 eager-load sites must keep this test green.

**Phase 7 v7.6 STOPS HERE. Do not start Phase 8 until CI shows `✅ Phase 7 v7.6 PASSES` AND your developer confirms `/orders/{id}/confirm` renders cleanly for a customized order in their local env.**
