# Phase 12.2 — Go-Live Checklist

Master sign-off list. Print. Mark. Two engineers sign the final line. Keep the signed copy.

## Infrastructure

- [ ] Domain registered and pointed to production server (verify `dig YOUR_DOMAIN` returns server IP)
- [ ] SSL certificate active (verify `curl -Ik https://YOUR_DOMAIN` returns `HTTP/2 200`)
- [ ] SSL certificate valid for > 30 days (`openssl s_client -connect YOUR_DOMAIN:443 </dev/null 2>/dev/null | openssl x509 -noout -dates`)
- [ ] SSL cert auto-renewal configured (`certbot renew --dry-run` for Let's Encrypt)
- [ ] Nginx / Apache configured with security headers
- [ ] Firewall configured (SSH restricted, DB port private, no `0.0.0.0/0` on service ports)
- [ ] Server timezone set (`timedatectl` — recommend UTC)

## Environment

- [ ] `.env` populated (all `CHANGE_ME_` replaced with real values)
- [ ] `APP_ENV=production` in `.env`
- [ ] `APP_DEBUG=false` in `.env`
- [ ] `APP_URL=https://YOUR_DOMAIN` (matches actual URL exactly)
- [ ] `APP_KEY` generated via `php artisan key:generate`
- [ ] `.env` permissions are 640 owned by `www-data:www-data`
- [ ] `.env` NOT in Git (verify `git ls-files | grep -c '^.env$'` returns 0)

## Database

- [ ] Production DB created with utf8mb4
- [ ] DB user has restricted privileges only (see `PHASE_12_DATABASE_SECURITY_CHECKLIST.md`)
- [ ] `php artisan migrate:status` shows all 77 migrations as Ran
- [ ] `mysql < scripts/db-integrity-check.sql` returns all 20 counts = 0
- [ ] System seeders run (Roles, Currencies, Settings, NotificationTemplates, VendorPackages, Categories, Attributes, PaymentMethods, EnsureAdminReportsAccess)
- [ ] Backup script scheduled + first backup verified restorable to scratch DB

## Admin account

- [ ] Super-admin created via `marketplace:create-super-admin --confirm` (NOT via seeder)
- [ ] Super-admin can log in at `/admin`
- [ ] Super-admin password stored in operator password manager (never in Slack, docs, or Git)
- [ ] `admin@marketplace.test` demo user does NOT exist (verify: `SELECT COUNT(*) FROM users WHERE email='admin@marketplace.test';` returns 0)

## Storage

- [ ] `php artisan storage:link` executed (`ls -la public/storage` shows symlink)
- [ ] `storage/`, `bootstrap/cache/` owned by `www-data:www-data` with mode 775
- [ ] No files with mode 777 (verify `find /var/www/marketplace -type f -perm -002 -not -path '*/node_modules/*' | head`)
- [ ] Public storage URL renders an uploaded image: `curl -I https://YOUR_DOMAIN/storage/branding/logo.png` returns 200 if logo uploaded
- [ ] Private paths blocked: `curl -I https://YOUR_DOMAIN/storage/vendors/1/license.pdf` returns 403 or 404 (not 200)

## Queue worker

- [ ] Supervisor config installed (`/etc/supervisor/conf.d/marketplace-queue.conf`)
- [ ] `sudo supervisorctl status marketplace-queue:*` shows all RUNNING
- [ ] `php artisan queue:failed` returns empty table
- [ ] Deploy script calls `queue:restart` (verified in `scripts/deploy-production-phase12.sh`)

## Scheduler

- [ ] `sudo -u www-data crontab -l` shows `* * * * * cd /var/www/marketplace && php artisan schedule:run ...`
- [ ] `php artisan schedule:list` shows all 6 scheduled commands
- [ ] Heartbeat monitor set up (Healthchecks.io / Cronitor / Dead Man's Snitch)
- [ ] First scheduled run verified (check `vendor_intelligence_summaries.last_generated_at` moves forward)

## Email

- [ ] SMTP credentials in `.env` (`MAIL_HOST`, `MAIL_USERNAME`, `MAIL_PASSWORD` populated)
- [ ] `MAIL_FROM_ADDRESS` on same domain as `APP_URL`
- [ ] SPF record added and validating (`dig +short TXT YOUR_DOMAIN | grep spf`)
- [ ] DKIM record added and validating
- [ ] DMARC record added (start with `p=none`, escalate to `p=quarantine` after a week)
- [ ] Test email sent + received + `Authentication-Results: spf=pass, dkim=pass, dmarc=pass`
- [ ] `notification_templates` table populated (verify: `SELECT COUNT(*) FROM notification_templates WHERE is_active=1;` > 0)

## Backups

- [ ] Backup script deployed at `/usr/local/bin/marketplace-backup.sh` with mode 755
- [ ] Backup cron scheduled (03:00 daily)
- [ ] First backup file exists in `/var/backups/marketplace/`
- [ ] First backup uploaded to off-site (S3 / Wasabi / etc.)
- [ ] Backup passphrase stored in operator password manager
- [ ] Monthly restore drill scheduled

## Payment

- [ ] COD enabled: `SELECT is_enabled FROM payment_methods WHERE code='cod';` returns 1
- [ ] Online payment either FULLY tested with real gateway OR remains disabled: `SELECT is_enabled FROM payment_methods WHERE is_online=1;` returns 0 for un-tested methods
- [ ] Payment callback URL registered with gateway (matches `APP_URL/payment/callback` or similar)
- [ ] Order confirmation email received after test order

## Cleanup

- [ ] Demo users deleted: `SELECT COUNT(*) FROM users WHERE email LIKE '%@marketplace.test';` returns 0
- [ ] Demo vendor + demo products deleted (cascade from demo vendor user)
- [ ] No test-only routes exposed (`grep -n "dd\|Route::any" routes/*.php` returns 0)
- [ ] `.env` has no test SMTP credentials

## SEO / launch polish

- [ ] `robots.txt` allows crawling (not blocking `/`)
- [ ] `sitemap.xml` returns 200 and lists real product URLs
- [ ] Favicon returns 200
- [ ] Default OG image uploaded via admin site-settings
- [ ] All footer links resolve to real pages
- [ ] Site name / logo configured via admin
- [ ] All meta tags render with real data (not placeholders)

## Logging & monitoring

- [ ] `LOG_LEVEL=error` in `.env`
- [ ] `logrotate` config installed at `/etc/logrotate.d/marketplace`
- [ ] Sentry / Bugsnag / equivalent configured (optional but strongly recommended)
- [ ] Uptime monitor pinging `/up` every 60 seconds
- [ ] Disk space alert configured (80% warning, 90% critical)

## Security final checks

- [ ] `curl -I https://YOUR_DOMAIN/.env` returns 404
- [ ] `curl -I https://YOUR_DOMAIN/.git/config` returns 404
- [ ] `curl -I https://YOUR_DOMAIN/storage/logs/laravel.log` returns 404
- [ ] `curl -I https://YOUR_DOMAIN/backups/` returns 404
- [ ] HTTPS enforced (HTTP → 301 to HTTPS)
- [ ] `Strict-Transport-Security` header present
- [ ] Cookies observed as `Secure; HttpOnly` in browser DevTools
- [ ] No mixed-content warnings in browser console

## Final QA

- [ ] Full `PHASE_12_2_FINAL_QA_CHECKLIST.md` walkthrough complete
- [ ] No critical must-pass item red
- [ ] Public smoke test passed
- [ ] Customer smoke test passed
- [ ] Vendor smoke test passed
- [ ] Admin smoke test passed
- [ ] Authorization audit passed
- [ ] Mobile regression passed

## Rollback readiness

- [ ] Pre-launch backup taken and stored in TWO locations
- [ ] Rollback plan reviewed by team
- [ ] Second engineer available during launch window
- [ ] Rollback dry-run performed on staging (not required but recommended)
- [ ] Communication plan for status updates during any incident

## Sign-off

**Engineer 1**

- Name: _________________________
- Date: _________________________
- Notes: _________________________

**Engineer 2**

- Name: _________________________
- Date: _________________________
- Notes: _________________________

Both signatures required. If either engineer is uncertain about any red item above, DO NOT LAUNCH. Fix the issue, re-verify, then sign.

## After launch

- [ ] T+1 hour: `storage/logs/` quiet, `queue:failed` empty, `SHOW PROCESSLIST` no runaways
- [ ] T+24 hours: first backup ran and off-site copy verified
- [ ] T+24 hours: scheduler entries verified running (check `vendor_intelligence_summaries.last_generated_at`)
- [ ] T+7 days: DMARC report reviewed for authentication failures
- [ ] T+30 days: monthly restore drill executed

Congratulations. The marketplace is live.
