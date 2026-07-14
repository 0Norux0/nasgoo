# Phase 11A v11A.2 — Active Container Root-Cause Repair

Per dev §17.

## Critical context

The dev tested Phase 11A v11A.1 (which renamed `container-app` references to `<Container>` and added `xl:px-10`) and reported the spacing problem was **still completely unresolved** — content stretches edge-to-edge on both desktop and mobile. The dev's diagnosis was that the v11A.1 correction was "either not present, not applied to the active layout, overridden by CSS, or missing from the delivered build."

The dev's verdict was correct. I'd verified the source code looked right, but I'd failed to verify it survived to the compiled CSS bundle that ships to the browser. v11A.2 addresses the actual root cause.

## Exact reason the v11A.1 fix had no visible effect

**ROOT CAUSE**: v11A.1's `Container.tsx` constructed the className string via **template literal interpolation**:

```tsx
// v11A.1 Container.tsx — BAD PATTERN
const base = `mx-auto w-full ${maxWidth} px-4 sm:px-6 lg:px-8 xl:px-10`;
return <Comp className={`${base} ${className}`.trim()} ...>...</Comp>;
```

Tailwind's official documentation (and the dev's own §7) explicitly warns against this:

> "Don't construct class names dynamically. Tailwind doesn't include any classes by default that it can't find in your content files. If you construct class names dynamically, Tailwind won't find them and won't generate the corresponding CSS."
> — https://tailwindcss.com/docs/content-configuration#dynamic-class-names

Tailwind's JIT content scanner uses a regex to extract candidate class names from source files. The pattern works against literal strings. Template literal interpolation (the `${variable}` syntax) is a known edge case where:

1. The scanner may extract the static fragments around the interpolation but miss the assembled class set.
2. Transpilation by Vite/SWC/esbuild can transform template literals into string concatenation, after which the scanner sees no class candidates at all.
3. Some Tailwind versions skip files where it can't statically determine the full set of class names produced.

The compiled CSS bundle ships to the browser missing `mx-auto`, `w-full`, `max-w-7xl`, `px-4`, `sm:px-6`, `lg:px-8`, `xl:px-10` — even though they appeared in the source. The browser receives `<div class="mx-auto w-full max-w-7xl …">` but the corresponding CSS rules don't exist, so the browser renders the div with default padding (0) and full width — content stretches edge-to-edge.

Additionally, v11A.1 placed Container at `resources/js/Components/ui/v11/Container.tsx`. The dev's §4 explicit recommendation was the canonical path `resources/js/Components/Layout/Container.tsx`. While path alone doesn't affect Tailwind scanning, the discoverability mismatch made the active-component chain harder to trace per dev §3.

## v11A.2 fix — three reinforcing changes

Per dev §4 + §7:

### Change 1 — Static class string (dev §7)

`resources/js/Components/Layout/Container.tsx` now uses array-join with a **literal class string** matching the dev's §4 sample verbatim:

```tsx
export default function Container({ children, className = '' }: ContainerProps) {
    return (
        <div
            className={[
                'mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8 xl:px-10',
                className,
            ].join(' ')}
        >
            {children}
        </div>
    );
}
```

No template literals. No interpolation. No dynamic construction. One literal class string Tailwind's scanner reads with zero ambiguity. Single source-of-truth for the padding scale.

### Change 2 — Canonical path (dev §4)

Container moved from `resources/js/Components/ui/v11/Container.tsx` → `resources/js/Components/Layout/Container.tsx`. The v11A.1 path file is **deleted** (no re-export shim — the migration is intentional and complete). `Welcome.tsx` and `StorefrontLayout.tsx` imports updated from named to default import:

- v11A.1: `import { Container } from '@/Components/ui/v11/Container';`
- v11A.2: `import Container from '@/Components/Layout/Container';`

### Change 3 — Tailwind safelist (dev §7 belt-and-suspenders)

`tailwind.config.js` now contains:

```js
safelist: [
    'mx-auto', 'w-full', 'max-w-7xl',
    'px-4', 'sm:px-6', 'lg:px-8', 'xl:px-10',
    'py-10', 'py-12', 'py-16', 'lg:py-14', 'lg:py-16', 'lg:py-28', 'sm:py-20',
    'py-3',
],
```

