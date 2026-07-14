# Phase 10 v10.12 — Developer Checklist

The dev's §10 manual runtime verification.

## Deploy

```bash
cd /var/www/marketplace
sha256sum -c marketplace-phase-10-v10.12.tar.gz.sha256        # → OK
tar -xzf marketplace-phase-10-v10.12.tar.gz --strip-components=1 --overwrite
./scripts/deploy.sh
```

## §11 — Required pre-test commands

```bash
php artisan optimize:clear
php artisan migrate:status                                # confirm migrations are up to date
php artisan route:list --path=admin/reports               # → 2 routes (index + export.csv)
php artisan permission:cache-reset                        # clear Spatie permission cache
php artisan test --filter='Phase10V1012'                  # → 15 scenarios, all should pass
php artisan test                                          # full suite
npm run typecheck
npm run build
```

Restart Laravel from this exact project folder:

```bash
# Ctrl+C the existing artisan serve, then:
php artisan serve
```

For PHP-FPM:
```bash
sudo systemctl restart php8.3-fpm
sudo systemctl reload nginx
```

Hard-refresh the browser (Ctrl+Shift+R). Confirm assets in the Network tab match `public/build/manifest.json`.

## The required confirmations

### ☐ Confirmation A — admin opens /admin/reports

1. **Logout** from any existing session.
2. Login as the same administrator account the developer uses.
3. Click `Reports → Reports Dashboard`.
4. **Expected:** URL `/admin/reports`, HTTP 200, dashboard renders.
5. **Expected: NO** `SQLSTATE[42S22] Column not found: 1054 Unknown column 'role'` error.

### ☐ Confirmation B — all KPI cards render

Check that every KPI / snapshot card on the dashboard loads with a numeric value (not a placeholder, not a stack trace fragment):

- Financial summary cards (gross, commission, earnings, etc — v10.0 metrics)
- Payouts (pending / approved / paid / rejected) — **v10.11's fix**
- **Customers total — v10.12's fix**
- Vendors (approved / pending)
- Products (published / total)
- Services (published / total)
- Open tickets / total tickets
- Approved reviews + average rating
- Top vendors / top products

If any card shows 0, that's expected if the database has no rows for that metric. Only treat as a regression if the page returns 500 or a SQL error fragment.

### ☐ Confirmation C — date filters work

Change the date preset to `last_7_days`, `this_month`, `previous_month`, `custom`. **Expected:** URL gains `?preset=...`, page re-renders, no 500.

### ☐ Confirmation D — credibility check

Compare displayed totals with the database:

```bash
php artisan tinker

# What v10.12 counts as customers_total
>>> \App\Models\User::role('customer')->count()
# Compare with the dashboard's "Customers" KPI

# Verify the users.role column does NOT exist
>>> Schema::getColumnListing('users')
# → should NOT contain 'role'

>>> Schema::hasColumn('users', 'role')
# → false
```

### ☐ Confirmation E — Vendor & customer denial preserved

1. Logout. Login as `vendor@marketplace.test` (or your vendor account).
2. Visit `/admin/reports` directly.
3. **Expected:** HTTP 403.

Then:

1. Login as `customer@marketplace.test`.
2. Visit `/admin/reports`.
3. **Expected:** HTTP 403.

### ☐ Confirmation F — Guest redirect

1. Logout. Visit `/admin/reports` without authentication.
2. **Expected:** redirect to `/login`.

### ☐ Confirmation G — Export still works (if implemented)

1. As admin, hit `/admin/reports/export.csv` (or click the CSV button if present).
2. **Expected:** CSV download begins. No SQL error. (`marketplaceCounts` isn't called by the export — but the export uses other parts of `ReportsService` which were also audited.)

## What to do if Confirmation A still fails

```bash
# 1. Confirm v10.12 deployed
cat VERSION                          # Must say: Phase 10 v10.12

# 2. Confirm the fix is in the deployed source
grep -n "User::role('customer')" app/Domain/Reports/ReportsService.php
# Expected: 1 line — the customers_total query

grep -nE "DB::table\(['\"]users['\"]\)->where\(['\"]role['\"]" app/
# Expected: 0 results

# 3. Clear caches
php artisan optimize:clear
php artisan permission:cache-reset

# 4. Re-run the Pest scenarios
php artisan test --filter='Phase10V1012'
```

If the Pest scenarios all pass but the browser still 500s, the deployed source isn't what the test runner is seeing. Re-extract the archive, run `composer dump-autoload`, restart PHP-FPM.

## CI verdict

```
✅ Phase 10 v10.12 PASSES — ready for final deployment review
```

## Final

**Phase 10 v10.12 STOPS HERE.** No Phase 11. Pending dev runtime verification of confirmations A-G.
