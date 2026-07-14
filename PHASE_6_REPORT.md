# Phase 6 Report — Dropshipping / Supplier Product Import

## Overview

Phase 6 builds a dropshipping foundation on top of the marketplace platform.
Vendors can register **supplier platforms** (Amazon, Temu, Daraz, AliExpress,
Alibaba, local wholesale, private suppliers), create per-platform
**integrations** with credentials encrypted at rest, import **supplier
products** manually or via CSV upload (with dry-run validation and per-row
error reports), map them into marketplace listings for admin approval, and
track **supplier orders** auto-created at customer checkout through a
status state machine.

**Compliance:** This phase implements manual entry, CSV import, supplier
URL reference (as data, never fetched), and an API-ready schema foundation.
**No code scrapes or pulls from any third-party platform.** Real API
adapters can be added later only where the supplier explicitly authorizes
API access.

---

## Architecture

```
┌─────────────────────┐         ┌────────────────────────┐
│  SupplierPlatform   │─────────│ SupplierIntegration    │
│  (admin-managed     │ hasMany │ (vendor-scoped,        │
│   catalogue)        │         │  encrypted credentials)│
└──────────┬──────────┘         └────────────┬───────────┘
           │                                 │
           │ hasMany                         │ hasMany
           ▼                                 ▼
┌─────────────────────────────────────────────────────┐
│              SupplierProduct                        │
│  (raw imports: pending → mapped → published)        │
│      ↓ mapped_at (mapper service)                   │
└──────────┬──────────────────────────────────────────┘
           │ optional product_id
           ▼
┌─────────────────────┐
│   Product           │
│  (type='dropship',  │
│   status pending →  │
│   published)        │
└──────────┬──────────┘
           │ customer buys at checkout
           ▼
┌─────────────────────┐         ┌─────────────────────────┐
│   OrderItem         │─────────│  SupplierOrder          │
│  (supplier_cost     │ via     │  (pending → placed →    │
│   snapshot)         │ FK      │   shipped → delivered)  │
└─────────────────────┘         └────────┬────────────────┘
                                          │ hasMany
                                          ▼
                                ┌─────────────────────────┐
                                │  SupplierOrderEvent     │
                                │  (status audit log)     │
                                └─────────────────────────┘
```

## Schema additions

7 new migrations + 2 existing tables extended. Full DDL is in
`database/migrations/2026_01_06_*`. Highlights:

- `supplier_platforms` — admin catalogue (name, slug, logo_path, website_url,
  integration_type[manual|csv|api|feed], default_currency, default_delivery_days,
  is_active, notes, display_order).
- `supplier_integrations` — vendor-scoped (vendor_id, supplier_platform_id,
  name, integration_type, **credentials text encrypted at app layer**, feed_url,
  sync_options json, is_active, last_synced_at, last_sync_status,
  last_sync_message). Unique on (vendor_id, supplier_platform_id, name).
- `supplier_products` — raw imports (external_product_id/sku, source_url, title,
  description, images json, supplier_cost_minor, supplier_currency,
  supplier_stock_status, supplier_stock_qty, supplier_shipping_minor,
  estimated_delivery_days, raw_payload json,
  import_status[pending|mapped|published|rejected|discontinued],
  imported_at/mapped_at/published_at).
- `supplier_orders` + `supplier_order_events` — auto-created on dropship checkout
  (number `SUP-YYYYMM-XXXXXX`, vendor_id, supplier_platform_id, order_id,
  supplier_product_id, 8 statuses, supplier_reference, tracking_number,
  tracking_url, carrier, cost snapshot, currency, placed_at/shipped_at/
  delivered_at/cancelled_at). Events table mirrors `order_events` pattern.
- `supplier_product_imports` — CSV batch records (original_filename, status,
  dry_run boolean, total_rows, successful_rows, failed_rows, errors json,
  summary json, processed_at).
- `products` extended — `supplier_product_id`, `supplier_platform_id`,
  `supplier_cost_minor`, `fulfillment_mode` (vendor_self | dropship_manual |
  dropship_admin | dropship_api), `estimated_delivery_days`.
- `order_items` extended — `supplier_order_id` FK, `supplier_cost_minor`
  snapshot (matches the commission/vendor_earning snapshot pattern from
  Phase 4).

## Services

`app/Domain/Supplier/`:

