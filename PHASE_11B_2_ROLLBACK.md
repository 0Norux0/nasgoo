# Phase 11B.2 — Rollback Instructions

3-tier rollback per dev §47 (item 17).

## Tier 1 — Config-level flags (no code change)

Disable any/all recommendation modules without re-deploying:

```bash
# Disable ALL recommendations (page omits sections)
RECOMMENDATIONS_ENABLED=false

# Or disable individual modules
RECOMMENDATIONS_SIMILAR_ENABLED=false
RECOMMENDATIONS_FBT_ENABLED=false
RECOMMENDATIONS_ALSO_BOUGHT_ENABLED=false
RECOMMENDATIONS_ANALYTICS_ENABLED=false
RECOMMENDATIONS_ADMIN_CURATED_ENABLED=false
```

After editing `.env`:

```bash
php artisan optimize:clear
```

The product detail page renders cleanly with the disabled sections omitted. No table drops, no data loss. The `product_pair_stats` and `recommendation_events` tables remain populated for when the flag is flipped back.

## Tier 2 — Partial revert (keep tables, revert code)

If a specific service has a problem but the data should be preserved:

```bash
# Restore individual files from the v11B.1.2 baseline
tar -xzf marketplace-phase-11B-1-2-dynamic-localization-mobile-search.tar.gz \
    --strip-components=1 --overwrite \
    marketplace/app/Http/Controllers/CartController.php \
    marketplace/app/Http/Controllers/CatalogController.php \
    marketplace/app/Providers/AppServiceProvider.php \
    marketplace/resources/js/Pages/Catalog/Show.tsx \
    marketplace/routes/web.php \
    marketplace/routes/console.php

php artisan optimize:clear
rm -rf public/build/ node_modules/.vite/
npm ci && npm run build
```

The 4 v11B.2 tables remain. The recommendation tables don't break the v11B.1.2 storefront (the partial revert removes the controller wiring that reads them).

## Tier 3 — Full revert to v11B.1.2 (drop tables + restore archive)

```bash
# 1. Drop the 4 v11B.2 tables (in reverse migration order)
php artisan migrate:rollback --step=4

# Verify rollback worked
php artisan migrate:status | grep 2026_07_01
# Should show all 4 v11B.2 migrations as 'Pending' (no longer 'Ran')

# 2. Extract v11B.1.2 archive over the workspace
tar -xzf marketplace-phase-11B-1-2-dynamic-localization-mobile-search.tar.gz \
    --strip-components=1 --overwrite

# 3. Rebuild
php artisan optimize:clear
rm -rf public/build/ node_modules/.vite/
npm ci && npm run build

# 4. Verify
cat VERSION                              # → Phase 11B.1 v11B.1.2
php artisan test --filter=Phase11B12     # 37 v11B.1.2 scenarios pass
php artisan test                          # 542 total
```

The v11B.1.2 translation system, v11B.1.1 vendor Arabic forms, v11B.1 smart search, and all preceding markers remain intact — only Phase 11B.2 work is removed.

## Cache cleanup after any revert

```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

This clears any cached recommendation payloads under the `rec:v11b2:*` key prefix.

## Data preservation

After Tier 1 or Tier 2 rollback:
- `product_pair_stats` rows: PRESERVED
- `product_recommendations` rows: PRESERVED (currently unused but reserved)
- `admin_product_relationships` rows: PRESERVED
- `recommendation_events` rows: PRESERVED (historical analytics retained)

After Tier 3 rollback:
- All 4 v11B.2 tables: DROPPED
- v11B.1.2 product_translations: PRESERVED
- All earlier translation JSON columns: PRESERVED
- No customer-visible content is lost; the storefront simply reverts to the v11B.1.2 product detail layout (no recommendation sections).

## Re-applying after rollback

To re-apply Phase 11B.2 after a Tier 3 rollback:

```bash
tar -xzf marketplace-phase-11B-2-recommendations.tar.gz --strip-components=1
php artisan migrate
php artisan recommendations:generate --truncate
npm ci && npm run build
```

All work is preserved in the archived tarball.
