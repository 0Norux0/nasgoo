# Phase 10 — Backup & Recovery Guide

Covers Phase 10 §18: database backup, uploaded-file backup, restore procedure, deployment rollback, queue failure recovery, log locations, maintenance mode.

---

## 1. Database backup (nightly)

### Manual backup

```bash
mysqldump -u marketplace -p \
    --single-transaction --quick \
    --routines --triggers --events \
    marketplace > marketplace-$(date +%Y%m%d-%H%M%S).sql
```

`--single-transaction` runs the dump inside a transaction so the DB stays consistent without locking tables. `--quick` streams row-by-row to avoid loading the whole table into memory.

For a compressed backup:

```bash
mysqldump -u marketplace -p \
    --single-transaction --quick \
    --routines --triggers --events \
    marketplace | gzip > marketplace-$(date +%Y%m%d-%H%M%S).sql.gz
```

### Automated nightly via cron

Create `/usr/local/bin/backup-marketplace-db.sh`:

```bash
#!/usr/bin/env bash
set -euo pipefail

BACKUP_DIR=/var/backups/marketplace
mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"

STAMP=$(date +%Y%m%d-%H%M%S)
TARGET="${BACKUP_DIR}/db-${STAMP}.sql.gz"

mysqldump -u marketplace -p"${DB_PASSWORD}" \
    --single-transaction --quick \
    --routines --triggers --events \
    marketplace | gzip > "$TARGET"

chmod 600 "$TARGET"

# Retain last 14 days
find "$BACKUP_DIR" -name 'db-*.sql.gz' -mtime +14 -delete

echo "[$(date)] DB backup written to ${TARGET}"
```

```bash
sudo chmod +x /usr/local/bin/backup-marketplace-db.sh
sudo crontab -e
```

Add:

```cron
0 3 * * * DB_PASSWORD='<password>' /usr/local/bin/backup-marketplace-db.sh >> /var/log/marketplace-backup.log 2>&1
```

For better security, use `~/.my.cnf` with `0600` permissions instead of putting the password in the env line.

---

## 2. Uploaded file backup

The marketplace stores uploaded files in two locations:

- `storage/app/public/` — product images, vendor logos (public via the storage symlink)
- `storage/app/private/` (or `storage/app/`) — customization uploads, proof files, ticket attachments (private)

### Manual backup

```bash
cd /var/www/marketplace
sudo -u www-data tar -czf /var/backups/marketplace/storage-$(date +%Y%m%d-%H%M%S).tar.gz \
    -C storage/app .
```

This tarball contains BOTH the public and private trees in one archive. Restore order matters — see §4 below.

### Nightly cron

Append to `/usr/local/bin/backup-marketplace-db.sh` OR create a separate script:

```bash
STAMP=$(date +%Y%m%d-%H%M%S)
tar -czf "/var/backups/marketplace/storage-${STAMP}.tar.gz" \
    -C /var/www/marketplace/storage/app .
find /var/backups/marketplace -name 'storage-*.tar.gz' -mtime +14 -delete
```

### Cloud offload (recommended)

The backup tarballs are large; keep only 2-3 days locally and sync the rest to S3 / R2 / Wasabi:

```bash
# Example: AWS CLI
aws s3 cp /var/backups/marketplace/db-${STAMP}.sql.gz s3://marketplace-backups/db/
aws s3 cp /var/backups/marketplace/storage-${STAMP}.tar.gz s3://marketplace-backups/storage/
```

S3 lifecycle policies can auto-tier to Glacier after 30 days, expire after 1 year, etc.

---

## 3. Restore procedure

### Restore DB only

```bash
# Stop the app cleanly first
sudo -u www-data php artisan down
sudo systemctl stop marketplace-queue

# Restore
gunzip -c /var/backups/marketplace/db-20260615-030000.sql.gz | mysql -u marketplace -p marketplace

# Bring back up
sudo systemctl start marketplace-queue
sudo -u www-data php artisan up
```

