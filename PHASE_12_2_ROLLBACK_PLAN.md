# Phase 12.2 — Rollback Plan

What to do when a production deploy goes wrong. Tiered by severity — try Tier 1 first, escalate only if the problem persists.

## Golden rules

1. **Take a fresh backup BEFORE any rollback**
2. **Enter maintenance mode BEFORE any destructive action**
3. **Get a second engineer's confirmation for Tier 3**
4. **Prefer forward-fix over rollback**
5. **Database rollback is dangerous** — see the DB tier for warnings

## Tier 1 — Application-level rollback (safe, fast)

**When**: bad code deploy causing 500s, wrong route behavior, broken UI

**Downtime**: 1-3 minutes

### Step 1.1 — Maintenance mode

```bash
sudo -u www-data php artisan down --refresh=15
```

### Step 1.2 — Revert code

If using Git:

```bash
cd /var/www/marketplace
sudo -u www-data git log --oneline -5
# Identify the last-good commit
sudo -u www-data git checkout <previous-tag-or-sha>
```

If using tarball delivery:

```bash
sudo tar -xzf /var/backups/marketplace-phase-12-1-approved.tar.gz -C /var/www/
```

### Step 1.3 — Reinstall dependencies (if composer.lock changed)

```bash
sudo -u www-data composer install --no-dev --optimize-autoloader
sudo -u www-data npm ci && sudo -u www-data npm run build
```

### Step 1.4 — Rebuild caches

```bash
sudo -u www-data php artisan optimize:clear
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
sudo -u www-data php artisan event:cache
```

### Step 1.5 — Restart queue worker

```bash
sudo -u www-data php artisan queue:restart
# Wait 30 seconds for workers to cycle
sudo supervisorctl status marketplace-queue:*
```

### Step 1.6 — Bring the site back up

```bash
sudo -u www-data php artisan up
# Verify
curl -I https://YOUR_DOMAIN/
```

## Tier 2 — Frontend build asset rollback

**When**: JS/CSS bundle broken, `/build/*` 404s, "Vite manifest not found"

**Downtime**: 30 seconds if you kept the previous build

### Step 2.1 — Restore previous build

```bash
cd /var/www/marketplace
# If you saved public/build_old (see PHASE_12_2_OPTIMIZATION_GUIDE.md):
sudo -u www-data mv public/build public/build_broken
sudo -u www-data mv public/build_old public/build
```

If no previous build was kept, rebuild from the last-good code:

```bash
# After Tier 1 code rollback:
sudo -u www-data npm ci && sudo -u www-data npm run build
```

### Step 2.2 — Cache-bust CDN if applicable

If assets are behind CloudFront / Cloudflare, invalidate the `/build/*` path.

## Tier 3 — Database rollback

**⚠️ DANGEROUS.** Read this whole section before starting.

**When**: only when a migration causes irrecoverable data corruption AND forward-fix isn't viable

**Downtime**: 15-60 minutes

**Requires**: written approval from a second engineer + fresh backup within last 10 minutes

### Step 3.1 — Stop all writes

```bash
sudo -u www-data php artisan down --refresh=60 --secret="dbrollback-$(date +%s)"
sudo supervisorctl stop marketplace-queue:*
```

### Step 3.2 — Take an emergency backup

```bash
mysqldump \
    --host=localhost --user=DB_USERNAME --password \
    --single-transaction --routines --triggers --events \
    --default-character-set=utf8mb4 --hex-blob \
    DB_DATABASE \
    | gzip -c > /var/backups/marketplace/db_emergency_$(date -u +%FT%TZ).sql.gz
```

### Step 3.3 — Decide: rollback migration OR restore backup?

**Rollback migration** (drops columns from most recent migration):

```bash
sudo -u www-data php artisan migrate:status
# Identify the problematic migration

sudo -u www-data php artisan migrate:rollback --step=1 --force
# Rolls back the MOST RECENT migration only
```

**Restore backup** (whole database replaced):

```bash
# From the last-good backup, restore to a SCRATCH database first:
mysql -u DB_USERNAME -p -e "CREATE DATABASE db_scratch;"
gunzip -c /var/backups/marketplace/db_last_good.sql.gz | mysql -u DB_USERNAME -p db_scratch
# Verify row counts on scratch
# ONLY THEN swap production DB with scratch (via renames or `.env` swap)
```

### Step 3.4 — After rollback: match code to schema

```bash
# Revert code to a version that matches the rolled-back schema (Tier 1)
# Then rebuild caches (Tier 1 Step 1.4)
```

### Step 3.5 — Restart queue + bring up

