# Phase 12 — Production Database Setup (Quick Guide)

Compact operator-facing checklist. Detailed rationale in `PHASE_12_DATABASE_READINESS_REPORT.md`.

## One-shot: fresh production launch

Assumes MySQL 8.0+, Redis available, Laravel 11, this codebase at `Phase 11B.4 v11B.4.3` or later.

### 1. Create the DB + user

```sql
-- as MySQL root
CREATE DATABASE `DB_DATABASE` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'DB_USERNAME'@'DB_HOST' IDENTIFIED BY 'RANDOM_20_PLUS_CHARS';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP,
      REFERENCES, CREATE TEMPORARY TABLES, EXECUTE, TRIGGER,
      CREATE VIEW, SHOW VIEW
   ON `DB_DATABASE`.* TO 'DB_USERNAME'@'DB_HOST';
FLUSH PRIVILEGES;
```

### 2. Configure app

```bash
# Use the PRODUCTION example, not the development .env.example.
# .env.example is for local development (APP_DEBUG=true, weak defaults).
# .env.example.production has safe production defaults + CHANGE_ME placeholders.
cp .env.example.production .env

# Edit .env:
#   APP_ENV=production (already set in .env.example.production)
#   APP_DEBUG=false    (already set)
#   APP_URL=your real HTTPS domain
#   DB_DATABASE, DB_USERNAME, DB_PASSWORD, DB_HOST, DB_PORT
#   Redis: CACHE_STORE, QUEUE_CONNECTION, SESSION_DRIVER
#   Filesystem: FILESYSTEM_DISK=s3 (or public), plus AWS_* keys
#   Mail: MAIL_* — use a transactional provider account
#   Replace every CHANGE_ME_ placeholder with a real value
php artisan key:generate

# Verify the .env is not tracked in Git and has safe permissions:
grep -q '^\.env$' .gitignore || echo ".env" >> .gitignore
chmod 640 .env
```

### 3. Migrate

```bash
php artisan migrate:status         # expect: 77 rows, all Pending
php artisan migrate --pretend      # optional dry-run
php artisan migrate --force        # actually run
php artisan migrate:status         # expect: 77 rows, all Ran
```

### 4. Seed the required rows (NOT full DatabaseSeeder)

```bash
for S in RolesAndPermissionsSeeder CurrenciesSeeder SettingsSeeder \
         NotificationTemplatesSeeder VendorPackagesSeeder \
         CategoriesSeeder AttributesSeeder PaymentMethodsSeeder \
         EnsureAdminReportsAccessSeeder; do
    php artisan db:seed --class=$S --force
done
```

### 5. Create the real super-admin

```bash
php artisan marketplace:create-super-admin --confirm
# Interactive: email, name, password (with strength check)
```

### 6. Verify

```bash
php artisan route:list | grep site-settings
php artisan schedule:list | grep vendor-intelligence
mysql -u $DB_USERNAME -p $DB_DATABASE < scripts/db-integrity-check.sql \
    > /tmp/integrity.txt
# Read /tmp/integrity.txt — all counts should be 0
```

### 7. Schedule + queue

```bash
# crontab entry (once per minute):
* * * * * cd /path/to/marketplace && php artisan schedule:run >> /dev/null 2>&1

# queue worker (as systemd service, supervisor, or Horizon):
php artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
```

### 8. Backup

Schedule the mysqldump command from `PHASE_12_DATABASE_READINESS_REPORT.md` §7 to run daily at 03:00. Verify the first backup restores to a scratch DB.

Done. Site is up.

## What NOT to do

- ❌ `php artisan db:seed` without `--class=` in production (creates `admin@marketplace.test / password`)
- ❌ `php artisan migrate:fresh` on production (drops all tables)
- ❌ Store `.env` in Git
- ❌ Grant the app user `SUPER`, `FILE`, or `CREATE USER` privileges
- ❌ Skip the restore drill

## Automated deployment (v12.1)

Instead of running steps 3–7 manually, use the safe deployment script:

```bash
./scripts/deploy-production-phase12.sh
```

The script:
- Refuses to run if `APP_ENV=local`
- Warns if `APP_DEBUG=true`
- Verifies php/composer/npm/mysqldump availability
- Verifies DB reachable + free disk space
- Takes a `mysqldump` backup BEFORE running any migration
- Requires you to type `DEPLOY` to confirm
- Runs `php artisan migrate --force` (never `migrate:fresh`)
- Rebuilds caches, restarts queue workers gracefully
- Leaves the app in maintenance mode with recovery instructions on any failure

Full details in `scripts/deploy-production-phase12.sh` (fully commented) and `PHASE_12_MIGRATION_SAFETY.md`.

The old `scripts/deploy.sh` (Phase 10 v10.2) has been marked as **LEGACY — DO NOT USE FOR PRODUCTION** with a runtime guard that refuses to execute when `APP_ENV=production`.