- **`SupplierProductImporter`**
  - `importManual($vendor, $platform, $payload)` — single-row supplier product
    create with field validation.
  - `importCsv($vendor, $platform, $rows, dryRun, $filename)` — batch import
    inside one DB transaction. Records the batch as a
    `SupplierProductImport`. Returns per-row error report. With `dryRun=true`,
    validates each row but persists nothing.
- **`SupplierProductMapper`**
  - `map($supplierProduct, $overrides, $actor)` — creates the marketplace
    Product in `pending_review` status. Enforces `selling_price >= supplier_cost`.
    Walks `supplier_product` `pending → mapped`. Generates a unique SKU
    `DRP-{platform_prefix}-{random}`.
  - `publish($supplierProduct, $admin)` — admin approval. Walks
    `supplier_product` `mapped → published` and the linked Product to
    `published` with `approved_at/approved_by/published_at` stamps.
  - `reject($supplierProduct, $reason, $admin)` — walks to `rejected`.
- **`DropshipOrderCreator`**
  - `createFromOrder($order)` — groups dropship items by
    `(vendor_id, supplier_platform_id)` into one `supplier_order` per group
    with cost snapshot and a `supplier_order.created` audit event.
  - `transition($so, $newStatus, $actorId, $actorRole, $note)` — validates
    against `SupplierOrder::ALL_STATUSES`, stamps the appropriate `*_at`
    timestamp (placed_at/shipped_at/delivered_at/cancelled_at), writes a
    `SupplierOrderEvent`.

`CheckoutService::place()` snapshots `supplier_cost_minor` on dropship
OrderItem rows (null for non-dropship) and calls
`DropshipOrderCreator::createFromOrder()` after items + totals are
persisted. Idempotent at the (vendor, platform, order) grouping level.

## Admin (Filament)

`app/Filament/Resources/`:

- `SupplierPlatformResource` — CRUD on the catalogue.
- `SupplierIntegrationResource` — list + view + edit; credentials display via
  a `Placeholder` field calling `maskedCredentials()` (never shows
  plaintext). `getEloquentQuery()` eager-loads `['vendor', 'platform']`.
  `resolveRecord()` override on View/Edit pages applies the same eager
  loads (lesson from Phase 5 v5.6).
- `SupplierProductResource` — list + view + edit (no create — vendors import).
  Row actions: `approve` (visible when status=mapped, gated on
  `supplier_products.approve`) calls `SupplierProductMapper::publish()`;
  `reject` (visible on pending|mapped, gated on
  `supplier_products.reject`) accepts a reason via form field.
- `SupplierOrderResource` — list + view + edit (no create — auto-generated).
  Row actions: `mark_placed`, `mark_shipped`, `mark_delivered` with v6.4-style
  visibility predicates and confirmation dialogs.
  `getEloquentQuery()` eager-loads `['vendor', 'platform', 'order:id,number',
  'orderItems', 'events.actor:id,name']` — every relation accessed in
  closures or the table layer.

## Vendor (Inertia + React)

`app/Http/Controllers/Vendor/`:

- `VendorSupplierProductController` — index, manualForm, storeManual,
  mapForm, storeMapping, csvForm, csvImport (parses CSV via `fgetcsv` — no
  extra dependency, 2MB max), importReport.
- `VendorSupplierOrderController` — index, show, update (refs/tracking),
  transition.
- `VendorSupplierIntegrationController` — index, store, update, destroy.

All vendor controllers scope by `vendor_id` at the query layer
(`SupplierProduct::forVendor()`, `SupplierOrder::forVendor()`,
`$vendor->supplierIntegrations()`). Cross-vendor access by route ID
produces a 404, not unauthorized — this is the convention from Phases 4-5.

`resources/js/Pages/Vendor/Supplier/`:

- `Products/Index.tsx` — paginated list with status badges + map link
- `Products/Manual.tsx` — single supplier-product form with multi-image URL adder
- `Products/CsvImport.tsx` — file upload, dry-run checkbox, expected-columns list
- `Products/ImportReport.tsx` — per-row error report
- `Products/Map.tsx` — side-by-side source preview + marketplace listing form;
  40% default markup; enforces `price >= cost` client-side too
- `Orders/Index.tsx` — supplier orders list with status colour codes
- `Orders/Show.tsx` — detail page with refs+tracking form + status transition
  buttons (`allowedNext` map) + event log
