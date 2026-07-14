# Phase 11A — Responsive Test Report

Per dev §14 + §18.5.

## Methodology

This report is a **static analysis** of the v11A markup against the dev's required breakpoints. I cannot perform live browser testing in this sandbox; the dev's §17 manual verification is the live confirmation gate. The static analysis below identifies whether the markup is *structurally capable* of rendering correctly at each breakpoint — actual rendering must be verified in a browser.

## Required breakpoints (per dev §14)

| Width | Device class | Tailwind breakpoint |
|---|---|---|
| 320px | iPhone SE (1st gen) | `< sm` (default mobile) |
| 375px | iPhone SE (2nd/3rd) | `< sm` |
| 390px | iPhone 12/13/14 | `< sm` |
| 414px | iPhone Plus/Max | `< sm` |
| 768px | iPad portrait | `md` |
| 1024px | iPad landscape, small laptop | `lg` |
| Desktop | 1280px+ | `xl`/`2xl` |

## Surface-by-surface analysis

### Homepage (`Welcome.tsx`)

#### Hero section
| Breakpoint | Behavior |
|---|---|
| 320–414 | Single column, headline `text-4xl` (36px), dual CTAs stacked via `flex-wrap`, illustration hidden via `lg:block`. Trust micro-row collapses to 2-col grid. |
| 768 | Headline `text-5xl`, CTAs side-by-side. Illustration still hidden (`hidden lg:block`). 3-col trust row. |
| 1024+ | Headline `text-6xl`, illustration column appears in 5/12 grid alongside text 7/12. Decorative blobs unaffected (absolute-positioned, pointer-events-none). |

Layout-shift risk: hero illustration card is `rotate-2` with transform-origin; rotate doesn't trigger reflow. No layout shift.

Overflow risk: `overflow-hidden` on the section + decorative blobs constrained to absolute positions inside. Section width = viewport width via `container-app`. No horizontal overflow.

#### Trust indicators
- Mobile (320–639): `grid-cols-2` with `TrustBadge` rendering icon + text in horizontal row (`flex items-start`).
- Desktop (1024+): `grid-cols-4` with `TrustBadge` switching to vertical centered layout via `sm:flex-col sm:items-center sm:text-center`.

#### Featured categories
- Mobile: `grid-cols-2` (2 tiles per row).
- 640+: `sm:grid-cols-3`.
- 1024+: `lg:grid-cols-6` (full 6-tile row).
- Cap at 12 tiles (`top_categories.slice(0, 12)`), so even on small screens the section doesn't grow indefinitely.

#### Featured products
- Mobile: `grid-cols-2`.
- 640+: `sm:grid-cols-3`.
- 1024+: `lg:grid-cols-4`.
- 8 cards (`slice(0, 8)`) cap.
- `ProductCard` uses `aspect-square` for images — fixed ratio = NO layout shift while images load.

#### Deals banner
- Stacks vertically below 1024 (`flex-col lg:flex-row`).
- CTA button stays full-width-ish on mobile (`shrink-0` prevents text overlap on flex children).

#### Services section
- Card layout: `grid lg:grid-cols-2 gap-8 items-center`.
- Mobile: text first, illustration column second (stacked).
- Desktop: 50/50 with illustration on the right.

#### How it works
- Mobile: `grid-cols-1` (each step full width).
- 640+: `sm:grid-cols-3` (3 steps in a row).

#### Vendor CTA
- Mobile: stacks (`flex-col lg:flex-row`).
- Desktop: text left, button right.

### StorefrontLayout

#### Header
| Breakpoint | Visible elements |
|---|---|
| 320–414 | Logo (`size-9`) + brand name (`hidden sm:block` — actually visible at 414 too since sm = 640 in Tailwind). Mobile cluster: cart icon + hamburger. **Brand name HIDDEN below 640px** — only the colored logo box shows. This is intentional to save horizontal space. |
| 640 | Brand name appears, search bar still hidden (`hidden md:flex`). |
| 768+ | Search bar appears as full center bar. Desktop user cluster (wishlist + cart + account dropdown OR sign-in/register) shown. |
| 1024+ | Secondary nav row visible (Products/Services/Deals + top 5 categories + vendor CTA). Header height grows to `h-20`. Top utility bar visible. |

Touch targets: all icon buttons are `size-10` (40px) — falls short of the WCAG 2.5.5 ideal 44×44px but exceeds the AA minimum (24px). Acceptable; for a polished release the buttons should grow to `size-11` (44px) — noted in known limitations.

#### Mobile drawer
- Width: `w-[85%] max-w-sm` — fits within any viewport ≥ 320px.
- Scrollable: `overflow-y-auto` on the aside.
- Phase 10 v10.6 Categories collapsible PRESERVED exactly (`categoriesOpen` state, ChevronDown rotation).
- All nav items have `min-h` ≥ 40px (`py-2.5` = 10px top + 10px bottom + text height ≈ 40px).

#### Footer
- Mobile: `grid` defaults to single column (all 4 columns stacked).
- 1024+: `lg:grid-cols-4` (4 columns side-by-side).
- Bottom bar: `flex-col sm:flex-row` (timestamp + trust micro-row stack on mobile, inline above).

### Overflow guard

Phase 10 v10.3's global mobile overflow guards (`body { overflow-x: clip }`, max-width image rules, table-responsive helpers) remain in `resources/css/app.css` and apply to all v11A surfaces.

## Specific anti-overflow measures

| Risk | Mitigation |
|---|---|
| Long product titles | `line-clamp-2 min-h-[2.5rem]` in `ProductCard` |
| Long vendor names | `truncate max-w-[120px]` on user name pill; `truncate` on vendor link |
| Long category names | `truncate max-w-[140px]` on desktop nav category links |
| Decorative blobs in hero | `absolute inset-0` inside `overflow-hidden` section parent + `pointer-events-none` |
| Cart count > 99 | `{cartCount > 99 ? '99+' : cartCount}` in both desktop and mobile cart badges |
| RTL text mirroring | `start:`/`end:` Tailwind logical utilities throughout (no `left:`/`right:`) |

## What still needs live browser verification

The dev's §17 manual verification covers these — I list them so they're explicit:

1. **Touch target sizes on iOS Safari**: WCAG 2.5.5 requires 44×44px for primary interactive targets. The `size-10` icon buttons may need increasing.
2. **Sticky header behavior under scroll** on iOS Safari (rubber-band scroll edge case).
3. **Account dropdown on touch devices** — uses CSS `:hover`; mobile users open the drawer instead. Desktop hover-only is intentional but should be confirmed.
4. **Mobile drawer focus trap** — when the drawer opens, focus should be trapped inside. Currently not implemented; v11A.1 enhancement.
5. **Reduced-motion** behavior: the `@media (prefers-reduced-motion: reduce)` rule applies globally. Live test: macOS System Settings → Accessibility → Display → Reduce motion ON, reload, confirm hover-lift and drawer transitions are instant.

## Recommended live verification

The dev should run through the homepage at each of: 320px (DevTools mobile iPhone SE), 768px (iPad portrait), 1024px (iPad landscape), 1440px (desktop). For each, confirm:

- No horizontal scroll
- All sections visible and proportional
- Header search bar accessible and functional
- Mobile drawer opens/closes via hamburger
- v10.6 Categories collapsible inside drawer still works
- ProductCard images load with fixed aspect (no layout jump as they appear)
- Hover states work on desktop
- Tap targets feel comfortable on mobile

If any of these fail, v11A.1 can patch — the design system tokens are the slow-changing surface; tweaks to specific component sizes are quick.
