# Marketplace Platform — Phase 10 Final Approved Baseline

**Status**: APPROVED FUNCTIONAL BASELINE — DO NOT MODIFY
**Approved version**: `Phase 10 v10.16`
**Archive name**: `marketplace-phase-10-final-approved.{tar.gz,zip}`
**Approved by**: Developer (all 29 verification areas confirmed working)
**Approval date**: Recorded by dev at start of Phase 11

---

## Archive SHA-256 (immutable — verify before any rollback)

```
marketplace-phase-10-final-approved.tar.gz
  ef0f4872bd67648a17740712806261d61be3014f31322693a780579d160537bf

marketplace-phase-10-final-approved.zip
  15090d4e5e84f0af0bbf09611a13539c84ba52adfd88893ac6f806223d37af7d
```

These hashes are identical to the shipped `marketplace-phase-10-v10.16.{tar.gz,zip}` — the approved baseline IS v10.16 under a preservation name.

Verify before trusting any local copy:
```bash
sha256sum -c marketplace-phase-10-final-approved.tar.gz.sha256
```

## Recommended Git tag

```bash
cd /var/www/marketplace
git checkout main                                      # whatever branch has v10.16
git tag -a phase-10-final-approved -m "Phase 10 v10.16 — approved functional baseline (29/29 dev-verified)"
git push origin phase-10-final-approved
```

The tag serves as an immutable marker. Future rollback is `git checkout phase-10-final-approved`.

---

## Page inventory — what's working in the approved baseline

The 29 dev-verified areas (from Phase 10 walkthrough confirmations):

### Storefront (public)
1. **Homepage** `/` — guest + authenticated customer both render (post-v10.16 fix to Welcome.tsx permissions normalize)
2. **Products listing** `/products` — paginated 24/page, filters, search, sort
3. **Product detail** `/products/{slug}` — variants, reviews, vendor info, related, promotions
4. **Services listing/detail** — bookings flow intact
5. **Promotions display** — promo badges + final_price calculations correct
6. **Cart** `/cart` — items, coupons, totals
7. **Checkout** — full flow operational
8. **Search** — Meilisearch / fallback
9. **Categories** — hierarchical navigation

### Customer authentication
10. **Login** `/login` (post-v10.15 defensive wrapping)
11. **Register** `/register`
12. **Logout** + session invalidation
13. **Password reset**
14. **Email verification** (where required)

### Customer self-service
15. **Customer orders** `/orders` — paginated, indexed
16. **Customer bookings**
17. **Wishlist** `/wishlist`
18. **Customer addresses**
19. **Customer profile / settings**
20. **Customer support tickets** — reply flow (v10.11 §4 LazyLoadingViolation fix preserved)

### Vendor
21. **Vendor dashboard** `/vendor` — package + subscription + commission cards
22. **Vendor orders** `/vendor/orders` — status dropdown (v10.11 §3 fix preserved)
23. **Vendor Reports** `/vendor/reports` — financial summary + product performance (v10.13 nav fix)
24. **Vendor wallet + payouts** (v10.11 §5 SUM column fix preserved)
25. **Vendor settings + services**

### Admin
26. **Admin Reports** `/admin/reports` (v10.10 direct guard + v10.11 SUM + v10.12 Spatie scope)
27. **Filament admin panel** `/admin/login` and resources
28. **Vendor application review**

### Mobile + cross-cutting
29. **Mobile hamburger menu + expandable categories** (v10.6 fix preserved)

---

## §15 — Test/build results recorded for this baseline

From the dev's pre-approval verification:
- `php artisan test` — full suite passes
- `php artisan test --filter=Phase10V1016` — 20/20 scenarios pass (the homepage repair)
- `php artisan test --filter=Phase10V1015` — 20/20 scenarios pass (login regression repair)
- `php artisan test --filter=Phase10V1014` — 15/15 scenarios pass (perf optimization)
- All v10.0-v10.13 regression scenarios pass
- `npm run typecheck` — 0 errors
- `npm run build` — succeeds
- `npm run lint` — no new violations

CI sub-checks (from `.github/workflows/ci.yml`):
- Phase 10 total: **65 sub-checks** (v10.1 through v10.16)
- All passing in CI for the approved release

---

## Database backup guidance

Before applying ANY further phase (11A, 11B, beyond), make a full DB backup:

```bash
# MySQL backup (recommended: include routines + triggers + events)
mysqldump --single-transaction --routines --triggers --events \
          --add-drop-database --databases marketplace \
          | gzip > marketplace-phase-10-final-approved-$(date +%Y%m%d-%H%M%S).sql.gz

# Verify the backup is readable
gunzip -t marketplace-phase-10-final-approved-*.sql.gz && echo "✓ Backup integrity OK"
```

Store the backup OUTSIDE the application directory (different disk or remote object storage). Recommended:
- One copy on the local server (fast rollback)
- One copy on object storage (e.g. Cloudflare R2, AWS S3) — encrypted, versioned, retention ≥ 90 days
- One copy off-platform for disaster recovery

For PostgreSQL (if migrated later):
```bash
pg_dump -Fc -d marketplace -f marketplace-phase-10-final-approved-$(date +%Y%m%d-%H%M%S).pgdump
```

### Critical tables to verify after restore

