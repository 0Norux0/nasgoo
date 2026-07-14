# Phase 12.3 v12.3.1 — Developer Verification Checklist

Run each command in order. Sign each row.

## Extract + sanity

```bash
mkdir -p /tmp/v1231-verify && cd /tmp/v1231-verify
tar -xzf marketplace-phase-12-3-1-license-repair.tar.gz
cd marketplace
```

### 1. VERSION

```bash
cat VERSION
```

**Expected**: `Phase 12.3 v12.3.1 License Repair`

- [ ] Matches

### 2. Fingerprint bypass fix present

```bash
grep -c "FINGERPRINT_REQUIRED" app/Services/Licensing/LicenseVerifier.php
grep -c "hash_equals" app/Services/Licensing/LicenseVerifier.php
```

**Expected**: `2` (constant declaration + rejection return) and `≥ 3` (fingerprint + domain-helper uses)

- [ ] Fingerprint bypass fix in place

### 3. Domain resolver in place

```bash
ls -la app/Services/Licensing/LicenseDomainResolver.php
grep "expectedDomain()" app/Services/Licensing/LicenseManager.php
```

**Expected**: file exists; manager calls `$this->domainResolver->expectedDomain()`

- [ ] Resolver present + wired

### 4. New config keys

```bash
grep -E "LICENSE_DOMAIN|LICENSE_ALLOW_WWW_ALIAS" .env.example.production
```

**Expected**: both present.

- [ ] Both new env keys in production template

### 5. Dead sodium call removed

```bash
grep -n "sk_to_curve25519" tools/license-generator/generate.php
```

**Expected**: 1 match, and it starts with `//` (comment only).

Confirm no LIVE call:

```bash
grep -E "^\s*\\\$\w+\s*=\s*sodium_crypto_sign_ed25519_sk_to_curve25519" tools/license-generator/generate.php
```

**Expected**: no output.

- [ ] Dead call gone; comment remains

### 6. Doc contradictions fixed

```bash
grep -R "never writes.*private\|never stores.*private" \
    LICENSE_PRIVATE_KEY_OWNER_INSTRUCTIONS.md \
    PHASE_12_3_LICENSE_OWNER_GUIDE.md \
    tools/license-generator/README.md
```

**Expected**: no output.

```bash
grep -R "MAY write\|keypair-generation mode" \
    LICENSE_PRIVATE_KEY_OWNER_INSTRUCTIONS.md \
    PHASE_12_3_LICENSE_OWNER_GUIDE.md \
    tools/license-generator/README.md
```

**Expected**: multiple matches (new honest wording).

- [ ] Contradictions replaced

## Test environment

### 7. Check DB driver

```bash
php -v
php -m | grep -E "pdo_pgsql|pgsql|pdo_mysql|pdo_sqlite"
```

Record which driver(s) are loaded: __________________________

### 8. Set up `.env.testing`

If pdo_pgsql available:

```bash
cp .env.testing.example .env.testing
# Uncomment "Option A — PostgreSQL" block
```

If only mysql available:

```bash
cp .env.testing.example .env.testing
# Uncomment "Option B — MySQL" block, comment "Option C" (default)
```

If nothing available OR fastest local option:

```bash
cp .env.testing.example .env.testing
# Option C (SQLite) is enabled by default in the example — install php-sqlite3 if needed
sudo apt-get install php8.3-sqlite3   # adjust PHP version to your setup
```

- [ ] `.env.testing` configured with a working driver

## Backend

### 9. PHP files parse

```bash
find app bootstrap config database routes -name "*.php" -print0 | xargs -0 -n1 php -l 2>&1 | grep -v "No syntax errors"
```

**Expected**: no output.

- [ ] All PHP files parse

### 10. Composer + optimize

