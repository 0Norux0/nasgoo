# Phase 10 v10.3 — Runtime Results

Per dev §9: I must "state clearly" what I can and cannot execute. This is the honest report.

## Sandbox capabilities

| Tool | Available? | Used? |
|---|---|---|
| `tar` extract/create | ✓ | ✓ extracted v10.2 baseline + built v10.3 archive |
| `grep` / `sed` / `awk` source scans | ✓ | ✓ every fix marker verified |
| `python3` for YAML parse + brace balance + verify-fixes simulation | ✓ | ✓ all green |
| `md5sum` / `sha256sum` checksums | ✓ | ✓ — see `PHASE_10_v10.3_PACKAGE_INTEGRITY.md` |
| `php` runtime | ✗ | — |
| `composer` | ✗ (no network) | — |
| `npm` / `node` | ✗ (no network) | — |
| MySQL / Redis | ✗ | — |
| Browser / Playwright | ✗ | — |

## Commands the dev §9 asked me to run

| Command | Status in my sandbox |
|---|---|
| `composer install` | ✗ blocked (no network) |
| `npm ci` | ✗ blocked (no network) |
| `php artisan optimize:clear` | ✗ blocked (no PHP) |
| `php artisan migrate:fresh --seed` | ✗ blocked (no PHP, no MySQL) |
| `php artisan test` | ✗ blocked |
| `npm run typecheck` | ✗ blocked |
| `npm run build` | ✗ blocked |

**Per §9: I do NOT endorse this package as runtime-verified.** All 7 commands above MUST be run by the developer or CI before the marketplace is considered ready.

## What I CAN claim with evidence

### Static — every v10.3 fix marker detected in source

Python simulation of `marketplace:verify-fixes` against the working tree:

```
✓ #4 v10.1 unset images
✓ #6 AdminLayout
✓ #6 Filament Reports nav
✓ #7 vendor-nav-reports
✓ #5 row-{confirm,ship,deliver}
✓ #2/#3/#9 VendorFileLinks
✓ #3 requested_package
✓ #8 sitemap
✓ #10 storefront mobile
✓ #10 vendor mobile
✓ #1 translations cached
✓ #1 perf indexes
✓ v10.2 Reports in baseItems
✓ v10.2 hasAnyRole
✓ v10.2 version banner
✓ v10.3 disableLabel removed
✓ v10.3 Product::fill guard
✓ v10.3 status dropdown
✓ v10.3 global overflow guards

✅ All 19 detected
```

### Structural — no regression

- CI YAML parses (Python `yaml.safe_load`).
- All 5 v10.3-touched files brace-balance.
- 51 unique global Pest helpers, 0 duplicates (v8.5 invariant).
- Phase 10 sub-checks: 23 (target: 22 = 6 v10.0 + 7 v10.1 + 5 v10.2 + 4 v10.3; +1 extra because the v10.3 Pest runner itself counts toward the prefix grep).
- Every v1-v10.2 file modified by v10.3: counted via diff against v10.2 baseline = 5 files.

### Provenance — workspace ↔ archive ↔ what dev receives

| File | working-tree SHA-256 | shipped-archive SHA-256 | Match? |
|---|---|---|---|
| `VERSION` | (see PHASE_10_v10.3_PACKAGE_INTEGRITY.md) | (same doc) | ✓ |
| `app/Models/Product.php` | ✓ | ✓ | ✓ |
| `app/Filament/Resources/VendorResource.php` | ✓ | ✓ | ✓ |
| `app/Console/Commands/VerifyFixesCommand.php` | ✓ | ✓ | ✓ |
| `resources/js/Pages/Vendor/Orders/Show.tsx` | ✓ | ✓ | ✓ |
| `resources/css/app.css` | ✓ | ✓ | ✓ |
| `tests/Feature/Phase10V103RegressionTest.php` | ✓ | ✓ | ✓ |
| `.github/workflows/ci.yml` | ✓ | ✓ | ✓ |
| `scripts/deploy.sh` | ✓ | ✓ | ✓ |

## What I REFUSE to claim

- I have NOT run `php artisan test` against the v10.3 working tree.
- I have NOT run `npm run build` so I cannot guarantee the Vite bundle compiles cleanly.
- I have NOT visually verified the dropdown renders correctly in a browser.
- I have NOT verified the mobile overflow guards visually at 375px.

The above are facts about my sandbox, not facts about the code. The code itself contains the documented fixes and the tests cover them. **The dev's environment is the authoritative source for runtime status.**

## What the dev must do

```bash
cd /var/www/marketplace
tar -xzf /path/to/marketplace-phase-10-v10.3.tar.gz --strip-components=1 --overwrite
./scripts/deploy.sh
```

After deploy.sh exits 0:
- `php artisan marketplace:verify-fixes` → 19 ✓
- Visit storefront → footer shows `· v Phase 10 v10.3`
- Walk through `PHASE_10_v10.3_DEVELOPER_CHECKLIST.md`
