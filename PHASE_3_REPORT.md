# Phase 3 — Product Marketplace / Catalog

**Status:** Complete. Built on verified Phase 2 v3.3 base.
**Scope discipline:** Phase 3 only — cart/checkout/orders/payments/reviews remain untouched and ship in Phase 4+.

---

## What Phase 3 delivers

| Domain | Deliverable |
|---|---|
| Schema | 7 new tables: `categories`, `attributes`, `attribute_values`, `products`, `product_variants`, `product_images`, `category_product`, plus `product_attribute_value` pivot |
| Catalog browse | `/products` with category filter, name search (case-insensitive ILIKE), four sort options, 24/page pagination |
| Product detail | `/products/{slug}` with gallery, variant display, spec table, breadcrumb |
| Vendor area | Product CRUD list/create/edit/delete, image upload, draft→submit workflow, package-limit enforcement, status banner per state |
| Admin (Filament) | CategoryResource, AttributeResource, ProductResource with approve/reject/archive row actions and pending-count nav badge |
| Commission | `CommissionResolver` now resolves product- and category-scoped rules with proper specificity ordering (Phase 2 stub complete) |
| Storefront chrome | New "Products" link in header, featured-products preview on Welcome, "Products" nav added to vendor layout |
| Vendor storefront | `/vendors/{slug}` now lists vendor's published products (up to 24) |
| i18n | 33 new keys × 3 locales (95 total per locale, real Arabic + Urdu translations) |
| Tests | 37 new scenarios across 5 new test files (150 total, up from 113) |
| CI | Phase 3 smoke check verifies seeded categories + attributes + color values |
| Docs | This report, updated README, PHASE_3_v4.0_README.md |

---

## Schema (verbatim from migrations on disk)

### `2026_01_03_000001_create_categories_table.php`
Hierarchical (`parent_id` self-FK + computed `depth` + `path`), translation JSON, soft deletes, denormalised `products_count`. Indexed on `(parent_id, position)` and `(is_active, position)`.

### `2026_01_03_000002_create_attributes_tables.php`
- `attributes` — type select/text/number/boolean, `is_filterable` and `is_variation` flags
- `attribute_values` — with `color_hex` for color swatches, position-ordered

### `2026_01_03_000003_create_products_table.php`
Main catalog table. Notable columns:
- `vendor_id` (FK), `category_id` (FK nullable — primary category)
- `slug` (globally unique), `name`, `name_translations`, `description`, `description_translations`
- `type` — simple | variable | digital
- `status` — draft | pending_review | published | rejected | archived (lifecycle)
- `approved_at`, `approved_by`, `published_at`, `rejection_reason`
- Pricing in minor units (`price_minor`, `compare_at_price_minor`, `cost_price_minor`, `currency`)
- Inventory (`track_stock`, `stock`)
- Storefront flags (`featured`, `featured_until`)
- SEO (`meta_title`, `meta_description`)
- Denormalised counters (`views_count`, `sales_count`, `rating_avg`, `rating_count`)
- Soft deletes
- Indexed on `(vendor_id, status)`, `(category_id, status)`, `(status, published_at)`, `(featured, featured_until)`
- Unique compound `(vendor_id, sku)` so vendors can reuse SKUs only across vendors

Plus `category_product` pivot for products that should appear in extra categories.

### `2026_01_03_000004_create_product_variants_and_images.php`
- `product_variants` — per-variant price/stock/attribute_values JSON, soft deletes
- `product_images` — `variant_id` nullable (images can be tied to a variant or just the parent product); `is_primary` for the gallery cover
- `product_attribute_value` pivot

---

## Domain layer

**`app/Domain/Product/ProductPublishingService.php`** — only path to status transitions:
- `submitForReview()` — draft/rejected → pending_review (clears rejection_reason)
- `publish()` — pending_review → published, stamps approved_at/approved_by/published_at, increments category `products_count`, audit-logged inside DB transaction
- `reject($reason)` — sets rejected + stores reason
- `archive()` — sets archived, decrements category counter if was previously published
- `restoreToDraft()` — for archived → draft re-edit

**`app/Domain/Commission/CommissionResolver.php`** — rewritten from Phase 2 stub:
- Specificity order locked in `SCOPE_ORDER` constant: product → category → vendor → package → global
- Pulls candidates with scope-aware WHERE clauses keyed off `context['product_id'/'category_id'/'package_id']`
- PHP-side sort by `(scope_rank, priority, id desc)` for portable behavior across DB drivers
- `forProduct($product, $paymentMethod)` convenience method — most common call site

---

## Permissions (added to `RolesAndPermissionsSeeder`)

New permission keys: `products.view`, `products.create`, `products.update`, `products.delete`, `products.publish`, `products.feature`, `categories.manage`, `attributes.manage`. The `customer` role already had `products.view` from Phase 1.

Default role mappings:
- super_admin → all (via `Gate::before`)
- admin_staff → all `products.*`, `categories.manage`, `attributes.manage`
- vendor → `products.view`, `products.create`, `products.update`, `products.delete` (own products only — enforced by `ProductPolicy::update/delete` checking status + ownership)
- customer → `products.view`

---

## Routes (Phase 3 additions)

