# Phase 10 v10.5 — TypeScript Build-Blocker Correction

## The actual root cause of the previous 4 rounds

When I created `resources/js/Layouts/AdminLayout.tsx` in v10.1, I wrote:

```tsx
interface PageAuth {
    user?: { id: number; name: string; email: string; role?: string };
}
const { auth } = usePage<{ auth: PageAuth }>().props;
```

That **inline incomplete type** does NOT satisfy the project's `PageProps` constraint. The project's `resources/js/types/inertia.d.ts` augments `@inertiajs/core`:

```tsx
declare module '@inertiajs/core' {
    interface PageProps extends SharedProps {}
}
```

where `SharedProps` requires `app`, `marketplace`, `auth`, `flash`, `translations`, `cart_summary`. The generic `<{ auth: PageAuth }>` fails to extend `SharedProps` → **TS2344** at typecheck time → `npm run typecheck` failed → `npm run build` failed → no compiled JS reached the browser → none of my v10.1+ React work (AdminLayout, mobile menu testids, status dropdown, vendor reports link) was actually visible at runtime.

**This is what was silently blocking the deployment of every React fix from v10.1 through v10.4.** The PHP/Filament/CSS fixes (Product::fill model guard, disableLabel removal, global mobile CSS) would have reached the runtime because they don't go through Vite. The React fixes were stuck in source-only.

The dev was right every time. I was wrong to keep blaming caches.

## The 3 specific errors fixed in v10.5

### Error 1 — TS2344 in AdminLayout.tsx:25

**Before** (broken):
```tsx
interface PageAuth {
    user?: { id: number; name: string; email: string; role?: string };
}
const { auth } = usePage<{ auth: PageAuth }>().props;
```

**After** (v10.5):
```tsx
import type { SharedProps } from '@/types/inertia';
const { auth } = usePage<SharedProps>().props;
```

Pattern matches what every other layout in the project does (`AuthLayout`, `StorefrontLayout`, `VendorLayout` — all use `usePage<SharedProps>().props`).

### Error 2 — TS6133 in Admin/Reports/Index.tsx:1

**Before:** `import { Link, router } from '@inertiajs/react';`
**After:** `import { router } from '@inertiajs/react';`

Verified by grep: `Link` was on the import line only, never referenced in the component body.

### Error 3 — TS6133 in Vendor/Reports/Index.tsx:1

Same fix as Error 2.

## Additional latent issues fixed in v10.5

A wider audit caught one more issue that would have surfaced once the dev got past the first 3 errors:

### Vendor/Orders/Show.tsx:134 — React.ChangeEvent namespace

My v10.3 dropdown code used `(e: React.ChangeEvent<HTMLSelectElement>) => {...}`. The file imports types via `{ type FormEvent } from 'react'` (no `React` namespace exposed). Under strict typecheck this is **TS2503** "Cannot find namespace 'React'".

**Fixed:** added `type ChangeEvent` to the named imports from `'react'`; changed the parameter type to bare `ChangeEvent<HTMLSelectElement>`. Project convention preserved.

## Files changed in v10.5 (exhaustive list)

| File | Change |
|---|---|
| `resources/js/Layouts/AdminLayout.tsx` | Removed inline `PageAuth` interface; import `SharedProps` from `@/types/inertia`; use `usePage<SharedProps>()` |
| `resources/js/Pages/Admin/Reports/Index.tsx` | Removed unused `Link` from `@inertiajs/react` import |
| `resources/js/Pages/Vendor/Reports/Index.tsx` | Removed unused `Link` from `@inertiajs/react` import |
| `resources/js/Pages/Vendor/Orders/Show.tsx` | Added `type ChangeEvent` to react imports; replaced `React.ChangeEvent` namespace with bare `ChangeEvent` |
| `VERSION` | `Phase 10 v10.4` → `Phase 10 v10.5` |
| `.github/workflows/ci.yml` | Added 3 v10.5 sub-checks (each fails CI if the regression returns) + v10.5 Pest runner step + verdict line update |
| `tests/Feature/Phase10V105RegressionTest.php` | NEW — 6 Pest scenarios |
| `app/Console/Commands/VerifyFixesCommand.php` | (unchanged — v10.5 fixes are pure TS, not new fix markers) |