```bash
sudo supervisorctl start marketplace-queue:*
sudo -u www-data php artisan up
```

### What each recent migration drops on rollback

From `PHASE_12_MIGRATION_SAFETY.md`:

| Migration | Rollback consequence | Risk |
| --- | --- | --- |
| `2027_01_01_000001_add_vendor_intelligence_digest_columns.php` | Loses digest send history + email opt-outs | LOW |
| `2026_12_01_000001_add_vendor_intelligence_dedupe_and_stale.php` | Loses UNIQUE + stale_at + last_generated_at | MEDIUM (duplicates could reappear) |
| `2026_11_01_000001_create_vendor_intelligence_tables.php` | Drops 4 VI tables + feedback history | HIGH |
| Older migrations | Catastrophic data loss on core commerce tables | DO NOT ROLL BACK |

## Tier 4 — Storage rollback

**When**: uploaded files corrupted or missing

**Downtime**: minimal

### Step 4.1 — Enter maintenance mode

```bash
sudo -u www-data php artisan down
```

### Step 4.2 — Restore from off-site backup

```bash
# Assuming rsync-style storage backup:
sudo rsync -av --delete /var/backups/marketplace/storage_2026-07-06/ \
    /var/www/marketplace/storage/app/

# Re-verify symlink
sudo -u www-data php artisan storage:link
```

### Step 4.3 — Fix ownership

```bash
sudo chown -R www-data:www-data /var/www/marketplace/storage
sudo chmod -R 775 /var/www/marketplace/storage
```

### Step 4.4 — Bring up

```bash
sudo -u www-data php artisan up
```

## Tier 5 — `.env` restore

**When**: `.env` corrupted or accidentally overwritten

**Downtime**: seconds if backup available

The operator should keep a copy of production `.env` in the password manager or encrypted backup. Restore:

```bash
sudo -u www-data cp /root/.env-marketplace-backup /var/www/marketplace/.env
sudo chmod 640 /var/www/marketplace/.env
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan queue:restart
```

## Tier 6 — DNS rollback

**When**: DNS was changed as part of deploy, and reverting the DNS solves the problem

**Downtime**: DNS TTL (usually 5-60 minutes for propagation)

- Log into DNS provider console
- Revert the A / AAAA record to the previous IP
- Wait for TTL propagation
- Verify via `dig YOUR_DOMAIN` from an external DNS resolver

## Queue restart procedure

After any code or DB change:

```bash
sudo -u www-data php artisan queue:restart
# Signals workers to exit gracefully after current job
# Supervisor / systemd auto-relaunches them
sleep 5
sudo supervisorctl status marketplace-queue:*
```

## Scheduler pause / resume

Pause (during maintenance):

```bash
# Comment out the cron line OR
sudo -u www-data crontab -l | sed 's|^\* \* \* \* \* cd|# \* \* \* \* \* cd|' | sudo -u www-data crontab -
```

Resume:

```bash
sudo -u www-data crontab -l | sed 's|^# \* \* \* \* \* cd|\* \* \* \* \* cd|' | sudo -u www-data crontab -
```

Alternative: use `php artisan schedule:interrupt` (Laravel 11+) to abort the currently-running scheduler.

## Maintenance mode reference

```bash
# Enter maintenance mode with a secret bypass URL:
php artisan down --refresh=15 --secret="OPERATOR_ONE_OFF"
# Users see: 503 with retry-after=15
# Operator bypasses via: https://YOUR_DOMAIN/OPERATOR_ONE_OFF

# Exit maintenance:
php artisan up
```

The `--refresh=15` header tells browsers to auto-retry every 15 seconds.

## Rollback verification (post-rollback)

- [ ] `curl -I https://YOUR_DOMAIN/` returns 200
- [ ] Login works
- [ ] No 500 errors in `storage/logs/laravel-$(date +%F).log` in last 5 min
- [ ] Queue worker is running
- [ ] Scheduler is running
- [ ] Payments work (test with staging gateway)
- [ ] Notify affected users if data was lost

## Communication during rollback

If customer-facing rollback affects data:

1. Post status page notice (if you have one)
2. Email affected customers if orders/data are affected
3. Post-mortem within 48 hours — root cause + timeline + prevention

## Do NOT do during rollback

- Do not roll back more than 1 migration step at a time
- Do not restore an old backup over a newer one without a fresh backup of the current state
- Do not restart the queue without stopping it cleanly (SIGKILL can leave jobs in an unknown state)
- Do not modify `.env` while `config:cache` is active — always `optimize:clear` first
- Do not skip the second-engineer approval for Tier 3
