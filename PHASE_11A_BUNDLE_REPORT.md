# Phase 11A — Bundle Report

Per dev §16 + §18.7.

## Methodology

The sandbox cannot run `npm ci && npm run build`, so this report uses **static analysis** of the new source files (line count, gzipped-text estimate, dependency surface). The dev should run `npm run build` in the project root for definitive bundle metrics — those numbers replace my estimates.

## Per dev §16 — Performance requirements check

| Requirement | v11A status |
|---|---|
| No heavy UI library unless justified | ✓ — no Material UI, Chakra, Mantine, Ant Design added |
| No unnecessary animation framework | ✓ — no Framer Motion, no GSAP. Only Tailwind `transition-*` and `animate-spin` (3 places) |
| Avoid giant background videos | ✓ — zero videos. Hero uses CSS gradient + absolute-positioned blurred divs |
| Optimize hero imagery | ✓ — hero illustration is a CSS composition (gradient + Card + SVG icon); ZERO raster images in the hero |
| Use responsive image dimensions | ✓ — `ProductCard` includes explicit `width=300 height=300` on `<img>` tags |
| Lazy-load below-the-fold images | ✓ — all `<img>` in `ProductCard` have `loading="lazy" decoding="async"` |
| Preserve server-side pagination | ✓ — homepage uses `featured_products.slice(0, 8)`; backend already limits to top N; catalog page (`/products`) untouched (still server-paginated) |
| Avoid oversized Inertia props | ✓ — no new shared props added. Homepage uses existing `featured_products`, `top_categories`, `health`, `phase` (all from Phase 10) |
| Keep JavaScript bundle growth controlled | See below |
| Avoid repeated unnecessary renders | ✓ — no new React hooks per render. `useState` only for drawer + search + categoriesOpen (same as Phase 10) |

## New code surface

| File | Lines | Approx. uncompressed | Notes |
|---|---|---|---|
| `resources/js/Components/ui/v11/Button.tsx` | ~150 | ~4 KB | One forwardRef component, no imports beyond `@inertiajs/react` (already used) and React |
| `resources/js/Components/ui/v11/primitives.tsx` | ~140 | ~4 KB | Four small components (Card, Badge, SectionHeading, TrustBadge) |
| `resources/js/Components/ui/v11/ProductCard.tsx` | ~190 | ~5.5 KB | One component + Rating sub-component; imports `lucide-react` icons (Heart, Star) |
| **Total new component code** | **~480** | **~13.5 KB uncompressed** | |

After gzip compression (typical 3–4× ratio for JS), this is **~3.5 KB gzipped** added to the bundle.

## Rewritten code surface (replaces existing code)

| File | Before (lines) | After (lines) | Delta | Notes |
|---|---|---|---|---|
| `resources/js/Layouts/StorefrontLayout.tsx` | 229 | ~370 | +141 | Net growth ~4 KB uncompressed |
| `resources/js/Pages/Welcome.tsx` | 213 | ~340 | +127 | Net growth ~4 KB uncompressed |

Both files share imports with the new component primitives, so the actual delta in the final bundle is less than the source delta (tree-shaking + shared module references).

## Dependency surface (NO new packages)

| Package | Status | Already in package.json? |
|---|---|---|
| `lucide-react` | Used (Heart, Star, Search, Menu, X, ShoppingBag, ChevronDown, Package, Sparkles, MessageCircle, Store, Shield, BadgeCheck, Headset, Truck, ArrowRight, User) | ✓ Yes (Phase 10 already used it sparingly) |
| `@inertiajs/react` | Used (Link, usePage, router) | ✓ Yes |
| `react` | Used (useState, FormEvent, forwardRef) | ✓ Yes |
| Tailwind CSS | Config extended (new color tokens) | ✓ Yes (no new package, just config) |

**Zero new package.json dependencies.** No bundle-size hit from npm packages.

## Tailwind config impact

