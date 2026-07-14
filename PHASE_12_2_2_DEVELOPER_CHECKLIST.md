# Phase 12.2 v12.2.2 — Developer Verification Checklist

Run each command in order. Do NOT skip.

## Setup

```bash
mkdir -p /tmp/v1222-verify && cd /tmp/v1222-verify
tar -xzf marketplace-phase-12-2-2-final-lint-format-repair.tar.gz
cd marketplace
```

## Sanity

### 1. VERSION

```bash
cat VERSION
```

**Expected**: `Phase 12.2 v12.2.2 Final Lint and Format Repair`

- [ ] VERSION matches

## Backend smoke checks (2 min)

### 2. PHP files parse (regression check for v12.2.1 fix)

```bash
find app bootstrap config database routes -name "*.php" -print0 | xargs -0 -n1 php -l 2>&1 | grep -v "No syntax errors"
```

**Expected**: no output. The v12.2.1 `CustomerAffinityService.php` fix must remain intact.

- [ ] No PHP syntax errors

### 3. Composer + Laravel boot

```bash
composer dump-autoload
php artisan optimize:clear
php artisan route:list | head -20
php artisan migrate:status | head -20
```

**Expected**: all commands succeed.

- [ ] `optimize:clear` succeeds
- [ ] `route:list` produces output (no ParseError)
- [ ] `migrate:status` produces output

## Frontend quality gates (5 min)

### 4. npm install

```bash
npm install
```

**Expected**: no errors; `node_modules/` populated.

- [ ] `npm install` succeeds

### 5. Prettier — REQUIRED STEP (this is what fixes the 50 files)

```bash
npm run format
```

**Expected**: Prettier reformats ~50 files. Only formatting diffs — line reflow, JSX attribute wrapping, Tailwind class ordering. No logic changes.

Then verify:

```bash
npm run format:check
```

**Expected**: `All matched files use Prettier code style!` (or equivalent success).

- [ ] `npm run format` runs and reformats the drifted files
- [ ] `npm run format:check` passes

### 6. ESLint (the v12.2.2 target)

```bash
npm run lint
```

**Expected**: 0 errors, 0 warnings. Specifically confirm the previous line 117 warning in `SiteSettings/Index.tsx` no longer fires.

If you want to check the specific file:

```bash
npm run lint -- resources/js/Pages/Admin/SiteSettings/Index.tsx
```

**Expected**: clean output for the file.

- [ ] `npm run lint` passes with 0 warnings
- [ ] Line 117 warning specifically resolved

### 7. TypeScript typecheck

```bash
npm run typecheck
```

**Expected**: 0 errors.

- [ ] `npm run typecheck` passes

### 8. Vite production build

```bash
npm run build
```

**Expected**: build completes. `public/build/manifest.json` exists.

- [ ] `npm run build` completes
- [ ] `public/build/manifest.json` exists

## Application-level tests

### 9. Full test suite (if practical)

```bash
php artisan test
```

**Expected**: 1,556 scenarios pass. Watch specifically for personalization tests since v12.2.1's PHP fix touched `CustomerAffinityService.php`.

- [ ] `php artisan test` runs without ParseError
- [ ] Personalization suite passes
- [ ] Overall result recorded

Actual result: __________________________________

### 10. Arabic translations audit

```bash
php artisan translations:audit ar
```

- [ ] Runs without error

## Regression protection

### 11. Site settings smoke test (touches the file I modified)

Log in as super_admin and visit `/admin/site-settings`. Verify:

- Tabs render (branding, appearance, header, homepage, footer, contact, social, seo, mobile, vendor_intelligence)
- Editing a field, clicking Save, refreshing the page shows the change persisted
- The reset button works

- [ ] Site settings UI functional
- [ ] Vendor intelligence tab visible (Phase 11B.4.3 fix intact)
- [ ] Save + reset both work

### 12. Personalization end-to-end

The v12.2.1 PHP fix in `CustomerAffinityService.php` is the risky change. Verify:

```bash
php artisan personalization:rebuild --stale-days=1
```

**Expected**: completes without ParseError.

- [ ] Personalization rebuild runs cleanly

### 13. Vendor intelligence

```bash
php artisan schedule:list | grep vendor-intelligence
php artisan vendor-intelligence:generate --vendor=1
```

**Expected**: 2 scheduled entries; generate command runs cleanly.

- [ ] Both checks pass

## Production readiness preservation

### 14. Deployment scripts

```bash
head -5 scripts/deploy-production-phase12.sh
head -5 scripts/deploy.sh
```

**Expected**:
- `deploy-production-phase12.sh` starts with Phase 12 production banner
- `deploy.sh` starts with `LEGACY — DO NOT USE FOR PRODUCTION` banner

- [ ] Both intact

### 15. `.env.example.production`

```bash
grep "APP_ENV=production" .env.example.production
grep "APP_DEBUG=false" .env.example.production
grep -c "CHANGE_ME_" .env.example.production
```

**Expected**: matches, 9 placeholders.

- [ ] Template intact

### 16. Phase 12.2 documents

```bash
ls PHASE_12_2_*.md | wc -l
```

**Expected**: ≥ 25 (19 original Phase 12.2 + 5 v12.2.1 + 5 v12.2.2 = 29 including this repair's docs, +1 route-authorization checklist).

- [ ] All Phase 12.2 documents present

## Sign-off

- Reviewer name: _________________________
- Date: _________________________
- Environment: _________________________
- PHP version: _________________________
- Node version: _________________________
- Overall status: [ ] APPROVED  [ ] BLOCKED

Blockers:

_____________________________________________________________
_____________________________________________________________

## If something fails

- **`npm run lint` still fails on SiteSettings/Index.tsx**: run `npm run lint -- resources/js/Pages/Admin/SiteSettings/Index.tsx --format=stylish` and share the exact rule name. My fix removed the type-inference ambiguity but I could not confirm the exact rule name without running ESLint in the sandbox.
- **`npm run format:check` still fails after `npm run format`**: try `npx prettier --write "resources/**/*.{ts,tsx,css,js,jsx}"` explicitly. If it still fails, share which files fail with `npm run format:check 2>&1 | head -20`.
- **`npm run typecheck` fails**: run `npx tsc --noEmit --pretty | head -20` and share the first errors.
- **PHP tests fail**: report which suites; personalization suite regression from my v12.2.1 fix would be the highest-priority investigation.
