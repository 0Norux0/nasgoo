# Phase 12.3 v12.3.2 — Patch Notes

Concise change log. Full rationale in `PHASE_12_3_2_LICENSE_TEST_ENV_FORMAT_REPAIR_REPORT.md`.

## What changed

### New: `php artisan license:doctor`

**`app/Console/Commands/LicenseDoctorCommand.php`** — preflight diagnostic:

- Checks PHP version (needs 8.2+)
- Checks ext-sodium loaded AND the 5 Ed25519 helpers we call
- Checks the configured DB driver's PDO module is loaded
- Checks LICENSE_PUBLIC_KEY is present and 32 raw bytes when base64-decoded
- Reports config flags
- Checks license tables exist
- Checks `storage/app/license/` is writable
- Checks fingerprint service produces a valid 64-char hex
- Checks cache round-trips
- Checks `LicenseManager::status()` succeeds

Prints OK / WARN / FAIL for each. FAIL rows include the exact install command for Ubuntu / Windows / macOS / Docker. Non-zero exit on any FAIL — usable in deploy scripts. `--json` flag for machine-readable output.

Auto-registered via Laravel 11 command discovery (no bootstrap change needed).

### 3 License pages rewritten Prettier-compliant

- `resources/js/Pages/Admin/License/Index.tsx` — long className strings extracted to top-of-file constants (STATUS_PILL_BASE, CARD_BASE, FLASH_SUCCESS, etc.), JSX attributes wrapped at 100 chars, Tailwind classes reordered to plugin's canonical category order (best-effort — plugin's final sort may still adjust)
- `resources/js/Pages/License/Status.tsx` — same treatment
- `resources/js/Pages/License/Blocked.tsx` — same treatment

The rewrites eliminate all Prettier violations I control (indent, quotes, semicolons, trailing commas, JSX attribute wrapping, line reflow). Residual: Tailwind class ordering may still differ from `prettier-plugin-tailwindcss` output — that's a plugin sort, not a code defect.

### `.env.testing.example` intro updated

Now references the developer's specific environment finding (has pdo_mysql + pdo_sqlite, missing pdo_pgsql) and points at `license:doctor` for automated diagnosis. Default remains Option C (SQLite in-memory) — uses only pdo_sqlite which the developer already has, works with zero external services.

### VERSION

- `Phase 12.3 v12.3.1 License Repair` → `Phase 12.3 v12.3.2 License Test Environment and Format Repair`

### Docs added (per directive §3 + §11)

- `PHASE_12_3_2_LICENSE_TEST_ENVIRONMENT_GUIDE.md` — REQUIRED per directive §4
- `PHASE_12_3_2_LICENSE_TEST_ENV_FORMAT_REPAIR_REPORT.md`
- `PHASE_12_3_2_PATCH_NOTES.md` (this file)
- `PHASE_12_3_2_DEVELOPER_CHECKLIST.md`
- `PHASE_12_3_2_ROLLBACK.md`
- `PHASE_12_3_2_PACKAGE_INTEGRITY.md`

## What did NOT change

- No PHP change to any license service, controller, middleware, or existing command
- No change to `LicenseVerifier`, `LicenseManager`, `LicenseDomainResolver`, `ServerFingerprintService`, `EnsureValidLicense`, `LicenseController`
- No change to any test file — v12.3.1's 34 scenarios preserved as-is
- No change to migrations, seeders, routes, `bootstrap/app.php`, `phpunit.xml`, `config/license.php`, `.env.example.production`
- No dependency change
- All v12.3.1 security fixes preserved byte-for-byte
- All prior-phase preservation intact: v12.2.3 SiteSettings, v12.2.1 parse fix, v12.1 deploy safety, v11B.4.3 vendor intelligence

## Honest pending items

Same as v12.3.1 but with better diagnostic tooling:

- ⏳ **License tests execution** — needs sodium + a PDO driver. `license:doctor` diagnoses; `.env.testing` Option C (SQLite) works with pdo_sqlite alone.
- ⏳ **Prettier `format:check` PASS** — 3 License pages should pass or need only trivial Tailwind re-sort. Other ~50 files remain pre-existing drift; dev MUST run `npm run format`.
- ⏳ **`npm run build` / `lint` / `typecheck`** — no `node_modules` in sandbox.

## Files changed by category (directive §7)

- **Formatting-only changes**: Admin/License/Index.tsx, License/Status.tsx, License/Blocked.tsx
- **License test environment/docs**: `.env.testing.example`, `PHASE_12_3_2_LICENSE_TEST_ENVIRONMENT_GUIDE.md`
- **License diagnostic command**: `LicenseDoctorCommand.php`
- **Reports/package**: VERSION + 5 new `PHASE_12_3_2_*.md` docs

## Verification the developer must run

```bash
cat VERSION                                              # → Phase 12.3 v12.3.2 License Test Environment and Format Repair

# NEW — diagnose environment gaps
php artisan license:doctor
# Follow the printed hints for any FAIL rows

# Set up test env (SQLite default works with your pdo_sqlite)
cp .env.testing.example .env.testing

# Tests (once sodium + at least one PDO driver installed)
php artisan test --filter=Phase12_3License   # → 34 pass

# Frontend
npm install
npm run format
npm run format:check                              # → should now pass
npm run lint && npm run typecheck && npm run build
```

## Rollback

Removing v12.3.2 removes the `license:doctor` command, the environment guide doc, and the 3 rewritten License pages (reverting to their v12.3.1 versions which fail Prettier). See `PHASE_12_3_2_ROLLBACK.md`.
