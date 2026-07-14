# Phase 10 v10.11 — Package Integrity Report

Per dev §8.

## Workspace forensics

- Project root: `/home/claude/marketplace`
- Single VERSION file
- VERSION content: `Phase 10 v10.11`
- No nested duplicate project
- Laravel ^11.0, Filament ^3.2 (composer.json)

## Archive SHA-256 — sidecar files

```
marketplace-phase-10-v10.11.tar.gz
marketplace-phase-10-v10.11.tar.gz.sha256

marketplace-phase-10-v10.11.zip
marketplace-phase-10-v10.11.zip.sha256
```

Verify on the dev's host:
```bash
sha256sum -c marketplace-phase-10-v10.11.tar.gz.sha256
```

## File-level SHA-256 of v10.11-touched files

| # | File | SHA-256 (workspace) |
|---|---|---|
| 1 | `VERSION` | `fdf761a0783939be43789dfcdd9a6f234a78777e6abbf3b8140a5bc26361457f` |
| 2 | `app/Domain/Reports/ReportsService.php` | `3b915854648c9a8b1cfaaf2bfa32d2a9152e619b4dda7512d46b11820e673d23` |
| 3 | `app/Http/Controllers/Vendor/VendorOrderController.php` | `e154832e260c86fd6c07c37a3d457e9e71889f1b80984496febf4b3cdad2ed9c` |
| 4 | `resources/js/Pages/Vendor/Orders/Show.tsx` | `7970fab1b1e4f0729bdd3fc553e6029dbae1aff2a5fe280fbcd2ff45b1cea37f` |
| 5 | `app/Filament/Resources/SupportTicketResource/Pages/ViewSupportTicket.php` | `0fe7d2cce4f87d375a32ff9eb55c25e6952db0593052d4be8eb6a2bdbd975b7f` |
| 6 | `app/Http/Controllers/SupportTicketController.php` | `bff5d9d13d504dea2e731bdea477bafe3d80916b4caa5706dff70a3d2c4e318c` |
| 7 | `app/Http/Controllers/Vendor/VendorSupportTicketController.php` | `3e6d98c9bfad2a3a16eadf9fbcdcf12ac0eceea9e03f28782fc7083b63aea8d3` |
| 8 | `app/Http/Middleware/HandleInertiaRequests.php` | `55d1ee3191358df8ac19c5aca3f673b30a7f3f732b678d3ae96759b22631f4a0` |
| 9 | `tests/Feature/Phase10V1011RegressionTest.php` | `1859246ed105cacc231797994e76749eb88c29d4b73369cdff1abade5ad887f5` |
| 10 | `.github/workflows/ci.yml` | `a0f41bd11e4f5c0af010bf52d3c6e4f352d25bd283f1d7ccd791eda827b7c9ce` |

## Leak check

The packaging command excludes:
- `marketplace/MARKETPLACE_PLATFORM_PLAN.md` (working notes; not for delivery)
- `marketplace/node_modules` (sandbox stubs only — the dev installs in their own env)
- `marketplace/vendor` (composer artifacts — the dev runs composer install)
- `marketplace/.git` (sandbox git data)
- `marketplace/tsconfig.verify.json` (sandbox-only stub config for tsc verification)

## §8 extract-verify (performed against the shipped archive)

1. Extract into `/tmp/v1011v` — clean extraction
2. `VERSION` in archive = `Phase 10 v10.11`
3. **§5**: `ReportsService::adminPayoutSummary` uses `SUM(requested_amount_minor)` in archive (2 sites: admin + per-vendor)
4. **§5**: `SUM(amount_minor)` regression pattern ABSENT from archive
5. **§3**: `VendorOrderController::computeStatusOptions` method defined in archive
6. **§3**: `'status_options'` prop included in show() response in archive
7. **§3**: `Show.tsx` reads `status_options` prop in archive
8. **§3**: `order.fulfillment_status === 'shipped'` regression pattern ABSENT from archive
9. **§4**: Filament admin support ticket page has ≥4 defensive `$record->load(['messages.user:id,name,email'])` calls in archive (one per mutating action)
10. **§4**: customer reply controller uses explicit `redirect("/tickets/{$ticket->id}")` in archive
11. **§4**: vendor reply controller uses explicit `redirect("/vendor/tickets/{$ticket->id}")` in archive
12. **§2**: `HandleInertiaRequests` no longer calls `getAllPermissions()->pluck` in default share (the only `getAllPermissions` reference is in the explanatory comment)
13. **§2**: `auth.user.is_admin` still shared (regression guard)
14. Pest test file `Phase10V1011RegressionTest.php` present (17 scenarios)
15. CI YAML valid + 5 new v10.11 sub-checks
16. SHA-256 of all v10.11-touched files match between workspace and archive
17. No node_modules / vendor / .git / tsconfig.verify in archive
18. No nested duplicate project folder
