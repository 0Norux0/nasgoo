# Phase 12 — Database Go-Live Checklist (compact)

Print, mark, sign, keep. Full rationale in `PHASE_12_DATABASE_READINESS_REPORT.md`.

## Pre-launch (T-7 days)

- [ ] Staging DB created; `.env` populated; APP_KEY set
- [ ] `php artisan migrate:fresh --seed` on staging → all 77 migrations Ran, all seeders complete
- [ ] `php artisan test` on staging → expected: all 1556 scenarios pass on staging (pending developer verification)
- [ ] Production DB + user created with restricted privileges (§2.1)
- [ ] Production `.env` populated; APP_KEY set; credentials rotated fresh
- [ ] Backup script deployed + tested (restore drill: pending developer verification per PHASE_12_DATABASE_BACKUP_PLAN.md §7.4)
- [ ] Off-site backup destination configured + first upload: pending developer verification

## Launch day (T-0)

- [ ] `php artisan migrate:status` on production → 77 rows all "Pending"
- [ ] `php artisan migrate --pretend` → SQL matches expectations
- [ ] Take FINAL pre-migration backup: `mysqldump ... > pre_launch.sql.gz`
- [ ] `php artisan migrate --force` → completes without error
- [ ] `php artisan migrate:status` → 77 rows all "Ran"
- [ ] Run only the system seeders (not full DatabaseSeeder):
   ```bash
   for S in RolesAndPermissionsSeeder CurrenciesSeeder SettingsSeeder \
            NotificationTemplatesSeeder VendorPackagesSeeder \
            CategoriesSeeder AttributesSeeder PaymentMethodsSeeder \
            EnsureAdminReportsAccessSeeder; do
     php artisan db:seed --class=$S --force
   done
   ```
- [ ] Create the real super-admin: `php artisan marketplace:create-super-admin --confirm`
- [ ] Confirm super-admin can log in at `/admin`
- [ ] Confirm NO `admin@marketplace.test` user exists
- [ ] `php artisan optimize:clear && php artisan config:cache && php artisan route:cache`
- [ ] `php artisan schedule:list | grep vendor-intelligence` → 2 lines
- [ ] Cron entry for `schedule:run` active
- [ ] Queue worker running (Horizon, systemd, or supervisor)
- [ ] `mysql ... < scripts/db-integrity-check.sql` → all 20 counts = 0
- [ ] `curl -I https://YOUR_DOMAIN/.env` → 404
- [ ] `curl -I https://YOUR_DOMAIN/.git/config` → 404
- [ ] SSL cert valid, auto-renew scheduled
- [ ] Application health check `/health` returns 200

## Post-launch (T+1 hour)

- [ ] Error logs quiet (`tail /path/to/marketplace/storage/logs/laravel.log` — no repeated stack traces)
- [ ] Slow query log check (top query < 500ms; if not, capture EXPLAIN, plan indexes)
- [ ] MySQL `SHOW PROCESSLIST` — no runaway queries
- [ ] Redis memory usage healthy
- [ ] Queue worker processing jobs (no growing `failed_jobs` table)
- [ ] First real customer registration + first real order attempted end-to-end

## Post-launch (T+24 hours)

- [ ] Daily backup ran overnight; encrypted copy in off-site bucket — pending developer verification of both
- [ ] `scripts/db-integrity-check.sql` re-run → still all 0
- [ ] Vendor intelligence hourly regeneration ran (check `vendor_intelligence_summaries.last_generated_at`)
- [ ] Audit log has expected entries (`SELECT action, COUNT(*) FROM audit_logs WHERE created_at > NOW() - INTERVAL 24 HOUR GROUP BY action;`)

## Sign-off

| Item | Signed by | Date |
| --- | --- | --- |
| Pre-launch (T-7) | | |
| Launch day (T-0) | | |
| Post-launch (T+24h) | | |

Two engineers must sign each row.

## v12.1 additions

- [ ] Copied `.env.example.production` → `.env` (NOT `.env.example`)
- [ ] Every `CHANGE_ME_` placeholder in `.env` replaced with real values
- [ ] `.env` file permissions set to 0640
- [ ] Deployment run via `./scripts/deploy-production-phase12.sh` (not the legacy `deploy.sh`)
- [ ] Backup file created by the script exists and is non-empty
- [ ] Restore command from the script's log file has been tested against a scratch DB (pending developer verification of at least one restore drill)
- [ ] `scripts/deploy.sh` legacy guard refused to run when APP_ENV=production (verified by an operator once, then leave the guard in place)
