# Phase 12.2 v12.2.2 — Patch Notes

Concise change log. Full rationale in `PHASE_12_2_2_FINAL_LINT_FORMAT_REPAIR_REPORT.md`.

## What changed

### Frontend (1 file)

- **`resources/js/Pages/Admin/SiteSettings/Index.tsx`** — the ESLint warning at line 117 was addressed at the code level (not by suppressing the rule):
  - Line 122 (was 117): added explicit generic `useForm<Record<string, unknown>>(values)` — eliminates the type-inference ambiguity that was the root cause
  - Line 135 (was 130): removed now-unnecessary `as Record<string, unknown>` cast on `data`
  - Line 141 (was 136): removed both `as never` casts on `setData(key, v)` and wrapped in a block so the return value is explicitly discarded

Net effect: one code-quality warning eliminated + two `as never` code smells removed.

### VERSION

- `Phase 12.2 v12.2.1 Quality Gate Repair` → `Phase 12.2 v12.2.2 Final Lint and Format Repair`

### Documentation added

- `PHASE_12_2_2_FINAL_LINT_FORMAT_REPAIR_REPORT.md`
- `PHASE_12_2_2_PATCH_NOTES.md` (this file)
- `PHASE_12_2_2_DEVELOPER_CHECKLIST.md`
- `PHASE_12_2_2_ROLLBACK.md`
- `PHASE_12_2_2_PACKAGE_INTEGRITY.md`

## What did NOT change

- No PHP files touched — the v12.2.1 parse-error fix in `app/Services/Personalization/CustomerAffinityService.php` remains intact
- No new features
- No business logic changes
- No migrations, seeders, routes, or config edits
- No new dependencies
- No changes to `.env.example.production`, `scripts/deploy-production-phase12.sh`, `scripts/deploy.sh`, or any Phase 12.2 launch-readiness document
- All 19 Phase 12.2 documents preserved
- All 5 v12.2.1 documents preserved
- All Phase 12 database preparation documents preserved
- All 77 migrations, 13 seeders, 106 test files unchanged

## Sandbox constraint (important context)

Prettier cannot run in this sandbox. `npm install` gets HTTP 403 from every registry and no cached prettier binary exists on disk. The safe Prettier subset (trailing whitespace, EOF newline, LF-only) was applied — but that touched 0 files because the codebase was already clean on those axes.

The ~50 files failing `format:check` are drifting on transformations that require Prettier itself (line reflow at 100 chars, JSX attribute wrapping, Tailwind class ordering). Those cannot be safely done without Prettier.

**Consequence**: after extracting this archive, the developer must run:

```bash
npm install
npm run format          # ← REQUIRED to fix the 50 files
npm run format:check    # → passes
```

This is explicit and non-negotiable — there is no path to `format:check` passing without running `npm run format` locally.

## Bug counts

| Category | Reported | Fixed in v12.2.2 | Deferred to `npm run format` on dev side |
| --- | ---: | ---: | ---: |
| ESLint warnings (line 117) | 1 | 1 | 0 |
| Prettier `format:check` failures | 50 | 0 | 50 |

## Verification the developer must run

Minimum sanity pass:

```bash
cat VERSION                       # → Phase 12.2 v12.2.2 Final Lint and Format Repair
npm install                       # populates node_modules
npm run format                    # ← REQUIRED for Prettier
npm run format:check              # → passes
npm run lint                      # → 0 warnings, 0 errors (the v12.2.2 target)
npm run typecheck                 # → passes
npm run build                     # → succeeds
php artisan optimize:clear && php artisan route:list && php artisan migrate:status
```

Full checklist in `PHASE_12_2_2_DEVELOPER_CHECKLIST.md`.

## Rollback

Tier 1 (safe): revert to v12.2.1 archive and redeploy. No database changes to roll back. See `PHASE_12_2_2_ROLLBACK.md`.
