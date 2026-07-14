# Phase 6 — Dropshipping / Supplier Product Import

**Status:** Built. Awaiting CI verification (only true gate).
**Version:** v7.0 (first cut of Phase 6 — no patch number yet)
**Prior baseline:** Phase 5 v6.4 (dev-approved)

---

## What this phase adds

A dropshipping/supplier-import foundation. Vendors can register supplier platforms (AliExpress, Temu, Daraz, Amazon, Alibaba, local wholesale, private suppliers), create per-platform integrations with encrypted credentials, import supplier products manually or via CSV, map them into marketplace listings for admin approval, and track auto-generated supplier orders through a status state machine when customers buy them.

**Compliance (spec note 14):** NO scraping. The phase supports manual entry, CSV import, supplier URL reference, and an API-ready schema foundation. No code in this release fetches data from external supplier platforms.

---

## What changed

### 7 new migrations (`database/migrations/2026_01_06_*`)
| # | Migration | Purpose |
|---|---|---|
| 1 | `create_supplier_platforms_table` | Admin-managed catalogue of supplier platforms |
| 2 | `create_supplier_integrations_table` | Per-vendor platform configs; credentials encrypted |
| 3 | `create_supplier_products_table` | Imported supplier product records (pre-mapping) |
| 4 | `create_supplier_orders_table` (+ `supplier_order_events`) | Auto-created when customers buy dropship products |
| 5 | `create_supplier_product_imports_table` | CSV batch records with per-row error reports |
| 6 | `add_dropshipping_fields_to_products_table` | `supplier_product_id`, `supplier_platform_id`, `supplier_cost_minor`, `fulfillment_mode`, `estimated_delivery_days` |
| 7 | `add_supplier_fields_to_order_items_table` | `supplier_order_id`, `supplier_cost_minor` snapshot |

### 6 new models + 3 extended
- New: `SupplierPlatform`, `SupplierIntegration` (encrypted credentials cast + `maskedCredentials()` helper), `SupplierProduct`, `SupplierOrder`, `SupplierOrderEvent`, `SupplierProductImport`
- Extended: `Product` (added `TYPE_DROPSHIP` constant, `FULFILLMENT_*` constants, supplier relations, `isDropship()` helper); `OrderItem` (added `supplier_order_id` + `supplier_cost_minor` snapshot, `supplierOrder()` relation); `Vendor` (added `supplierIntegrations`, `supplierProducts`, `supplierOrders`, `supplierImports` relations)

### 3 new domain services (`app/Domain/Supplier/`)
- `SupplierProductImporter` — `importManual()` + `importCsv($rows, dryRun:true)` with per-row validation and error report saved on `supplier_product_imports`
- `SupplierProductMapper` — `map()` (vendor turns supplier product into pending marketplace Product, enforces price ≥ cost), `publish()` (admin approval), `reject()`
- `DropshipOrderCreator` — `createFromOrder()` groups dropship items by `(vendor_id, supplier_platform_id)` into one `supplier_order` per group with cost snapshot; `transition()` for the status state machine + audit event log

### 4 new Filament admin resources (`app/Filament/Resources/`)
- `SupplierPlatformResource` — full CRUD
- `SupplierIntegrationResource` — list/view/edit with credentials displayed masked via `maskedCredentials()`
- `SupplierProductResource` — `approve` (visible when status=mapped) and `reject` (visible on pending|mapped) row actions calling the mapper service
- `SupplierOrderResource` — `mark_placed`/`mark_shipped`/`mark_delivered` row actions with v6.4-style visibility predicates; `getEloquentQuery()` eager-loads `['vendor', 'platform', 'order:id,number', 'orderItems', 'events.actor:id,name']` to prevent lazy-load under strict mode

### 3 new vendor controllers + 14 new routes
- `VendorSupplierProductController` — list / manual entry / CSV import (with dry-run) / mapping / import-report
- `VendorSupplierOrderController` — list / detail / supplier-reference + tracking update / status transition
- `VendorSupplierIntegrationController` — list / store / update / destroy with encrypted credentials

All scoped via `SupplierProduct::forVendor()` / `SupplierOrder::forVendor()` at the query layer. Cross-vendor access is impossible by route.

### 8 new React vendor pages (`resources/js/Pages/Vendor/Supplier/`)
- `Products/Index.tsx`, `Products/Manual.tsx`, `Products/CsvImport.tsx`, `Products/ImportReport.tsx`, `Products/Map.tsx`
- `Orders/Index.tsx`, `Orders/Show.tsx`
- `Integrations/Index.tsx`

### Navigation
`resources/js/Layouts/VendorLayout.tsx` — added two links inside `isApprovedVendor`: **Suppliers** (→ `/vendor/supplier-products`) and **Supplier Orders** (→ `/vendor/supplier-orders`)

### Permissions catalogue (`database/seeders/RolesAndPermissionsSeeder.php`)
**4 new top-level module keys, 13 new permissions.** Module keys are distinct (`supplier_platforms`, `supplier_integrations`, `supplier_products`, `supplier_orders`) — no overlap with existing modules, so no risk of the v6.3 duplicate-array-key bug class. Vendor role gets the self-management subset (no `supplier_products.approve` / `supplier_products.reject` / `supplier_platforms.manage`). super_admin gets all.

