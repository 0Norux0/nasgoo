# Phase 12.2 — Queue Worker Guide

Setup and operation of the queue worker for production. Grounded in the actual Job classes shipped with this codebase.

## Queue-driven work in this codebase

Enumerated from `app/Jobs/` and `app/Mail/`:

| Class | Purpose | Queue mode |
| --- | --- | --- |
| `App\Jobs\SendVendorIntelligenceDigest` | Sends vendor intelligence digest email (Phase 11B.4.3) | `implements ShouldQueue` — always queued |
| `App\Jobs\QueueProductTranslation` | Queues an async translation request for a product | `implements ShouldQueue` |
| `App\Jobs\RecordPurchaseAttributionJob` | Records recommendation → purchase attribution after order completion | `implements ShouldQueue` |
| `App\Mail\VendorIntelligenceDigestMail` | Mailable dispatched by SendVendorIntelligenceDigest | Auto-queued when `Mail::to()->send()` inside a queued job |

All three jobs use the default queue. No priority queues configured.

## Queue driver recommendation

For production, use **Redis** or **database** — never `sync` (which runs jobs in-process and defeats the purpose).

### Redis (preferred)

Pros: fastest dispatch/pop, atomic operations, Horizon support if desired later.

`.env`:
```env
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=CHANGE_ME_REDIS_PASSWORD
```

### Database

Pros: no extra service required; jobs stored in `jobs` table alongside app data (easier backup consistency).

Cons: slower under high load; polling adds DB writes.

`.env`:
```env
QUEUE_CONNECTION=database
```

If using database queue, verify the migration ran:

```bash
$ php artisan migrate:status | grep -i jobs
2014_10_12_000000  create_users_table                 [Ran]
0001_01_01_000002  create_jobs_table                  [Ran]
```

(Laravel 11's default `0001_01_01_000002_create_jobs_table.php` creates `jobs` + `job_batches` + `failed_jobs`.)

## Queue worker command

Basic:

```bash
php artisan queue:work \
    --queue=default \
    --tries=3 \
    --backoff=30 \
    --timeout=120 \
    --max-jobs=1000 \
    --max-time=3600 \
    --memory=256
```

Options explained:

- `--tries=3` — retry a failed job 3 times before moving to `failed_jobs`
- `--backoff=30` — wait 30 seconds between retries (grows exponentially by default with `retryUntil()` on the job class)
- `--timeout=120` — SIGKILL a job that runs longer than 120 s (protects against DB deadlocks)
- `--max-jobs=1000` — restart the worker after 1000 jobs (memory hygiene)
- `--max-time=3600` — restart the worker after 1 hour (memory hygiene)
- `--memory=256` — restart if memory exceeds 256 MB

**Important**: `queue:work` runs forever. Do NOT run it inside a bare shell — the worker will die on logout. Use Supervisor or systemd (see below).

## Supervisor configuration (recommended)

Create `/etc/supervisor/conf.d/marketplace-queue.conf`:

```ini
[program:marketplace-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/marketplace/artisan queue:work --queue=default --tries=3 --backoff=30 --timeout=120 --max-jobs=1000 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/marketplace-queue.log
stopwaitsecs=130
```

Apply:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start marketplace-queue:*
sudo supervisorctl status marketplace-queue:*
# Expected: RUNNING (both processes)
```

Note: `numprocs=2` starts two workers. Increase to 4-8 on a busy marketplace. Each worker uses ~50 MB idle. `stopwaitsecs=130` gives workers 10 seconds more than `--timeout` to finish gracefully during restart.

## systemd alternative

If your host doesn't run Supervisor, use systemd. Create `/etc/systemd/system/marketplace-queue.service`:

```ini
[Unit]
Description=Marketplace queue worker
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/marketplace
ExecStart=/usr/bin/php artisan queue:work --queue=default --tries=3 --backoff=30 --timeout=120 --max-jobs=1000 --max-time=3600
Restart=always
RestartSec=5
StandardOutput=append:/var/log/marketplace-queue.log
StandardError=append:/var/log/marketplace-queue.log

[Install]
WantedBy=multi-user.target
```

Apply:

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now marketplace-queue
sudo systemctl status marketplace-queue
```

For multiple workers, create additional units (`marketplace-queue@.service` as a template).

## Deployment restart procedure

After deploying new code, the queue worker MUST be restarted — otherwise it keeps running the OLD code from memory.

`scripts/deploy-production-phase12.sh` line 261 sends the signal:

```bash
php artisan queue:restart
```

Supervisor / systemd sees the workers exit (they check the signal file between jobs) and relaunches them automatically. No manual `supervisorctl restart` needed.

## Failed jobs handling

Query failed jobs:

```bash
php artisan queue:failed
# Displays a table: ID, Connection, Queue, Class, Failed At
```

Retry all failed:

```bash
php artisan queue:retry all
```

Retry one:

```bash
php artisan queue:retry <job-id>
```

Delete a failed job (do NOT retry — usually because the underlying data is broken):

```bash
php artisan queue:forget <job-id>
```

Prune failed jobs older than 72h (scheduled — see PHASE_12_2_SCHEDULER_GUIDE.md):

```bash
php artisan queue:prune-failed --hours=72
```

Confirmed scheduled in `routes/console.php`:

```php
Schedule::command('queue:prune-failed --hours=72')->daily();
```

## Log file location

Worker output (Supervisor): `/var/log/marketplace-queue.log`
Laravel-level exceptions inside jobs: `storage/logs/laravel.log` (rotated daily via Laravel's `stack` → `daily` channel).

Both should be monitored. See PHASE_12_2_LOGGING_MONITORING_GUIDE.md.

## Queue-dependent features (all use ShouldQueue)

- Vendor intelligence digest emails — throttled per-vendor to once per 24h (default)
- Product translation batch requests
- Recommendation-attribution recording (fires after order completion)

Failure of the queue worker means:
- Vendors don't get intelligence digests (soft failure; alerts still visible in-dashboard)
- Product translations don't get requested (soft; visible as `status=missing` in the admin)
- Recommendation attribution isn't recorded (soft; `recommendation_events` misses the `event_type=purchase` row → downstream analytics slightly inaccurate)

None of these cause immediate customer-facing breakage. But if the worker is DOWN for days, digest backlog + missing attributions add up. Monitor.

## Evidence status

| Claim | Status | Evidence |
| --- | :---: | --- |
| 3 Job classes present | ✅ | `ls app/Jobs/` → 3 files |
| `SendVendorIntelligenceDigest` implements ShouldQueue | ✅ | `grep "implements ShouldQueue" app/Jobs/SendVendorIntelligenceDigest.php` |
| Failed job pruner scheduled | ✅ | `grep "queue:prune-failed" routes/console.php` |
| Redis client in composer.json | ⏳ | Verify `predis/predis` or PHP `ext-redis` installed on server |
| Queue worker running under Supervisor/systemd | ⏳ | Operator sets up unit file |
| `queue:failed` returns empty on production | ⏳ | Operator runs after first deploy |
| Queue driver configured to non-`sync` | ⏳ | Operator sets `QUEUE_CONNECTION` in real `.env` |
