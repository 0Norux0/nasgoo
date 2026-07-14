# Phase 10 v10.4 — Developer Checklist

This is the forensic-package checklist. Step zero is **prove your deployed code is actually v10.4**. Without that, every subsequent test is meaningless.

## Step 0 — Prove the deployment fidelity (NEW in v10.4)

```bash
cd /var/www/marketplace                                                # ACTIVE project root
ls -la artisan VERSION                                                  # confirm you're in the right place

# 1. Verify archive integrity (sidecar checksum)
sha256sum -c marketplace-phase-10-v10.4.tar.gz.sha256
# Expected: marketplace-phase-10-v10.4.tar.gz: OK

# 2. Extract OVER the running app
tar -xzf marketplace-phase-10-v10.4.tar.gz --strip-components=1 --overwrite

# 3. Run the full deploy (composer install + npm ci + npm run build + ALL cache flushes)
./scripts/deploy.sh

# 4. Verify deployment fingerprint matches the canonical fingerprint
php artisan marketplace:fingerprint --json | python3 -c "
import json, sys
d = json.load(sys.stdin)
print(f'Aggregate: {d[\"aggregate_sha256\"]}')
print(f'Expected:  14c5d36d3e01b4236411b668e4fc17645c906cb079ab04dc7d0cd37caf71bf35')
print(f'Match: {d[\"aggregate_sha256\"] == \"14c5d36d3e01b4236411b668e4fc17645c906cb079ab04dc7d0cd37caf71bf35\"}')"
```

**If aggregate matches expected: your deployed source IS Phase 10 v10.4.** Proceed to Step 1.

**If they DIFFER: re-extract the archive. Until the aggregate matches, no further test result is interpretable.**

Common reasons for mismatch:
- Archive was extracted into a sibling directory, not the running app
- `git pull` ran after extract, overwriting files
- The file ownership/permission prevented some files from being written
- A symlink in the project root points elsewhere

## Step 1 — Verify-fixes (substring scan, 19 checks)

```bash
php artisan marketplace:verify-fixes
```

Expected: 19 ✓ lines, exit 0. (Complementary to fingerprint: catches a case where the right markers are present but file contents differ slightly from canonical.)

## Step 2 — Visible version banner

Open any storefront page in a browser. Footer should show `· v Phase 10 v10.4`.

If footer shows an older version: Vite hasn't rebuilt. Run `npm run build`; hard-refresh the browser.

## Step 3 — The 5 critical defect tests (in priority order)

### Defect 1 — Admin opens vendor edit page → form RENDERS (no 500)

1. Sign in as `admin@marketplace.test`
2. Navigate to `/admin/vendors`
3. Click the row for `pending-vendor@marketplace.test`
4. Click "Edit"
5. **Expected:** the page renders. NOT a 500 error, NOT a blank page.
6. Scroll to "Documents" section: 4 fields (Logo, Banner, License, ID) each show either a thumbnail, a download link, or a fallback "—" placeholder.
7. Scroll to "Vendor-selected package (from application)": shows package name + price + commission + max products.

If #5 still shows 500 or blank: `grep -c '->disableLabel(' app/Filament/Resources/VendorResource.php` MUST output 0. If it doesn't, re-extract the archive.

### Defect 2 — Vendor creates a product with images → no MassAssignment

1. Sign in as `vendor@marketplace.test`
2. Navigate to `/vendor/products/create`
3. Fill: name, type=Simple, price=50.00, currency=KWD; upload 3 image files
4. Submit
5. **Expected:** redirects to product list; product created; no exception

If exception appears: `grep 'public function fill' app/Models/Product.php` MUST show the v10.3 override. If missing, re-extract.

### Defect 3 — Vendor sees status dropdown on order detail

1. Sign in as `vendor@marketplace.test`
2. Open any `/vendor/orders/{id}` page
3. **Expected:** in the header bar, a `<select>` labeled "Update status:" is visible
4. Open the dropdown: shows current status + Confirm/Ship/Deliver transitions (invalid ones disabled with tooltip)

If dropdown missing: `grep 'vendor-order-status-dropdown' resources/js/Pages/Vendor/Orders/Show.tsx` MUST output a line. If it does but the dropdown still isn't in the browser, `npm run build` didn't run (or the browser is caching old JS).

### Defect 5 — Mobile pages don't overflow

Chrome DevTools → 375x812 viewport. Visit `/`, `/products`, `/vendor`, `/admin/vendors/{id}/edit`. In console:

```js
document.documentElement.scrollWidth > window.innerWidth
```

**Expected:** `false` on every page.

If `true`: `grep 'overflow-x-hidden' resources/css/app.css` MUST output a line. If it does but pages still overflow, the built CSS bundle is stale → `npm run build`.

### Defect 6/7 — Reports pages reachable

1. As admin: click "Reports Dashboard" in Filament sidebar → `/admin/reports` loads
2. As vendor: click "Reports" in vendor nav → `/vendor/reports` loads

## Step 4 — Run the Pest suite

```bash
php artisan test --filter='Phase10'
```

All 43 Phase 10 scenarios (13 v10.0 + 14 v10.1 + 8 v10.2 + 8 v10.3 + 6 v10.4) should pass.

## Step 5 — Final acceptance

Mark each as ☐ pass / ✗ fail:

- ☐ `marketplace:fingerprint` aggregate matches canonical
- ☐ `marketplace:verify-fixes` 19 ✓
- ☐ Browser footer shows `· v Phase 10 v10.4`
- ☐ Defect 1 — admin vendor edit form renders, documents visible
- ☐ Defect 2 — product create with images succeeds
- ☐ Defect 3 — status dropdown visible + functional
- ☐ Defect 5 — no page overflows at 375px
- ☐ Defect 6 — admin Reports page reachable
- ☐ Defect 7 — vendor Reports page reachable
- ☐ Pest filter='Phase10' passes
- ☐ CI shows `✅ Phase 10 v10.4 PASSES`

If ALL pass: Phase 10 is implemented and verified. Proceed to launch checklist (PHASE_10_DEPLOYMENT_GUIDE.md §6).

If ANY fail AFTER `marketplace:fingerprint` matches canonical: there's a runtime infrastructure issue (OPcache, browser cache, CDN, queue worker). The first place to look:

```bash
sudo systemctl restart php8.3-fpm  # flush OPcache (critical when validate_timestamps=0)
php artisan queue:restart           # workers reload code on next job
# Browser: hard-refresh + DevTools → Network → Disable cache
```

If after THAT the failures persist, share:
- The exact failed step number above
- Browser DevTools console screenshot
- `php artisan marketplace:fingerprint` output
- `php artisan route:list | grep -E 'reports|sitemap|vendor-files'` output
- `cat public/build/manifest.json | head -20` (proves Vite produced a new build)

That set of evidence is sufficient to target the specific failure in v10.5.
