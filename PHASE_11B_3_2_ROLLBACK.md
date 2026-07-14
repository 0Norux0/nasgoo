# Phase 11B.3 v11B.3.2 — Rollback Procedure

## Tier 1 — Revert code only (keep indexes, keep data)

The added indexes are harmless if the app code reverts. The vendor Settings route disappears (which restores the 404, so use Tier 1 ONLY if you want to bring back the 404 while keeping the schema).

```bash
tar -xzf marketplace-phase-11B-3-1-baseline.tar.gz --strip-components=1 --overwrite \
    marketplace/app/Filament/Widgets/StatsOverview.php \
    marketplace/routes/web.php

php artisan optimize:clear
rm -rf public/build && npm ci && npm run build
```

**Use this when**: the widget cache misbehaves in an unforeseen way — you want the pre-cache queries back but keep the indexes for performance.

## Tier 2 — Full code revert, drop v11B.3.2 migration

```bash
# 1. Roll back the index migration
php artisan migrate:rollback --step=1

# 2. Full source revert to v11B.3.1
tar -xzf marketplace-phase-11B-3-1-baseline.tar.gz --strip-components=1 --overwrite

# 3. Clean build
php artisan optimize:clear
rm -rf public/build node_modules/.vite
npm ci && npm run build

# 4. Verify
cat VERSION                            # → Phase 11B.3 v11B.3.1
php artisan test --filter=Phase11B31   # 42 scenarios still pass
```

**Use this when**: v11B.3.2 introduces a regression that Tier 1 doesn't cover.

## Tier 3 — Same as Tier 2 (there is no separate Tier 3 for v11B.3.2)

v11B.3.2 doesn't ship destructive data changes. The Tier 3 destination is the v11B.3.1 baseline, same as Tier 2. If you want to also roll back v11B.3.1 → v11B.3, see the v11B.3.1 rollback doc (deferred — chain rollbacks are the developer's responsibility).

## What NEVER to do

- Do NOT modify the immutable `marketplace-phase-11B-3-1-baseline.tar.gz` archive
- Do NOT drop the `users`, `vendors`, `products`, `orders`, `categories`, or `audit_logs` tables — the migration adds indexes to existing tables, `migrate:rollback` only drops the indexes
- Do NOT run `php artisan migrate:fresh` in production
