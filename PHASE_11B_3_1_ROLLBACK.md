# Phase 11B.3 v11B.3.1 — Rollback Procedure

## Tier 1 — Revert code, keep data + schema

Fastest. Settings table + audit columns retained. Reversible in minutes.

```bash
# Restore only the code files changed by v11B.3.1
tar -xzf marketplace-phase-11B-3-final-approved.tar.gz --strip-components=1 --overwrite \
    marketplace/app/Http/Middleware/HandleInertiaRequests.php \
    marketplace/resources/js/Layouts/VendorLayout.tsx \
    marketplace/resources/js/Pages/Orders/Index.tsx \
    marketplace/resources/js/Pages/Bookings/Index.tsx \
    marketplace/resources/js/Pages/Tickets/Index.tsx \
    marketplace/routes/web.php

# Rebuild frontend
php artisan optimize:clear
rm -rf public/build node_modules/.vite
npm ci && npm run build
```

The added service files (`SiteSettingsService`, `HomepageSectionRegistry`, controllers, primitives, tests, migrations) become dead code but don't break anything. Storefront rendering reverts to hardcoded values. Settings table + new columns (`updated_by`, `is_translatable`) remain — harmless because they're nullable.

**Use this when**: v11B.3.1 has a UX regression but the data model is trusted.

## Tier 2 — Full code revert + drop new migration

Drops the audit columns from `settings` and reverts all code.

```bash
# 1. Roll back the v11B.3.1 migration (drops updated_by + is_translatable)
php artisan migrate:rollback --step=1

# 2. Confirm rollback
php artisan migrate:status | tail -3
# Expect: the v11B.3.1 row shows Ran=No

# 3. Restore full v11B.3 baseline
tar -xzf marketplace-phase-11B-3-final-approved.tar.gz --strip-components=1 --overwrite

# 4. Clean build
php artisan optimize:clear
rm -rf public/build node_modules/.vite
npm ci && npm run build

# 5. Verify
cat VERSION                             # → Phase 11B.3
php artisan test --filter=Phase11B3     # 56 v11B.3 scenarios
php artisan test                         # 731 total
```

**Use this when**: v11B.3.1 has a fundamental issue but no need to preserve settings data.

## Tier 3 — Full revert to v11B.3 formally-approved baseline

Same as Tier 2 for practical purposes; use if you want to be paranoid and blow away any accidentally-written settings rows.

```bash
# 1. Drop the v11B.3.1 migration
php artisan migrate:rollback --step=1

# 2. Optional: truncate the settings table (only if you want a totally clean state)
# WARNING: this deletes any settings rows that existed before v11B.3.1 too
php artisan tinker
> Schema::disableForeignKeyConstraints(); DB::table('settings')->truncate(); Schema::enableForeignKeyConstraints();

# 3. Restore code
tar -xzf marketplace-phase-11B-3-final-approved.tar.gz --strip-components=1 --overwrite

# 4. Build
php artisan optimize:clear
rm -rf public/build node_modules/.vite
npm ci && npm run build

# 5. Verify
cat VERSION                                # → Phase 11B.3
php artisan test                            # 731 total pass
```

**Warning**: Step 2 destroys any pre-v11B.3.1 settings rows. If you want to keep those but drop only the v11B.3.1 additions, skip step 2.

## What NEVER to do

- Do NOT modify the formally-approved `marketplace-phase-11B-3-final-approved.tar.gz` — its SHA must remain the exact baseline.
- Do NOT run `php artisan migrate:fresh` in production — that drops the entire schema.
- Do NOT delete rows from `personalization_preferences`, `customer_product_views`, or `customer_affinities` — these belong to v11B.3.

## Recovery of settings data

If Tier 3 is used and admin-configured settings are lost, they must be re-entered via the admin UI (`/admin/site-settings`). Config defaults from `config/site.php` apply until then — no page will crash.

## Emergency contact

Common gotchas:
- If the settings admin page returns 500 after rollback: `php artisan optimize:clear` — a stale cache reference to the removed controller may linger
- If storefront pages show "Undefined property: siteSettings": `npm run build` — old bundle referenced `siteSettings` from Inertia share that's no longer present
