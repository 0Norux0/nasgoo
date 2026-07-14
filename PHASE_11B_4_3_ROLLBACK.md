# Phase 11B.4 v11B.4.3 — Rollback Procedure

## Tier 1 — Roll back the digest column migration + revert 3 files

Removes email digest infrastructure and reverts the admin settings tab. Keeps v11B.4.2 architecture. `email_opted_out` and `last_digest_sent_at` columns are dropped (no data loss for anything except the digest send-log).

```bash
# 1. Roll back the migration
php artisan migrate:rollback --step=1
# Expected: 2027_01_01_000001_add_vendor_intelligence_digest_columns rolled back

# 2. Restore the two files that carried the admin-UI Fix 1 code
tar -xzf marketplace-phase-11B-4-2-mandatory-vendor-intelligence-repair.tar.gz \
    --strip-components=1 --overwrite \
    marketplace/app/Http/Controllers/Admin/SiteSettingsController.php \
    marketplace/resources/js/Pages/Admin/SiteSettings/Index.tsx \
    marketplace/app/Providers/AppServiceProvider.php \
    marketplace/config/site.php \
    marketplace/app/Models/VendorIntelligenceSummary.php \
    marketplace/app/Console/Commands/GenerateVendorIntelligence.php

# 3. Delete new v11B.4.3 files
rm -f app/Mail/VendorIntelligenceDigestMail.php
rm -f app/Jobs/SendVendorIntelligenceDigest.php
rm -f resources/views/emails/vendor-intelligence-digest.blade.php
rm -f app/Observers/VendorIntelligence/ProductTranslationObserver.php

# 4. Clear caches + rebuild
php artisan optimize:clear
rm -rf public/build node_modules/.vite
npm ci && npm run build

echo "Phase 11B.4 v11B.4.2" > VERSION
```

Note: after Tier 1, the three v11B.4.3 issues will return (missing admin tab, no email digest, no translation stale marking). Roll back only if v11B.4.3 introduces a worse regression than those.

## Tier 2 — Full revert to v11B.4.2 baseline

```bash
php artisan migrate:rollback --step=1
tar -xzf marketplace-phase-11B-4-2-mandatory-vendor-intelligence-repair.tar.gz \
    --strip-components=1 --overwrite

php artisan optimize:clear
rm -rf public/build node_modules/.vite
npm ci && npm run build

cat VERSION   # → Phase 11B.4 v11B.4.2
```

## Tier 3 — Chain back to v11B.4 or v11B.3.3

For v11B.4 (removes all v11B.4.2 + v11B.4.3 work but keeps the module):
```bash
php artisan migrate:rollback --step=2   # rolls back 2027_01_01 + 2026_12_01
tar -xzf marketplace-phase-11B-4-baseline.tar.gz --strip-components=1 --overwrite
```

For v11B.3.3 approved (removes all vendor intelligence):
```bash
php artisan migrate:rollback --step=3   # includes v11B.4 table creation migration
tar -xzf marketplace-phase-11B-3-3-final-approved.tar.gz --strip-components=1 --overwrite
```

## What NEVER to do

- Do NOT modify the immutable v11B.4.2 baseline archive
- Do NOT drop `vendor_intelligence_*` tables outside `migrate:rollback` (loses dismiss/snooze history)
- Do NOT `migrate:fresh` in production
- Do NOT downgrade past v11B.3.3 without a fresh directive