- `Integrations/Index.tsx` — list + inline create form with masked credential
  display, conditional API key fields when integration type is `api` or `feed`

VendorLayout: added `Suppliers` and `Supplier Orders` links inside the
`isApprovedVendor` block.

## Permissions

`database/seeders/RolesAndPermissionsSeeder.php` — 4 new top-level catalogue
module keys (`supplier_platforms`, `supplier_integrations`, `supplier_products`,
`supplier_orders`) adding 13 permissions. The keys are deliberately distinct
from existing module names to avoid the v6.3 duplicate-array-key class of bug
that caused permissions to overwrite each other silently. Permission catalogue
now has 18 unique top-level keys.

Vendor role syncs to the self-management subset (no
`supplier_products.approve` / `supplier_products.reject` /
`supplier_platforms.manage`); super_admin gets the full set automatically.

## Demo data

`DemoSeeder::seedSupplierPlatforms()` + `seedSupplierIntegrationsAndProducts()`
+ `seedDropshippingOrder()`:

- 7 supplier platforms (AliExpress, Alibaba, Amazon, Temu, Daraz, Local
  Wholesale, Private Supplier).
- Demo Trading Co (`vendor@marketplace.test`) gets one `AliExpress demo
  catalogue` integration with demo encrypted credentials.
- 3 supplier products for Demo Trading Co: `DEMO-AE-001` (pending),
  `DEMO-AE-002` (mapped, awaiting admin), `DEMO-AE-003` (mapped + published
  by demo admin via `SupplierProductMapper::publish()`).
- One customer order `DEMO-DROPSHIP-{timestamp}` from
  `customer@marketplace.test` buying the published `LED Desk Lamp` (COD,
  paid, 20% commission) — `DropshipOrderCreator` auto-creates one
  `supplier_order` in `pending` status so vendors can immediately test the
  status state machine without placing a fresh order.

All seed steps are idempotent (`firstOrCreate`, status guards) — safe to
re-run with `migrate:fresh --seed`.

## Tests

`tests/Feature/Phase6DropshippingTest.php` — 23 scenarios across 13 sections:

1. Permission catalogue integrity + role split (super_admin vs vendor)
2. Admin SupplierPlatformResource CRUD
3. Vendor creates `SupplierIntegration` — **DB column-level assertion that
   plaintext is NOT in `\DB::table('supplier_integrations')->value('credentials')`**
4. Manual import via POST `/vendor/supplier-products/manual`
5. CSV import (4-row mix of valid/invalid) + dry-run-does-not-persist
6. Mapping → Product in `STATUS_PENDING_REVIEW`; price-below-cost rejection
7. Admin `publish()` walks `SupplierProduct` to `PUBLISHED` and Product
   to `PUBLISHED`
8. Storefront query finds the published dropship product
9. `DropshipOrderCreator` with quantity-multiplied cost snapshot; non-dropship
   checkout does NOT create a supplier_order
10. Status state machine: `pending → placed → shipped → delivered` with
    timestamp stamps + 3 events
11. Vendor scoping (3 tests): v1 sees only v1's supplier products + supplier
    orders; cannot map v2's supplier product (404)
12. **Lazy-load defenses (v6.4 pattern)** on `/vendor/supplier-products`,
    `/vendor/supplier-orders`, `/vendor/supplier-orders/{id}` under
    `Model::shouldBeStrict(true)` with multi-row + multi-event collections
13. Admin Filament SupplierPlatformResource + SupplierOrderResource queries
    open under strict mode

## CI

`.github/workflows/ci.yml`:

- Phase 6 verdict header bumped (`## 🎯 Phase 6 Verification Result`).
- Final verdict: `### ✅ Phase 6 PASSES — ready to approve Phase 7`.
- Audit-map row added describing Phase6DropshippingTest assertions.
- Phase 6 verdict-table row summarising schema, services, Filament, CSV
  format, and the no-scraping compliance note.