```bash
composer dump-autoload -o
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

**Expected**: all succeed.

- [ ] Optimize sequence green

### 11. Routes + license commands

```bash
php artisan route:list | grep -i license
```

**Expected**: 3 routes — `license.status`, `admin.license.index`, `admin.license.activate`.

```bash
php artisan license:status
php artisan license:fingerprint
```

**Expected**: both print output cleanly.

- [ ] All 3 routes present
- [ ] Both commands run

### 12. License test suite (34 scenarios)

```bash
php artisan test --filter=Phase12_3License
```

**Expected**: 34 pass (20 baseline + 14 new v12.3.1 security scenarios).

Specifically confirm these new SECURITY scenarios pass:

- `§12.3.1.f3 required fingerprint + missing token fingerprint = REJECTED`
- `§12.3.1.f4 required fingerprint + empty string = REJECTED`
- `§12.3.1.f5 required fingerprint + whitespace only = REJECTED`
- `§12.3.1.d3 LicenseDomainResolver uses request()->getHost() in web context`
- `§12.3.1.d4 resolver falls back to LICENSE_DOMAIN in CLI context`
- `§12.3.1.d8 required domain + missing domain claim = REJECTED`

- [ ] 34 / 34 pass
- [ ] All 6 SECURITY scenarios above pass

### 13. Full suite

```bash
php artisan test
```

**Expected**: 1,590 total (1,556 baseline + 20 v12.3 + 14 v12.3.1).

- [ ] Full green

## Frontend

### 14. Install + format + lint + typecheck + build

```bash
npm install
npm run format
npm run format:check
npm run lint
npm run typecheck
npm run build
```

**Expected**: every command succeeds. Zero ESLint warnings.

- [ ] All 5 commands green

If `format:check` still fails after `format`:

```bash
npm run format:check 2>&1 | head -30
# Share the exact failing files if any
```

## Generator sanity

### 15. Keypair mode works on any libsodium build

```bash
mkdir -p /tmp/dev-keys && chmod 700 /tmp/dev-keys
php tools/license-generator/generate.php --generate-keypair --out /tmp/dev-keys
```

**Expected**: prints public key + creates `/tmp/dev-keys/private.b64` (mode 600) + `/tmp/dev-keys/public.b64` (mode 644). **NO** fatal error from missing sodium helper.

- [ ] Keypair generated
- [ ] Modes correct

### 16. Sign + verify round-trip

```bash
TOKEN=$(php tools/license-generator/generate.php \
    --private-key /tmp/dev-keys/private.b64 \
    --holder "Dev Test" \
    --domain localhost \
    --days 60 --type owner)

# Set the public key in .env, migrate, and activate
PUBKEY=$(cat /tmp/dev-keys/public.b64)
sed -i.bak "s|^LICENSE_PUBLIC_KEY=.*|LICENSE_PUBLIC_KEY=$PUBKEY|" .env
sed -i "s|^LICENSE_ENFORCEMENT_ENABLED=.*|LICENSE_ENFORCEMENT_ENABLED=true|" .env
php artisan config:cache
php artisan migrate --force
php artisan license:clear-cache
php artisan license:activate "$TOKEN"
php artisan license:status
```

**Expected**: `license:activate` → "License activated successfully"; `license:status` → `status: active`.

Cleanup:

```bash
rm -rf /tmp/dev-keys
mv .env.bak .env
php artisan config:cache
```

- [ ] End-to-end activation succeeds
- [ ] Test cleaned up

### 17. Fingerprint bypass CANNOT happen anymore

Sign a token WITHOUT a fingerprint claim:

```bash
TOKEN_NO_FP=$(php tools/license-generator/generate.php \
    --private-key /tmp/dev-keys/private.b64 \
    --holder "Test" --domain localhost --days 60 --type owner)
# The generator doesn't include a fingerprint by default (--fingerprint not passed)

# Enable fingerprint binding
sed -i "s|^LICENSE_REQUIRE_FINGERPRINT_MATCH=.*|LICENSE_REQUIRE_FINGERPRINT_MATCH=true|" .env
php artisan config:cache
php artisan license:clear-cache

