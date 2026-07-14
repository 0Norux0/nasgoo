# Phase 11B.3 v11B.3.2 — Developer Checklist

## 1. Backup

```bash
git tag phase-11B-3-1-baseline    # v11B.3.1 archive already preserved in outputs
git checkout -b phase-11B-3-2-modular-mobile-performance-links
mysqldump --single-transaction ... > backup-before-11b32.sql
```

## 2. Extract archive

```bash
tar -xzf marketplace-phase-11B-3-2-modular-mobile-performance-links.tar.gz
diff -r . /path/to/extracted-marketplace   # confirm SHA-match per PACKAGE_INTEGRITY.md
```

## 3. Migrate

```bash
php artisan migrate:status                       # 1 new v11B.3.2 row Ran=No
php artisan migrate                               # applies index migration
php artisan migrate:status | grep 2026_10        # confirm Ran=Yes
```

The migration adds 7 indexes (`hasIndex`-guarded, safe to re-run).

## 4. Route sanity

```bash
php artisan route:list | grep vendor.settings    # 2 new routes
php artisan route:list | grep -i site-settings   # v11B.3.1 routes still present
```

## 5. Tests — the critical proof for §21 broken-link audit

```bash
php artisan test --filter=Phase11B32   # 37 v11B.3.2 scenarios
php artisan test --filter=Phase11B31   # 42 v11B.3.1 regression
php artisan test --filter=Phase11B3    # 56 v11B.3 regression
php artisan test --filter=Phase11B22   # 45 v11B.2.2 regression
php artisan test                        # 810 total
```

The `Phase11B32` filter includes the sidebar walkthrough — every URL is hit; any 404 fails.

## 6. Build

```bash
npm ci
npm run typecheck      # must pass 0 errors on new .tsx files
npm run build
```

## 7. Manual verification

### Vendor Settings 404 fix
- [ ] Log in as `vendor@marketplace.test` (approved vendor)
- [ ] Click Settings in the sidebar
- [ ] Expect: `/vendor/settings` renders with the store-profile form + status card + 3 placeholder cards
- [ ] Change business name, save, confirm success flash
- [ ] Try `javascript:alert(1)` as website — expect validation error
- [ ] Log out, log in as customer, hit `/vendor/settings` — expect 403 (not 200, not 404)

### Admin performance
- [ ] Log in as super admin, visit `/admin` dashboard
- [ ] With Debugbar enabled: first render fires ≤12 queries for the StatsOverview widget
- [ ] Refresh: subsequent renders fire 0 queries (cache hit) — widget renders instantly
- [ ] Verify no visible change in the stats numbers between refresh and 5-minute-later refresh (cache TTL)

### Broken-link click-through
- [ ] Log in as vendor, click every sidebar item — no 404
- [ ] Log in as customer, click every storefront nav item — no 404
- [ ] Log in as super admin, visit `/admin/site-settings` — no 404

### RTL
- [ ] Switch locale to Arabic
- [ ] Open vendor drawer on mobile — slides from RIGHT
- [ ] Vendor Settings form labels rendered in Arabic

## 8. Rollback readiness

Keep `marketplace-phase-11B-3-1-baseline.tar.gz` accessible. Procedure in `PHASE_11B_3_2_ROLLBACK.md`.
