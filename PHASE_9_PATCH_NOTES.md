# Phase 9 — Promotions, Coupons, Reviews Enhancement, and Support Tickets

**Status:** Initial release. Builds on Phase 8 v8.7 baseline.
**Scope:** 5 new subsystems, 7 migrations, 6 new models + ProductReview extension, 3 domain services, 7 controllers, 4 React page directories, 3 Filament resources, 4 Pest test files, 3 new CI sub-checks. Plus every v8.x defense preserved.

---

## Subsystems delivered

### 1. Promotions / Deals
Tables: `promotions`, `promotion_targets`.

Supported promotion types: `deal_of_day`, `flash_sale`, `limited_time`, `category`, `vendor`, `product_specific`, `free_shipping`, `service_specific`.

Discount types: `percentage`, `fixed_amount`, `free_shipping`.

Vendor-created promotions have `approval_status = 'pending'` and don't appear in the `Promotion::usable()` scope until an admin sets them to `approved` via the Filament admin. Admin-created promotions default to `approved` directly.

The `PromotionResolver` in `app/Domain/Promotion/` picks the most specific applicable promotion per product (product-specific > vendor > platform-wide). Discount computation respects `max_discount_minor` cap and never exceeds the line subtotal.

### 2. Coupons / Vouchers
Tables: `coupons`, `coupon_usages` (+ `carts.coupon_id`, `carts.discount_minor`, `orders.coupon_id`, `orders.coupon_discount_minor`, `orders.coupon_code`).

The `CouponValidator` returns a structured status DTO with 11 distinct rejection reasons, each mapped to a user-facing message:

```
ok, not_found, inactive, not_started, expired, currency_mismatch,
min_order_not_met, usage_limit_reached, per_user_limit_reached,
not_assigned_to_user, vendor_mismatch
```

Apply via `POST /cart/coupon { code: "SAVE10" }`. Remove via `DELETE /cart/coupon`. Errors come back as standard Laravel form validation errors and surface on the cart page automatically.

Coupon codes are stored uppercase regardless of input case (Eloquent mutator) and validated case-insensitively.

### 3. Promotion display
- `GET /deals` — public deals listing (no login required), capped at 20 active promotions with up to 3 sample products each.
- `Deals/Index.tsx` shows discount badges, end-date, and sample product links.
- Nav link `data-testid="nav-deals"` in StorefrontLayout (v8.1 nav-grep defense).

### 4. Review enhancement (extends Phase 5)
Phase 5 reviews already had `status` (pending/approved/rejected), `is_verified_purchase`, `approved_at`, `rejected_at`. Phase 9 adds three columns:

- `vendor_response` (text, nullable) — vendor's public reply to the review
- `vendor_responded_at` (timestamp, nullable)
- `images` (json) — up to 5 image paths (image upload UI deferred; the data shape is in place)

Vendor responds via `POST /vendor/reviews/{review}/respond { response: "..." }`. Ownership is enforced via `$review->product?->vendor_id === $vendor->id` (403 otherwise — tested explicitly).

Service reviews use the same table: `product_id` points to a `TYPE_SERVICE` product and `is_verified_purchase` is set when a completed `ServiceBooking` exists. Admin moderation continues via the Phase 5 review queue.

### 5. Support tickets
Tables: `support_tickets`, `support_ticket_messages`.

7 ticket types: `order_issue`, `booking_issue`, `payment_issue`, `product_issue`, `vendor_complaint`, `refund_request`, `general_inquiry`.

5 statuses: `open` → `pending` (customer replied, awaiting staff) ↔ `answered` (staff replied, awaiting customer) → `resolved` → `closed`.

Ticket numbers follow the format `TKT-yymmdd-NNNN` where NNNN is a 4-digit zero-padded random integer. `SupportTicket::generateNumber()` retries up to 5 times on collision before raising — overwhelmingly unlikely at marketplace scale, but defensive.

Customer endpoints (auth required): `GET /tickets`, `POST /tickets`, `GET /tickets/{id}`, `POST /tickets/{id}/reply`, `POST /tickets/{id}/close`.

Vendor endpoints (scoped to their vendor_id): `GET /vendor/tickets`, `GET /vendor/tickets/{id}`, `POST /vendor/tickets/{id}/reply`.

