# Phase 12.2 v12.2.3 — Patch Notes

Concise change log. Full rationale in `PHASE_12_2_3_FINAL_ADMIN_LINT_FORMAT_REPAIR_REPORT.md`.

## What changed

### Frontend (1 file)

- **`resources/js/Pages/Admin/SiteSettings/Index.tsx`** — the ESLint `no-explicit-any` warning at line 122 fixed at the code level:
  - Introduced `type JsonPrimitive = string | number | boolean | null`
  - Introduced `type JsonValue = JsonPrimitive | JsonValue[] | { [k: string]: JsonValue }` (recursive, matches actual server-side JSON shape)
  - Introduced `type SiteSettingsFormData = Record<string, JsonValue>`
  - Updated the whole type chain to use `SiteSettingsFormData`: `Props.settings`, `SectionsRegistry[...].default_settings`, `GroupEditorProps.values`
  - Updated `FieldEditorProps.value: JsonValue` and `onChange: (v: JsonValue) => void` (was `unknown`)
  - Changed `useForm<Record<string, any>>(values)` → `useForm<SiteSettingsFormData>(values)`
  - Narrowed `Object.entries(data)` result to `[string, JsonValue][]` (needed since TS can't preserve the generic through `Object.entries`)

Net: no more `Record<string, any>`, no more `unknown` in the useForm chain, no `as never` casts, no ESLint suppression.

### VERSION

- `Phase 12.2 v12.2.2 Final Lint and Format Repair` → `Phase 12.2 v12.2.3 Final Admin Login, Lint and Format Repair`

### Documentation added

- `PHASE_12_2_3_FINAL_ADMIN_LINT_FORMAT_REPAIR_REPORT.md`
- `PHASE_12_2_3_PATCH_NOTES.md` (this file)
- `PHASE_12_2_3_DEVELOPER_CHECKLIST.md`
- `PHASE_12_2_3_ROLLBACK.md`
- `PHASE_12_2_3_PACKAGE_INTEGRITY.md`

## What did NOT change

- No PHP file touched
- No new features
- No business logic
- No migrations, seeders, routes, config, or dependency changes
- No `.env.example.production` change
- No `scripts/deploy-production-phase12.sh` change (Filament optimize command recommended in the report but NOT applied — per directive rules about not disturbing approved deploy scripts)
- All 19 Phase 12.2 documents preserved
- All 5 v12.2.1 documents preserved
- All 5 v12.2.2 documents preserved
- All 7 Phase 12 database preparation documents preserved
- Vendor intelligence, personalization, checkout, cart, orders, admin, vendor, customer code unchanged
- Test count unchanged: 106 files, 1,556 `it()` scenarios

## Phase 12.3 (license activation)

**NOT included** per directive rule "Do not begin Phase 12.3 license activation yet." This is a pure v12.2.3 repair on top of v12.2.2. When the developer approves this repair, Phase 12.3 can be delivered separately.

## Bug counts

| Category | Reported | Fixed in v12.2.3 | Deferred |
| --- | ---: | ---: | ---: |
| ESLint `no-explicit-any` warning (line 122) | 1 | 1 | 0 |
| `/admin/login` cold boot (~39 s) | 1 | 0 (env issue, not code) | Documented + mitigations listed |
| Prettier `format:check` failures | 50 | 0 (sandbox cannot run Prettier) | 50 — dev runs `npm run format` |

## Verification the developer must run

Minimum sanity pass:

```bash
cat VERSION                              # → Phase 12.2 v12.2.3 Final Admin Login, Lint and Format Repair
composer dump-autoload -o
php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache
php artisan route:list | wc -l
php artisan migrate:status
npm install
npm run format                           # ← REQUIRED for Prettier
npm run format:check
npm run lint                             # ← 0 warnings, 0 errors (the v12.2.3 target)
npm run typecheck
npm run build
```

Then test `/admin/login` first load and second load — record the timings.

Full checklist in `PHASE_12_2_3_DEVELOPER_CHECKLIST.md`.

## Rollback

Tier 1 (safe): revert to v12.2.2 archive. Single file change; no DB migration. See `PHASE_12_2_3_ROLLBACK.md`.