```sql
SELECT 'users' AS table_name, COUNT(*) AS row_count FROM users
UNION ALL SELECT 'products', COUNT(*) FROM products
UNION ALL SELECT 'orders', COUNT(*) FROM orders
UNION ALL SELECT 'order_items', COUNT(*) FROM order_items
UNION ALL SELECT 'vendors', COUNT(*) FROM vendors
UNION ALL SELECT 'product_reviews', COUNT(*) FROM product_reviews
UNION ALL SELECT 'support_tickets', COUNT(*) FROM support_tickets
UNION ALL SELECT 'vendor_payout_requests', COUNT(*) FROM vendor_payout_requests
UNION ALL SELECT 'service_bookings', COUNT(*) FROM service_bookings
UNION ALL SELECT 'promotions', COUNT(*) FROM promotions
UNION ALL SELECT 'coupons', COUNT(*) FROM coupons
ORDER BY table_name;
```

Record counts BEFORE Phase 11. Compare after any future deploy to detect data loss.

### Spatie permissions cache

After restore, ALWAYS run:
```bash
php artisan permission:cache-reset
php artisan optimize:clear
```

---

## Uploaded-files backup guidance

The marketplace stores uploaded files in:
- `storage/app/public/` (locally) — symlinked to `public/storage`
- AWS S3 / Cloudflare R2 (production, depending on `FILESYSTEM_DISK`)
- `storage/app/vendor_documents/` — private vendor verification documents (auth-protected access only)

### Local backup

```bash
cd /var/www/marketplace
tar --exclude='*/cache/*' --exclude='*/sessions/*' -czf \
    storage-phase-10-final-approved-$(date +%Y%m%d-%H%M%S).tar.gz \
    storage/app/
```

### Cloud storage backup (R2/S3)

```bash
# Cloudflare R2 — use rclone for incremental backup
rclone sync r2:marketplace-public /var/backups/r2-marketplace-public --transfers 8

# AWS S3
aws s3 sync s3://marketplace-public /var/backups/s3-marketplace-public
```

Critical asset categories:
- Product images (`storage/app/public/products/`)
- Vendor logos + banners (`storage/app/public/vendors/`)
- Customer review attachments
- Vendor verification documents (`storage/app/vendor_documents/` — sensitive, keep encrypted)
- Order customization proofs

Don't include `storage/framework/cache/`, `storage/framework/sessions/`, `storage/framework/views/` — those regenerate.

---

## Rollback instructions

If Phase 11A (or any future phase) introduces a regression, roll back to Phase 10 final-approved:

### Option 1 — Git tag (fastest)

```bash
cd /var/www/marketplace
sudo systemctl stop nginx                              # stop traffic during swap
git checkout phase-10-final-approved
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan optimize:clear
php artisan migrate:status                             # verify schema state
# If migrations have advanced past v10.14 (which is the latest in this baseline),
# you may need to roll back migrations: php artisan migrate:rollback --step=N
sudo systemctl start nginx
```

### Option 2 — Restore from the preservation archive

```bash
cd /var/www
# Move current (broken) build aside
sudo mv marketplace marketplace.broken.$(date +%Y%m%d-%H%M%S)
# Restore the preserved baseline
sudo mkdir marketplace
sudo tar -xzf /backup/marketplace-phase-10-final-approved.tar.gz -C /var/www/
sudo chown -R www-data:www-data marketplace
cd marketplace
composer install --no-dev --optimize-autoloader
npm ci && npm run build
# Restore .env from the broken copy (the .env IS environment-specific, not baselined)
sudo cp ../marketplace.broken.*/.env .env
sudo cp ../marketplace.broken.*/storage/oauth-*.key storage/   # if using Passport
sudo cp -r ../marketplace.broken.*/storage/app/. storage/app/   # restore uploads
sudo systemctl restart php8.3-fpm nginx
```

### Option 3 — DB rollback

If a future phase corrupted DB rows, restore from the most recent pre-phase backup:

```bash
gunzip -c marketplace-phase-10-final-approved-YYYYMMDD-HHMMSS.sql.gz | mysql marketplace
php artisan permission:cache-reset
php artisan optimize:clear
```

### Verification after rollback

```bash
cat VERSION                                            # → must show Phase 10 v10.16
php artisan test --filter=Phase10V1016                 # → 20/20 pass
php artisan test --filter=Phase10V1015                 # → 20/20 pass
php artisan test                                       # full suite passes
```

Plus the dev's 29-area manual walkthrough.

---

## What this baseline does NOT include

These are KNOWN limitations the dev approved as out-of-scope for the launch baseline:

- No thumbnail generation pipeline (full-resolution originals served as listing thumbnails for some product images) — documented in `PHASE_10_KNOWN_LIMITATIONS.md`
- No advanced search ranking (Meilisearch fallback to MySQL LIKE)
- No personalization (homepage shows same featured products to all users)
- No related-products / "frequently bought together" recommendations
- No vendor coaching suggestions
- No risk/anomaly flags
- Filament admin panel uses Filament's default theme (not the custom design system)

These are addressed in subsequent phases:
- **Phase 11A** — UI/UX redesign (this NEXT phase)
- **Phase 11B** — Smart marketplace intelligence (after 11A approval)

---

## Immutability statement

This document and the corresponding archives represent the marketplace's approved functional baseline. **DO NOT modify these archive files or this document.** Future phases produce NEW archives with NEW version strings.

If a future phase is rolled back, this baseline is the recovery point. Treat it like a release tag — append-only, never rewritten.
