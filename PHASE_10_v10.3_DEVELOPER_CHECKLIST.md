# Phase 10 v10.3 — Developer Checklist

The 5 unresolved defects, the exact actions to verify each one in your environment.

## Pre-test

```bash
cd /var/www/marketplace
mysqldump marketplace > backup-pre-v103-$(date +%Y%m%d).sql
tar -xzf /path/to/marketplace-phase-10-v10.3.tar.gz --strip-components=1 --overwrite
./scripts/deploy.sh
```

If `deploy.sh` exits non-zero, STOP. Read the failure output; fix that.

After `deploy.sh` exits 0:

```bash
php artisan marketplace:verify-fixes
```

Must show 19 ✓ lines, exit 0.

Visit any storefront page → footer must show `· v Phase 10 v10.3`.

## Defect 1+4 — admin can view vendor documents

| # | Action | Expected | Result |
|---|---|---|---|
| 1 | As `admin@marketplace.test`, open `/admin/vendors`. | Table loads. "Requested package" column visible. | ☐ |
| 2 | Click a vendor row → Edit. | Page renders (was crashing in v10.1/v10.2 due to disableLabel BadMethodCallException). | ☐ |
| 3 | Scroll to "Documents" section. | 4 fields: Logo, Banner, Business license, ID/passport. Each shows either a thumbnail (for images), a download link (for PDFs), or a "Not uploaded" placeholder. | ☐ |
| 4 | Click the license document link. | Opens in new tab via signed URL; PDF/image renders. | ☐ |
| 5 | Copy the URL; open in incognito (logged out). | 403. | ☐ |
| 6 | Wait 31 minutes; click the original link. | 403 (signature expired). | ☐ |

If #2 still throws a 500 / blank page → the deployed VendorResource is NOT v10.3. Check:
```bash
grep -c '->disableLabel(' app/Filament/Resources/VendorResource.php   # must be 0
grep -c 'data-v103' app/Filament/Resources/VendorResource.php          # must be 4
```

## Defect 2 — product creation works (no MassAssignmentException)

| # | Action | Expected | Result |
|---|---|---|---|
| 7 | As `vendor@marketplace.test`, open `/vendor/products/create`. | Form renders. | ☐ |
| 8 | Fill (name, type=Simple, price, currency=KWD). Upload 0 images. Submit. | Saves; redirects to product list. | ☐ |
| 9 | Repeat with 1 image. | Saves; image appears on product edit page. | ☐ |
| 10 | Repeat with 3 images. | All 3 images uploaded. | ☐ |
| 11 | Edit an existing product; upload another image. | Saves; image appended. | ☐ |
| 12 | As `admin@marketplace.test`, go to `/admin/products/create` (Filament). Fill the form. Add a row to the Images repeater. Submit. | Product created. No MassAssignment. | ☐ |

If any of 8-12 throws `MassAssignmentException [images]`:
```bash
grep -A2 'public function fill' app/Models/Product.php | head -5
# Must show the v10.3 override that calls unset($attributes['images'])
```

If the override is present but the exception still fires → cache layer issue:
```bash
sudo systemctl restart php8.3-fpm   # flush OPcache
php artisan optimize:clear
```

## Defect 3 — vendor order status dropdown

| # | Action | Expected | Result |
|---|---|---|---|
| 13 | As `vendor@marketplace.test`, open `/vendor/orders`. | List shows orders with inline action buttons in the Actions column. | ☐ |
| 14 | Click an order → opens `/vendor/orders/{id}`. | Show page renders. | ☐ |
| 15 | Look at the header bar. | A dropdown labeled "Update status:" is visible with `data-testid="vendor-order-status-dropdown"`. | ☐ |
| 16 | Open the dropdown. | Lists: Current status (default), → Confirm order, → Processing (informational), → Shipped, → Delivered. Invalid transitions are DISABLED with tooltip explaining why. | ☐ |
| 17 | Select a VALID transition (e.g. "→ Confirm order" on a pending paid order). | Confirms; status updates; success flash; dropdown resets. | ☐ |
| 18 | Try to select a DISABLED option. | The browser refuses (the `<option disabled>` cannot be selected). | ☐ |
| 19 | As `customer@marketplace.test`, refresh `/orders/{id}`. | Customer sees the new status. | ☐ |

## Defect 5 — mobile responsiveness

| # | Action | Expected | Result |
|---|---|---|---|
| 20 | Chrome DevTools → toggle device toolbar → 375x812. Visit `/`. | NO horizontal scroll. Logo + cart icon + hamburger button visible. | ☐ |
| 21 | Visit `/products`. Open DevTools console; run `document.documentElement.scrollWidth > window.innerWidth`. | Output: `false` (no overflow). | ☐ |
| 22 | Same for `/cart`, `/vendor`, `/vendor/orders/{id}`, `/admin/reports`. | All return `false`. | ☐ |
| 23 | Tap the hamburger on `/`. | Drawer opens with nav links. | ☐ |
| 24 | Tap the vendor hamburger on `/vendor`. | Drawer opens with vendor links incl. Reports. | ☐ |
| 25 | On `/vendor/orders/{id}` at 375px, locate the status dropdown. | Tappable; fits the viewport. | ☐ |

If any page overflows horizontally:
```bash
# Verify the v10.3 CSS guards made it into the build
grep -c 'overflow-x-hidden' resources/css/app.css   # must be 1
grep -c 'max-width: 100vw' resources/css/app.css     # must be 1

# Force Vite rebuild + hard-refresh
npm run build
# Browser: Ctrl+Shift+R, plus DevTools → Network → Disable cache
```

## Final go/no-go

- ☐ All 25 boxes above checked
- ☐ `php artisan marketplace:verify-fixes` shows 19 ✓
- ☐ Browser footer shows `· v Phase 10 v10.3`
- ☐ CI shows `✅ Phase 10 v10.3 PASSES`
- ☐ Production config audit per `PHASE_10_DEPLOYMENT_GUIDE.md` §6 complete

If any single ☐ is unchecked, the marketplace is NOT ready for production.

If you still see ANY of defects 1-5 after running this checklist AND verify-fixes is all ✓ AND the footer shows v10.3, please share:
- The exact action number that failed (1-25)
- Browser DevTools console screenshot
- Output of `php artisan marketplace:verify-fixes`
- Output of `cat public/build/manifest.json | head -10`

With that I can target the specific issue in v10.4 without guessing.
