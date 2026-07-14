# Phase 10 v10.4 — Mobile Test Report

Per dev §J: test mobile at 320/375/390/414/768px and provide screenshots if browser tooling is available.

## Sandbox capability

I have no browser, no headless Chrome, no Playwright, no Puppeteer. I cannot produce screenshots.

## What v10.x shipped — verified statically

| Fix | Source file | Static verification |
|---|---|---|
| Storefront hamburger (v10.1) | `resources/js/Layouts/StorefrontLayout.tsx` | `grep -c 'storefront-mobile-menu' ...` = 1 |
| Vendor hamburger (v10.1) | `resources/js/Layouts/VendorLayout.tsx` | `grep -c 'vendor-mobile-menu' ...` = 1 |
| Reports in baseItems (v10.2) | `resources/js/Layouts/VendorLayout.tsx` | `grep -c 'Reports moved into baseItems' ...` = 1 |
| Global overflow guard (v10.3) | `resources/css/app.css` | `grep -c 'overflow-x-hidden' ...` = 1; `grep -c 'max-width: 100vw' ...` = 1 |
| Responsive media (v10.3) | `resources/css/app.css` | `grep -nE 'max-width: 100%; height: auto' ...` returns the rule |
| Long-text wrap (v10.3) | `resources/css/app.css` | `grep -nE 'overflow-wrap: anywhere' ...` returns the rule |

## What the dev should test (per §J)

Chrome DevTools → Toggle device toolbar → set viewport, visit each URL, run in console:

```js
document.documentElement.scrollWidth > window.innerWidth
```

Must output `false` on every page.

| Viewport | Pages to test | Expected |
|---|---|---|
| 320px | `/`, `/products`, `/cart` | no horizontal scroll |
| 375px | all of the above + `/vendor`, `/vendor/orders/{id}`, `/admin/vendors/{id}/edit` | no horizontal scroll; dropdowns visible; status dropdown tappable |
| 390px | same | same |
| 414px | same | same |
| 768px | same | desktop-ish, sidebar visible |

If any page outputs `true`, share the URL + viewport + a screenshot. I can target the specific overflowing element in v10.5.

## What I refuse to claim

- I have NOT verified mobile renders visually correctly
- I have NOT confirmed the hamburger drawer animates
- I have NOT verified Tailwind classes resolve to expected CSS after Vite compilation
