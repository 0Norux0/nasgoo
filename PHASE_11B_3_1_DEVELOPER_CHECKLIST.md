# Phase 11B.3 v11B.3.1 — Developer Checklist

Concise action list for verifying and deploying v11B.3.1.

## 1. Backup

```bash
git tag phase-11B-3-final-approved
git checkout -b phase-11B-3-1-modular-mobile-ux
mysqldump --single-transaction ... > backup-before-11b31.sql
```

## 2. Extract archive

```bash
tar -xzf marketplace-phase-11B-3-1-modular-mobile-ux.tar.gz
diff -r . /path/to/extracted-marketplace   # confirm SHA-match per PACKAGE_INTEGRITY.md
```

## 3. Migrate

```bash
php artisan migrate:status                          # 1 v11B.3.1 row should be Ran=No
php artisan migrate                                  # applies idempotently (guarded by hasColumn)
php artisan migrate:status | grep 2026_09_01        # confirm Ran=Yes
```

The migration adds `updated_by` (nullable) + `is_translatable` (default false) to the existing `settings` table. Zero data loss; existing rows unchanged.

## 4. Route sanity

```bash
php artisan route:list | grep -i site-settings     # 4 routes
php artisan route:list | grep -i vendor            # confirm vendor routes preserved
```

## 5. Tests

```bash
php artisan test --filter=Phase11B31   # 42 v11B.3.1 scenarios
php artisan test --filter=Phase11B3    # 56 v11B.3 regression
php artisan test --filter=Phase11B22   # 45 v11B.2.2 regression
php artisan test                        # 773 total
```

## 6. Build

```bash
npm ci
npm run typecheck           # must pass — 0 errors on new .tsx files
npm run build
```

## 7. Smoke verification (manual)

- [ ] `/` still renders as guest + as customer (siteSettings loads)
- [ ] `/admin/site-settings` renders for super_admin
- [ ] `/admin/site-settings` returns 403 for customer, redirects unauthenticated
- [ ] Change branding.site_name → save → reload storefront (no cache-clear command) → header shows new name via siteSettings share
- [ ] Change appearance.color_primary → save → siteSettings.appearance.color_primary reflects new hex (visual injection is a follow-up)
- [ ] Change footer.description (Arabic value) → switch locale to ar → footer shows Arabic value
- [ ] Attempt to save `javascript:alert(1)` as facebook URL → 422 error
- [ ] Attempt to save `not-a-color` as color_primary → 422 error
- [ ] Upload an SVG containing `<script>` → rejected
- [ ] Reset branding to defaults → all rows deleted → falls back to config/site.php defaults

Mobile / responsive:
- [ ] `/orders` at 320px shows card list (no horizontal scroll)
- [ ] `/orders` at 1024px shows table
- [ ] `/bookings` at 375px shows cards
- [ ] `/tickets` at 414px shows cards + "New ticket" button visible in PageHeader
- [ ] `/vendor` at desktop shows persistent sidebar
- [ ] `/vendor` at mobile: hamburger opens drawer, Escape closes, backdrop click closes, Tab loops within panel
- [ ] Switch locale to Arabic: drawer slides from RIGHT (RTL start-side)

## 8. Feature-flag defaults

Site-settings feature is not gated by an env var — the settings service is always on. To disable JUST the admin UI without a full rollback:

```
# Block the routes at the reverse-proxy / firewall level, e.g. nginx
location /admin/site-settings { return 404; }
```

The service remains available for programmatic use (seeders, tests).

## 9. Data / performance

The `settings` table typically holds <200 rows even at scale (one row per configured key across ~10 groups). Grouped reads are `WHERE group = ?` which uses the existing index. Every read after cache warm-up returns from `Cache::remember` — no DB hit.

If you notice storefront settings changes not appearing after admin save, check:
- Cache driver is functional (Redis/database/file)
- CDN / reverse proxy cache TTL for the Inertia response

## 10. Rollback readiness

Keep `marketplace-phase-11B-3-final-approved.tar.gz` accessible. The 3-tier rollback procedure is in `PHASE_11B_3_1_ROLLBACK.md`.
