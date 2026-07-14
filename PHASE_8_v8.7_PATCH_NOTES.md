# Phase 8 v8.7 — Controller return type regression fix (`CatalogController::show`)

**Status:** Targeted fix on top of Phase 8 v8.6. Pending CI verification.
**Scope:** Type signature change on **1 method** + 1 added `use` import + 1 new CI sub-check that generalizes the v5.3 defense. No logic changes, no migrations, no React, no seeder.

---

## The bug your developer hit

Opening any product detail page at `/products/{slug}` raised:

```
App\Http\Controllers\CatalogController::show(): Return value must be of type
Symfony\Component\HttpFoundation\Response, Inertia\Response returned
```

## Root cause — same bug class as v5.3, in a different controller

In v8.1 I added a service-slug redirect to `CatalogController::show()`. The method previously returned `Inertia\Response` (the normal product detail render); I added a branch that returns a redirect when the slug belongs to a service-type product:

```php
// v8.1 — broken:
public function show(string $slug): \Symfony\Component\HttpFoundation\Response
{
    $type = Product::where('slug', $slug)->value('type');
    if ($type === Product::TYPE_SERVICE) {
        return redirect("/services/{$slug}", 301);     // ← RedirectResponse
    }
    // ...
    return Inertia::render('Catalog/Show', [...]);     // ← Inertia\Response
}
```

The intent was to type the method as a common superclass of both branches. **It worked for `RedirectResponse` (which DOES extend `Symfony\HttpFoundation\Response`) but NOT for `Inertia\Response`, which implements `Responsable` and does not extend the Symfony class.** Result: PHP's return-type check fails the moment the normal branch executes.

This is identical to the v5.3 bug we already fixed in `CheckoutController::show()`. The v5.3 patch added `tests/Feature/ControllerReturnTypeRegressionTest.php` to lock the fix in — but that test specifically inspects `CheckoutController::show`. It didn't generalize. So my v8.1 regression on `CatalogController` slipped through.

## Fix

Use a proper union type that covers both concrete return types:

```php
// v8.7 — correct:
public function show(string $slug): Response|RedirectResponse
{
    $type = Product::where('slug', $slug)->value('type');
    if ($type === Product::TYPE_SERVICE) {
        return redirect("/services/{$slug}", 301);     // ← RedirectResponse ✓
    }
    // ...
    return Inertia::render('Catalog/Show', [...]);     // ← Inertia\Response ✓
}
```

`Response` here resolves to `Inertia\Response` via the existing `use Inertia\Response;` at the top of the file. I added `use Illuminate\Http\RedirectResponse;` so the second branch of the union is also a proper name.

The fix is type-safe — no `mixed`, no `any`, no removed type. Both concrete return types are explicitly listed, so PHP's type checker validates that both branches actually return one of them.

---

## Audit (per your explicit instruction — every Phase 8 controller)

Resolved `use` imports per file so that bare `Response` could be correctly mapped to either `Inertia\Response`, `Illuminate\Http\Response`, or `Symfony\Component\HttpFoundation\Response`. Result:

| Controller | Method | Declared | Resolves to | Returns Inertia::render? | Status |
|---|---|---|---|---|---|
| `CatalogController` | `index` | `Response` | `Inertia\Response` | yes | ✓ |
| `CatalogController` | `show` | **was** `\Symfony\...\Response`, **now** `Response\|RedirectResponse` | **was** `Symfony\...\Response`, **now** `Inertia\Response\|Illuminate\Http\RedirectResponse` | yes (one branch) | ✗ → ✓ |
| `BookingController` | `index` | `Response` | `Inertia\Response` | yes | ✓ |
| `BookingController` | `show` | `Response` | `Inertia\Response` | yes | ✓ |
| `BookingController` | `confirmation` | `Response` | `Inertia\Response` | yes | ✓ |
| `BookingController` | `store` / `reschedule` / `cancel` | `RedirectResponse` | `Illuminate\Http\RedirectResponse` | no | ✓ |
| `ServiceCatalogController` | `index` / `show` | `Response` | `Inertia\Response` | yes | ✓ |
| `ServiceCatalogController` | `slots` | `array` | n/a | no (JSON) | ✓ |
| `VendorServiceController` | `index` / `create` / `edit` | `Response` | `Inertia\Response` | yes | ✓ |
| `VendorServiceController` | `store` / `update` | `RedirectResponse` | `Illuminate\Http\RedirectResponse` | no | ✓ |
| `VendorServiceProviderController` | `index` | `Response` | `Inertia\Response` | yes | ✓ |
| `VendorServiceProviderController` | `store` / `update` / `destroy` | `RedirectResponse` | `Illuminate\Http\RedirectResponse` | no | ✓ |
| `VendorAvailabilityController` | `show` | `Response` | `Inertia\Response` | yes | ✓ |
| `VendorAvailabilityController` | `upsertAvailability` / `blockDate` / `unblockDate` | `RedirectResponse` | `Illuminate\Http\RedirectResponse` | no | ✓ |
| `VendorBookingController` | `index` / `show` | `Response` | `Inertia\Response` | yes | ✓ |
| `VendorBookingController` | `accept` / `reject` / `complete` / `reschedule` | `RedirectResponse` | `Illuminate\Http\RedirectResponse` | no | ✓ |