| Addition | Bundle impact |
|---|---|
| New color palettes (`accent`, `gold`, `brand.ink`) | Tailwind v3 uses JIT — only utilities ACTUALLY USED in source generate CSS. So adding `accent: { 50-900 }` doesn't bloat CSS unless you actually write `bg-accent-600` somewhere. v11A writes ~30 utility classes from the new palettes — minimal CSS impact (~1-2 KB before gzip). |
| New shadows (`soft`, `card`, `card-hover`, `hero`) | Similar JIT behavior. ~4 utilities used; ~200 bytes of CSS. |
| `fontFamily.display` | Adds one font-family CSS class. ~50 bytes. |

Total CSS delta: **~2 KB uncompressed, ~600 bytes gzipped**.

## Lucide icon imports (per-file analysis)

Lucide icons are imported individually (named imports), so tree-shaking removes unused icons. v11A imports these icons:

| File | Icons imported |
|---|---|
| Welcome.tsx | Shield, BadgeCheck, Star, Headset, Package, Truck, Sparkles, ArrowRight, Search |
| StorefrontLayout.tsx | Search, Menu, X, ShoppingBag, Heart, User, ChevronDown, Package, Sparkles, MessageCircle, Store |
| Button.tsx | (none — uses children) |
| primitives.tsx | (none) |
| ProductCard.tsx | Heart, Star |

Each lucide icon is ~500 bytes uncompressed; ~150 bytes gzipped. Total icon surface: ~17 unique icons = **~2.5 KB gzipped**.

## Estimated bundle delta

| Component | Estimated delta (gzipped) |
|---|---|
| New v11 primitive components | +3.5 KB |
| StorefrontLayout net growth | +1.5 KB (replaces existing) |
| Welcome.tsx net growth | +1.5 KB (replaces existing) |
| New CSS from Tailwind tokens | +0.6 KB |
| New lucide-react icons | +2.5 KB |
| **Estimated total** | **~9.6 KB gzipped delta** |

This is well within the dev's "controlled growth" requirement (and well under common SPA performance budgets of 200 KB initial JS).

## Critical performance preservations

- **Phase 10 v10.1 perf indexes migration** — untouched. Database queries still hit the same indexes.
- **Phase 10 v10.11 §2 permissions removal from share** — untouched. The expensive `getAllPermissions()->pluck()` is still absent.
- **Phase 10 v10.14 health check 30s cache** — untouched. The homepage `<details>` status strip still uses the cached health data.
- **Phase 10 v10.14 8 composite indexes** — untouched.
- **Phase 10 v10.15 defensive share() wrappings** — untouched. The fail-safe Inertia share remains.

## What needs live measurement

The dev should run:

```bash
cd /var/www/marketplace
npm ci
npm run build 2>&1 | tee build.log
ls -la public/build/assets/*.js | awk '{print $5, $9}' | sort -n
ls -la public/build/assets/*.css | awk '{print $5, $9}' | sort -n
```

Then capture:
- Total `.js` size (gzipped — use `gzip -c public/build/assets/X.js | wc -c`)
- Total `.css` size (gzipped)
- Number of chunks
- Largest chunk

Compare against the Phase 10 final-approved build (if those metrics were captured at approval time). The expectation is **~10 KB gzipped JS growth and ~1 KB gzipped CSS growth**.

## Suggested Lighthouse runs

After build:
1. Run Lighthouse on `/` as guest.
2. Run Lighthouse on `/` as customer (after login).
3. Capture: Performance, Accessibility, Best Practices, SEO.

Phase 10 baseline scores (per dev approval) are the reference point. v11A should:
- Performance: equal or higher (no new heavy assets)
- Accessibility: equal or higher (focus indicators, contrast, ARIA labels added)
- Best Practices: equal (no new third-party scripts)
- SEO: equal or higher (better semantic structure)

If any score drops, identify the specific Lighthouse diagnostic and patch in v11A.1.

## Bottom line

v11A's bundle growth is well-bounded and dominated by NEW UI surface that the dev explicitly asked for (premium-looking homepage). No new dependencies, no new heavy imports, no animation libraries. Performance optimizations from v10.1, v10.11, v10.14 are all intact.

**Estimated runtime impact**: imperceptible. The redesign should feel snappier, not slower, because:
- Fewer DOM nodes per product card (better-organized markup)
- CSS gradients instead of image-based heros (no image fetch)
- Same lazy-loaded product images as before
- Same Phase 10 health-check cache
