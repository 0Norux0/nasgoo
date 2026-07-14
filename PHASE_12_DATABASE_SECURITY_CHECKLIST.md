# Phase 12 — Database Security Checklist

Every item below must be signed BEFORE production launch and rechecked on every material change.

## Access control

- [ ] Database user has ONLY: SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES, CREATE TEMPORARY TABLES, EXECUTE, TRIGGER, CREATE VIEW, SHOW VIEW
- [ ] Database user does NOT have: SUPER, FILE, CREATE USER, GRANT OPTION, RELOAD, REPLICATION SLAVE
- [ ] Verified: `SHOW GRANTS FOR 'DB_USERNAME'@'HOST';` output matches allowlist
- [ ] Root password differs from application user password
- [ ] No anonymous users (`SELECT user, host FROM mysql.user WHERE user = '';` returns 0 rows)
- [ ] No users with blank passwords (`SELECT user FROM mysql.user WHERE authentication_string = '';` returns 0 rows)

## Password strength

- [ ] Application DB password ≥ 20 chars random, generated via `openssl rand -base64 24`
- [ ] Root DB password ≥ 24 chars random
- [ ] Backup GPG passphrase ≥ 32 chars random, stored in operator password manager only
- [ ] APP_KEY set (`base64:` prefix, 32 bytes random) and stored securely
- [ ] Super-admin passwords enforced by `marketplace:create-super-admin` (12+ chars, mixed classes)

## Network access

- [ ] MySQL `bind-address` restricted to loopback (127.0.0.1) OR private subnet only
- [ ] `bind-address = 0.0.0.0` is NEVER used in production
- [ ] Firewall/security-group: port 3306 accessible only from app server IPs
- [ ] SSL/TLS between app and DB when they run on different hosts (`REQUIRE SSL` on the user)
- [ ] Backup pipeline uses encrypted transport (SFTP, S3 with TLS, rclone over HTTPS)

## Secret hygiene

- [ ] `.env` is in `.gitignore` (verify: `git ls-files | grep -c '^.env$'` returns 0)
- [ ] `git log --all -- .env` returns empty (no history of committed .env)
- [ ] `.env` on production has permissions 0640, owned by app user, readable by webserver group
- [ ] APP_KEY rotation plan documented (`SupplierIntegration.credentials` is `encrypted:array` — rotation requires re-encrypt migration)

## Web exposure

- [ ] Verify: `curl -I https://YOUR_DOMAIN/.env` returns 404 (nginx/apache blocks dotfiles)
- [ ] Verify: `curl -I https://YOUR_DOMAIN/.git/config` returns 404
- [ ] Verify: `curl -I https://YOUR_DOMAIN/storage/backups/` returns 404 (backups NOT in web-accessible path)
- [ ] Laravel `storage/logs/laravel.log` is NOT world-readable (0640, owned by app user)

## Backup security

- [ ] Backups encrypted at rest (GPG AES256 or LUKS/EBS)
- [ ] Backups uploaded off-server (different provider from primary DB)
- [ ] Backup passphrase NOT stored on backup server
- [ ] Off-site bucket has versioning + object lock (or equivalent write-once)
- [ ] IAM keys for backup upload have write-only permission (cannot read/delete existing backups)

## Data at rest

- [ ] Filesystem encryption on DB volume (RDS default, EBS-encrypted, or LUKS on self-hosted)
- [ ] `mysql.user` table encrypted (part of the DB volume)
- [ ] TLS certificates for HTTPS renewed automatically (Let's Encrypt + certbot)

## Audit trail

- [ ] `audit_logs` table is being written (verify via `SELECT COUNT(*), MAX(created_at) FROM audit_logs;`)
- [ ] Access to `audit_logs` restricted (super_admin + admin_staff only through app UI, no direct DB read for other roles)
- [ ] Backup of `audit_logs` follows same retention as customer data (regulatory requirement in many jurisdictions)

## Regular tasks

- [ ] Weekly: review `SHOW GRANTS` on all DB users
- [ ] Weekly: review `mysql.user` for new users
- [ ] Monthly: rotate backup passphrase (only after successful restore drill)
- [ ] Monthly: restore drill (see `PHASE_12_DATABASE_BACKUP_PLAN.md`)
- [ ] Quarterly: penetration test focused on SQL injection + authentication paths
- [ ] Yearly: rotate application DB password + root password

## Incident response

- [ ] Runbook exists for suspected DB compromise: (1) rotate credentials, (2) restore from pre-compromise backup, (3) forensic review of `audit_logs` + slow query log
- [ ] Runbook exists for accidental data loss: (1) STOP writes (maintenance mode), (2) identify last-good backup, (3) restore to scratch DB, (4) manually reconcile, (5) restore to prod after approval

## Sign-off

Reviewer name: _________________________
Date: _________________________
Notes: _________________________
