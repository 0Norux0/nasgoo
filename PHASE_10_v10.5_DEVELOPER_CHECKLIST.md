# Phase 10 v10.5 — Developer Checklist

## Step 0 — Verify archive integrity

```bash
sha256sum -c marketplace-phase-10-v10.5.tar.gz.sha256
# Expected: marketplace-phase-10-v10.5.tar.gz: OK
```

## Step 1 — Extract over the running app

```bash
cd /var/www/marketplace
tar -xzf /path/to/marketplace-phase-10-v10.5.tar.gz --strip-components=1 --overwrite
```

## Step 2 — Run the dev §4 demand: typecheck + build

This is the critical step that was silently failing for 4 rounds.

```bash
npm ci
npm run typecheck
npm run build
```

**Expected:** both commands exit 0. The build outputs a fresh `public/build/manifest.json` + compiled `assets/app-XXXX.js`.

If `npm run typecheck` STILL produces TS2344 / TS6133 / TS2503 errors → the deployed source is NOT v10.5. Verify:

```bash
php artisan marketplace:fingerprint
# Aggregate must match the canonical fingerprint in PHASE_10_v10.5_PACKAGE_INTEGRITY.md
```

If aggregate mismatches → re-extract the archive.

If aggregate matches but typecheck still fails → there may be errors I missed in files I didn't touch. Share `npm run typecheck` output verbatim; I'll target them in v10.6.

## Step 3 — Full deploy

```bash
./scripts/deploy.sh
```

This will re-run `npm ci && npm run build` (just to be sure) + flush all caches + restart workers + reload PHP-FPM.

## Step 4 — Verify the v10.1+v10.2+v10.3 React fixes NOW actually reach the browser

With `npm run build` finally succeeding, the compiled JS should now include:

- AdminLayout.tsx → `/admin/reports` renders without blank page
- Vendor/Orders/Show.tsx → status dropdown visible at `data-testid='vendor-order-status-dropdown'`
- VendorLayout.tsx → Reports link visible in vendor nav; vendor-mobile-menu hamburger visible at < lg
- StorefrontLayout.tsx → `· v Phase 10 v10.5` in footer; storefront-mobile-menu hamburger at < md

Quick test:
1. Sign in as `vendor@marketplace.test` → open any `/vendor/orders/{id}` page → status dropdown MUST be visible above the order detail
2. Browser DevTools at 375px viewport → run `document.documentElement.scrollWidth > window.innerWidth` → MUST return `false` on every page
3. Sign in as `admin@marketplace.test` → click Reports Dashboard in Filament nav → `/admin/reports` MUST render with KPI cards, not a blank page

## Step 5 — Run the Pest suite

```bash
php artisan test --filter='Phase10'
```

All 55 Phase 10 scenarios (13 v10.0 + 14 v10.1 + 8 v10.2 + 8 v10.3 + 6 v10.4 + 6 v10.5) should pass.

## Acceptance gate

- ☐ `npm run typecheck` exits 0
- ☐ `npm run build` exits 0; manifest.json is freshly generated
- ☐ `php artisan marketplace:fingerprint` aggregate matches canonical
- ☐ `php artisan marketplace:verify-fixes` shows all ✓
- ☐ Browser footer shows `· v Phase 10 v10.5`
- ☐ `/vendor/orders/{id}` status dropdown visible
- ☐ `/admin/reports` renders
- ☐ Mobile pages don't overflow at 375px
- ☐ `php artisan test --filter='Phase10'` green
- ☐ CI shows `✅ Phase 10 v10.5 PASSES`

If all boxes check, the marketplace is finally ready for the launch checklist.

If `npm run typecheck` still fails AND the fingerprint matches v10.5 canonical, share the exact tsc output — there may be other typing issues in files I haven't audited, and I can target them precisely.