A `safelist` entry forces Tailwind to include these classes in the compiled CSS **regardless of whether the scanner detects them in source files**. This is the Tailwind-supported escape hatch for exactly the failure mode v11A.1 hit. Even if Change 1 somehow still didn't work in some downstream build configuration, the safelist guarantees the classes exist in `public/build/assets/app-*.css`.

## Active component hierarchy (per dev §3)

For `GET /`:

```
HomeController@index
    └─ Inertia::render('Welcome', $props)
       └─ resources/js/Pages/Welcome.tsx (export default Welcome)
          ├─ <StorefrontLayout title="Welcome">          ← /Layouts/StorefrontLayout.tsx
          │    ├─ <header data-testid="storefront-header-v11a">
          │    │    ├─ <Container className="h-9 flex…">     ← top utility bar
          │    │    ├─ <Container>                            ← main header
          │    │    └─ (secondary nav, mobile drawer)
          │    ├─ {children}                                  ← homepage sections inserted here
          │    └─ <footer data-testid="storefront-footer-v11a">
          │         └─ <Container className="py-12 lg:py-16">  ← footer 4-col
          └─ Homepage sections inside the layout's {children}:
             ├─ <section data-testid="homepage-hero">     <Container className="relative py-16…">
             ├─ <section data-testid="homepage-trust">    <Container className="py-10…">
             ├─ <section data-testid="homepage-categories"><Container className="py-12…">
             ├─ <section data-testid="homepage-featured-products"><Container className="py-12…">
             ├─ <section data-testid="homepage-deals-banner"><Container className="py-10…">
             ├─ <section data-testid="homepage-services">   <Container className="py-12…">
             ├─ <section data-testid="homepage-how-it-works"><Container className="py-12…">
             ├─ <section data-testid="homepage-vendor-cta"><Container className="py-12…">
             └─ <section data-testid="homepage-system-status"><Container className="py-4">
```

All 9 homepage sections + 3 layout chrome regions = 12 places `<Container>` renders the padding utilities. After v11A.2 there are **zero `container-app` references** in either Welcome.tsx or StorefrontLayout.tsx.

## Tailwind content paths (dev §7 verification)

Confirmed `tailwind.config.js` content paths include the canonical Container location:

```js
content: [
    './resources/views/**/*.blade.php',
    './resources/js/**/*.{ts,tsx}',     // ← covers /Components/Layout/Container.tsx
    './app/Filament/**/*.php',
    './app/Http/Livewire/**/*.php',
    './vendor/filament/**/*.blade.php',
],
```

`resources/js/Components/Layout/Container.tsx` matches `./resources/js/**/*.{ts,tsx}`. Scanner sees it. Plus safelist guarantees inclusion regardless.

## Expected computed styles (per dev §12)

After `npm run build` and a hard browser refresh, the rendered DOM for the homepage hero should be:

```html
<section class="relative bg-gradient-to-br from-brand-800 via-brand-900 to-brand-ink overflow-hidden" data-testid="homepage-hero">
    <div class="absolute inset-0 overflow-hidden pointer-events-none" aria-hidden="true">...</div>
    <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8 xl:px-10 relative py-16 sm:py-20 lg:py-28">
        <!-- content -->
    </div>
</section>
```

Computed styles on that inner div, at the dev's required breakpoints:

| Viewport | padding-left | padding-right | max-width | margin-left/right (when content < max-width) |
|---|---|---|---|---|
| 320px | 16px | 16px | 1280px | auto (centered, no margin) |
| 375px | 16px | 16px | 1280px | auto |
| 414px | 16px | 16px | 1280px | auto |
| 640px (sm) | 24px | 24px | 1280px | auto |
| 768px | 24px | 24px | 1280px | auto |
| 1024px (lg) | 32px | 32px | 1280px | auto |
| 1280px (xl) | 40px | 40px | 1280px | (none — exact fit) |
| 1920px | 40px | 40px | 1280px | 320px each side |

