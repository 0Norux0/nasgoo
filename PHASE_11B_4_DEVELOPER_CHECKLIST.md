# Phase 11B.4 — Developer Checklist

## 1. Backup

```bash
git tag phase-11B-3-3-final-approved
git checkout -b phase-11B-4-vendor-intelligence
mysqldump --single-transaction ... > backup-before-11b4.sql
```

## 2. Extract archive

```bash
tar -xzf marketplace-phase-11B-4-vendor-intelligence.tar.gz
diff -r . /path/to/extracted-marketplace   # SHA-match per PACKAGE_INTEGRITY.md
```

## 3. Migrate

```bash
php artisan migrate:status | tail -3        # v11B.4 migration should show Ran=No
php artisan migrate                          # creates 4 tables
php artisan migrate:status | grep 2026_11   # confirm Ran=Yes
```

## 4. Route sanity

```bash
php artisan route:list | grep intelligence  # 4 new routes
```

## 5. Tests

```bash
php artisan test --filter=Phase11B4    # 53 v11B.4 scenarios
php artisan test --filter=Phase11B33   # 33 v11B.3.3 regression
php artisan test --filter=Phase11B32   # 37 v11B.3.2 regression
php artisan test --filter=Phase11B31   # 42 v11B.3.1 regression
php artisan test --filter=Phase11B3    # 56 v11B.3 regression
php artisan test                        # 896 total
```

## 6. Build

```bash
npm ci
npm run typecheck    # 0 errors on new .tsx files
npm run build
```

## 7. Populate initial data

```bash
php artisan vendor-intelligence:generate
# Expect: "Complete. N vendors processed, 0 failed."
```

## 8. Schedule (optional — recommended)

Add to `app/Console/Kernel.php` or the `routes/console.php` schedule:

```php
$schedule->command('vendor-intelligence:generate')->hourly();
$schedule->command('vendor-intelligence:prune')->dailyAt('03:00');
```

## 9. Manual verification

**Vendor side**:
- Log in as `vendor@marketplace.test` (approved vendor with real product data)
- Load `/vendor` — intelligence panel renders with summary + alerts
- Alerts sorted critical → info
- Click Snooze on a non-critical alert → alert vanishes → check DB: `vendor_intelligence_alerts.status = 'snoozed'`
- Try Dismiss on a critical alert → the button should not exist on those rows
- Click Review → navigates to product edit page
- Change locale to Arabic → alert titles render in Arabic

**Admin side**:
- Log in as super_admin → `/admin/vendor-intelligence`
- Rollup shows total vendors + total alerts + avg completion + avg quality
- Filter by `low_stock` → table sorts by low_stock_count desc
- Any customer logged in → `/admin/vendor-intelligence` returns 403

**Cross-vendor isolation**:
- Log in as vendor A, note IDs of alerts
- Log in as vendor B in another browser
- vendor B's `/vendor/intelligence` JSON shouldn't include any of vendor A's entity_id values

## 10. Threshold tuning

```bash
# Via SiteSettingsService (from tinker or admin)
php artisan tinker
> app(\App\Services\Settings\SiteSettingsService::class)->set('vendor_intelligence.low_stock_threshold', 10);
> exit

php artisan vendor-intelligence:generate    # regenerate with new threshold
```

## 11. Rollback readiness

Keep `marketplace-phase-11B-3-3-final-approved.tar.gz` accessible.
Procedure in `PHASE_11B_4_ROLLBACK.md`.