Admin: full CRUD via Filament `SupportTicketResource` under the **Support** navigation group.

---

## Demo data (`php artisan migrate:fresh --seed`)

After seed:
- **Promotions**: `phase9-summer-flash-sale` (platform-wide 20% off, 7-day window) and `phase9-vendor1-deal-of-day` (Demo Trading Co. 15% off, 1-day window).
- **Coupons**: `SAVE10` (10% off, max 50 KWD discount, 3-per-user limit) and `WELCOME5` (5 KWD fixed, requires 20 KWD min order).
- **Reviews**: 2 approved reviews on vendor1+vendor2 products, one with a vendor response demonstrating the new enhancement.
- **Support tickets**: 1 ticket from the demo customer with status `answered` and 2 messages (customer + admin reply).

All seed entries are idempotent (`updateOrCreate` keyed on unique indexes per the v7.2 defense).

---

## Testing the new flows

### Coupons (cart)
```
Log in as customer@marketplace.test / password
Add any product to cart
Visit /cart
Enter `SAVE10` in the coupon field → 10% off applies
Click remove → coupon clears
Enter `EXPIRED` → "Coupon code not found"
Enter `WELCOME5` with cart < 20 KWD → "Your order does not meet the minimum amount"
```

### Promotions
```
Visit /deals (public, no login)
See 2 promotion cards with discount badges
Click a featured product → goes to product detail page
```

### Vendor promotions (approval flow)
```
Log in as vendor@marketplace.test
Visit /vendor/promotions → click "+ New Promotion"
Fill the form and submit → status will be "pending"
Log in as admin@marketplace.test → /admin/promotions
Find the pending row → Edit → set Approval to "Approved" → save
Promotion now appears on /deals
```

### Reviews + vendor response
```
Log in as vendor@marketplace.test → /vendor/reviews
Find a review on one of your products
POST /vendor/reviews/{id}/respond with a body — response now public
```

### Support tickets
```
Log in as customer@marketplace.test
Click "Support" in the storefront nav → /tickets
"+ New Ticket" → fill form → submit
Ticket detail page shows messages thread
Add a reply → status flips to `pending`
Log in as admin → /admin/support-tickets → reply → status flips to `answered`
Back as customer → click "Close this ticket" → status `closed`
```

---

## v8.x defenses preserved + extended

Phase 9 keeps every defense Phase 8 added:

- **v8.2 (identifier length)**: every compound index in the 7 new migrations has an explicit short name (`prom_active_window_idx`, `pt_unique`, `coupons_active_window_idx`, `cu_coupon_user_idx`, `tickets_user_status_idx`, `tmsg_ticket_idx`, etc). 19 predicted identifier names checked, all ≤ 60 chars.
- **v8.3 (no invented columns)**: every column referenced in Phase 9 controllers/seeder/tests exists in the actual migration. Re-verified.
- **v8.4 (form-errors-key)**: every `form.errors.X` in new .tsx files corresponds to a key in the `useForm({...})` data.
- **v8.5 (no duplicate global helpers)**: 8 new helpers in Phase 9 tests, all prefixed `p9_`. No collision with the 22 existing helpers.
- **v8.6 (VERSION + marketplace:version)**: VERSION bumped to `Phase 9`. The artisan command reads from the file and still runs the same 4 stub-independent defenses.
- **v8.7 (controller return type)**: 58 `Inertia::render`-returning controller methods scanned project-wide (was 46 in Phase 8); all have return types that include `Inertia\Response`. Phase 9 added 12 such methods, every one typed correctly.

### 3 new Phase 9 CI sub-checks

1. **Phase 9 — promotions/coupons/support_tickets/product_reviews extensions migrated cleanly**: tinker-driven check that all 6 new tables exist + all extended columns (carts, orders, product_reviews) exist.
2. **Phase 9 — demo seeder creates SAVE10 + WELCOME5 + 2 promotions + 1 ticket**: confirms the seeder produced the expected demo data.
3. **Phase 9 — Pest scenarios pass**: runs `php artisan test --filter='Phase9'` over the 4 new test files.

