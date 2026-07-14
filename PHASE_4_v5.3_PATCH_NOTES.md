# Phase 4 v5.3 — TypeError fix + demo data for `migrate:fresh --seed`

**Targeted fix only.** Same Phase 4 scope as v5.0–v5.2. Phase 5 is still not started.

---

## What broke in v5.2

Clicking **Proceed to Checkout** raised:

```
TypeError
App\Http\Controllers\CheckoutController::show(): Return value must be of type
Symfony\Component\HttpFoundation\Response, Inertia\Response returned
```

## Root cause

When I wrote `CheckoutController::show()` I typed it as `Symfony\Component\HttpFoundation\Response` (aliased as `HttpResponse`). The method has two return paths:

```php
if ($cart->isEmpty()) {
    return redirect('/cart')->with('error', '…');   // RedirectResponse — IS Symfony Response → OK
}
return Inertia::render('Checkout/Show', [...]);     // Inertia\Response  — NOT Symfony Response → 💥
```

`Inertia\Response` implements `Illuminate\Contracts\Support\Responsable` — it's NOT a Symfony Response subclass. The redirect path silently worked, but the moment the cart had items and the Inertia path was hit, PHP refused to return it as Symfony Response.

The fix: use a proper PHP 8 union type — `\Inertia\Response | \Illuminate\Http\RedirectResponse`. Both branches are now individually correct.

The v5.2 audit didn't catch this because the new `CheckoutAddressSchemaTest` tested **POST /checkout** (the `place()` method, which only returns `RedirectResponse` and is correctly typed). I never asserted GET /checkout returns 200 — the Inertia branch was untested at the HTTP layer. That's been fixed in v5.3 (see new regression test below).

---

## Files changed

### Production code

| File | Change |
|---|---|
| `app/Http/Controllers/CheckoutController.php` | `show()` return type fixed to `InertiaResponse \| RedirectResponse`. Removed unused `Symfony\Component\HttpFoundation\Response as HttpResponse` import. |
| `app/Http/Controllers/Auth/LoginController.php` | `store()` + `redirectAfterLogin()` retyped from `HttpResponse` to `RedirectResponse` (latent over-broad type — they only redirect). Removed `HttpResponse` import. |
| `app/Http/Controllers/Auth/RegisterController.php` | `store()` retyped from `HttpResponse` to `RedirectResponse`. Removed `HttpResponse` import. |
| `database/factories/VendorFactory.php` | Fixed `started_at` → `starts_at` typo in `withActivePackage()`. The column is `starts_at` per the v3.0 vendor_subscriptions migration; the typo was a latent v5.2 bug that would have surfaced as a DB error on any test path that exercised the factory against a real database. |

### New files

| File | Purpose |
|---|---|
| `database/seeders/DemoSeeder.php` | Fleshes out the four demo accounts after foundation seeding: approved vendor profile + active Basic subscription + 3 cart-ready published products + 1 draft + 1 pending-review; pending vendor + rejected vendor accounts; default Phase 1 address for the customer. Self-guards against `testing` env. |
| `tests/Feature/ControllerReturnTypeRegressionTest.php` | 9 scenarios — the regression for the v5.3 TypeError. |
| `tests/Feature/DemoSeederTest.php` | 11 scenarios — verifies the demo state after `db:seed`. |

### CI

- `.github/workflows/ci.yml` — verdict bumped to **`✅ Phase 4 v5.3 PASSES — ready to approve Phase 5`**.
- New CI step `v5.3 — migrate:fresh --seed produces a complete demo environment` that runs the actual command under `APP_ENV=local` and verifies via tinker: vendor approved + active subscription, 3+ cart-ready products, customer has default Gulf-style address.
- New rows in the audit-coverage table for v5.3.
- `ControllerReturnTypeRegressionTest` and `DemoSeederTest` added to the per-file pass/fail summary.

---

## The new regression test — `ControllerReturnTypeRegressionTest`

Three layers of defence:

1. **Static reflection check** (no DB needed): asserts `CheckoutController::show` declares `Inertia\Response` in its return type. Hard-fails if the union is missing.
2. **Whole-codebase scan**: walks every controller method that contains `Inertia::render`, parses its declared return type via reflection, and asserts the type union includes `Inertia\Response`. **Any future PR that re-introduces this bug — anywhere in the codebase — fails this test.**
3. **Real HTTP round-trip**: hits `GET /checkout` and asserts the response status is `200`, not `500`. The v5.0–v5.2 TypeError would surface here.

Plus end-to-end checkout against each payment provider (COD, manual_transfer, online_mock) via real HTTP — same coverage the developer asked for.

---

## Demo data — what `php artisan migrate:fresh --seed` produces

