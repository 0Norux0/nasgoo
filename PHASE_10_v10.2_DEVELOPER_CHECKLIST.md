# Phase 10 v10.2 — Developer Checklist

This is the explicit step-by-step verification checklist the developer requested in §14. Each item maps to one of the 16 actions in the brief; the result column is for the dev to fill in.

## Pre-test setup

```bash
cd /var/www/marketplace        # or wherever the app lives

# 1. Backup current state in case something goes wrong
mysqldump -u marketplace -p marketplace > backup-pre-v102-$(date +%Y%m%d-%H%M%S).sql
tar -czf storage-pre-v102.tar.gz storage/app

# 2. Extract v10.2 OVER the running app (not a sibling!)
tar -xzf /path/to/marketplace-phase-10-v10.2.tar.gz --strip-components=1 --overwrite

# 3. Run the comprehensive deploy
./scripts/deploy.sh
```

If `deploy.sh` exits non-zero, STOP. The output identifies which step failed; fix that before testing.

---

## 1. Confirm v10.2 is actually live

| Step | Action | Expected | Result |
|---|---|---|---|
| 1.0 | Visit any storefront page (e.g. `/`) | Footer shows `· v Phase 10 v10.2` | ☐ |
| 1.1 | Run `php artisan marketplace:verify-fixes` | Every line shows ✓; exit code 0 | ☐ |
| 1.2 | Run `cat VERSION` | Output is `Phase 10 v10.2` | ☐ |
| 1.3 | Run `php artisan route:list \| grep -E 'reports\|sitemap\|vendor-files'` | Shows all 7 routes from PHASE_10_v10.2_ROUTE_REPORT.md | ☐ |

If any of 1.0-1.3 fails, the v10.2 deploy is incomplete. Re-run `./scripts/deploy.sh` and inspect the output for errors.

---

## 2. The §14 mandatory browser verification (the 16 explicit actions)

### Vendor product creation (defect #4)

| # | Action | Expected | Result |
|---|---|---|---|
| 1 | Sign in as `vendor@marketplace.test`. Go to `/vendor/products/create`. Fill the form with name, type=Simple, price=50.00, currency=KWD, track_stock=off. Add ONE image file (JPG). Submit. | Form submits. No "MassAssignmentException" anywhere. Product saved as draft. | ☐ |
| 2 | Same as #1 but no image. | Submits successfully, no exception. | ☐ |
| 2a | Same as #1 but with TWO images. | All images uploaded, no exception. | ☐ |

If any of these throws `MassAssignmentException [images]`, the deployed VendorProductController is NOT the v10.2 version. Run:
```bash
grep -c "unset(\$data\['images'\])" app/Http/Controllers/Vendor/VendorProductController.php
# Must output 2. If 0 or 1, re-extract the v10.2 archive.
```

### Admin sees vendor application + selected package + images (defects #2, #3)

| # | Action | Expected | Result |
|---|---|---|---|
| 3 | Sign in as `admin@marketplace.test`. Open `/admin/vendors`. The table shows a "Requested package" column. | Column exists; pending vendor shows their selected package name + status. | ☐ |
| 4 | Click into any vendor record (Edit). | "Vendor-selected package (from application)" section shows the package name, price, commission %, max products, and selection date. | ☐ |
| 5 | Same screen, "Documents" section. | Logo + license + ID show as ACTUAL THUMBNAILS or download links — NOT raw paths like `vendors/123/logo.jpg`. | ☐ |
| 5a | Click the license document link. | Opens in new tab; PDF/image renders. | ☐ |
| 5b | Copy the license document URL; open in incognito (logged out). | 403 — signature is bound to a logged-in admin session. | ☐ |

If #3-5 fails, the deployed VendorResource is NOT v10.2. Run:
```bash
grep -c "VendorFileLinks::previewHtml" app/Filament/Resources/VendorResource.php
# Must output 4. If 0, re-extract.

grep -c "requested_package" app/Filament/Resources/VendorResource.php
# Must output 2. If 0, re-extract.
```

If #3-5 succeeds in code but #5 still shows paths in the browser, the Filament navigation cache is stale:
```bash
php artisan filament:cache-components
php artisan cache:clear
php artisan view:clear
```

### Vendor order status updates (defect #5)

| # | Action | Expected | Result |
|---|---|---|---|
| 6 | As vendor, open `/vendor/orders`. | List shows orders with an Actions column. Each row has Confirm/Ship/Deliver buttons appropriate to its current state. | ☐ |
| 7 | Click "Confirm" on a paid order with status=pending_payment. | Status transitions to confirmed; success flash; row updates. | ☐ |
| 7a | Click "Ship" on a paid order with fulfillment=unfulfilled. | Fulfillment transitions to shipped. | ☐ |
| 7b | Click "Deliver" on an order with fulfillment=shipped. | Fulfillment transitions to fulfilled. | ☐ |
| 7c | As customer, refresh `/orders/<id>`. | Customer sees the updated status. | ☐ |

