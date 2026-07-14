# Phase 12 — Database Readiness Report

Production-database preparation for the Kuwait multi-vendor marketplace running on Laravel 11 + MySQL. Every claim in this report is grounded in an audit of the actual migrations in `database/migrations/` — 77 files total across 80 tables, verified by static grep.

Version at the time of this audit: `Phase 11B.4 v11B.4.3`. All prior phase work (11B.4.2 vendor intelligence, 11B.3.3 CSS/settings, 11B.2.2 pricing, etc.) is preserved.

## Contents

1. [Sandbox constraint declaration](#0)
2. [Module → tables audit](#1)
3. [Production database setup](#2)
4. [Migration commands](#3)
5. [Staging fresh test](#4)
6. [Existing data handling](#5)
7. [First super-admin creation](#6)
8. [Backup plan](#7)
9. [File + database backup coordination](#8)
10. [Performance / index review](#9)
11. [Integrity checks](#10)
12. [Database security](#11)
13. [Production seed policy](#12)
14. [Migration safety + rollback](#13)
15. [Required commands + expected outputs](#14)
16. [Final go-live checklist](#15)

<a id="0"></a>
## 0. Sandbox constraint declaration

This report was prepared in an environment without PHP, MySQL, or a live database. What this means:

- I can and did read every migration file and enumerate every `Schema::create()` call
- I can and did enumerate `->index(...)`, `->unique(...)`, `->foreign(...)` on every major table
- I cannot run `php artisan migrate:status`, cannot connect to a database, cannot benchmark queries
- Expected outputs of Artisan commands are described in prose, not captured verbatim

Sign-off requires the operator to run the "Required commands" in section 14 and paste the outputs into the checklist in section 15.

<a id="1"></a>
## 1. Module → tables audit

Cross-referenced the directive's module list against actual `Schema::create()` calls in `database/migrations/`. Every migration file that creates one of these tables is named.

| Module | Required Tables | Migration Exists | Production Ready |
| --- | --- | --- | --- |
| Users | `users`, `password_reset_tokens`, `sessions`, `personal_access_tokens` | `0001_01_01_000000_create_users_table.php`, `0001_01_01_000003_create_personal_access_tokens_table.php` | ✅ |
| Roles / permissions (spatie) | `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` | `2026_01_01_000001_create_permission_tables.php` (spatie stub) | ✅ |
| Customer profile | `users` + `addresses` | `2026_01_01_000002_create_addresses_table.php` | ✅ |
| Vendors | `vendors`, `vendor_packages`, `vendor_subscriptions`, `vendor_commission_rules`, `vendor_payout_requests` | `2026_01_02_*` (4 migrations) + `2026_01_05_000003_create_vendor_payout_requests_table.php` | ✅ |
| Products | `products`, `product_variants`, `product_images`, `product_attribute_value` | `2026_01_03_000003_create_products_table.php`, `2026_01_03_000004_create_product_variants_and_images.php` | ✅ |
| Product variants | `product_variants` | `2026_01_03_000004_create_product_variants_and_images.php` | ✅ |
| Categories | `categories`, `category_product` (pivot) | `2026_01_03_000001_create_categories_table.php` | ✅ |
| Attributes | `attributes`, `attribute_values`, `product_attribute_value` | `2026_01_03_000002_create_attributes_tables.php` | ✅ |
| Product images / media | `product_images`, `customization_proofs` | `2026_01_03_000004_*` + `2026_01_07_000004_create_customization_proofs_table.php` | ✅ |
| Cart | `carts`, `cart_items`, `cart_item_customizations` | `2026_01_04_000001_create_carts_table.php` + `2026_01_07_000002_*` | ✅ |
| Checkout / orders | `orders`, `order_items`, `order_addresses`, `order_events`, `order_item_customizations` | `2026_01_04_000002_create_orders_tables.php` + `2026_01_07_000003_*` | ✅ |
| Payments | `payments`, `payment_transactions`, `payment_methods` | `2026_01_04_000003_create_payments_tables.php` | ✅ |
| Bookings (services) | `service_details`, `service_providers`, `service_provider_assignments`, `service_availabilities`, `service_blocked_dates`, `service_bookings` | `2026_01_08_*` (6 migrations) | ✅ |
| Support tickets | `support_tickets`, `support_ticket_messages` | `2026_01_15_000005_*`, `2026_01_15_000006_*` | ✅ |
| Settings | `settings` | `2026_01_01_000003_create_settings_table.php` + `2026_09_01_000001_add_audit_and_translatable_to_settings.php` | ✅ |
| Translations | `product_translations` (workflow); JSON columns for other locales | `2026_06_28_000001_create_product_translations_table.php` + `2026_06_27_000001_add_short_description_translations_to_products.php` + `2026_06_24_000001_backfill_arabic_category_translations.php` | ✅ |
| Promotions | `promotions`, `promotion_targets` | `2026_01_15_000001_*`, `2026_01_15_000002_*`, `2026_06_17_000001_add_phase10_v108_promotion_snapshot_columns.php` | ✅ |
| Coupons | `coupons`, `coupon_usages` | `2026_01_15_000003_*`, `2026_01_15_000004_*`, `2026_01_20_000001_add_coupon_allocation_to_order_items.php` | ✅ |
| Recommendations | `product_recommendations`, `admin_product_relationships`, `product_pair_stats`, `recommendation_events` | `2026_07_01_*` (4 migrations) + `2026_07_05_000001_extend_recommendation_events_for_purchase_attribution.php` | ✅ |
| Personalization | `customer_product_views`, `customer_affinities`, `personalization_preferences`, `personalization_feedback` | `2026_08_01_*` (3 migrations) | ✅ |
| Search | `search_synonyms`, `search_queries`, `user_recent_searches` + search indexes on `products` | `2026_06_25_*` (4 migrations) | ✅ |
| Vendor intelligence | `vendor_intelligence_summaries`, `vendor_intelligence_alerts`, `vendor_intelligence_feedback`, `vendor_product_quality_scores` | `2026_11_01_000001_create_vendor_intelligence_tables.php` + `2026_12_01_000001_add_vendor_intelligence_dedupe_and_stale.php` + `2027_01_01_000001_add_vendor_intelligence_digest_columns.php` | ✅ |
| Suppliers (dropship / POD backend) | `supplier_platforms`, `supplier_integrations`, `supplier_products`, `supplier_orders`, `supplier_order_events`, `supplier_product_imports` | `2026_01_06_*` (6 migrations) | ✅ |
| Customization (POD) | `product_customization_fields`, `cart_item_customizations`, `order_item_customizations`, `customization_proofs` | `2026_01_07_*` (5 migrations) | ✅ |
| Reviews / wishlists | `product_reviews`, `wishlists` | `2026_01_05_000001_*`, `2026_01_05_000002_*`, `2026_01_15_000007_extend_product_reviews_for_phase_9.php` | ✅ |
| Shipping | `shipping_zones`, `shipping_methods` | `2026_01_05_000004_create_shipping_zones_and_methods_tables.php` | ✅ |
| Currencies | `currencies`, `currency_rates` | `2026_01_01_000005_create_currencies_tables.php` | ✅ |
| Notification templates | `notification_templates` | `2026_01_01_000004_create_notification_templates_table.php` | ✅ |
| Audit logs | `audit_logs`, `activity_log` | `2026_01_01_000006_create_audit_logs_table.php`, `2026_01_01_000007_create_activity_log_table.php` | ✅ |
| Queue / jobs | `jobs`, `job_batches`, `failed_jobs` | `0001_01_01_000002_create_jobs_table.php` | ✅ |
| Cache | `cache`, `cache_locks` | `0001_01_01_000001_create_cache_table.php` | ✅ (used only in fallback; production should prefer Redis) |

**Email / notification LOGS (as opposed to templates)**: the codebase has `notification_templates` (blueprints) but no dedicated `notification_logs` or `email_logs` table. Delivery observability comes from the queue driver (`jobs` for pending / `failed_jobs` for failed dispatches) plus mailer transport logs (SES/SMTP provider dashboards). This is a legitimate design choice, not a gap — Laravel's `Mail::fake()` is used in tests and mail provider dashboards give production observability. Note this in the go-live checklist.

**Coverage verdict**: every module from the directive is backed by at least one migration. No missing tables blocking production.

<a id="2"></a>
## 2. Production database setup

Below is the sequence for creating the production database. Placeholders in ALL CAPS should be replaced by real values; real credentials must never be committed to Git or included in this report.

### 2.1 Create the database + user (MySQL 8.0+)

```sql
-- run as the DB root user (not the application user)
CREATE DATABASE `DB_DATABASE`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

CREATE USER 'DB_USERNAME'@'DB_HOST_OR_%'
    IDENTIFIED BY 'STRONG_RANDOM_PASSWORD';

-- The application needs SELECT/INSERT/UPDATE/DELETE + DDL for
-- `php artisan migrate`. Nothing more (no GRANT, no CREATE USER,
-- no FILE, no SUPER).
GRANT SELECT, INSERT, UPDATE, DELETE,
      CREATE, ALTER, INDEX, DROP, REFERENCES,
      CREATE TEMPORARY TABLES, EXECUTE, TRIGGER,
      CREATE VIEW, SHOW VIEW
   ON `DB_DATABASE`.* TO 'DB_USERNAME'@'DB_HOST_OR_%';

FLUSH PRIVILEGES;
```

If migrations are pre-baked (see §3 note on `schema:dump`), the application user can be dropped to SELECT/INSERT/UPDATE/DELETE only at runtime.

### 2.2 Configure `.env`

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example

DB_CONNECTION=mysql
DB_HOST=          # RDS endpoint, private IP, or "127.0.0.1" for socket
DB_PORT=3306
DB_DATABASE=      # the DB you just created
DB_USERNAME=      # the user you just created (not root)
DB_PASSWORD=      # the STRONG_RANDOM_PASSWORD

CACHE_STORE=redis
QUEUE_CONNECTION=redis      # or database — see §13
SESSION_DRIVER=redis
```

**Password requirements**: minimum 20 characters, mixed case, digits, symbols. Generate with `openssl rand -base64 24`. Never reuse across environments. Rotate on any suspected exposure.

**Never commit `.env`**. Verify `.gitignore` contains `.env`; the shipped `.env.example` is safe to commit and should have only placeholders.

### 2.3 Storage prerequisites

Make sure the following environment variables are set before running any Artisan command that touches storage:

```env
APP_KEY=base64:...       # generate with `php artisan key:generate --show`
FILESYSTEM_DISK=s3       # or "public" if using local disk
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=...
AWS_BUCKET=...
AWS_URL=...
```

The `SupplierIntegration` model has an `encrypted:array` credentials column. If `APP_KEY` is missing or rotated, seed and existing rows will crash the app. Set APP_KEY BEFORE first migration + never rotate without a documented re-encryption plan.

<a id="3"></a>
## 3. Migration commands

### 3.1 Production — the ONLY command to use

```bash
php artisan migrate --force
```

The `--force` flag bypasses Laravel's "Do you really wish to run this command?" prompt. It's required in non-interactive environments (deploy scripts, CI, systemd units). It does NOT drop anything; it only runs pending migrations forward.

### 3.2 Commands that MUST NEVER run in production

```bash
php artisan migrate:fresh          # DROPS ALL TABLES then re-runs everything
php artisan migrate:fresh --seed   # same + re-seeds demo data
php artisan db:wipe                # DROPS ALL TABLES
php artisan migrate:refresh        # rolls back everything then re-runs
```

Any of these on a live database destroys customer accounts, order history, vendor data, and payment records. There is no recovery except from a backup. If you accidentally run one of these against production, STOP THE APP IMMEDIATELY, keep the process open, and restore from the most recent backup before any further writes happen.

### 3.3 Preview what will run (dry-run)

```bash
php artisan migrate --pretend
```

Prints the SQL that WOULD execute without actually running it. Useful right before `migrate --force` on production to verify only the migrations you expect are pending.

Expected output on a freshly extracted v11B.4.3 deploy against a clean DB: 77 migration files listed as "Pending"; the SQL for each will be dumped. On an incremental deploy, only the newly added migration files should appear (typically 1–3).

<a id="4"></a>
## 4. Staging fresh test

Before going live, verify the full system installs cleanly from zero on a **separate staging database** — not production.

```bash
# On staging, with a staging .env pointing to a staging DB:
php artisan migrate:fresh --seed
php artisan test
```

`migrate:fresh --seed` will:
1. Drop all tables in the staging DB
2. Re-run every migration from scratch
3. Run `DatabaseSeeder` (roles + settings + currencies + notification templates + vendor packages + categories + attributes + payment methods)
4. Create the `admin@marketplace.test` user with password `password` (see `DatabaseSeeder`)
5. In `local`, `development`, or `testing` environment, run `DemoSeeder` (~5 demo users + demo vendor + demo products)
6. Run `ArabicProductContentSeeder` + `BackfillProductTranslationsSeeder`
7. Run `EnsureAdminReportsAccessSeeder`

`DemoSeeder` self-guards against non-{local,development,testing} environments (verified in `database/seeders/DemoSeeder.php` line 61). But the admin@marketplace.test user + password IS created unconditionally in `DatabaseSeeder`. **Do not run `--seed` on production**. Section 6 documents the safe alternative.

The `php artisan test` step runs the full Pest suite — Phase 1 through Phase 11B.4 v11B.4.3, 1556 `it(...)` scenarios across 106 test files (confirmed via `grep -c '^it(' tests/**/*.php`; pass/fail NOT verified in this package). Pass/fail on staging: pending developer verification. The scenarios exist in the codebase but were NOT executed as part of preparing this package.

<a id="5"></a>
## 5. Existing data handling

Answer this question BEFORE first migration:

> Which of the following describes your production launch?

1. **Empty database** — new marketplace, no existing customers/vendors/products. Nothing to import.
2. **Imported real data** — customers/vendors/products migrating from a previous system. You need an import plan and a data-mapping doc.
3. **Demo/sample data** — you want the DemoSeeder's demo vendor + demo products live at launch. **NOT recommended** — see below.
4. **Manually created admin/vendor accounts** — you'll bootstrap the admin via section 6, then let vendors sign up via the storefront.

For case 4 (most common launch), the plan is:

- Run `php artisan migrate --force` — creates the schema (empty tables)
- Run **only** the system seeders (roles, currencies, settings, notification templates, vendor packages, categories, attributes, payment methods) — see section 12
- Create the first super-admin via `php artisan marketplace:create-super-admin` (see section 6)
- Do NOT run `DatabaseSeeder` in full — it seeds `admin@marketplace.test / password` which is a known-bad credential

If demo data was accidentally seeded onto production, the following users must be disabled or deleted before launch:

- `admin@marketplace.test` (super_admin — the known credential; change password and rename, or delete after creating a real super_admin)
- `staff@marketplace.test`, `vendor@marketplace.test`, `vendor2@marketplace.test`, `customer@marketplace.test`, `pending-vendor@marketplace.test`, `rejected-vendor@marketplace.test`

Cleanup SQL (**take a backup first**, then run):

```sql
-- Verify what will be deleted BEFORE running the DELETE
SELECT id, email, status FROM users
WHERE email IN (
    'admin@marketplace.test','staff@marketplace.test',
    'vendor@marketplace.test','vendor2@marketplace.test',
    'customer@marketplace.test','pending-vendor@marketplace.test',
    'rejected-vendor@marketplace.test'
);

-- Only run this after you've confirmed the above and taken a backup.
-- FK cascades will clean up vendor rows, product rows, orders, etc.
-- for the demo vendor / customer users.
DELETE FROM users WHERE email IN (
    'admin@marketplace.test','staff@marketplace.test',
    'vendor@marketplace.test','vendor2@marketplace.test',
    'customer@marketplace.test','pending-vendor@marketplace.test',
    'rejected-vendor@marketplace.test'
);
```

Demo products created by `DemoSeeder` are owned by the demo vendor; deleting the demo vendor row cascades to those products (verified by `foreign('vendor_id')...cascadeOnDelete()` on `products` migration).

<a id="6"></a>
## 6. First super-admin creation

Do **not** rely on `DatabaseSeeder`'s default `admin@marketplace.test / password`. Use the dedicated command shipped with this phase:

```bash
php artisan marketplace:create-super-admin --confirm
```

Behavior (see `app/Console/Commands/CreateSuperAdminCommand.php`):

- Interactive prompts for email + full name + password + confirmation
- Password entered via `secret()` — hidden from terminal, not saved to shell history
- Password strength enforced: minimum 12 chars + one upper + one lower + one digit + one symbol
- Refuses to run if a super_admin already exists, unless `--force` is passed
- Requires `--confirm` in `APP_ENV=production` (a self-check that mirrors `migrate --force`)
- Writes an entry to `audit_logs` with action `super_admin_created` — the password itself is never logged or stored anywhere except the users.password bcrypt hash
- If the users table has a `password_changed_at` column, sets it to `null` so downstream middleware can force a change on next login; if not, prints a warning to change manually

**Force-change-on-first-login is aspirational**: this codebase does not currently ship with a middleware that inspects `password_changed_at`. Add one in a future release. For now, the operator must change the password after first sign-in.

Do NOT create super-admins via direct SQL INSERT unless you have a specific compliance reason; direct INSERT skips the audit log and role assignment.

<a id="7"></a>
## 7. Backup plan

### 7.1 Backup command (mysqldump)

```bash
# Simple: dump one database, timestamped filename
mysqldump \
    --host="$DB_HOST" --port="$DB_PORT" \
    --user="$DB_USERNAME" --password="$DB_PASSWORD" \
    --single-transaction --routines --triggers --events \
    --default-character-set=utf8mb4 \
    --hex-blob \
    "$DB_DATABASE" \
    | gzip -c > "backup_${DB_DATABASE}_$(date +%F_%H-%M).sql.gz"
```

Flags explained:

- `--single-transaction` — consistent snapshot without locking tables (InnoDB only, which this schema uses everywhere)
- `--routines --triggers --events` — includes stored routines, triggers, and scheduled events (this project doesn't use them today, but future-proof)
- `--hex-blob` — safely dumps binary columns (encrypted supplier credentials)
- `| gzip -c` — compresses the dump inline

**Do not** run `mysqldump` without `--single-transaction` on a live DB — it locks tables and can cause 30+ second stalls for customer traffic.

### 7.2 Restore command

```bash
# WARNING: this OVERWRITES the current database.
# Confirm the file's contents (head backup.sql | head -50) before running.

# Uncompress + restore in one pipe
gunzip -c backup_YOUR_DB_2026-07-06_10-30.sql.gz \
    | mysql \
        --host="$DB_HOST" --port="$DB_PORT" \
        --user="$DB_USERNAME" --password="$DB_PASSWORD" \
        --default-character-set=utf8mb4 \
        "$DB_DATABASE"
```

For a partial restore (e.g. just one table), extract with `sed` or restore to a scratch DB first, then `INSERT INTO production_db.X SELECT * FROM scratch_db.X`.

### 7.3 Backup cadence

Minimum acceptable for a live e-commerce marketplace:

- **Daily full backup**: taken at low-traffic time (03:00 local for Kuwait = midnight UTC). Retained 30 days on primary storage, 90 days on cold storage.
- **Hourly incremental / binary-log backup**: for point-in-time recovery. MySQL's built-in binary logs are sufficient; enable `log_bin` + `binlog_expire_logs_seconds = 604800` (7 days).
- **Off-server copy**: SAME backup file uploaded to a different provider than the database (e.g. DB on RDS, backups to Wasabi/B2/S3 with cross-region replication). Compromise of the DB server should not compromise the backups.
- **Backup encryption**: `gpg --symmetric --cipher-algo AES256` on the .sql.gz before upload. Store the passphrase in the operator's password manager (NOT in `.env`).
- **Restore drill**: at least monthly, take yesterday's backup, restore to a scratch database, run a small suite of read queries to verify integrity. This proves the backups are actually restorable.

### 7.4 What NOT to do

- Do NOT store backups inside `storage/app/public/` or any web-accessible directory. If they must live on the app server temporarily, put them in `/var/backups/` with `chmod 600`.
- Do NOT let the backup process share credentials with the application user — a dedicated backup user with SELECT-only + LOCK TABLES privileges is cleaner.
- Do NOT skip the restore drill. Backups that have never been restored are often unusable in practice — restore drills catch problems (missing tables, encoding drift, permission issues) before a real emergency.

<a id="8"></a>
## 8. File + database backup coordination

Database rows reference files by path (`products.image_path`, `product_images.path`, `vendors.logo_path`, `vendors.banner_path`, `vendors.license_document_path`, etc.). A database backup that is 10 minutes newer than a storage backup can point at files that no longer exist — and vice versa.

### 8.1 What to back up alongside the database

- **Public storage** (`storage/app/public/`) — customer-uploaded profile pictures, product images stored locally (S3 mirror not required if using S3 as primary)
- **Private storage** (`storage/app/private/` or `storage/app/`) — vendor documents (license, ID), customization proofs, invoice PDFs
- **`.env`** — application secrets (encrypt separately; NEVER in the same tarball as the DB dump)
- **Nginx / Apache configuration + SSL certificates** — for disaster recovery
- **Application code** — if you deploy from Git, this is automatically covered; if not, tar the deploy tree

### 8.2 Consistency approach

Two options:

1. **Snapshot the whole disk** (cloud VPS snapshot on Linode/DO/AWS EBS). Guaranteed consistent — DB + files + config all at one moment. Simple. Expensive if done frequently.
2. **Coordinate**: enable Laravel maintenance mode (`php artisan down --refresh=15`) → take DB dump → sync files with rsync → `php artisan up`. Short maintenance window, cheap.

Option 2 timeline for a typical marketplace:

```bash
# 30 seconds to a few minutes of downtime for daily backup
php artisan down --refresh=15 --secret="OPERATOR_SECRET_ONE_OFF"

mysqldump ... | gzip -c > /var/backups/db_$(date +%F).sql.gz
rsync -a --delete storage/app/ /var/backups/storage_$(date +%F)/

php artisan up
```

The `--secret` flag lets the operator access the site during maintenance to verify the backup completed before restoring service.

### 8.3 If storage is on S3

Cross-region replication on the bucket + versioning is the standard approach; the storage side is effectively continuous, so only the DB needs the coordination. Storage retention should match DB retention (both 30d hot + 90d cold minimum).

### 8.4 Diagnostic query — DB↔storage drift

After restore, run this to find product rows pointing at missing files:

```sql
-- Non-executable diagnostic; wrap in an app command that stat()s each path
SELECT p.id, p.name, pi.path
FROM products p
JOIN product_images pi ON pi.product_id = p.id
ORDER BY p.id LIMIT 100;
```

Then use `Storage::disk('public')->exists($path)` in an Artisan command to check each path. A future release could add `php artisan storage:audit`.

<a id="9"></a>
## 9. Performance / index review

I audited the migrations for `->index(...)`, `->unique(...)`, and `->foreign(...)` on every major table. Summary:

| Table | Notable indexes | Verdict |
| --- | --- | --- |
| `users` | `(status)`, `(locale)`, `users_status_idx` | ✅ |
| `vendors` | `(status, created_at)`, `(country, city)`, `(featured)`, `vendors_status_idx` | ✅ |
| `products` | `(vendor_id, status)`, `(category_id, status)`, `(status, published_at)`, `(featured, featured_until)`, `UNIQUE(vendor_id, sku)`, `(fulfillment_mode)` + search perf indexes | ✅ excellent |
| `product_variants` | `(product_id, is_active)`, `UNIQUE(product_id, sku)` | ✅ |
| `categories` | `(parent_id, position)`, `(is_active, position)` | ✅ |
| `orders` | `(user_id, status)`, `(status, created_at)`, `(payment_status)`, `(status, payment_status)` | ✅ excellent |
| `order_items` | `(vendor_id, fulfillment_status)`, `(order_id, vendor_id)`, `(supplier_order_id)`, `(customization_status)`, `(product_id)`, `(promotion_id)` | ✅ excellent |
| `service_bookings` | `(service_provider_id, booked_for_date, booked_for_time)`, `(vendor_id, status, booked_for_date)`, `(user_id, status)` | ✅ excellent — 3-column composite |
| `support_tickets` | `(user_id, status, created_at)`, `(vendor_id, status, created_at)`, `(status, priority)`, `(assigned_to)`, `(status, created_at)` | ✅ excellent |
| `settings` | `UNIQUE(group, key)` | ✅ |
| `product_translations` | `UNIQUE(product_id, locale, field)`, `(product_id, locale, status)`, `(status, locale)` | ✅ |
| `carts` | `UNIQUE(user_id)` | ✅ |
| `cart_items` | `UNIQUE(cart_id, product_id, variant_id)`, `(vendor_id)` | ✅ |
| `coupons` | `UNIQUE(code)`, `(is_active, starts_at, ends_at)`, `(vendor_id, is_active)` | ✅ |
| `promotions` | `(is_active, starts_at, ends_at)`, `(vendor_id, is_active)`, `(approval_status)` | ✅ |
| `payments` | `(order_id, status)` | ✅ |
| `payment_transactions` | `(payment_id, type)` | ✅ |
| `product_reviews` | `UNIQUE(user_id, product_id, order_item_id)`, `(product_id, status)`, `(user_id, created_at)`, `(status)` | ✅ |
| `wishlists` | `UNIQUE(user_id, product_id)`, `(user_id, created_at)` | ✅ |
| `vendor_intelligence_alerts` | `(vendor_id, status, priority)`, `(vendor_id, alert_type, entity_type, entity_id, status)`, `UNIQUE(active_dedupe_key)` (v11B.4.2), `(status, expires_at)` | ✅ excellent |
| `vendor_intelligence_summaries` | `UNIQUE(vendor_id)`, `(computed_at)`, `(stale_at)` (v11B.4.2), `(last_digest_sent_at)` (v11B.4.3) | ✅ |
| `product_recommendations` | `UNIQUE(product_id, recommended_product_id, recommendation_type)`, `(product_id, recommendation_type, score)`, `(expires_at)` | ✅ |
| `recommendation_events` | `(recommendation_type, event_type, created_at)`, `(product_id, recommendation_type)`, `(recommended_product_id)`, `(created_at)`, `UNIQUE(order_item_id, event_type, product_id, recommendation_type)` | ✅ |
| `customer_affinities` | `UNIQUE(user_id, dimension, dimension_id, dimension_key)`, `(user_id, score)`, `(last_signal_at)` | ✅ |
| `search_queries` | `UNIQUE(query, locale)`, `(locale, is_blocked, search_count)` | ✅ |

**Verdict**: the schema is well-indexed. Every major filter mentioned in the directive (product status, vendor ID, category ID, order status, customer ID, created date, payment status, booking status, ticket status) is backed by at least one composite index.

Phase 10 v10.1 (`add_phase10_v101_performance_indexes.php`) and v10.14 (`add_phase10_v1014_performance_indexes.php`) already added targeted admin-panel indexes. Phase 6 (`add_search_performance_indexes_to_products.php`) added search indexes. No new indexes are needed at Phase 12 launch. If specific slow queries appear post-launch, add indexes surgically — do not preemptively add indexes without EXPLAIN evidence.

**How to profile post-launch**:

```sql
-- MySQL slow-query log — enable in my.cnf
[mysqld]
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow.log
long_query_time = 0.5      -- flag queries slower than 500ms
```

Then use `pt-query-digest` (Percona toolkit) on `/var/log/mysql/slow.log` to find the top-N slowest queries and their EXPLAIN plans.

### Before/after evidence template

For any index added post-launch, capture:

```sql
-- Before
EXPLAIN SELECT ... FROM ... WHERE ...;  -- expect "type: ALL" or "rows: N large"

-- Add index
ALTER TABLE X ADD INDEX ix_x_col (col1, col2);

-- After
EXPLAIN SELECT ... FROM ... WHERE ...;  -- expect "type: ref" or "rows: N small"
```

Save both plans in the phase's patch notes.

<a id="10"></a>
## 10. Integrity checks

A 20-query read-only diagnostic SQL script ships at `scripts/db-integrity-check.sql`. It checks:

1. Orphaned product images (product deleted)
2. Orphaned order items (order deleted)
3. Orphaned vendor products (vendor deleted)
4. Orphaned product translations
5. Orphaned recommendation events (source product)
6. Orphaned vendor intelligence alerts
7. **Duplicate active vendor intelligence alerts** — must be 0 after v11B.4.2 migration
8. Orders with mismatched item totals (rounding beyond 1 fils)
9. Users with no role assigned
10. Vendors without a corresponding user row
11. Approved vendors with no active subscription
12. Products with negative stock (data-corruption sentinel; `stock IS NULL` was the previous form but the column is non-nullable, so the check was meaningless)
13. Published products missing category
14. Cart items where product is deleted
15. Coupon usages with no matching coupon
16. Payments not linked to any order
17. **Duplicate product SKUs per vendor** — must be 0 (UNIQUE constraint)
18. Blocked search queries still counting up
19. Product reviews with rating out of [1, 5]
20. Service bookings for non-service products

Run it via:

```bash
mysql --host="$DB_HOST" --user="$DB_USERNAME" --password="$DB_PASSWORD" \
      "$DB_DATABASE" < scripts/db-integrity-check.sql \
      > "/tmp/integrity_$(date +%F).txt"
```

Expected outcome on a clean production DB: every count = 0. Any non-zero count is either (a) a bug, (b) historical drift from a period when a constraint wasn't enforced, or (c) intentional data (rare — investigate before dismissing).

**Do not delete** rows returned by the diagnostic without a backup. Some "orphans" are intentional retention (e.g. an anonymized-then-deleted customer whose orders must remain for tax auditing).

<a id="11"></a>
## 11. Database security

Confirm each item BEFORE launch:

- [ ] Database user has ONLY the privileges listed in §2.1 (no GRANT, no CREATE USER, no FILE, no SUPER). Verify with `SHOW GRANTS FOR 'DB_USERNAME'@'HOST';`
- [ ] Password is ≥ 20 chars random, generated by `openssl rand -base64 24`, stored only in `.env` on the app server + operator's password manager
- [ ] `bind-address` in `my.cnf` restricts MySQL to private IPs (`127.0.0.1` if same-server, or private subnet only). No `0.0.0.0` in production.
- [ ] Firewall (iptables / cloud security group) allows port 3306 only from the app server(s). Never from `0.0.0.0/0`.
- [ ] `.env` is NOT web-accessible: `/etc/nginx/sites-enabled/marketplace` blocks `/.env` and `/.git`. Verify: `curl -I https://YOUR_DOMAIN/.env` returns 404, not 200
- [ ] Backups are in `/var/backups/` or an S3 bucket, NOT in `/public/`, `/storage/app/public/`, or any web-accessible path
- [ ] Production `.env` is NOT in Git. Verify: `git log -p .env` returns "not found" (should be gitignored)
- [ ] SSL/TLS between app and DB in production, especially when app and DB are on different hosts. RDS enforces this; self-hosted MySQL needs `ssl-ca=` and `mysql.default_ssl_verify_server_cert = 1`
- [ ] Database user `root` password is different from `DB_USERNAME` password
- [ ] `mysql.user` table (or Percona equivalent) shows no anonymous or blank-password users: `SELECT user, host, authentication_string FROM mysql.user WHERE user = '' OR authentication_string = '';`
- [ ] Encrypted at rest (RDS / EBS-encrypted volume / LUKS on self-hosted). Data-at-rest encryption doesn't stop SQL injection but does stop physical-media theft
- [ ] Application encryption key (`APP_KEY`) is set + rotation plan documented. Rotating APP_KEY breaks `SupplierIntegration.credentials` (`encrypted:array` cast) — a rotation must include a decrypt-with-old-key + encrypt-with-new-key migration

<a id="12"></a>
## 12. Production seed policy

The `DatabaseSeeder` chain is layered. Some layers are safe for production; others are not.

| Seeder | Safe on production? | Purpose |
| --- | --- | --- |
| `RolesAndPermissionsSeeder` | ✅ | Creates 4 roles + 60+ permissions. Idempotent. **REQUIRED for launch.** |
| `CurrenciesSeeder` | ✅ | Seeds KWD/USD/AED/PKR with initial rates. Idempotent. **REQUIRED.** |
| `SettingsSeeder` | ✅ | Seeds site branding + defaults from `config/site.php`. Idempotent. **REQUIRED.** |
| `NotificationTemplatesSeeder` | ✅ | Seeds transactional email templates. Idempotent. **REQUIRED.** |
| `VendorPackagesSeeder` | ✅ | Seeds Basic/Standard/Pro package tiers. Idempotent. **REQUIRED.** |
| `CategoriesSeeder` | ⚠️ | Seeds a starter category tree. Safe to run once BEFORE launch; running after real vendors have listed products may create duplicates. Read the seeder before running. |
| `AttributesSeeder` | ⚠️ | Same caveat as Categories. |
| `PaymentMethodsSeeder` | ✅ | Seeds KNET/Visa/COD payment method rows. Idempotent. **REQUIRED.** |
| `EnsureAdminReportsAccessSeeder` | ✅ | Idempotent permission repair. Safe to re-run. **REQUIRED after every migration.** |
| **`DatabaseSeeder`** (whole thing) | ⚠️ | Creates `admin@marketplace.test / password` unconditionally. Do NOT run the full chain in production. |
| `DemoSeeder` | ❌ | Self-guards against production. Would create demo vendor + demo products. Never run on prod. |
| `ArabicProductContentSeeder` | ⚠️ | Only affects products with known demo slugs; safe if those slugs don't exist in production. |
| `BackfillProductTranslationsSeeder` | ✅ (idempotent) | Migrates JSON-column translations into `product_translations` workflow table. Idempotent; safe after real vendors add products with translations. |

**Recommended production seed sequence** (one time, at launch):

```bash
# Do NOT run "db:seed" (it runs DatabaseSeeder which includes the well-known admin credential).
# Run the required seeders individually:
php artisan db:seed --class=RolesAndPermissionsSeeder --force
php artisan db:seed --class=CurrenciesSeeder --force
php artisan db:seed --class=SettingsSeeder --force
php artisan db:seed --class=NotificationTemplatesSeeder --force
php artisan db:seed --class=VendorPackagesSeeder --force
php artisan db:seed --class=CategoriesSeeder --force            # only if starting fresh
php artisan db:seed --class=AttributesSeeder --force            # only if starting fresh
php artisan db:seed --class=PaymentMethodsSeeder --force
php artisan db:seed --class=EnsureAdminReportsAccessSeeder --force

# Then create the real super-admin:
php artisan marketplace:create-super-admin --confirm
```

<a id="13"></a>
## 13. Migration safety + rollback

**Rolling back a migration on production is dangerous.** Migrations that add columns/tables have `down()` methods that drop those columns/tables — and if live data is in them, that data is gone forever.

### 13.1 The safe policy

1. **Never `migrate:rollback` production** as first response to a bug. Instead:
2. **Fix forward**: add a new migration that reverses just the problematic change, deploy it via normal `migrate --force`
3. **Rollback only with**:
   - A fresh backup taken minutes ago (see §7)
   - Application in maintenance mode (`php artisan down`)
   - Written approval from a second engineer
   - A specific reason WHY forward-fix isn't viable

### 13.2 If you must roll back

```bash
# 1. Take a backup NOW
mysqldump ... | gzip -c > /var/backups/emergency_$(date +%F_%H-%M).sql.gz

# 2. Maintenance mode
php artisan down --refresh=15

# 3. Rollback ONE step (only)
php artisan migrate:rollback --step=1 --force

# 4. Verify
php artisan migrate:status

# 5. Bring back up if OK
php artisan up
```

Rolling back more than one step in a single command is almost never right; each step should be assessed.

### 13.3 What each recent migration would drop on rollback

| Migration | If rolled back, drops |
| --- | --- |
| `2027_01_01_000001_add_vendor_intelligence_digest_columns.php` | Digest send-log timestamps + opt-out flags (safe — regenerated data) |
| `2026_12_01_000001_add_vendor_intelligence_dedupe_and_stale.php` | UNIQUE constraint + stale marking columns (safe — regenerated) |
| `2026_11_01_000001_create_vendor_intelligence_tables.php` | Vendor intelligence summaries, alerts, feedback, quality scores (SAFE — regenerated, but loses feedback + quality scores) |
| Any migration in `2026_08_01_*` (personalization) | Customer views, affinities, preferences — losing this means personalization needs to relearn, but no customer-visible data lost |
| Any migration in `2026_07_01_*` (recommendations) | Product recommendations, pair stats — safe if you can regenerate |
| `2026_06_28_000001_create_product_translations_table.php` | ALL translations — DANGEROUS. Vendors will need to re-enter Arabic content |
| Anything in `2026_01_*` (core schema — products, orders, users, vendors) | CATASTROPHIC. All commerce data lost. Rolling back these is equivalent to `migrate:fresh` without dropping the whole DB |

<a id="14"></a>
## 14. Required commands + expected outputs

Run each of these in sequence during a deploy or verification pass. Capture the output.

### 14.1 Clear caches (safe on prod)

```bash
php artisan optimize:clear
# Expected: "Cached view files cleared", "Application cache cleared",
#           "Route cache cleared", "Configuration cache cleared",
#           "Compiled views cleared", "Events cache cleared"
```

### 14.2 Migration status (READ-ONLY, always safe)

```bash
php artisan migrate:status
# Expected on fresh v11B.4.3 → clean DB: 77 lines, each "Pending"
# Expected on incremental v11B.4.3 → up-to-date DB: 77 lines, each "Ran"
```

### 14.3 Route audit (site-settings)

```bash
php artisan route:list | grep -i site-settings
# Expected (v11B.4.3):
#   GET   /admin/site-settings           Admin\SiteSettingsController@index
#   POST  /admin/site-settings/{group}   Admin\SiteSettingsController@update
#   POST  /admin/site-settings/{group}/reset ...
# The {group} regex should include vendor_intelligence.
```

### 14.4 Vendor intelligence routes

```bash
php artisan route:list --path=vendor/intelligence
# Expected (v11B.4.3, inside vendor:approved group):
#   GET   /vendor/intelligence          Vendor\VendorIntelligenceController@index
#   POST  /vendor/intelligence/dismiss  Vendor\VendorIntelligenceController@dismiss
#   POST  /vendor/intelligence/snooze   Vendor\VendorIntelligenceController@snooze
```

### 14.5 Scheduler entries

```bash
php artisan schedule:list | grep vendor-intelligence
# Expected (v11B.4.2):
#   0 * * * *  php artisan vendor-intelligence:generate --stale-only
#   0 3 * * *  php artisan vendor-intelligence:prune
```

### 14.6 Preview pending migrations (dry-run)

```bash
php artisan migrate --pretend
# Expected: prints the SQL for every pending migration WITHOUT executing.
# Useful last check before migrate --force.
```

### 14.7 Vendor intelligence smoke tests

```bash
php artisan vendor-intelligence:generate --vendor=1
# Expected: "Regenerating intelligence for vendor #1 (Business Name)..." → "Done."

php artisan vendor-intelligence:generate --send-emails
# Expected: N vendors processed, N digest jobs dispatched. Jobs may or may not send
# emails depending on digest_emails_enabled + per-vendor gates (see PHASE_11B_4_3 report).

php artisan vendor-intelligence:generate --stale-only
# Expected: skips vendors whose stale_at is null; processes stale ones.

php artisan vendor-intelligence:prune
# Expected: removes expired dismissed/snoozed alerts + old feedback rows.
```

### 14.8 Test suite (should NOT be run against production DB)

```bash
# On STAGING with STAGING .env:
php artisan test --filter=Phase11B43   # 38 scenarios
php artisan test --filter=Phase11B42   # 43 scenarios
php artisan test --filter=Phase11B4    # 56 scenarios
php artisan test                       # full suite: 1556 scenarios present (pass/fail NOT verified in this package)
```

Never run `php artisan test` against a production database — some tests may DROP tables via `RefreshDatabase` trait.

### 14.9 Translations audit

```bash
php artisan translations:audit ar
# Expected: report of ar coverage across products / categories.
```

### 14.10 Frontend build (should be run at deploy, not runtime)

```bash
npm ci
npm run typecheck
npm run build
# Expected: build succeeds, produces public/build/ manifest.json + assets.
```

### 14.11 schema:dump — use with caution

```bash
php artisan schema:dump --prune
```

Only appropriate if the deploy workflow supports it. It:
1. Dumps the current schema into a single `database/schema/mysql-schema.sql`
2. If `--prune` is passed, DELETES all existing migration files that were consolidated
3. Future `migrate` calls will load the schema dump instead of running 77 migrations

This is a one-way operation. **Do not run `schema:dump --prune` unless the project workflow explicitly opts in.** For this marketplace, current recommendation is to NOT dump — the migration history is a useful audit trail and 77 migrations execute in seconds on empty MySQL 8.

<a id="15"></a>
## 15. Final go-live checklist

Sign each item before flipping DNS.

- [ ] Section 2: production DB + user created; `.env` populated; `APP_KEY` set and stored
- [ ] Section 3: `php artisan migrate:status` on production shows all 77 migrations as "Pending" (fresh) OR "Ran" (already migrated)
- [ ] Section 3: `php artisan migrate --force` completed without error; migrate:status now shows "Ran" for all
- [ ] Section 4: staging DB tested with `migrate:fresh --seed` + `php artisan test` (pending developer execution — see PHASE_12_DATABASE_READINESS_REPORT.md §4)
- [ ] Section 5: existing-data plan documented and executed; no `admin@marketplace.test` demo credential lingering on production
- [ ] Section 6: real super-admin created via `marketplace:create-super-admin --confirm`; can log in; can access `/admin`
- [ ] Section 7: backup script scheduled (cron / systemd timer); dry-run backup file produced and restored successfully to a scratch DB
- [ ] Section 8: storage backup coordinated with DB backup; both go off-server
- [ ] Section 9: index audit reviewed; no urgent additions
- [ ] Section 10: `scripts/db-integrity-check.sql` run against production; all 20 counts = 0 (or documented exceptions)
- [ ] Section 11: security checklist all boxes ticked
- [ ] Section 12: only the safe seeders run on production; DemoSeeder confirmed self-guarded
- [ ] Section 13: rollback policy documented and communicated to on-call
- [ ] Section 14: every command in this section run at least once; outputs archived in the ops repo
- [ ] Two humans have reviewed this checklist and both signed off

Once all boxes are checked, production is database-ready.

---

## Package deliverables

Files added to the Phase 12 delivery:

- `PHASE_12_DATABASE_READINESS_REPORT.md` — this document
- `app/Console/Commands/CreateSuperAdminCommand.php` — safe super-admin creation
- `scripts/db-integrity-check.sql` — 20-query read-only diagnostic

Files preserved unchanged from v11B.4.3:

- Every migration in `database/migrations/` (77 files)
- Every seeder in `database/seeders/` (13 files)
- All Vendor Intelligence infrastructure (v11B.4.2 + v11B.4.3)
- All prior-phase work back to v11B.3.3 approved baseline

## Honest declaration

Static verification only. No PHP, no MySQL, no live database in this sandbox. The operator must run the commands in section 14 against real staging and production infrastructure and archive the outputs. This report tells you WHAT to run and WHAT to expect; it doesn't run the commands for you.
