# Phase 5 v6.1 — Navigation + Lazy-Load Targeted Fix

**Scope:** the six developer-reported regressions in v6.0. Same Phase 5 scope. **Phase 6 is not started.**

---

## Honest root-cause summary

| # | Reported issue | Root cause | Fix |
|---|---|---|---|
| 1 | Wishlist menu missing for customers | `StorefrontLayout.tsx` was last touched before Phase 5 — I built the `/wishlist` route + page + tests but never linked it from the header. | Added a Wishlist link in the auth-gated nav block of `StorefrontLayout.tsx`. |
| 2 | `Attempted to lazy load [actor] on model [OrderEvent]` on online payment | `OrderController::confirm()` eager-loaded `events` but **not** `events.actor`. `present()` reads `$e->actor?->name`. For COD orders only one event exists so Eloquent's strict-mode detector doesn't fire (it needs multiple parents). Online payment creates several events (initiated + captured + status transitions) → lazy-load on actor. Same exact pattern as the v5.7 Product→vendor bug, different relation. | Added `events.actor:id,name` to the confirm() eager-load chain. |
| 3 | No order status actions on admin Order Detail page | `OrderResource` table rows already had Confirm/Ship/Deliver/COD-capture/Transfer-confirm/Cancel/Refund actions, but `ViewOrder` (the detail page) only had `EditAction`. Admins opening "Order Details" couldn't see any lifecycle controls. | Ported all seven row actions to `ViewOrder::getHeaderActions()` with `$this->record` lookups + page-level redirects after each action so the status refresh is visible. |
| 4 | Vendor Reviews menu missing | `VendorLayout.tsx` had `Dashboard / Products / Orders / Profile`. The Phase 5 routes existed; the sidebar wasn't extended. | Added Reviews / Wallet / Payouts links to `VendorLayout.tsx`. |
| 5 | Vendor Wallet menu missing | Same as #4. | Same as #4. |
| 6 | Vendor Payout menu missing | Same as #4. Additionally, the dev's expected URL was `/vendor/payouts`, but the Phase 5 build wired only `/vendor/wallet` (the wallet page already contains the payout request form + history). | Added `/vendor/payouts` and `/vendor/payouts` (POST) as **aliases** for the existing wallet controller — same page, expected URL. |

There's also a layout-consistency fix that wasn't in the brief but came out of audit:

- **`Vendor/Wallet.tsx` and `Vendor/Reviews/Index.tsx`** were originally built using `StorefrontLayout`. Other vendor pages use `VendorLayout`. v6.1 switches both to `VendorLayout` so the vendor sidebar is consistent across vendor pages.

And a UX improvement so non-approved vendors don't see broken links:

- **`vendor_status` added to Inertia shared `auth.user`.** `VendorLayout` now hides Reviews/Wallet/Payouts links unless `auth.user.vendor_status === 'approved'`. The routes themselves were already guarded by `middleware:vendor:approved`; this just keeps the nav from showing links that would bounce the user back to the dashboard.

---

## Files changed

### React (3 edits)
- `resources/js/Layouts/StorefrontLayout.tsx` — wishlist link
- `resources/js/Layouts/VendorLayout.tsx` — Reviews/Wallet/Payouts links + `isApprovedVendor` gating
- `resources/js/Pages/Vendor/Wallet.tsx` — switched to VendorLayout, accepts title
- `resources/js/Pages/Vendor/Reviews/Index.tsx` — switched to VendorLayout, accepts title
- `resources/js/types/inertia.d.ts` — added `vendor_status?: string | null` to `AuthUser`

### PHP (4 edits)
- `app/Http/Controllers/OrderController.php` — `confirm()` now eager-loads `events.actor:id,name`
- `app/Http/Middleware/HandleInertiaRequests.php` — `auth.user.vendor_status` shared prop
- `app/Filament/Resources/OrderResource/Pages/ViewOrder.php` — 7 lifecycle header actions
- `routes/web.php` — `/vendor/payouts` GET + POST aliases

