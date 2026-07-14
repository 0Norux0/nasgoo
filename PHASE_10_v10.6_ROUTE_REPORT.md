# Phase 10 v10.6 — Route Report

Routes touched by each of the 3 defects.

## Defect 1 — vendor file viewing

```
GET|HEAD  /admin/vendor-files/{vendor}/{kind}     admin.vendor-files.show
          web, auth, signed signature, admin role check (in controller)
          Admin\VendorFileController@show
```

Source: `routes/web.php:368-371`. Behavior change in v10.6: the disk it reads from (`config('marketplace.vendor_private_disk', 'vendors')`) is now configured in `config/filesystems.php`, so the controller no longer crashes when called.

## Defect 2 — vendor order status updates

```
POST  /vendor/orders/{order}/confirm     vendor.orders.confirm
POST  /vendor/orders/{order}/ship        vendor.orders.ship
POST  /vendor/orders/{order}/deliver     vendor.orders.deliver
      web, auth, vendor:approved
      Vendor\VendorOrderController@confirm|ship|deliver
```

Source: `routes/web.php:93-96`. Routes are unchanged in v10.6 — the fix is in the React dropdown handler that calls them.

## Defect 3 — no new routes (purely frontend + middleware)

The `top_categories` shared prop is set in `HandleInertiaRequests::share()` and applies to EVERY Inertia request. No new route added.

## How to verify in deployment

```bash
php artisan route:list --columns=method,uri,name,middleware | grep -E "admin\.vendor-files|vendor\.orders\.(ship|confirm|deliver)"
```

Expected output (4 lines):
```
GET|HEAD  admin/vendor-files/{vendor}/{kind}  admin.vendor-files.show  web,auth
POST      vendor/orders/{order}/ship          vendor.orders.ship       web,auth,vendor:approved
POST      vendor/orders/{order}/confirm       vendor.orders.confirm    web,auth,vendor:approved
POST      vendor/orders/{order}/deliver       vendor.orders.deliver    web,auth,vendor:approved
```
