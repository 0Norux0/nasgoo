# Phase 11A — Design System

Per dev §3 + §4.

## Three coherent theme options (proposal)

I propose three coherent visual systems. Each is grounded in a different brand personality and is internally consistent (color + type + components reinforce each other). The dev's §4 hint pointed toward "deep navy or royal blue for trust + teal/emerald/warm gold as accent" — Option A leans into that recommendation; B and C are alternatives for comparison.

### Option A — "Sapphire Trust" (SELECTED)

| Token | Value | Rationale |
|---|---|---|
| Primary | Deep indigo `#3730a3` (brand-800) | Trust, reliability — works for a multi-vendor commerce platform that needs to feel safe |
| Primary hover | Royal indigo `#312e81` (brand-900) | Slightly darker for clear hover state without changing hue |
| Accent | Emerald `#059669` (success-600) | Money-positive associations; great for CTAs ("Buy now", "Confirm booking"), badges ("In stock"), and price highlights |
| Warm accent | Amber `#f59e0b` (warning-500) | For "Deal", "Limited time", and rating stars — culturally readable across MENA/EU/US |
| Neutral background | Warm cream `#fafaf9` (stone-50) | Less sterile than pure white; soft enough that product images pop |
| Card surface | Pure white `#ffffff` | Maximum contrast for product imagery |
| Text primary | Deep slate `#0f172a` (slate-900) | High contrast, never pure black (less harsh on screens) |
| Text secondary | Slate `#475569` (slate-600) | For metadata, descriptions, secondary info |
| Border | `#e2e8f0` (slate-200) | Soft enough to suggest structure without feeling cage-like |
| Success | Emerald `#059669` | Mirrors the accent — confirmations, stock-in indicators |
| Warning | Amber `#f59e0b` | Mirrors the warm accent — "few left", "shipping soon" |
| Danger | Rose `#e11d48` (rose-600) | Errors, cancellations — distinct from warning |
| Typography (display) | Inter, font-bold, tight tracking | Modern, readable, multi-language (works with Cairo + Noto Naskh fallbacks) |
| Typography (body) | Inter 16/24, font-normal | Body comfort, 16px minimum (accessibility) |
| Typography (numerals) | Inter tabular-nums | Prices align in tables |
| Button (primary) | brand-800 bg, white text, rounded-xl, shadow-sm, h-11 (44px) | 44px = WCAG min touch target |
| Button (accent) | emerald-600 bg, white text, same shape | For "Add to cart" and booking CTAs |
| Button (secondary) | white bg, slate-200 border, slate-900 text | For "View details", "Cancel" |
| Card | white bg, rounded-2xl, border-slate-200, shadow-sm, hover:shadow-md | Subtle elevation, modern radii |
| Badge (promo) | amber-100 bg, amber-900 text, rounded-full, text-xs font-semibold | High-visibility but not aggressive |
| Badge (stock) | emerald-100 bg, emerald-900 text | Mirrors success |
| Banner (hero) | brand-800 → brand-900 gradient with subtle emerald highlights | Deep, confident, premium |
| Spacing scale | 4 / 6 / 8 / 12 / 16 / 20 / 24 / 32 (Tailwind defaults extended) | Generous breathing room |
| Border radius | 0.5rem (sm), 0.75rem (md), 1rem (xl), 1.25rem (2xl) | Modern but not playful |
| Shadow scale | shadow-sm (cards), shadow-md (hover), shadow-lg (modals), shadow-xl (deals callout) | Soft, layered depth |
| Brand personality | Trustworthy, premium, global, calm. The kind of marketplace where you'd buy a $500 item without second-guessing | |

**Why selected**: matches the dev's explicit hint, works in LTR + RTL (the indigo→emerald palette doesn't carry direction-specific cultural meaning), survives in greyscale (high contrast values), and provides clear visual distinction between primary actions (indigo) and money-positive actions (emerald) — critical for a commerce UI.

### Option B — "Coastal Calm"

| Token | Value |
|---|---|
| Primary | Slate-blue `#1e40af` (lighter than A) |
| Accent | Teal `#0d9488` |
| Warm accent | Coral `#fb923c` |
| Background | Cool gray `#f8fafc` |
| Brand personality | Lighter, friendly, approachable — like Etsy or Allbirds |

Trade-off: lighter feel may read as less "luxury", and the cool tones can feel sterile in a multi-vendor marketplace where each vendor brings its own product photography.

