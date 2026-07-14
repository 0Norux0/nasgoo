# Phase 11A v11A.1 — Package Integrity Report

Per dev §17.

## Workspace forensics

- Project root: `/home/claude/marketplace`
- VERSION: `Phase 11A v11A.1`
- 0 PHP files modified vs Phase 11A
- 7 frontend/config/test files NEW or MODIFIED in v11A.1

## Archive SHA-256 — sidecar files

```bash
sha256sum -c marketplace-phase-11A-v11A.1-spacing-fix.tar.gz.sha256
sha256sum -c marketplace-phase-11A-v11A.1-spacing-fix.zip.sha256
```

## File-level SHA-256 of v11A.1-touched files (workspace)

| # | File | SHA-256 (workspace) |
|---|---|---|
| 1 | `VERSION` | `5b292d1eef4611541668b4b11c039a2bbaca93858e353d8d02df2cfd9b111b65` |
| 2 | `resources/css/app.css` | `de8a6ffc8a5009cb2feb1c6d60d37daeac5b7eabf865344e60aa8e787d66da09` |
| 3 | `resources/js/Components/ui/v11/Container.tsx` | `8f907aa1343fff998de2e1b223c5883b04414fce5a441b87c7d4c02fe61d5a4c` |
| 4 | `resources/js/Pages/Welcome.tsx` | `6460ae8c9be72453facd02031fd81593ec69d8b375eec05e1f773d9c50cc7b43` |
| 5 | `resources/js/Layouts/StorefrontLayout.tsx` | `a15f5b702e72b1475403e4af00c6423e4e2e695d3cd21edbf4e505b546c9648d` |
| 6 | `tests/Feature/Phase11AV1Hot1RegressionTest.php` | `a19a673b5030752546ae19cd0f2b245e5568044c40be657221018cad1162ac2a` |
| 7 | `.github/workflows/ci.yml` | `9c11b9c0fa1e5e80a247dacc121b114782830e287bb44b66c854b686734008f6` |

## v11A baseline files untouched in v11A.1

These v11A NEW files (Button, primitives, ProductCard) and config (tailwind.config.js) are SHA-IDENTICAL between v11A and v11A.1:
- `resources/js/Components/ui/v11/Button.tsx`
- `resources/js/Components/ui/v11/primitives.tsx`
- `resources/js/Components/ui/v11/ProductCard.tsx`
- `tailwind.config.js`
- `tests/Feature/Phase11ARegressionTest.php`

## v10.0-v10.16 PHP files untouched

All Phase 10 PHP files remain SHA-identical to the final-approved baseline. v11A.1 made zero PHP changes (verified by source diff and the v11A.1 Pest regression suite which checks for v10.x marker preservation).

## Leak check

The archive must NOT contain:
- `marketplace/MARKETPLACE_PLATFORM_PLAN.md`
- `marketplace/node_modules`
- `marketplace/vendor`
- `marketplace/.git`
- `marketplace/tsconfig.verify.json`

## §17 Extract-verify (against shipped archive)

After build:
1. Extract into `/tmp/v11ah1/` — clean
2. `VERSION` in archive = `Phase 11A v11A.1`
3. Container.tsx in archive with mx-auto/max-w-7xl/px-4/sm:px-6/lg:px-8/xl:px-10
4. Welcome.tsx: 0 container-app references, 9 <Container opens
5. StorefrontLayout.tsx: 0 container-app references, 3 <Container opens
6. CSS .container-app definition includes xl:px-10
7. All 7 v11A.1-touched files SHA-identical workspace ↔ archive
8. All v11A markers (7 homepage sections + StorefrontLayout) intact
9. All v10.0-v10.16 markers intact (no PHP modified)
10. CI YAML valid with 4 new v11A.1 sub-checks
11. No node_modules / vendor / .git / tsconfig.verify in archive
12. No nested duplicate project
