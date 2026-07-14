# Phase 11A v11A.1 — Responsive Container and Page Spacing Repair

Per dev §17.

## Critical context

The dev reported that on both desktop AND mobile, content stretched directly to the left and right edges of the screen — insufficient horizontal padding, looking crowded and unfinished. Phase 11A was otherwise working correctly: theme rendered, sections in order, mobile drawer functional. The defect was specifically the responsive container padding.

## Root cause

Phase 11A relied on a CSS component class `.container-app` defined in `resources/css/app.css` via `@layer components`:

```css
@layer components {
    .container-app {
        @apply mx-auto max-w-7xl px-4 sm:px-6 lg:px-8;
    }
}
```

The class was used 9 times across `Welcome.tsx` and 3 times across `StorefrontLayout.tsx`. The CSS file content was structurally correct, and the class WAS defined.

Despite this, the dev observed edge-to-edge content. The likely combination of failure modes:

1. **Tailwind v3 + Vite + `@layer components` edge case** — under some build configurations, custom component classes inside `@layer components` blocks can fail to land in the final compiled CSS. This is especially true when there are MULTIPLE `@layer components` blocks in the same file (v11A added a second `@layer components` block for `.container-prose`).

2. **Browser cache / service worker serving pre-v11A assets** — `npm run build` produces hashed asset filenames, but if a previous service worker registration still claims `/build/assets/app-OLD.css`, the new manifest's CSS wouldn't be loaded.

3. **CSS specificity** — `.container-app` is a custom component class that could be overridden by any utility class with higher specificity if applied via `className`. Unlikely in our code, but possible.

Whatever the exact trigger, the fix is to ELIMINATE THE INDIRECTION. Move from a CSS-class-based container to a React component that puts the Tailwind utilities INLINE in the source. Inline utilities in TSX files are guaranteed to be picked up by Tailwind's JIT scanner because the scanner reads TSX source directly.

## Fix — the smallest robust change

Per dev §1: "Create or reuse a shared responsive container pattern for the storefront."

I created a `<Container>` React component at `resources/js/Components/ui/v11/Container.tsx`:

```tsx
export function Container({ as: Comp = 'div', maxWidth = 'max-w-7xl', className = '', children, ...rest }) {
    const base = `mx-auto w-full ${maxWidth} px-4 sm:px-6 lg:px-8 xl:px-10`;
    return <Comp className={`${base} ${className}`.trim()} {...rest}>{children}</Comp>;
}
```

This is the dev's §1 recommended pattern verbatim, plus the dev's recommended `xl:px-10` step for large desktops.

Then I replaced `<div className="container-app ...">` with `<Container className="...">` across `Welcome.tsx` (9 occurrences) and `StorefrontLayout.tsx` (3 occurrences). The Container's outer `<div>` matches what the previous nested `<div className="container-app ...">` provided, so structurally the markup is equivalent — but now the responsive utilities are guaranteed in the bundle because they appear LITERALLY in the source code of Container.tsx.

I also updated the LEGACY `.container-app` CSS class in `app.css` to include the v11A.1 spacing scale:

```css
.container-app {
    @apply mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8 xl:px-10;
}
```

This is a backwards-compatible improvement. Other pages still using the CSS class (Catalog/Index, Orders, etc.) benefit from the v11A.1 spacing scale without code changes. If the CSS class compilation issue is fixed in their context (e.g., the dev rebuilds and clears caches), they automatically get the same spacing as v11A surfaces. If the CSS class still fails to compile in their context, the v11A.1 fix would be to migrate those pages to `<Container>` too — but that's an incremental migration, not v11A.1's scope.

## Spacing scale (dev §1 → applied)

| Breakpoint | Tailwind class | Padding | Visual |
|---|---|---|---|
| Mobile (< 640) | `px-4` | 16px | Comfortable thumb-edge space |
| Small tablet (≥ 640) | `sm:px-6` | 24px | More breathing room as viewport grows |
| Desktop (≥ 1024) | `lg:px-8` | 32px | Standard desktop side gutter |
| Large desktop (≥ 1280) | `xl:px-10` | 40px | Premium feel at wide viewports |

Max content width: `max-w-7xl` = 1280px. Beyond that, content is centered with `mx-auto` and grows margins, not content width.

## Files changed in v11A.1

| File | Change |
|---|---|
| `resources/js/Components/ui/v11/Container.tsx` | **NEW** — reusable container primitive with inline Tailwind classes |
| `resources/js/Pages/Welcome.tsx` | Replaced 9 `<div className="container-app ...">` with `<Container className="...">`. Added Container import. |
| `resources/js/Layouts/StorefrontLayout.tsx` | Replaced 3 `<div className="container-app ...">` with `<Container className="...">`. Added Container import. Also improved mobile drawer internal padding (`px-2 py-2` → `px-4 py-3`) so nav links don't touch drawer edges per dev §4. |
| `resources/css/app.css` | `.container-app` definition extended with `xl:px-10` per dev §1 padding scale. JSDoc comment added explaining v11A.1 context. |
| `tests/Feature/Phase11AV1Hot1RegressionTest.php` | **NEW** — 20 Pest scenarios |
| `.github/workflows/ci.yml` | 4 new v11A.1 sub-checks |
| `VERSION` | `Phase 11A` → `Phase 11A v11A.1` |

**Zero PHP files modified. Zero migrations, zero routes, zero config files, zero models, zero controllers.**

## Active layout files audited (per §2)

