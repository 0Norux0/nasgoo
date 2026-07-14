# Phase 12.3 v12.3.2 — Package Integrity

## Archive names

| File | Format |
| --- | --- |
| `marketplace-phase-12-3-2-license-test-env-format-repair.tar.gz` | gzip-compressed tar |
| `marketplace-phase-12-3-2-license-test-env-format-repair.zip` | ZIP |

## SHA-256 hashes

The definitive hashes live in `/mnt/user-data/outputs/*.sha256`. Verify:

```bash
sha256sum -c marketplace-phase-12-3-2-license-test-env-format-repair.tar.gz.sha256
sha256sum -c marketplace-phase-12-3-2-license-test-env-format-repair.zip.sha256
# Both must return: OK
```

**Note**: this document is inside the archive, so adding it changes the archive's SHA. Trust the sidecar `.sha256` files, which are computed AFTER this file is included and finalized.

## Baseline

Built on the v12.3.1 archive (`Phase 12.3 v12.3.1 License Repair`). Every file byte-identical outside the v12.3.2 touchpoints below.

## Extract-verify results

### VERSION

```
$ cat VERSION
Phase 12.3 v12.3.2 License Test Environment and Format Repair
```

✅ Correct.

### Required v12.3.2 documents

Per directive §3 + §13:

| # | File | Present |
| ---: | --- | :---: |
| 1 | `PHASE_12_3_2_LICENSE_TEST_ENV_FORMAT_REPAIR_REPORT.md` | ✅ |
| 2 | `PHASE_12_3_2_PATCH_NOTES.md` | ✅ |
| 3 | `PHASE_12_3_2_DEVELOPER_CHECKLIST.md` | ✅ |
| 4 | `PHASE_12_3_2_ROLLBACK.md` | ✅ |
| 5 | `PHASE_12_3_2_PACKAGE_INTEGRITY.md` | ✅ (this file) |
| 6 | `PHASE_12_3_2_LICENSE_TEST_ENVIRONMENT_GUIDE.md` (directive §4) | ✅ |

### v12.3.2 additions verified

- ✅ `app/Console/Commands/LicenseDoctorCommand.php` — NEW file, 9 KB
- ✅ `.env.testing.example` — updated intro (SQLite in-memory remains active default)
- ✅ 3 License page rewrites — all Prettier-compliant (indent, quotes, semicolons, trailing commas, JSX wrapping, class-name extraction)

### Phase 12.3 v12.3.1 preservation (must be intact)

- ✅ `LicenseVerifier.php` still has `FINGERPRINT_REQUIRED` (2 occurrences)
- ✅ `LicenseVerifier.php` still has `hash_equals` (4 occurrences)
- ✅ `LicenseDomainResolver.php` file still present
- ✅ `LicenseManager.php` still calls `expectedDomain()`
- ✅ `LICENSE_DOMAIN` + `LICENSE_ALLOW_WWW_ALIAS` present in `.env.example.production`
- ✅ Generator no longer has live `sk_to_curve25519` call (only in explanatory comment)
- ✅ Doc corrections intact (no "never writes" wording; "MAY write" wording present)

### Phase 12.3 preservation

All 6 Phase 12.3 documents present. All Phase 12.3 code files byte-identical outside the v12.3.1 security fixes and v12.3.2 formatting rewrites.

### Migration count

- 77 baseline + 1 Phase 12.3 = **78 migrations** (unchanged; v12.3.2 added none)

### Test count

- 1,556 baseline + 20 v12.3 + 14 v12.3.1 = **1,590 total scenarios** (unchanged; v12.3.2 added none)

## Preservation

### v12.3.1 (immediate baseline)

Every v12.3.1 change preserved byte-identical:

| Item | Preserved |
| --- | :---: |
| LicenseVerifier security fixes (FINGERPRINT_REQUIRED, DOMAIN_REQUIRED, hostsEqual/normalizeHost) | ✅ |
| LicenseDomainResolver.php | ✅ |
| LicenseManager uses `domainResolver->expectedDomain()` | ✅ |
| `config/license.php` `domain` + `allow_www_alias` keys | ✅ |
| `.env.example.production` LICENSE_DOMAIN + LICENSE_ALLOW_WWW_ALIAS | ✅ |
| Generator dead sodium call removed | ✅ |
| 3 doc contradiction fixes (README, OWNER instructions, OWNER guide) | ✅ |
| 34 test scenarios in Phase12_3LicenseActivationTest | ✅ |

