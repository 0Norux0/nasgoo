# Phase 10 v10.6 — Developer Checklist

Per dev §8: "The checklist must require the developer to verify only these three items first."

## Step 0 — Deploy

```bash
cd /var/www/marketplace
sha256sum -c marketplace-phase-10-v10.6.tar.gz.sha256          # OK
tar -xzf marketplace-phase-10-v10.6.tar.gz --strip-components=1 --overwrite
./scripts/deploy.sh                                            # composer + npm + build + cache flush
php artisan marketplace:fingerprint                            # aggregate must match canonical
```

If `marketplace:fingerprint` reports a mismatch, STOP — the deployed source is not v10.6. Re-extract.

## The three verification items the dev demanded — IN THIS ORDER

### ☐ Item 1: Open pending vendor documents without filesystem error

1. Sign in as `admin@marketplace.test`
2. Navigate to `/admin/vendors`
3. Click the row for `pending-vendor@marketplace.test`
4. Click "Edit"
5. **Expected:** the form RENDERS. NO `InvalidArgumentException: Disk [vendors] has no configured driver` message. The Documents section shows logo/banner/license/ID either as thumbnails or as download links.
6. Click any document link.
7. **Expected:** the file opens (image inline / PDF inline / browser downloads depending on type). MIME type is correct. URL is signed (the URL contains a `signature=...` query param).
8. As `customer@marketplace.test`, attempt the same URL.
9. **Expected:** 403.

If step 5 still shows the disk-driver error → the deployed config/filesystems.php is NOT v10.6:
```bash
grep -A4 "'vendors' =>" config/filesystems.php
# Expected: a block with driver='local', root=storage_path('app/private')

php artisan config:clear && php artisan config:cache
```

### ☐ Item 2: Change and persist vendor order status

1. Sign in as `vendor@marketplace.test`
2. Open any `/vendor/orders/{id}` page
3. **Expected:** the page header shows a "Update status:" `<select>` dropdown.
4. Open the dropdown.
5. **Expected:** Current status visible as the disabled default; → Confirm / → Shipped / → Delivered available based on the order's state (invalid transitions are disabled with a tooltip explaining why).
6. Pick a valid transition (e.g. "→ Confirm order" on a pending paid order).
7. **Expected:** the dropdown immediately disables itself and shows "Updating…" (not a confirm() dialog!). After ~1 second the page reloads with a green success flash and the order's status visibly updates.
8. Reload the page hard (Ctrl+Shift+R).
9. **Expected:** the new status persists.
10. Sign in as the customer who placed the order.
11. **Expected:** the customer-side order detail page shows the updated fulfillment status.

If step 7 still shows a confirm() dialog → the deployed Show.tsx is NOT v10.6:
```bash
grep -c "submitStatusChange" resources/js/Pages/Vendor/Orders/Show.tsx
# Expected: 2+ (function definition + invocation)

# Force Vite rebuild:
npm run build
# Then hard-refresh the browser.
```

### ☐ Item 3: Mobile collapsed Categories inside hamburger

Chrome DevTools → toggle device toolbar → set viewport to 375 × 812.

1. Visit `/products`.
2. **Expected:** NO category links visible above the product grid. The category sidebar is hidden at mobile widths.
3. Tap the hamburger button (top-right of header).
4. **Expected:** drawer opens.
5. **Expected:** the FIRST item in the drawer is "Categories" with a `›` chevron next to it.
6. **Expected:** the category list is NOT visible yet (collapsed).
7. Tap "Categories".
8. **Expected:** the chevron rotates 90° (down), and the full category list appears below the toggle, with each category as a tappable row.
9. Tap a category.
10. **Expected:** the drawer closes AND the page navigates to `/products?category={slug}` (filtered view).

If step 2 still shows category links above the products → the deployed Catalog/Index is NOT v10.6:
```bash
grep -E 'hidden lg:block' resources/js/Pages/Catalog/Index.tsx
# Expected: a line showing the <aside className="hidden lg:block">

npm run build  # rebuild Vite
```

If step 5 shows no "Categories" toggle → StorefrontLayout is NOT v10.6:
```bash
grep -c "storefront-mobile-categories-toggle" resources/js/Layouts/StorefrontLayout.tsx
# Expected: 1

npm run build
```

## Final acceptance

All three ☐ checked → v10.6 is verified. If any fails after `marketplace:fingerprint` matches canonical, share:
- The exact failed step number
- Browser DevTools console screenshot
- `php artisan marketplace:fingerprint --json` output
- `cat public/build/manifest.json | head -10` (proves Vite produced a fresh build)

That information is enough to target the specific cause in v10.7 without guessing.

**Phase 10 v10.6 STOPS HERE.** No Phase 11. No publicly-launched declaration. Pending your 3-item verification.
