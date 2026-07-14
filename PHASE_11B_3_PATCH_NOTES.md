# Phase 11B.3 — Patch Notes

## Summary

Deterministic personalization built on ordinary marketplace activity. Adds Recently Viewed, Continue Shopping, Buy Again, category affinity, and privacy controls to the storefront homepage without breaking existing sections. **No paid AI APIs.** **No sensitive profiling.** **Cross-customer isolation enforced by design.**

## What's new for customers

- Homepage now renders personalized sections between trust indicators and featured categories:
  - **Continue Shopping** (cart items > wishlist items > recently viewed)
  - **Recently Viewed** with a Clear control
  - **Recommended for you** based on the customer's top-affinity categories
  - **Buy Again** for products purchased 7–180 days ago
- Per-card "Not Interested" control hides a product for 90 days for THIS customer only
- Account settings page at `/account/personalization` with 3 privacy toggles + Reset button
- All new sections localized in English + Arabic (54 new translation keys)

## What's new for guests

- Recently Viewed based on session ID (30-day retention)
- No email required, no account creation, no persistent tracking beyond the browser session

## What's new for admins

- Scheduled jobs run nightly:
  - 03:00 — `personalization:prune` (expired views/feedback/stale affinities)
  - 03:15 — `personalization:rebuild --stale-days=1` (incremental affinity refresh)
- Feature flags in `config/marketplace_personalization.php`:
  - Master `enabled`
  - Per-section: `recently_viewed`, `continue_shopping`, `recommended_for_you`, `category_affinity`, `buy_again`
  - Guest: `guest_personalization`, `guest_to_customer_merge`
  - Data collection: `behavior_tracking`, `analytics`, `feedback_controls`

## Files added (14 new)

**Migrations (3)**:
- `2026_08_01_000001_create_customer_product_views_table.php`
- `2026_08_01_000002_create_customer_affinities_table.php`
- `2026_08_01_000003_create_personalization_preferences_and_feedback_tables.php`

**Models (4)**:
- `app/Models/CustomerProductView.php`
- `app/Models/CustomerAffinity.php`
- `app/Models/PersonalizationPreference.php`
- `app/Models/PersonalizationFeedback.php`

**Services (5)**:
- `app/Services/Personalization/RecentlyViewedService.php`
- `app/Services/Personalization/ContinueShoppingService.php`
- `app/Services/Personalization/CustomerAffinityService.php`
- `app/Services/Personalization/BuyAgainService.php`
- `app/Services/Personalization/PersonalizationManager.php`

**Controller + Middleware (2)**:
- `app/Http/Controllers/PersonalizationController.php`
- `app/Http/Middleware/RecordProductView.php`

**Console commands (2)**:
- `app/Console/Commands/PersonalizationRebuildCommand.php`
- `app/Console/Commands/PersonalizationPruneCommand.php`

**Config (1)**:
- `config/marketplace_personalization.php` (11 feature flags + weights + decay + retention + priority + limits + price bands)

**React (2)**:
- `resources/js/Components/Personalization/PersonalizedSections.tsx`
- `resources/js/Pages/Account/Personalization.tsx`

**Tests + docs (6)**:
- `tests/Feature/Phase11B3PersonalizationTest.php` (56 scenarios)
- `PHASE_11B_3_PERSONALIZED_HOMEPAGE_REPORT.md`
- `PHASE_11B_3_PATCH_NOTES.md`
- `PHASE_11B_3_DEVELOPER_CHECKLIST.md`
- `PHASE_11B_3_ROLLBACK.md`
- `PHASE_11B_3_PACKAGE_INTEGRITY.md`

## Files modified (6)

- `app/Http/Controllers/HomeController.php` — calls PersonalizationManager, passes payload to Welcome
- `app/Http/Controllers/CatalogController.php` — sets `viewed_product_id` request attribute for middleware
- `resources/js/Pages/Welcome.tsx` — imports + renders PersonalizedSections above featured categories
- `routes/web.php` — 4 new personalization routes + RecordProductView middleware on product show
- `routes/console.php` — 2 new scheduled tasks
- `lang/en.json` + `lang/ar.json` — 27 new keys each
- `.github/workflows/ci.yml` — +10 v11B.3 sub-checks
- `VERSION` — `Phase 11B.2 v11B.2.2` → `Phase 11B.3`

## Counts

| Metric | v11B.2.2 → v11B.3 |
|---|---|
| CI sub-checks | 162 → **172** (+10) |
| Pest scenarios | 675 → **731** (+56) |
| Unique Pest helpers | 151 → **155** (+4 p11b3_*) |
| Migrations | (cumulative) → **+3** additive |
| New services | 0 → **5** |
| New commands | 0 → **2** |
| New middleware | 0 → **1** |
| Feature flags | (existing) → **+11** |
| Translation keys | +27 en + 27 ar |

