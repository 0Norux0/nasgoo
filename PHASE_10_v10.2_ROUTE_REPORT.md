# Phase 10 v10.2 — Route Report

The developer asked in §12: "Please provide the actual registered route names and URLs for: admin reports, vendor reports, sitemap, vendor order actions, secure file preview/download."

I cannot run `php artisan route:list` in the sandbox (no PHP runtime). What I CAN do is grep `routes/web.php` for the exact route definitions; the route names and URIs come straight from the source. The dev's `php artisan route:list` will produce equivalent output (sorted differently and with the resolved middleware names).

---

## Admin Reports

```
GET|HEAD   /admin/reports                            admin.reports.index           Admin\ReportsController@index
GET|HEAD   /admin/reports/export.csv                 admin.reports.export          Admin\ReportsController@exportOrdersCsv
```

**Middleware:** `web`, `auth` (route group)
**Additional authorization:** `$this->authorize('viewReports', User::class)` invoked inside the controller method (Gate registered in AppServiceProvider; checks Spatie `reports.view` permission). Customers and vendors get 403.

**Source:** `routes/web.php` lines ~370-372 (auth-only group at `Route::middleware(['auth'])->group(...)`)

```php
Route::middleware(['auth'])->group(function () {
    Route::get('/admin/reports',            [\App\Http\Controllers\Admin\ReportsController::class, 'index'])->name('admin.reports.index');
    Route::get('/admin/reports/export.csv', [\App\Http\Controllers\Admin\ReportsController::class, 'exportOrdersCsv'])->name('admin.reports.export');
    // ... admin.vendor-files.show also in this group, see below
});
```

**Filament navigation link to /admin/reports:** registered in `app/Providers/Filament/AdminPanelProvider.php` lines 52-68:
```php
->navigationItems([
    \Filament\Navigation\NavigationItem::make('Reports Dashboard')
        ->url('/admin/reports')
        ->icon('heroicon-o-chart-bar')
        ->group('Reports')
        ->sort(1)
        ->visible(fn (): bool => auth()->user()?->hasAnyRole(['super_admin', 'admin_staff']) ?? false)
        ->openUrlInNewTab(false),
])
```

The 'Reports' group is registered in `navigationGroups([...])` between 'Operations' and 'Access Control'.

---

## Vendor Reports

```
GET|HEAD   /vendor/reports                           vendor.reports.index          Vendor\VendorReportsController@index
GET|HEAD   /vendor/reports/export.csv                vendor.reports.export         Vendor\VendorReportsController@exportCsv
```

**Middleware:** `web`, `auth`, `vendor:approved` (route group)
**Additional authorization:** The middleware resolves the authenticated user's vendor from the database and sets it on `$request->attributes`. The controller reads from request attributes only — NEVER from a request param. This guarantees a vendor cannot pass `?vendor_id=N` and read another vendor's data.

**Source:** `routes/web.php` lines ~175-177 (inside `Route::middleware(['auth', 'vendor:approved'])->group(...)`)

```php
Route::middleware(['auth', 'vendor:approved'])->group(function () {
    // ... wallet, payouts, reviews ...
    Route::get('/vendor/reports',            [\App\Http\Controllers\Vendor\VendorReportsController::class, 'index'])->name('vendor.reports.index');
    Route::get('/vendor/reports/export.csv', [\App\Http\Controllers\Vendor\VendorReportsController::class, 'exportCsv'])->name('vendor.reports.export');
});
```

**VendorLayout navigation link to /vendor/reports:** `resources/js/Layouts/VendorLayout.tsx` baseItems (visible to every vendor user; route middleware enforces approval).

---

## Sitemap

```
GET|HEAD   /sitemap.xml                              public.sitemap                Public\SitemapController@index
GET|HEAD   /robots.txt                               public.robots                 Public\RobotsController@index
```

**Middleware:** `web` only (no auth — these are public)
**Source:** `routes/web.php` line 377 + 378 (at the end of the file, outside any auth group)

```php
Route::get('/sitemap.xml', [\App\Http\Controllers\Public\SitemapController::class, 'index'])->name('public.sitemap');
Route::get('/robots.txt',  [\App\Http\Controllers\Public\RobotsController::class, 'index'])->name('public.robots');
```

**Note on the dev's report:** if `/sitemap.xml` returns 404 in deployment, the issue is the web server, not Laravel. Nginx's `try_files` directive must include `/index.php?$query_string` as the fallback. See `TROUBLESHOOTING.md` "/sitemap.xml doesn't exist" section.