### Tests (1 new file, 16 scenarios)
- `tests/Feature/Phase5V61RegressionTest.php`

### CI
- Verdict bumped to `✅ Phase 5 v6.1 PASSES — ready to approve Phase 6`
- New audit-map row for the regression test
- New verdict-table row summarizing all six fixes
- New CI step **`v6.1 — online-payment confirmation does not lazy-load events.actor`** that places a real `online_mock` order through the HTTP kernel under `Model::shouldBeStrict(true)`, manufactures multiple events (the dev's exact failure condition), and follows the redirect to `/orders/{id}/confirm` — if v6.1's eager-load fix were missing, this CI step returns 500.

### Docs
- `PHASE_5_v6.1_PATCH_NOTES.md` (this file)
- `PHASE_5_REPORT.md` — v6.1 section appended
- `README.md` — header bumped to v6.1
- `TROUBLESHOOTING.md` — six new entries

---

## What v6.1 deliberately does NOT change

- **Phase 5 schema** — no new migrations.
- **Phase 5 business logic** — no model relations changed, no service signatures changed.
- **Phase 6 work** — not started. The same scope as v6.0, just unblocked.

---

## Manual Developer Verification Checklist

After applying v6.1 + `migrate:fresh --seed`:

| # | Step | Expected |
|---|---|---|
| 1 | Sign in as `customer@marketplace.test` | Header shows: Cart · My Orders · **♡ Wishlist** · Sell on Marketplace · Logout. The Wishlist link was missing in v6.0. |
| 2 | Click Wishlist | Opens `/wishlist`, shows 2 demo wishlist items. |
| 3 | Open any product detail page → click the heart icon (top-right of price block) | Toggles wishlist state with no full page reload. |
| 4 | Add a product to cart → checkout → choose **Online (mock)** payment → Place Order | Confirmation page loads cleanly. **No** "Attempted to lazy load [actor] on model [OrderEvent]" error. |
| 5 | Sign in as `vendor@marketplace.test` | Vendor header shows: Dashboard · Products · Orders · **Reviews** · **Wallet** · **Payouts** · Profile · Storefront. The last three were missing in v6.0. |
| 6 | Click Wallet | Opens `/vendor/wallet` with balance breakdown + history. |
| 7 | Click Payouts | Opens `/vendor/payouts` (alias of wallet). Same page content. |
| 8 | Click Reviews | Opens `/vendor/reviews` showing the demo approved review. |
| 9 | Sign in as `pending-vendor@marketplace.test` | Vendor header shows: Dashboard · Products · Orders · Profile · Storefront. Wallet/Payouts/Reviews are **hidden** (the user's vendor profile isn't approved). |
| 10 | As pending vendor, try to navigate to `/vendor/wallet` manually | Bounced to `/vendor` dashboard (middleware:vendor:approved). |
| 11 | Sign in as `admin@marketplace.test` → Admin → Orders → click any order to open Order Details | Header shows up to 7 action buttons: Confirm / Mark shipped / Mark delivered / Mark COD paid / Confirm transfer / Cancel / Refund — visibility depends on current order status. **All were missing in v6.0.** |
| 12 | Open the seeded delivered order in admin | Refund button is visible (paid + delivered). Cancel button is **not** (delivered orders cannot be cancelled). |
| 13 | Open an order in `paid` status as admin → click Confirm → confirm → page redirects with status updated | Order status flips to `confirmed`. Audit log shows the transition. |
| 14 | GitHub Actions on the v6.1 branch | Verdict: `✅ Phase 5 v6.1 PASSES — ready to approve Phase 6`. The new `v6.1 — online-payment confirmation` CI step is green. |

If step 4 still fails: capture the **full** stack trace, not just the headline. With `events.actor` eager-loaded plus the OrderController doing `with(['events.actor:id,name'])`, the only way actor would still lazy-load is from a code path that hands the page a different Order instance.

---

## Stop discipline

Phase 6 has not been started. Reply **"approve Phase 6"** with your chosen scope only after the 14-step checklist above passes and the CI verdict is green.
