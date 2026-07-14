# Phase 10 v10.3 — Route Report

Per dev §8: confirm `php artisan route:list` would show every relevant route. I cannot run `php artisan` in the sandbox; I CAN grep `routes/web.php` and show the exact route definitions.

## Routes relevant to the 5 v10.3 defects

### Defect 1 / Defect 4 — admin vendor document viewing

```
GET|HEAD  /admin/vendor-files/{vendor}/{kind}    admin.vendor-files.show
          web, auth + signed signature + admin role
          Admin\VendorFileController@show
```

Source: `routes/web.php` (inside `Route::middleware(['auth'])->group(...)`).

The Filament VendorResource (`app/Filament/Resources/VendorResource.php`) renders 4 `Forms\Components\Placeholder` controls (logo_view, banner_view, license_view, id_view). Each one's `->content()` callback calls `VendorFileLinks::previewHtml($record, $kind)`. For private kinds (license_document, id_document) the helper generates a `temporarySignedRoute('admin.vendor-files.show', now()->addMinutes(30), ...)` URL.

**v10.3 difference vs v10.2:** the Placeholder API calls are now valid Filament 3.x (`->extraAttributes` instead of removed `->disableLabel`). Same route + same controller + same VendorFileLinks helper.

### Defect 2 — vendor product create/update

```
POST       /vendor/products                       vendor.products.store
PATCH      /vendor/products/{product}             vendor.products.update
           web, auth, vendor:approved
           Vendor\VendorProductController@store/update
```

Source: `routes/web.php` (inside `Route::middleware(['auth', 'vendor:approved'])->group(...)`).

**v10.3 difference vs v10.2:** the controller is unchanged (v10.1 `unset($data['images'])` preserved at lines 120 + 195). But `Product::fill()` now has a model-level override that strips `images` from ANY mass-assignment, covering paths beyond these two routes (Filament admin product create, factories, future code).

### Defect 3 — vendor order status

```
POST       /vendor/orders/{order}/confirm         vendor.orders.confirm
POST       /vendor/orders/{order}/ship            vendor.orders.ship
POST       /vendor/orders/{order}/deliver         vendor.orders.deliver
           web, auth, vendor:approved
           Vendor\VendorOrderController@confirm/ship/deliver
```

Source: `routes/web.php` (inside `Route::middleware(['auth', 'vendor:approved'])->group(...)`).

**v10.3 difference vs v10.2:** routes unchanged. The new `vendor-order-status-dropdown` on the Show page posts to these existing routes based on the selected option.

### Defect 5 — no new routes; CSS-only fix

## All v10.x routes (from v10.2 route report — unchanged in v10.3)

| URI | Method | Name | Middleware | Controller |
|---|---|---|---|---|
| `/admin/reports` | GET | admin.reports.index | web, auth | Admin\ReportsController@index |
| `/admin/reports/export.csv` | GET | admin.reports.export | web, auth | Admin\ReportsController@exportOrdersCsv |
| `/admin/vendor-files/{vendor}/{kind}` | GET | admin.vendor-files.show | web, auth + signed + role | Admin\VendorFileController@show |
| `/vendor/reports` | GET | vendor.reports.index | web, auth, vendor:approved | Vendor\VendorReportsController@index |
| `/vendor/reports/export.csv` | GET | vendor.reports.export | web, auth, vendor:approved | Vendor\VendorReportsController@exportCsv |
| `/vendor/orders/{order}/confirm` | POST | vendor.orders.confirm | web, auth, vendor:approved | Vendor\VendorOrderController@confirm |
| `/vendor/orders/{order}/ship` | POST | vendor.orders.ship | web, auth, vendor:approved | Vendor\VendorOrderController@ship |
| `/vendor/orders/{order}/deliver` | POST | vendor.orders.deliver | web, auth, vendor:approved | Vendor\VendorOrderController@deliver |
| `/sitemap.xml` | GET | public.sitemap | web | Public\SitemapController@index |
| `/robots.txt` | GET | public.robots | web | Public\RobotsController@index |

## Dev verification command

```bash
php artisan route:list --columns=method,uri,name,middleware \
  | grep -E "admin\.reports|admin\.vendor-files|vendor\.reports|vendor\.orders\.(ship|confirm|deliver)|public\.(sitemap|robots)"
```

If any of the above routes are MISSING from the dev's `route:list` output, the routes file in deployment is NOT the v10.3 routes/web.php. Re-extract the archive.

If the routes ARE there but the URLs 404 in the browser → server config issue (route:cache, nginx try_files). See TROUBLESHOOTING.md.
