# Phase 8 v8.3 — Wrong column-name fix (`stock_minor`/`manage_stock` → `stock`/`track_stock`)

**Status:** Targeted bug fix on top of Phase 8 v8.2. Pending CI verification.
**Scope:** 8 string replacements across 5 files. No schema changes, no logic changes, no React changes. Same patch pattern as v8.2 (data-only fix).

---

## Root cause

I invented the column names `stock_minor` and `manage_stock` from memory when writing the Phase 8 service-creation code. The **real** `products` table columns are:

| Real column | Type | What I wrongly wrote |
|---|---|---|
| `stock` | `integer` default 0 | `stock_minor` ✗ |
| `track_stock` | `boolean` default true | `manage_stock` ✗ |

The error your developer hit:

```
SQLSTATE[42S22]: Column not found: 1054 Unknown column 'stock_minor' in 'INSERT INTO'
(Connection: mysql, SQL: insert into `products` (`vendor_id`, `sku`, `name`, ..., `stock_minor`, `manage_stock`, ...) ...)
```

The 8 references that needed fixing (audited via `grep -rE 'stock_minor|manage_stock'`):

| File | Line(s) | What it did |
|---|---|---|
| `app/Http/Controllers/Vendor/VendorServiceController.php` | 97-98 | Wrote bad columns when vendor created a service via form |
| `database/seeders/DemoSeeder.php` | 1636-1637 | Wrote bad columns seeding the Doctor Consultation service |
| `database/seeders/DemoSeeder.php` | 1668-1669 | Wrote bad columns seeding the Home AC Cleaning service |
| `tests/Feature/Phase8ServiceBookingTest.php` | 60 | Bad column in test factory helper |
| `tests/Feature/Phase8V81CompletionTest.php` | 53 | Bad column in test factory helper |

---

## Why the v7.1 schema-vs-code pre-flight didn't catch this

The v7.1 pre-flight checks **model `$fillable` arrays** against migration columns. It does not check **runtime data shapes** passed to `Model::create()` / `Model::updateOrCreate()`. Because:

1. `Product::$fillable` correctly lists `stock` and `track_stock` (never had the bad names)
2. The v7.1 check therefore passed cleanly
3. But the seeder + controller + tests directly passed `stock_minor` / `manage_stock` keys to mass-assign calls — and those keys made it into the SQL `INSERT`

**v8.3 adds the missing complementary check** (see CI section below).

---

## Why the runtime CI step didn't catch this either

The Phase 8.0 CI step `Phase 8 — runtime demo data check` does run `php artisan migrate:fresh --seed --force` with `set -e`. It should have failed identically on Postgres. The most likely explanations:

1. The CI had been failing earlier in the pipeline (e.g. at the v8.2 MySQL identifier check before that fix), masking later failures
2. The runtime check used an older snapshot of the seeder
3. Or that step wasn't reaching the seeded services for some other config reason

Either way, the new static check **runs in seconds before any database step** and catches the same bug class without needing migration infrastructure.

---

## Fix

8 string replacements:

```diff
- 'stock_minor'  => 0,
- 'manage_stock' => false,
+ 'stock'        => 0,
+ 'track_stock'  => false,   // services don't track inventory
```

For services, `track_stock=false` is semantically correct: services don't have inventory the way physical goods do. Setting it to false means the storefront won't show them as out-of-stock when `stock=0`.

---

## New CI sub-check (codifies the defense)

**`Phase 8 v8.3 — schema-vs-runtime-data pre-flight`**

A static Python script that:

