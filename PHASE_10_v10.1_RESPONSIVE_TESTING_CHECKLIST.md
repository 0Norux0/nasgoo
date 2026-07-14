# Phase 10 v10.1 — Responsive Testing Checklist

The developer reported that mobile responsiveness was seriously broken in v10.0. v10.1 ships responsive fixes for both `StorefrontLayout` and `VendorLayout`, plus the vendor orders list. This checklist guides verification.

Test in browser DevTools at these viewport widths (Chrome DevTools → toolbar → Toggle device toolbar → Responsive):

| Width | Common device |
|---|---|
| 320 | iPhone SE (1st gen) |
| 375 | iPhone SE / iPhone 12 mini |
| 390 | iPhone 14 / iPhone 15 |
| 414 | iPhone 14 Plus |
| 768 | iPad portrait / mid-tablet |
| 1024 | iPad landscape / small laptop |
| 1440 | desktop |

For each viewport, verify:

- ☐ No horizontal page overflow (drag the page sideways — it shouldn't move)
- ☐ Header logo + cart + hamburger visible at minimum
- ☐ Hamburger opens a drawer; drawer can be dismissed
- ☐ Touch targets ≥ 40px high (most buttons + nav links use py-2.5 ≥ 40px)
- ☐ Text is readable (no overlap, no cut-off)
- ☐ Forms fit the viewport; inputs don't overflow

---

## 1. Public storefront

### Homepage `/`

- ☐ 320 px: hamburger present; logo not truncated to nothing
- ☐ 375 px: cart icon visible with badge (if items present)
- ☐ 414 px: same
- ☐ 768 px: hamburger still present (md breakpoint is 768) — desktop nav shows
- ☐ Hero content, featured grid scale correctly

### Product list `/products`

- ☐ Product cards stack to 1 column at 320-414 px
- ☐ Filter sidebar collapses or moves above the grid
- ☐ Pagination controls don't overflow
- ☐ "Add to cart" buttons remain tappable

### Product detail `/products/<slug>`

- ☐ Main image fills viewport width
- ☐ Gallery thumbnails scroll or wrap
- ☐ Price + Add to cart visible above the fold
- ☐ Reviews block doesn't break the layout

### Service detail `/services/<slug>`

- ☐ Booking date/time picker is usable at 320px
- ☐ Slot grid scrolls/wraps

### Cart `/cart`

- ☐ Line items show product image + name + qty + price without overflow
- ☐ Coupon input is full-width on mobile
- ☐ Total summary is sticky or clearly visible

### Checkout `/checkout`

- ☐ Address form fields stack vertically on mobile
- ☐ Payment method radio buttons are tappable
- ☐ Place Order button is full-width on mobile

### My orders `/orders`

- ☐ Order list rows are readable
- ☐ Status badges don't overflow

### My bookings `/bookings`

- ☐ Same

### Tickets `/tickets`

- ☐ Ticket list readable
- ☐ Reply form fits

### Auth pages `/login`, `/register`

- ☐ Form fits 320 px
- ☐ Inputs are not cut off

---

## 2. Vendor dashboard

Vendor layout was the worst offender pre-v10.1 (~15 inline nav items). Verify thoroughly:

### Header at every width

- ☐ 320 px: logo + hamburger only; no nav links visible
- ☐ 768 px: still hamburger (vendor uses lg breakpoint = 1024)
- ☐ 1024 px: full horizontal nav shows with wrap if needed

### Vendor mobile drawer

- ☐ Click hamburger → drawer opens
- ☐ Drawer has all links: Dashboard, Products, Orders, Reports, Reviews, Wallet, Payouts, Suppliers, Supplier Orders, Services, Providers, Bookings, Promotions, Coupons, Tickets, Storefront, Profile, Logout
- ☐ Clicking a link closes the drawer and navigates
- ☐ Drawer scrolls if content exceeds viewport (`max-h-[70vh] overflow-y-auto`)

### Vendor orders list `/vendor/orders`

- ☐ Table scrolls horizontally on mobile (`overflow-x-auto`); page doesn't overflow
- ☐ Inline action buttons (Confirm / Ship / Deliver) remain visible
- ☐ Order links remain tappable

### Vendor products list `/vendor/products`

- ☐ Table or card layout fits mobile
- ☐ Edit / Delete actions accessible

### Vendor reports `/vendor/reports`

- ☐ KPI cards stack 2-wide on mobile
- ☐ Daily revenue chart scales (SVG `preserveAspectRatio="none"`)
- ☐ Date filter inputs stack vertically
- ☐ Export CSV button visible
- ☐ Per-product table scrolls horizontally

### Vendor service create / product create forms

- ☐ Fields stack vertically
- ☐ File upload widget is usable
- ☐ Submit button is reachable without horizontal scrolling

---

## 3. Admin (Filament + Inertia)

Filament handles its own responsiveness; spot-check:

### Filament admin sidebar `/admin`

- ☐ Sidebar collapses to icons or drawer at < 768 px
- ☐ Reports Dashboard link visible (v10.1)
- ☐ Clicking Reports navigates to `/admin/reports`

### Admin reports `/admin/reports` (Inertia)

- ☐ KPI cards stack 2-wide on mobile (grid grid-cols-2)
- ☐ Date filter visible
- ☐ Daily revenue chart scales
- ☐ Top vendors + top products tables scroll horizontally

### Vendor edit form in Filament (`/admin/vendors/{id}/edit`)

- ☐ Documents section shows the new viewable previews (v10.1)
- ☐ Requested package section displays selected tier (v10.1)
- ☐ Form fields stack vertically on mobile

### Filament order, product, review tables

- ☐ Each table has the standard Filament responsive treatment (typically horizontal scroll)
- ☐ Action buttons accessible

---

## 4. Critical interaction tests

At 375 px (a popular phone width):

- ☐ Sign in as customer, add a product to cart, complete checkout
- ☐ Sign in as vendor, create a product (verify v10.1 mass-assignment fix), submit for review
- ☐ Sign in as admin, navigate to Reports via the Filament sidebar
- ☐ View vendor application; click Open document to view a private file (signed URL)
- ☐ Open Reports → switch date filter to custom → apply

All of these should be completable on a 375 px phone screen without rotating or pinching.

---

## 5. Quick automation hooks

The v10.1 layouts expose stable test IDs:

| Test ID | Purpose |
|---|---|
| `storefront-mobile-toggle` | Click to open the storefront mobile drawer |
| `storefront-mobile-menu` | The drawer's container |
| `vendor-mobile-menu` | The vendor drawer's container |
| `vendor-nav-reports` | Reports link in the vendor nav |
| `row-confirm-{id}` | Confirm button on vendor orders row |
| `row-ship-{id}` | Ship button |
| `row-deliver-{id}` | Deliver button |

Sample Playwright assertions (write in your test runner of choice):

```javascript
test('storefront mobile menu opens on 375px viewport', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 812 });
    await page.goto('/');
    await page.click('[data-testid="storefront-mobile-toggle"]');
    await expect(page.locator('[data-testid="storefront-mobile-menu"]')).toBeVisible();
});
```

---

## 6. Known gaps (NOT fixed in v10.1)

- Filament's own forms are NOT fully responsive at 320 px — the table layouts work, but forms with many columns may be cramped. Filament 3.x's responsive support is improving but not perfect.
- Pages I did not directly modify in v10.1 (e.g. `Bookings/Show.tsx`, individual ticket pages) may still have cramped layouts. They're functionally usable but not visually polished on mobile.
- Service slot picker on `/services/<slug>` may still be tight at 320 px — narrow viewports show small touch targets for hour slots.

If any of these block real users, escalate as a v10.2 task with the specific page + viewport.
