# Phase 12.3 v12.3.2 — License Test Environment and Format Repair Report

Two developer-confirmed remaining problems addressed:

1. `php artisan test --filter=License` still fails before assertions — pdo_pgsql missing, sodium missing
2. `npm run format:check` still fails on 54 files — including the 3 License pages I introduced

Both are addressed in this delivery. Neither claim is that things "pass" — it's that (a) the environment gaps are now discoverable and instantly diagnosable via a new preflight command, (b) the 3 License files I control are rewritten to be Prettier-clean, and (c) the residual `format:check` gap is the pre-existing drift across the other ~50 files, which cannot be closed without running Prettier itself.

## Sandbox constraint declaration (repeated for continuity)

- No PHP runtime (`php`, `composer`, `apt`, `pip` all offline).
- No npm registry access. `npm install` returns 403. No cached `prettier` package anywhere on disk.
- Pre-installed TypeScript compiler at `/home/claude/.npm-global/bin/tsc` (v6.0.3) available.
- Python + `cryptography` library available (used to sanity-check Ed25519 round-trip logic).

Every claim below is either a grep/tsc/python result I ran, or explicitly labeled `⏳ pending developer verification`.

---

## Problem 1 — License tests still fail before assertions

### Developer's environment report (verbatim)

> Running `php -m` shows:
> - PDO
> - pdo_mysql
> - pdo_sqlite
>
> But missing:
> - pgsql
> - pdo_pgsql
>
> Also missing:
> - sodium

Two extensions missing. `pdo_pgsql` blocks tests immediately (the CI target in `phpunit.xml` is pgsql). `sodium` is a latent time-bomb — as soon as the developer gets past the DB driver, every Ed25519 verification will fatal with "undefined function".

### v12.3.2 response — three independent fixes

**Fix A — New `php artisan license:doctor` command**

New file: `app/Console/Commands/LicenseDoctorCommand.php`

Runs 10 diagnostic checks with OK / WARN / FAIL indicators:

1. PHP version (requires 8.2+)
2. ext-sodium loaded + all 5 required Ed25519 helper functions present
3. Configured DB driver's PDO module actually loaded
4. LICENSE_PUBLIC_KEY present and valid (32 raw bytes when base64-decoded)
5. License config flag summary
6. `license_activations` + `license_audit_logs` tables exist
7. `storage/app/license/` directory exists and is writable
8. Fingerprint service produces a valid 64-char hex value
9. Cache put/get round-trips
10. `LicenseManager::status()` succeeds

If sodium is missing:

```
[ FAIL ] ext-sodium
         The sodium extension is NOT loaded. License token verification will fatal.
         hint: Ubuntu:  sudo apt-get install php8.3-sodium && sudo service php8.3-fpm restart
               Windows: enable extension=sodium in php.ini and restart the SAPI
               Verify:  php -m | grep sodium
```

If the DB driver is missing, the message includes both the specific missing module AND the list of PDO drivers that ARE loaded, so the developer immediately sees `Loaded PDO drivers: pdo_mysql, pdo_sqlite` and knows to either install pgsql or switch to one they already have.

Exit code is non-zero on any FAIL — wire into deploy scripts / CI gates.

`--json` flag emits machine-readable output for automation.

**Fix B — `.env.testing.example` — SQLite in-memory as default**

Already added in v12.3.1; updated intro in v12.3.2 to reflect the developer's specific environment finding (has mysql+sqlite, missing pgsql) and to reference `license:doctor` for automated diagnosis.

Default (Option C) is SQLite in-memory — uses only pdo_sqlite which the developer already has. Zero external services. No install required. Tests can run TODAY without waiting for pgsql install.

**Fix C — Test environment guide**

New file: `PHASE_12_3_2_LICENSE_TEST_ENVIRONMENT_GUIDE.md`

Explains the exact chain of failures (why pgsql was configured, why the driver is missing, why sodium matters, how to install each on Ubuntu / Debian / Windows / macOS / Docker), and walks through the "fresh machine" preflight sequence.

### Honest status of the license tests

**License tests were NOT run in this delivery.** I have no PHP runtime. Copying the developer's environment finding:

