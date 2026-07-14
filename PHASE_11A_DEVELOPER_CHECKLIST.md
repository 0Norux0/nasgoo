# Phase 11A — Developer Verification Checklist

Per dev §17 + §18.10.

## Deploy

```bash
cd /var/www/marketplace
sha256sum -c marketplace-phase-11A-ui-redesign.tar.gz.sha256
tar -xzf marketplace-phase-11A-ui-redesign.tar.gz --strip-components=1 --overwrite
```

## §17 Required commands

```bash
php artisan optimize:clear
php artisan test --filter=Phase11A    # → 24 v11A scenarios pass
php artisan test                       # → full suite passes (244 + 24 = 268)
npm ci
npm run typecheck                      # MUST PASS — zero errors
npm run build                          # MUST SUCCEED
```

Then restart Laravel/PHP-FPM and hard-refresh the browser.

## §17 Manual verification walkthrough

Open DevTools → Console + Network → "Disable cache". For each step below, confirm: page renders correctly, zero Console errors, no horizontal scroll on mobile.

### A. Homepage as guest
- [ ] Open `/` while signed out.
- [ ] Hero section visible with "Discover. Buy. Book." headline + dual CTAs.
- [ ] 4-up trust indicators row visible below hero.
- [ ] Featured categories grid renders (2-col mobile, 6-col desktop).
- [ ] Featured products grid renders (2-col mobile, 4-col desktop).
- [ ] Deals banner visible (gold gradient).
- [ ] Services section visible.
- [ ] How it works 3-step grid visible.
- [ ] "Become a vendor" CTA visible.
- [ ] Footer 4-column layout visible.
- [ ] System status `<details>` collapsed by default at bottom of page.

### B. Homepage after customer login
- [ ] Sign in as `customer@marketplace.test` / `password`.
- [ ] Redirected to `/`.
- [ ] Page renders identically EXCEPT:
  - Header shows account dropdown (avatar + name).
  - "Become a vendor" CTA still visible (customer is not a vendor).
  - System status strip may show "N permission(s) granted" inside the `<details>` (v10.16 null-safe pattern).
- [ ] **Zero Console errors.** (This was the v10.16 launch-blocker; CI guards it but visual confirmation is the gate.)

### C. Products catalog (`/products`)
- [ ] Header search bar visible (desktop) — type a query, submit.
- [ ] Search redirects to `/products?q=...`.
- [ ] Products grid renders. Pages NOT yet redesigned (inherit StorefrontLayout chrome only).

### D. Product detail (`/products/{slug}`)
- [ ] Page loads, uses StorefrontLayout chrome (new header + footer).
- [ ] Cart / wishlist / variant selection / customization flows all work (Phase 10 functionality preserved).

### E. Services (`/services`, `/services/{slug}`)
- [ ] Service listing loads. Booking flow works.

### F. Cart (`/cart`)
- [ ] Cart icon in header shows accent-600 badge with item count.
- [ ] Cart page renders.
- [ ] Coupon entry + financial calculations unchanged.

### G. Checkout
- [ ] Existing checkout flow works (NOT visually redesigned in v11A — inherits design tokens only).

### H. Customer account
- [ ] `/orders` renders.
- [ ] `/bookings` renders.
- [ ] `/wishlist` renders.
- [ ] `/tickets` renders (support).

### I. Vendor dashboard
- [ ] Sign in as `vendor@marketplace.test`.
- [ ] `/vendor` renders. NOT visually redesigned in v11A.
- [ ] "Vendor dashboard" CTA in header (not "Become a vendor").

### J. Vendor orders + Vendor Reports
- [ ] `/vendor/orders` renders. Status dropdown works (v10.11 §3).
- [ ] `/vendor/reports` renders (v10.13 nav).

### K. Admin Reports
- [ ] Sign in as `admin@marketplace.test`.
- [ ] `/admin/reports` renders.

### L. Mobile navigation
- [ ] Resize browser to 375px width (iPhone SE simulation in DevTools).
- [ ] Header collapses: only logo + cart icon + hamburger visible.
- [ ] Tap hamburger — drawer slides in from end side.
- [ ] Search input present at top of drawer.
- [ ] Tap "Categories" — list expands (v10.6 preservation).
- [ ] Tap a category — drawer closes, navigates to `/products?category=...`.
- [ ] Tap backdrop (or × button) — drawer closes.

### M. Categories (preserved from v10.6)
- [ ] Desktop: top 5 categories visible in secondary nav row.
- [ ] Mobile: categories collapsible in drawer.

### N. Support tickets (`/tickets`)
- [ ] Renders.
- [ ] Phase 10 v10.11 §4 lazy-load fix preserved.

## Visual regression confirmations

These must look DIFFERENT from Phase 10 (this is the point of v11A):

- [ ] Homepage hero: gradient brand-800 → brand-ink with decorative blurred blobs (was: small phase pill + simple welcome text).
- [ ] Header: utility bar above + 2-row main header with prominent search (was: single-row dense nav).
- [ ] Footer: dark brand-ink 4-column layout (was: minimal or absent).
- [ ] Product cards: aspect-square images + line-clamped titles + gold ratings + dual prices (was: simpler ad-hoc cards).
- [ ] Buttons: rounded-xl, brand-800 primary / accent-600 accent, focus-visible:ring (was: rounded-md, indigo-600).

## §15 Accessibility quick checks

- [ ] Tab through homepage — every interactive element gets a visible focus ring.
- [ ] System Settings → Accessibility → Reduce Motion ON → reload `/`.
- [ ] Confirm card hover-lifts and drawer transitions are instant.
- [ ] Browser zoom to 200% — content remains usable; no overlapping elements.

