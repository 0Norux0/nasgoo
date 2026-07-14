# Phase 11B.4 v11B.4.2 — Rollback Procedure

## Tier 1 — Rollback ONLY the v11B.4.2 migration + revert code

Removes the dedupe/stale columns but preserves v11B.4 vendor intelligence data (summaries, alerts, feedback).

```bash
# 1. Roll back the migration
php artisan migrate:rollback --step=1
# Expected: 2026_12_01_000001_add_vendor_intelligence_dedupe_and_stale rolled back

# 2. Extract v11B.5 baseline over the current workspace
tar -xzf marketplace-phase-11B-5-vendor-intelligence-repair.tar.gz \
    --strip-components=1 --overwrite

php artisan optimize:clear
rm -rf public/build node_modules/.vite
npm ci && npm run build

cat VERSION    # → Phase 11B.5
```

## Tier 2 — Full revert to v11B.4 baseline

Removes v11B.4.2 AND v11B.5 code changes AND the 2026_12_01 migration. Keeps v11B.4 architecture.

```bash
# 1. Roll back the migration
php artisan migrate:rollback --step=1

# 2. Extract v11B.4 baseline
tar -xzf marketplace-phase-11B-4-baseline.tar.gz \
    --strip-components=1 --overwrite

php artisan optimize:clear
rm -rf public/build node_modules/.vite
npm ci && npm run build

cat VERSION    # → Phase 11B.4
```

Note: after Tier 2 you'll be back to the buggy v11B.4 Pest suite. That's expected — Tier 2 is for when v11B.4.2 introduces a worse regression than v11B.4's known bugs (unlikely).

## Tier 3 — Chain back to v11B.3.3 approved baseline

Removes ALL vendor intelligence functionality. Use `marketplace-phase-11B-3-3-final-approved.tar.gz` (SHA `ed778b3394902d5ec89834a143a64a2cbe66b794f3600313783f9155333cf2e7`).

```bash
# 1. Roll back all vendor intelligence migrations
php artisan migrate:rollback --step=2
# This drops the 4 vendor_intelligence_* tables AND the v11B.4.2 columns

# 2. Extract v11B.3.3 approved baseline
tar -xzf marketplace-phase-11B-3-3-final-approved.tar.gz \
    --strip-components=1 --overwrite

php artisan optimize:clear
rm -rf public/build node_modules/.vite
npm ci && npm run build

cat VERSION    # → Phase 11B.3 v11B.3.3
```

## What NEVER to do

- Do NOT modify the immutable baseline archives (`marketplace-phase-11B-4-baseline.tar.gz`, `marketplace-phase-11B-3-3-final-approved.tar.gz`)
- Do NOT drop the vendor_intelligence tables outside `migrate:rollback` (loss of dismiss/snooze history)
- Do NOT `migrate:fresh` in production
- Do NOT downgrade past v11B.3.3 without a fresh directive — v11B.2.2 canonical pricing was a critical fix
