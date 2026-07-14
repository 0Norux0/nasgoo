# Phase 7 v7.1 — `fulfillment_mode` schema fix + write-path pre-flight (regression guard)

**Status:** Targeted ergonomics + correctness fix on top of Phase 7 v7.0. Pending CI verification (the only true gate).
**Scope:** One-line fix to `DemoSeeder.php` + a new CI pre-flight that prevents this entire bug class from recurring.

---

## Symptom (what the developer reported after v7.0)

`php artisan migrate:fresh --seed` halted partway with:

```
SQLSTATE[42S22]: Column not found: 1054
Unknown column 'fulfillment_type' in 'INSERT INTO'
```

The failed insert was for the seeded **Personalized Photo Mug** demo product, with:

```php
'fulfillment_type' => \App\Models\Product::FULFILLMENT_VENDOR_SELF,
```

---

## Root cause

I made up the column name. The **actual** fulfillment column on `products` was added by **Phase 6** (`2026_01_06_000006_add_dropshipping_fields_to_products_table.php` line 34):

```php
$table->string('fulfillment_mode')->default('vendor_self')->after('supplier_cost_minor');
```

The Phase 6 dropshipping seed uses the correct name (`fulfillment_mode` — see DemoSeeder lines 1112, 1149). My Phase 7 customizable-product seed used `fulfillment_type` — a column that **does not exist**. SQL rejected the INSERT at runtime.

**Why my v7.0 sandbox sweep missed it:** I ran PHP brace balance + permission catalogue dedup + real `tsc` with stubs + a Phase 7 React validator — but I had no static check that the keys in `Model::create([...])` calls actually existed in the model's `$fillable` AND as a column in some migration. The existing CI step `Phase 6 v7.2 — marketplace:setup-demo guided command works end-to-end` runs `migrate:fresh --seed` and **would** have caught this, but the developer ran the command locally and hit the error before CI ran.

---

## What v7.1 changes

### 1. One-line fix (the actual bug)

`database/seeders/DemoSeeder.php` lines 1296 + 1351:

```diff
-                'fulfillment_type'   => \App\Models\Product::FULFILLMENT_VENDOR_SELF,
+                'fulfillment_mode'   => \App\Models\Product::FULFILLMENT_VENDOR_SELF,
```

The constant `FULFILLMENT_VENDOR_SELF = 'vendor_self'` on `Product` was already correct — only the **key name** was wrong. Both the mug and T-shirt demo products are fixed.

**Note**: customizable products defaulting to `vendor_self` fulfillment matches the Phase 6 migration's `default('vendor_self')`, so this line is technically optional — I kept it explicit to mirror the Phase 6 dropshipping seed's pattern (which sets `FULFILLMENT_DROPSHIP_MANUAL` explicitly).

### 2. New CI pre-flight — prevents this entire bug class

A new CI step `Phase 7 v7.1 — schema-vs-code pre-flight (prevents v7.0 column-mismatch bug class)` runs BEFORE the heavier `migrate:fresh --seed` sub-check. It uses a Python static analyser to:

