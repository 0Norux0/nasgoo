# Phase 12.2 — Logging & Monitoring Guide

Production observability setup. Grounded in Laravel 11's default log config + the `.env.example.production` LOG_* settings.

## Log locations

| Log | Path | Written by |
| --- | --- | --- |
| Application (all errors) | `storage/logs/laravel.log` (rotated daily) | Laravel `Log::` facade + framework |
| Queue worker output | `/var/log/marketplace-queue.log` (Supervisor) or `journalctl -u marketplace-queue` (systemd) | Worker process |
| Scheduler output | `/var/log/marketplace-scheduler.log` (if configured) or silent (`>/dev/null 2>&1`) | Cron |
| Backup output | `/var/log/marketplace-backup.log` | Backup cron |
| Web server access | `/var/log/nginx/access.log` or `/var/log/apache2/access.log` | nginx / apache |
| Web server errors | `/var/log/nginx/error.log` or `/var/log/apache2/error.log` | nginx / apache |
| MySQL slow query | `/var/log/mysql/slow.log` (if enabled) | MySQL |
| Redis | `/var/log/redis/redis-server.log` | Redis |

## Laravel log configuration

`.env.example.production` sets:

```env
LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=error
LOG_DAILY_DAYS=30
```

Interpretation:

- **`stack`** → uses the `stack` channel, which combines multiple lower-level channels
- **`daily`** → Laravel rotates `storage/logs/laravel.log` to `laravel-YYYY-MM-DD.log` at midnight; keeps N days
- **`error`** → only ERROR and CRITICAL messages recorded (not DEBUG, INFO, NOTICE, WARNING)
- **`30 days`** → old rotated logs deleted after 30 days

Adjust `LOG_LEVEL` temporarily during incident response:

```bash
# In .env, change:
LOG_LEVEL=debug        # verbose — for troubleshooting only
# Then:
php artisan config:cache
# After troubleshooting:
LOG_LEVEL=error        # back to normal
```

## Log rotation

Laravel's `daily` channel handles rotation for `laravel-*.log`. Verify at day boundary:

```bash
ls -la storage/logs/ | head
# Expected: laravel-YYYY-MM-DD.log for today + prior days up to 30
```

System-level rotation (belt-and-suspenders) — add `/etc/logrotate.d/marketplace`:

```
/var/www/marketplace/storage/logs/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
    sharedscripts
}
```

## Failed job monitoring

The `failed_jobs` table grows every time a queued job fails 3 times. Watch its size:

```sql
SELECT COUNT(*) AS failed_count,
       MIN(failed_at) AS oldest_failure,
       MAX(failed_at) AS most_recent
FROM failed_jobs;
```

- `failed_count > 0` — investigate; retry or delete after fixing
- `failed_count > 100` — something is systematically broken
- `oldest_failure > 3 days ago` — investigate why it's not being retried

The `queue:prune-failed --hours=72` scheduled command (see PHASE_12_2_SCHEDULER_GUIDE.md) auto-cleans older-than-3-days rows, but the WHY of failures must be understood before pruning normalizes.

## Scheduler monitoring

If the cron for `schedule:run` stops firing, scheduled tasks silently don't run. Recommended: external heartbeat monitor.

Add a ping at the end of your cron line:

```cron
* * * * * cd /var/www/marketplace && php artisan schedule:run > /dev/null 2>&1 && curl -fsS -m 10 --retry 3 https://hc-ping.com/your-uuid > /dev/null
```

Services: Healthchecks.io (open source), Cronitor, Dead Man's Snitch.

If the ping is missed for > 5 minutes, the service alerts you.

## Queue worker monitoring

Supervisor status:

```bash
sudo supervisorctl status marketplace-queue:*
# Expected: all processes RUNNING with recent PIDs
```

systemd status:

```bash
sudo systemctl status marketplace-queue
```

If workers keep dying and restarting (autorestart), grep for exit codes:

```bash
sudo journalctl -u marketplace-queue | grep -iE "error|exit"
```

## Disk space monitoring

Full disk = production outage. Watch `/`, `/var`, and wherever backups live:

```bash
df -h | grep -E "/$|/var|/var/backups"
```

Alert at 80% full. Panic at 90%.

Common growth causes:

- `storage/logs/*.log` — LOG_DAILY_DAYS mis-set → grows unbounded
- `storage/framework/cache/data/` — Laravel cache — if using file driver, prune periodically
- `backups/` — if backups aren't offloaded → grows daily by DB size
- `/var/lib/mysql/*` — DB naturally grows; MySQL binlogs need `binlog_expire_logs_seconds`

## Database backup monitoring

Confirm each daily backup succeeded:

```bash
# Check the most recent backup file
ls -la /var/backups/marketplace/ | tail -5

# Verify it's not empty
find /var/backups/marketplace/ -name '*.sql.gz.gpg' -mmin -1440 -size +1M
# Expected: at least one file from the last 24h, larger than 1 MB
```

If a backup is missing or empty, treat as urgent — the next disaster has no restore point.

## Error alert recommendations

Options for production error alerting:

### Sentry (recommended)

```bash
composer require sentry/sentry-laravel
```

Configure in `.env`:

```env
SENTRY_LARAVEL_DSN=https://your-dsn@sentry.io/project-id
SENTRY_TRACES_SAMPLE_RATE=0.1
```

Every uncaught exception is sent to Sentry with stack trace, request context, and user context (careful about PII). Free tier handles most marketplace loads.

### Bugsnag / Rollbar

Similar setup, alternative providers.

### Homegrown

If external providers are out of scope:

- Route `LOG_CHANNEL=stack` to include a `slack` channel that posts CRITICAL messages to a webhook
- Or: `mail` channel that emails you on ERROR+
- Configure in `config/logging.php`

Sample `.env`:

```env
LOG_CHANNEL=stack
LOG_STACK=daily,slack
LOG_SLACK_WEBHOOK_URL=https://hooks.slack.com/services/...
```

## Uptime monitoring

External service that pings your site every 60 seconds. If 2 consecutive pings fail → alert you.

Options: UptimeRobot (free), Pingdom, Better Uptime, Datadog Synthetics.

Configure a ping to `https://YOUR_DOMAIN/up` (Laravel's built-in health endpoint). Alert on:

- HTTP status != 200
- Response time > 3 s
- SSL certificate expiry < 7 days

## Do NOT expose logs publicly

Confirmed statically:

```bash
$ grep "location.*storage/logs\|Alias.*storage/logs" scripts/ 2>/dev/null
# → 0 matches (deploy script doesn't expose logs)
```

But nginx / apache config is the operator's responsibility. Confirm:

```bash
# On production
curl -I https://YOUR_DOMAIN/storage/logs/laravel.log
# Expected: 404 (nginx blocks it)
```

## Sensitive data in logs

Laravel's default logging can accidentally include request bodies, which may contain passwords or tokens. Confirm the log filter is set:

```bash
$ grep -r "hideParameters\|dontFlash" bootstrap/ 2>/dev/null
```

Laravel 11 defaults hide `password`, `password_confirmation`, `token`, etc. If you add custom sensitive fields, extend the filter.

## Log inspection during incidents

Quick commands:

```bash
# Tail live errors
tail -f storage/logs/laravel-$(date +%F).log

# Search for a specific error message
grep -A20 "SQLSTATE" storage/logs/laravel-*.log | head -50

# Errors in the last hour
find storage/logs -name '*.log' -mmin -60 -exec grep -l ERROR {} \;

# Errors for a specific user
grep -A10 "user_id.*1234" storage/logs/laravel-*.log
```

## Evidence status

| Claim | Status | Evidence |
| --- | :---: | --- |
| `.env.example.production` sets `LOG_CHANNEL=stack` | ✅ | grep |
| `.env.example.production` sets `LOG_LEVEL=error` | ✅ | grep |
| `.env.example.production` sets 30-day retention | ✅ | grep |
| Deploy script does not expose logs | ✅ | No public location for storage/logs in scripts |
| logrotate config installed | ⏳ | Operator adds `/etc/logrotate.d/marketplace` |
| Sentry / Bugsnag / etc. configured | ⏳ | Operator chooses + installs |
| Uptime monitor pinging `/up` | ⏳ | Operator sets up |
| Disk space alert configured | ⏳ | Operator sets up |
| Nginx blocks public log access | ⏳ | Operator adds nginx location + verifies with curl |
| `queue:failed` returns 0 rows | ⏳ | Operator monitors |
| Backup cron ran overnight | ⏳ | Operator monitors |
