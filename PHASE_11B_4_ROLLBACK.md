# Phase 11B.4 — Rollback Procedure

## Tier 1 — Revert code, keep tables (data preservation)

Preserves alerts/summaries/feedback rows in case a future re-install wants them.

```bash
tar -xzf marketplace-phase-11B-3-3-final-approved.tar.gz \
    --strip-components=1 --overwrite

php artisan optimize:clear
rm -rf public/build node_modules/.vite
npm ci && npm run build

cat VERSION    # → Phase 11B.3 v11B.3.3
```

The 4 vendor_intelligence tables remain but nothing references them — harmless. Consumes a small amount of DB storage.

**Use this when**: v11B.4 UI or service misbehaves but the data may be needed later.

## Tier 2 — Full code revert + drop v11B.4 migration

```bash
# 1. Drop the 4 tables
php artisan migrate:rollback --step=1

# 2. Confirm
php artisan migrate:status | tail -3     # v11B.4 row shows Ran=No

# 3. Restore code
tar -xzf marketplace-phase-11B-3-3-final-approved.tar.gz \
    --strip-components=1 --overwrite

# 4. Clean build
php artisan optimize:clear
rm -rf public/build node_modules/.vite
npm ci && npm run build

# 5. Verify
cat VERSION                              # → Phase 11B.3 v11B.3.3
php artisan test --filter=Phase11B33     # 33 scenarios still pass
```

**Use this when**: v11B.4 introduces a regression.

## Tier 3 — Full revert to v11B.3.3 baseline

Same as Tier 2 for practical purposes — v11B.4's rollback destination IS the v11B.3.3 baseline.

## What NEVER to do

- Do NOT modify the immutable `marketplace-phase-11B-3-3-final-approved.tar.gz` archive
- Do NOT drop the `customer_product_views`, `wishlists`, or `orders` tables — those belong to earlier phases
- Do NOT run `php artisan migrate:fresh` in production

## Recovery notes

- If admin overview shows "Undefined variable" after rollback: `npm run build` — old bundle references v11B.4 imports
- If the vendor dashboard shows the intelligence panel but the JSON endpoint returns 404: the routes weren't reloaded → `php artisan route:cache` (or `php artisan optimize:clear`)
- If `vendor-intelligence:generate` was scheduled and remains in Kernel.php after code revert: comment out the schedule entry — otherwise cron will emit "command not found" errors

## Emergency: keep tables but disable feature

If you want to preserve data + code but stop showing the panel to vendors:

```bash
php artisan tinker
> app(\App\Services\Settings\SiteSettingsService::class)->set('vendor_intelligence.enabled', false);
```

Then check the `enabled` flag in `VendorIntelligenceManager::regenerateForVendor()` (currently the flag is defined in config but not yet fully enforced across all read paths — extending that check is a follow-up documented in Limitations).
