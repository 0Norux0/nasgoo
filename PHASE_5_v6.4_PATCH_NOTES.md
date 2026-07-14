# Phase 5 v6.4 — Admin Orders Eager-Load Completeness Fix

**Scope:** the lazy-load regression reported on `/admin/orders` after v6.3. Same Phase 5 scope. **Phase 6 not started.**

---

## Honest accounting

This is the third lazy-load bug in the v6.x series that's reduced to the same pattern: a closure references a relation that wasn't in the eager-load chain. v5.7 was `Product->vendor`. v6.1 was `OrderEvent->actor`. v6.3 was `Order->latestPayment`.

The dev's exact words: "each time one lazy-loaded relation is fixed, another relation fails." That's accurate, and source-string inspection on action names doesn't catch any of them. v6.4 changes how I verify:

- **CI step `v6.4 — admin order pages open under strict mode (no lazy-load)`** dispatches REAL HTTP GETs to `/admin/orders`, `/admin/orders/{id}/edit`, `/admin/orders/{id}` under `Model::shouldBeStrict(true)`. If any relation lazy-loads, the response is 500 and CI fails.
- **Pest test `every relation accessed in admin Order closures is in the eager-load chain`** does a Python-style static cross-reference: parses every `$record->X` and `$this->record->X` access in the OrderResource + ViewOrder + EditOrder source files, then asserts each relation is in the `with()` list.

---

## Root cause

`OrderResource::getEloquentQuery()` was:

```php
return parent::getEloquentQuery()->with(['items', 'shippingAddress', 'payments']);
```

**`payments` is not `latestPayment`.** `payments` is `HasMany` (all payment rows on the order). `latestPayment` is a separate `HasOne` with `latestOfMany()` (the single most recent payment). They issue different queries; loading one does NOT load the other.

Every closure in OrderResource's table actions reads `$record->latestPayment?->method_slug` to decide whether to show COD-capture / Transfer-confirm / Refund. On a multi-row table this triggers Eloquent's strict-mode lazy-load detector — same exact pattern as the v5.7 `Product->vendor` bug.

ViewOrder + EditOrder override `resolveRecord()` to use `OrderResource::getEloquentQuery()` (v5.6 fix), so they inherit the same incomplete eager-load.

---

## The full audit

I parsed every `$record->X` and `$this->record->X` access across the three admin order files:

| File | Relations accessed |
|---|---|
| `OrderResource.php` | `items`, `latestPayment` |
| `OrderResource/Pages/ViewOrder.php` | `latestPayment` |
| `OrderResource/Pages/EditOrder.php` | `latestPayment` |

(`->status`, `->currency`, `->payment_status` etc. are model columns, not relations.)

Both `items` and `latestPayment` MUST be eager-loaded. Plus, by the same v6.1 pattern, `events.actor` should be eager-loaded defensively in case any view-page block iterates events.

v6.4 eager-load chain:

```php
public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
{
    return parent::getEloquentQuery()->with([
        'user:id,name,email',     // table column 'user.name'
        'items',                   // table 'items_count' + view page items
        'shippingAddress',         // view page address block
        'addresses',               // covers shippingAddress's underlying query
        'payments',                // view page payment history
        'latestPayment',           // ALL action visibility predicates ← THE BUG
        'shippingMethod:id,name',  // view page shipping summary
        'events.actor:id,name',    // defensive — any event iteration
    ]);
}
```

Then I wrote a Python-style cross-reference test that asserts every `$record->X` / `$this->record->X` relation name appearing in the three admin files is in this `with()` array. If a future change adds a new relation access without updating the eager-load, that test fails.

---

## Also fixed: VendorOrderController defensive load

`VendorOrderController::show()` loaded `events` but not `events.actor`. Same v6.1 risk class. v6.4 changes it to `events.actor:id,name`.

---

## Files changed

