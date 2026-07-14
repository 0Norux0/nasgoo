# Phase 12.3 v12.3.1 — Patch Notes

Concise change log. Full rationale in `PHASE_12_3_1_LICENSE_REPAIR_REPORT.md`.

## What changed

### Security (2 fixes)

**Bug #3 — Fingerprint binding bypass** — `LicenseVerifier`:
- Old compound `&&` short-circuited on null/empty/whitespace fingerprint, silently accepting tokens that should have been rejected
- New: two separate checks — first require non-empty fingerprint, then `hash_equals` constant-time compare
- New result code: `FINGERPRINT_REQUIRED`

**Bug #4 — Domain matching read APP_URL, not request host** — `LicenseManager` + new `LicenseDomainResolver`:
- Web context: `request()->getHost()` (respects TrustProxies middleware)
- CLI context: `LICENSE_DOMAIN` env → falls back to APP_URL
- New helpers `hostsEqual` + `normalizeHost` (lowercase, strip scheme/port/path, optional www alias)
- Same class of "missing claim" bug fixed in domain check — new result code `DOMAIN_REQUIRED`
- New config keys: `LICENSE_DOMAIN`, `LICENSE_ALLOW_WWW_ALIAS`

### Generator (2 fixes)

**Bug #5 — Doc contradiction about `private.b64`**:
- `tools/license-generator/README.md`: added "Honest disclosure about the private key" section + "Rules for the written private.b64 file" list
- `LICENSE_PRIVATE_KEY_OWNER_INSTRUCTIONS.md` line 7: distinguished "production package NEVER contains it" from "generator DOES write it locally when explicitly asked"
- `PHASE_12_3_LICENSE_OWNER_GUIDE.md`: added "Where the private key lives (v12.3.1 clarification)" section

**Bug #6 — Dead `sodium_crypto_sign_ed25519_sk_to_curve25519` call**:
- Removed. That helper does X25519 conversion (wrong operation) and throws on some libsodium builds. The next line's `substr()` seed extraction was already correct.

### Test infrastructure (1 fix)

**Bug #1 — Missing pdo_pgsql driver**:
- Added `.env.testing.example` with three options: pgsql / mysql / sqlite:in-memory
- Recommended for local dev: SQLite in-memory (no external services)
- `phpunit.xml` intentionally NOT modified (that's CI config)
- Documented in developer checklist

### Prettier (partial — sandbox limitation)

**Bug #2 — 53 files fail `format:check`**:
- Safe subset applied (0 files needed changes — already clean on whitespace/EOF)
- Cannot run Prettier in sandbox (no npm registry, no cached binaries)
- **Developer must run `npm run format`** — no way around it. Same declaration as v12.2.2/v12.2.3.

### Test scenarios added (14)

| Group | Count | Directive |
| --- | ---: | --- |
| Fingerprint (§10.1–6) | 6 | Missing/empty/whitespace token fingerprint = REJECTED when binding on |
| Domain (§10.7–12) | 8 | Request host used; APP_URL not authoritative; www alias; normalization |

Baseline 20 scenarios preserved. **Total: 34 scenarios** in `Phase12_3LicenseActivationTest.php`.

### VERSION

- `Phase 12.3 License Activation` → `Phase 12.3 v12.3.1 License Repair`

### Documentation added

- `PHASE_12_3_1_LICENSE_REPAIR_REPORT.md`
- `PHASE_12_3_1_PATCH_NOTES.md` (this file)
- `PHASE_12_3_1_DEVELOPER_CHECKLIST.md`
- `PHASE_12_3_1_ROLLBACK.md`
- `PHASE_12_3_1_PACKAGE_INTEGRITY.md`

## What did NOT change

- No PHP file touched outside the licensing layer + tests
- No React page changed (the 3 License pages are byte-identical to Phase 12.3)
- No route added or removed
- No migration change
- No seeder change
- No dependency change
- `bootstrap/app.php` middleware wiring unchanged
- `routes/web.php` unchanged
- `phpunit.xml` unchanged
- All prior-phase preservation intact: v12.2.3 SiteSettings `JsonValue`, v12.2.1 parse fix, v12.1 deploy safety, v11B.4.3 vendor intelligence

## Files changed

| Category | Files |
| --- | --- |
| License security logic | `LicenseVerifier`, `LicenseManager`, `LicenseDomainResolver` (new), `config/license.php`, `.env.example.production`, `tests/Feature/Phase12_3LicenseActivationTest.php` |
| Generator/documentation | `generate.php`, `README.md` (generator), `LICENSE_PRIVATE_KEY_OWNER_INSTRUCTIONS.md`, `PHASE_12_3_LICENSE_OWNER_GUIDE.md` |
| Environment/testing | `.env.testing.example` (new) |
| Formatting-only | 0 files (sandbox cannot run Prettier — dev must run `npm run format`) |
| Reports/package | `VERSION`, 5 new `PHASE_12_3_1_*.md` docs |

## Verification the developer must run

```bash
cat VERSION                                              # → Phase 12.3 v12.3.1 License Repair

# Set up test env (choose driver)
cp .env.testing.example .env.testing
# uncomment Option A (pgsql), B (mysql), or C (sqlite in-memory)

# Backend + tests
composer dump-autoload -o
php artisan optimize:clear
php artisan config:cache
php artisan license:status
php artisan license:fingerprint
php artisan test --filter=Phase12_3License   # → 34 pass (20 baseline + 14 new)
php artisan test                              # → full suite

# Frontend
npm install
npm run format
npm run format:check
npm run lint
npm run typecheck
npm run build

# Security audit
grep -R "sodium_crypto_sign_ed25519_sk_to_curve25519" tools/license-generator/generate.php
# → 1 match (only in the explanatory comment)

grep -R "BEGIN PRIVATE KEY\|BEGIN RSA PRIVATE KEY" . \
    --exclude-dir=node_modules --exclude-dir=vendor --exclude-dir=.git
# → 0 real matches
```

## Rollback

Tier 1 (safest): revert to Phase 12.3 archive. See `PHASE_12_3_1_ROLLBACK.md`. Fingerprint bypass returns — do not roll back unless the security fix is causing a specific documented regression.
