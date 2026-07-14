# Phase 12.3 — Package Integrity Report

Verification that the Phase 12.3 delivery is complete, contains no private keys, and preserves all prior phase work — including the developer-approved v12.2.3 SiteSettings fix.

## Archive names

| File | Format |
| --- | --- |
| `marketplace-phase-12-3-license-activation-protection.tar.gz` | gzip-compressed tar (recommended for Linux) |
| `marketplace-phase-12-3-license-activation-protection.zip` | ZIP (recommended for Windows / macOS) |

## SHA-256 hashes

The definitive hashes live in `/mnt/user-data/outputs/*.sha256`. This document lists the hashes as of the last rebuild; the sidecar files are always the source of truth.

Verify:

```bash
sha256sum -c marketplace-phase-12-3-license-activation-protection.tar.gz.sha256
sha256sum -c marketplace-phase-12-3-license-activation-protection.zip.sha256
# Both must return: OK
```

**On the SHA paradox**: this document is inside the archive, so adding it changes the archive's SHA. Trust the sidecar `.sha256` files for what's on disk.

## Baseline

This Phase 12.3 delivery is built on the **v12.2.3 approved baseline** (`Phase 12.2 v12.2.3 Final Admin Login, Lint and Format Repair`). Every file from v12.2.3 remains byte-identical in this archive EXCEPT the three files touched to integrate the license layer:

- `VERSION` — bumped
- `bootstrap/app.php` — added `license` middleware alias + appendToGroup on web
- `routes/web.php` — appended 3 license routes at the end
- `.env.example.production` — inserted `LICENSE_*` section before pre-deployment checklist

No application code (controllers, services, models) outside the new `Licensing/` namespace was touched. No migration was modified — only one new additive migration was added.

## Extract-verify results

Performed against the shipped archive:

### VERSION

```
$ cat VERSION
Phase 12.3 License Activation
```

✅ Correct.

### 6 required Phase 12.3 documents

Directive lists 6 required documents. All present in the archive:

| # | File | Present |
| ---: | --- | :---: |
| 1 | `PHASE_12_3_LICENSE_ACTIVATION_REPORT.md` | ✅ |
| 2 | `PHASE_12_3_LICENSE_OWNER_GUIDE.md` | ✅ |
| 3 | `PHASE_12_3_LICENSE_DEVELOPER_CHECKLIST.md` | ✅ |
| 4 | `PHASE_12_3_LICENSE_ROLLBACK.md` | ✅ |
| 5 | `PHASE_12_3_PACKAGE_INTEGRITY.md` | ✅ (this file) |
| 6 | `LICENSE_PRIVATE_KEY_OWNER_INSTRUCTIONS.md` | ✅ |

### License code files (18 total)

- `config/license.php` — safe defaults, every setting documented
- `app/Services/Licensing/LicenseVerifier.php` — pure Ed25519 verification
- `app/Services/Licensing/LicenseManager.php` — orchestrator + cache + audit
- `app/Services/Licensing/ServerFingerprintService.php` — installation UUID + fingerprint
- `app/Http/Middleware/EnsureValidLicense.php` — the gate
- `app/Http/Controllers/Admin/LicenseController.php` — 3 actions (index / activate / publicStatus)
- `app/Console/Commands/LicenseStatusCommand.php`
- `app/Console/Commands/LicenseFingerprintCommand.php`
- `app/Console/Commands/LicenseActivateCommand.php`
- `app/Console/Commands/LicenseClearCacheCommand.php`
- `database/migrations/2027_02_01_000001_create_license_tables.php`
- `resources/js/Pages/Admin/License/Index.tsx`
- `resources/js/Pages/License/Status.tsx`
- `resources/js/Pages/License/Blocked.tsx`
- `tools/license-generator/generate.php` (owner-side, not runtime)
- `tools/license-generator/README.md`
- `tests/Feature/Phase12_3LicenseActivationTest.php` — 20 `it()` scenarios (verified: `grep -c "^it(" ...` returns 20)

### Migration count

- 77 baseline (from v12.2.3 == v12.2.2) + 1 new = **78 migrations total**

### Route additions

`routes/web.php` gained 3 routes at the end:

- `GET  /license/status` — public, minimal status page
- `GET  /admin/license` — admin activation UI (auth)
- `POST /admin/license/activate` — activate a pasted token (auth, throttle:10,1)

### `bootstrap/app.php` middleware

- `'license'` alias → `\App\Http\Middleware\EnsureValidLicense::class`
- Appended to the `web` middleware group

## Preservation — v12.2.3 (the immediate previous baseline)

**Critical verification**: the developer-approved v12.2.3 SiteSettings fix must be intact. Confirmed:

| Item | Status | Evidence |
| --- | :---: | --- |
| `type JsonValue` definition present | ✅ | `grep -c "type JsonValue " resources/js/Pages/Admin/SiteSettings/Index.tsx` = 1 |
| `useForm<SiteSettingsFormData>` fix present | ✅ | grep = 1 |
| No `Record<string, any>` in code | ✅ | Only 1 match, which is inside the v12.2.3 explanatory comment |
| `JsonPrimitive`, `SiteSettingsFormData` types present | ✅ | grep verified |
| The full type chain (Props → SectionsRegistry → GroupEditorProps → FieldEditorProps) uses `SiteSettingsFormData` / `JsonValue` | ✅ | grep verified |

## Preservation — v12.2.2 (approved lint/format fix)

| Item | Preserved | Evidence |
| --- | :---: | --- |
| `SiteSettings/Index.tsx` uses explicit generic on `useForm` (upgraded to `SiteSettingsFormData` in v12.2.3) | ✅ | Verified above |
| No `as never` in code | ✅ | 0 code matches |
| Phase 12.2.2 documents preserved | ✅ | 5 `PHASE_12_2_2_*.md` present |

## Preservation — v12.2.1 (parse-error fix)

| Item | Preserved | Evidence |
| --- | :---: | --- |
| `CustomerAffinityService.php` uses `if/elseif` chain | ✅ | `grep -c "match (\$dim)" ...` returns 0 |
| The v12.2.1 comment marker `v12.2.1 parse-error fix` | ✅ | grep = 1 |
| All 8 unescaped-entity fixes (Admin/Reports, Bookings/Confirmation, Checkout/Show, Orders/Confirm) | ✅ | Files unchanged from v12.2.3 |
| Phase 12.2.1 documents preserved | ✅ | 5 `PHASE_12_2_1_*.md` present |

## Preservation — v12.2 (production launch readiness)

All 19+1 Phase 12.2 documents preserved.

## Preservation — v12.1 (database preparation)

| Item | Preserved | Evidence |
| --- | :---: | --- |
| `.env.example.production` with 9 `CHANGE_ME_` placeholders | ✅ | grep verified |
| `.env.example.production` LICENSE_* section ADDED (Phase 12.3 only) | ✅ | New section between vendor intelligence + pre-deployment checklist |
| `scripts/deploy-production-phase12.sh` executable | ✅ | Mode `-rwxr-xr-x` in archive |
| `scripts/deploy.sh` LEGACY banner + `APP_ENV=production` refuse-gate | ✅ | grep confirms LEGACY at line 4, refuse-gate at line 21 |
| All 7 v12/v12.1 documents preserved | ✅ | All present |

## Preservation — v11B.4.3 vendor intelligence

| Item | Preserved |
| --- | :---: |
| `app/Mail/VendorIntelligenceDigestMail.php` | ✅ |
| `app/Jobs/SendVendorIntelligenceDigest.php` | ✅ |
| `app/Observers/VendorIntelligence/ProductTranslationObserver.php` | ✅ |
| `resources/views/emails/vendor-intelligence-digest.blade.php` | ✅ |
| Migration `2027_01_01_000001_add_vendor_intelligence_digest_columns.php` | ✅ |

## Preservation — v11B.4.2 + earlier

| Item | Preserved |
| --- | :---: |
| Migration `2026_12_01_000001_add_vendor_intelligence_dedupe_and_stale.php` | ✅ |
| Migration `2026_11_01_000001_create_vendor_intelligence_tables.php` | ✅ |
| All 77 baseline migrations | ✅ |
| All 13 seeders | ✅ |
| All 106 baseline test files (1,556 `it()` scenarios) | ✅ |
| Personalization, checkout, cart, orders, admin, vendor, customer, pricing code | ✅ |