## Approval gate

If all the above tick boxes pass, Phase 11A is ready for approval. Open `marketplace-phase-11A-ui-redesign.tar.gz` archive review:

```bash
sha256sum -c marketplace-phase-11A-ui-redesign.tar.gz.sha256
```

## Page inventory (in lieu of screenshots)

Per dev §18.8: I can't take real browser screenshots in this sandbox. The following inventory describes each redesigned surface — pair it with the dev's live walkthrough to confirm intent matches reality.

### Welcome.tsx (homepage)
- **Hero**: full-width gradient `bg-gradient-to-br from-brand-800 via-brand-900 to-brand-ink`. Decorative blurred blobs in accent-500 and brand-500 absolute-positioned. 12-col grid: text occupies cols 1-7, illustration card cols 8-12 (hidden < lg). Headline `text-4xl sm:text-5xl lg:text-6xl font-extrabold` white with the second line in `text-accent-300`. Lede paragraph in `text-brand-100`. Two CTAs: accent green "Shop products" + transparent-white "Browse services". Trust micro-row of 3 indicators with `text-accent-300` icons. Illustration card: rotated 2° → 0° on hover, contains 2×2 grid of placeholder tiles + "Featured today / Premium selection" header.
- **Trust indicators**: white section, `<TrustBadge>` ×4 (Secure / Verified / Flexible delivery / Real human support). Mobile: 2-col, horizontal layout per badge. Desktop: 4-col, vertical centered layout.
- **Featured categories**: slate-50 section. `SectionHeading` ("Browse" eyebrow / "Shop by category" title / count subtitle / "View all" ghost CTA). 6-col grid of `Card variant="interactive"` tiles. Each tile: brand-50 rounded-xl icon container with `Package` icon → brand-700, then category name in font-semibold.
- **Featured products**: white section. `SectionHeading` ("Featured" / "Hand-picked for you" / "See all products" primary CTA). 4-col grid of `ProductCard`. Empty state: `Card` with Search icon + "No featured products yet" + CTA.
- **Deals banner**: full-width `bg-gradient-to-r from-gold-500 to-gold-600`. "Today's offers" eyebrow + "Save big" headline in gold-950. CTA: brand-950-bg button "Shop the deals".
- **Services section**: slate-50. `SectionHeading` ("Beyond products" / "Book trusted services" / "Browse services" primary CTA). `Card variant="elevated"` containing 2-col grid: text + bullet list (✓ icons) on left, abstract accent-100 gradient illustration with Headset icon on right.
- **How it works**: white centered. `SectionHeading` align="center". 3-col grid of `Card variant="default"`: each card has brand-50 rounded-2xl icon, "Step 01/02/03" eyebrow, title, body text.
- **Become a vendor CTA**: brand-50 section. `Card variant="elevated"` with brand-800→brand-ink gradient bg. White text. "For vendors" trust badge + headline + body + accent CTA.
- **System status strip**: slate-100 thin band. `<details>` collapsed by default. Shows the 4 health probe dots when expanded (database / cache / search / storage). Also shows "Signed in as X · N permission(s) granted" using v10.16 safe pattern.

### StorefrontLayout
- **Utility bar (desktop only)**: brand-ink bg, 9px height. "Welcome to {app.name}" + "Global shipping available" on start side. "Help" link + LangSwitcher on end side.
- **Main header**: white, sticky top-0, 80px height desktop. Logo: 40px gradient brand-700→900 square with brand initial, then app.name in `font-display font-bold text-xl`. Center: prominent search with Search icon + "Search products, services, vendors…" placeholder + Search button on end. Right cluster: Wishlist heart icon (10×10 hover bg-slate-100), Cart shopping bag icon with accent-600 badge for count, Account dropdown (avatar circle + name + ChevronDown).
- **Secondary nav (desktop, lg+)**: 44px tall. Products / Services / Deals (gold-700) / divider / top 5 categories / spacer / "Become a vendor" pill (accent-50 bg, accent-700 text).
- **Mobile drawer**: 85% width, max-w-sm, slides in from end. Header bar: "Menu" + close button. Search input. Nav list: Products / Services / Deals / Categories (collapsible per v10.6) / divider / account or sign-in section / language switcher.
- **Footer**: brand-ink bg, 4-col on lg+. Column 1: logo + name + intro paragraph. Column 2: Customer (Orders, Bookings, Wishlist, Support, Sign in). Column 3: Sell on (Become a vendor, Vendor dashboard). Column 4: Marketplace (All products, Services, Today's deals, Sitemap). Bottom bar: copyright + "Secure payments · Verified vendors · Buyer protection".

### ProductCard
- Article element with rounded-2xl + shadow-card → shadow-card-hover on hover.
- Image: aspect-square, object-cover, lazy-loaded with explicit width/height. Empty state: package icon.
- Badges absolute top-start (promo, new) and wishlist top-end (44px touch target — actually 36px in v11A; v11A.1 fix).
- Body: title (line-clamp-2, min-h-[2.5rem] prevents card jumping), vendor link (truncated), star rating (gold-400 fill), spacer, price block (final price + line-through original), out-of-stock label if applicable.

### Theme tokens
- Primary indigo `#3730a3` brand-800 (buttons, links emphasis)
- Accent emerald `#059669` accent-600 (Add to cart, success)
- Gold `#f59e0b` gold-500 (deals, ratings)
- Background warm cream `#fafaf9` (stone-50) — but slate-50 is also used; consistent neutral grays
- Text `#0f172a` slate-900 primary, `#475569` slate-600 secondary

Pair with `PHASE_11A_DESIGN_SYSTEM.md` for the full token spec.
