# Phase 11A — Rollback Instructions

Per dev §18.11.

## When to roll back

Roll back to **Phase 10 final-approved** if v11A introduces ANY of:
- A render crash on the homepage (regression of the v10.16 fix)
- Broken header navigation (regression of Phase 10 v10.6 mobile categories)
- A page that previously worked now returns 500 (Phase 10 had 29/29 passing surfaces)
- A measurable performance regression (page load > 2× the Phase 10 baseline)
- Layout breakage at a required breakpoint (320, 375, 390, 414, 768, 1024)

v11A is a pure frontend/CSS change. It modified ZERO PHP files, ZERO migrations, ZERO routes. Rollback is therefore SAFE and FAST — no DB schema changes to revert, no data migration to undo.

## Three rollback paths

### Option 1 — Git tag (fastest, ~30 seconds)

```bash
cd /var/www/marketplace
sudo systemctl stop nginx          # stop traffic during swap
git fetch --tags
git checkout phase-10-final-approved
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan optimize:clear
sudo systemctl start nginx
```

Verify:
```bash
cat VERSION                         # → Phase 10 v10.16
```

### Option 2 — Archive restore (if Git tag not available)

```bash
cd /var/www
# Move broken v11A aside (don't delete yet — for forensics)
sudo mv marketplace marketplace.v11a.broken.$(date +%Y%m%d-%H%M%S)

# Restore the preserved Phase 10 final-approved baseline
sudo mkdir marketplace
sudo tar -xzf /backup/marketplace-phase-10-final-approved.tar.gz -C /var/www/
sudo chown -R www-data:www-data marketplace

cd marketplace
# Restore environment + uploads + keys (NOT baselined)
sudo cp ../marketplace.v11a.broken.*/.env .env
sudo cp -r ../marketplace.v11a.broken.*/storage/app/. storage/app/

composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan optimize:clear

sudo systemctl restart php8.3-fpm nginx
```

Verify:
```bash
cat VERSION                         # → Phase 10 v10.16
sha256sum marketplace-phase-10-final-approved.tar.gz
# → ef0f4872bd67648a17740712806261d61be3014f31322693a780579d160537bf
```

### Option 3 — Frontend-only rollback (granular)

Since v11A touched only frontend files, you can revert the specific files without doing a full archive restore:

```bash
cd /var/www/marketplace
# Restore only the 6 frontend files v11A modified
git checkout phase-10-final-approved -- \
    tailwind.config.js \
    resources/css/app.css \
    resources/js/Layouts/StorefrontLayout.tsx \
    resources/js/Pages/Welcome.tsx \
    VERSION

# Remove the 3 new component files (didn't exist in Phase 10)
rm -rf resources/js/Components/ui/v11/

# Remove the v11A Pest test
rm tests/Feature/Phase11ARegressionTest.php

# Restore the CI config
git checkout phase-10-final-approved -- .github/workflows/ci.yml

npm run build
php artisan optimize:clear
```

This is the most surgical option — it returns the codebase to Phase 10 v10.16 exactly while preserving any work done elsewhere (e.g., environment changes, database state).

## What you do NOT need to do

- **No database rollback needed.** v11A made zero schema changes.
- **No data migration.** No rows touched.
- **No cache flush beyond `php artisan optimize:clear`.** Spatie permissions cache + the v10.14 health cache are unaffected (their structure is unchanged).
- **No npm dependency rollback.** v11A added zero new packages; `package.json` is identical to Phase 10.

## Verification after rollback

```bash
cat VERSION                                            # → Phase 10 v10.16
php artisan test --filter=Phase10V1016                 # 20/20 pass
php artisan test --filter=Phase10V1015                 # 20/20 pass
php artisan test                                       # full suite 244 passes
php artisan optimize:clear
npm run typecheck                                      # 0 errors
npm run build                                          # success
```

Then the dev's original Phase 10 29-area manual walkthrough should pass (as it did at approval).

## What to do AFTER rolling back

1. **Capture diagnostics** from the broken v11A deploy:
   - Browser DevTools Console screenshot (the exact error message + file/line)
   - Network tab showing what assets loaded
   - Lighthouse score
   - `npm run build` output
   - `php artisan test --filter=Phase11A` output

2. **Tag the broken state** for forensics:
   ```bash
   cd /var/www/marketplace.v11a.broken.*
   tar -czf /backup/v11a-failure-snapshot.tar.gz .
   ```

3. **Report back** with the diagnostics. A v11A.1 patch can be targeted at the specific failure mode — not a full re-redesign.

## Cannot roll back partial state

The three new component files (`Button.tsx`, `primitives.tsx`, `ProductCard.tsx`) and the design tokens in `tailwind.config.js` are a coherent set. If `Welcome.tsx` references `@/Components/ui/v11/Button` but that file is removed, the build will fail. So rollback options 2 and 3 must complete BEFORE the next `npm run build`, or the build will produce errors.

If you're mid-rollback and `npm run build` fails, you've likely removed component files but kept Welcome.tsx. Either:
- Finish the rollback (run all the rollback commands then `npm run build`)
- Or restore from the v11A archive: `tar -xzf marketplace-phase-11A-ui-redesign.tar.gz` and start the rollback from a known state

## Restore the v11A archive after rollback

If you roll back, then later want to retry:

```bash
sha256sum -c marketplace-phase-11A-ui-redesign.tar.gz.sha256
tar -xzf marketplace-phase-11A-ui-redesign.tar.gz --strip-components=1 --overwrite
npm ci && npm run build
php artisan optimize:clear
```

The archive's SHA-256 is captured in `PHASE_11A_PACKAGE_INTEGRITY.md`. Verify before extracting.
