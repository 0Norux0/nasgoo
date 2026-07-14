# Phase 11B.5 — Developer Checklist

## 1. Backup

```bash
git tag phase-11B-4-baseline
git checkout -b phase-11B-5-vendor-intelligence-repair
```

## 2. Extract

```bash
tar -xzf marketplace-phase-11B-5-vendor-intelligence-repair.tar.gz
```

## 3. Verify VERSION and archive integrity

```bash
cat VERSION                          # → Phase 11B.5
sha256sum -c marketplace-phase-11B-5-vendor-intelligence-repair.tar.gz.sha256
```

## 4. Migration status check

```bash
php artisan migrate:status | grep vendor_intelligence
```

Expected: 4 tables from v11B.4 migration should show as "Ran". If they show as "Pending" or missing, this is a v11B.4 migration issue — run:

```bash
php artisan migrate
```

Expected new migrations added by v11B.5: **0** (this is a code-only bug fix).

## 5. Generate initial vendor intelligence data

```bash
php artisan vendor-intelligence:generate
```

This populates `vendor_intelligence_summaries` for every approved vendor. Without this, the dashboard shows zeros (not N/A, but zeros).

## 6. Run the tests

```bash
php artisan test --filter=Phase11B4    # 56 v11B.5-rewritten scenarios
```

**If any fail**, the failure output will tell you exactly which schema assumption was wrong. Please share the exact failure message.

```bash
php artisan test --filter=Phase11B33   # 33 v11B.3.3 regression
php artisan test --filter=Phase11B32   # 37 v11B.3.2 regression
php artisan test                       # ~899 total
```

## 7. Frontend

```bash
npm ci
npm run typecheck     # expected: 0 errors on all v11B.4 tsx files (no changes in v11B.5)
npm run build
```

## 8. Browser walkthrough

Log in as an approved vendor (`vendor@marketplace.test`) with a product that has stock=0:

1. Navigate to `/vendor` — expect the "Vendor intelligence" panel to load below the status banner
2. Check the summary counters — expect real numbers, not "N/A"
3. Check the alerts list — expect at least one "critical" priority "Out of stock" alert
4. Try to dismiss the "Out of stock" alert — expect no dismiss button (critical, non-dismissable)
5. Try to snooze a low-stock alert (create one first if needed) — expect it to disappear from the panel
6. Reload the page — expect the snoozed alert to stay hidden until its `expires_at` passes

Log in as super_admin (`admin@marketplace.test`):

1. Navigate to `/admin/vendor-intelligence` — expect the aggregate overview page
2. Try each filter chip (Low stock / Incomplete stores / Missing Arabic / Many pending)
3. Verify no customer-level personalization or private support ticket text is exposed

Vendor isolation check (via curl or Postman):

```bash
# As vendor A, try to dismiss vendor B's product alert
curl -X POST /vendor/intelligence/dismiss \
  -H "Cookie: laravel_session=..." \
  -d '{"suggestion_type":"low_stock","entity_type":"product","entity_id":<vendor_B_product_id>}'

# Expected: 302 back to /vendor, but vendor B's alert row unchanged
```

## 9. Rollback readiness

v11B.4 baseline preserved as `marketplace-phase-11B-4-baseline.tar.gz` (same SHA as v11B.4 archive: `8739eade96520f1464f88bf5aa742f017c8895f3a75dc41d87a7e574e9f81ceb`).

See PHASE_11B_5_ROLLBACK.md for procedure.

## 10. Sign off

Only close v11B.5 as approved once:
- [ ] `php artisan test --filter=Phase11B4` shows 56 passing
- [ ] Vendor dashboard shows real numbers in browser
- [ ] Admin overview page loads
- [ ] Snooze/dismiss actions persist through page reload
- [ ] Vendor A cannot affect vendor B's data via crafted requests