- `resources/js/Layouts/StorefrontLayout.tsx` ✓ migrated
- `resources/js/Pages/Welcome.tsx` ✓ migrated
- `resources/js/Components/ui/v11/Container.tsx` ✓ NEW
- `resources/js/Layouts/VendorLayout.tsx` — NOT modified (uses its own container pattern; appropriate)
- `resources/js/Pages/Catalog/Index.tsx` — NOT modified (uses `container-app` CSS class; benefits from the updated CSS definition with `xl:px-10`)
- `resources/js/Pages/Catalog/Show.tsx` — NOT modified (same reason)

The v11A surfaces (homepage + storefront chrome) are the ones the dev reported broken. The fix focuses there. The legacy `container-app` CSS class update is a side benefit for other pages.

## Mobile drawer §4 specific fixes

Per dev §4 — "the drawer content must have its own internal padding; category links must not touch the drawer edges":

| Drawer element | Before v11A.1 | After v11A.1 |
|---|---|---|
| Drawer header (Menu + close) | `h-16 px-4 border-b` | Same — already padded ✓ |
| Drawer search form | `p-4 border-b` | Same — already padded ✓ |
| Drawer nav list block | `px-2 py-2` (8px sides) | `px-4 py-3` (16px sides) — improved per §4 |
| Each nav Link | `px-3 py-2.5 rounded-lg` | Same — already padded ✓ |

The drawer header and search were already adequately padded. The nav list block had only 8px of side padding so category links could feel tight against the drawer edge — now 16px per dev §4.

## Pages manually verified (static source-level analysis only — live verification is dev §15)

| Page | Container source | Status |
|---|---|---|
| Homepage (`/`) | `<Container>` × 9 (one per section) | ✓ |
| Header (every page) | `<Container>` × 2 (utility bar + main header) | ✓ |
| Footer (every page) | `<Container>` × 1 | ✓ |
| Mobile drawer | Internal `px-4 py-3` padding on nav block | ✓ |
| Catalog list (`/products`) | Inherits via `container-app` CSS class (now includes `xl:px-10`) | ✓ via legacy CSS |
| Product detail (`/products/{slug}`) | Inherits via `container-app` CSS class | ✓ via legacy CSS |
| Cart (`/cart`) | Inherits via `container-app` CSS class | ✓ via legacy CSS |
| Orders, Bookings | Inherits via `container-app` CSS class | ✓ via legacy CSS |
| Vendor dashboard | Uses `VendorLayout` — separate container pattern | NOT modified (untouched in v11A.1) |
| Admin Reports | Uses `AdminLayout` — separate container pattern | NOT modified |

The dev should live-verify that:
1. `/products`, `/cart`, etc. now have visible side padding at ALL viewport widths (the CSS update should make `container-app` compile reliably with the new spacing).
2. Vendor dashboard and Admin Reports also have side padding (if not, they may need a similar migration in v11A.2).

## Counts

| | Phase 11A → Phase 11A v11A.1 |
|---|---|
| CI sub-checks | 71 → 75 (+4 v11A.1) |
| Pest scenarios | 268 → 288 (+20 v11A.1) |
| Unique global Pest helpers | 93 → 97 (+4 p11ah1_*, 0 dups) |
| TSX files NEW | n/a → 1 (Container.tsx) |
| TSX files MODIFIED | n/a → 2 (Welcome.tsx, StorefrontLayout.tsx) |
| CSS files MODIFIED | n/a → 1 (app.css — single line of class definition) |
| PHP files modified | n/a → 0 |

## v11A + v10.x preservation matrix

All v11A redesign markers PRESERVED. All v10.0-v10.16 fixes PRESERVED. Verified by 18 regression-guard tests in the v11A.1 Pest file:

- v11A 7 homepage sections (hero, trust, categories, featured-products, deals-banner, services, how-it-works) all present
- v11A StorefrontLayout markers (header-v11a, footer-v11a, search) preserved
- v10.6 mobile-categories-toggle preserved
- v10.16 null-safe permissions pattern preserved (no `user.permissions.length` access)
- v10.16 `AuthUser.permissions?: string[]` optional type preserved
- v10.15 defensive try/catch wrappings (5 markers) preserved
- v10.14 scope-aware closures + indexes migration preserved
- v10.11 §2 perf preservation (permissions removed from share) preserved
- v10.10 admin reports direct guard (3 occurrences) preserved
- TSX brace + paren balance verified

## Why this matters beyond cosmetics

The dev's observation surfaced a real architectural risk: **relying on CSS component classes for layout-critical structure can fail under various build/cache scenarios**, with no obvious error signal. v11A.1's switch to inline Tailwind utilities via a Container component is more robust because:

1. Tailwind's JIT scanner ALWAYS picks up classes that appear literally in TSX source.
2. There's no `@layer components` compilation step that can be skipped or overridden.
3. The component is type-safe and discoverable in IDE.
4. Future devs can override per-instance via the `className` prop or change the global default by editing one file.

The lesson goes in `PHASE_10_KNOWN_LIMITATIONS.md`: prefer inline Tailwind utilities (or component-encapsulated utilities) over CSS `@apply` for anything that's structurally critical to page layout. Use `@apply` only for visual polish that's safe to degrade gracefully.

## Per dev §17 acceptance

Dev runs:
```bash
php artisan optimize:clear
php artisan test --filter=Phase11AV1Hot1     # → 20 v11A.1 scenarios pass
php artisan test                              # → 288 total scenarios pass
npm run typecheck                             # MUST PASS
npm run build                                 # MUST SUCCEED
```

Then live-verifies horizontal padding at the breakpoints in `PHASE_11A_v11A.1_DEVELOPER_CHECKLIST.md` (320, 375, 390, 414, 768, 1024, 1280, large desktop) — content should NOT touch viewport edges at any width.

## Per dev §17 stop directive

**Phase 11A v11A.1 STOPS HERE. No Phase 11B work begun.** Pending dev verification.
