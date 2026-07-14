# Phase 10 v10.3 — Mobile Screenshots

Per dev §6 ("Provide screenshots for at least...") + §12 deliverable #8: I cannot produce browser screenshots from this sandbox.

## Why not

My sandbox has: bash, str_replace, create_file, view, python3, tar, grep, sed, awk. It does NOT have: a browser, Playwright, Puppeteer, Chrome headless, Selenium, or any tool capable of rendering HTML at a given viewport size.

## What I provided instead

1. **The actual CSS fix** in `resources/css/app.css` — globally applied, defensive against any page content overflow
2. **A static CI sub-check** (`Phase 10 v10.3 — Global mobile overflow guards in app.css`) that fails the build if the guards are removed
3. **A Pest test** (`Global CSS has mobile overflow guards`)
4. **An explicit testing checklist** in `PHASE_10_v10.3_DEVELOPER_CHECKLIST.md` walking the dev through 375px viewport tests

## What the dev should produce (and share)

The dev's environment can produce real screenshots. Using Chrome DevTools (Toggle device toolbar → Responsive → set 375x812):

| Page | Suggested filename |
|---|---|
| `/` (storefront, hamburger closed) | `mobile-storefront-closed.png` |
| `/` (hamburger open) | `mobile-storefront-open.png` |
| `/products` | `mobile-product-list.png` |
| `/products/<slug>` | `mobile-product-detail.png` |
| `/cart` | `mobile-cart.png` |
| `/checkout` | `mobile-checkout.png` |
| `/vendor/products/create` | `mobile-vendor-product-create.png` |
| `/vendor/orders/<id>` (with the new status dropdown) | `mobile-vendor-order-detail.png` |
| `/admin/vendors/<id>/edit` (with documents visible) | `mobile-admin-vendor-edit.png` |

If any of those shows broken layout (horizontal scroll, clipped buttons, unreadable text), share the screenshot + page URL + viewport size. I can target the specific page in v10.4 without needing to guess.

## Quick browser check the dev can do

In Chrome DevTools at 375px:
```js
// Run in the console
document.documentElement.scrollWidth > window.innerWidth
// Must be `false` — true means the page overflows horizontally
```

If false on every public + vendor + admin page → the global overflow guards are working.