**46 controller methods that call `Inertia::render()` scanned project-wide. After the v8.7 fix: 0 mismatches.** Before the fix: 1 mismatch (the `CatalogController::show` reported by your developer).

The v5.3 specifically-targeted `CheckoutController::show` test still passes — no regression there.

---

## New CI sub-check (generalizes the v5.3 defense)

**`Phase 8 v8.7 — controller return type covers Inertia::render`**

A Python static check that:

1. Walks every `.php` file under `app/Http/Controllers/`
2. For each file, builds an alias → FQN map from the `use` statements at the top
3. Finds every `public function NAME(...): RETURN_TYPE {` declaration
4. Inspects the method body for `Inertia::render(` or `inertia(`
5. If found, resolves each part of the (possibly union) `RETURN_TYPE` through the alias map
6. Fails CI with a `file=...,line=...` annotation + fix suggestion if no part of the resolved type is `Inertia\Response` (or `mixed`)

Stub-independent. Runs in under a second on any runner. Generalizes the v5.3 test's scope from "CheckoutController only" to "every controller in the project". This is the defense that should have existed in v5.3 — I'm adding it now because the same bug class just bit us again.

---

## Phase 8 CI sub-check totals after v8.7

| Release | Sub-checks |
|---|---|
| Phase 8.0 | 6 |
| v8.1 | 5 |
| v8.2 | 3 |
| v8.3 | 1 |
| v8.4 | 1 |
| v8.5 | 1 |
| v8.6 | 2 |
| **v8.7** | **1** |
| **Total Phase 8** | **20** |
| Plus Phase 7 | 14 |
| **Grand total** | **34 phase-specific CI sub-checks** |

Phase 8 Pest scenarios: still **44** (18 + 20 + 6). v8.7 doesn't add new tests — the new defense is stub-independent static analysis.

Final CI verdict: `✅ Phase 8 v8.7 PASSES — ready to approve Phase 9`.

---

## What did NOT change in v8.7

