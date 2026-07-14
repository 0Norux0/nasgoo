# Phase 12.3 v12.3.1 — Package Integrity

## Archive names

| File | Format |
| --- | --- |
| `marketplace-phase-12-3-1-license-repair.tar.gz` | gzip-compressed tar |
| `marketplace-phase-12-3-1-license-repair.zip` | ZIP |

## SHA-256 hashes

The definitive hashes live in `/mnt/user-data/outputs/*.sha256`. Verify:

```bash
sha256sum -c marketplace-phase-12-3-1-license-repair.tar.gz.sha256
sha256sum -c marketplace-phase-12-3-1-license-repair.zip.sha256
# Both must return: OK
```

**On the SHA paradox**: this document is inside the archive, so adding it changes the archive's SHA. Trust the sidecar `.sha256` files.

## Baseline

Built on the developer-reviewed Phase 12.3 archive (`Phase 12.3 License Activation`). Every prior-phase file byte-identical outside the touchpoints listed below.

## Extract-verify results

### VERSION

```
$ cat VERSION
Phase 12.3 v12.3.1 License Repair
```

✅ Correct.

### Required v12.3.1 documents

Directive §3 lists 5 required documents. All present:

| # | File | Present |
| ---: | --- | :---: |
| 1 | `PHASE_12_3_1_LICENSE_REPAIR_REPORT.md` | ✅ |
| 2 | `PHASE_12_3_1_PATCH_NOTES.md` | ✅ |
| 3 | `PHASE_12_3_1_DEVELOPER_CHECKLIST.md` | ✅ |
| 4 | `PHASE_12_3_1_ROLLBACK.md` | ✅ |
| 5 | `PHASE_12_3_1_PACKAGE_INTEGRITY.md` | ✅ (this file) |

### Phase 12.3 documents preserved (6)

