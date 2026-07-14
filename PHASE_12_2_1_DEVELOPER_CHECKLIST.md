# Phase 12.2 v12.2.1 — Developer Verification Checklist

Run each command in order. Every command is either an evidence-gathering check or a build gate. Do NOT skip.

## Setup

```bash
# Extract the archive to a clean directory
mkdir -p /tmp/v1221-verify && cd /tmp/v1221-verify
tar -xzf marketplace-phase-12-2-1-quality-gate-repair.tar.gz
cd marketplace
```

## Sanity checks (30 seconds)

### 1. VERSION file

```bash
cat VERSION
```

**Expected**: `Phase 12.2 v12.2.1 Quality Gate Repair`

- [ ] VERSION matches expected string

### 2. PHP version

```bash
php -v
```

**Expected**: PHP 8.3+ (works on 8.3, 8.4, 8.5). If lower than 8.3, upgrade before proceeding.

- [ ] PHP ≥ 8.3

## PHP quality gates (2 minutes)

### 3. Every PHP file parses

```bash
find app bootstrap config database routes -name "*.php" -type f -print0 | xargs -0 -n1 php -l 2>&1 | grep -v "No syntax errors detected"
```

**Expected**: no output (all files parse cleanly). If ANY file errors, the fix didn't take hold — do NOT proceed.

- [ ] No PHP syntax errors

### 4. Composer autoload + Laravel boot

```bash
composer dump-autoload
php artisan optimize:clear
```

**Expected**: `Cached view files cleared`, `Application cache cleared`, `Route cache cleared`, `Configuration cache cleared`, `Compiled views cleared`, `Events cache cleared`.

- [ ] `optimize:clear` succeeds

### 5. Route list boots (proves the app didn't ParseError)

```bash
php artisan route:list | head -20
```

**Expected**: table of routes. If this fails with a ParseError, the fix didn't take hold.

- [ ] Route list produces output

### 6. Migration status

```bash
php artisan migrate:status | head -20
```

**Expected**: 77 migrations listed, each with `Ran` or `Pending` status. If this errors on ParseError, the app can't boot.

- [ ] Migration status produces output

### 7. Full test suite

```bash
php artisan test
```

**Expected**: 1,556 scenarios collected. Green results across the suite. Any red should be investigated — particularly `CustomerAffinityService`-related tests (personalization suite), since that's the file I modified.

- [ ] `php artisan test` runs without ParseError
- [ ] Personalization tests pass
- [ ] Full pass/fail result recorded in this checklist

Actual result observed: __________________________________

### 8. Arabic translations audit

```bash
php artisan translations:audit ar
```

**Expected**: coverage report for `ar` locale.

- [ ] Command runs without ParseError
- [ ] Coverage report produced (record status here)

## Frontend quality gates (5 minutes)

### 9. Install dependencies

```bash
npm install
```

**Expected**: no errors, `node_modules/` populated. If you don't have a `package-lock.json` yet, `npm install` will create one; on subsequent runs, prefer `npm ci`.

- [ ] `npm install` succeeds

### 10. Run Prettier (the 51-file formatting fix)

```bash
npm run format
```

**Expected**: Prettier reformats the pre-existing drift across the 51 files. Only formatting changes (whitespace, line wrapping, quote style). NO logic changes.

Then verify:

```bash
npm run format:check
```

**Expected**: `All matched files use Prettier code style!` (or equivalent success message).

- [ ] `npm run format` runs and only formatting diffs appear
- [ ] `npm run format:check` passes

### 11. TypeScript typecheck

```bash
npm run typecheck
```

**Expected**: no errors. If TS fails on my modified files, it's a real problem — report the exact error.

- [ ] `npm run typecheck` passes with zero errors

### 12. ESLint (with `--max-warnings 0`)

```bash
npm run lint
```

**Expected**: no errors, no warnings. Specifically confirm the following rules no longer fire:

- `react/no-unescaped-entities` (was 8 errors)
- `@typescript-eslint/no-explicit-any` (was ≥ 2 warnings — I fixed 6 to be safe)
- `react-hooks/exhaustive-deps` on `slotsByDate` in `Services/Show.tsx` (was 1 warning)

- [ ] `npm run lint` passes with 0 errors, 0 warnings

### 13. Vite production build

```bash
npm run build
```

**Expected**: Vite completes without errors. `public/build/manifest.json` exists. `public/build/assets/` contains hashed JS + CSS files.

- [ ] `npm run build` completes successfully
- [ ] `public/build/manifest.json` exists

## Regression checks

### 14. Personalization end-to-end (the hot path I touched)

The PHP fix is in `app/Services/Personalization/CustomerAffinityService.php`. Verify:

```bash
php artisan personalization:rebuild --stale-days=1
```

**Expected**: completes without ParseError. Prints "Rebuilt affinity for N users" or similar.

- [ ] Personalization rebuild runs cleanly

### 15. Vendor intelligence (v11B.4.3 must be intact)

```bash
php artisan schedule:list | grep vendor-intelligence
```

**Expected**: two entries — hourly `--stale-only` regenerate and daily prune.

- [ ] Both scheduled entries present

### 16. Vendor intelligence generate

```bash
php artisan vendor-intelligence:generate --vendor=1
```

**Expected**: completes without ParseError. Prints regen status.

- [ ] Command runs cleanly

## Route authorization spot checks

### 17. Vendor `/vendor/intelligence` route inside vendor:approved

```bash
php artisan route:list | grep -i intelligence
```

**Expected**: routes visible; middleware column includes `vendor:approved`.

- [ ] Route present in the `vendor:approved` group

### 18. Site settings vendor_intelligence tab regex

```bash
php artisan route:list | grep site-settings
```

**Expected**: `POST /admin/site-settings/{group}` where `{group}` regex allows `vendor_intelligence`.

- [ ] Route present

## Production deployment sanity

### 19. Deploy script preserved + safe

```bash
head -5 scripts/deploy-production-phase12.sh
head -5 scripts/deploy.sh
```

**Expected**:
- `deploy-production-phase12.sh` starts with the Phase 12 production script banner
- `deploy.sh` starts with the `LEGACY — DO NOT USE FOR PRODUCTION` banner

- [ ] Production deploy script header intact
- [ ] Legacy script LEGACY banner intact

### 20. `.env.example.production` preserved

```bash
ls -la .env.example.production
grep "APP_ENV=production" .env.example.production
grep "APP_DEBUG=false" .env.example.production
grep -c "CHANGE_ME_" .env.example.production
```

**Expected**: file present, `APP_ENV=production` match, `APP_DEBUG=false` match, 9 `CHANGE_ME_` placeholders.

- [ ] Template intact from v12.1

## Sign-off

Once every box above is checked, the repair is verified. Record the following:

- Reviewer name: _________________________
- Date: _________________________
- Environment (staging / production-mirror): _________________________
- PHP version observed: _________________________
- Node version observed: _________________________
- Test suite result: _________________________
- Overall status: [ ] APPROVED  [ ] BLOCKED (list blockers below)

Blockers:

_____________________________________________________________
_____________________________________________________________
_____________________________________________________________

## If something fails

- **PHP parse error on ANY file**: run `php -l <file>` on the specific file, screenshot the error, and report back. Do NOT attempt to fix without confirming the file is unmodified since extract.
- **`npm run lint` fails**: run `npm run lint -- --format=stylish` for readable output. Report the first 3 error lines back.
- **`npm run format:check` still fails after `npm run format`**: unusual. Try `npx prettier --write "resources/**/*.{ts,tsx,css}"` directly.
- **`npm run typecheck` fails**: run `npx tsc --noEmit --pretty` for context. Report the first error.
- **`php artisan test` has red tests**: report which suites fail. If personalization suites specifically fail, likely a regression from my fix — I'll investigate.