| File | Change |
|---|---|
| `app/Filament/Resources/OrderResource.php` | `getEloquentQuery()` expanded from 3 relations to 8, covering every relation accessed in admin closures. |
| `app/Http/Controllers/Vendor/VendorOrderController.php` | `show()` eager-load changed from `events` to `events.actor:id,name` (defensive). |
| `tests/Feature/Phase5V64RegressionTest.php` (new) | 8 scenarios: strict-mode iteration of `latestPayment` across multi-row orders, ViewOrder/EditOrder eager-load inheritance check, multi-event `events.actor` access, real GET `/admin/orders` under strict mode, customer-side and vendor-side regression coverage. |
| `.github/workflows/ci.yml` | Verdict bumped. New audit-map + verdict rows. **New CI step `v6.4 — admin order pages open under strict mode (no lazy-load)`** that GETs `/admin/orders`, `/admin/orders/{id}/edit`, `/admin/orders/{id}` (with multi-event variant) under `Model::shouldBeStrict(true)`. |
| `PHASE_5_v6.4_PATCH_NOTES.md` (this file) | New. |
| `PHASE_5_REPORT.md` | v6.4 section appended. |
| `README.md` | Header bumped to v6.4. |
| `TROUBLESHOOTING.md` | One new entry. |

---

## What the CI step actually checks

```yaml
- name: v6.4 — admin order pages open under strict mode (no lazy-load)
```

Under `Model::shouldBeStrict(true)`:

1. `GET /admin/orders` — must not return 5xx (was the v6.3 dev's crash).
2. `GET /admin/orders/{id}/edit` on a seeded DEMO-ACTIONABLE-PAID order — must not return 5xx.
3. `GET /admin/orders/{id}` (view) on the same order — must not return 5xx.
4. Add 3 events to the order (multi-event collection triggers the strict-mode detector for `events.actor`), then re-GET the view page — must not return 5xx.

If any sub-step returns 5xx, CI fails with the response body excerpt.

---

## Manual Developer Verification Checklist

After applying v6.4:

| # | Step | Expected |
|---|---|---|
| 1 | Apply v6.4: `tar -xzf marketplace-phase-5-v6.4.tar.gz && docker compose down && docker compose build app && docker compose up -d` | Builds clean. |
| 2 | `docker compose exec app php artisan migrate:fresh --seed` | Completes successfully (v6.3 fix retained). |
| 3 | Sign in as `admin@marketplace.test` → **`/admin/orders`** | List loads without lazy-load error. **Was the v6.3 crash.** |
| 4 | Click any order row → opens ViewOrder | No lazy-load error. Header shows lifecycle action buttons. |
| 5 | Same order → click Edit icon → opens EditOrder | No lazy-load error. Header shows same lifecycle action buttons. |
| 6 | On DEMO-ACTIONABLE-PAID order, click **Confirm** → confirm dialog → submit | Status → confirmed. No errors. |
| 7 | Continue: **Mark shipped** → **Mark delivered** → each transitions successfully | Status walks through paid → confirmed → shipped → delivered. Each writes an order event + audit log. |
| 8 | On DEMO-ACTIONABLE-COD-PENDING order, click **Mark COD paid** | payment_status → paid. Toast confirms. |
| 9 | GitHub Actions on the v6.4 branch | Verdict: `✅ Phase 5 v6.4 PASSES — ready to approve Phase 6`. The new `v6.4 — admin order pages open under strict mode` CI step is green. |
| 10 | All earlier CI steps remain green (v5.x lazy-load defenses, v6.1 events.actor, v6.2 EditOrder actions, v6.3 permission seeder) | No regressions. |

---

## What I can and cannot verify in the sandbox

**Can:** PHP brace balance (283/283 ✓), TypeScript (0 errors ✓), YAML/JSON parse ✓, Python static cross-reference (every admin closure relation is in the eager-load chain ✓).

**Cannot:** Filament admin route resolution, Livewire component lifecycle, actual database queries, strict-mode runtime behavior. The CI step `v6.4 — admin order pages open under strict mode` is the only place this is genuinely verified.

If CI step fails: the response body excerpt names the missing relation. The fix is to add it to the `with()` array in `OrderResource::getEloquentQuery()` AND add it to the Python cross-reference test so the bug class can't reoccur.

---

## Stop discipline

Phase 6 has not been started. Reply **"approve Phase 6"** only after the 10-step checklist passes AND the CI verdict is green. I will not declare a release ready beyond what its CI steps actually verify.
