# Phase 11B.3 v11B.3.3 — Developer Checklist

## 1. Backup

```bash
git tag phase-11B-3-2-baseline
git checkout -b phase-11B-3-3-real-modular-mobile-performance-repair
```

## 2. Extract archive

```bash
tar -xzf marketplace-phase-11B-3-3-real-modular-mobile-performance-repair.tar.gz
diff -r . /path/to/extracted-marketplace   # SHA-match per PACKAGE_INTEGRITY.md
```

## 3. No migrations

v11B.3.3 is CSS + JS + Blade only. No schema changes.

```bash
php artisan migrate:status | tail -5   # last row is v11B.3.2 index migration
```

## 4. Route sanity

No route changes. Existing routes preserved:

```bash
php artisan route:list | grep -i site-settings   # v11B.3.1 admin routes
php artisan route:list | grep -i vendor.settings # v11B.3.2 vendor Settings routes
```

## 5. Tests

```bash
php artisan test --filter=Phase11B33   # 33 v11B.3.3 scenarios
php artisan test --filter=Phase11B32   # 37 v11B.3.2 regression
php artisan test --filter=Phase11B31   # 42 v11B.3.1 regression
php artisan test --filter=Phase11B3    # 56 v11B.3 regression
php artisan test                        # 843 total
```

## 6. Build

```bash
npm ci
npm run typecheck    # 0 errors on new files (SharedProps.siteSettings type added)
npm run build
```

## 7. Manual verification — the key browser test

1. Log in as super_admin, visit `/admin/site-settings`
2. Branding tab: set `site_name = "TestStore"`, `logo_url = "/images/logo.svg"` (or any URL to an image you have), save
3. Appearance tab: change `color_primary = #ff0000`, save
4. Homepage tab: toggle `sections.featured.enabled` off, save
5. Load `/` (as guest, no cache-clear):
   - [ ] Header shows "TestStore"
   - [ ] Logo image visible (or gradient monogram fallback if no image)
   - [ ] Featured Products section is HIDDEN
   - [ ] View page source: `<style id="v11b33-appearance-vars">` contains `--color-primary: #ff0000`
   - [ ] `<meta name="theme-color" content="#ff0000">` present

6. Load `/vendor/orders/{any_id}` at 375px viewport as approved vendor:
   - [ ] "Update status:" text renders on a single line, NOT letter-by-letter
   - [ ] Select dropdown next to it

7. Load `/cart` at 375px:
   - [ ] Content doesn't overflow horizontally
   - [ ] Left and right gutters are 16px

8. Load `/checkout` at 375px:
   - [ ] Same — no overflow, 16px gutters

9. Load `/products/{any_slug}` at 375px:
   - [ ] Same

10. Load `/vendor/settings`:
    - [ ] Still works (v11B.3.2 preservation)

## 8. Rollback readiness

v11B.3.2 archive preserved as `marketplace-phase-11B-3-2-modular-mobile-performance-links.tar.gz`.
Procedure in `PHASE_11B_3_3_ROLLBACK.md`.