Baseline unchanged: 106 test files + 1 new = **107 total**. 1,556 baseline scenarios + 20 new = **1,576 total**.

## Security audit — no private key anywhere

```bash
$ grep -R "PRIVATE KEY" . --exclude-dir=node_modules --exclude-dir=vendor --exclude-dir=.git
./PHASE_12_3_LICENSE_OWNER_GUIDE.md              # documentation reference
./PHASE_12_3_LICENSE_ACTIVATION_REPORT.md        # documentation reference
./PHASE_12_3_LICENSE_DEVELOPER_CHECKLIST.md      # documentation reference
./LICENSE_PRIVATE_KEY_OWNER_INSTRUCTIONS.md      # documentation reference
```

Every match is a documentation reference. **No actual key material** exists in any file.

```bash
$ grep -R "BEGIN RSA\|BEGIN EC\|BEGIN OPENSSH\|BEGIN PGP PRIVATE" . \
    --exclude-dir=node_modules --exclude-dir=vendor --exclude-dir=.git
./PHASE_12_3_LICENSE_DEVELOPER_CHECKLIST.md      # the developer's grep command inside code block
```

Zero real PEM markers.

```bash
$ grep -rE '"[A-Za-z0-9+/]{43}="' . --include="*.php" --include="*.env*" --include="*.json"
(empty)
```

Zero base64 strings that could be a raw 32-byte key.

### `.env.example.production` audit

- `LICENSE_PUBLIC_KEY=` is EMPTY (correct — real key installed by operator)
- `LICENSE_ENFORCEMENT_ENABLED=false` (safe default)
- No `CHANGE_ME_PRIVATE_KEY_` or similar placeholder — because there is no private key placeholder to fill in

### `config/license.php` audit

- `public_key_base64 => env('LICENSE_PUBLIC_KEY', '')` — empty default
- No hardcoded key anywhere
- Rejects the string `PLACEHOLDER_PUBLIC_KEY_MUST_BE_REPLACED` explicitly (belt-and-suspenders)

## Excluded from archive

- `MARKETPLACE_PLATFORM_PLAN.md` — plan file
- `node_modules/` — restored via `npm ci`
- `vendor/` — restored via `composer install`
- `.git/` — history not shipped
- `tsconfig.verify.json` — sandbox-only helper
- **Any Ed25519 private key** — never generated in this codebase; owner generates on their own machine per `LICENSE_PRIVATE_KEY_OWNER_INSTRUCTIONS.md`

Verified via `tar -tzf ... | grep`:

- 0 matches for `MARKETPLACE_PLATFORM_PLAN.md`
- 0 matches for `node_modules`
- 0 matches for `private.b64`
- 0 matches for `license-test-keys`

## What Phase 12.3 did NOT change (relative to v12.2.3)

- Every migration prior to `2027_02_01_000001` is unchanged
- Every controller outside `Admin/LicenseController.php` is unchanged
- Every model is unchanged
- Every service outside `Services/Licensing/` is unchanged
- Every seeder is unchanged
- No dependency added to `composer.json` or `package.json`
- No changes to the SiteSettings `JsonValue` type chain (v12.2.3 fix intact)
- No changes to the `CustomerAffinityService` if/elseif fix (v12.2.1 fix intact)
- `bootstrap/app.php` gained one alias + one appendToGroup; nothing removed
- `routes/web.php` gained 3 routes at the END; nothing above changed
- `.env.example.production` gained one LICENSE_* section; existing lines unchanged
- All 30+ Phase 12.2 documents (v12.2 + v12.2.1 + v12.2.2 + v12.2.3) preserved unchanged

## Sign-off

Two engineers should sign after downloading and extract-verifying:

**Engineer 1**

- Name: _________________________
- Date: _________________________
- SHA verified: [ ] tar.gz  [ ] zip
- `grep -R "PRIVATE KEY"` confirmed no real keys: [ ]
- `php artisan test --filter=Phase12_3License` — pass count: _______ / 20

**Engineer 2**

- Name: _________________________
- Date: _________________________
- SHA verified: [ ] tar.gz  [ ] zip
- Full test suite: _______ / 1,576
- End-to-end owner activation flow tested: [ ]
- v12.2.3 SiteSettings `JsonValue` type family confirmed intact: [ ]
