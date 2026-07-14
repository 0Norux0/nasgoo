# Phase 12.3 v12.3.2 — Developer Verification Checklist

Run each command in order. Sign each row.

## Extract + sanity

```bash
mkdir -p /tmp/v1232-verify && cd /tmp/v1232-verify
tar -xzf marketplace-phase-12-3-2-license-test-env-format-repair.tar.gz
cd marketplace
```

### 1. VERSION

```bash
cat VERSION
```

**Expected**: `Phase 12.3 v12.3.2 License Test Environment and Format Repair`

- [ ] Matches

### 2. `license:doctor` command present

```bash
ls -la app/Console/Commands/LicenseDoctorCommand.php
```

**Expected**: file exists, ~9 KB.

- [ ] Present

### 3. `.env.testing.example` present + SQLite default

```bash
ls -la .env.testing.example
grep -E "^DB_CONNECTION|^DB_DATABASE" .env.testing.example
```

**Expected**: file present; the ACTIVE (uncommented) block is `DB_CONNECTION=sqlite` + `DB_DATABASE=:memory:`.

- [ ] Present, SQLite active by default

### 4. Rewritten License pages present

```bash
wc -l resources/js/Pages/Admin/License/Index.tsx \
       resources/js/Pages/License/Status.tsx \
       resources/js/Pages/License/Blocked.tsx
```

**Expected**: all three exist with reasonable line counts (283 / 43 / 28 respectively).

- [ ] All 3 present

### 5. Test environment guide present

```bash
ls -la PHASE_12_3_2_LICENSE_TEST_ENVIRONMENT_GUIDE.md
```

**Expected**: present, ~7–9 KB.

- [ ] Present

## Environment diagnosis (the point of this phase)

### 6. PHP + extensions inventory

```bash
php --version
php -m | grep -E "sodium|pdo_pgsql|pdo_mysql|pdo_sqlite|pgsql|mysql"
```

Record what you see: __________________________

### 7. Run the new preflight diagnostic

```bash
php artisan license:doctor
```

**Expected**: a table of 10 rows, each labeled OK / WARN / FAIL. Any FAIL prints the exact install command. The developer's v12.3.1 environment would have shown:

```
[ FAIL ] ext-sodium              → prints sodium install command
[ FAIL ] Database driver         → prints pdo_pgsql install command + lists loaded drivers
```

- [ ] Doctor runs
- [ ] All FAIL rows resolved (or documented for follow-up)

### 8. JSON mode for automation

```bash
php artisan license:doctor --json
```

**Expected**: valid JSON with `summary` + `checks` fields.

- [ ] JSON output valid

## Install missing extensions

### 9. Install ext-sodium

**Ubuntu / Debian:**

```bash
sudo apt-get install php8.3-sodium          # adjust PHP version
sudo service php8.3-fpm restart
php -m | grep sodium
```

**Windows:** uncomment `extension=sodium` in `php.ini`, restart PHP.

- [ ] sodium loaded after install

### 10. Install a PDO driver you don't have (optional)

If you want to use the CI target (pgsql):

```bash
sudo apt-get install php8.3-pgsql
sudo service php8.3-fpm restart
php -m | grep pdo_pgsql
```

Otherwise skip — SQLite Option C already works with your existing pdo_sqlite.

- [ ] Either pdo_pgsql installed OR SQLite Option C chosen

### 11. Re-run the doctor

```bash
php artisan license:doctor
```

**Expected**: all rows OK (or non-blocking WARN).

- [ ] All FAILs resolved

## Backend

### 12. PHP files parse

```bash
find app bootstrap config database routes -name "*.php" -print0 | xargs -0 -n1 php -l 2>&1 | grep -v "No syntax errors"
```

**Expected**: no output.

- [ ] All PHP files parse

### 13. Set up `.env.testing` + test env

```bash
cp .env.testing.example .env.testing
# Default is Option C (SQLite in-memory) — uses your existing pdo_sqlite
# If you installed pdo_pgsql and prefer PostgreSQL, comment Option C and uncomment Option A
```

- [ ] `.env.testing` prepared

### 14. Composer + optimize

```bash
composer dump-autoload -o
php artisan optimize:clear
php artisan config:cache
```

- [ ] Succeeds

### 15. Routes + license commands

```bash
php artisan route:list | grep -i license
```

**Expected**: 3 routes.

```bash
php artisan license:status
php artisan license:fingerprint
php artisan license:doctor
```

**Expected**: all print output.

- [ ] All commands run

### 16. License test suite (34 scenarios from v12.3.1)

```bash
php artisan test --filter=Phase12_3License
```

**Expected**: 34 pass — provided sodium + a PDO driver are now available.

- [ ] 34 / 34 pass

If tests fail with "could not find driver": `license:doctor` will name the specific missing extension.

If tests fail with "Call to undefined function sodium_...": install ext-sodium and re-run.

