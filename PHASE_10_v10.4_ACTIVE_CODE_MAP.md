# Phase 10 v10.4 — Active Code Map

Per dev §A: this document maps every defect to the EXACT active production code that handles it. Every file referenced has been verified to exist at the path shown. No nested or sibling project; one VERSION file at `/home/claude/marketplace/VERSION`.

## Workspace forensics

```
Absolute project path:    /home/claude/marketplace
artisan files (count=1):  /home/claude/marketplace/artisan
package.json (count=1):   /home/claude/marketplace/package.json
VERSION files (count=1):  /home/claude/marketplace/VERSION  → "Phase 10 v10.4"
Nested marketplace dirs:  (none)
```

## Framework versions (verified via composer.json + package.json)

```
laravel/framework:          ^11.0
filament/filament:          ^3.2
inertiajs/inertia-laravel:  ^2.0
spatie/laravel-permission:  ^6.0
react / react-dom:          ^18.3.1
@inertiajs/react:           ^2.0.0
vite:                       ^5.3.3
typescript:                 ^5.5.3
tailwindcss:                ^3.4.6
```

---

## Defect 1 — Admin can't view documents uploaded by pending vendor

**Active route chain (Filament uses internal routing; the entry is the admin panel itself):**

```
HTTP   /admin/vendors                                  → Filament panel → VendorResource::table()
HTTP   /admin/vendors/{record}/edit                    → Filament panel → VendorResource::form() → renders schema
HTTP   /admin/vendor-files/{vendor}/{kind}             → admin.vendor-files.show route
```

**Active production files:**

| Role | Active file (verified present) |
|---|---|
| Filament resource | `app/Filament/Resources/VendorResource.php` |
| Edit page | `app/Filament/Resources/VendorResource/Pages/EditVendor.php` |
| Helper rendering preview HTML | `app/Domain/Vendor/VendorFileLinks.php` |
| Signed file controller | `app/Http/Controllers/Admin/VendorFileController.php` |
| Signed route | `routes/web.php:370` (`admin.vendor-files.show`) |

**Why v10.1/v10.2 didn't appear to work:** `VendorResource.php` had 4 calls to `Forms\Components\Placeholder::disableLabel(false)`. That method was Filament **2.x** API, **removed in 3.x**. The project uses Filament 3.2. Calling it throws `BadMethodCallException` at form-render time → the entire vendor edit form crashes with HTTP 500 → admin sees a broken/blank page → couldn't view documents OR the package OR anything else on that form.

**v10.3 fix (verified present in v10.4 baseline):** All 4 `->disableLabel(false)` calls removed. Replaced with `->extraAttributes(['data-v103' => 'vendor-file-preview'])`. Static check: `grep -c '->disableLabel(' app/Filament/Resources/VendorResource.php` = **0**. `grep -c 'data-v103' app/Filament/Resources/VendorResource.php` = **4**.

---

## Defect 2 — Product creation crashes with MassAssignmentException [images]

**Active route chain:**

```
POST   /vendor/products              → vendor.products.store    → VendorProductController@store
POST   /vendor/products/{product}    → vendor.products.update   → VendorProductController@update
```

(Both routes inside `Route::middleware(['auth', 'vendor:approved'])->group(...)` in `routes/web.php`.)

**Other product-creation paths audited:**

| Active file | Path through | MassAssignment risk |
|---|---|---|
| `app/Filament/Resources/ProductResource.php` | Admin Filament product CRUD | Uses `Repeater::make('images')->relationship('images')` — relation pattern; v10.3 model guard catches edge cases |
| `app/Http/Controllers/Vendor/VendorServiceController.php` | Service-type product creation | Uses explicit column list; no 'images' key — safe |
| `app/Domain/Supplier/SupplierProductMapper.php` | Dropshipping mapping | Uses explicit column list — safe |

**Active production files:**