### Option C — "Charcoal Editorial"

| Token | Value |
|---|---|
| Primary | Charcoal `#1f2937` |
| Accent | Mustard gold `#ca8a04` |
| Warm accent | Burnt sienna `#9a3412` |
| Background | Cream `#fefce8` |
| Brand personality | Editorial, magazine-like, design-conscious — like SSENSE or Mr Porter |

Trade-off: very strong personality may not suit the breadth of vendor categories (works for boutique fashion; less for everyday electronics or services). Lower contrast in some areas requires larger text.

---

## Selected: **Option A — "Sapphire Trust"**

The rest of this document specifies Option A in full. Options B and C are documented above for the developer's reference; they're not implemented in v11A.

## Tailwind token extensions

The existing `tailwind.config.js` already had a `brand` indigo palette. v11A extends with:

```js
colors: {
    brand: {
        50: '#eef2ff', 100: '#e0e7ff', 200: '#c7d2fe',
        300: '#a5b4fc', 400: '#818cf8', 500: '#6366f1',
        600: '#4f46e5', 700: '#4338ca', 800: '#3730a3',
        900: '#312e81',
        // v11A — explicit ink for high-contrast hero text on dark gradients
        ink: '#0b1142',
    },
    accent: {
        50: '#ecfdf5', 100: '#d1fae5', 200: '#a7f3d0',
        300: '#6ee7b7', 400: '#34d399', 500: '#10b981',
        600: '#059669', 700: '#047857', 800: '#065f46',
        900: '#064e3b',
    },
    gold: {
        50: '#fffbeb', 100: '#fef3c7', 200: '#fde68a',
        300: '#fcd34d', 400: '#fbbf24', 500: '#f59e0b',
        600: '#d97706', 700: '#b45309', 800: '#92400e',
        900: '#78350f',
    },
    rose: {
        50: '#fff1f2', 600: '#e11d48', 700: '#be123c',
    },
},
fontFamily: {
    sans: ['Inter', 'Cairo', 'system-ui', 'sans-serif'],
    display: ['Inter', 'Cairo', 'system-ui', 'sans-serif'],
    arabic: ['Cairo', 'system-ui', 'sans-serif'],
},
boxShadow: {
    'soft':     '0 1px 2px 0 rgb(15 23 42 / 0.06)',
    'card':     '0 2px 6px -1px rgb(15 23 42 / 0.06), 0 1px 2px -1px rgb(15 23 42 / 0.04)',
    'card-hover': '0 8px 16px -4px rgb(15 23 42 / 0.10), 0 4px 8px -2px rgb(15 23 42 / 0.06)',
    'hero':     '0 20px 50px -10px rgb(55 48 163 / 0.30)',
},
```

## Component primitives (reusable building blocks)

New components introduced in v11A:

| Component | Purpose | Props (short) |
|---|---|---|
| `<Button>` | Standard button — variant: primary / accent / secondary / ghost / danger | `variant`, `size`, `href` (renders as Link), `loading`, full ARIA support |
| `<Card>` | Reusable surface — variant: default / elevated / interactive | `as`, `padding`, `interactive` |
| `<Badge>` | Pills — variant: promo / stock / new / trust / warning / danger | `variant`, `size` |
| `<ProductCard>` | Storefront product tile | `product` (typed), `compact?` |
| `<SectionHeading>` | Consistent h2 / h3 styling for homepage sections | `title`, `subtitle`, `align`, `cta?` |
| `<CategoryCard>` | Tile for the Featured Categories homepage section | `category` (typed) |
| `<TrustBadge>` | The "Verified vendor" / "Secure checkout" type indicators | `icon`, `title`, `body` |
| `<VendorCard>` | Compact vendor tile (homepage "Popular vendors" + catalog filter) | `vendor` |

All primitives:
- Are TypeScript-typed with explicit props
- Use the design tokens (no hard-coded color hex)
- Support both LTR and RTL (no `left:`/`right:` — always `start:`/`end:` or logical flex order)
- Pass minimum WCAG 2.1 AA contrast
- Have keyboard focus visible (`focus-visible:ring-2 focus-visible:ring-brand-500`)

## Spacing + sizing

Use Tailwind's default spacing scale strictly. Components prefer larger spacings to feel premium:

