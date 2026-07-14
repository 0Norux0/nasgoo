# Phase 9 v9.1 — Developer Testing Checklist

This is the 16-step end-to-end test specified in your bug report, plus the v9.0 regression checks. Walk through it in order. Every step has the expected result and how to verify it.

---

## 0. Apply v9.1 cleanly (the v8.6 lesson)

```bash
# Clean PHP autoload + Filament + view caches
rm -rf vendor/composer/autoload_files.php .phpunit.cache bootstrap/cache/*.php

# Extract v9.1 archive over the v9.0 working tree
tar -xzf /path/to/marketplace-phase-9-v9.1.tar.gz --strip-components=1 --overwrite

# Reinstall PHP autoload
composer dump-autoload -o
```

Verify deploy:

```bash
cat VERSION
# expect: Phase 9 v9.1

php artisan marketplace:version
# expect: all 4 ✓ (the 4 stub-independent static defenses)
```

---

## 1. Database + tests + frontend

```bash
php artisan optimize:clear
php artisan migrate:fresh --seed                     # No errors. Seeder line for Phase 9.
php artisan test --filter='Phase9'                   # 35 scenarios green (24 v9.0 + 11 v9.1)
php artisan test                                     # Full suite green (no regressions)
npm ci
npm run typecheck                                    # 0 errors
npm run build                                        # Bundle written to public/build
php artisan serve &
```

---

## 2. The 16-step end-to-end flow

### Step 1 — Admin creates coupon (BUG #1 + #2 — Filament form opens)

Log in as `admin@marketplace.test / password`.

- Visit `/admin/coupons`
- Click "+ New coupon"
- ✅ **Expected: form opens without `BindingResolutionException`** (v9.0 crashed here)
- Fill: code=`TESTNEW`, discount type=`percentage`, discount value=`15`, active=on
- Save
- ✅ Coupon row appears in the list with code `TESTNEW`

### Step 2 — Customer applies coupon in cart (BUG #3 — coupon UI in cart)

Log in as `customer@marketplace.test / password`.