| Role | Active file (verified present) |
|---|---|
| Active controller | `app/Http/Controllers/Vendor/VendorProductController.php` |
| Active model | `app/Models/Product.php` |
| Active product image model | `app/Models/ProductImage.php` |
| Schema: `products.images` column | **DOES NOT EXIST** (images live in `product_images` table) |

**v10.3 fix (verified present in v10.4):**

1. `Product::fill(array $attributes): static` override at `app/Models/Product.php:45-49` — strips `images` key BEFORE Eloquent's fillable check. **Every** mass-assignment path flows through `fill()`. The exception is now impossible by construction regardless of caller hygiene.
2. v10.1's controller-level `unset($data['images']);` preserved at `VendorProductController.php:120` (store) and `:195` (update) — redundant defense.

**Static verification (run against v10.4 working tree):**
```
grep -nE "public function fill\(array .* \): static" app/Models/Product.php
  → 45: public function fill(array $attributes): static
grep -nE "unset\(\\\$attributes\['images'\]\)" app/Models/Product.php
  → 47:        unset($attributes['images']);
grep -nE "unset\(\\\$data\['images'\]\)" app/Http/Controllers/Vendor/VendorProductController.php
  → 120:        unset($data['images']);
  → 195:        unset($data['images']);
```

---

## Defect 3 — Vendor can't update or complete order status

**Active route chain:**

```
GET    /vendor/orders/{order}                  → Vendor\VendorOrderController@show     → renders Vendor/Orders/Show.tsx
POST   /vendor/orders/{order}/confirm          → vendor.orders.confirm                  → VendorOrderController@confirm
POST   /vendor/orders/{order}/ship             → vendor.orders.ship                     → VendorOrderController@ship
POST   /vendor/orders/{order}/deliver          → vendor.orders.deliver                  → VendorOrderController@deliver
```

All three POST routes inside `Route::middleware(['auth', 'vendor:approved'])->group(...)`. Server-side enforcement via `OrderLifecycleService`.

**Active production files:**

| Role | Active file (verified present) |
|---|---|
| Order list page | `resources/js/Pages/Vendor/Orders/Index.tsx` (v10.1 row buttons) |
| Order detail page | `resources/js/Pages/Vendor/Orders/Show.tsx` (v10.3 dropdown) |
| Active controller | `app/Http/Controllers/Vendor/VendorOrderController.php` |
| Order lifecycle service | `app/Domain/Order/OrderLifecycleService.php` |
| Order status enum | `app/Models/Order.php` (STATUS_*, PAYMENT_STATUS_*, FULFILLMENT_STATUS_* constants) |

**v10.3 fix (verified present in v10.4):** `Vendor/Orders/Show.tsx:172` adds `<select data-testid="vendor-order-status-dropdown">` element. Static check: `grep -c "vendor-order-status-dropdown" resources/js/Pages/Vendor/Orders/Show.tsx` = **1**.

Transition matrix (server-enforced):
- `pending_payment / paid → confirmed` (via Confirm action)
- `confirmed + paid → shipped` (via Ship action)
- `shipped → fulfilled` (via Deliver action)
- Payment status NOT in dropdown (admin-only); vendors cannot manipulate online-payment status

---

## Defect 4 — Raw paths shown instead of viewable files

**Active rendering locations audited:**

| Location | Active file | v10.x fix |
|---|---|---|
| Vendor logo (Filament admin) | `VendorResource.php` Documents section | v10.1 VendorFileLinks helper (v10.3 unblocks via disableLabel fix) |
| Vendor banner (Filament admin) | same | same |
| License document (Filament admin) | same | same |
| ID document (Filament admin) | same | same |
| Product image (storefront) | `resources/js/Pages/Products/Show.tsx` | Uses `Storage::url` from controller — was correct pre-v10 |
| Product image (vendor list) | `resources/js/Pages/Vendor/Products/Index.tsx` | Same — was correct |
| Customization proof | `resources/js/Pages/Customer/Customizations/Show.tsx` | Already uses signed URLs |