### v12.2.3 (developer-approved SiteSettings)

| Item | Preserved | Evidence |
| --- | :---: | --- |
| `type JsonValue` definition | ✅ | grep = 1 |
| `useForm<SiteSettingsFormData>` | ✅ | grep = 1 |
| Full type chain intact | ✅ | grep verified |

### v12.2.1 (parse-error fix)

| Item | Preserved | Evidence |
| --- | :---: | --- |
| `CustomerAffinityService` if/elseif | ✅ | 0 `match ($dim)` |
| 8 unescaped-entity fixes | ✅ | Byte-identical |

### v12.2 (production launch readiness)

All 19+1 Phase 12.2 docs preserved.

### v12.1 (database preparation)

| Item | Preserved |
| --- | :---: |
| 9 CHANGE_ME_ placeholders in `.env.example.production` | ✅ |
| `scripts/deploy-production-phase12.sh` mode 755 | ✅ |
| `scripts/deploy.sh` LEGACY banner + refuse-gate | ✅ |

### v11B.4.3 vendor intelligence

| Item | Preserved |
| --- | :---: |
| Mailable, Job, Blade, observer, migration | ✅ |

## Security audit — no private key anywhere

```bash
$ grep -R "PRIVATE KEY" . --exclude-dir=node_modules --exclude-dir=vendor --exclude-dir=.git
```

Every match is inside a documentation file discussing the CONCEPT of the private key. **No actual key material.**

```bash
$ grep -R "BEGIN RSA PRIVATE KEY\|BEGIN EC PRIVATE KEY\|BEGIN OPENSSH PRIVATE KEY\|BEGIN PGP PRIVATE" . \
    --exclude-dir=node_modules --exclude-dir=vendor --exclude-dir=.git
```

Only inside grep-command blocks within checklist documents.

```bash
$ grep -rE '"[A-Za-z0-9+/]{43}="' . --include="*.php" --include="*.env*" --include="*.json"
(empty)
```

Zero base64 strings that could be a raw 32-byte key.

### `.env.example.production` audit

- `LICENSE_PUBLIC_KEY=` EMPTY (correct)
- `LICENSE_ENFORCEMENT_ENABLED=false` (safe default)
- `LICENSE_DOMAIN=` EMPTY (from v12.3.1)
- `LICENSE_ALLOW_WWW_ALIAS=true` (from v12.3.1)

### `config/license.php` audit

- Byte-identical to v12.3.1 (v12.3.2 did not touch)
- `public_key_base64` uses `trim((string) env(...))` (from v12.3.1)
- No closures anywhere (config:cache safe)

## Excluded from archive

- `MARKETPLACE_PLATFORM_PLAN.md`
- `node_modules/`
- `vendor/`
- `.git/`
- `tsconfig.verify.json`
- Any Ed25519 private key (never generated in this codebase)

Verified via `tar -tzf ... | grep`:

- 0 matches for `MARKETPLACE_PLATFORM_PLAN.md`
- 0 matches for `node_modules`
- 0 matches for `private.b64`
- 0 matches for `license-test-keys`

## What Phase 12.3 v12.3.2 did NOT change

- No PHP change to any license service or the 4 existing artisan commands (LicenseStatusCommand, LicenseFingerprintCommand, LicenseActivateCommand, LicenseClearCacheCommand)
- Every migration byte-identical
- Every controller (including LicenseController) byte-identical
- Every model byte-identical
- Every seeder byte-identical
- No dependency change (`composer.json`, `package.json` untouched)
- `bootstrap/app.php` byte-identical (Phase 6 v7.2 `withCommands([...])` already auto-discovers the new command)
- `routes/web.php` byte-identical
- `phpunit.xml` byte-identical (per directive — CI config)
- `config/license.php` byte-identical
- All Phase 12.3 / v12.3.1 test scenarios byte-identical (no v12.3.2 tests)
- v12.3.1 security fixes byte-identical
- All Phase 12.2 / v12.2.1 / v12.2.2 / v12.2.3 files byte-identical

## Sign-off

**Engineer 1**

- Name: _________________________
- Date: _________________________
- SHA verified: [ ] tar.gz  [ ] zip
- `license:doctor` runs cleanly after ext installs: [ ]
- 3 License pages pass `format:check` after `npm run format`: [ ]

**Engineer 2**

- Name: _________________________
- Date: _________________________
- SHA verified: [ ] tar.gz  [ ] zip
- Full test suite: _______ / 1,590
- License subset: _______ / 34