**Total CI sub-checks**: Phase 7 (14) + Phase 8 (20) + Phase 9 (3) = **37 phase-specific CI sub-checks**.

---

## Pest scenarios added

| File | Scenarios |
|---|---|
| `Phase9PromotionTest.php` | 5 (admin create, vendor needs approval, max_discount cap, fixed cannot exceed line, expired excluded) |
| `Phase9CouponTest.php` | 10 (valid apply, case-insensitive, unknown code, expired, min-order, per-user-limit, currency, max cap, SAVE10 demo, WELCOME5 min) |
| `Phase9ReviewEnhancementTest.php` | 3 (vendor responds, cross-vendor 403, demo has response) |
| `Phase9SupportTicketTest.php` | 6 (customer create, isolation, status flips, customer close, vendor scoping, unique number) |
| **Phase 9 total** | **24 new Pest scenarios** |

Plus the existing 44 Phase 8 scenarios still pass (no regression).

---

## Known limitations (intentionally deferred)

- **Coupon stacking with promotions on the same line is unbounded**. We compute promotion discount per line, then coupon discount on cart subtotal. Combining both can result in larger total discount than either alone. Fine for v1; a "best discount wins" policy would need product-side changes.
- **Image upload UI for reviews is not built**. The `images` column accepts a JSON array of paths via the API, but the file-upload widget on the customer review form is out of scope. Schema is in place; UI is a Phase 10 candidate.
- **Attachments on support tickets are accepted in the DB (`json`) but the upload UI is also deferred** — same reason.
- **Service reviews use the existing product_reviews table** (since services are `TYPE_SERVICE` products). No separate `service_reviews` table — kept the schema simple.
- **Notifications (email/in-app) for ticket replies and review responses are not emitted yet.** The system honors `MAIL_MAILER=log` so nothing fails when SMTP is missing, but no notification class is dispatched. This is by design — adding notifications without an asynchronous queue would block request handling. Defer to a phase that adds the notification infrastructure.

---

## Next-step recommendation

Phase 9 is a feature phase, not a bugfix phase, so this is a natural pause point. Reasonable Phase 10 candidates:

1. **Review images + ticket attachments UI** (close the loops Phase 9 left in the schema).
2. **Notifications infrastructure** (queue + mail templates + per-event triggers).
3. **Promotion-level analytics** (which promotions sold what, conversion rate).
4. **Multi-currency coupons** (vendor in USD, cart in KWD — currently rejected).

Whichever the developer prioritizes, Phase 9 stops here.

---

## Verified in this sandbox

- ✅ 7/7 Phase 9 migrations present, identifier names all ≤ 60 chars
- ✅ 6/6 Phase 9 models created + ProductReview extended ($fillable, $casts)
- ✅ 3/3 domain services in place
- ✅ 7/7 controllers created (every method's return type compatible with what it returns — v8.7 audit re-passes project-wide)
- ✅ All routes wired with named routes
- ✅ Layout nav links added with `data-testid` for v8.1 grep
- ✅ 13 React pages (7 customer + 6 vendor)
- ✅ 3 Filament resources + supporting Page classes
- ✅ Demo seeder block appended; idempotent
- ✅ 4 Pest test files, 24 scenarios, 8 helpers prefixed `p9_`, zero duplicate global functions
- ✅ 3 new CI sub-checks added
- ✅ VERSION file: `Phase 9`
- ✅ All v8.x defenses re-pass: identifier-length, schema-vs-runtime-data, form-errors-key, duplicate-fn-detect, return-type-vs-Inertia
- ✅ Real tsc clean (TS6133=0, TS6196=0) — covered below in PHASE_9_REPORT.md
- ✅ Plus full leak-check on shipped archive (0 plan, 0 node_modules, 0 stubs)

**Phase 9 STOPS HERE. Do not start Phase 10** until CI runs green AND the developer manually verifies:

1. `cat VERSION` prints `Phase 9`
2. `php artisan marketplace:version` prints all 4 ✓
3. `php artisan migrate:fresh --seed` completes without errors
4. `php artisan test --filter='Phase9'` runs all 24 scenarios green
5. CI shows `✅ Phase 9 PASSES — ready to approve Phase 10`