- Walk every Phase 7 PHP write path (`Model::create`, `firstOrCreate`, `updateOrCreate`, `->relation()->create`, `->createMany`)
- Extract top-level keys with bracket-balanced parsing (so nested `options => [{...}]` arrays don't false-positive)
- Assert each key is in the model's `$fillable` AND in a migration-defined column for the relevant table
- Loud Phase 7-specific failure on any mismatch:
  ```
  ::error::Phase 7 v7.1 pre-flight FAILED — 2 write-path key(s) missing from $fillable or migration:
  ::error file=database/seeders/DemoSeeder.php::Product → migration missing: 'fulfillment_type'
  ```

The `up()` body of each migration is parsed in isolation so `dropColumn(...)` calls in `down()` methods don't accidentally remove columns from the analysis.

### 3. New CI step — explicit `migrate:fresh --seed`

A new step `Phase 7 v7.1 — explicit migrate:fresh --seed (proves runtime success of v7.0 bug fix)` runs `php artisan migrate:fresh --seed --force` directly and:

- Fails loudly if the command exits non-zero with the full output
- Greps the output for the literal string `fulfillment_type` to detect any future regression
- Verifies via `Schema::hasColumn` that `products.fulfillment_mode` exists AND `products.fulfillment_type` does NOT exist

This step is technically redundant with the existing v7.2 `marketplace:setup-demo --force` sub-check (which also runs `migrate:fresh --seed`) but the focused failure message makes regression debugging instant.

### 4. Verdict bumped

`✅ Phase 7 v7.1 PASSES — ready to approve Phase 8`

---

## Files touched in v7.1

| File | Change |
|---|---|
| `database/seeders/DemoSeeder.php` | `fulfillment_type` → `fulfillment_mode` on lines 1296 + 1351 (the only Phase 7-introduced occurrences) |
| `.github/workflows/ci.yml` | +110 lines — new pre-flight sub-check + explicit `migrate:fresh --seed` sub-check + verdict bumped |
| `PHASE_7_v7.1_PATCH_NOTES.md` | This file (new) |
| `PHASE_7_REPORT.md` | v7.1 update section appended |
| `README.md` | v7.1 changelog block prepended |
| `TROUBLESHOOTING.md` | New entry: "SQL column not found during migrate:fresh --seed" |

**Files NOT touched in v7.1:** all Phase 7 migrations, all 4 new models, all 3 extended models, all 4 services, both Filament resources, all 5 controllers, all 11 routes, both React files, all 22 Phase 7 Pest scenarios. No business logic was modified.

---

## Verification I ran in the sandbox

I cannot run `php artisan migrate:fresh --seed` in the sandbox (network 403, no PHP runtime). What I verified:

- **PHP brace balance**: 346/346 (unchanged from v7.0)
- **CI YAML parses**: valid
- **Phase 7 v7.1 pre-flight script** (the exact code that ships as the new CI sub-check): **✓ clean** — every TOP-LEVEL key in every Phase 7 write path exists in both `$fillable` and a migration-defined column
- **Audit of `fulfillment_type` everywhere in the repo**: only the 2 lines I fixed; no other occurrences (no factories, no tests, no other seeders, no controllers)

Specifically the pre-flight covers these models and tables:

| Model | Table | $fillable | Migration columns |
|---|---|---|---|
| Product | products | 35 | 39 ✓ |
| CartItem | cart_items | 8 | 11 ✓ |
| CartItemCustomization | cart_item_customizations | 11 | 14 ✓ |
| OrderItem | order_items | 20 | 23 ✓ |
| OrderItemCustomization | order_item_customizations | 10 | 13 ✓ |
| CustomizationProof | customization_proofs | 11 | 14 ✓ |
| ProductCustomizationField | product_customization_fields | 15 | 18 ✓ |
| Order | orders | 26 | 30 ✓ |
| OrderAddress | order_addresses | 16 | 19 ✓ |

(Migration column count > fillable is normal — `id`, `timestamps`, and FKs aren't typically mass-assigned.)

CI remains the only authoritative gate. **Do not approve Phase 8 until CI shows `✅ Phase 7 v7.1 PASSES`.**

---

## Developer testing checklist

After pulling v7.1:

```bash
git pull
composer install
php artisan optimize:clear
php artisan migrate:fresh --seed     # the actual bug-repro command — must succeed
npm ci
npm run typecheck
npm run build
php artisan test --filter Phase7Customization
```

Then manually:

1. Log in as `customer@marketplace.test` → `/products/demo-custom-mug` → confirm the customization form renders
2. `/orders` → open the `DEMO-CUSTOM-%` order → confirm the SENT proof is visible with Approve / Reject buttons
3. Log in as `vendor@marketplace.test` → `/vendor/products` → confirm both customizable products show the "Customize" link

If `php artisan migrate:fresh --seed` reports any further SQL column errors, the v7.1 CI pre-flight would have caught them — file an issue with the exact error.

---

## Apology + accountability

This is the second time my v7.x release has shipped with a runtime error that the developer caught in seconds. The bug class is "I introduced a column name that doesn't exist and my static checks couldn't see it." My sandbox cannot run PHP, but I should have grep'd for the exact column name in existing migrations BEFORE writing the seed. The new CI pre-flight + the explicit `migrate:fresh --seed` step exist so this entire class of bug fails loud in CI even if I miss it again.

**Phase 7 v7.1 STOPS HERE. Do not start Phase 8 until CI verdict is green AND `php artisan migrate:fresh --seed` runs locally to green.**
