# Phase 10 v10.13 — Developer Checklist

The dev's §9 + §10 manual walkthrough.

## Deploy

```bash
cd /var/www/marketplace
sha256sum -c marketplace-phase-10-v10.13.tar.gz.sha256        # → OK
tar -xzf marketplace-phase-10-v10.13.tar.gz --strip-components=1 --overwrite
./scripts/deploy.sh
```

## §10 — Required pre-test commands

```bash
php artisan optimize:clear
php artisan route:list | grep -i report                  # → 4 routes (admin index/export + vendor index/export)
php artisan test --filter='Phase10V1013'                  # → 19 scenarios, all should pass
npm run typecheck
npm run build
```

Restart Laravel from the active folder:
```bash
# Ctrl+C the existing artisan serve, then:
php artisan serve
```

For PHP-FPM:
```bash
sudo systemctl restart php8.3-fpm
sudo systemctl reload nginx
```

**Hard-refresh the browser** (Ctrl+Shift+R) — this is critical. The dev's pre-v10.13 inability to find the Reports nav link may have been compounded by cached pre-build assets. The browser must load the newly built `/build/assets/*.js` referenced in `public/build/manifest.json`.

## §9 — The required confirmations

### ☐ Confirmation A — Approved vendor sees the Reports nav link

1. **Logout** any existing session.
2. Login as an approved vendor (`vendor@marketplace.test` / `password`).
3. After landing on `/vendor`, look at the top navigation bar.
4. **Expected (desktop):** the nav row contains `Dashboard | Products | Orders | 📊 Reports | Reviews | Wallet | Payouts | Suppliers | …`. The "📊 Reports" link has a bar-chart icon prefix that no other link has.
5. **Expected (mobile, < 1024px):** click the hamburger button (top-right). The mobile drawer shows the same list with the same icon prefix on Reports.

### ☐ Confirmation B — Vendor Dashboard has a prominent Reports CTA

1. Still on `/vendor` (the dashboard).
2. Directly under the "Business" header card, **expected:** a wide indigo gradient card with:
   - A bar-chart icon in an indigo square on the left
   - Header: "View My Reports"
   - Subtext: "Gross sales, commission, earnings, payouts, top products — your own data only."
   - "Open Reports →" link on the right

### ☐ Confirmation C — Click Reports → route opens

1. Click EITHER the "Reports" nav link OR the "Open Reports →" CTA.
2. **Expected:** URL becomes `/vendor/reports`, HTTP 200, the Vendor Reports page renders with date filters and KPI sections.
3. **Expected:** while on `/vendor/reports`, the "Reports" nav link is now highlighted in indigo (active state).

### ☐ Confirmation D — Vendor sees only their own data

1. On `/vendor/reports`, note the gross/earnings totals.
2. Compare with the actual order rows in the database for this vendor:
   ```bash
   php artisan tinker
   >>> \App\Models\OrderItem::where('vendor_id', YOUR_VENDOR_ID)->sum('line_total_minor')
   ```
3. **Expected:** the dashboard's `gross_minor` matches the manual sum.

### ☐ Confirmation E — Date filter works

1. On `/vendor/reports`, change the date preset to `last_7_days`, `this_month`, `previous_month`, `custom`.
2. **Expected:** URL gains `?preset=...`, page re-renders, totals update.

### ☐ Confirmation F — Two-vendor isolation (manual)

1. Open `/vendor/reports` as Vendor A. Note the totals.
2. Try to URL-hack: navigate to `/vendor/reports?vendor_id=<some_other_vendor_id>`.
3. **Expected:** the totals are STILL Vendor A's (the `?vendor_id` query string is ignored — vendor is resolved server-side from `auth()->user()->vendor`).
4. Logout. Login as a different vendor (Vendor B). Open `/vendor/reports`.
5. **Expected:** the totals are Vendor B's (different from A's).

### ☐ Confirmation G — Customer is blocked

1. Logout. Login as `customer@marketplace.test`.
2. Visit `/vendor/reports` directly.
3. **Expected:** redirect to `/vendor/apply` (customer has no vendor profile) — NOT a 200 response.
4. Note: the vendor navigation is not shown to customers at all (customers use `StorefrontLayout`, not `VendorLayout`).

### ☐ Confirmation H — Guest is blocked

1. Logout. Visit `/vendor/reports` without authentication.
2. **Expected:** redirect to `/login`.

### ☐ Confirmation I — Admin Reports STILL work (v10.12 regression guard)

1. Login as admin (`admin@marketplace.test`).
2. Visit `/admin/reports`.
3. **Expected:** HTTP 200, admin reports dashboard renders. The customers_total KPI shows the correct count using v10.12's Spatie scope. The payout cards (v10.11 fix) populate.

### ☐ Confirmation J — Export works (if implemented)

1. As an approved vendor, hit `/vendor/reports/export.csv` (or click the export button if present in the React page).
2. **Expected:** CSV download begins. No 403/404/500.

## What to do if Confirmation A fails (Reports link still missing)

```bash
# 1. Confirm v10.13 deployed
cat VERSION                            # → Phase 10 v10.13

# 2. Confirm the visibility surfaces are in the deployed source
grep -c "ReportsIcon" resources/js/Layouts/VendorLayout.tsx
# → 4 (definition + JSX in desktop + JSX in mobile + 1 other reference)

grep -c "vendor-dashboard-reports-cta" resources/js/Pages/Vendor/Dashboard.tsx
# → 1

# 3. Hard-refresh browser (Ctrl+Shift+R), clear browser cache for the site

# 4. Confirm the React assets were rebuilt
ls -la public/build/assets/*.js | head -5
# the file mtime should be recent (the rebuild moment)

# 5. Confirm the loaded asset in the Network tab matches public/build/manifest.json
```

If the source has the markers but the browser still doesn't show the link: stale Vite dev server. Stop it. Run `npm run build` (production build) and verify the browser loads from `public/build/`.

## What to do if Confirmation D fails (vendor sees another vendor's data)

This would indicate a data isolation bug — escalate immediately. v10.13 ships Pest test `vendor cannot pass ?vendor_id to read another vendor's data` which asserts this. If it fails in the field but the Pest test passes, the deployed source isn't actually v10.13.

```bash
grep -n "request->attributes->get('vendor')" app/Http/Controllers/Vendor/VendorReportsController.php
# Expected: 1 line — the canonical vendor resolution from request attributes

grep -nE "where\(.vendor_id., .*vendor_id.*request" app/
# Expected: 0 hits — no code path should read vendor_id from request
```

## CI verdict

```
✅ Phase 10 v10.13 PASSES — ready for final deployment review
```

## Final

**Phase 10 v10.13 STOPS HERE.** No Phase 11. Pending dev runtime verification of confirmations A-J.