- **New CI step `Phase 6 — supplier platforms, integrations, imports, and
  dropship checkout end-to-end`** with 5 sub-checks via
  `php artisan tinker --execute`:
  1. ≥5 supplier platforms exist + demo integration exists + credentials
     are NOT plaintext in the underlying DB column + supplier products in
     pending/mapped/published states + dropship Product is published.
  2. `DEMO-DROPSHIP-*` order exists + has a `supplier_order` in `pending`
     with cost > 0.
  3. Walks the supplier order `pending → placed → shipped → delivered`,
     asserts timestamps + 3 status events.
  4. GET `/vendor/supplier-products` and `/vendor/supplier-orders` under
     `Model::shouldBeStrict(true)` returns < 500 (no lazy-load fault).
  5. GET `/admin/supplier-platforms`, `/admin/supplier-integrations`,
     `/admin/supplier-products`, `/admin/supplier-orders` under strict mode.

## Honest limitations (sandbox)

I cannot run PHP, Composer, or migrations in the sandbox. What this build
has confirmed in the sandbox:

- **PHP brace balance**: 320/320 source files
- **TypeScript**: 0 errors in `resources/js/**`
- **YAML/JSON parse**: `ci.yml`, `package.json`, `composer.json` valid
- **Permission catalogue**: 18 unique top-level keys, no duplicates
- **Cross-reference scan**: every relation accessed in Phase 6 closures is
  in the eager-load chain of its query (one false positive on
  `VendorSupplierIntegrationController` resolved manually — uses
  single-string `->with('platform:...')` form not matched by the array regex)

What only CI can verify:

- migrate:fresh --seed actually completes
- All 23 Phase 6 tests pass
- The 5 sub-checks of the new Phase 6 CI step pass
- No runtime regressions in any earlier phase

The CI step is the only true verification gate. **Approve Phase 7 only
after the CI summary shows `✅ Phase 6 PASSES`.**

---

## v7.1 update — APP_KEY pre-seed guard (targeted fix)

After v7.0 was delivered, the developer ran `php artisan migrate:fresh --seed` on a fresh clone WITHOUT first running `php artisan key:generate`. The seeder crashed partway through with `Illuminate\Encryption\MissingAppKeyException` — Phase 6's `SupplierIntegration::credentials` cast is `encrypted:array` and the Encrypter needs `APP_KEY`. v7.1 fixes the developer experience without touching the encryption itself.

**Changes:**

- `DemoSeeder::run()` checks `blank(config('app.key'))` immediately after the env gates and throws a clear `RuntimeException` with the exact remedy (`cp .env.example .env` → `php artisan key:generate` → `php artisan migrate:fresh --seed`) **before any encrypted write is attempted**. The guard sits below the `testing` env skip so Pest is unaffected.
- `GETTING_STARTED.md` and `README.md` now lead with the three-step quick-start banner. `TROUBLESHOOTING.md` gains a top-of-file entry for `MissingAppKeyException` and a note about the `--seed.` trailing-dot typo.
- Two new test scenarios in `Phase6DropshippingTest.php` (now 25 total): encrypted credentials round-trip with APP_KEY set; DemoSeeder throws the helpful `RuntimeException` when APP_KEY is blank.
- New CI step `Phase 6 v7.1 — APP_KEY setup verification (regression check on local-install crash)` with 5 sub-checks: `.env.example` is blank, `.env` `APP_KEY` is a generated `base64:*` key, blanking APP_KEY at runtime fires the helpful message, credentials column is encrypted at rest AND the cast decrypts back to an array, CI itself never accidentally uses `migrate:fresh --seed.` with a trailing dot.

**Not changed:** encryption (cast preserved), schema, services, Filament resources, controllers, React pages, routes, demo data, `.env.example` (already correct). Full v7.1 detail in `PHASE_6_v7.1_PATCH_NOTES.md`.

Final CI verdict is now `✅ Phase 6 v7.1 PASSES — ready to approve Phase 7`.

---

## v7.2 update — guided setup command (targeted fix)

After v7.1 traded the cryptic `MissingAppKeyException` for a clear remedy message, the developer still hit the same crash on new clones because the 4-command recovery sequence wasn't memorable and the `optimize:clear` step was easy to skip. v7.2 collapses the entire setup into a single guided artisan command without removing the safer manual path.

**Changes:**