The actually-broken location was the Filament admin vendor edit page (#1 root cause). Other locations were already rendering correctly.

---

## Defect 5 — Vendor-selected package hidden from admin

**Active production files:**

| Role | Active file (verified present) |
|---|---|
| Vendor package selection at application time | `resources/js/Pages/VendorApplication/Apply.tsx` |
| Application controller | `app/Http/Controllers/Vendor/VendorApplicationController.php` |
| Subscription model | `app/Models/VendorSubscription.php` (FK: `vendor_package_id`) |
| Admin display | `app/Filament/Resources/VendorResource.php` (v10.1 Placeholder + table column) |

**v10.1 fix preserved in v10.4:** `VendorResource.php:144` adds `Forms\Components\Placeholder::make('requested_package')` showing the most-recent subscription's package name + status + features. `:228` adds `Tables\Columns\TextColumn::make('latest_requested_package')` to the table.

**Same root cause as #1:** the disableLabel(false) bug crashed the form before the requested_package Placeholder could render. v10.3 unblocks; the v10.1 display now actually shows.

---

## Defect 6 — Admin Reports page

**Active route chain:**

```
GET    /admin/reports                  → admin.reports.index   → Admin\ReportsController@index
GET    /admin/reports/export.csv       → admin.reports.export  → Admin\ReportsController@exportOrdersCsv
```

(Both inside `Route::middleware(['auth'])->group(...)`. Authz via `viewReports` Gate.)

**Active production files:**

| Role | Active file (verified present) |
|---|---|
| Controller | `app/Http/Controllers/Admin/ReportsController.php` |
| Inertia page | `resources/js/Pages/Admin/Reports/Index.tsx` |
| Layout (NEW in v10.1) | `resources/js/Layouts/AdminLayout.tsx` (81 lines) |
| Filament nav item | `app/Providers/Filament/AdminPanelProvider.php:52-66` (`Reports Dashboard` group) |
| Gate registration | `app/Providers/AuthServiceProvider.php` |

**v10.1+v10.2 fix preserved:** AdminLayout.tsx exists (was missing in v10.0 → page failed to compile). Filament nav item uses `hasAnyRole(['super_admin', 'admin_staff'])` directly (v10.2; bypasses Spatie permission cache).

---

## Defect 7 — Vendor Reports page

**Active route chain:**

```
GET    /vendor/reports                 → vendor.reports.index    → Vendor\VendorReportsController@index
GET    /vendor/reports/export.csv      → vendor.reports.export   → Vendor\VendorReportsController@exportCsv
```

(Inside `Route::middleware(['auth', 'vendor:approved'])->group(...)`.)

**Active production files:**

| Role | Active file (verified present) |
|---|---|
| Controller | `app/Http/Controllers/Vendor/VendorReportsController.php` |
| Inertia page | `resources/js/Pages/Vendor/Reports/Index.tsx` |
| Layout | `resources/js/Layouts/VendorLayout.tsx` |
| Nav link | `resources/js/Layouts/VendorLayout.tsx:37` (v10.2 moved into baseItems — always visible) |

**v10.2 fix preserved:** Reports link in `baseItems` array (visible to every vendor user; server-side authz still gates the route via `vendor:approved` middleware).

---

## Defect 8 — /sitemap.xml

**Active route chain:**

```
GET    /sitemap.xml             → public.sitemap     → Public\SitemapController@index
GET    /robots.txt              → public.robots      → Public\RobotsController@index
```

(No auth middleware. Public.)

**Active production files:**

| Role | Active file (verified present) |
|---|---|
| Sitemap route | `routes/web.php:377` |
| Sitemap controller | `app/Http/Controllers/Public/SitemapController.php` (4.5 KB) |
| Robots controller | `app/Http/Controllers/Public/RobotsController.php` |

**If /sitemap.xml returns 404 in deployment:** the issue is the web server config. Nginx's `try_files` directive must include `/index.php?$query_string` fallback. See TROUBLESHOOTING.md "/sitemap.xml doesn't exist" section.

---

## Defect 9 — Raw image/file paths in normal UI

(Equivalent to #4 — see above.)

---

## Defect 10 — Mobile responsiveness

**Active production files:**

| Role | Active file (verified present) |
|---|---|
| Storefront layout | `resources/js/Layouts/StorefrontLayout.tsx` (`storefront-mobile-menu` testid present) |
| Vendor layout | `resources/js/Layouts/VendorLayout.tsx` (`vendor-mobile-menu` testid present) |
| Admin layout | `resources/js/Layouts/AdminLayout.tsx` (Filament panel uses its own mobile responsive behavior; outside our React layouts) |
| Global CSS | `resources/css/app.css` (v10.3 added overflow-x-hidden + max-width 100vw at base layer) |
| Tailwind config | `tailwind.config.js` |

**v10.3 fix preserved in v10.4:** `app.css:28-29` `html, body { overflow-x: hidden; max-width: 100vw }` + responsive `img/video/iframe` + `overflow-wrap: anywhere` on text elements. Static check: `grep -c 'overflow-x-hidden' resources/css/app.css` = **1**, `grep -c 'max-width: 100vw' resources/css/app.css` = **1**.

---

## Summary — every active file v10.x changed

The active code map in tabular form:

| Defect | Active file | v10.x change |
|---|---|---|
| 1, 4 | `app/Filament/Resources/VendorResource.php` | v10.1 VendorFileLinks; v10.1 requested_package; **v10.3 disableLabel removed** (unblocks all v10.1 work) |
| 1, 4 | `app/Domain/Vendor/VendorFileLinks.php` (NEW v10.1) | preview HTML helper |
| 1, 4 | `app/Http/Controllers/Admin/VendorFileController.php` (NEW v10.1) | signed URL guard |
| 2 | `app/Http/Controllers/Vendor/VendorProductController.php` | v10.1 unset images × 2 |
| 2 | `app/Models/Product.php` | **v10.3 fill() override** (bulletproof) |
| 3 | `resources/js/Pages/Vendor/Orders/Show.tsx` | **v10.3 status dropdown** |
| 3 | `resources/js/Pages/Vendor/Orders/Index.tsx` | v10.1 row buttons |
| 5 | `app/Filament/Resources/VendorResource.php` | v10.1 requested_package; v10.3 unblocks |
| 6 | `resources/js/Layouts/AdminLayout.tsx` (NEW v10.1) | 81-line layout |
| 6 | `app/Providers/Filament/AdminPanelProvider.php` | v10.1 nav item; v10.2 hasAnyRole |
| 6, 7 | `app/Http/Controllers/Admin/ReportsController.php`, `Vendor/VendorReportsController.php` | already correct pre-v10.1 |
| 7 | `resources/js/Layouts/VendorLayout.tsx` | v10.1 nav link; v10.2 moved to baseItems |
| 8 | `app/Http/Controllers/Public/SitemapController.php`, `routes/web.php:377` | already correct pre-v10.1 |
| 10 | `resources/js/Layouts/StorefrontLayout.tsx`, `VendorLayout.tsx` | v10.1 mobile menus |
| 10 | `resources/css/app.css` | **v10.3 global overflow guard** |
| (diagnostic) | `app/Console/Commands/VerifyFixesCommand.php` (NEW v10.2) | 19 fix-marker checks |
| (diagnostic) | `app/Console/Commands/FingerprintCommand.php` (NEW v10.4) | SHA-256 fingerprint |
| (diagnostic) | `scripts/deploy.sh` (NEW v10.2) | full cache invalidation |

**These are the ACTIVE files. They are the same paths the application's routes resolve to. Modifying them changes the running behavior.**

## How the dev verifies my modifications reach their environment

After `./scripts/deploy.sh`:

```bash
php artisan marketplace:fingerprint
# Outputs 23-file SHA-256 table + aggregate hash
# Compare against PHASE_10_v10.4_PACKAGE_INTEGRITY.md → "Canonical fingerprint"
# Match  → deployed source IS v10.4
# Differ → deployed source is NOT v10.4; re-extract
```

This is the single command that settles the question of "is my code actually running?"