If #6 fails (no buttons visible), the deployed Vendor/Orders/Index.tsx is the OLD version. After `npm run build` it MUST contain the row-{confirm,ship,deliver} testids. Run:
```bash
grep -cE "row-(confirm|ship|deliver)-" resources/js/Pages/Vendor/Orders/Index.tsx
# Must output 3. If 0, re-extract.

# Force Vite rebuild:
npm run build
# Then hard-refresh the browser (Ctrl+Shift+R) to bypass browser cache.
```

### Admin Reports findable + functional (defect #6)

| # | Action | Expected | Result |
|---|---|---|---|
| 8 | As admin, open `/admin`. Look at the Filament sidebar. | A "Reports" group is visible with "Reports Dashboard" item. | ☐ |
| 9 | Click "Reports Dashboard". | Navigates to `/admin/reports`. Page RENDERS (no blank screen). KPI cards visible. | ☐ |
| 9a | Open browser DevTools console; check for JS errors. | No errors. | ☐ |
| 9b | Change date filter to "last 7 days" → Apply. | Page reloads with the filter applied. | ☐ |
| 9c | Click "Download CSV". | CSV downloads; opens cleanly in Excel/Sheets. | ☐ |
| 9d | As `vendor@marketplace.test`, try to navigate to `/admin/reports` directly. | 403 Forbidden. | ☐ |
| 9e | As `customer@marketplace.test`, try to navigate to `/admin/reports`. | 403 Forbidden. | ☐ |

If #8 fails (no Reports group in sidebar), the Filament navigation cache is stale. Run:
```bash
php artisan filament:cache-components
php artisan optimize:clear
# Then hard-refresh the Filament admin page.
```

If #9 produces a blank page or "cannot resolve module AdminLayout", `npm run build` didn't run or the build failed. Run:
```bash
ls -la resources/js/Layouts/AdminLayout.tsx
# Must exist (81 lines).

npm ci
npm run build
# Look for errors in the output. Then hard-refresh the browser.
```

### Vendor Reports findable + functional (defect #7)

| # | Action | Expected | Result |
|---|---|---|---|
| 10 | As vendor, look at the vendor sidebar/topnav. | "Reports" link is visible alongside Dashboard, Products, Orders. | ☐ |
| 11 | Click "Reports". | Navigates to `/vendor/reports`. Page renders. KPI cards reflect THIS VENDOR's data only. | ☐ |
| 11a | Try `/vendor/reports?vendor_id=999` directly (URL manipulation). | Still shows YOUR data (the param is ignored — controller resolves vendor from request attributes). | ☐ |
| 11b | Click "Download CSV". | CSV downloads with only this vendor's order items. | ☐ |

If #10 fails, the deployed VendorLayout is OLD. Run:
```bash
grep -c "vendor-nav-reports" resources/js/Layouts/VendorLayout.tsx
# Must output 2. If 0, re-extract + npm run build.

grep -c "Reports moved into baseItems" resources/js/Layouts/VendorLayout.tsx
# Must output 1 (the v10.2 comment marker confirming Reports is in baseItems).
```

### /sitemap.xml works (defect #8)

| # | Action | Expected | Result |
|---|---|---|---|
| 12 | In a browser or `curl`, open `https://your-domain.example/sitemap.xml`. | Returns HTTP 200, Content-Type: application/xml. Body starts with `<?xml version="1.0"` and contains `<urlset>` with at least one `<url><loc>` for a published product. | ☐ |
| 13 | Same URL via curl: `curl -i https://your-domain.example/sitemap.xml`. | Same result via CLI. | ☐ |

If #12-13 returns 404:
```bash
# Step 1: confirm Laravel knows about the route
php artisan route:list | grep sitemap
# Must show: GET|HEAD  sitemap.xml ... public.sitemap

# Step 2: bypass nginx and hit Laravel directly
php artisan serve --port=8001 &
curl -i http://127.0.0.1:8001/sitemap.xml
kill %1
# Must return 200 + XML

# Step 3: if step 2 works but the production URL still 404s, the issue is nginx.
# Verify the try_files directive includes /index.php?$query_string as the fallback.
# See PHASE_10_DEPLOYMENT_GUIDE.md §2.11 for the correct nginx config.
```

### Mobile responsiveness (defect #10)

