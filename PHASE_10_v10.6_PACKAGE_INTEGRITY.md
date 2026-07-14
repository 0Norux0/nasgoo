# Phase 10 v10.6 — Package Integrity Report

Per dev §7: forensic proof that the shipped archive contains the actual repaired files.

## Workspace forensics

- Project root: `/home/claude/marketplace`
- Single VERSION file: ✓
- VERSION content: `Phase 10 v10.6`
- No nested duplicate project: ✓ (find . -maxdepth 3 -type d -name marketplace returns nothing inside the project)
- Laravel ^11.0, Filament ^3.2 (verified from composer.json)

## Archive SHA-256 — sidecar files

```
marketplace-phase-10-v10.6.tar.gz
marketplace-phase-10-v10.6.tar.gz.sha256

marketplace-phase-10-v10.6.zip
marketplace-phase-10-v10.6.zip.sha256
```

Verify with:
```bash
sha256sum -c marketplace-phase-10-v10.6.tar.gz.sha256
# Expected: marketplace-phase-10-v10.6.tar.gz: OK
```

## File-level SHA-256 (filled in at build time below)

## File-level SHA-256 — workspace (canonical)

Computed from `/home/claude/marketplace` at packaging time. Each entry will be re-verified against the extracted archive in the §7 forensic check below.

| # | File | SHA-256 (workspace) |
|---|---|---|
| 1 | `VERSION` | `f34db8c4decfc492ba94fd711af151c38f9de83fc1ef746e0e725b42c46839d4` |
| 2 | `config/filesystems.php` | `6367cf7f85acf55ce471ed47a42b96944749b5fb13a12cef3826c825d8fd646f` |
| 3 | `config/marketplace.php` | `907ce46bb4fc38fadf215b54b3a112bcd93bd9171820a0bdcb6656a33c0f0e2c` |
| 4 | `app/Http/Middleware/HandleInertiaRequests.php` | `c3d89f7cc477b86801950a092f24c3c139868c892b97daeb8c56a4e04d81a46e` |
| 5 | `resources/js/Pages/Vendor/Orders/Show.tsx` | `8b14d802492c87e52d80905148c3baf94259ceb2c2d3b7f106e30864fd0df72c` |
| 6 | `resources/js/Layouts/StorefrontLayout.tsx` | `f17a4b6ebaea58dada5dab70e2c7a822a0a3c405e587fd822c00a6d0fb858e94` |
| 7 | `resources/js/Pages/Catalog/Index.tsx` | `3a3bc624970947ca6d7e6ae4671a8dbaf865e44103286086e23e58ee6067e7fa` |
| 8 | `resources/js/types/inertia.d.ts` | `01e445659ba8ee42ea54596dd01849823983c720f84484c0d5483b163cac63d4` |
| 9 | `.github/workflows/ci.yml` | `bbd29d745479cea476f01698b5389cad4ee44dfd3cbc71e8f20ec946bdc0e436` |
| 10 | `tests/Feature/Phase10V106RegressionTest.php` | `df0959ac462a2f2c2b0615e6d5e8b3a4c0312cb37519de116f27b6aaed75091b` |