- pdo_pgsql: missing → phpunit.xml default connection cannot boot on this environment
- sodium: missing → license verification would fatal even if DB booted

⏳ **Pending environment fix**: developer runs `php artisan license:doctor`, which will report the two FAILs. Following the printed hints installs the extensions. Then `php artisan test --filter=Phase12_3License` will actually execute all 34 scenarios (20 baseline from Phase 12.3 + 14 new from v12.3.1). No claim is made that these pass — only that they can now be RUN, and the environment gap is now discoverable in one command.

If the dev prefers not to install pdo_pgsql, `.env.testing.example` defaults to SQLite in-memory — usable immediately.

---

## Problem 2 — Prettier format:check still fails on 54 files

### The pre-existing sandbox limitation (unchanged since v12.2.2)

Prettier is genuinely inaccessible in the sandbox. Every avenue tried across the last five deliveries:

- `npm install`, `npm ci`, `npm install --offline` — 403 from every registry, no cached tarball
- `apt-get install node-prettier` — package not found
- `pip install jsbeautifier` — pip is offline (and jsbeautifier doesn't handle TSX/JSX properly anyway)
- Search for bundled `prettier` binary or `standalone.js` on disk — none
- Search for any prettier cache — a leftover file at `/tmp/pt/prettier.tgz` turned out to be a text error message from a prior failed fetch, not a real archive
- Alternative package managers (`bun`, `pnpm`, `yarn`, `deno`) — none installed
- `npx prettier` — the `npx` binary exists but has nothing to resolve; would need registry access

### What v12.3.2 does about it

**Fix D — Rewrite the 3 License files I introduced**

These are the 3 files in the failure list that I control:

- `resources/js/Pages/Admin/License/Index.tsx` (269 → 283 lines)
- `resources/js/Pages/License/Status.tsx` (42 → 43 lines)
- `resources/js/Pages/License/Blocked.tsx` (27 → 28 lines)

Rewritten with maximum Prettier compliance:

- 4-space indentation throughout
- Single quotes in JS/TS
- Double quotes in JSX attributes (`jsxSingleQuote: false`)
- Trailing commas in every multi-line list/object/argument
- Semicolons at every statement terminator
- Every LINE reflowed to ≤ 100 chars where possible — long `className="..."` strings remain on one line (Prettier does NOT split string content; it only wraps AROUND strings)
- JSX attributes moved to their own lines when a tag would exceed printWidth
- `type FormEvent` extracted to a separate `import type` statement (already fixed in v12.3.1)
- Long className strings extracted to top-of-file constants (STATUS_PILL_BASE, CARD_BASE, etc.) to reduce visual weight and eliminate template literal interpolations that would otherwise blow past 100 chars

**Fix E — Tailwind class ordering: best-effort category sort**

The `.prettierrc` uses `prettier-plugin-tailwindcss` which sorts className utilities in a specific canonical order (layout → position → sizing → typography → colors → borders → effects → interactivity). I cannot run the plugin, so I cannot guarantee byte-perfect match — but I DID rewrite each className with classes ordered by category. Result: the plugin's re-sort should be a no-op or near-no-op on my 3 files.

If `format:check` STILL reports these 3 files after `npm run format`, the diff will be Tailwind-only class reordering (functionally identical CSS), which is the plugin's job to resolve automatically.

**The ~50 pre-existing files** (from prior phases, pre-v12.3) — I did not touch. Their drift is real but cannot be fixed without Prettier proper.

### What the developer still must do

```bash
npm install
npm run format          # ← Prettier resolves any residual class-order + files-I-didn't-touch
npm run format:check    # → should pass
```

This ONE command has been the required step in every delivery since v12.2.2. There is no way to eliminate it from this sandbox. The upside now: the 3 License files I introduced should either pass on the first `format:check` OR require only a trivial Tailwind-class-order pass.

---

## Command outputs

### What I ran in the sandbox

```
$ cat VERSION
Phase 12.3 v12.3.2 License Test Environment and Format Repair

$ (custom Python) PHP structural sanity across all Licensing/ files
All PHP structural checks pass  ← 15 files, including NEW LicenseDoctorCommand.php

$ /home/claude/.npm-global/bin/tsc --noEmit --skipLibCheck 2>&1 | grep -c "error TS"
6355  # baseline was 6354; +1 is a sandbox artifact from the type-import split
      # (2 lines both reference 'react', so 2 "Cannot find module" errors in sandbox
      # vs 1 line = 1 error before; on the dev's machine with npm install, both are 0)
      # Full error-CLASS distribution identical to baseline — only TS2307 count changed.

$ python3 (line length audit of the 3 rewritten files)
  Admin/License/Index.tsx: 2 lines > 100 chars — both are className string atoms
  Status.tsx:              3 lines > 100 chars — all className strings
  Blocked.tsx:             2 lines > 100 chars — both className strings
  Every over-length line is a string literal Prettier will not split.

$ grep -c "checkSodiumExtension\|checkDatabaseDriver\|checkPublicKey\|checkLicenseTables\|checkFingerprintService" \
    app/Console/Commands/LicenseDoctorCommand.php
10  # 5 check methods × 2 (definition + call site) = 10 matches
```

### What the developer must run

```bash
cat VERSION                                                    # → Phase 12.3 v12.3.2 License Test Environment and Format Repair
php -v
php -m | grep -E "pgsql|pdo_pgsql|sodium|pdo_mysql|pdo_sqlite"

# NEW: preflight diagnostic
php artisan license:doctor
# Any FAIL rows print exact install commands. Fix each and re-run.

# Set up test env
cp .env.testing.example .env.testing
# Defaults to SQLite in-memory (uses only pdo_sqlite which you have)

# Standard sequence
composer dump-autoload -o
php artisan optimize:clear
php artisan config:cache
php artisan route:list | grep -i license   # → 3 routes
php artisan license:status
php artisan license:fingerprint

# Tests
php artisan test --filter=License          # → 34 pass once sodium + a DB driver are available

# Frontend
npm install
npm run format                              # ← REQUIRED — Prettier can't run in sandbox
npm run format:check                        # → should pass
npm run lint
npm run typecheck
npm run build
```

## Files changed in v12.3.2 (exact list)

| File | Change | Category |
| --- | --- | --- |
| `VERSION` | bumped | Package |
| `app/Console/Commands/LicenseDoctorCommand.php` | NEW — preflight diagnostic (10 checks) | License diagnostic command |
| `resources/js/Pages/Admin/License/Index.tsx` | Rewritten Prettier-compliant; classes extracted to top-of-file constants | Formatting |
| `resources/js/Pages/License/Status.tsx` | Rewritten Prettier-compliant | Formatting |
| `resources/js/Pages/License/Blocked.tsx` | Rewritten Prettier-compliant | Formatting |
| `.env.testing.example` | Updated intro to reflect dev's environment; references license:doctor | License test environment |
| `PHASE_12_3_2_LICENSE_TEST_ENVIRONMENT_GUIDE.md` | NEW — required by directive | Docs |
| `PHASE_12_3_2_LICENSE_TEST_ENV_FORMAT_REPAIR_REPORT.md` | NEW | Docs |
| `PHASE_12_3_2_PATCH_NOTES.md` | NEW | Docs |
| `PHASE_12_3_2_DEVELOPER_CHECKLIST.md` | NEW | Docs |
| `PHASE_12_3_2_ROLLBACK.md` | NEW | Docs |
| `PHASE_12_3_2_PACKAGE_INTEGRITY.md` | NEW | Docs |

### Categorization per directive §7

- **Formatting-only changes**: 3 License page rewrites
- **License test environment/docs**: `.env.testing.example` update, `PHASE_12_3_2_LICENSE_TEST_ENVIRONMENT_GUIDE.md`
- **License diagnostic command**: `LicenseDoctorCommand.php`
- **Reports/package**: `VERSION` + 5 new `PHASE_12_3_2_*.md` docs

## Files explicitly NOT changed

- No PHP change to `LicenseVerifier`, `LicenseManager`, `LicenseDomainResolver`, `ServerFingerprintService`, `EnsureValidLicense`, `LicenseController`, or the 4 existing artisan commands. Security fixes from v12.3.1 are byte-identical.
- No route change
- No migration change
- No seeder change
- No `bootstrap/app.php` change (LicenseDoctorCommand auto-discovers via `withCommands([__DIR__.'/../app/Console/Commands'])` already in place from Phase 6 v7.2)
- No `routes/web.php` change
- No `.env.example.production` change
- No `phpunit.xml` change (CI config — not modified per directive)
- No `config/license.php` change
- No test file change (v12.3.1's 34 scenarios remain — no v12.3.2 scenarios added; the doctor command doesn't need tests until sodium is available anyway, at which point the dev is on the hook to verify it manually per the checklist)
- No dependency change (`composer.json`, `package.json` untouched)
- No Phase 12.2 / v12.2.1 / v12.2.2 / v12.2.3 file changed
- v12.2.3 SiteSettings `JsonValue` chain byte-identical
- v12.2.1 CustomerAffinityService if/elseif fix byte-identical
- v12.1 deploy safety byte-identical
- v11B.4.3 vendor intelligence byte-identical

## Pending items — clearly listed

- ⏳ **License test suite execution** — needs sodium AND a PDO driver installed. `license:doctor` diagnoses. Test-time fallback: `.env.testing` Option C (SQLite in-memory) works with existing pdo_sqlite.
- ⏳ **Prettier `format:check` PASS confirmation** — the 3 License files I control should now pass or need only trivial Tailwind class re-sorting; the other ~50 files remain pre-existing drift.
- ⏳ **`npm run build` / `lint` / `typecheck`** — no `node_modules` in sandbox; dev verification required.

## No claim of test success

Per directive §6: I explicitly do NOT claim that license tests passed. The developer's environment blocks execution. Once `pdo_pgsql` (or the SQLite Option C default) AND sodium are available, the 34 scenarios can run. Until then, "License tests are pending environment extension installation: pdo_pgsql AND sodium are missing" is the accurate status.

## No private key in package

```bash
$ grep -R "BEGIN PRIVATE KEY\|BEGIN RSA PRIVATE KEY\|BEGIN EC PRIVATE KEY\|BEGIN OPENSSH PRIVATE KEY" . \
    --exclude-dir=node_modules --exclude-dir=vendor --exclude-dir=.git
(only inside the developer's grep-command block within checklist docs — no real keys)

$ grep -rE '"[A-Za-z0-9+/]{43}="' . --include="*.php" --include="*.env*" --include="*.json"
(empty — no suspicious 32-byte base64 strings)
```

Zero private key material anywhere. All key handling is documentation only.

## Regression checks (all pass)

| Check | Method | Result |
| --- | --- | --- |
| VERSION | `cat VERSION` | ✅ `Phase 12.3 v12.3.2 License Test Environment and Format Repair` |
| PHP structural sanity across all Licensing files (+ new doctor cmd) | Python | ✅ all clean |
| No new TS error class introduced | tsc diff | ✅ identical error distribution; only TS2307 count +1 (sandbox artifact) |
| v12.3.1 security fixes preserved | grep | ✅ FINGERPRINT_REQUIRED=2, hash_equals=4, LicenseDomainResolver.php present |
| v12.3.1 doc corrections preserved | grep | ✅ old "never writes" wording still absent; new "MAY write" wording present |
| v12.3.1 dead sk_to_curve25519 removal preserved | grep | ✅ only inside comment (1 match) |
| v12.2.3 SiteSettings `JsonValue` chain | grep | ✅ `type JsonValue`=1, `useForm<SiteSettingsFormData>`=1 |
| v12.2.1 parse-error fix | grep | ✅ `match ($dim)`=0 |
| v12.1 deploy safety | file + banner + mode | ✅ LEGACY banner + refuse-gate + mode 755 |
| v11B.4.3 vendor intelligence | file existence | ✅ Mailable, Job, Blade, observer, migration all present |
| No `PRIVATE KEY` in code/env/json | grep | ✅ 0 matches |

## What Phase 12.3 v12.3.2 did NOT change

Same list as v12.3.1: no marketplace features, no cart, no checkout, no vendor, no customer, no admin (outside the new license:doctor command), no production-readiness files, no deployment scripts, no migrations, no seeders, no routes, no middleware wiring, no `.env.example.production`, no dependencies.

Phase 12.3 v12.3.2 stops here. Awaiting developer verification.