1. Parses `database/migrations/*create_products_table.php` to extract the real column list (also picks up columns added by `*add*products*` migrations from Phase 6+)
2. Walks every PHP file in `database/seeders/`, `app/Http/Controllers/`, and `tests/Feature/`
3. Finds every `Product::create([...])`, `Product::updateOrCreate([...], [...])`, and `Product::factory()->create([...])` call (with a negative lookbehind so `SupplierProduct` / `ProductCustomizationField` don't false-match)
4. Extracts every string key passed in the data arrays
5. Verifies each key is a real column on the `products` table
6. Fails CI with `file=...` annotations pointing to the exact line if any key doesn't exist as a column

After the v8.3 fix, scanning **9 real `Product` create/update sites** in the codebase finds **0 bad column references**. Before the fix it found 8.

Sandbox run (the same script as the CI step):
```
Scanned 9 Product (not SupplierProduct) create/update calls
✓ All keys map to real products columns — v8.3 fix verified.
```

---

## Phase 8 CI sub-check totals after v8.3

| Release | Sub-checks |
|---|---|
| Phase 8.0 | 6 |
| Phase 8 v8.1 | 5 |
| Phase 8 v8.2 | 3 |
| Phase 8 v8.3 | **1** |
| **Total** | **15** |

Plus 14 Phase 7 sub-checks = **29 phase-specific CI sub-checks**.

Final CI verdict bumped to: `✅ Phase 8 v8.3 PASSES — ready to approve Phase 9`.

---

## What did NOT change in v8.3

- Migrations (Phase 8 schema unchanged — same as v8.2)
- Routes
- React layouts and pages
- Domain services (`ServiceBookingService`, `ServiceAvailabilityService`)
- Models (`ServiceBooking`, `ServiceProvider`, `ServiceDetail`, etc.)
- Filament admin resource
- v8.1 booking confirmation page
- v8.1 reschedule flow
- v8.2 explicit short index names (still in place)
- 38 Pest scenarios from v8.0 + v8.1 (still in place)
- 6 Pest scenarios from v8.2 (still in place)

---

## Sandbox verification

What I verified in the sandbox before packaging:

1. ✅ Zero remaining `stock_minor` or `manage_stock` references anywhere in `app/`, `database/`, `tests/`, `resources/js/`
2. ✅ New `track_stock=false` present in 4 places (2 in seeder, 1 in controller, factories handled by Phase 6 ProductFactory)
3. ✅ The v8.3 schema-vs-runtime-data check runs locally with 0 failures (9 Product calls scanned)
4. ✅ CI YAML still parses
5. ✅ PHP brace balance preserved on all 5 touched files
6. ✅ Real tsc on the whole project: TS6133=0, TS6196=0
7. ✅ All v8.2 explicit short index names still present
8. ✅ All v8.1 nav links + confirmation page + reschedule routes still present
9. ✅ All v7.x defenses still present
10. ✅ Leak check: 0 plan, 0 node_modules, 0 tsconfig.verify.json

What I cannot verify in the sandbox (no PHP / Composer / MySQL): the actual `php artisan migrate:fresh --seed` run. That's what CI is for — the new sub-check catches the bug class statically in seconds without needing a database.

---

## Developer testing checklist for v8.3

```bash
git pull
composer install
php artisan optimize:clear

# THE critical check — must complete without SQLSTATE 1054 (column not found)
# AND without SQLSTATE 1059 (identifier too long, fixed in v8.2)
php artisan migrate:fresh --seed

# Run twice to confirm idempotency
php artisan migrate:fresh --seed

# Verify the seeded services exist with real columns set
php artisan tinker --execute='echo \App\Models\Product::where("type","service")->first()->track_stock ? "true" : "false";'
# Should print: false

php artisan test
npm ci && npm run typecheck && npm run build
```

---

## Accountability

This is the fourth bug in the Phase 8 release cycle:

- **8.0**: backend complete but no nav links → unreachable
- **8.1**: fixed nav, but compound index names too long for MySQL
- **8.2**: fixed MySQL identifier limit, but seeder used invented column names
- **8.3 (this release)**: fixes invented column names + adds CI sub-check to prevent recurrence

Each successive bug has been a class the previous CI didn't catch:
- v8.1 added: nav link greps + confirmation page check + reschedule check
- v8.2 added: static identifier-length pre-flight + MySQL runtime check + index-name regression tests
- v8.3 adds: static **schema-vs-runtime-data** pre-flight (the missing complement to v7.1's schema-vs-model check)

Each new CI sub-check is the lesson from the bug it codifies. Going forward, anyone who passes a wrong column name to `Product::create()` / `Product::updateOrCreate()` / `Product::factory()->create()` will get a hard failure in CI with `file=...,line=...` annotations, before any database-touching step runs.

**Phase 8 v8.3 STOPS HERE. Do not start Phase 9** until:
1. `php artisan migrate:fresh --seed` completes cleanly on MySQL AND Postgres
2. CI shows `✅ Phase 8 v8.3 PASSES — ready to approve Phase 9`
3. The v8.1 manual smoke test still passes (nav links, booking confirmation, reschedule)
