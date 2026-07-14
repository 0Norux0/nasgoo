# Phase 10 v10.7 — Developer Checklist

Targeted verification — only what the dev §14 demanded.

## Deploy

```bash
cd /var/www/marketplace
sha256sum -c marketplace-phase-10-v10.7.tar.gz.sha256        # → OK
tar -xzf marketplace-phase-10-v10.7.tar.gz --strip-components=1 --overwrite
./scripts/deploy.sh
```

`scripts/deploy.sh` runs `composer install`, `npm ci`, `npm run build`, `php artisan storage:link`, full cache flush. Also `php artisan optimize:clear && php artisan config:clear && php artisan config:cache` to verify cached config still finds the new disk.

## The two required confirmations (§14)

### ☐ Confirmation A — PDF vendor documents still open correctly (regression check)

1. Sign in as `admin@marketplace.test`.
2. Navigate to `/admin/vendors` → click `pending-vendor@marketplace.test`.
3. In the Documents section, click the link for the PDF license / PDF ID document.
4. **Expected:** PDF opens inline in a new tab. NOT a "File not found" message. NOT a 404 page.

If this fails: v10.7 broke the previously-working PDF flow. STOP — share the URL + response status + `php artisan route:list | grep vendor-files`.

### ☐ Confirmation B — image vendor documents display and open correctly

1. Submit a NEW vendor application (use `register-new-vendor@test.com` or test account); attach a JPG logo, a PNG banner, a JPG license, and a PNG id_document. Submit.
2. Sign in as admin → navigate to `/admin/vendors` → click the new vendor → Edit.
3. Documents section: each of the 4 fields shows a **thumbnail image** (not the text "File not found", not a raw path).
4. Click any thumbnail's "Open full size →" link or "View" button.
5. **Expected:** the image opens in a new tab. HTTP 200. Correct image MIME type (image/jpeg, image/png, image/webp).
6. **Expected for license/ID:** URL contains a `signature=...` query param (signed route, not direct public URL).
7. **Expected for logo/banner:** URL is either `/storage/vendors/{id}/{filename}` (direct public) OR the signed route (legacy compatibility for vendors registered before v10.7).
8. Sign in as `customer@marketplace.test` and attempt any image URL above that uses the signed route.
9. **Expected:** HTTP 403.

If thumbnail still shows "File not found": the deployed source is not v10.7. Verify:
```bash
grep -c "VendorFileResolver::resolve" app/Domain/Vendor/VendorFileLinks.php
# Expected: 1 (the resolver delegate)

php artisan marketplace:verify-fixes
php artisan marketplace:fingerprint
```

If file paths differ (legacy records on `local` instead of `public`), the resolver fallback should still find them. The page should NEVER show "File not found" for an existing file.

## Legacy compatibility test

If your database has pre-v10.7 vendor records (logo/banner stored on the `local` disk), they should still render. The resolver checks disks in priority order:
- Public kinds: `vendor_public_disk` → `vendor_private_disk` → `filesystems.default`
- Private kinds: `vendor_private_disk` → `filesystems.default` → `vendor_public_disk`

For a legacy logo on `local`, the resolver finds it on the second probe and routes the URL through the signed admin route (defense-in-depth for files that landed in the wrong place).

## Targeted Pest run

```bash
php artisan test --filter='Phase10V107'
```

All 18 scenarios should pass.

## CI verdict

After CI runs against this archive, the final job should output:

```
✅ Phase 10 v10.7 PASSES — ready for final deployment review
```

## Final

Per §14: the package may be described as fixed only after both Confirmation A and Confirmation B above complete successfully. Until then: **Phase 10 v10.7 is implemented but requires developer runtime verification.**

**Phase 10 v10.7 STOPS HERE.** No Phase 11. Pending your verification.