```
# Public
GET  /products                          catalog.index   CatalogController@index
GET  /products/{slug}                   catalog.show    CatalogController@show

# Vendor (auth + vendor:approved middleware)
GET    /vendor/products                 vendor.products.index
GET    /vendor/products/create          vendor.products.create
POST   /vendor/products                 vendor.products.store
GET    /vendor/products/{product}/edit  vendor.products.edit
POST   /vendor/products/{product}       vendor.products.update
DELETE /vendor/products/{product}       vendor.products.destroy
POST   /vendor/products/{product}/submit vendor.products.submit
```

Admin product/category/attribute management is via Filament resources under the `Catalog` nav group.

---

## Tests — 37 new scenarios

| File | Scenarios |
|---|---|
| `tests/Feature/CategoryAndAttributeSeedTest.php` | 6 — top-level count, child depth/path, attribute count + value count, color hex, translatedName fallback, translatedValue fallback |
| `tests/Feature/VendorProductCrudTest.php` | 8 — list own only, unapproved blocked, create as draft, package limit (Basic=30) enforced, edit only when draft/rejected, delete only drafts, submit refused with no price/no images, foreign vendor returns 404 |
| `tests/Feature/ProductPublishingTest.php` | 8 — draft→pending, rejected→pending clears reason, publish sets approver + category counter, no-category safe, reject stores reason, archive decrements counter for published only, draft archive does NOT decrement, service allows admin publish from draft |
| `tests/Feature/ProductCatalogTest.php` | 10 — index renders, published-only visibility, category filter, ILIKE search, pagination 24/page, price_asc/desc sort, detail page success, 404 for non-published statuses, views_count increment, category list with counts |
| `tests/Feature/CommissionResolverProductScopeTest.php` | 5 — product wins over all other scopes, category fallthrough, vendor fallthrough, null when no rule, `is_active=false` respected |

**Total project test count:** 150 scenarios in 24 files (up from 113 / 19 at end of Phase 2 v3.3).

---

## CI — Phase 3 Verification

Workflow renamed `Phase 3 Verification`. Final verdict line: **`✅ Phase 3 PASSES — ready to approve Phase 4`**.

New smoke checks in the tinker post-seed verification:
- ≥5 top-level categories seeded
- ≥4 attributes (Color, Size, Brand, Material) seeded
- ≥5 color values seeded with hex codes

All v3.3 asset checks retained (Filament published, Vite manifest exists, no 419, admin/customer/vendor separation working).

---

## Manual checklist — walk top-to-bottom after applying

| # | Action | Expected |
|---|---|---|
| 1 | Run migrations + seed | New categories table populated with Electronics, Fashion, Home & Living, Beauty, Sports (5 top-level + 8 children); 4 attributes with 14 total values |
| 2 | Visit `/products` while logged out | Empty state ("No products yet…") with the 5 top categories listed in the sidebar |
| 3 | Sign in as `vendor@marketplace.test`, click "Products" in the vendor header | Vendor products list shows "0 of 30 products used" + "+ New product" button enabled |
| 4 | Click "+ New product", fill the form (name + price + stock + 1 image), Save | Lands on edit page with "Draft" status banner |
| 5 | Click "Submit for review" | Status banner switches to "Pending review", editing locked |
| 6 | Sign out, sign in at `/admin/login` as `admin@marketplace.test`, go to Catalog → Products | Pending count badge shows "1" next to Products in the sidebar; the new product appears with the `pending_review` filter applied by default |
| 7 | Click row "Publish" | Confirmation prompt, then product flips to `published` with toast |
| 8 | Sign out, visit `/products` | The product is now visible. Click into it → `/products/{slug}` shows gallery + price + spec table |
| 9 | Visit `/products?category=electronics` | Only electronics products listed (or empty state if none) |
| 10 | Visit `/products?q=<part-of-product-name>` | ILIKE search returns the product |
| 11 | Visit `/products?sort=price_desc` | Sorted high→low |
| 12 | Try `/products/some-nonexistent-slug` | 404 |
| 13 | Switch language to العربية → reload `/products` | Product browse labels translate, sidebar category names show Arabic translations from the seed (e.g. "إلكترونيات") |
| 14 | As admin, archive the published product | It disappears from `/products` and the category `products_count` decreases |

---

## Known limitations (intentional — Phase 3 only)

| Item | Will ship in |
|---|---|
| Cart, checkout, orders | Phase 4 |
| Payment processing | Phase 4 |
| Reviews & ratings | Phase 5 |
| Wishlist | Phase 5 |
| Meilisearch index sync | Phase 5 (catalog uses Postgres ILIKE for now) |
| Faceted attribute filters in catalog | Phase 5 (categories + sort + search only at this stage) |
| Bulk vendor product import (CSV) | Phase 5 |
| Inventory ledger / stock-movement history | Phase 6 |
| Product customization fields (text/image/file uploads on add-to-cart) | Phase 7 |
| Service booking products | Phase 8 |
| Dropship integrations | Phase 9 |
| Print-on-demand | Phase 10 |

The Filament admin chrome is still English-only by design — the marketplace storefront is multilingual; admin localization is a future polish item, not on the roadmap.

---

## Stop discipline

Phase 4 (Cart, Checkout, Orders) is **not** started. The schema, models, controllers, and routes for it do not exist on disk. Reply **"approve Phase 4"** after CI is green and the manual checklist passes. If anything in Phase 3 looks wrong, send the failing step + screenshot/log and I'll ship a targeted Phase 3 patch — I will not start Phase 4 until you confirm.
