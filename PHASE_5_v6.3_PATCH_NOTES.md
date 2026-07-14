# Phase 5 v6.3 — Permission Seeder + Actionable Demo Orders Fix

**Scope:** the two developer-reported regressions in v6.2. Same Phase 5 scope. **Phase 6 is not started.**

---

## Honest accounting

You're right to be frustrated. Three releases in a row (v6.0, v6.1, v6.2) shipped with basic manual-check failures, and my "ready for testing" claims weren't grounded in real verification. Two things made that happen:

1. **I can't run PHP in this sandbox.** No `php artisan migrate:fresh --seed`, no `vendor/bin/pest`, no Filament UI. My pre-package "verification" is brace balance + TypeScript + YAML parse. That catches syntax mistakes but **misses every semantic bug** — including the one in v6.2: duplicate PHP array keys silently dropping half the permission catalogue.

2. **My v6.2 tests asserted "source contains `Action::make('confirm')`".** That passed even though the action's `->visible()` predicate returned false at runtime because `orders.confirm` didn't exist as a permission. Source-string inspection is not equivalent to runtime behaviour.

What v6.3 changes about that:

- **CI step `v6.3 — migrate:fresh --seed succeeds + permissions registered + actionable demo orders exist`** runs `php artisan migrate:fresh --seed` for real. If seeding throws PermissionDoesNotExist, this step exits with code 1 and CI fails. Six sub-checks: (1) seed succeeds, (2) every `->can()` permission is registered, (3) super_admin role + user actually have those permissions at runtime, (4) actionable demo orders exist in the right statuses, (5) action `->visible()` predicates evaluate **TRUE** at runtime for super_admin, (6) a real Confirm transition writes order_event + audit_log.
- **Pest test `RolesAndPermissionsSeeder runs without "PermissionDoesNotExist" exceptions`** exercises the actual seeder, not a string check.
- **Pest test `super_admin user can() every order lifecycle permission`** verifies the runtime behaviour Filament cares about.

I will not declare this release "ready" beyond what those checks actually validate. Your CI run is the verification I cannot perform in the sandbox; the manual 10-step checklist below is the verification that depends on you.

---

## Root cause #1: duplicate PHP array keys silently dropping permissions

`database/seeders/RolesAndPermissionsSeeder.php` had this in `permissionCatalogue()`:

```php
return [
    // ...
    'products' => [
        'products.view', 'products.create', 'products.update',
        'products.delete', 'products.publish', 'products.feature',
        'categories.manage', 'attributes.manage',
    ],
    'orders' => [
        'orders.view', 'orders.view.any', 'orders.confirm',
        'orders.ship', 'orders.deliver', 'orders.cancel',
        'orders.refund', 'orders.export',
        'payments.view', 'payments.capture', 'payments.refund',
        'payment_methods.manage',
    ],
    'products' => [                  // ← DUPLICATE KEY
        'products.view', 'products.create', 'products.update',
        'products.approve', 'products.delete',
    ],
    'services' => [ /* … */ ],
    'orders' => [                    // ← DUPLICATE KEY
        'orders.view', 'orders.manage',
    ],
    // ...
];
```

In PHP, when an associative array has duplicate keys, the **later value silently overwrites the earlier value**. So the registered `'orders'` array became `['orders.view', 'orders.manage']` — **dropping `orders.confirm/ship/deliver/cancel/refund/export` + `payments.view/capture/refund` + `payment_methods.manage`**.

Effects:
1. `$vendor->syncPermissions(['orders.confirm', ...])` blew up because the permission record didn't exist → **`migrate:fresh --seed` failed entirely** with `Spatie\Permission\Exceptions\PermissionDoesNotExist`.
2. On any environment where seeding had succeeded historically (older seed version), the Filament `->visible(fn () => auth()->user()?->can('orders.confirm'))` predicate returned false because the permission record was never created → **action buttons silently hidden**.

