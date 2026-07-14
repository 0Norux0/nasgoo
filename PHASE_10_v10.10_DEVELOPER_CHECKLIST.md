# Phase 10 v10.10 — Developer Checklist

The dev's §12 manual runtime verification process, focused.

## Deploy

```bash
cd /var/www/marketplace
sha256sum -c marketplace-phase-10-v10.10.tar.gz.sha256        # → OK
tar -xzf marketplace-phase-10-v10.10.tar.gz --strip-components=1 --overwrite
./scripts/deploy.sh
```

## §12.2 — run the targeted permission repair seeder

For the canonical demo admin (`admin@marketplace.test`):

```bash
php artisan db:seed --class=EnsureAdminReportsAccessSeeder
```

For a custom admin email (the one the developer logs in with):

```bash
php artisan reports:repair-access your-admin@your-email.test
```

Both are idempotent. Both clear Spatie's permission cache as part of their work.

## §12.3 — clear all Laravel + permission caches

```bash
php artisan optimize:clear
php artisan route:clear
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan permission:cache-reset
```

(The repair command + seeder already call `permission:cache-reset` internally, so this is belt-and-suspenders.)

## §12.4 — restart Laravel server / PHP-FPM

```bash
# For Artisan dev server:
# Ctrl+C the existing process, then:
php artisan serve

# For systemd / production PHP-FPM:
sudo systemctl restart php8.3-fpm
sudo systemctl reload nginx
```

## §12.5-9 — the required confirmation

### ☐ Confirmation A — admin opens /admin/reports

1. **Logout** from any existing session.
2. Clear browser cookies for `127.0.0.1` if necessary.
3. Login as the same administrator account the developer normally uses.
4. From the Filament panel sidebar, click **Reports Dashboard**.
5. **Expected:** the URL becomes `/admin/reports`, HTTP 200, dashboard renders with KPI cards + date filters.

**If this fails: run `php artisan reports:diagnose-access YOUR_ADMIN_EMAIL`**. The output shows exactly what state the user is in. Example output for a failing user:

```
════ Diagnostic for user #1 ════
  email:               admin@marketplace.test
  name:                Marketplace Admin
  status:              NULL  (type: NULL)
  status === "active": false
  roles:               []
  hasRole('super_admin'   ): false
  hasRole('admin_staff'   ): false
  hasRole('admin'         ): false
  hasRole('administrator' ): false
  canManageAdminReports(): false  ← the v10.10 canonical helper
  Gate::allows("viewReports"): false  ← legacy gate
  total permissions:   0

  ✗ This user will be DENIED /admin/reports.
    Run: php artisan reports:repair-access admin@marketplace.test
```

Then run the repair command and Confirmation A again.

### ☐ Confirmation B — date filters work

On the now-loaded `/admin/reports`:
1. Change the date preset to `last_7_days`, `this_month`, `previous_month`, `custom`.
2. **Expected:** the URL gains `?preset=...`, the page re-renders, no 403 reappears.

### ☐ Confirmation C — export works

1. Click CSV export (or visit `/admin/reports/export.csv` directly).
2. **Expected:** CSV download begins. No 403.

### ☐ Confirmation D — vendor cannot access admin reports

1. Logout, login as `vendor@marketplace.test` / `password`.
2. Visit `/admin/reports` directly.
3. **Expected:** HTTP 403.

### ☐ Confirmation E — customer cannot access admin reports

1. Logout, login as `customer@marketplace.test` / `password`.
2. Visit `/admin/reports` directly.
3. **Expected:** HTTP 403.

### ☐ Confirmation F — guest is redirected

1. Logout. Visit `/admin/reports` without authentication.
2. **Expected:** redirect to `/login`.

### ☐ Confirmation G — vendor's own reports still work

1. Login as vendor again. Visit `/vendor/reports`.
2. **Expected:** HTTP 200, the vendor's own reports page renders.

## Targeted Pest run

```bash
php artisan test --filter='Phase10V1010'
```

All 18 scenarios should pass — including the dev §11.12 case ("Existing admin created before the new permission can be repaired by the targeted seeder").

## What to do if Confirmation A STILL fails

This is the unhappy path. v10.10 is engineered so that this is now a single, observable failure:

1. Run `php artisan reports:diagnose-access YOUR_ADMIN_EMAIL`.
2. The output prints the exact reason `canManageAdminReports()` returns false. The most likely causes:
   - **Role not assigned:** the diagnostic shows `roles: []` or only `vendor`/`customer`. Run `php artisan reports:repair-access YOUR_ADMIN_EMAIL`.
   - **User not found:** the email doesn't exist in the database. Use `--all-admins` to list admin-like users: `php artisan reports:diagnose-access --all-admins`.
3. After the repair, log out, log back in, retest.
4. If the diagnostic shows `canManageAdminReports(): true` but the page STILL returns 403, the deployed source isn't actually v10.10:
   ```bash
   cat VERSION   # Must say: Phase 10 v10.10
   grep guardAdminReportsAccess app/Http/Controllers/Admin/ReportsController.php
   ```
   Expected output: 1 method definition + 2 call sites. If absent, re-extract the archive.

## CI verdict

```
✅ Phase 10 v10.10 PASSES — ready for final deployment review
```

## Final

**Phase 10 v10.10 STOPS HERE.** No Phase 11. Pending dev runtime verification of confirmations A-G.
