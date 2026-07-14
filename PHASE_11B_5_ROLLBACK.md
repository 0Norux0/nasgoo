# Phase 11B.5 — Rollback Procedure

## Tier 1 — Revert only ProductQualityService + Manager fixes

Restores the pre-v11B.5 buggy code but keeps the corrected Pest suite. Useful if the images() relation query is somehow causing an issue that the buggy is_array() version didn't (very unlikely).

```bash
tar -xzf marketplace-phase-11B-4-baseline.tar.gz \
    --strip-components=1 --overwrite \
    marketplace/app/Services/VendorIntelligence/ProductQualityService.php \
    marketplace/app/Services/VendorIntelligence/VendorIntelligenceManager.php

php artisan optimize:clear
```

## Tier 2 — Full revert to v11B.4 baseline

Reverts every v11B.5 code change AND the Pest suite. No schema changes to roll back.

```bash
tar -xzf marketplace-phase-11B-4-baseline.tar.gz \
    --strip-components=1 --overwrite

php artisan optimize:clear
rm -rf public/build node_modules/.vite
npm ci && npm run build

cat VERSION                              # → Phase 11B.4
```

Note: after Tier 2, the Pest suite will FAIL on 40+ scenarios (that's why v11B.5 was needed). Rollback ONLY if v11B.5 introduces a worse regression than v11B.4's known bugs.

## Tier 3 — Chain back to v11B.3.3 approved baseline

Use `marketplace-phase-11B-3-3-final-approved.tar.gz` (SHA `ed778b3394902d5ec89834a143a64a2cbe66b794f3600313783f9155333cf2e7`). This removes ALL vendor intelligence functionality.

```bash
tar -xzf marketplace-phase-11B-3-3-final-approved.tar.gz \
    --strip-components=1 --overwrite

php artisan optimize:clear
# v11B.4 migration must be rolled back
php artisan migrate:rollback --step=1     # drops the 4 vendor_intelligence tables
npm ci && npm run build

cat VERSION                              # → Phase 11B.3 v11B.3.3
```

## What NEVER to do

- Do NOT modify the immutable v11B.4 baseline archive
- Do NOT drop the vendor_intelligence tables outside of `php artisan migrate:rollback` (loss of dismiss/snooze history)
- Do NOT run `php artisan migrate:fresh` in production