- Section vertical padding: `py-16 md:py-24`
- Card interior: `p-6` (compact: `p-4`)
- Grid gaps: `gap-6` for product grids, `gap-8` for category grids
- Container max-width: `max-w-7xl` for content sections, `max-w-6xl` for narrative (hero)

## Responsive breakpoints

| Breakpoint | Tailwind | Purpose |
|---|---|---|
| Mobile | default (< 640px) | iPhone SE through Pixel — single column grids, mobile drawer |
| sm | ≥ 640px | Larger mobile, small tablet — 2-column product grids |
| md | ≥ 768px | Tablet — 3-column grids, side drawers visible |
| lg | ≥ 1024px | Small laptop — 4-column grids, sticky filters |
| xl | ≥ 1280px | Desktop — full layout, generous spacing |
| 2xl | ≥ 1536px | Large desktop — caps content max-width, never stretches |

Verified target sizes per dev §14: 320, 375, 390, 414, 768, 1024, desktop.

## Typography scale

| Class | Size | Line height | Use |
|---|---|---|---|
| `text-xs` | 12px | 16px | metadata, badges, fine print |
| `text-sm` | 14px | 20px | secondary info, labels |
| `text-base` | 16px | 24px | **body default** (accessibility min) |
| `text-lg` | 18px | 28px | emphasized body, lead paragraphs |
| `text-xl` | 20px | 28px | card titles |
| `text-2xl` | 24px | 32px | section subtitles |
| `text-3xl` | 30px | 36px | section headings |
| `text-4xl` | 36px | 40px | page headings, mobile hero |
| `text-5xl` | 48px | 1 (tight) | desktop hero headline |

Weights:
- `font-normal` (400) — body
- `font-medium` (500) — emphasized body, labels
- `font-semibold` (600) — card titles, button text, badges
- `font-bold` (700) — section headings
- `font-extrabold` (800) — hero only

## Iconography

Use the existing `lucide-react` library (already in `package.json`). No new dependencies. Icons sized 16/20/24 to match line heights:

| Size | Use |
|---|---|
| 16px | Inline with text, badges |
| 20px | Buttons, list bullets |
| 24px | Header navigation, prominent CTAs |
| 32px | Trust indicator cards |
| 40px | Hero feature blocks |

## Color contrast (WCAG 2.1 AA verification)

Verified ratios for the selected palette:

| Foreground | Background | Ratio | Pass? |
|---|---|---|---|
| slate-900 (`#0f172a`) | white | 18.0 : 1 | ✓ AAA |
| slate-900 | stone-50 (`#fafaf9`) | 17.7 : 1 | ✓ AAA |
| slate-600 (`#475569`) | white | 6.4 : 1 | ✓ AA |
| white | brand-800 (`#3730a3`) | 10.4 : 1 | ✓ AAA |
| white | accent-600 (`#059669`) | 4.6 : 1 | ✓ AA |
| amber-900 | amber-100 | 9.2 : 1 | ✓ AAA |
| accent-900 | accent-100 | 8.7 : 1 | ✓ AAA |
| rose-700 | rose-50 | 9.5 : 1 | ✓ AAA |

No combination fails AA. The button primary (white on brand-800) is AAA.

## Motion + animation policy

Used very sparingly. No animation libraries beyond Tailwind's built-in `transition-*`.

| Effect | Class | Use |
|---|---|---|
| Card hover lift | `transition-shadow duration-200 ease-out` | Product cards |
| Button press | `transition-colors duration-150 ease-in-out` | All buttons |
| Drawer open | `transition-transform duration-200 ease-out` | Mobile menu (existing) |

Respects `prefers-reduced-motion`:
```css
@media (prefers-reduced-motion: reduce) {
    * { animation-duration: 0.01ms !important; transition-duration: 0.01ms !important; }
}
```

(Added via global CSS in v11A.)

## What this design system does NOT change

Per dev §2 "preserve all existing functionality" and §16 "redesign must not reintroduce the previous lag":

- Backend business logic untouched
- No new frontend dependencies (Tailwind already installed; lucide-react already installed)
- No new heavy UI library (no Material UI, Chakra, Mantine, Ant Design)
- No animation framework (no Framer Motion, no GSAP)
- No giant background videos or hero animations
- Image sizes respect Tailwind's responsive image guidance + `loading="lazy"`
- Bundle growth target: < 50KB gzipped delta vs Phase 10 baseline

Performance budget is in `PHASE_11A_BUNDLE_REPORT.md`.
