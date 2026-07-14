# Phase 11B.3 — Rollback Procedure

## Tier 1 — Disable via feature flag (no code revert)

Fastest. Data retained. Reversible in minutes.

```bash
# .env
PERSONALIZATION_ENABLED=false

php artisan config:clear
php artisan optimize:clear
```

Homepage will render without the personalized band. Guest and authenticated users see the pre-v11B.3 experience (health probes → featured products → top categories).

**Use this when:** the personalized homepage has a UX regression or the affinity ranking looks wrong but the DATA is fine. The tables remain populated so re-enabling is instant.

## Tier 2 — Revert code, keep data

Drops the React integration but keeps the DB tables (in case v11B.3.1 will re-use them).

```bash
tar -xzf marketplace-phase-11B-2-final-approved.tar.gz --strip-components=1 --overwrite \
    marketplace/app/Http/Controllers/HomeController.php \
    marketplace/app/Http/Controllers/CatalogController.php \
    marketplace/resources/js/Pages/Welcome.tsx \
    marketplace/routes/web.php \
    marketplace/routes/console.php

# Personalization services + models still present but unreachable
php artisan optimize:clear
rm -rf public/build/ node_modules/.vite/
npm ci && npm run build
```

The 3 tables remain populated. The scheduled tasks stop firing (routes/console.php reverted). Re-applying v11B.3 later re-hooks them without data loss.

**Use this when:** a code regression is discovered in the personalization service layer but the schema/data is trusted.

## Tier 3 — Full revert + drop migrations

Complete rollback to the formally-approved v11B.2 baseline.

```bash
# 1. Roll back migrations (drops 3 v11B.3 tables)
php artisan migrate:rollback --step=3

# 2. Confirm rollback
php artisan migrate:status | tail -5
# Expect: the 3 v11B.3 rows show Ran=No

# 3. Restore source from the v11B.2 baseline archive
tar -xzf marketplace-phase-11B-2-final-approved.tar.gz --strip-components=1 --overwrite

# 4. Clean build artifacts
php artisan optimize:clear
rm -rf public/build/ node_modules/.vite/
npm ci && npm run build

# 5. Verify
cat VERSION
# Expected: Phase 11B.2 v11B.2.2

php artisan test --filter=Phase11B22
# Expected: 45 passed

php artisan test --filter=Phase11B21
# Expected: 38 passed

php artisan test
# Expected: 675 passed (631 v11B.2.2-and-earlier + 44 v11B.2.1 with the difference from removing v11B.3)
```

**Use this when:** v11B.3 has a fundamental issue that requires a clean restart, OR when a critical CVE demands returning to the formally-approved baseline while a patched v11B.3.1 is prepared.

**Warning**: Tier 3 destroys the personalization tables. Customer view history and affinity data are LOST. If this is unacceptable, use Tier 2 instead and drop the tables manually after backup.

## Emergency contact

- Rebuild the affinity profiles after re-applying v11B.3: `php artisan personalization:rebuild`
- Recovery of the 3 tables from a rolled-back deployment is only possible from the pre-rollback backup (recommend keeping mysqldump before Tier 3).

## What NEVER to do

- Do NOT modify the formally-approved `marketplace-phase-11B-2-final-approved.tar.gz` — its SHA must remain the exact baseline.
- Do NOT run `php artisan migrate:fresh` in production — that drops the entire schema, not just v11B.3.
- Do NOT delete `customer_product_views` rows via `TRUNCATE` while workers are running — use `personalization:prune` which chunked-deletes safely.