---

## Vendor Order Status Actions

```
POST       /vendor/orders/{order}/confirm            vendor.orders.confirm         Vendor\VendorOrderController@confirm
POST       /vendor/orders/{order}/ship               vendor.orders.ship            Vendor\VendorOrderController@ship
POST       /vendor/orders/{order}/deliver            vendor.orders.deliver         Vendor\VendorOrderController@deliver
```

**Middleware:** `web`, `auth`, `vendor:approved`
**Authorization:** Each method calls `OrderLifecycleService` which enforces the state transition rules (e.g. only paid orders can be shipped; only shipped orders can be delivered). The vendor can only update items where `order_items.vendor_id === $vendor->id`; aggregate parent order status is recalculated via `refreshFulfillment()` which uses `$order->load('items')` (Phase 9 v9.4 force-reload fix).

**Source:** `routes/web.php` lines ~93-96

```php
Route::middleware(['auth', 'vendor:approved'])->group(function () {
    // ...
    Route::post('/vendor/orders/{order}/ship',     [\App\Http\Controllers\Vendor\VendorOrderController::class, 'ship'])->name('vendor.orders.ship');
    Route::post('/vendor/orders/{order}/confirm',  [\App\Http\Controllers\Vendor\VendorOrderController::class, 'confirm'])->name('vendor.orders.confirm');
    Route::post('/vendor/orders/{order}/deliver', [\App\Http\Controllers\Vendor\VendorOrderController::class, 'deliver'])->name('vendor.orders.deliver');
});
```

**UI exposure (v10.1 fix):** inline action buttons on `/vendor/orders` list page (`resources/js/Pages/Vendor/Orders/Index.tsx`) with data-testid='row-{action}-{id}'. Conditional gating: Confirm shown for `status='pending_payment'|'paid'`; Ship shown for `payment_status='paid' AND fulfillment_status='unfulfilled'|'partially_shipped'`; Deliver shown for `fulfillment_status='shipped'|'partially_shipped'`.

---

## Secure File Preview / Download

```
GET|HEAD   /admin/vendor-files/{vendor}/{kind}       admin.vendor-files.show       Admin\VendorFileController@show
```

**Middleware:** `web`, `auth` (the route is inside the same `auth` group as `/admin/reports`)
**Defense in depth:**
1. The URL must be a signed URL — generated via `URL::temporarySignedRoute('admin.vendor-files.show', now()->addMinutes(30), [...])`. `$request->hasValidSignature()` is called at the top of the controller and aborts 403 on failure.
2. The authenticated user must have `super_admin` or `admin_staff` role (`hasAnyRole` check inside the controller).
3. The `{kind}` parameter is matched against an ALLOWED_KINDS allowlist (currently: `license_document`, `id_document`). Unknown kinds return 404.
4. The file is served via `Storage::disk('vendors')->response(...)` — the raw disk path is never exposed.

**Source:** `routes/web.php` lines ~373-375

```php
Route::middleware(['auth'])->group(function () {
    Route::get('/admin/reports',            [...]);
    Route::get('/admin/reports/export.csv', [...]);

    Route::get('/admin/vendor-files/{vendor}/{kind}', [\App\Http\Controllers\Admin\VendorFileController::class, 'show'])
        ->name('admin.vendor-files.show');
});
```

**Controller:** `app/Http/Controllers/Admin/VendorFileController.php` (~70 lines).

---

## All v10.x routes — full table

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

---

## How to confirm in the dev's environment

```bash
php artisan route:list --columns=method,uri,name,middleware

# Filter to v10.x routes:
php artisan route:list --columns=method,uri,name,middleware | grep -E "admin\.reports|admin\.vendor-files|vendor\.reports|vendor\.orders\.(ship|confirm|deliver)|public\.(sitemap|robots)"
```

If any of the routes in the table above are MISSING from the dev's `route:list` output, the routes file in the deployed environment is NOT the v10.2 routes/web.php. Re-extract the archive.

If the routes ARE in route:list but the URLs return 404 in the browser:
- For `/sitemap.xml` and `/robots.txt`: nginx misconfig (try_files fallback missing). See deployment guide §2.11.
- For other routes: check `php artisan route:cache` was rebuilt after v10.2 was extracted. `scripts/deploy.sh` does this.
