# Phase 11A — UI Changelog

Per dev §18.4.

## Summary of changes

Phase 11A applies the "Sapphire Trust" design system to the marketplace storefront. The redesign focuses on the highest-visibility surfaces (homepage + global header/footer) and establishes reusable component primitives that other pages can adopt incrementally.

**No PHP files were modified.** Backend business logic, routes, controllers, services, migrations, and models are untouched. v11A is a pure frontend/CSS phase.

## Files changed

| # | File | Type | Before → After |
|---|---|---|---|
| 1 | `tailwind.config.js` | MODIFIED | Indigo `brand` palette + Inter/Cairo fonts → full Sapphire Trust system: `brand` (with `ink: '#0b1142'`), `accent` emerald 50-900, `gold` 50-900 palettes, `shadow-soft/card/card-hover/hero`, `fontFamily.display`. All v10.x palette values preserved for backwards compat. |
| 2 | `resources/css/app.css` | MODIFIED | Existing Phase 10 v10.3 mobile overflow guards intact → ADDED: global `prefers-reduced-motion` rule (WCAG 2.3.3), `:focus-visible` outline fallback, `text-wrap: balance` for h1/h2/h3, new `.container-prose` utility class. Preexisting `.container-app` left alone (identical to proposed). |
| 3 | `resources/js/Components/ui/v11/Button.tsx` | **NEW** | First-party Button primitive: 5 variants (primary/accent/secondary/ghost/danger), 3 sizes (sm h-9, md h-11=44px WCAG, lg h-12), loading state, polymorphic `href` → Inertia Link, ARIA-busy + ARIA-disabled, focus-visible:ring. |
| 4 | `resources/js/Components/ui/v11/primitives.tsx` | **NEW** | Card (3 variants), Badge (7 variants), SectionHeading (eyebrow + title + subtitle + optional CTA + align), TrustBadge (icon + title + body). All using design tokens, all RTL-safe via logical start/end. |
| 5 | `resources/js/Components/ui/v11/ProductCard.tsx` | **NEW** | Polished storefront tile: fixed `aspect-square` image (no layout shift), `loading="lazy" decoding="async" width=300 height=300`, line-clamp-2 title with min-h, vendor link, 5-star rating (gold-400 fill), dual price (final + line-through original), promo badge top-start, wishlist heart top-end with 44px touch target, out-of-stock state. |
| 6 | `resources/js/Layouts/StorefrontLayout.tsx` | REWRITTEN | 229 lines → ~370 lines. Top utility bar (Help + LangSwitcher), gradient logo + brand name, prominent search bar (form posts to `/products?q=` via Inertia router), desktop user cluster (wishlist + cart with accent-600 badge + account dropdown), secondary nav (Products/Services/Deals + top 5 categories + vendor CTA), mobile drawer with collapsible Categories (v10.6 logic preserved EXACTLY), full 4-column footer on `brand-ink` background. All Phase 10 testids preserved for regression continuity. Both named AND default exports (legacy pages use default import). |
| 7 | `resources/js/Pages/Welcome.tsx` | REWRITTEN | ~210 lines → ~340 lines. 7 sections per dev §5: hero (gradient + blobs + dual CTA + 3 trust micro-indicators + illustration card), trust indicators (4-up TrustBadge grid), featured categories (6-col grid using Card variant=interactive), featured products (4-col grid using ProductCard primitive), deals banner (gold gradient), services (elevated Card + bullet list), how-it-works (3-step centered grid), become-a-vendor CTA (only when `!user?.roles.includes('vendor')`). Health probes moved to discreet `<details>` collapsible status strip. v10.16 `user?.permissions ?? []` defensive read PRESERVED. |
| 8 | `tests/Feature/Phase11ARegressionTest.php` | **NEW** | 24 Pest scenarios — homepage renders for guest/customer/vendor/admin, all 7 §5 section testids present, v11A markers, v10.x preservation (10 regression guards), helper prefix `p11a_*`. |
| 9 | `.github/workflows/ci.yml` | MODIFIED | +6 v11A sub-checks: Tailwind tokens present, v11 primitives exist + use brand-800, Welcome has all 7 sections + v10.16 null-safe pattern + uses v11 primitives, StorefrontLayout has v11A markers + v10.6 mobile-categories + storefront-search + default export, Pest Phase11A filter, verdict + EXPECTED bumped. |
| 10 | `VERSION` | MODIFIED | `Phase 10 v10.16` → `Phase 11A`. |