**Fix:** merged the duplicate-key entries into single canonical arrays. Result: 14 unique module keys, 47 total permissions registered, every permission referenced by `->can()` in OrderResource / EditOrder / ViewOrder is in the catalogue.

I also added a Pest test that asserts `count($catalogue) === count(array_unique(array_keys($catalogue)))` so this class of bug can't sneak back in.

## Root cause #2: no demo orders in actionable statuses

Even after fixing the permissions, the dev still wouldn't see action buttons because every seeded order was in `delivered` status. The Filament action visibility predicates check `status === 'paid'`, `status === 'shipped'`, etc. — none match `delivered`.

**Fix:** new `seedActionableOrdersForAdmin()` method seeds 4 orders:
- **DEMO-ACTIONABLE-PAID-…** in `paid` status → admin sees Confirm + Mark shipped + Cancel + Refund
- **DEMO-ACTIONABLE-CONFIRMED-…** in `confirmed` status → admin sees Mark shipped + Cancel + Refund
- **DEMO-ACTIONABLE-SHIPPED-…** in `shipped` status → admin sees Mark delivered + Cancel + Refund
- **DEMO-ACTIONABLE-COD-PENDING-…** with payment_status `pending` + COD method → admin sees Mark COD paid

So immediately after `migrate:fresh --seed`, opening any of these four orders in the admin panel shows lifecycle actions.

---

## Files changed

| File | Change |
|---|---|
| `database/seeders/RolesAndPermissionsSeeder.php` | `permissionCatalogue()` rewritten — merged duplicate-key entries; 47 permissions across 14 modules. Header comment explains the bug + fix so future maintainers can't reintroduce it. |
| `database/seeders/DemoSeeder.php` | New `seedActionableOrdersForAdmin()` method called from `run()`. Idempotent (skips if DEMO-ACTIONABLE-* orders already exist). |
| `tests/Feature/Phase5V63RegressionTest.php` (new) | 11 scenarios that exercise real runtime behavior — seeder runs without throwing, catalogue has no duplicate keys, every `->can()` permission registered, super_admin role + user actually has them, lifecycle service writes events + audit logs. |
| `.github/workflows/ci.yml` | Verdict bumped. New audit-map row + verdict row. **New CI step `v6.3 — migrate:fresh --seed succeeds + …`** that runs the actual seed and 6 sub-checks via `php artisan tinker --execute`. |
| `PHASE_5_v6.3_PATCH_NOTES.md` (this file) | New. |
| `PHASE_5_REPORT.md` | v6.3 section appended. |
| `TROUBLESHOOTING.md` | Permission seeding troubleshooting entries added. |
| `README.md` | Header bumped to v6.3. |

---

## What the CI step actually checks

```bash
- name: v6.3 — migrate:fresh --seed succeeds + permissions registered + actionable demo orders exist
```

Steps:
1. **Run `php artisan migrate:fresh --seed --force`** — if Spatie throws, this fails immediately.
2. **`Permission::where('name', 'orders.confirm')->exists()`** for each of orders.{view,confirm,ship,deliver,cancel,refund} and payments.{view,capture,refund}. List of missing perms reported on failure.
3. **`$admin->hasRole('super_admin')` AND `$admin->can('orders.confirm')`** etc. — runtime ability check, not just role attachment. List of denied abilities reported on failure.
4. **Actionable demo orders exist** in paid / confirmed / shipped / cod-pending statuses with the `DEMO-ACTIONABLE-` number prefix.
5. **Visibility predicates evaluate TRUE** for super_admin on the paid + shipped demo orders, using the same boolean expression that Filament evaluates at render time.
6. **Real lifecycle transition** — call `OrderLifecycleService::confirm()` on the paid demo order, then assert: status flipped, `order_event` row written with `event_type='confirmed'`, `audit_log` row written with `action='order.confirmed'`.