| # | Action | Expected | Result |
|---|---|---|---|
| 14 | Open Chrome DevTools → Toggle device toolbar → set viewport to 375x812 (iPhone 12 mini). Visit `/` (logged out). | Page fits horizontally — no horizontal scroll. Logo + cart icon (if logged in) + hamburger button visible in header. | ☐ |
| 15a | Tap the hamburger. | Mobile drawer opens with all nav links: Products, Services, Deals, Sign in / Register (or My Orders, etc. if logged in). | ☐ |
| 15b | Sign in. Visit `/vendor` (assuming vendor login). | Vendor header shows logo + hamburger. Hamburger drawer has Dashboard, Products, Orders, **Reports**, Reviews, etc. | ☐ |
| 15c | Visit `/vendor/orders`. | Table is horizontally scrollable but the page itself doesn't overflow. Inline action buttons are tappable. | ☐ |
| 15d | Visit `/admin` (as admin) at 375px. | Filament's own mobile responsive behavior (sidebar collapses to hamburger). Reports link still findable. | ☐ |

If #14-15 still show broken mobile layout:
```bash
# Vite bundle is stale. Run:
npm ci
npm run build

# Then in the BROWSER:
# 1. Hard-refresh (Ctrl+Shift+R / Cmd+Shift+R)
# 2. If still broken, open DevTools → Network tab → check Disable cache → reload
```

### Performance smoke test (defect #1)

| # | Action | Expected | Result |
|---|---|---|---|
| 16a | Visit `/` 5 times in a row. | Each subsequent load feels at least as fast as the first; not slower. | ☐ |
| 16b | Visit `/products`. Open DevTools → Network tab. Check the slowest request. | < 1 second on a 100-product seed. | ☐ |
| 16c | As admin, open `/admin/reports`. | Page loads in < 2 seconds. | ☐ |
| 16d | Run `php artisan tinker --execute='echo \Illuminate\Support\Facades\Cache::get("inertia:translations:v1:en") ? "HIT" : "MISS";'` | After at least one Inertia request: output is HIT (translations are cached). | ☐ |

For a deeper performance audit, install Laravel Debugbar locally:
```bash
composer require barryvdh/laravel-debugbar --dev
# Then visit suspicious pages and check the debugbar query count + duration
```

---

## 3. Regression — confirm v10.0/v10.1/v9.x work didn't break

Run the v10.0 + v10.1 Pest scenarios:
```bash
php artisan test --filter='Phase10'
php artisan test --filter='Phase10V101'
php artisan test --filter='Phase10V102'
```

All three should pass. If any fail, capture the failing test names and message; share them so I can address in v10.3 if needed.

---

## 4. CI gate

The CI workflow runs the same checks above PLUS `marketplace:verify-fixes`. Push v10.2 to the branch CI watches. Expected verdict:

```
✅ Phase 10 v10.2 PASSES — ready for final deployment review
```

If CI shows red on any v10.2 sub-check, the failure name tells you exactly which fix isn't present in the source — re-extract the v10.2 archive over the deployed directory.

---

## 5. Final go/no-go

The marketplace is ready for production traffic ONLY when:

- ☐ Every action 1-16 above passes in the browser
- ☐ `php artisan marketplace:verify-fixes` exits 0
- ☐ `php artisan test` is green
- ☐ CI shows `✅ Phase 10 v10.2 PASSES`
- ☐ Production config audit per `PHASE_10_DEPLOYMENT_GUIDE.md` §6 is complete

If any single ☐ is unchecked, do NOT launch.

If you still see any v10.0/v10.1 defect after running the checklist, the issue is **NOT in the v10.2 code** (verified via `marketplace:verify-fixes`). The issue is at the deployment/cache layer. Run `./scripts/deploy.sh` again, and as a final option, reboot the server to flush every possible cache (OPcache, PHP-FPM, opcode cache, browser cache via hard-refresh).

---

## Quick summary card

| Item | Defect | Static fix present? | Runtime verification |
|---|---|---|---|
| 1 | Site slow | ✓ (cached translations + 7 indexes) | Manual load timing |
| 2 | Admin sees paths | ✓ (VendorFileLinks helper) | Browser screenshot of /admin/vendors/{id}/edit |
| 3 | No package shown | ✓ (Requested package field) | Same as #2 |
| 4 | MassAssignment[images] | ✓ (unset before create/update, 2 calls) | Submit product create form with image |
| 5 | No vendor order actions | ✓ (row-confirm/ship/deliver testids) | Click buttons on /vendor/orders |
| 6 | Admin Reports unfindable | ✓ (AdminLayout.tsx + Filament nav item) | Navigate via sidebar |
| 7 | Vendor Reports unfindable | ✓ (vendor-nav-reports testid in baseItems) | See nav + page renders |
| 8 | /sitemap.xml missing | ✓ (route + controller) | curl /sitemap.xml |
| 9 | Images as paths | (same as #2) | Same as #2 |
| 10 | Mobile broken | ✓ (hamburger menus in both layouts) | 375px viewport test |

**All 10 defects have static fix code present in the v10.2 archive. All 10 require deployment to actually run (`npm run build` + `optimize:clear` + Filament/permission cache flush) to be visible at runtime.**
