# Phase 12.2 v12.2.1 — Rollback Procedure

Tiered rollback if v12.2.1 introduces a regression. This release is CODE-ONLY — no database migrations, no seeder changes, no environment changes. Rollback is therefore simpler than the general production rollback in `PHASE_12_2_ROLLBACK_PLAN.md`.

## When to roll back

- ParseError still appears after deploy (my fix didn't take hold on your server)
- Personalization regression appears (my fix changed `CustomerAffinityService`'s hot path — semantically equivalent, but always test)
- Any of the 8 frontend fixes broke a page's rendering
- Prettier reformatting introduced an unexpected diff in a file I did NOT list as modified

## Tier 1 — Revert code (safe, fast, no downtime beyond a deploy)

### Prerequisites

- The prior approved archive: `marketplace-phase-12-2-production-launch-readiness.tar.gz`
- Or a Git tag / commit at the `phase-12-2-production-launch-readiness-reviewed` snapshot

### Step 1 — Enter maintenance mode

```bash
sudo -u www-data php artisan down --refresh=15 --secret="v1221-rollback-$(date +%s)"
```

### Step 2 — Restore the prior code

Option A — from Git:

```bash
cd /var/www/marketplace
sudo -u www-data git checkout phase-12-2-production-launch-readiness-reviewed
```

Option B — from archive:

```bash
sudo tar -xzf /path/to/marketplace-phase-12-2-production-launch-readiness.tar.gz \
    -C /var/www/ --strip-components=1
sudo chown -R www-data:www-data /var/www/marketplace
```

### Step 3 — Restart PHP-FPM + queue worker

```bash
sudo systemctl restart php8.3-fpm       # or your PHP version
sudo -u www-data php artisan queue:restart
sudo -u www-data php artisan optimize:clear
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
```

### Step 4 — Rebuild frontend

The v12.2.1 archive shipped the same JS/TS source with formatting/type fixes only. Reverting drops those fixes and returns to the prior source. Rebuild:

```bash
sudo -u www-data npm ci
sudo -u www-data npm run build
```

### Step 5 — Bring the site back up

```bash
sudo -u www-data php artisan up
curl -I https://YOUR_DOMAIN/
# Expected: HTTP 200
```

### Step 6 — Confirm rollback

- [ ] `cat VERSION` returns `Phase 12.2 Production Launch Readiness`
- [ ] Personalization + vendor intelligence pages load correctly
- [ ] `storage/logs/laravel.log` shows no new ParseError since revert
- [ ] Frontend assets served from fresh build (Network tab: no 404 on `/build/*`)

## What NOT to roll back

- **Database** — no DB changes were made in v12.2.1. No rollback needed on the DB side. Do not `migrate:rollback` — v12.2.1 did not run any migration.
- **`.env`** — no changes were made. Do not restore an older `.env`.
- **Queue workers / scheduler cron** — no changes. Do not touch Supervisor / systemd / crontab.

## Tier 2 — Fix forward (recommended over Tier 1)

If a specific fix in v12.2.1 introduces a regression, prefer to author a v12.2.2 patch that reverts just the problematic fix rather than reverting all 9 files:

1. Identify the specific fix that regressed (usually via the personalization test suite, since that's the risky change)
2. Revert only that file to its v12.2 form
3. Ship as v12.2.2 with a note

## Notes on the PHP fix

The `CustomerAffinityService.php` change was designed to be behavior-identical:

- Same three accumulators (`$catScores`, `$vendorScores`, `$bandScores`)
- Same `$signalCounts[$dim][$key]` increment
- Same `$lastSignal[$dim][$key]` update
- Same short-circuit on unknown dimensions

If the personalization test suite fails after v12.2.1 deploy, it's more likely a pre-existing test flakiness or environment issue than a real regression. Please share the failing test name before rolling back.

## Sign-off

- Reviewer name: _________________________
- Date: _________________________
- Rollback tier chosen (1 or 2): _________________________
- Reason for rollback: _________________________
- Post-rollback status: _________________________
