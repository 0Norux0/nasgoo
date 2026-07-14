# Phase 11A v11A.2 — Package Integrity Report

Per dev §16 + §18.

## Workspace forensics

- Project root: `/home/claude/marketplace`
- VERSION: `Phase 11A v11A.2`
- 0 PHP files modified vs Phase 11A v11A.1
- 5 frontend/config files modified, 1 NEW, 1 NEW test file, 1 OBSOLETE file removed

## Files INSIDE the archive (v11A.2-touched)

| # | File | SHA-256 (workspace) |
|---|---|---|
| 1 | `VERSION` | `fac19bd5df2003a9e76e2235ffd4a7173065dc0203ab2403290a496c5f1cceee` |
| 2 | `tailwind.config.js` | `891fd276de41028c5747f0488e3073c572fe47c51c25bc0ee69007b2d5caf6ac` |
| 3 | `resources/js/Components/Layout/Container.tsx` | `383d83e9270b36f69be8e3fea97e83ba8e10cc1ec48e9aa07b5da5d58e72a58f` |
| 4 | `resources/js/Pages/Welcome.tsx` | `b05854df0125769747ac26c399a24a26fe4c551184cf2b62fe7bb6db19e77a81` |
| 5 | `resources/js/Layouts/StorefrontLayout.tsx` | `42910e4e2f150d187f67e8b9f9953f9668db4935c293beb5f3a889f58b4b9bd1` |
| 6 | `tests/Feature/Phase11AV1Hot2RegressionTest.php` | `0eee37877b30c8c77f2ed8c0999d9e3194fe2c17e070a33068e255ec755e0ba9` |
| 7 | `.github/workflows/ci.yml` | `ae03b20ee24b864376c20e11d7ac74d90abfe6b9f57158a1c7f64c0a994e9ed6` |

## Files DELETED in v11A.2 (must NOT appear in archive)

- `resources/js/Components/ui/v11/Container.tsx` — obsolete v11A.1 location with the dynamic-class-construction root cause

## Files SHA-IDENTICAL to v11A.1 (no regression)

All v11A primitives untouched in v11A.2:
- `resources/js/Components/ui/v11/Button.tsx`
- `resources/js/Components/ui/v11/primitives.tsx`
- `resources/js/Components/ui/v11/ProductCard.tsx`
- `resources/css/app.css` (unchanged — `.container-app` legacy CSS class kept from v11A.1)
- `tests/Feature/Phase11ARegressionTest.php`
- `tests/Feature/Phase11AV1Hot1RegressionTest.php`

## All v10.0-v10.16 PHP files untouched (zero PHP changes)

Verified by source diff and the v11A.2 Pest regression suite.

## §16 Extract-verify procedure

After build:
1. Extract into `/tmp/v11ah2/` — clean
2. `VERSION` in archive = `Phase 11A v11A.2`
3. Canonical Container at `resources/js/Components/Layout/Container.tsx` PRESENT
4. Obsolete `resources/js/Components/ui/v11/Container.tsx` ABSENT (deleted, no zombie)
5. Container has the dev §4 literal class string + default export + NO template interpolation
6. tailwind.config.js has safelist with all 7 critical container classes
7. Welcome.tsx imports from canonical path (default import)
8. StorefrontLayout.tsx imports from canonical path (default import)
9. Welcome.tsx: 9 `<Container>` uses, 0 container-app references
10. StorefrontLayout.tsx: 3 `<Container>` uses, 0 container-app references
11. 7/7 v11A.2-touched files SHA-identical between workspace and archive
12. All Phase 10 PHP files SHA-identical to Phase 10 final-approved (no PHP modified)
13. CI YAML valid with 5 new v11A.2 sub-checks
14. No node_modules / vendor / .git / tsconfig.verify in archive
15. No nested duplicate project folder
