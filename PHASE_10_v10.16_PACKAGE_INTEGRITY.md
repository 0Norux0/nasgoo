# Phase 10 v10.16 — Package Integrity Report

Per dev §15.

## Workspace forensics

- Project root: `/home/claude/marketplace`
- VERSION: `Phase 10 v10.16`
- No nested duplicate project
- Laravel ^11.0, Inertia ^2.0, React 18

## Archive SHA-256 — sidecar files

```
marketplace-phase-10-v10.16.tar.gz
marketplace-phase-10-v10.16.tar.gz.sha256
marketplace-phase-10-v10.16.zip
marketplace-phase-10-v10.16.zip.sha256
```

Verify:
```bash
sha256sum -c marketplace-phase-10-v10.16.tar.gz.sha256
```

## File-level SHA-256 of v10.16-touched files

| # | File | SHA-256 (workspace) |
|---|---|---|
| 1 | `VERSION` | `b0a3c1da4fec84cdd895698a9abd0e2be3366cae8483a27bf3f1db5755bd1acf` |
| 2 | `resources/js/Pages/Welcome.tsx` | `d46bf8594a64b6a8d23f67559eea319cee2f1d62ce311dafeb0589b4734297bf` |
| 3 | `resources/js/types/inertia.d.ts` | `739c3454e6b506df422a8428653055ad8ea489de00967f4277a3ee6ef0ad9808` |
| 4 | `tests/Feature/Phase10V1016RegressionTest.php` | `03d455ff3e55bfc0a11f0e57f34022ac6c033de5bda59bc9078b3cf9d9991cb6` |
| 5 | `.github/workflows/ci.yml` | `d910673ca9737b647aead0aa04e34935a9434f876aed02f82f72295d623ebd70` |

## Leak check

Excludes from archive:
- `marketplace/MARKETPLACE_PLATFORM_PLAN.md`
- `marketplace/node_modules`
- `marketplace/vendor`
- `marketplace/.git`
- `marketplace/tsconfig.verify.json`

## §15 extract-verify (performed against the shipped archive)

1. Extract into `/tmp/v1016v` — clean extraction
2. `VERSION` in archive = `Phase 10 v10.16`
3. **Welcome.tsx** in archive:
   - ZERO occurrences of `user.permissions.<method>` (length/map/filter/forEach/reduce)
   - ONE occurrence of `user.permissions ?? []` (the safe normalization)
   - "Phase 10 v10.16 §4" marker present
4. **inertia.d.ts** in archive:
   - `permissions?: string[]` (optional form)
   - NO required `permissions: string[]` form
5. **HandleInertiaRequests** UNTOUCHED — still has v10.15 defensive markers, v10.14 scope-aware closures, v10.11 §2 perf removal
6. **HomeController** UNTOUCHED — still has v10.15 defensive marker + v10.14 cache key
7. **v10.14 indexes migration** UNTOUCHED — same SHA-256
8. Pest test file `Phase10V1016RegressionTest.php` present (20 scenarios)
9. CI YAML valid + 3 new v10.16 sub-checks
10. SHA-256 of v10.16-touched files match between workspace and archive
11. No node_modules / vendor / .git / tsconfig.verify in archive
12. No nested duplicate project folder
13. **All v10.0-v10.15 preservation markers intact**