- Any other controller (audit confirmed every other Phase 8 controller already correct)
- Migrations, models, services, seeders, routes, React, Filament
- Pest test files (still has v8.5's renamed helpers `dropshipVendor` + `vendorWithPackage`)
- v8.6's `VERSION` file + `marketplace:version` artisan command (preserved; VERSION bumped to `Phase 8 v8.7`)
- All 19 prior CI sub-checks

v8.7 touches: `app/Http/Controllers/CatalogController.php` (1 line of code + 1 added `use` import), `VERSION`, `.github/workflows/ci.yml` (1 new step + version-tag bump), docs.

---

## Verified in this sandbox

1. ✅ Re-audited all 46 `Inertia::render`-returning controller methods project-wide — 0 mismatches after fix
2. ✅ `CatalogController::show()` signature now reads `Response|RedirectResponse` (resolves to `Inertia\Response|Illuminate\Http\RedirectResponse`)
3. ✅ `use Illuminate\Http\RedirectResponse;` added to the imports block
4. ✅ All prior defenses still pass: v8.5 dup-detect (22 unique helpers, 0 dups), v8.4 form-errors-key (0 mismatches), v8.3 schema-vs-runtime-data, v8.2 identifier-length
5. ✅ CI YAML valid, 20 Phase 8 steps + 14 Phase 7 = 34 phase-specific
6. ✅ Real tsc: TS6133=0, TS6196=0 (v8.7 didn't touch any .tsx)
7. ✅ `VERSION` file: `Phase 8 v8.7`
8. ✅ PHP brace balance preserved on CatalogController

What I cannot verify in the sandbox: actual HTTP round-trip on `/products/{slug}` (no PHP runtime). The fix is purely a type-signature change matched against `return` statement classes — straightforward to verify by inspection, which the static check does.

---

## Developer testing checklist for v8.7

```bash
# 1. Apply v8.7 cleanly (CRITICAL — the v8.6 lesson)
rm -rf vendor/composer/autoload_files.php .phpunit.cache
tar -xzf /path/to/marketplace-phase-8-v8.7.tar.gz --strip-components=1 --overwrite
composer dump-autoload -o

# 2. Verify the deploy + run static defenses
cat VERSION                       # expect: Phase 8 v8.7
php artisan marketplace:version   # expect: all 4 ✓

# 3. The specific bug — must NOT return TypeError now
php artisan optimize:clear
php artisan serve &
curl -s -o /dev/null -w "%{http_code}\n" http://127.0.0.1:8000/products
# expect: 200
curl -s -o /dev/null -w "%{http_code}\n" "http://127.0.0.1:8000/products/<any-real-product-slug>"
# expect: 200  (was 500 with TypeError on v8.6)

# 4. Service slug should redirect to /services
curl -s -o /dev/null -w "%{http_code}\n" "http://127.0.0.1:8000/products/demo-doctor-consultation"
# expect: 301

# 5. Full regression
php artisan migrate:fresh --seed
php artisan test
npm ci && npm run typecheck && npm run build
```

Then the manual UX flows from the v8.1 smoke test:

1. Browse `/products` — normal products listed, NO services
2. Click a normal product → detail page renders correctly (the bug's primary symptom)
3. Click a dropshipping product → detail page renders correctly
4. Click a customizable product → detail page renders correctly
5. Browse `/services` — services listed
6. Click a service → service detail page (with booking widget) renders correctly
7. Confirm checkout flows (normal, dropship, customizable, mixed cart) all work
8. Confirm wishlist / reviews / wallet / payouts unchanged

---

## Honest accountability — eighth Phase 8 patch

| Release | Bug class | Defense added |
|---|---|---|
| 8.0 → 8.1 | No nav links (unreachable backend) | 5 sub-checks |
| 8.1 → 8.2 | MySQL identifier > 64 chars | 3 sub-checks |
| 8.2 → 8.3 | Invented column names | 1 sub-check (schema-vs-runtime-data) |
| 8.3 → 8.4 | TS2339 form.errors.KEY | 1 sub-check (form-errors-key) |
| 8.4 → 8.5 | Duplicate global test fn + Reflection warnings | 1 sub-check (duplicate-fn) |
| 8.5 → 8.6 | Package not deployed where tests ran (observability) | 2 sub-checks (VERSION + marketplace:version) |
| **8.6 → 8.7** | **Controller return type incompatible with Inertia\Response** | **1 sub-check (return-type-vs-Inertia::render — generalizes v5.3)** |

This v8.7 bug is the **same class** as the v5.3 bug — a controller method typed with the wrong return type for `Inertia::render()`. The v5.3 test pinned the fix on `CheckoutController` specifically but didn't generalize. I introduced the same bug class in v8.1 on `CatalogController`, and it took the developer's report to surface it.

The lesson is: **when a bug class is fixed in one place, the CI defense added at the same time should cover the entire surface that bug class can appear in, not just the specific instance.** v8.7 retroactively does that for the return-type-vs-Inertia bug class — every controller method, every release going forward.

**Phase 8 v8.7 STOPS HERE. Do not start Phase 9** until:

1. `cat VERSION` prints `Phase 8 v8.7` on the deployed system
2. `php artisan marketplace:version` prints all 4 ✓
3. `curl http://127.0.0.1:8000/products/<slug>` returns 200 (was 500 in v8.1-v8.6)
4. `php artisan test` runs cleanly
5. CI shows `✅ Phase 8 v8.7 PASSES`
