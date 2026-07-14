# Phase 12.2 v12.2.1 — Patch Notes

Concise change log. Full rationale in `PHASE_12_2_1_QUALITY_GATE_REPAIR_REPORT.md`.

## What changed

### PHP (1 file)

- **`app/Services/Personalization/CustomerAffinityService.php`** — fixed PHP 8.5 `syntax error, unexpected token '&'`. The invalid `match => &$var` reference pattern (arms of a match expression cannot return references — only array literals can) was replaced with an equivalent `if / elseif` chain that updates the same accumulators. Semantically identical.

### Frontend (8 files)

- **`resources/js/Pages/Admin/Reports/Index.tsx`** — 4 raw apostrophes in a `<code>` shell-command example wrapped in `{"..."}` JS-string expression
- **`resources/js/Pages/Bookings/Confirmation.tsx`** — `You'll` → `You&apos;ll`
- **`resources/js/Pages/Checkout/Show.tsx`** — `don't` → `don&apos;t`, `we'll` → `we&apos;ll`
- **`resources/js/Pages/Orders/Confirm.tsx`** — `You'll` → `You&apos;ll`
- **`resources/js/Pages/Services/Show.tsx`** — `slotsByDate` wrapped in `useMemo` for stable reference across renders (fixes hook-dep warning)
- **`resources/js/Components/Customization/CustomizationForm.tsx`** — `children: any` → `children: ReactNode` + type import
- **`resources/js/Pages/Vendor/Supplier/Products/Manual.tsx`** — `children: any` → `ReactNode`; `as any` → `as typeof data.supplier_stock_status`
- **`resources/js/Pages/Vendor/Supplier/Products/Map.tsx`** — `as any` → `as typeof data.fulfillment_mode` on fulfillment mode; `as any` → `Number()` coercion on estimated_delivery_days
- **`resources/js/Pages/Vendor/Supplier/Integrations/Index.tsx`** — `as any` → `as typeof data.integration_type`

### VERSION

- `Phase 12.2 Production Launch Readiness` → `Phase 12.2 v12.2.1 Quality Gate Repair`

### Documentation added

- `PHASE_12_2_1_QUALITY_GATE_REPAIR_REPORT.md`
- `PHASE_12_2_1_PATCH_NOTES.md` (this file)
- `PHASE_12_2_1_DEVELOPER_CHECKLIST.md`
- `PHASE_12_2_1_ROLLBACK.md`
- `PHASE_12_2_1_PACKAGE_INTEGRITY.md`

## What did NOT change

- No new features
- No business logic (the PHP fix preserves the same three-accumulator update pattern; only the branching syntax changed)
- No migrations added, removed, or edited
- No routes added, removed, or edited
- No seeders touched
- No config files touched
- No `composer.json` / `package.json` / lockfile changes
- No `.env.example` / `.env.example.production` changes
- No deploy script changes (`scripts/deploy-production-phase12.sh` untouched; `scripts/deploy.sh` LEGACY banner + APP_ENV=production refuse-gate preserved)
- The 19 Phase 12.2 documents from the prior delivery all still ship in this archive unchanged
- All Vendor Intelligence work (Phase 11B.4.2 + 11B.4.3) preserved
- All Phase 12 database preparation work (v12 + v12.1) preserved

## Bug counts

| Category | Reported | Fixed | Notes |
| --- | ---: | ---: | --- |
| PHP ParseError | 1 | 1 | Located in `CustomerAffinityService.php` |
| ESLint unescaped-entity errors | 8 | 8 | Distributed 4/1/2/1 as reported |
| ESLint `any` warnings | 2 | 6 | 2 reported + 4 additional found in `Vendor/Supplier/*` — all fixed for `--max-warnings 0` compliance |
| React hook dep warning | 1 | 1 | `slotsByDate` in `Services/Show.tsx` |
| Prettier format failures | 51 | 0* | *Sandbox limitation — developer must run `npm run format` locally. Fully documented in the main report. |
| PsySH history file | 1 | 0 | Documented as local env issue per directive §9; not classified as a marketplace defect |

## Verification the developer must run

Full list in `PHASE_12_2_1_DEVELOPER_CHECKLIST.md`. Minimum sanity pass:

```bash
cat VERSION                                              # → Phase 12.2 v12.2.1 Quality Gate Repair
php -l app/Services/Personalization/CustomerAffinityService.php   # → No syntax errors
composer dump-autoload && php artisan route:list         # → boots without ParseError
npm install && npm run format && npm run lint            # → clean
npm run typecheck && npm run build                       # → clean
```

## Rollback

Tier 1 (safe): revert to v12.2 archive and redeploy. See `PHASE_12_2_1_ROLLBACK.md`. No database changes to roll back — everything in this release is code-only.
