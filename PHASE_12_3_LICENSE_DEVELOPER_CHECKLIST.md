# Phase 12.3 — Developer Verification Checklist

Run these commands, in order, on a real PHP 8.3 + MySQL environment. Sign off on each row as you go.

## Pre-flight

- [ ] Extract the Phase 12.3 archive into a scratch directory (do NOT overwrite v12.2.2 yet)
- [ ] Confirm `cat VERSION` reads `Phase 12.3 License Activation`
- [ ] Confirm `.env.example.production` has the `LICENSE_*` section (lines 132+)
- [ ] Confirm your local `.env` has `LICENSE_ENFORCEMENT_ENABLED=false` for the first boot

## Composer + migration

```bash
composer install --optimize-autoloader
composer dump-autoload
php artisan optimize:clear
php artisan migrate --pretend | grep -A2 "2027_02_01_000001"
# Review the SQL. Expected: CREATE TABLE license_activations + license_audit_logs
php artisan migrate --force
```

- [ ] Migration completes without error
- [ ] `SHOW TABLES LIKE 'license_%';` returns 2 rows

## No-key baseline (enforcement disabled)

```bash
php artisan license:status
```

Expected output:

```
enforcement_enabled                     false
configured (public key installed)       no
status                                  unconfigured
warning level                           ok
```

- [ ] Enforcement is `false`
- [ ] Status is `unconfigured` (correct — no key installed)

```bash
php artisan license:fingerprint
```

Expected: prints an installation ID (UUID), an app host, and a 64-char hex fingerprint.

- [ ] Installation ID is a valid UUID
- [ ] Fingerprint is exactly 64 hex characters

## Baseline smoke test — no gating

While `LICENSE_ENFORCEMENT_ENABLED=false`, the whole app should behave exactly as v12.2.2.

- [ ] `curl -I http://localhost/` → 200
- [ ] `curl -I http://localhost/products` → 200
- [ ] Login works
- [ ] Admin panel reachable
- [ ] Vendor dashboard reachable
- [ ] Checkout works

If any of the above fails, the license middleware has a bug. STOP and report.

## Route audit

```bash
php artisan route:list | grep -i license
```

Expected: 3 routes

- `GET /license/status → license.status`
- `GET /admin/license → admin.license.index` (auth)
- `POST /admin/license/activate → admin.license.activate` (auth, throttle:10,1)

- [ ] All 3 routes present

## Test suite

```bash
php artisan test --filter=Phase12_3License
```

Expected: 20 scenarios pass. If any fail, capture the output and share it — a real environment can reveal bugs my sandbox couldn't catch.

- [ ] 20 pass, 0 fail

```bash
php artisan test
```

Expected: 1,576 scenarios total (1,556 baseline + 20 new). No REGRESSIONS in the 1,556 pre-existing scenarios.

- [ ] Full suite green

## Frontend

```bash
npm ci
npm run format
npm run format:check
npm run lint
npm run typecheck
npm run build
```

- [ ] `format` cleans up any Prettier drift
- [ ] `format:check` passes
- [ ] `lint` — 0 errors, 0 warnings (`--max-warnings 0`)
- [ ] `typecheck` — 0 errors
- [ ] `build` — successful, produces `public/build/manifest.json`

The 3 new React pages (`Admin/License/Index.tsx`, `License/Status.tsx`, `License/Blocked.tsx`) should be included in the build output.

- [ ] `public/build/manifest.json` references the 3 new pages

## No private key in archive

```bash
grep -R "PRIVATE KEY" . --exclude-dir=node_modules --exclude-dir=vendor --exclude-dir=.git
```

Expected: 0 hits (or only documentation lines that discuss the concept — those are fine).

```bash
grep -R "BEGIN RSA\|BEGIN EC\|BEGIN OPENSSH\|BEGIN PGP PRIVATE" . \
    --exclude-dir=node_modules --exclude-dir=vendor --exclude-dir=.git
```

Expected: 0 hits.

- [ ] No PEM private key markers anywhere
- [ ] No raw 32-byte base64 keys committed (the generator writes to `/secure/license-keys/`, external)

## End-to-end owner flow

Now simulate the full owner activation flow:

### Step 1 — Generate a keypair

```bash
mkdir -p /tmp/dev-license-keys
chmod 700 /tmp/dev-license-keys
php tools/license-generator/generate.php --generate-keypair --out /tmp/dev-license-keys
```

- [ ] Generator prints the public key
- [ ] `/tmp/dev-license-keys/private.b64` has mode 600
- [ ] `/tmp/dev-license-keys/public.b64` has mode 644

### Step 2 — Install the public key

Copy the public key value into `.env`:

```env
LICENSE_PUBLIC_KEY=<paste the base64 string>
LICENSE_ENFORCEMENT_ENABLED=true
```

Then:

```bash
php artisan config:cache
php artisan license:clear-cache
php artisan license:status
```

- [ ] `configured` is now `yes`
- [ ] `status` is `unlicensed` (correct — key installed but no activation yet)

### Step 3 — Test that admin gets redirected to /admin/license

```bash
# Log in as super_admin, then:
curl -sI -c /tmp/cookies -b /tmp/cookies http://localhost/admin | head -3
```

Expected: 302 redirect to `/admin/license`.

- [ ] Super admin is redirected to activation page

### Step 4 — Sign a token

```bash
php tools/license-generator/generate.php \
    --private-key /tmp/dev-license-keys/private.b64 \
    --holder "Dev Test" \
    --domain localhost \
    --days 60 \
    --type owner
```

Copy the printed token.

- [ ] Token is a 3-part dot-separated base64url string

### Step 5 — Activate

Either paste the token into `/admin/license` in the browser, OR:

```bash
php artisan license:activate "<paste-token-here>"
```

Expected: "License activated successfully."

- [ ] Activation succeeds
- [ ] `php artisan license:status` now shows `status: active`, `expires_at` 60 days from now
- [ ] `SELECT COUNT(*) FROM license_activations WHERE status='active'` returns 1
- [ ] `SELECT event FROM license_audit_logs ORDER BY created_at DESC LIMIT 1` returns `activation.success`

### Step 6 — Test that admin panel now works

```bash
curl -sI -c /tmp/cookies -b /tmp/cookies http://localhost/admin | head -3
```

Expected: 200 (not the redirect anymore).

- [ ] Admin panel is reachable
- [ ] Vendor dashboard reachable
- [ ] Checkout reachable

### Step 7 — Test expiry behavior (optional but recommended)

Sign a token that expires in the past:

```bash
# Modify generate.php temporarily OR sign with --days 1 and wait 24h.
# Alternative: manipulate the license_activations row directly:
UPDATE license_activations SET expires_at = NOW() - INTERVAL 1 DAY WHERE status = 'active';
```

Then:

```bash
php artisan license:clear-cache
php artisan license:status  # → status: expired
curl -sI http://localhost/orders  # (as authed customer) → 403
curl -sI http://localhost/  # (public) → 200 (storefront stays visible)
```

- [ ] Expired state blocks `/orders` but not `/`
- [ ] Data was NOT deleted (verify: `SELECT COUNT(*) FROM orders`)

### Step 8 — Cleanup

```bash
rm -rf /tmp/dev-license-keys  # never leave the test private key on the box
UPDATE license_activations SET status = 'revoked';  # reset for next test
```

## Sign-off

**Verified by**

- Name: _________________________
- Date: _________________________
- All rows above ticked: [ ]

If any row fails, do NOT deploy to production. Report which row failed + full error output.