# Try to activate — SHOULD FAIL
php artisan license:activate "$TOKEN_NO_FP"
```

**Expected**: activation FAILS with reason "fingerprint binding is enabled but token has no server_fingerprint claim".

- [ ] Fingerprintless token correctly rejected

## Security audit

### 18. No private key material anywhere

```bash
grep -R "BEGIN PRIVATE KEY\|BEGIN RSA PRIVATE KEY\|BEGIN EC PRIVATE KEY\|BEGIN OPENSSH PRIVATE KEY" . \
    --exclude-dir=node_modules --exclude-dir=vendor --exclude-dir=.git
```

**Expected**: zero real matches (only the grep-command block inside this checklist file).

```bash
grep -rE '"[A-Za-z0-9+/]{43}="' . --include="*.php" --include="*.env*" --include="*.json"
```

**Expected**: no output.

- [ ] Zero private key material

## Preservation checks

### 19. v12.2.3 SiteSettings JsonValue intact

```bash
grep -c "type JsonValue " resources/js/Pages/Admin/SiteSettings/Index.tsx
grep -c "useForm<SiteSettingsFormData>" resources/js/Pages/Admin/SiteSettings/Index.tsx
```

**Expected**: `1` each.

- [ ] Present

### 20. v12.2.1 parse fix intact

```bash
grep -c "match (\$dim)" app/Services/Personalization/CustomerAffinityService.php
```

**Expected**: `0`.

- [ ] No regression

### 21. v12.1 deploy safety intact

```bash
grep -c LEGACY scripts/deploy.sh
ls -la scripts/deploy-production-phase12.sh | awk '{print $1}'
```

**Expected**: LEGACY count ≥ 1; production script has mode `-rwxr-xr-x`.

- [ ] Both intact

### 22. Vendor intelligence intact

```bash
[ -f app/Mail/VendorIntelligenceDigestMail.php ] && echo ok
[ -f app/Jobs/SendVendorIntelligenceDigest.php ] && echo ok
```

- [ ] Both `ok`

## Trusted-proxy warning (production deployments only)

The v12.3.1 domain fix uses `request()->getHost()` which respects Laravel's `TrustProxies` middleware. If your production sits behind a load balancer:

1. Verify `app/Http/Middleware/TrustProxies.php` OR `config/trustedproxy.php` lists your LB's IPs (or `*` if same-network)
2. Verify `X-Forwarded-Host` is forwarded by your LB
3. Test that `curl -H "Host: your-real-domain.com" ...` reaches Laravel and `request()->getHost()` returns your real domain, not the LB's address

Without proper trusted-proxy config, domain-matching may reject valid tokens because Laravel sees the LB's host instead of the real one.

- [ ] Trusted-proxy config verified (production only)

## Sign-off

- Reviewer name: _________________________
- Date: _________________________
- Environment: _________________________
- PHP version: _________________________
- Test DB driver used: __________________
- Node version: _________________________
- Test suite result: _______ / 1,590
- License subset: _______ / 34
- Overall: [ ] APPROVED  [ ] BLOCKED

## If something fails

- **Test env driver still missing**: verify `.env.testing` matches your installed driver; run `php artisan config:clear && php artisan test` again.
- **`§12.3.1.f3–f5` still ACCEPT missing/empty/whitespace fingerprints**: means my fix didn't take hold. Grep for `FINGERPRINT_REQUIRED` — should return 2. If 0, the fix wasn't applied.
- **`§12.3.1.d3` fails ("resolver uses request()->getHost() in web context")**: verify `LicenseDomainResolver.php` exists AND `LicenseManager` constructor takes it. Then run `composer dump-autoload -o` to reset autoload cache.
- **Generator fatal on keypair**: capture full output; verify PHP ext-sodium loaded (`php -m | grep sodium`).
- **`npm run format:check` still fails after `format`**: share the failing file list; this is the same limitation documented since v12.2.2 and can ONLY be resolved by running Prettier locally.
