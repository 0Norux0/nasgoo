# Phase 10 v10.4 — Runtime Results

Per dev §L: "Do not claim a command passed unless it was executed."

## Commands the dev §L required vs my sandbox capability

| Command | Status | Reason |
|---|---|---|
| `composer install` | ✗ not executed | sandbox has no network — composer can't reach packagist |
| `npm ci` | ✗ not executed | sandbox has no network |
| `php artisan optimize:clear` | ✗ not executed | sandbox has no PHP runtime |
| `php artisan migrate:fresh --seed` | ✗ not executed | no PHP + no MySQL |
| `php artisan route:list` | ✗ not executed | no PHP |
| `php artisan test` | ✗ not executed | no PHP |
| `npm run typecheck` | ✗ not executed | no node |
| `npm run build` | ✗ not executed | no node |

## What I CAN run + did run

| Command / check | Result |
|---|---|
| `tar -xzf` extract archive | ✓ v10.3 baseline extracted cleanly |
| `find` for nested project / VERSION files | ✓ exactly 1 of each; no duplicate |
| `grep` static fix-marker scans | ✓ all 19 markers detected |
| `python3` brace-balance check | ✓ all v10.4-touched files balanced |
| `python3` Pest helper uniqueness | ✓ 52 unique global helpers, 0 duplicates |
| `python3` YAML validation | ✓ CI YAML parses |
| `sha256sum` on every critical file | ✓ — see PACKAGE_INTEGRITY |
| `tar -tzvf` to check archive contents | ✓ — see PACKAGE_INTEGRITY |
| Python simulation of `verify-fixes` | ✓ 19/19 markers present in archive |
| Python simulation of `fingerprint` aggregate | ✓ canonical SHA recorded |

## Per §O final clause

Phase 10 v10.4 is implemented but requires developer runtime verification.

## What the dev must execute

```bash
cd /var/www/marketplace
sha256sum -c marketplace-phase-10-v10.4.tar.gz.sha256          # archive integrity
tar -xzf marketplace-phase-10-v10.4.tar.gz --strip-components=1 --overwrite
./scripts/deploy.sh                                             # full pipeline + cache invalidation
php artisan marketplace:fingerprint                             # cryptographic deployment verification
php artisan marketplace:verify-fixes                            # all 19 ✓
php artisan route:list | grep -E 'reports|sitemap|vendor-files' # routes registered
php artisan test --filter='Phase10'                              # all v10.0+v10.1+v10.2+v10.3+v10.4 Pest scenarios
```

Then walk through `PHASE_10_v10.4_DEVELOPER_CHECKLIST.md` for the browser tests.

## Honest claims (with evidence)

- The v10.4 archive contains the same fix code that v10.3 shipped (same SHA-256 for files unchanged in v10.4)
- The v10.4 fingerprint is `14c5d36d3e01b4236411b668e4fc17645c906cb079ab04dc7d0cd37caf71bf35`
- The CI sub-check counts: 24 Phase 10 sub-checks total (6 v10.0 + 7 v10.1 + 5 v10.2 + 5 v10.3 + 4 v10.4); 80 phase-specific total
- 52 unique Pest helpers, 0 duplicates
