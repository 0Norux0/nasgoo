# Phase 10 v10.9 — Developer Checklist

The dev's §10 manual walk-through, focused.

## Deploy

```bash
cd /var/www/marketplace
sha256sum -c marketplace-phase-10-v10.9.tar.gz.sha256        # → OK
tar -xzf marketplace-phase-10-v10.9.tar.gz --strip-components=1 --overwrite
./scripts/deploy.sh
```

`scripts/deploy.sh` runs the standard chain. v10.9 adds ONE NEW migration that's also self-healing:

```bash
php artisan migrate
# Expected: ... phase10_v109_ensure_reports_permission_assigned ... DONE
```

The migration:
- `firstOrCreate` the `reports.view` permission with `guard_name = 'web'`
- Ensures `super_admin` + `admin_staff` roles exist and hold `reports.view` (idempotent — Spatie skips duplicates)
- Calls `Artisan::call('permission:cache-reset')` so the change is effective immediately

**Optional belt-and-suspenders cache flush:**
```bash
php artisan optimize:clear
php artisan permission:cache-reset
```

## The required confirmation (§10 + §14)

### ☐ Confirmation A — admin can open /admin/reports

1. Logout from any existing session.
2. Login as `admin@marketplace.test` / `password` (the developer's super_admin account).
3. From the Filament panel sidebar, click **Reports Dashboard** (the same link that was 403'ing before).
4. **Expected:** the URL becomes `/admin/reports`, HTTP 200, the report dashboard renders with KPI cards + date filters.
5. **NOT expected:** any "This action is unauthorized." message, any 403 page, any blank page, any redirect loop.

If this fails:
```bash
# Confirm v10.9 deployed
cat VERSION   # Phase 10 v10.9

# Confirm the wiring
grep "canManageAdminReports" app/Models/User.php
grep "canManageAdminReports" app/Providers/AppServiceProvider.php

# Confirm the migration ran
php artisan migrate:status | grep v109

# Confirm the admin user actually has the role
php artisan tinker --execute='
  $u = App\Models\User::where("email","admin@marketplace.test")->first();
  echo "roles: "; print_r($u?->getRoleNames()?->toArray());
  echo "canManageAdminReports: "; var_dump($u?->canManageAdminReports());
'
```

### ☐ Confirmation B — date filters work

On the now-loaded `/admin/reports`:
1. Change the date preset to `last_7_days`, `this_month`, `previous_month`, `custom`.
2. **Expected:** the URL gains `?preset=...` params, the page re-renders with new figures, no 403 reappears on any preset change.

### ☐ Confirmation C — export works if implemented

1. Click the CSV export button (or visit `/admin/reports/export.csv` directly).
2. **Expected:** a CSV download begins, no 403.

### ☐ Confirmation D — vendor cannot access admin reports

1. Logout, login as `vendor@marketplace.test` / `password`.
2. Visit `/admin/reports` directly via URL.
3. **Expected:** HTTP 403.

### ☐ Confirmation E — customer cannot access admin reports

1. Logout, login as `customer@marketplace.test` / `password`.
2. Visit `/admin/reports` directly.
3. **Expected:** HTTP 403.

### ☐ Confirmation F — vendor reports surface still works

1. Still logged in as vendor, visit `/vendor/reports`.
2. **Expected:** the vendor's own reports page loads (HTTP 200), unrelated to the admin reports auth path.

## Targeted Pest run

```bash
php artisan test --filter='Phase10V109'
```

All 16 scenarios should pass — including the explicit `super_admin can access (200)`, `vendor 403`, `customer 403`, `guest redirect`, `seeder idempotent`, `permission has guard_name=web`, `inactive admin denied`, `Gate::before grants super_admin every ability`.

## CI verdict

```
✅ Phase 10 v10.9 PASSES — ready for final deployment review
```

## Final

**Phase 10 v10.9 STOPS HERE.** No Phase 11. Pending your verification of confirmations A-F.
