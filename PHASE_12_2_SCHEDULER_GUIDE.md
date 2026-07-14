# Phase 12.2 — Scheduler Guide

Setup and monitoring of the Laravel task scheduler for production. Grounded in the actual `routes/console.php` scheduled commands.

## Scheduled commands (from `routes/console.php`)

Verified via:

```bash
$ grep -B1 "Schedule::command" routes/console.php
```

| Command | Frequency | Purpose | Phase |
| --- | --- | --- | --- |
| `queue:prune-failed --hours=72` | daily | Removes `failed_jobs` rows older than 72 hours | Core |
| `recommendations:generate --since=2` | daily at 03:30 | Regenerates product recommendation pairs from last 2 days | Phase 7 |
| `personalization:prune` | daily at 03:00 | Removes stale customer_product_views > N days | Phase 8 |
| `personalization:rebuild --stale-days=1` | daily at 03:15 | Recomputes customer_affinities for users active last N days | Phase 8 |
| `vendor-intelligence:generate --stale-only` | hourly | Regenerates VI summaries for vendors whose stale_at is set | Phase 11B.4 v11B.4.2 |
| `vendor-intelligence:prune` | daily at 03:00 | Prunes expired dismissed/snoozed VI alerts + old feedback | Phase 11B.4 v11B.4.2 |

All entries use `withoutOverlapping()` or equivalent guards where a long-running previous execution could still be in flight. The vendor-intelligence entries use it explicitly (per Phase 11B.4.2 fix Defect 2).

## Runtime verification (operator must run)

```bash
php artisan schedule:list
```

Expected: the six commands above listed, with next-run timestamps. If any are MISSING, `routes/console.php` was not deployed properly.

## Cron entry (required on production)

Laravel's scheduler needs a single cron entry to fire every minute:

```cron
* * * * * cd /var/www/marketplace && php artisan schedule:run >> /dev/null 2>&1
```

Install via `crontab -e` as the app user (typically `www-data`):

```bash
sudo -u www-data crontab -e
# Then paste the line above, save.

sudo -u www-data crontab -l
# Verify the line is present.
```

Replace `/var/www/marketplace` with your actual project root.

## Logging the scheduler

Silent cron (`>> /dev/null 2>&1`) is convenient but hides errors. Better:

```cron
* * * * * cd /var/www/marketplace && php artisan schedule:run >> /var/log/marketplace-scheduler.log 2>&1
```

Or use Laravel's own logging by adding `->onFailure()` to individual schedules if you want per-command failure notifications.

## No duplicate scheduler warning

If you run multiple app nodes (load-balanced setup), running the cron on ALL of them will cause duplicate execution of every scheduled task. Two options:

1. **Cron only on ONE node** — designate one as the "scheduler node." Simple, single point of failure.
2. **Use `onOneServer()`** — Laravel's built-in cache-based locking. Requires a shared cache (Redis) and `->onOneServer()` on every scheduled command. The current `routes/console.php` does NOT use `onOneServer()` — if you go multi-node, add it.

Example update (do NOT apply unless multi-node):

```php
Schedule::command('vendor-intelligence:generate --stale-only')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();   // ← add this line
```

## Failure detection

If a scheduled task fails silently, no user notices until stale data is customer-visible. Recommended: monitor the scheduler with a heartbeat.

Options:

- **Cron-monitoring service** (Healthchecks.io, Cronitor, Dead Man's Snitch): add a curl call at the end of the cron line that pings the service. If the ping is missed for > 5 minutes, the service alerts you.

  ```cron
  * * * * * cd /var/www/marketplace && php artisan schedule:run > /dev/null 2>&1 && curl -fsS -m 10 --retry 3 https://hc-ping.com/your-uuid > /dev/null
  ```

- **Laravel's `->emailOutputTo()`**: on individual commands, capture output to a file and email it on failure. Heavier but works out of the box.

- **`->onFailure(fn() => ...)`**: register a callback on each schedule that logs / notifies.

## Backup scheduling

The database backup script (see `PHASE_12_DATABASE_BACKUP_PLAN.md`) is a SEPARATE cron entry — NOT run through Laravel's scheduler. This keeps backups isolated from app-level failures:

```cron
0 3 * * * /usr/local/bin/marketplace-backup.sh >> /var/log/marketplace-backup.log 2>&1
```

Schedule this AFTER the Laravel scheduler entry, so both are visible in `crontab -l`.

## Manual runs (operator sanity)

Run a specific scheduled command right now:

```bash
sudo -u www-data php artisan vendor-intelligence:generate --vendor=1
sudo -u www-data php artisan queue:prune-failed --hours=72
```

Use these to verify a specific command works BEFORE relying on the scheduler.

## Evidence status

| Claim | Status | Evidence |
| --- | :---: | --- |
| 6 scheduled commands in codebase | ✅ | `grep -c "Schedule::command" routes/console.php` returns 6 |
| Vendor intelligence uses `withoutOverlapping()` | ✅ | `grep "withoutOverlapping" routes/console.php` |
| Vendor intelligence commands scheduled hourly + daily | ✅ | Phase 11B.4.2 Defect 2 fix — verified in Phase11B42 Pest §Defect2.1, §Defect2.2 |
| `schedule:list` shows all 6 commands on server | ⏳ | Operator runs after deploy |
| Cron entry installed | ⏳ | `crontab -l` on server should show the entry |
| Heartbeat monitoring | ⏳ | Operator sets up Healthchecks.io / cronitor / etc |
| Backup cron scheduled | ⏳ | Operator adds the backup entry to crontab |