| Account | Email / password | What's set up |
|---|---|---|
| Super admin | `admin@marketplace.test` / `password` | role: super_admin |
| Admin staff | `staff@marketplace.test` / `password` | role: admin_staff |
| Approved vendor | `vendor@marketplace.test` / `password` | role: vendor; Vendor profile **Demo Trading Co.** with status=approved, active Basic subscription (30% commission), **3 cart-ready published products** + 1 draft + 1 pending-review |
| Pending vendor | `pending-vendor@marketplace.test` / `password` | role: vendor; Vendor profile status=pending — for testing the application/approval flow |
| Rejected vendor | `rejected-vendor@marketplace.test` / `password` | role: vendor; Vendor profile status=rejected with reason — for testing the rejected state |
| Customer | `customer@marketplace.test` / `password` | role: customer; **Default Phase 1 address** (Kuwait City / Block 7 / Beach Road / Bldg 15 / Floor 3 / Apt 4) — ready to check out immediately |

Seeded products (attached to the approved vendor):
- Wireless Bluetooth Headphones — 12.500 KWD — 25 in stock — featured
- Cotton T-Shirt Classic Fit — 3.500 KWD — 80 in stock
- Stainless Steel Water Bottle — 4.750 KWD — 50 in stock — featured

DemoSeeder is `firstOrCreate`-keyed so running it twice produces the same row counts (verified by `DemoSeederTest`).

### Why no demo orders are seeded

The developer asked for a clean "place your first order" experience. Pre-populating orders would clutter the empty state. The flow is:
1. Sign in as `customer@marketplace.test`
2. Visit `/products`, add a headset to cart, click Proceed to Checkout
3. Address picker shows the default Phase 1 address pre-selected
4. Pick COD, place order
5. Order shows up in `/orders`, in the vendor's `/vendor/orders`, and in admin Filament under Operations → Orders

---

## How to apply v5.3

```bash
tar -xzf marketplace-phase-4-v5.3.tar.gz
docker compose down
docker compose build app
docker compose up -d
docker compose exec app php artisan migrate:fresh --seed
```

Then the seeder will print:

```
═══════════════════════════════════════════════════════════
Demo data ready. Test accounts:
  Admin    → admin@marketplace.test / password
  Staff    → staff@marketplace.test / password
  Vendor   → vendor@marketplace.test / password   (approved, has products)
  Customer → customer@marketplace.test / password (has default address)
  Pending vendor → pending-vendor@marketplace.test / password
  Rejected vendor → rejected-vendor@marketplace.test / password
═══════════════════════════════════════════════════════════
Try it: sign in as customer, visit /products, add to cart, checkout with COD.
```

---

## Manual checklist for the developer

| # | Step | Expected |
|---|---|---|
| 1 | `migrate:fresh --seed`, watch the output | The "Demo data ready" banner above prints. |
| 2 | Open the storefront, sign in as `customer@marketplace.test` / `password` | Header shows the cart icon (badge = 0). "My orders" link visible. |
| 3 | Click "Products" — pick the Bluetooth Headphones — quantity 1 — Add to cart | Cart badge increments to 1. |
| 4 | Click the cart icon → Proceed to Checkout | **/checkout opens with 200, no TypeError, no SQL error.** Default address (Block 7, Beach Road) is pre-selected. |
| 5 | Pick "Cash on Delivery" — Place order | Lands on `/orders/{id}/confirm` showing "Pay 12.500 KWD on delivery". |
| 6 | Repeat steps 3-5 picking "Bank Transfer" instead | Confirm page shows `BT-{number}-{hash}` reference for the customer to quote on the transfer. |
| 7 | Repeat with "Card / Online (Demo)" | Order payment_status = paid immediately, external_id starts with `MOCK-`. |
| 8 | Sign out, sign in as `vendor@marketplace.test` → Vendor → Orders | Shows the three orders you just placed with your earnings (70% of total after Basic 30% commission). |
| 9 | Sign out, sign in at `/admin/login` as `admin@marketplace.test` → Operations → Orders | Pending count badge shows the COD + manual_transfer orders. Click the COD order → "Mark COD paid". |
| 10 | Open GitHub Actions for the v5.3 branch | Verdict: **`✅ Phase 4 v5.3 PASSES — ready to approve Phase 5`**. The `v5.3 — migrate:fresh --seed produces a complete demo environment` step is green. `ControllerReturnTypeRegressionTest` + `DemoSeederTest` rows show ✅ in the per-file table. |

---

## Known limitations (unchanged from v5.0)

Same as `PHASE_4_REPORT.md § Known limitations` — Phase 5+ items (reviews, wishlist, real PSP, tax, shipping zones, payouts execution) are still deferred.

---

## Stop discipline

**Phase 5 is not started.** v5.3 is purely the TypeError fix + demo data. Reply **"approve Phase 5"** only after:
1. CI green with the v5.3 verdict visible
2. The 10-step v5.3 manual checklist above passes
3. The 8-step v5.2 checklist (in `PHASE_4_v5.2_PATCH_NOTES.md`) still passes
4. The 14-step original Phase 4 checklist (in `PHASE_4_REPORT.md`) still passes end-to-end after a fresh seed