- Add any product to cart (eg. browse `/products` and click "Add to cart")
- Visit `/cart`
- ✅ **Expected: a "Have a coupon?" input field is visible in the summary aside on the right** (v9.0 didn't have this UI)
- Type `TESTNEW` in the field
- Click "Apply"

### Step 3 — Discount appears correctly

- ✅ Summary aside shows: "Subtotal", "Coupon (TESTNEW) −X.XX KWD", "Subtotal after discount X.XX KWD"
- ✅ Below the input, the coupon code is shown with a "Remove" button
- Try clicking "Remove" — the discount disappears and the input reappears
- Re-apply for the next steps

### Step 4 — Customer completes checkout (BUG #4 — coupon snapshot on order)

- Click "Proceed to checkout"
- Fill the shipping form (if address not already saved)
- Submit
- ✅ Redirects to `/orders/{id}/confirm` (success page)

### Step 5 — Order stores coupon snapshot

```bash
php artisan tinker --execute='echo \App\Models\Order::latest("id")->first()->only(["coupon_id","coupon_code","coupon_discount_minor","subtotal_minor","total_minor"])|json_encode($it=$_)|json_encode(...);'
# OR simpler:
php artisan tinker --execute='dd(\App\Models\Order::latest("id")->first()->toArray());'
```

✅ Expected output includes:
- `coupon_id`: not null
- `coupon_code`: "TESTNEW"
- `coupon_discount_minor`: matches what the cart showed
- `total_minor`: subtotal_minor − coupon_discount_minor (+ shipping if any)

Also check that a `coupon_usages` row was created:

```bash
php artisan tinker --execute='echo \App\Models\CouponUsage::count() . "\n";'
# Expected: ≥ 1 (the customer's usage)
```

### Step 6 — Vendor confirms the order (BUG #5 — confirm button)

Open a private window. Log in as `vendor@marketplace.test / password` (the vendor for the product the customer bought).

- Visit `/vendor/orders/{id}` (you can find the id from `/vendor/orders` list)
- ✅ **Expected: a green "Confirm order" button is visible** (v9.0 didn't have this)
- Click it
- Browser prompts to confirm
- ✅ Order status changes (visible in the page reload)

### Step 7 — Vendor marks the order shipped

- On the same page, the "Mark items shipped" button now becomes available
- Click it
- ✅ Status changes again

### Step 8 — Vendor marks the order delivered (BUG #5 — deliver button)

- Now a teal "Mark delivered" button appears
- Click it
- Confirm the COD-cash prompt
- ✅ `fulfillment_status` becomes `delivered`, `delivered_at` populated

### Step 9 — Customer sees Write Review (BUG #6 — review button)

Switch back to the customer window.

- Visit `/orders/{id}`
- ✅ **Expected: under the delivered item, a "Write a Review →" link is visible** (v9.0 didn't have this)
- Click it
- ✅ Lands on the product detail page with the review form (the form was added in Phase 5)

### Step 10 — Customer submits review

- Pick a rating, type a review, submit
- ✅ Review appears with "Pending moderation" badge
- Visit `/orders/{id}` again
- ✅ The "Write a Review" link is replaced with "✓ Review submitted"

Try clicking the order link again to confirm duplicate prevention:
- The button is gone (already reviewed)

### Step 11 — Admin moderates review (BUG #7 — moderation works)

Switch to admin window.

- Visit `/admin/product-reviews`
- ✅ Pending review visible
- Open the review (View/Edit)
- Approve it
- Switch back to customer view of the product — review now visible with verified-purchase badge

### Step 12 — Customer creates support ticket

- Customer: `/tickets/create`
- Fill: type=`general_inquiry`, priority=`normal`, subject=`Test ticket from v9.1 QA`, body=`This is a test message`
- Submit
- ✅ Redirects to ticket detail with the message visible

### Step 13 — Admin opens ticket in VIEW mode (BUG #8 — critical)

Admin window:

- Visit `/admin/support-tickets`
- ✅ Pending ticket visible
- Click the row (or the eye icon)
- ✅ **Expected: page opens in VIEW mode, NOT EDIT mode** (v9.0 opened the edit form here)
- ✅ The subject, customer email, original message body are displayed as **text, not editable inputs**
- ✅ Header has buttons: "Reply", "Change status", "Change priority", "Assign" (no "Save" or "Edit fields" buttons)

### Step 14 — Admin replies without editing original

- Click the "Reply" header button
- Modal opens with a single textarea (the reply body)
- ✅ **Notice: there's no field for `subject` or to edit the customer's first message**
- Type a reply
- Submit
- ✅ The view page reloads and now shows 2 messages in the conversation: the customer's original (unchanged) and the admin's new reply
- ✅ Status badge updated to `answered`

Verify in DB:

```bash
php artisan tinker --execute='
$t = \App\Models\SupportTicket::latest("id")->first();
echo "Subject: " . $t->subject . "\n";
echo "Message count: " . $t->messages()->count() . "\n";
echo "First message body: " . $t->messages()->orderBy("id")->first()->body . "\n";
'
```

✅ Subject = "Test ticket from v9.1 QA" (unchanged)
✅ Message count = 2
✅ First message body = "This is a test message" (the customer's original, unchanged)

### Step 15 — Customer sees admin reply

Switch back to customer window.

- Visit `/tickets/{id}` again (the ticket they created)
- ✅ The admin's reply is visible in the thread
- The customer's reply form is still available (status is `answered`, not closed)
- Type a follow-up reply and submit
- ✅ Status flips to `pending` (the SupportTicketService rule for customer replies)

### Step 16 — Mail/notification safety (BUG #9)

The structural property is verified by code grep, not by sending mail. To prove the behavior:

```bash
# Verify .env.example
grep -E '^MAIL_MAILER=log' .env.example
# ✅ Expected: MAIL_MAILER=log

# Verify no Phase 9 code dispatches mail
grep -rE '->notify\(|Mail::|Notification::send' \
    app/Domain/Promotion app/Domain/Support \
    app/Http/Controllers/Cart \
    app/Http/Controllers/SupportTicketController.php \
    app/Http/Controllers/DealsController.php \
    app/Http/Controllers/Vendor/Vendor{Promotion,Coupon,ReviewResponse,SupportTicket}Controller.php
# ✅ Expected: no output (no matches)
```

Optional: set `MAIL_MAILER=array` in your `.env` (in-memory transport, captures messages without sending), restart, and re-run steps 12-15. All actions succeed; no 500.

---

## 3. v9.0 Regression checks (none of these should fail)

| Surface | URL | Expected |
|---|---|---|
| Product listing | `/products` | 200, no services in list |
| Product detail | `/products/{slug}` | 200 (v8.7 fix preserved) |
| Service listing | `/services` | 200 |
| Service detail | `/services/{slug}` | 200 with booking widget |
| Customer bookings | `/bookings` | 200 |
| Vendor bookings | `/vendor/bookings` | 200 |
| Wishlist | `/wishlist` | 200 |
| Wallet | `/vendor/wallet` | 200 |
| Payouts | `/vendor/payouts` | 200 |
| Public deals | `/deals` | 200, 2 promotion cards |
| Promotion creation (vendor) | `/vendor/promotions/create` | 200, form |
| Customer tickets list | `/tickets` | 200, list of customer's tickets only |

---

## 4. CI verdict

CI should produce in the summary:

```
## 🎯 Phase 9 v9.1 Verification Result
### ✅ Phase 9 v9.1 PASSES — ready to approve Phase 10
```

Confirm these stepped green:

- All Phase 7 sub-checks (14)
- All Phase 8 sub-checks (20)
- All Phase 9 v9.0 sub-checks (3)
- All Phase 9 v9.1 sub-checks (6):
  - Filament closure parameter injection check
  - Cart page contains coupon input UI
  - Admin support-ticket page is VIEW mode, not EDIT mode
  - Vendor order lifecycle has confirm + deliver routes
  - `.env.example` has `MAIL_MAILER=log` (mail-safety)
  - Phase 9 v9.1 Pest regression scenarios pass

---

## 5. Stop here

Do not begin Phase 10 until every step above is checked. If any step fails, file a regression report and we'll fix in v9.2 before moving on.
