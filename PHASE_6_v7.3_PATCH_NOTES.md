# Phase 6 v7.3 — Inertia `usePage<>` SharedProps fix (frontend build hardening)

**Status:** Targeted fix on top of Phase 6 v7.2. Pending CI verification (only true gate).
**Scope:** TypeScript typing only. **No business logic, no schema, no Filament, no controllers, no PHP changes.**

---

## Symptom (what the developer reported after v7.2)

`npm run build` failed with three TS2344 errors:

```
resources/js/Pages/Vendor/Supplier/Integrations/Index.tsx:25:31
  error TS2344: Type 'PageProps' does not satisfy the constraint 'PageProps'.
  Type 'PageProps' is missing the following properties from type 'PageProps':
  app, marketplace, auth, translations, cart_summary

resources/js/Pages/Vendor/Supplier/Orders/Show.tsx:30:31
  error TS2344: Type 'FlashProps' does not satisfy the constraint 'PageProps'.
  …

resources/js/Pages/Vendor/Supplier/Products/Index.tsx:35:31
  error TS2344: Type 'PageProps' does not satisfy the constraint 'PageProps'.
  …
```

---

## Root cause

Three Phase 6 React pages declared **local** `interface PageProps { ... }` (or `type FlashProps = ...`) and passed them as the generic to `usePage<X>()`. Inertia v2 augments `@inertiajs/core`'s `PageProps` to extend the project's `SharedProps` (defined in `resources/js/types/inertia.d.ts`):

```ts
declare module '@inertiajs/core' {
    interface PageProps extends SharedProps {}
}
```

…and `SharedProps` requires `app`, `marketplace`, `auth`, `translations`, `cart_summary`, `flash`. The local `interface PageProps { products: ...; }` didn't include those, so the `usePage<T extends PageProps>()` constraint rejected it.

**Why my v7.2 sandbox sweep missed this:** my offline `tsc` config used `noResolve: true` (because the sandbox has no `node_modules`). With module resolution off, tsc can't read `@inertiajs/core`'s `PageProps` constraint, so the violation was invisible. The existing CI frontend job (which runs the real `npm install && npm run typecheck && npm run build`) WOULD have caught this — but I should have caught it locally first. **This is my mistake; v7.3 includes a fix to my own verification methodology so it won't happen again** (see §5 below).

---

## What v7.3 changes

### 1. The 3 files that broke the build — use `SharedProps`

**`resources/js/Pages/Vendor/Supplier/Integrations/Index.tsx`**

```ts
import type { SharedProps } from '@/types/inertia';

type SupplierIntegrationsPageProps = SharedProps & {
    integrations: Integration[];
    platforms: Platform[];
};

export default function Index() {
    const { props } = usePage<SupplierIntegrationsPageProps>();
    const { integrations, platforms, flash } = props;   // flash comes from SharedProps
}
```

**`resources/js/Pages/Vendor/Supplier/Orders/Show.tsx`** (only needs flash — use `SharedProps` directly)

```ts
import type { SharedProps } from '@/types/inertia';

export default function Show({ so }: { so: SO }) {
    const { props } = usePage<SharedProps>();
    const flash = props.flash ?? {};
}
```

**`resources/js/Pages/Vendor/Supplier/Products/Index.tsx`**

```ts
import type { SharedProps } from '@/types/inertia';

type SupplierProductsPageProps = SharedProps & {
    products: { data: PageProduct[]; links: ... };
    platforms: { id: number; name: string; ... }[];
};

export default function Index() {
    const { props } = usePage<SupplierProductsPageProps>();
}
```

### 2. The 4 files with local `PageProps` that didn't break the build — renamed defensively

`Orders/Index.tsx`, `Products/CsvImport.tsx`, `Products/Manual.tsx`, `Products/Map.tsx` each had a local `interface PageProps { ... }` used only as a function-arg type (not with `usePage<>`). These compiled fine in v7.2 BUT the name `PageProps` shadows the augmented global. v7.3 renames each to a page-specific name to eliminate the future-bug risk:

| File | Old name | New name |
|---|---|---|
| `Orders/Index.tsx` | `interface PageProps` | `interface SupplierOrdersIndexProps` |
| `Products/CsvImport.tsx` | `interface PageProps` | `interface CsvImportPageProps` |
| `Products/Manual.tsx` | `interface PageProps` | `interface ManualPageProps` |
| `Products/Map.tsx` | `interface PageProps` | `interface MapPageProps` |

This matches the project's existing convention (visible in `Vendor/Profile.tsx`, `Vendor/Wallet.tsx`, `Catalog/Show.tsx`, etc.).

### 3. New CI sub-check inside the frontend job

`.github/workflows/ci.yml` — added **Phase 6 v7.3 — every usePage<X>() in Phase 6 React pages must use SharedProps** to the existing frontend job (which already runs `npm ci && npm run typecheck && npm run lint && npm run build`). This sub-check:

- Runs a Python validator against every `.tsx` under `resources/js/Pages/Vendor/Supplier/`
- For each `usePage<X>()` call: if `X` is `SharedProps`, or a local type whose definition contains `SharedProps`, pass; else fail with a Phase 6-specific message.
- Additionally bans any local `interface PageProps` / `type PageProps` / `interface FlashProps` / `type FlashProps` in Phase 6 files (these are name-shadowing hazards).

The check is **belt-and-suspenders on top of `npm run typecheck`** — typecheck already catches TS2344, but this gives a focused, attributable error message for Phase 6 specifically. If a future Phase change reintroduces local `PageProps`, this fires before the full tsc trace.

### 4. Verdict bumped

Final CI verdict is now `✅ Phase 6 v7.3 PASSES — ready to approve Phase 7`.

### 5. My own verification methodology — fixed

My v7.2 sandbox `tsc` sweep had `noResolve: true` which silenced module-level constraint checking — that's how this slipped through. For v7.3 I built minimal type stubs for `@inertiajs/react`, `@inertiajs/core`, `react`, and `lucide-react` (sandbox-only, NOT shipped in the archive), then ran **real `tsc` with strict mode, module resolution, and proper Inertia constraint enforcement** against all Phase 6 React files plus the existing `inertia.d.ts`. Result:

```
TS2344 (generic-constraint violations) in Phase 6: 0
```

The remaining errors in the sandbox run (TS7006, TS2875, TS7026) are all stub-gap artifacts — they don't exist in the real `@types/react` shipped via `npm ci` on the CI runner.

**Honest caveat:** I still cannot run `npm install` or `npm run build` directly in the sandbox (network disabled, 403 from registry). My verification path was: real tsc + Inertia-constraint stubs + a focused Python validator. The CI frontend job remains the only authoritative end-to-end gate.

---

## Files touched in v7.3

| File | Change |
|---|---|
| `resources/js/Pages/Vendor/Supplier/Integrations/Index.tsx` | `usePage<>` now uses `SharedProps & ...` |
| `resources/js/Pages/Vendor/Supplier/Orders/Show.tsx` | `usePage<>` now uses `SharedProps` |
| `resources/js/Pages/Vendor/Supplier/Products/Index.tsx` | `usePage<>` now uses `SharedProps & ...` |
| `resources/js/Pages/Vendor/Supplier/Orders/Index.tsx` | Local `PageProps` → `SupplierOrdersIndexProps` |
| `resources/js/Pages/Vendor/Supplier/Products/CsvImport.tsx` | Local `PageProps` → `CsvImportPageProps` |
| `resources/js/Pages/Vendor/Supplier/Products/Manual.tsx` | Local `PageProps` → `ManualPageProps` |
| `resources/js/Pages/Vendor/Supplier/Products/Map.tsx` | Local `PageProps` → `MapPageProps` |
| `.github/workflows/ci.yml` | New Phase 6 v7.3 sub-check inside the frontend job + verdict bumped |
| `PHASE_6_v7.3_PATCH_NOTES.md` | This file (new) |
| `README.md` | v7.3 changelog block prepended |
| `TROUBLESHOOTING.md` | New entry for TS2344 / Inertia generic-constraint violations |
| `PHASE_6_REPORT.md` | Appended v7.3 update section |

**Files NOT touched in v7.3:** ALL 7 Phase 6 migrations, all 6 new models, all 3 services, all 4 Filament resources + Pages, all 3 vendor controllers, routes, VendorLayout (the layout never used local `PageProps`), RolesAndPermissionsSeeder, DemoSeeder, `.env.example`, `bootstrap/app.php`, `MarketplaceSetupDemo.php`, Phase6DropshippingTest, the v7.0/v7.1/v7.2 CI sub-checks. **No business logic was modified — this is purely a TypeScript typing fix.**

---

## Pattern guide for future Phase 7+ pages

When a new Inertia page calls `usePage<>()`:

```ts
import type { SharedProps } from '@/types/inertia';

// CASE A — page only needs SharedProps fields (auth, flash, app, etc.)
const { props } = usePage<SharedProps>();

// CASE B — page has its own Inertia props from the controller
type MyPageProps = SharedProps & {
    items: Item[];
    pagination: { ... };
};
const { props } = usePage<MyPageProps>();
```

**Do NOT:**
- Declare a local `interface PageProps { ... }` (it shadows the augmented global)
- Declare a local `type FlashProps = { flash?: ... }` and pass to `usePage<>` (same problem)
- Use the bare augmented `PageProps` as the generic when you have page-specific props (pointless — its index-signature is `unknown`)

The new CI sub-check enforces this for all `resources/js/Pages/Vendor/Supplier/` files.

---

## Developer checklist after pulling v7.3

```bash
git pull
composer install
npm ci
npm run typecheck     # must pass
npm run build         # must pass — public/build/manifest.json appears
php artisan marketplace:setup-demo --force
```

Open `http://localhost:8000/vendor/supplier-products` after login as `vendor@marketplace.test / password` and confirm the page loads with the demo dropship products. If it does, v7.3 is good.

**Approve Phase 7 only after CI shows `✅ Phase 6 v7.3 PASSES`.**
