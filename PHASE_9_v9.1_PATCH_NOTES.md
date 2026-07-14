# Phase 9 v9.1 — Correction Package

**Status:** Targeted correction on top of Phase 9 v9.0. Pending CI verification.
**Scope:** 7 real bugs from the developer report fixed at their root cause. No new features beyond the bug fixes. 6 new CI sub-checks, 11 new Pest scenarios.

---

## The 7 bugs reported by your developer

| # | Symptom | Root cause | Fix |
|---|---|---|---|
| 1 | `BindingResolutionException: [$s] was unresolvable` when opening Filament coupon form | `fn ($s) => strtoupper(trim((string) $s))` in `CouponResource.php`. Filament 3 injects closure parameters BY NAME for the documented list (`$state`, `$record`, `$component`, `$get`, `$set`, `$livewire`, `$context`, `$operation`, ...). `$s` isn't in that list and has no type hint, so the container couldn't resolve it. | `fn (?string $state): string => strtoupper(trim((string) $state))`. The parameter is now typed AND uses the recognized name. |
| 3 | "No coupon code field in cart or checkout" | I added the `POST /cart/coupon` + `DELETE /cart/coupon` controller endpoints in v9.0 but never wired a UI form into `Cart/Show.tsx`. The customer had nowhere to type a code. | Added a `CartCouponForm` sub-component to `Cart/Show.tsx` with two modes: input+apply when no coupon is applied, and the code+remove-button when one is. Also extended `CartController::present()` to expose `coupon` (code + discount) and `payable` (subtotal − discount). |
| 4 | "Normal checkout works, but coupon checkout cannot be tested" | `CheckoutService` had `$discount = 0; // Phase 5+ — promo codes` as a hard-coded TODO. The cart's `coupon_id` and `discount_minor` were never read at checkout time. | `CheckoutService::placeOrder()` now re-validates the cart's coupon **server-side** via `CouponValidator::validate()` (never trusts the cart's stored `discount_minor` — a malicious client could mutate it), records `coupon_id`/`coupon_discount_minor`/`coupon_code` on the order, and creates a `CouponUsage` row to enforce per-user limits on the NEXT order. |
| 5 | "Reviews cannot be tested because orders cannot be confirmed/delivered" | `VendorOrderController` only had `ship()`. The `OrderLifecycleService` had `confirm` + `markDelivered` since Phase 5, but there was no UI/route to trigger them. | Added `VendorOrderController::confirm()` + `deliver()` methods, registered `POST /vendor/orders/{order}/confirm` + `/deliver` routes, and added "Confirm order" + "Mark delivered" buttons on `Vendor/Orders/Show.tsx`. Buttons gated on status preconditions matching the lifecycle service's allowed transitions. |
| 6 | "No customer review button after delivery" | Order detail page didn't surface review eligibility for delivered items. | `OrderController::present()` now computes `can_review` per item (delivered + product_id + not already reviewed). `Orders/Show.tsx` renders a "Write a Review →" link to the product detail page (which already has the review form from Phase 5) or "✓ Review submitted" if the customer has already reviewed this product. |
| 7 | "Admin support tickets open in edit mode; admins should view and reply" | `SupportTicketResource::getPages()` exposed only `index` + `edit`. The table's default action was `EditAction`. Clicking a row opened a form that let the admin overwrite the customer's `subject` and `body` fields. | Replaced the Edit page with a new `ViewSupportTicket` page that extends `ViewRecord` (not `EditRecord`) and uses an `Infolist` to display every ticket field read-only. The admin replies via a "Reply" header action that calls `SupportTicketService::reply()` to create a new immutable `support_ticket_message` row. Also added Change Status, Change Priority, and Assign header actions. The customer's original subject and body cannot be mutated from this page. `EditSupportTicket.php` deleted. Table default action: `ViewAction`. |
| 9 (was 8 in your numbering) | "Mail/notification safety check fails" | Two parts: (a) verify `.env.example` carries `MAIL_MAILER=log`, (b) verify no Phase 9 code path dispatches mail that would 500 when no transport is configured. | `.env.example` already had `MAIL_MAILER=log` from Phase 7 (verified). All 7 Phase 9 controllers + 3 domain services + 1 seeder block — zero `->notify()`, zero `Mail::`, zero `Notification::send` calls. No mail-related failure can block a Phase 9 action. New CI sub-check makes this property structural. |

Bug #2 ("Coupon creation must work fully") was a consequence of #1 — fixing the `$s` closure unblocks the form.

---

## Files touched in v9.1

```
app/Filament/Resources/CouponResource.php                                    (closure fix)
app/Filament/Resources/SupportTicketResource.php                             (getPages: edit → view, table action: edit → view)
app/Filament/Resources/SupportTicketResource/Pages/ViewSupportTicket.php     (NEW — replaces EditSupportTicket)
app/Filament/Resources/SupportTicketResource/Pages/EditSupportTicket.php     (DELETED)
app/Http/Controllers/CartController.php                                      (present(): expose coupon + payable)
app/Http/Controllers/OrderController.php                                     (present(): compute can_review per item)
app/Http/Controllers/Vendor/VendorOrderController.php                        (add confirm() + deliver())
app/Domain/Order/CheckoutService.php                                         (wire coupon → order snapshot + CouponUsage)
routes/web.php                                                               (register confirm + deliver routes)
resources/js/Pages/Cart/Show.tsx                                             (add CartCouponForm sub-component)
resources/js/Pages/Orders/Show.tsx                                           (Write Review button on delivered items)
resources/js/Pages/Vendor/Orders/Show.tsx                                    (Confirm + Deliver buttons)
tests/Feature/Phase9V91RegressionTest.php                                    (NEW — 11 scenarios)
.github/workflows/ci.yml                                                     (6 new sub-checks)
VERSION                                                                       Phase 9 → Phase 9 v9.1
```

No migrations, no model schema changes, no removed routes. v9.0's data and seed survive untouched.

---

## How to test v9.1 end-to-end (the 16-step flow you specified)

```bash
# Apply v9.1 cleanly (the v8.6 lesson)
rm -rf vendor/composer/autoload_files.php .phpunit.cache bootstrap/cache/*.php
tar -xzf marketplace-phase-9-v9.1.tar.gz --strip-components=1 --overwrite
composer dump-autoload -o
php artisan optimize:clear
php artisan migrate:fresh --seed
php artisan test --filter='Phase9'    # 24 v9.0 + 11 v9.1 = 35 scenarios green
npm ci && npm run typecheck && npm run build
php artisan serve
```

Then in a browser:

1. **Admin creates a coupon** — log in as `admin@marketplace.test / password` → `/admin/coupons` → "+ New". Form opens without exception. Fill code=`TESTNEW`, type=percentage, value=15. Save succeeds.
2. **Customer applies coupon in cart** — log in as `customer@marketplace.test / password`. Add a product. Visit `/cart`. The coupon input is visible at the bottom of the summary. Type `TESTNEW`, click Apply. Discount shows.
3. **Discount appears correctly** — "Coupon (TESTNEW)" line shows `−X.XX KWD`, "Subtotal after discount" line shows the new payable amount.
4. **Customer completes checkout** — click "Proceed to checkout", fill address, submit. Order detail page shows the coupon line.
5. **Order stores coupon snapshot** — verify in DB: `select coupon_id, coupon_code, coupon_discount_minor from orders order by id desc limit 1` — all three populated.
6. **Vendor confirms the order** — log in as `vendor@marketplace.test / password` → `/vendor/orders/{id}`. Click "Confirm order". Status moves.
7. **Vendor marks shipped** — "Mark items shipped" button. Click it.
8. **Vendor marks delivered** — "Mark delivered" button appears once shipped. Click it.
9. **Customer sees Write Review** — back as customer at `/orders/{id}`, the delivered item shows "Write a Review →" link.
10. **Customer submits review** — click it, lands on the product page with the review form (Phase 5). Submit. Returns to product page with "Pending moderation" badge.
11. **Admin moderates review** — admin → `/admin/product-reviews` → Approve.
12. **Customer creates ticket** — `/tickets/create` → submit.
13. **Admin opens ticket in VIEW mode** — admin → `/admin/support-tickets` → click a row. Opens read-only view, not an edit form. The subject and body are text, not editable inputs.
14. **Admin replies without editing original** — click "Reply" header action → form for the reply body only → submit. Original subject + first message unchanged.
15. **Customer sees admin reply** — `/tickets/{id}` shows the new admin message in the thread.
16. **Mail transport** — verified by code grep: no Phase 9 code path dispatches mail.

---

## Architectural decisions in v9.1

### Server-side coupon revalidation at checkout (security)
The cart's `discount_minor` column is a session cache for UX. It is **never trusted** at order placement. `CheckoutService` re-runs `CouponValidator::validate()` with the freshly-loaded cart + user, and computes the discount from scratch. If the coupon has expired between cart-apply and checkout, it's silently dropped (order placed without discount) rather than failing the entire checkout — the customer's cart contents shouldn't get stuck in limbo because of timing.

### Ticket view, not edit (data integrity)
The fix isn't merely "remove the edit form" — it's "the customer's original message is immutable as a matter of system contract". The new `ViewSupportTicket` page uses an Infolist (Filament's read-only display primitive), and replies are inserts into `support_ticket_messages`, never updates to the `support_tickets.subject` or first-message `body`. This means an admin literally CANNOT alter what the customer wrote, even if they wanted to — there's no code path that does it.

### Review eligibility computed at the server (no client-side trust)
`OrderController::present()` calls `ProductReview::where(user_id, product_id, ...)->exists()` for each item. The client can't bypass this — the `can_review` flag and the "Write a Review" link visibility are entirely determined by what the server returns. If the customer manually navigates to `/products/{slug}` and tries to POST a duplicate review, the existing Phase 5 `ReviewController` enforces the unique index `reviews_user_product_orderitem_unique` and 422s.

### `MAIL_MAILER=log` as a structural property
v9.1 doesn't just verify `.env.example` carries the line — it adds a CI sub-check that no Phase 9 code path dispatches mail at all. This is stronger than "make mail tolerant of failure": even if `MAIL_MAILER` is misconfigured in production, no Phase 9 endpoint can 500 because of it.

---

## v9.x defenses re-run

| Defense | Result on v9.1 changes |
|---|---|
| v8.2 identifier length | Unchanged — no new migrations |
| v8.3 schema-vs-runtime-data | 0 hits — no `stock_minor`/`manage_stock` introduced |
| v8.4 form-errors-key | `Cart/Show.tsx`'s new `CartCouponForm` has `form.errors.code` matching the `useForm({ code: '' })` key |
| v8.5 duplicate global helpers | 3 new `p91_` helpers, 0 collisions with existing 30 (33 total now) |
| v8.6 VERSION + marketplace:version | VERSION bumped to `Phase 9 v9.1` |
| v8.7 controller return type | 58 `Inertia::render`-returning methods scanned project-wide, all compatible (v9.1 didn't change any return types) |

### 6 new v9.1 CI sub-checks

1. **Filament closure parameter injection check** — scans every closure passed directly to a Filament setter, fails on untyped params with names outside Filament's documented injection list. Project-wide. (The static analog of the runtime `BindingResolutionException`.)
2. **Cart page coupon UI markers present** — Cart/Show.tsx must reference `CartCouponForm`, `data-testid="cart-coupon-input"`, `data-testid="cart-coupon-apply"`, and `form.post('/cart/coupon'`. Static.
3. **SupportTicketResource is view-mode, not edit-mode** — `getPages()` has `view` key, no `edit` key. `ViewSupportTicket` extends `ViewRecord`. `EditSupportTicket` class doesn't exist.
4. **Vendor order lifecycle routes registered** — `vendor.orders.confirm`, `vendor.orders.deliver`, `vendor.orders.ship` all present in the route table.
5. **`.env.example` has `MAIL_MAILER=log` AND no Phase 9 code dispatches mail** — both must be true.
6. **Phase 9 v9.1 Pest scenarios pass** — `php artisan test --filter='Phase9V91'`.

### Counts

| | Phase 9 v9.0 | Phase 9 v9.1 |
|---|---|---|
| Phase 9-specific CI sub-checks | 3 | **9** (3 + 6) |
| Phase 9-specific Pest scenarios | 24 | **35** (24 + 11) |
| Grand total phase-specific CI sub-checks | 37 | **43** |

---

## Sandbox verification (the v8.6 lesson — verify shipped archive directly)

1. ✅ Real tsc clean on v9.1-touched files (Cart/Show, Orders/Show, Vendor/Orders/Show — TS6133=0, TS6196=0, 0 real type errors)
2. ✅ Project-wide Filament closure audit — 0 untyped non-injectable parameters
3. ✅ Cart/Show.tsx has CartCouponForm + 3 test IDs
4. ✅ SupportTicketResource exposes `view` only; `ViewSupportTicket` extends `ViewRecord`; `EditSupportTicket` class removed
5. ✅ VendorOrderController has `confirm()` + `deliver()` methods; routes registered
6. ✅ OrderController::present computes `can_review` from delivered_at + product_id + ProductReview existence
7. ✅ CheckoutService records `coupon_id`/`coupon_discount_minor`/`coupon_code` on order + creates CouponUsage row
8. ✅ `.env.example` has `MAIL_MAILER=log`; 0 mail dispatch calls in Phase 9 code paths
9. ✅ 33 unique global helpers across `tests/Feature/`, 0 duplicates (3 new `p91_` helpers)
10. ✅ CI YAML parses; 9 Phase 9 sub-checks now (3 v9.0 + 6 v9.1)
11. ✅ Phase 9 v9.0 work intact in archive (every model, controller, route, Filament resource, React page from v9.0 unchanged unless v9.1 explicitly touched it)

---

## v9.1 STOPS HERE — do not start Phase 10

Approval requires:
1. `cat VERSION` prints `Phase 9 v9.1` on the deployed system
2. `php artisan marketplace:version` prints all 4 ✓
3. The 16-step manual flow above passes end-to-end
4. CI shows `✅ Phase 9 v9.1 PASSES — ready to approve Phase 10`