### Restore files only

```bash
sudo -u www-data php artisan down

cd /var/www/marketplace
# Move the existing storage out of the way (don't delete yet)
sudo mv storage/app storage/app.bad
sudo -u www-data mkdir -p storage/app
sudo -u www-data tar -xzf /var/backups/marketplace/storage-20260615-030000.tar.gz \
    -C storage/app

sudo chown -R www-data:www-data storage/app
sudo -u www-data php artisan storage:link   # re-link if needed

sudo -u www-data php artisan up
```

If the new storage looks good, delete the old:

```bash
sudo rm -rf /var/www/marketplace/storage/app.bad
```

### Full restore (catastrophic recovery)

If the DB and files were lost together:

```bash
# 1. Provision fresh server, follow PHASE_10_DEPLOYMENT_GUIDE.md through step 2.4 (env config)
# 2. Restore the DB
gunzip -c db-<latest>.sql.gz | mysql -u marketplace -p marketplace

# 3. Restore storage
sudo -u www-data tar -xzf storage-<latest>.tar.gz -C /var/www/marketplace/storage/app

# 4. Set permissions + caches
sudo chown -R www-data:www-data /var/www/marketplace/storage
sudo -u www-data php artisan storage:link
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache

# 5. Start services
sudo systemctl start marketplace-queue
sudo systemctl reload nginx
sudo -u www-data php artisan up
```

---

## 4. Deployment rollback

If a deploy breaks production, prefer **DB restore over migration rollback** for any deploy that included a destructive migration.

### Path A — code-only rollback

If the deploy only changed code (no migrations, no destructive changes):

```bash
cd /var/www/marketplace
sudo -u www-data php artisan down --refresh=60
sudo -u www-data git checkout <previous-tag>
sudo -u www-data composer install --no-dev --optimize-autoloader
sudo -u www-data npm ci && npm run build
sudo -u www-data php artisan optimize:clear
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo systemctl restart marketplace-queue
sudo -u www-data php artisan up
```

### Path B — migration-included rollback (safe)

If the deploy included migrations, the cleanest rollback is:

```bash
cd /var/www/marketplace
sudo -u www-data php artisan down

# Step 1 — restore the DB to its pre-deploy state
gunzip -c /var/backups/marketplace/db-<pre-deploy timestamp>.sql.gz | mysql -u marketplace -p marketplace

# Step 2 — check out the previous code tag
sudo -u www-data git checkout <previous-tag>
sudo -u www-data composer install --no-dev --optimize-autoloader
sudo -u www-data npm ci && npm run build

# Step 3 — refresh caches
sudo -u www-data php artisan optimize:clear
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache

sudo systemctl restart marketplace-queue
sudo -u www-data php artisan up
```

### Path C — `migrate:rollback` (last resort)

Only use this when:
- The migration is purely additive (added a column, added an index)
- No data has yet been written to the new columns
- You have NO pre-deploy backup (you should always have one)

```bash
sudo -u www-data php artisan migrate:rollback --step=N --force
```

`migrate:rollback` runs the `down()` method which may not handle live data gracefully. For any destructive change, use Path B.

---

## 5. Queue failure recovery

The marketplace uses Redis-backed queues for: order confirmation emails, vendor notifications, customization proof workflow notifications, support-ticket notifications, and (Phase 10) optional queued large exports.

### Check failed jobs

```bash
sudo -u www-data php artisan queue:failed
```

This lists every job that exhausted retries. Each row shows the job class, payload, exception, and timestamp.

### Retry one or all

```bash
sudo -u www-data php artisan queue:retry <uuid>           # one job
sudo -u www-data php artisan queue:retry all              # all failed jobs
```

### Permanently remove a failed job

```bash
sudo -u www-data php artisan queue:forget <uuid>
sudo -u www-data php artisan queue:flush                  # nukes ALL failed
```

### Investigate stuck queues