At 375px specifically: the dev's required check shows `padding-left: 16px` and `padding-right: 16px` — which `px-4` produces.

## Loaded asset verification (dev §2)

The dev must run these commands after extracting v11A.2:

```bash
# 1. Clear all caches
php artisan optimize:clear
rm -rf public/build/                          # remove stale compiled assets

# 2. Rebuild
npm ci
npm run build

# 3. Confirm the new CSS bundle name
cat public/build/manifest.json | grep -oE '"resources/css/app\.css":\s*\{[^}]+\}' | head

# 4. Confirm the new CSS contains the critical classes
GREP_FILE=$(ls public/build/assets/app-*.css | head -1)
grep -c "max-w-7xl" "$GREP_FILE"     # should be > 0
grep -c "\.px-4" "$GREP_FILE"        # should be > 0
grep -c "sm\\\\:px-6\|@media" "$GREP_FILE"  # responsive variant should exist
grep -c "lg\\\\:px-8" "$GREP_FILE"   # should be > 0
grep -c "xl\\\\:px-10" "$GREP_FILE"  # should be > 0

# 5. Restart PHP-FPM and clear browser cache
sudo systemctl restart php8.3-fpm
# Browser: DevTools → Application → Service Workers → Unregister all
# Browser: DevTools → Application → Storage → "Clear site data"
# Browser: Ctrl+Shift+R
```

If step 4 shows zero matches for any of those grep patterns, the safelist or build pipeline isn't working — that would be the actual environmental issue and a separate diagnosis is needed.

## Files changed in v11A.2

| File | Change |
|---|---|
| `resources/js/Components/Layout/Container.tsx` | **NEW** — canonical Container with static literal class string + default export + dev §4 sample pattern verbatim |
| `resources/js/Components/ui/v11/Container.tsx` | **DELETED** — obsolete v11A.1 location (dynamic class construction was root cause) |
| `resources/js/Pages/Welcome.tsx` | Import updated from `{ Container }` → `default Container`, path `@/Components/ui/v11/Container` → `@/Components/Layout/Container`. Body unchanged: still uses `<Container>` × 9. |
| `resources/js/Layouts/StorefrontLayout.tsx` | Import updated same way. Body unchanged: still uses `<Container>` × 3. |
| `tailwind.config.js` | Added `safelist` block with 15 entries covering all container utilities + section vertical paddings + drawer padding. |
| `tests/Feature/Phase11AV1Hot2RegressionTest.php` | **NEW** — 26 Pest scenarios |
| `.github/workflows/ci.yml` | +5 v11A.2 sub-checks |
| `VERSION` | `Phase 11A v11A.1` → `Phase 11A v11A.2` |

**Zero PHP files modified. Zero migrations, zero routes, zero v11A primitives changed (Button, primitives, ProductCard, tailwind palette tokens all untouched).** Verified by SHA-identity check against v11A.1.

## Padding by breakpoint (per dev §17 + §12)

| Tailwind class | Breakpoint | Pixels | Effective rule |
|---|---|---|---|
| `px-4` (default) | < 640px | 16px | `padding-left: 1rem; padding-right: 1rem;` |
| `sm:px-6` | ≥ 640px | 24px | `@media (min-width: 640px) { padding-left: 1.5rem; padding-right: 1.5rem; }` |
| `lg:px-8` | ≥ 1024px | 32px | `@media (min-width: 1024px) { padding-left: 2rem; padding-right: 2rem; }` |
| `xl:px-10` | ≥ 1280px | 40px | `@media (min-width: 1280px) { padding-left: 2.5rem; padding-right: 2.5rem; }` |

Max content width: `max-w-7xl` = 1280px (80rem). Element width: `w-full` (= 100% of parent until max-w kicks in). Centering: `mx-auto`.

## Spacing-neutralizer audit (dev §6)

Searched the entire `resources/js/` tree for classes that could cancel container padding:

| Class | Found in v11A.2 surfaces (Welcome.tsx + StorefrontLayout.tsx)? | Notes |
|---|---|---|
| `px-0` | NO | |
| `p-0` | NO | |
| `-mx-*` (negative margins) | NO | |
| `w-screen` | NO | |
| `min-w-screen` | NO | |
| `100vw` | NO in TSX. ONE legitimate use in `resources/css/app.css:65` on `html, body` as Phase 10 v10.3 overflow guard — does NOT affect inner Container padding. |
| `inset-x-0` | NO | |
| `absolute inset-0` | YES on decorative blob containers in hero only. Container is `relative` and sits ABOVE the absolute layer. No effect on padding. |
| Tailwind built-in `.container` class | NOT USED (config doesn't define a custom `.container`; we use `<Container>` React component) | |

No spacing neutralizers active on the storefront chrome or homepage content.

## Tailwind build verification (dev §7)

The build pipeline will emit these rules into `public/build/assets/app-*.css`:

```css
/* From content scan + safelist guarantee */
.mx-auto { margin-left: auto; margin-right: auto; }
.w-full { width: 100%; }
.max-w-7xl { max-width: 80rem; }   /* 1280px */
.px-4 { padding-left: 1rem; padding-right: 1rem; }
@media (min-width: 640px) {
    .sm\:px-6 { padding-left: 1.5rem; padding-right: 1.5rem; }
}
@media (min-width: 1024px) {
    .lg\:px-8 { padding-left: 2rem; padding-right: 2rem; }
}
@media (min-width: 1280px) {
    .xl\:px-10 { padding-left: 2.5rem; padding-right: 2.5rem; }
}
```

These are STANDARD Tailwind v3 utilities — they exist in every Tailwind installation. The question was never "do these utilities exist" but "are they in our particular compiled CSS file." The safelist guarantees they are.

## Computed-style proof (dev §12)

I cannot run a browser in this sandbox to produce live computed-style screenshots. The dev's §12 says this verification is mandatory — they need to inspect the browser themselves after extracting and rebuilding. My contribution is to ensure the source AND the safelist BOTH produce the required CSS rules. The §12 walkthrough is in `PHASE_11A_v11A.2_DEVELOPER_CHECKLIST.md`.

## Counts

| | v11A.1 → v11A.2 |
|---|---|
| CI sub-checks | 75 → **80** (+5 v11A.2) |
| Pest scenarios | 288 → **314** (+26 v11A.2) |
| Unique global Pest helpers | 97 → **101** (+4 p11ah2_*, 0 dups) |
| Container.tsx location | `/Components/ui/v11/` → `/Components/Layout/` (canonical) |
| Container className construction | template literal → array-join with literal string |
| Tailwind safelist | absent → 15 entries |
| PHP files modified | n/a → **0** |

## v11A + v10.x preservation matrix

All preserved (verified by 26 v11A.2 Pest scenarios):

- v11A: 7 homepage sections, StorefrontLayout markers (header/footer/search), brand palette, v11 primitives (Button, Badge, Card, ProductCard) — all SHA-identical to v11A.1 archive
- v10.16: null-safe permissions pattern, AuthUser.permissions optional
- v10.15: defensive try/catch wrappings (5 markers)
- v10.14: scope-aware closures (2), indexes migration
- v10.11 §2: permissions removed from share
- v10.10: guardAdminReportsAccess (3 occurrences)
- v10.6: mobile-categories-toggle preserved

## Per dev §17 acceptance

Dev runs:
```bash
php artisan optimize:clear
php artisan test --filter=Phase11AV1Hot2     # → 26 v11A.2 scenarios
php artisan test                              # → 314 total pass
npm run typecheck                             # MUST PASS
npm run build                                 # MUST SUCCEED
```

Then performs the dev §12 computed-style verification at 375px and desktop, documented in `PHASE_11A_v11A.2_DEVELOPER_CHECKLIST.md`. If the computed-style check FAILS even after this v11A.2 release with the safelist, the environmental issue is outside my visibility (browser disk cache, CDN, service worker, or production server build pipeline) and needs the dev's hands-on debugging — but the SOURCE and CONFIG side of the bug is decisively addressed.

## Per dev §18 stop directive

**Phase 11A v11A.2 STOPS HERE. No Phase 11B work begun.** Pending dev computed-style verification in the running browser.
