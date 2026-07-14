# Phase 11B.1 — Rollback Procedure

Three rollback strategies, from least to most invasive. Choose based on the severity of the issue.

## Tier 1 — Feature flag rollback (instant, no code/file changes)

Each v11B.1 capability is gated by a config flag. Setting `false` reverts to v11A.5 behavior WITHOUT removing code.

### Disable smart search ranking → revert to v11A.5 LIKE search

```bash
# .env
SEARCH_FEATURE_SMART=false

php artisan config:clear
```

Result: `CatalogController::index` and `SearchSuggestionController` fall back to the v11A.5 `LOWER(name) LIKE '%q%'` path. Weighted scoring + score expression no longer applied. All v11B.1 services remain on disk.

### Disable individual capabilities

```bash
SEARCH_FEATURE_SUGGESTIONS=false       # /search/suggestions returns empty groups but 200
SEARCH_FEATURE_SYNONYMS=false          # SynonymService::expand returns [original] only
SEARCH_FEATURE_DID_YOU_MEAN=false      # DidYouMeanService::suggest returns null
SEARCH_FEATURE_RECENT=false            # Recent group empty for all users
SEARCH_FEATURE_POPULAR=false           # Popular group empty
SEARCH_FEATURE_FACETS=false            # buildFacets returns []
SEARCH_FEATURE_ANALYTICS=false         # recordSearch is a no-op (no DB writes)
```

After any flag change:
```bash
php artisan config:clear
php artisan cache:clear
```

## Tier 2 — Partial revert (replace v11B.1 frontend or controller only)

If a specific layer misbehaves, restore that layer from the v11A.5 archive:

```bash
# Pull v11A.5 archive
tar -xzf marketplace-phase-11A-final-approved.tar.gz -C /tmp/v11a5-rollback

# Replace controllers (reverts Catalog enhancements)
cp /tmp/v11a5-rollback/marketplace/app/Http/Controllers/CatalogController.php \
   app/Http/Controllers/CatalogController.php

cp /tmp/v11a5-rollback/marketplace/app/Http/Controllers/SearchSuggestionController.php \
   app/Http/Controllers/SearchSuggestionController.php

# Replace frontend (reverts Catalog/Index + SearchBar UI)
cp /tmp/v11a5-rollback/marketplace/resources/js/Pages/Catalog/Index.tsx \
   resources/js/Pages/Catalog/Index.tsx

cp /tmp/v11a5-rollback/marketplace/resources/js/Components/common/SearchBar.tsx \
   resources/js/Components/common/SearchBar.tsx

# Remove v11B.1-only frontend testids — none — they would just be unused

# Rebuild
rm -rf public/build node_modules/.vite
npm run build

php artisan optimize:clear
php artisan cache:clear
```

The v11B.1 SERVICES, MODELS, and MIGRATIONS remain in place (harmless when unused — the migrations are reversible).

**DO NOT delete the v11B.1 services** while controllers reference them — DI will throw. The dependency-injection flow requires either:
1. Both controllers + services rolled back together, OR
2. Both controllers + services left at v11B.1 with feature flags `false`

## Tier 3 — Full revert to Phase 11A baseline

If the entire v11B.1 release must be undone:

```bash
# 1. Down-migrate v11B.1 migrations (reversible)
php artisan migrate:rollback --step=4
# Should down() in order:
#   2026_06_25_000004 indexes — drops 5 indexes (safe; products table intact)
#   2026_06_25_000003 user_recent_searches — drops table
#   2026_06_25_000002 search_queries — drops table  
#   2026_06_25_000001 search_synonyms — drops table

# 2. Verify migrations are rolled back
php artisan migrate:status | grep 2026_06_25
# All 4 should show "Pending"

# 3. Extract Phase 11A baseline over current workspace
tar -xzf marketplace-phase-11A-final-approved.tar.gz --strip-components=1 --overwrite

# 4. Restore .env (if it had SEARCH_FEATURE_* vars added, they're harmless to leave)

# 5. Clear caches and rebuild
php artisan optimize:clear
php artisan cache:clear
rm -rf public/build/ node_modules/.vite/
npm ci
npm run build

# 6. Verify VERSION
cat VERSION
# Expected: Phase 11A v11A.5

# 7. Re-run v11A.5 tests
php artisan test --filter=Phase11AV1Hot5
```

## Per-migration down() behaviors

| Migration | down() effect |
|---|---|
| `2026_06_25_000001_create_search_synonyms_table` | `dropIfExists('search_synonyms')` — data lost (synonym pairs) |
| `2026_06_25_000002_create_search_queries_table` | `dropIfExists('search_queries')` — data lost (aggregate analytics, no PII) |
| `2026_06_25_000003_create_user_recent_searches_table` | `dropIfExists('user_recent_searches')` — data lost (per-user history) |
| `2026_06_25_000004_add_search_performance_indexes_to_products` | Drops 5 indexes (4 composite + 1 prefix). Existence-guarded. Products table data INTACT. |

**No products / orders / users / categories are affected by any down().**

## Cache cleanup

After any rollback tier, flush the v11B.1 caches:

```bash
php artisan cache:forget marketplace:search:synonyms:v1:en
php artisan cache:forget marketplace:search:synonyms:v1:ar
php artisan cache:forget marketplace:search:typo_dict:v1:en
php artisan cache:forget marketplace:search:typo_dict:v1:ar

# Or nuclear option (clears EVERYTHING including non-search caches):
php artisan cache:clear
```

Facet cache keys are short-lived (60s) and self-expire — no explicit cleanup needed.

## Tier-by-tier comparison

| Aspect | Tier 1 (flags) | Tier 2 (partial) | Tier 3 (full) |
|---|---|---|---|
| Downtime | ~seconds | ~minutes (rebuild) | ~minutes (migrations + rebuild) |
| Data preserved | All v11B.1 tables intact | All v11B.1 tables intact | v11B.1 tables DROPPED (data lost) |
| Code reverted | None | Controllers + frontend | Everything |
| Reapply later | Just unset env vars | Re-extract v11B.1 tarball | Re-run migrate + extract |
| Risk | Lowest | Low | Medium — verify v11A.5 tests pass after |

**Recommended default**: Tier 1 unless a specific layer is broken beyond a flag fix.

## Verification after rollback

```bash
# Tier 1
php artisan tinker --execute="echo config('marketplace_search.features.smart_search_enabled') ? 'ON' : 'OFF';"

# Tier 2
diff <(cat app/Http/Controllers/CatalogController.php) \
     <(tar -xzf marketplace-phase-11A-final-approved.tar.gz -O marketplace/app/Http/Controllers/CatalogController.php) \
     && echo "✓ CatalogController matches v11A.5"

# Tier 3
[ "$(cat VERSION)" = "Phase 11A v11A.5" ] && echo "✓ VERSION restored"
php artisan migrate:status | grep -c 2026_06_25  # → 0 (no v11B.1 migrations applied)
```

## When to call the developer

After any rollback, run `php artisan test`. If any v10.x or v11A.x suite fails, the rollback was incomplete — escalate. The v10.x baseline contract:
- 5 defensive markers in HandleInertiaRequests
- 3 guardAdminReportsAccess calls in admin/ReportsController
- 2 `str_starts_with($path, 'admin/')` checks
- All v10.0–v10.16 defenses preserved

If those test, the system is healthy at the rolled-back tier.
