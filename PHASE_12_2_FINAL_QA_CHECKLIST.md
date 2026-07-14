# Phase 12.2 — Final Functional QA Checklist

Manual smoke tests to run on staging (with production-like data) before flipping DNS. Sign each item with initials + date. Two engineers should sign the final row.

## Public (unauthenticated)

- [ ] `/` — homepage renders in English; hero + featured products visible; no console errors
- [ ] `/` — homepage renders in Arabic (locale switcher); RTL layout correct; text wraps by word not character
- [ ] `/` — mobile 320px: no horizontal scroll; menu toggles; no letter-by-letter wrapping
- [ ] `/products` — product listing renders; filters work; pagination works
- [ ] `/products/{slug}` — product detail renders; images visible; add-to-cart works (redirects to login)
- [ ] `/search?q=widget` — search returns relevant results
- [ ] `/search?q=قميص&locale=ar` — Arabic search returns relevant results
- [ ] `/services` — services listing renders
- [ ] `/vendors/{slug}` — vendor storefront renders
- [ ] Footer links — all resolve to real pages
- [ ] Header search bar — works from any page
- [ ] Locale switcher — persists across page loads
- [ ] Currency switcher (KWD/USD/AED/PKR) — persists; prices update
- [ ] Mobile menu (< 768px) — opens, all items reachable
- [ ] `/robots.txt` — returns 200
- [ ] `/sitemap.xml` — returns 200 with XML
- [ ] `/favicon.ico` — returns 200

## Customer (authed)

- [ ] `/register` — creates account; email verification sent
- [ ] Email verification link — activates account
- [ ] `/login` — authenticates
- [ ] `/forgot-password` — sends reset email; link works within 60 min; second use fails
- [ ] `/logout` — signs out; back button doesn't reveal authed content
- [ ] `/wishlist` — add + remove products
- [ ] `/cart` — add product; update quantity; remove; total updates
- [ ] `/cart` — apply valid coupon; total updates; invalid coupon rejected
- [ ] `/checkout` — full flow with COD works end-to-end
- [ ] `/checkout` — full flow with online payment (staging gateway only) works
- [ ] Order confirmation email received
- [ ] `/orders` — order visible; status correct
- [ ] `/orders/{id}` — details correct; items grouped by vendor
- [ ] `/bookings` — service booking flow (if enabled)
- [ ] `/support` — create ticket; reply from vendor visible
- [ ] Address book — add / edit / delete addresses
- [ ] Password change from account settings

## Vendor (authed as approved vendor)

- [ ] `/login` (vendor account) — redirects to `/vendor`
- [ ] `/vendor` — dashboard widgets load; totals are correct
- [ ] `/vendor/products` — list of vendor's own products only (no leaks)
- [ ] `/vendor/products/create` — create new product; images upload
- [ ] `/vendor/products/{id}/edit` — quality badge (Phase 11B.4.2 fix 9) visible
- [ ] `/vendor/orders` — vendor sees only their own order_items
- [ ] `/vendor/orders/{id}` — vendor can update fulfillment status
- [ ] `/vendor/reports` — reports render; intelligence embed (Phase 11B.4.2 fix 8) at top
- [ ] `/vendor/intelligence` — dashboard loads; freshness banner shows last generated timestamp
- [ ] `/vendor/intelligence` — dismiss / snooze on a low-priority alert works
- [ ] `/vendor/settings` — profile updates persist
- [ ] Vendor mobile menu (< 768px) — all sections reachable
- [ ] Digest email arrives (with `digest_emails_enabled=true` in admin)

## Pending / suspended vendor (should be BLOCKED)

- [ ] Pending vendor at `/vendor` → 403 or redirect to onboarding
- [ ] Pending vendor at `/vendor/intelligence` → 403 (Phase 11B.4.2 defect 1 fix)
- [ ] Suspended vendor at `/vendor` → 403
- [ ] Suspended vendor at `/vendor/products` → 403

## Admin (authed as super_admin)

- [ ] `/admin` (Filament) — dashboard widgets load
- [ ] `/admin/products` — all products across all vendors listed
- [ ] `/admin/vendors` — vendor list; approve/reject flow works
- [ ] `/admin/customers` — customer list; masked PII correct
- [ ] `/admin/orders` — order list; can override status
- [ ] `/admin/reports` — aggregate reports render
- [ ] `/admin/site-settings` — Vendor Intelligence tab visible (Phase 11B.4.3 fix 1)
- [ ] `/admin/site-settings` — change `low_stock_threshold`, save, refresh, value persists
- [ ] `/admin/site-settings` — invalid negative threshold shows validation error
- [ ] `/admin/translations` (Filament) — translation workflow: pending → machine_draft → approved → deployed
- [ ] Translation approval — check `vendor_intelligence_summaries.stale_at` is set (Phase 11B.4.3 fix 3)
- [ ] `/admin/recommendations` — data loads
- [ ] `/admin/personalization` — data loads
- [ ] `/admin/vendor-intelligence` — overview page loads

## Authorization audit

- [ ] Guest → `/checkout` → redirected to `/login`
- [ ] Customer → `/vendor` → 403 or redirected
- [ ] Customer → `/admin` → 403 or redirected
- [ ] Vendor → `/admin` → 403 or redirected
- [ ] Vendor A → try to fetch Vendor B's orders → 403
- [ ] Vendor A → try to fetch Vendor B's products → 403
- [ ] Vendor A → try to modify Vendor B's product → 403
- [ ] Any user → `/api/tokens/create` (Sanctum) works only when authed
- [ ] Direct URL to a private file (e.g. `/storage/vendors/1/license.pdf`) → 403 or 404

## Mobile regression

Test at each viewport width. iPhone SE (320), iPhone Pro (390), iPhone Pro Max (430) — DevTools presets:

- [ ] 320px: `/` no horizontal scroll
- [ ] 320px: `/products` cards in single column
- [ ] 320px: `/checkout` all form fields fit
- [ ] 375px: `/vendor` mobile menu works
- [ ] 375px: `/vendor/products` list scrolls smoothly
- [ ] 414px: `/products/{slug}` product images swipeable
- [ ] Long Arabic name in product card — wraps by word
- [ ] Long English name in product card — wraps by word (Phase 11B.3.3 CSS fix)
- [ ] Container padding correct (`px-4 sm:px-6 lg:px-8`) — Phase 11A.2 preserved

## Critical must-pass items

The following are hard-fails for launch:

- [ ] No page returns 500
- [ ] No page returns broken images (missing storage:link)
- [ ] No admin/vendor route is publicly accessible
- [ ] No `admin@marketplace.test` / demo credentials remain
- [ ] `APP_DEBUG=false` — no stack traces to users
- [ ] HTTPS active on all pages; no mixed-content warnings
- [ ] Login works after full deploy (no session driver misconfiguration)
- [ ] Cart preserves across page reloads
- [ ] Currency preferences persist per user
- [ ] Notifications don't leak PII across users

## Sign-off

| Section | Signed by | Date |
| --- | --- | --- |
| Public smoke test | | |
| Customer smoke test | | |
| Vendor smoke test | | |
| Admin smoke test | | |
| Authorization audit | | |
| Mobile regression | | |
| Critical must-pass | | |
| **Final go / no-go** | | |

Two engineers should sign the "Final go / no-go" row. If any critical must-pass is red, do NOT launch — fix and re-run.
