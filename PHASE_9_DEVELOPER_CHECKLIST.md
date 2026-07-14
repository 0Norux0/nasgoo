# Phase 9 — Developer Testing Checklist

Run through this list end-to-end before approving Phase 10. Each step has the exact command and the expected result.

---

## 0. Prerequisites (the v8.6 lesson)

```bash
# Clean the autoload cache so PHP picks up the new classes
rm -rf vendor/composer/autoload_files.php .phpunit.cache bootstrap/cache/*.php

# Extract Phase 9 archive over the current working tree
tar -xzf /path/to/marketplace-phase-9.tar.gz --strip-components=1 --overwrite

# Reinstall PHP autoload
composer dump-autoload -o

# PHP requirement: pdo_pgsql or pdo_mysql installed (Phase 8 v8.6 lesson)
php -m | grep -E 'pdo_(pgsql|mysql)'
```

Expected: at least one `pdo_pgsql` or `pdo_mysql` line printed.

---

## 1. Confirm Phase 9 is deployed

```bash
cat VERSION
```
Expected: `Phase 9`

```bash
php artisan marketplace:version
```
Expected: all 4 ✓ (the 4 stub-independent static defenses), version `Phase 9`.

---

## 2. Database migrations + seed

```bash
php artisan optimize:clear
php artisan migrate:fresh --seed
```

Expected: no errors. Should output:
- "Phase 8: 2 demo services seeded..." (preserved)
- "Phase 9: 2 promotions + 2 coupons (SAVE10, WELCOME5) + 2 reviews + 1 ticket w/ reply seeded." (new)

```bash
php artisan tinker --execute='echo \App\Models\Promotion::count() . "\n";'
php artisan tinker --execute='echo \App\Models\Coupon::count() . "\n";'
php artisan tinker --execute='echo \App\Models\SupportTicket::count() . "\n";'
```

Expected: `2`, `2`, `1` (at minimum).

---

## 3. Pest test suite

```bash
php artisan test --filter='Phase9'
```

Expected: 24 scenarios across 4 files, all pass.

```bash
php artisan test
```

Expected: full suite green (Phase 7 + Phase 8 + Phase 9). No regressions.

---

## 4. TypeScript + frontend build

```bash
npm ci
npm run typecheck
```

Expected: 0 errors.

```bash
npm run build
```

Expected: bundle built cleanly. Verify `public/build/manifest.json` exists.

---

## 5. Smoke test — promotions display

```bash
php artisan serve &
SERVE_PID=$!
sleep 2

# Public deals page
curl -s -o /dev/null -w "%{http_code}\n" http://127.0.0.1:8000/deals
# Expected: 200
```

Manual browser check:
1. Visit `http://127.0.0.1:8000/deals` (no login)
2. See 2 promotion cards with discount badges
3. Click a featured product → redirects to product detail page

---

## 6. Smoke test — coupon application

Log in as `customer@marketplace.test / password`.

1. Add any product to cart
2. Visit `/cart`
3. Enter `SAVE10` in the coupon field
   - Expected: success message, line items show 10% discount, "Coupon: SAVE10" displayed
4. Click "Remove coupon"
   - Expected: discount cleared
5. Enter `WELCOME5` with cart < 20 KWD
   - Expected: error "Your order does not meet the minimum amount"
6. Add more items so cart > 20 KWD, enter `WELCOME5` again
   - Expected: 5 KWD off
7. Try `INVALID123`
   - Expected: error "Coupon code not found"
8. Try `WELCOME5` again after a previous redemption (in another session)
   - Expected: error "You have already used this coupon the maximum number of times"

---

## 7. Smoke test — support tickets (customer)

Still as `customer@marketplace.test`.

1. Click "Support" in the storefront nav
   - Expected: `/tickets` page shows 1 existing demo ticket
2. Click "+ New Ticket"
3. Fill: type=`general_inquiry`, priority=`normal`, subject=`Test`, body=`Test message`
4. Submit
   - Expected: redirect to ticket detail, ticket number visible, 1 message shown