```bash
# Watch the live queue
sudo -u www-data php artisan queue:monitor redis:default --max=100

# Inspect Redis directly
redis-cli -a "$REDIS_PASSWORD" LLEN queues:default
redis-cli -a "$REDIS_PASSWORD" LRANGE queues:default 0 10
```

If the worker dies repeatedly, check:

```bash
sudo systemctl status marketplace-queue
sudo journalctl -u marketplace-queue --since "1 hour ago"
```

Common causes: memory exhaustion (raise `--memory=512`), DB connection exhaustion (Laravel will reconnect; check MySQL `max_connections`), Redis auth failure (verify `REDIS_PASSWORD` matches the running Redis config).

---

## 6. Log locations

| Log | Path | Rotation |
|---|---|---|
| Laravel application | `storage/logs/laravel-<date>.log` | Daily via Monolog `daily` channel |
| Queue worker | `journalctl -u marketplace-queue` | systemd default (rotates) |
| Nginx access | `/var/log/nginx/access.log` | logrotate weekly |
| Nginx error | `/var/log/nginx/error.log` | logrotate weekly |
| PHP-FPM | `/var/log/php8.3-fpm.log` | logrotate weekly |
| MySQL | `/var/log/mysql/error.log` | logrotate weekly |
| Redis | `/var/log/redis/redis-server.log` | logrotate weekly |
| Backup script | `/var/log/marketplace-backup.log` | manual; rotate via logrotate |

Critical to monitor:
- `storage/logs/laravel-*.log` ERROR-level entries
- `storage/logs/laravel-*.log` for `LazyLoadingViolationException` (would indicate a strict-mode regression)
- Failed queue jobs (see §5)
- Nginx 5xx responses

If you have a log-aggregation tool (Loki, ELK, Splunk, CloudWatch), ship `storage/logs/*.log` + Nginx access + journalctl entries to it. The Laravel `LOG_CHANNEL=stack` config supports a Syslog channel that ships to remote receivers.

---

## 7. Maintenance mode

```bash
# Enter maintenance mode
sudo -u www-data php artisan down --refresh=60 --secret=<random secret>

# All requests return 503 except those carrying the secret cookie:
#   https://your-domain.example/<random secret>
# That URL sets a cookie that bypasses maintenance mode for the admin.

# Exit
sudo -u www-data php artisan up
```

Use during:
- Pre-deployment DB restore
- Any operation that involves dropping/renaming a table
- Major Redis flush

Don't use maintenance mode for ordinary deploys — Laravel handles in-flight requests well. Use it only when you actively need to block traffic.

---

## 8. Disaster recovery test (run quarterly)

A backup that hasn't been tested is hypothetical. Quarterly:

1. Spin up a fresh staging VM
2. Apply the latest backup (DB + storage tarball)
3. Verify:
   - Admin can log in at `/login`
   - At least one customer order is visible via `/admin/orders`
   - Product images render on `/products/<slug>`
   - Customization files are accessible to the owning customer
4. Document the test in a runbook: date, backup used, result, time to recover

This catches: corrupted backups, expired credentials, missing files in the tarball, schema drift between backup and current code.

---

## 9. Backup integrity check (monthly)

```bash
# Pick the latest DB backup
LATEST=$(ls -t /var/backups/marketplace/db-*.sql.gz | head -1)

# Verify gzip integrity
gunzip -t "$LATEST"  && echo "✓ gzip OK"

# Verify SQL parses (without applying)
gunzip -c "$LATEST" | mysql --execute='exit' --user=marketplace --password
# (executes only the connection step; doesn't import)
```

For the storage tarball:

```bash
LATEST=$(ls -t /var/backups/marketplace/storage-*.tar.gz | head -1)
tar -tzf "$LATEST" > /dev/null && echo "✓ tar OK"
```

If either check fails, investigate immediately. The backup script in §1 should email on failure (add `set -euo pipefail` to make any error cause the script to exit non-zero, then have cron's `MAILTO=` route the output).