If any of those 6 sub-steps fails, the verdict line **`✅ Phase 5 v6.3 PASSES`** never prints and the workflow's exit code is non-zero.

---

## What I can verify in the sandbox

| Check | What it catches | What it misses |
|---|---|---|
| PHP brace balance | Syntax errors only | Logic, semantics, runtime |
| TypeScript offline compile | Type errors in React | Inertia-server contract, runtime |
| YAML/JSON parse | Workflow file malformations | What the workflow actually does |
| Python static cross-ref | Permission strings used vs registered | Whether Spatie's seed runs |

282/282 PHP brace balance, 0 TypeScript errors, valid CI YAML, **14/14 unique permission catalogue keys, 0 permissions referenced but not registered**.

What I cannot verify in the sandbox: `php artisan migrate:fresh --seed` actually completing successfully. The CI step above is the verification.

---

## Manual Developer Verification Checklist

After applying v6.3:

| # | Step | Expected |
|---|---|---|
| 1 | `docker compose down && docker compose build app && docker compose up -d` | Builds clean. |
| 2 | `docker compose exec app php artisan migrate:fresh --seed` | **Completes successfully.** No `PermissionDoesNotExist` exception. Final log lines include "Phase 5 v6.3: 4 actionable demo orders seeded". |
| 3 | Sign in as `admin@marketplace.test` / `password` → Admin → Orders | List shows multiple orders including **DEMO-ACTIONABLE-PAID**, **CONFIRMED**, **SHIPPED**, **COD-PENDING** prefixes. |
| 4 | Click the Edit icon on DEMO-ACTIONABLE-PAID order | Header shows: **Confirm** · **Mark shipped** · **Cancel** · **Refund** · View. The Confirm + Mark shipped buttons were missing in v6.2. |
| 5 | Click **Confirm** → confirm dialog | Status flips to `confirmed`; page reloads. Toast "Order confirmed." appears. |
| 6 | Same order, click **Mark shipped** → confirm | Status flips to `shipped`. Toast "Order shipped." |
| 7 | Same order, click **Mark delivered** → confirm | Status flips to `delivered`. Toast about 7-day earnings release. |
| 8 | Open DEMO-ACTIONABLE-COD-PENDING order in Edit | Header shows **Mark COD paid** button + Cancel. |
| 9 | Click **Mark COD paid** | payment_status flips to `paid`. Order status moves out of pending_payment. |
| 10 | After each transition, check the audit log (Admin → Audit Logs or via tinker) | One row per transition with `action='order.confirmed'`, `'order.shipped'`, `'order.delivered'`, etc. The order's "events" timeline shows the transition records. |
| 11 | GitHub Actions on the v6.3 branch | Verdict: `✅ Phase 5 v6.3 PASSES — ready to approve Phase 6`. The new `v6.3 — migrate:fresh --seed succeeds + …` step is green. |

If step 2 still fails: capture the **full** stack trace and share it. The output should now show exactly which permission name (if any) is still missing.

If step 4 still shows no buttons: run this in tinker to diagnose:
```bash
docker compose exec app php artisan tinker --execute="
  \$admin = App\Models\User::where('email','admin@marketplace.test')->first();
  echo 'roles: '.\$admin->roles->pluck('name')->implode(',').PHP_EOL;
  echo 'can(orders.confirm): '.var_export(\$admin->can('orders.confirm'), true).PHP_EOL;
  echo 'orders.confirm exists: '.var_export(Spatie\Permission\Models\Permission::where('name','orders.confirm')->exists(), true).PHP_EOL;
"
```
Expected: roles contains `super_admin`, both `can()` calls return `true`, exists returns `true`. If any return false, the v6.3 archive wasn't extracted into the container.

---

## Stop discipline

Phase 6 has not been started. I will not declare a future release ready beyond what its CI step actually verifies. Reply **"approve Phase 6"** only after the 11-step checklist passes and the CI verdict is green.