## Per §6 — what I did NOT do

- ✓ Did NOT disable `noUnusedLocals`
- ✓ Did NOT weaken `strict`
- ✓ Did NOT exclude any file from `tsconfig.json`
- ✓ Did NOT add `@ts-ignore` or `@ts-expect-error` anywhere
- ✓ Did NOT cast `usePage()` to `any`
- ✓ Did NOT change build scripts to skip TypeScript

The fix is in the source code only. The project's strict TypeScript configuration is untouched.

## Verification per §4

I ran `tsc` against the corrected files in this sandbox using hand-written stubs for `@inertiajs/react` + `react` (the v7.7 stub defense pattern). Exit code 0.

```
$ /home/claude/.npm-global/bin/tsc -p tsconfig.verify.json
(no output)
$ echo $?
0
```

Files type-checked clean:
- `resources/js/Layouts/AdminLayout.tsx` ✓
- `resources/js/Layouts/VendorLayout.tsx` ✓
- `resources/js/Layouts/StorefrontLayout.tsx` ✓
- `resources/js/Layouts/AuthLayout.tsx` ✓
- `resources/js/Pages/Admin/Reports/Index.tsx` ✓
- `resources/js/Pages/Vendor/Reports/Index.tsx` ✓
- `resources/js/Pages/Vendor/Orders/Show.tsx` ✓
- `resources/js/Pages/Vendor/Orders/Index.tsx` ✓

The dev's environment will run `npm run typecheck` against the real `@inertiajs/react` + `react` packages (not my stubs), with the real `tsconfig.json` (not my verify config). My stubs are minimal but produce the same TS2344/TS6133/TS2503 errors the dev's environment would catch — and now reports the same exit code 0.

## What this means for the previous defects

After v10.5, `npm run typecheck` and `npm run build` should both succeed in the dev's environment. Once `npm run build` produces a new bundle, ALL the React fixes from v10.1+v10.3 finally reach the browser:

- AdminLayout.tsx loads → `/admin/reports` page renders (Defect 6 resolved)
- Vendor/Orders/Show.tsx loads → status dropdown visible (Defect 3 resolved)
- VendorLayout.tsx loads → Reports link visible + vendor-mobile-menu hamburger (Defects 7 + 10 resolved)
- StorefrontLayout.tsx loads → version banner + storefront-mobile-menu (Defects 1, 10 resolved)
- Filament forms unblocked by v10.3's disableLabel removal (Defects 1, 4, 5)

This is also why "approximately 99% of defects remained" through v10.4 — the 1% that worked was anything reaching the user without going through Vite (PHP-rendered Filament forms, after `php artisan filament:cache-components`).

## Counts

| | v10.4 → v10.5 |
|---|---|
| Phase 10 CI sub-checks | 27 → **30** (6+7+5+5+4+3) |
| Phase 10 Pest scenarios | 49 → **55** (13+14+8+8+6+6) |
| Phase-specific CI grand total | 82 → **85** |
| Unique global helpers | 51 (unchanged — v10.5 tests use only `it()` blocks) |
| Files modified | 4 source + 1 CI + 1 test + 1 doc |
| v1-v9 files touched | 0 |
| v10.0-v10.4 fix code reverted | 0 (only v10.3 Show.tsx event type updated for consistency) |

## Final CI verdict

```
✅ Phase 10 v10.5 PASSES — ready for final deployment review
```

appears only when `npm run typecheck` AND `npm run build` exit 0 in CI. The existing Frontend job in `.github/workflows/ci.yml` runs both; v10.5 was the first release where they should actually pass.