### 17. Full test suite

```bash
php artisan test
```

**Expected**: 1,590 total (unchanged from v12.3.1 — no new scenarios in v12.3.2).

- [ ] Full green

## Frontend

### 18. Install + format + lint + typecheck + build

```bash
npm install
npm run format
npm run format:check
npm run lint
npm run typecheck
npm run build
```

**Expected**: every command succeeds.

If `format:check` still reports issues:

```bash
npm run format:check 2>&1 | head -60
# Share any residual failing files. My 3 License page rewrites should
# either pass on first check OR need only trivial Tailwind class re-sorting
# which `npm run format` resolves automatically.
```

- [ ] All 5 frontend commands green

### 19. Focused check on the 3 rewritten License files

```bash
npm run lint -- resources/js/Pages/Admin/License/Index.tsx
npm run typecheck 2>&1 | grep "Admin/License/Index" | head
npm run format:check 2>&1 | grep -E "License/(Index|Status|Blocked)\.tsx"
```

**Expected**: clean output. If `format:check` still flags any of these 3 files, it's Tailwind class ordering — run `npm run format` and re-check.

- [ ] All 3 License files clean

## Security audit

### 20. No private key material

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

### 21. v12.3.1 security fixes intact

```bash
grep -c "FINGERPRINT_REQUIRED" app/Services/Licensing/LicenseVerifier.php
grep -c "hash_equals" app/Services/Licensing/LicenseVerifier.php
ls -la app/Services/Licensing/LicenseDomainResolver.php
grep -c "expectedDomain()" app/Services/Licensing/LicenseManager.php
```

**Expected**: `2`, `≥ 3`, file exists, `≥ 1`.

- [ ] All v12.3.1 security bits intact

### 22. v12.3.1 doc corrections intact

```bash
grep -R "never writes.*private\|never stores.*private" \
    LICENSE_PRIVATE_KEY_OWNER_INSTRUCTIONS.md \
    PHASE_12_3_LICENSE_OWNER_GUIDE.md \
    tools/license-generator/README.md
```

**Expected**: no output (old wording still absent).

```bash
grep -R "MAY write\|keypair-generation mode" \
    LICENSE_PRIVATE_KEY_OWNER_INSTRUCTIONS.md \
    PHASE_12_3_LICENSE_OWNER_GUIDE.md \
    tools/license-generator/README.md
```

**Expected**: at least one match per file (new wording still present).

- [ ] Both preserved

### 23. v12.2.3 SiteSettings JsonValue intact

```bash
grep -c "type JsonValue " resources/js/Pages/Admin/SiteSettings/Index.tsx
grep -c "useForm<SiteSettingsFormData>" resources/js/Pages/Admin/SiteSettings/Index.tsx
```

**Expected**: `1` each.

- [ ] Present

### 24. v12.2.1 parse fix intact

```bash
grep -c "match (\$dim)" app/Services/Personalization/CustomerAffinityService.php
```

**Expected**: `0`.

- [ ] No regression

### 25. v12.1 deploy safety intact

```bash
grep -c LEGACY scripts/deploy.sh
ls -la scripts/deploy-production-phase12.sh | awk '{print $1}'
```

**Expected**: `≥ 1`; mode `-rwxr-xr-x`.

- [ ] Intact

### 26. v11B.4.3 vendor intelligence intact

```bash
[ -f app/Mail/VendorIntelligenceDigestMail.php ] && echo ok
[ -f app/Jobs/SendVendorIntelligenceDigest.php ] && echo ok
```

- [ ] Both ok

## Sign-off

- Reviewer name: _________________________
- Date: _________________________
- Environment: _________________________
- PHP version: _________________________
- Extensions after fixes: _________________________
- Test DB driver used: __________________
- Node version: _________________________
- Test suite result: _______ / 1,590
- License subset: _______ / 34
- `format:check` result: [ ] PASS  [ ] FAIL (list residual files: __________)
- Overall: [ ] APPROVED  [ ] BLOCKED

## If something fails

- **`license:doctor` shows FAIL for sodium**: follow the printed hint. Sodium is bundled with PHP 8.3+ on most platforms — if missing, install `php{version}-sodium`.
- **`license:doctor` shows FAIL for DB driver**: either install the driver (`php{version}-pgsql`) OR use SQLite Option C in `.env.testing`.
- **Tests still fail after doctor OK**: capture full output; share the exact scenario name and error.
- **`npm run format:check` STILL fails after `npm run format`**: share the failing file list. If License/Index.tsx / Status.tsx / Blocked.tsx are still listed, capture the specific diff — my rewrite may have missed a Tailwind class ordering nuance the plugin insists on.
- **`license:doctor` doesn't exist** (`Command "license:doctor" is not defined`): run `composer dump-autoload -o && php artisan optimize:clear` to reset command discovery cache.
