# Phase 12.2 — Environment Readiness

Verifies the shipped `.env.example.production` against directive §4 requirements. Actual `.env` deployment is the operator's responsibility.

## Required production values — audit against `.env.example.production`

| Setting | Directive expects | Actually in `.env.example.production` | ✅/⏳ |
| --- | --- | --- | :---: |
| `APP_ENV=production` | Yes | Present at line 15 | ✅ |
| `APP_DEBUG=false` | Yes | Present at line 17 | ✅ |
| `APP_URL=https://real-domain.com` | Yes | `APP_URL=https://your-domain.com` (placeholder) | ✅ |
| `SESSION_SECURE_COOKIE=true` | Yes | Present with warning comment about HTTPS requirement | ✅ |
| `DB_CONNECTION=mysql` | Yes | Present | ✅ |
| `CACHE_STORE=redis` | Yes | Present | ✅ |
| `QUEUE_CONNECTION=redis` | Yes | Present | ✅ |
| `SESSION_DRIVER=database` | Yes | Present | ✅ |
| `MAIL_MAILER=smtp` | Yes | Present | ✅ |
| `FILESYSTEM_DISK=public` | Yes | Present; S3 alternative documented | ✅ |
| `APP_KEY=` empty (to be generated) | Yes | Present with instruction `php artisan key:generate` | ✅ |
| `SESSION_HTTP_ONLY=true` | Implied by "secure cookies" | Present | ✅ |
| `SESSION_SAME_SITE=lax` | Not required but good | Present | ✅ |
| `LOG_LEVEL=error` | Not required but good | Present | ✅ |

Verify yourself:

```bash
grep -E '^(APP_ENV|APP_DEBUG|APP_URL|SESSION_SECURE_COOKIE|DB_CONNECTION|CACHE_STORE|QUEUE_CONNECTION|SESSION_DRIVER|MAIL_MAILER|FILESYSTEM_DISK|LOG_LEVEL)=' .env.example.production
```

## Confirm — no real secrets committed

`grep -c "CHANGE_ME_" .env.example.production` returned **9**. Every credential slot uses a placeholder token, not a real value. Slots include:

- `DB_PASSWORD=CHANGE_ME_STRONG_PASSWORD`
- `REDIS_PASSWORD=CHANGE_ME_REDIS_PASSWORD`
- `MAIL_PASSWORD=CHANGE_ME_SMTP_PASSWORD`
- `MEILISEARCH_KEY=CHANGE_ME_MEILISEARCH_MASTER_KEY`
- `PAYMENT_KNET_MERCHANT_ID=CHANGE_ME_KNET_MERCHANT_ID`
- `PAYMENT_KNET_SECRET=CHANGE_ME_KNET_SECRET`
- (plus S3 credentials — commented out but with CHANGE_ME_ placeholders)

Any of these left as `CHANGE_ME_` at deployment time is a bug.

## Confirm — no weak password examples

The developer-focused `.env.example` (retained from Phase 0) has development-only values. It is NOT presented as production-ready. The setup guide (PHASE_12_DATABASE_SETUP_GUIDE.md) explicitly instructs:

```bash
cp .env.example.production .env    # ← NOT .env.example
```

## Confirm — no test credentials remain

Grep for test emails in `.env.example.production`:

```bash
$ grep -E '@marketplace\.test|password' .env.example.production
```

Result: 0 matches. The `admin@marketplace.test / password` demo credential from `DatabaseSeeder` is NOT referenced in the production env template. Section 6 of `PHASE_12_DATABASE_READINESS_REPORT.md` documents how to remove those seeded users if `DatabaseSeeder` was accidentally run on production.

## Confirm — production `.env` never overwritten by deployment

Audit of `scripts/deploy-production-phase12.sh`:

```bash
$ grep -n "> \.env\|>> \.env\|cp .env" scripts/deploy-production-phase12.sh
# Result: 0 write operations to .env
```

The script only reads `.env` via grep (lines 108, 190-198). No `>` or `>>` redirection to `.env` anywhere. Operator's real `.env` cannot be clobbered by the deploy script.

The old legacy `scripts/deploy.sh` also does not write `.env` (verified via grep).

## APP_KEY handling — safety notes

The `APP_KEY` in `.env.example.production` is intentionally blank. Operators must generate one on the target server:

```bash
php artisan key:generate
```

**Do not** copy an `APP_KEY` from staging to production. Do not reuse across environments. Do not commit an `APP_KEY` to Git.

**Rotation warning**: this codebase's `SupplierIntegration.credentials` column uses Eloquent's `encrypted:array` cast. If you rotate `APP_KEY` in place, all existing encrypted values become undecryptable. A rotation requires:

1. Decrypt all encrypted values with the current key (in a migration or artisan command)
2. Change `APP_KEY`
3. Re-encrypt those values with the new key
4. Commit the migration to the deploy record

Non-trivial. Prefer to set `APP_KEY` once at initial deploy and keep it forever.

## Secure cookie prerequisites

`SESSION_SECURE_COOKIE=true` only works over HTTPS. If your APP_URL is `https://` but the app is behind a load balancer terminating TLS, the app must be configured to trust the proxy — see `bootstrap/app.php` for `TrustProxies` middleware settings.

Verify HTTPS + secure cookies are working end-to-end AFTER deployment:

```bash
curl -Ik https://YOUR_DOMAIN/ | grep -Ei 'set-cookie|strict-transport'
```

Expected: `Set-Cookie: ... Secure; HttpOnly; SameSite=Lax` and a `Strict-Transport-Security` header (if configured at the reverse proxy — not automatic).

## Domain / URL replacements the operator must make

Search for occurrences of the placeholder domain and replace before deploy:

```bash
$ grep -R "your-domain.com\|your-cdn-domain" .env.example.production
```

Any `your-domain.com` reference in the operator's actual `.env` is a bug.

## Evidence status

| Claim | Status | Evidence |
| --- | :---: | --- |
| `.env.example.production` has `APP_ENV=production` | ✅ | `grep "APP_ENV=production" .env.example.production` |
| `.env.example.production` has `APP_DEBUG=false` | ✅ | Same |
| `.env.example.production` has `SESSION_SECURE_COOKIE=true` | ✅ | Same |
| No real secrets in template (9 CHANGE_ME_ slots) | ✅ | `grep -c "CHANGE_ME_" .env.example.production` returns 9 |
| No test credentials in template | ✅ | `grep '@marketplace.test\|password' .env.example.production` returns 0 |
| Deploy script does not write `.env` | ✅ | `grep -n "> .env\|>> .env\|cp .env" scripts/deploy-production-phase12.sh` returns 0 |
| Operator's real `.env` populated | ⏳ | Developer must copy `.env.example.production` to `.env` and edit |
| Operator generated `APP_KEY` | ⏳ | Developer must run `php artisan key:generate` |
| HTTPS actually served | ⏳ | Operator configures TLS at reverse proxy or Cloudflare |
| Cookies observed as Secure/HttpOnly at runtime | ⏳ | Operator runs the `curl -Ik` check after deploy |