- `PHASE_12_3_LICENSE_ACTIVATION_REPORT.md` (unchanged)
- `PHASE_12_3_LICENSE_OWNER_GUIDE.md` (v12.3.1 clarification section added — bug #5)
- `PHASE_12_3_LICENSE_DEVELOPER_CHECKLIST.md` (unchanged — v12.3.1 has its own checklist)
- `PHASE_12_3_LICENSE_ROLLBACK.md` (unchanged)
- `PHASE_12_3_PACKAGE_INTEGRITY.md` (unchanged)
- `LICENSE_PRIVATE_KEY_OWNER_INSTRUCTIONS.md` (bug #5 clarification applied)

### v12.3.1 additions verified in extracted archive

- ✅ `app/Services/Licensing/LicenseDomainResolver.php` — NEW file, 4.4 KB
- ✅ `.env.testing.example` — NEW file with 3 DB driver options
- ✅ `LicenseVerifier.php` contains `FINGERPRINT_REQUIRED` + `DOMAIN_REQUIRED` constants
- ✅ `LicenseVerifier.php` contains `hash_equals(...)` for constant-time compare (3 uses)
- ✅ `LicenseVerifier.php` contains `hostsEqual` + `normalizeHost` helpers
- ✅ `LicenseManager.php` injects `LicenseDomainResolver` in constructor
- ✅ `LicenseManager.php` calls `$this->domainResolver->expectedDomain()`
- ✅ `config/license.php` contains `license.domain` + `license.allow_www_alias` keys
- ✅ `.env.example.production` contains `LICENSE_DOMAIN=` + `LICENSE_ALLOW_WWW_ALIAS=true`
- ✅ `generate.php` no longer has live `sk_to_curve25519` call (only inside explanatory comment)
- ✅ Test file has 34 scenarios (baseline 20 + 14 new v12.3.1)

### Migration count

- 77 baseline + 1 Phase 12.3 = **78 migrations total** (unchanged from Phase 12.3 — v12.3.1 added no migrations)

## Preservation

### Phase 12.3 (the immediate baseline)

| Item | Preserved | Evidence |
| --- | :---: | --- |
| Phase 12.3 `license_activations` migration | ✅ | Unchanged (no schema edit in v12.3.1) |
| `EnsureValidLicense` middleware | ✅ | Byte-identical |
| `LicenseController` | ✅ | Byte-identical |
| 4 artisan commands | ✅ | Byte-identical |
| 3 React License pages | ✅ | Byte-identical (Prettier drift pending `npm run format` on dev's side) |
| Phase 12.3 routes in `routes/web.php` | ✅ | Byte-identical |
| Phase 12.3 middleware alias in `bootstrap/app.php` | ✅ | Byte-identical |

### v12.2.3 (developer-approved SiteSettings fix)

| Item | Status | Evidence |
| --- | :---: | --- |
| `type JsonValue` definition | ✅ | grep = 1 |
| `useForm<SiteSettingsFormData>` | ✅ | grep = 1 |
| Full type chain (Props → SectionsRegistry → GroupEditorProps → FieldEditorProps) | ✅ | grep verified |

### v12.2.1 (parse-error fix)

| Item | Preserved | Evidence |
| --- | :---: | --- |
| `CustomerAffinityService.php` uses `if/elseif` chain | ✅ | 0 matches for `match ($dim)` |
| All 8 unescaped-entity fixes | ✅ | Files unchanged |

### v12.2 (production launch readiness)

All 19+1 Phase 12.2 documents preserved.

### v12.1 (database preparation)

| Item | Preserved |
| --- | :---: |
| `.env.example.production` with 9 CHANGE_ME_ placeholders | ✅ |
| `scripts/deploy-production-phase12.sh` (mode 755) | ✅ |
| `scripts/deploy.sh` LEGACY banner + refuse-gate | ✅ |

### v11B.4.3 vendor intelligence

| Item | Preserved |
| --- | :---: |
| `VendorIntelligenceDigestMail`, `SendVendorIntelligenceDigest`, Blade template, observer, migration | ✅ |

## Security audit — no private key anywhere

```bash
$ grep -R "PRIVATE KEY" . --exclude-dir=node_modules --exclude-dir=vendor --exclude-dir=.git
./PHASE_12_3_LICENSE_OWNER_GUIDE.md              # documentation
./PHASE_12_3_LICENSE_ACTIVATION_REPORT.md        # documentation
./PHASE_12_3_LICENSE_DEVELOPER_CHECKLIST.md      # documentation
./LICENSE_PRIVATE_KEY_OWNER_INSTRUCTIONS.md      # documentation
./PHASE_12_3_1_LICENSE_REPAIR_REPORT.md          # documentation (this phase)
./PHASE_12_3_1_DEVELOPER_CHECKLIST.md            # documentation (this phase)
```

Every match is documentation. **No actual key material.**

```bash
$ grep -R "BEGIN RSA PRIVATE KEY\|BEGIN EC PRIVATE KEY\|BEGIN OPENSSH PRIVATE KEY\|BEGIN PGP PRIVATE" . \
    --exclude-dir=node_modules --exclude-dir=vendor --exclude-dir=.git
./PHASE_12_3_LICENSE_DEVELOPER_CHECKLIST.md      # developer's grep command inside code block
./PHASE_12_3_1_LICENSE_REPAIR_REPORT.md          # same command
```

Zero real PEM markers.

```bash
$ grep -rE '"[A-Za-z0-9+/]{43}="' . --include="*.php" --include="*.env*" --include="*.json"
(empty)
```

Zero base64 strings that could be a raw 32-byte key.

### `.env.example.production` audit

- `LICENSE_PUBLIC_KEY=` EMPTY (correct — real key installed by operator)
- `LICENSE_ENFORCEMENT_ENABLED=false` (safe default)
- `LICENSE_DOMAIN=` EMPTY (v12.3.1 addition — falls back to APP_URL)
- `LICENSE_ALLOW_WWW_ALIAS=true` (v12.3.1 addition — safe default)
- No CHANGE_ME_PRIVATE placeholder — because there is no private key placeholder to fill in

### `config/license.php` audit

- `public_key_base64 => trim((string) env('LICENSE_PUBLIC_KEY', ''))` (v12.3.1 hardened form)
- `domain => env('LICENSE_DOMAIN', '')` (v12.3.1 addition)
- `allow_www_alias => (bool) env('LICENSE_ALLOW_WWW_ALIAS', true)` (v12.3.1 addition)
- No hardcoded key anywhere
- No closures (config:cache safe)

## Excluded from archive

- `MARKETPLACE_PLATFORM_PLAN.md`
- `node_modules/`
- `vendor/`
- `.git/`
- `tsconfig.verify.json`
- **Any Ed25519 private key** — never generated in this codebase

Verified via `tar -tzf ... | grep`:

- 0 matches for `MARKETPLACE_PLATFORM_PLAN.md`
- 0 matches for `node_modules`
- 0 matches for `private.b64`
- 0 matches for `license-test-keys`

## What Phase 12.3 v12.3.1 did NOT change

- No PHP change outside the licensing layer + tests
- Every migration byte-identical (no v12.3.1 migration added or modified)
- Every controller outside licensing byte-identical
- Every model byte-identical
- Every seeder byte-identical
- No dependency change (`composer.json`, `package.json` untouched)
- `bootstrap/app.php` byte-identical (Phase 12.3 wiring intact)
- `routes/web.php` byte-identical (Phase 12.3 routes intact)
- All 3 React License pages byte-identical
- All Phase 12.2 / v12.2.1 / v12.2.2 / v12.2.3 files byte-identical

## Sign-off

**Engineer 1**

- Name: _________________________
- Date: _________________________
- SHA verified: [ ] tar.gz  [ ] zip
- Grep-confirmed no real private keys: [ ]
- `php artisan test --filter=Phase12_3License` pass count: _______ / 34
- Security scenarios (§12.3.1.f3–f5 + §12.3.1.d8) all pass: [ ]

**Engineer 2**

- Name: _________________________
- Date: _________________________
- SHA verified: [ ] tar.gz  [ ] zip
- Full test suite: _______ / 1,590
- End-to-end fingerprintless-token rejection tested (checklist §17): [ ]