5. Type a reply, submit
   - Expected: 2 messages now, status changed to `pending`
6. Click "Close this ticket"
   - Expected: status becomes `closed`, reply form hidden

---

## 8. Smoke test — support tickets (admin)

Log in as `admin@marketplace.test / password` → Filament admin `/admin`.

1. Navigate to **Support → Support Tickets**
   - Expected: list shows the customer's tickets, filterable by status/priority
2. Open a ticket
3. Update its status (open → answered) via the edit form
   - Expected: save succeeds, status reflected in the list

---

## 9. Smoke test — vendor promotions (approval flow)

Log in as `vendor@marketplace.test / password`.

1. Click "Promotions" in the vendor nav
   - Expected: empty list (or existing demo-vendor promotion)
2. Click "+ New Promotion"
3. Fill the form: title=`My Test Sale`, type=`flash_sale`, discount type=`percentage`, value=`20`
4. Submit
   - Expected: redirect to /vendor/promotions with success "Promotion submitted for approval"
   - In the list, status `Pending`

Now switch to `admin@marketplace.test` → `/admin/promotions`:
1. Find the pending row, click Edit
2. Change Approval Status to `Approved`, save
   - Expected: success
3. Visit `/deals` (logged out or as customer)
   - Expected: the new promotion now appears

---

## 10. Smoke test — vendor coupons

Still as `vendor@marketplace.test`.

1. Click "Coupons" in the vendor nav
2. "+ New Coupon"
3. Fill: code=`VENDOR10`, type=`percentage`, value=`10`, active=`yes`
4. Submit → redirect to list, see the new row

---

## 11. Smoke test — vendor review response

Still as `vendor@marketplace.test`.

1. Visit `/vendor/reviews`
2. Find a review on one of your products
3. POST `/vendor/reviews/{id}/respond` (via the form) with a body
   - Expected: review's `vendor_response` and `vendor_responded_at` set
4. Verify the response renders on the customer-facing product page

---

## 12. Smoke test — vendor tickets scoping

Still as `vendor@marketplace.test`.

1. Click "Tickets" in the vendor nav
   - Expected: only tickets where `vendor_id = your_vendor_id`
2. Try visiting `/vendor/tickets/{some-id-belonging-to-other-vendor}`
   - Expected: 403 (verified by test `vendor only sees tickets assigned to their vendor`)

---

## 13. Regression — every Phase 8 feature still works

| Feature | URL | Expected |
|---|---|---|
| Product listing | `/products` | 200, no services in list |
| Product detail | `/products/{slug}` | 200 (was 500 in v8.6) |
| Service listing | `/services` | 200 |
| Service detail | `/services/{slug}` | 200 with booking widget |
| Customer bookings | `/bookings` | 200, paginated list |
| Vendor bookings | `/vendor/bookings` | 200, with accept/reject actions |
| Wishlist | `/wishlist` | 200 |
| Wallet | `/vendor/wallet` | 200 |
| Payouts | `/vendor/payouts` | 200 |
| Cart | `/cart` | 200, **now with coupon input** |
| Checkout | `/checkout` | 200 |

---

## 14. CI verdict

CI should produce in the summary:

```
## 🎯 Phase 9 Verification Result
### ✅ Phase 9 PASSES — ready to approve Phase 10
```

Confirm in the run logs that all of these stepped green:

- All Phase 7 sub-checks (14)
- All Phase 8 sub-checks (20)
- Phase 9 — promotions/coupons/support_tickets/product_reviews extensions migrated cleanly
- Phase 9 — demo seeder creates SAVE10 + WELCOME5 + 2 promotions + 1 ticket
- Phase 9 — Pest scenarios pass

---

## 15. Stop here

Do not begin Phase 10 until every box above is checked. If any step fails, file a regression report and we'll fix in v9.x before moving on.