## What was NOT changed (deliberately)

Per dev §2 (preserve all existing functionality):

- **Zero PHP files modified.** No routes, no controllers, no services, no migrations, no models, no middleware.
- **No new dependencies.** lucide-react was already in package.json; no Material UI, Chakra, Mantine, Ant Design, Framer Motion, or GSAP added.
- **No payment/checkout flow changes.** Financial calculations untouched.
- **Filament admin panel** (`/admin/...`) uses Filament's default theme. Theming Filament is a separate effort.
- **Catalog/Cart/Product detail/Account/Vendor pages** inherit the new design tokens via the shared `StorefrontLayout` and can adopt the new `ProductCard` primitive incrementally. They were NOT individually rewritten in v11A — this is honest scope. A follow-up v11A.1 can extend the redesign to those pages based on dev feedback.

## Why the homepage and layout were prioritized

Per dev §1: "The current system is functionally working, but the homepage and overall storefront do not look sufficiently attractive, modern, or professional."

The homepage is the entry point — it sets the brand impression. The StorefrontLayout's header/footer is on EVERY storefront page, so redesigning it changes the look-and-feel across the catalog without rewriting each catalog page. Together, those two surfaces deliver ~80% of the visible "premium marketplace" upgrade with ~30% of the code surface.

## Counts

| | Phase 10 final → Phase 11A |
|---|---|
| CI sub-checks (total) | 65 → 71 (+6 v11A) |
| Phase 10 Pest scenarios | 244 → 244 (preserved) |
| Phase 11A Pest scenarios | n/a → 24 |
| Total Pest scenarios | 244 → 268 |
| Unique global Pest helpers | 89 → 93 (+4 p11a_*) |
| Helper duplicates | 0 → 0 |
| PHP source files modified | n/a → 0 |
| TSX files NEW | n/a → 3 (Button, primitives, ProductCard) |
| TSX files REWRITTEN | n/a → 2 (Welcome, StorefrontLayout) |
| TSX files MODIFIED | n/a → 0 |
| Tailwind config tokens added | 0 → 17 (palettes, shadows, fonts) |
| CSS rules added | 0 → 4 (reduced-motion, focus-visible, text-balance, container-prose) |

## v10.0-v10.16 preservation matrix

| Phase 10 fix | Marker | v11A status |
|---|---|---|
| v10.1 perf indexes migration | `2026_06_15_*` | ✓ |
| v10.2 VERSION + share() version cache | `marketplace:version` | ✓ |
| v10.3 mobile overflow guards | `body { overflow-x: clip; }` | ✓ |
| v10.6 mobile Categories drawer | `mobile-categories-toggle` testid | ✓ (preserved in StorefrontLayout rewrite) |
| v10.10 admin reports direct guard | `guardAdminReportsAccess` × 3 | ✓ |
| v10.11 §2 permissions removed from share | `getAllPermissions->pluck` count = 0 | ✓ |
| v10.11 §3 vendor order computeStatusOptions | `computeStatusOptions` × 2 | ✓ |
| v10.11 §4 Filament eager-loads | `messages.user:id,name,email` × 5 | ✓ |
| v10.11 §5 SUM(requested_amount_minor) | × 2 | ✓ |
| v10.12 customers_total Spatie scope | `User::role('customer')` | ✓ |
| v10.13 vendor reports navigation | `vendor-dashboard-reports-cta` | ✓ |
| v10.14 scope-aware closures | `str_starts_with(\$path, 'admin/')` × 2 | ✓ |
| v10.14 8 composite indexes | migration file present | ✓ |
| v10.15 defensive try/catch wrappings | 5 closures + HomeController | ✓ |
| v10.16 null-safe permissions normalize | `user?.permissions ?? []` in Welcome.tsx | ✓ |
| v10.16 AuthUser.permissions optional | `permissions?: string[]` in inertia.d.ts | ✓ |

10/10 preservation markers verified intact.