### CheckoutService integration
`app/Domain/Order/CheckoutService.php` — `OrderItem::create()` now snapshots `supplier_cost_minor` for dropship lines (null for non-dropship); after items are persisted and order totals updated, calls `app(DropshipOrderCreator::class)->createFromOrder($order)` which groups dropship items by `(vendor, supplier_platform)` into one `supplier_order` each.

---

## CSV format

Header row required. Required fields marked with `*`.

| Column | Type | Notes |
|---|---|---|
| `title`* | string | Supplier product title |
| `description` | text | Optional description |
| `supplier_sku` | string | Supplier-side SKU |
| `source_url` | URL | Link to the source listing (no scraping; for reference only) |
| `supplier_cost`* | decimal | Major units (e.g. `8.50` for $8.50) — converted to minor units internally |
| `currency` | 3-letter ISO | Defaults to the platform's default currency |
| `stock_quantity` | integer | Optional |
| `stock_status` | enum | `in_stock` / `out_of_stock` / `unknown` |
| `image_url` | URL or list | Single URL, or multiple separated by `\|` or `,` |
| `estimated_delivery_days` | integer | 0-365 |
| `external_product_id` | string | Optional supplier-side product ID |

**Workflow:** upload with **dry run** checked first to validate; if zero errors, re-upload with dry run unchecked to commit.

---

## Phase 6 manual checklist (10 steps)

After CI is green, run on a fresh codespace:

```bash
php artisan migrate:fresh --seed
```

1. Log in as `admin@marketplace.test` / `password`. Visit `/admin/supplier-platforms` — confirm 7 platforms (AliExpress, Alibaba, Amazon, Temu, Daraz, Local Wholesale, Private Supplier) appear with correct integration types and currencies.
2. Visit `/admin/supplier-integrations`. Confirm the demo `AliExpress demo catalogue` row appears for Demo Trading Co. Open it — credentials field shows masked `api_key: ••••1234` etc., not plaintext.
3. Visit `/admin/supplier-products`. Confirm 3 demo supplier products (one `pending`, one `mapped`, one `published`). Open the `mapped` one and click **Approve** — its status moves to `published` and the linked marketplace product becomes published.
4. Visit `/admin/supplier-orders`. Confirm one supplier order in `pending` status linked to the `DEMO-DROPSHIP-*` customer order.
5. Log in as `vendor@marketplace.test` / `password`. Visit `/vendor/supplier-products` — confirm only the demo vendor's supplier products appear, with **Map →** link on the `pending` one.
6. Visit `/vendor/supplier-products/csv`. Upload a small CSV (use the format above) with **Dry run** checked — confirm error reporting works on a deliberately broken row.
7. Click **Map →** on the pending demo supplier product. Enter a selling price below the supplier cost — confirm the form rejects with a clear error. Enter a valid price (≥ cost) and submit — marketplace product is created in `pending_review` status.
8. Visit `/vendor/supplier-orders`. Click into the demo supplier order. Set a supplier reference + tracking number, save. Walk the status from `pending` → `placed` → `shipped` → `delivered` — confirm each transition appears in the event log.
9. Log in as `customer@marketplace.test` / `password`. Browse the storefront and find **LED Desk Lamp** (the published dropshipping product). Add to cart, check out (COD or transfer). Confirm a new supplier order appears in the vendor's `/vendor/supplier-orders` immediately after checkout.
10. Visit `/vendor/supplier-integrations`. Click **+ New integration**. Pick a platform, set integration type to `api`, enter an API key and secret. Save. Confirm the credentials show masked (e.g. `••••5678`) and never plaintext on the list page.

---

## Known limitations

- **No live API integrations** — `integration_type=api` only persists encrypted credentials; no provider-specific adapter is wired up. Add adapters under `app/Domain/Supplier/Adapters/` in a future phase.
- **No webhook ingestion** — supplier-side status changes (shipped, delivered) must be entered manually by vendor or admin.
- **CSV parsing uses PHP's built-in `fgetcsv`** to avoid adding a dependency. Files >2MB are rejected at upload; raise the limit in `csvImport()` validation if needed.
- **Currency conversion is not yet applied to supplier costs** — the cost is stored in the supplier's currency (`supplier_currency` on `supplier_products`) and the snapshot on `order_items.supplier_cost_minor` uses the marketplace order currency at the time of mapping. Vendors should set prices in their selling currency directly.
- **Refunds + returns for dropshipping** orders are not yet plumbed end-to-end — `supplier_orders.status` supports `refunded` but the customer-side refund flow lives in Phase 7+.

---

## Compliance reminder

> Do not build illegal scraping from Amazon, Temu, Daraz, AliExpress, or any other platform.

Phase 6 supports manual entry, CSV import, supplier URL reference (as data only — never fetched), and an API-ready schema. No code reaches out to third-party platforms. Real API adapters can be added later only where the supplier explicitly authorizes API use.

---

## Next phase recommendation

After Phase 6 is verified, candidates for Phase 7:
- Multi-currency display + checkout (proper FX conversion against supplier cost)
- Service booking system (existing `services` permission grants are placeholders)
- Vendor analytics + reports
- Notification dispatcher (email/SMS) for supplier order status changes