- `app/Console/Commands/MarketplaceSetupDemo.php` — new artisan command `php artisan marketplace:setup-demo` that walks `.env` check → `key:generate` → `optimize:clear` → `migrate:fresh --seed` → prints demo logins. `--force` skips confirmations (for CI); `--skip-migrate` runs env checks only. Refuses to silently continue past missing APP_KEY.
- `bootstrap/app.php` — explicit `->withCommands([__DIR__.'/../app/Console/Commands'])` so the command is registered unambiguously (Laravel 11 auto-discovers this dir anyway; being explicit documents intent).
- `DemoSeeder::run()` — APP_KEY helpful-error message expanded to include `php artisan optimize:clear` AND a pointer to `php artisan marketplace:setup-demo`.
- README, GETTING_STARTED, TROUBLESHOOTING — all updated to lead with the guided command.
- 3 new test scenarios (Phase 6 total: 28): command is registered as artisan; `--skip-migrate --force` returns 0 via `$this->artisan(...)`; reflection asserts command class shape + remedy strings; v7.1 helpful-error test extended to assert the new `optimize:clear` + guided-command substrings.
- New CI step `Phase 6 v7.2 — marketplace:setup-demo guided command works end-to-end` with 5 sub-checks (registered, full run exits 0 with demo output, all Phase 6 demo data present + credentials still encrypted, `--skip-migrate` works, DemoSeeder helpful error contains all 5 required strings).

**Not changed:** encryption, schema, services, Filament resources, controllers, React pages, routes, demo data, `.env.example`. Full v7.2 detail in `PHASE_6_v7.2_PATCH_NOTES.md`.

**Preferred local setup (Phase 6 v7.2+):**

```bash
php artisan marketplace:setup-demo
```

**Manual sequence still works:**

```bash
cp .env.example .env
php artisan key:generate
php artisan optimize:clear
php artisan migrate:fresh --seed
```

Final CI verdict is now `✅ Phase 6 v7.2 PASSES — ready to approve Phase 7`.

---

## v7.3 update — Inertia `usePage<>` SharedProps fix (frontend build hardening)

After v7.2's guided setup landed, the developer's `npm run build` failed with three `TS2344` errors on Phase 6 React pages. The pages had used local `interface PageProps { ... }` (or `type FlashProps = ...`) as the generic to `usePage<X>()`, but Inertia v2 constrains that generic to `T extends import('@inertiajs/core').PageProps` — augmented in `resources/js/types/inertia.d.ts` to extend `SharedProps` (`app`, `marketplace`, `auth`, `translations`, `cart_summary`, `flash`). Local types without those fields failed compile.

**Changes:**

- `Integrations/Index.tsx`, `Orders/Show.tsx`, `Products/Index.tsx` — `usePage<>` generics now use `SharedProps` (via `import type { SharedProps } from '@/types/inertia'`), either directly or as `SharedProps & PageSpecificProps`.
- `Orders/Index.tsx`, `Products/CsvImport.tsx`, `Products/Manual.tsx`, `Products/Map.tsx` — local `interface PageProps` renamed to page-specific names (`SupplierOrdersIndexProps`, `CsvImportPageProps`, `ManualPageProps`, `MapPageProps`) so they can never shadow the augmented global. (These didn't break the build but were name-shadowing hazards.)
- `.github/workflows/ci.yml` — new sub-check inside the frontend job (after `npm run build`) runs a Python validator over every `resources/js/Pages/Vendor/Supplier/**/*.tsx`. Asserts every `usePage<>()` generic uses `SharedProps` (directly or via extension) AND bans local `PageProps`/`FlashProps` interfaces/types in Phase 6 files. Belt-and-suspenders on top of the existing `npm run typecheck`.

**My own sandbox sweep was upgraded** because v7.2's offline `tsc` ran with `noResolve: true` — which silenced module-level constraint checking and let this slip through. v7.3's sandbox-side verification builds minimal type stubs for `@inertiajs/react`/`@inertiajs/core`/`react` and runs real `tsc --strict` with module resolution against all Phase 6 React files. Result: 0 TS2344 errors. The Python validator is the same code that ships as the new CI sub-check.

**Not changed:** all 7 Phase 6 migrations, 6 models, 3 services, 4 Filament resources, 3 vendor controllers, routes, RolesAndPermissionsSeeder, DemoSeeder, `bootstrap/app.php`, `MarketplaceSetupDemo.php`, all PHP tests, all v7.0/v7.1/v7.2 CI sub-checks. No business-logic file was modified. Full v7.3 detail in `PHASE_6_v7.3_PATCH_NOTES.md`.

Final CI verdict is now `✅ Phase 6 v7.3 PASSES — ready to approve Phase 7`.
