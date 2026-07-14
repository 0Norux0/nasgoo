# Phase 12 — Database Backup Plan

Cross-references section 7 + 8 of `PHASE_12_DATABASE_READINESS_REPORT.md`. Operator-focused.

## Backup schedule

| Cadence | Type | Retention |
| --- | --- | --- |
| Every 5 minutes | MySQL binary log (`log_bin`) | 7 days |
| Daily 03:00 local | Full `mysqldump` (gzipped, encrypted) | 30 days on-server, 90 days off-site |
| Daily 03:15 local | `storage/app/` rsync | Same as DB |
| Monthly | Full DB restore drill to scratch DB | Report retained 1 year |

## Backup script (drop into `/usr/local/bin/marketplace-backup.sh`)

```bash
#!/usr/bin/env bash
set -euo pipefail

# Load config (contains DB_* and BACKUP_* vars, not committed to Git)
source /etc/marketplace/backup.env

DATE=$(date +%F_%H-%M)
BACKUP_DIR="/var/backups/marketplace"
DUMP_FILE="${BACKUP_DIR}/db_${DB_DATABASE}_${DATE}.sql.gz"
GPG_FILE="${DUMP_FILE}.gpg"

mkdir -p "$BACKUP_DIR"

# 1. DB dump — single-transaction, all objects, hex-blob for encrypted cols
mysqldump \
    --host="$DB_HOST" --port="$DB_PORT" \
    --user="$DB_USERNAME" --password="$DB_PASSWORD" \
    --single-transaction --routines --triggers --events \
    --default-character-set=utf8mb4 --hex-blob \
    "$DB_DATABASE" \
    | gzip -9 -c > "$DUMP_FILE"

# 2. Encrypt at rest with GPG symmetric AES256
echo "$BACKUP_PASSPHRASE" | gpg --batch --yes --passphrase-fd 0 \
    --symmetric --cipher-algo AES256 \
    --output "$GPG_FILE" "$DUMP_FILE"
rm "$DUMP_FILE"

# 3. Upload off-site (example: rclone → S3-compatible)
rclone copy "$GPG_FILE" "$RCLONE_REMOTE:marketplace-backups/db/" --quiet

# 4. Retain last 30 days on-server; delete older
find "$BACKUP_DIR" -name 'db_*.sql.gz.gpg' -mtime +30 -delete

# 5. Also back up storage (paths that DB rows reference)
STORAGE_ARCHIVE="${BACKUP_DIR}/storage_${DATE}.tar.gz"
tar -czf "$STORAGE_ARCHIVE" -C /path/to/marketplace storage/app
rclone copy "$STORAGE_ARCHIVE" "$RCLONE_REMOTE:marketplace-backups/storage/" --quiet
find "$BACKUP_DIR" -name 'storage_*.tar.gz' -mtime +30 -delete

echo "[$(date -u +%Y-%m-%dT%H:%M:%SZ)] Backup complete: $GPG_FILE"
```

Cron entry:

```
0 3 * * *  /usr/local/bin/marketplace-backup.sh >> /var/log/marketplace-backup.log 2>&1
```

## Restore procedure (from encrypted backup)

```bash
# 1. Fetch from off-site
rclone copy "$RCLONE_REMOTE:marketplace-backups/db/db_YOUR_DB_2026-07-06_03-00.sql.gz.gpg" ./

# 2. Decrypt
echo "$BACKUP_PASSPHRASE" | gpg --batch --yes --passphrase-fd 0 \
    --decrypt --output ./db_restore.sql.gz \
    ./db_YOUR_DB_2026-07-06_03-00.sql.gz.gpg

# 3. VERIFY the dump — inspect before restoring
gunzip -c ./db_restore.sql.gz | head -50   # should show CREATE DATABASE / CREATE TABLE

# 4. Restore to a SCRATCH database FIRST (never overwrite production directly)
mysql -u $DB_USERNAME -p -e "CREATE DATABASE ${DB_DATABASE}_scratch;"
gunzip -c ./db_restore.sql.gz \
    | mysql -u $DB_USERNAME -p ${DB_DATABASE}_scratch

# 5. Smoke test scratch: row counts should match expected
mysql -u $DB_USERNAME -p ${DB_DATABASE}_scratch \
    -e "SELECT COUNT(*) FROM users; SELECT COUNT(*) FROM orders; SELECT COUNT(*) FROM products;"

# 6. Only then, restore to production (after taking a FRESH backup of current prod state)
```

## Monthly restore drill

Automated on the 1st of each month:

```bash
#!/usr/bin/env bash
# /usr/local/bin/marketplace-restore-drill.sh
set -euo pipefail

# Pick yesterday's backup
BACKUP=$(ls -t /var/backups/marketplace/db_*.sql.gz.gpg | head -1)

# Restore to drill DB
DRILL_DB="marketplace_restore_drill"
mysql -u "$DRILL_USER" -p"$DRILL_PASS" -e "DROP DATABASE IF EXISTS $DRILL_DB; CREATE DATABASE $DRILL_DB;"

echo "$BACKUP_PASSPHRASE" | gpg --batch --yes --passphrase-fd 0 --decrypt "$BACKUP" \
    | gunzip -c \
    | mysql -u "$DRILL_USER" -p"$DRILL_PASS" "$DRILL_DB"

# Assertion: at least one user + no obvious corruption
USER_COUNT=$(mysql -u "$DRILL_USER" -p"$DRILL_PASS" "$DRILL_DB" -Ne "SELECT COUNT(*) FROM users;")
if [ "$USER_COUNT" -eq 0 ]; then
    echo "DRILL FAILED: users table is empty" >&2
    exit 1
fi
echo "DRILL PASSED: $USER_COUNT users restored"
```

## Recovery targets

| Scenario | RPO (data loss) | RTO (downtime) |
| --- | --- | --- |
| App server crash (DB intact) | 0 | < 5 minutes (redeploy) |
| DB corruption, restore from last daily backup | ≤ 24h | ~30 minutes (dump size dependent) |
| DB corruption, restore + replay binlog | < 5 minutes | ~1 hour |
| Whole-region outage, restore off-site backup | ≤ 24h | ~4 hours (DNS + provision) |