## Required-result mapping (per directive)

| Required proof | Status |
|---|---|
| Recently Viewed appears for auth + guest | ✅ Pest §39.1-12 |
| Continue Shopping ranks cart > wishlist > viewed | ✅ Pest §40.1-3 |
| Section priority documented + enforced | ✅ `config.sections.authenticated_priority` + Pest §44.10 |
| Cross-section deduplication | ✅ Pest §44.11 |
| Purchase signal > wishlist > view | ✅ Pest §41.2, §41.3 |
| Recency decay | ✅ Pest §41.4, §41.10 |
| Refresh-spam capped | ✅ Pest §41.5 |
| Cross-customer isolation | ✅ Pest §42.4, §42.5, §42.6 |
| Cache isolated per user | ✅ Pest §42.5 |
| Runtime eligibility recheck | ✅ Pest §44.4 |
| Suspended-vendor + unpublished excluded | ✅ Pest §39.6, §39.7, §40.5, §43.4 |
| Feature flag disables section | ✅ Pest §44.1, §44.2 |
| Homepage still works when disabled | ✅ Pest §44.5 |
| Retention pruning | ✅ Pest §44.14 (dry-run) + prune command exists |
| Opt-out returns disabled payload | ✅ Pest §42.1 |
| Reset scoped to caller | ✅ Pest §42.3 |
| Auth required for settings page | ✅ Pest §42.8 |
| English fallback for missing Arabic | ✅ Pest §44.12 |
| Rebuild idempotent | ✅ Pest §41.9 |
| Pricing integration via canonical v11B.2.2 engine | ✅ `PersonalizationManager::shapeItem` calls `PricingService::priceForProduct` |

## Deploy commands

```bash
php artisan optimize:clear
php artisan migrate                                       # 3 new additive migrations
php artisan migrate:status                                # confirm 3 v11B.3 rows present
php artisan route:list | grep -i personalization         # 4 routes
php artisan schedule:list | grep personalization         # 2 scheduled tasks
php artisan personalization:rebuild                       # initial rebuild for all customers
php artisan personalization:prune --dry-run              # count-only sanity check
php artisan test --filter=Phase11B3                       # 56 v11B.3 scenarios
php artisan test                                           # 731 total
npm ci && npm run typecheck && npm run build
```

## Rollback

3-tier:

**Tier 1** — Disable via feature flags (no code revert):
```bash
PERSONALIZATION_ENABLED=false
php artisan optimize:clear
```
Homepage reverts to pre-v11B.3 behavior. Data tables remain but are not read.

**Tier 2** — Revert code + drop migrations:
```bash
php artisan migrate:rollback --step=3    # drops the 3 v11B.3 tables
tar -xzf marketplace-phase-11B-2-final-approved.tar.gz --strip-components=1 --overwrite
php artisan optimize:clear
npm ci && npm run build
```

**Tier 3** — Full revert to the formally-approved v11B.2 baseline:
```bash
php artisan migrate:rollback --step=3
tar -xzf marketplace-phase-11B-2-final-approved.tar.gz --strip-components=1 --overwrite
rm -rf public/build node_modules/.vite
npm ci && npm run build
cat VERSION                                # → Phase 11B.2 v11B.2.2
php artisan test --filter=Phase11B22       # v11B.2.2 regression suite
```

## Honest scope

✅ Recently Viewed (auth + guest)
✅ Continue Shopping (cart > wishlist > viewed)
✅ Category affinity + Buy Again
✅ Privacy controls (clear / opt-out / feedback / reset)
✅ 3 tables, 5 services, 2 commands, 2 middleware
✅ 11 feature flags, weights, decay, retention all in config
✅ 56 Pest scenarios + 10 CI sub-checks
✅ Localization: 27 keys × 2 locales
✅ Runtime eligibility recheck backs up cache
✅ Cross-customer + cross-session isolation
✅ v11B.2.2 pricing engine wired in unchanged

❌ NOT in v11B.3 (deferred, documented in REPORT):
- Guest-to-customer merge (scaffolded via flag but not active)
- Recommended services section (empty placeholder — requires bookings-based affinity)
- Search-term affinity dimension
- Auto-impression emit on every card render
- Buy Again refund-aware exclusion (currently order-status-based only)
- Vendor diversity cap enforcement (documented in config, not applied)
- Personalization admin analytics dashboard (data is queryable via existing recommendation_events + new tables; no new admin UI in this phase)

## Phase 11B.3 STOPS HERE

No Phase 11B.4 work begun. Do NOT proceed to vendor intelligence, inventory forecasting, smart pricing, report narratives, support assistant, quality scoring, or fraud/risk scoring. Pending dev verification.
