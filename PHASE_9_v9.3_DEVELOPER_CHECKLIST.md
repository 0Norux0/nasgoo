# Phase 9 v9.3 — Developer Testing Checklist

The 13-step end-to-end test from your bug report. Each step has the expected result and how to verify it. Critical reconciliation/lazy-load checks marked ✱.

---

## 0. Apply v9.3 cleanly (the v8.6 lesson)

```bash
rm -rf vendor/composer/autoload_files.php .phpunit.cache bootstrap/cache/*.php
tar -xzf /path/to/marketplace-phase-9-v9.3.tar.gz --strip-components=1 --overwrite
composer dump-autoload -o
```

Verify deploy:

```bash
cat VERSION
# expect: Phase 9 v9.3
php artisan marketplace:version
# expect: all 4 ✓
```

---

## 1. Migrations + tests + frontend rebuild

```bash
php artisan optimize:clear
php artisan migrate:fresh --seed
```

The new migration `2026_01_20_000001_add_coupon_allocation_to_order_items` MUST run cleanly. Verify:

```bash
php artisan tinker --execute='echo Schema::hasColumn("order_items","coupon_allocation_minor") ? "✓" : "✗";'
# expect: ✓
```

```bash
php artisan test --filter='Phase9V93'    # 10 new scenarios green
php artisan test --filter='Phase9'       # 45 total Phase 9 scenarios green
php artisan test                         # full suite green
npm ci
npm run typecheck
npm run build                            # CRITICAL — frontend changes need rebuild
php artisan serve &
```

---

## 2. The 13-step end-to-end flow

### Step 1 — Apply coupon in cart

Log in as `customer@marketplace.test / password`. Add a product. Visit `/cart`.

- ✅ Coupon input visible in the summary aside
- Type `SAVE10`, click Apply
- ✅ "Subtotal", "Coupon (SAVE10) −X.XX KWD", "Subtotal after discount" all visible

### Step 2 — Proceed to checkout (BUG #1 — coupon must persist)

- Click "Proceed to checkout"
- ✅ **Checkout page summary shows the coupon line** (this is what failed in v9.1)
- The coupon line appears between Subtotal and Shipping with the discount in green/amber

### Step 3 ✱ — Confirm coupon remains visible

The checkout page summary must show:

```
Subtotal:                X.XX KWD
Coupon (SAVE10):        −X.XX KWD       ← MUST APPEAR
Shipping:                X.XX KWD
Total:                   X.XX KWD       ← reflects post-coupon amount
```

If the Coupon line is missing here, **stop and report** — the v9.3 fix didn't deploy. Re-run `npm run build` and hard-refresh.

### Step 4 — Place order

Fill the address form (or pick existing), choose payment method, submit.

- ✅ Redirects to `/orders/{id}/confirm`
- ✅ Order confirmation shows the coupon code + discount amount

### Step 5 ✱ — Order stores discount snapshot

```bash
php artisan tinker --execute='
  $o = \App\Models\Order::latest("id")->first();
  echo "coupon_code: " . ($o->coupon_code ?? "NULL") . "\n";
  echo "coupon_discount_minor: " . $o->coupon_discount_minor . "\n";
  echo "subtotal_minor: " . $o->subtotal_minor . "\n";
  echo "total_minor: " . $o->total_minor . "\n";
'
```

- ✅ `coupon_code` matches (SAVE10)
- ✅ `coupon_discount_minor` > 0
- ✅ `total_minor` == `subtotal_minor + shipping + tax − coupon_discount_minor`

### Step 6 ✱ — Vendor earnings/commission reconcile

The financial invariant. Run:

```bash
php artisan tinker --execute='
  $o = \App\Models\Order::whereNotNull("coupon_id")->latest("id")->first();
  $sumAlloc = $o->items->sum("coupon_allocation_minor");
  $sumPay   = $o->items->sum("vendor_earning_minor") + $o->items->sum("commission_amount_minor");
  $netExpected = $o->subtotal_minor - $o->coupon_discount_minor;
  echo "sum(coupon_allocation_minor)  = $sumAlloc   (expected " . $o->coupon_discount_minor . ")\n";
  echo "sum(earning + commission)     = $sumPay   (expected $netExpected)\n";
  echo "match: " . (($sumAlloc === (int) $o->coupon_discount_minor && $sumPay === $netExpected) ? "✓" : "✗") . "\n";
'
```

- ✅ Both equalities pass with `✓`

### Step 7 — Vendor confirms + delivers

Log in as the vendor. `/vendor/orders/{id}`.

- ✅ The vendor portion section shows: "Gross subtotal", "Coupon (CODE) — your share", "Net subtotal (what customer paid)", "Platform commission", "Your earnings"
- ✅ Click "Confirm order" → status moves
- ✅ Click "Mark items shipped" → fulfillment shifts
- ✅ Click "Mark delivered" → `delivered_at` populated

### Step 8 — Customer sees Write a Review (BUG #2)

Switch back to customer. Visit `/orders/{id}`.

- ✅ Order summary shows "Coupon (SAVE10) −X.XX KWD" line
- ✅ **Under the delivered item, a "Write a Review →" link is visible** (this is what failed in v9.1)

### Step 9 — Submit review

Click "Write a Review →" → lands on the product page → fill the form → submit.

- ✅ Review appears with "Pending moderation"
- Return to `/orders/{id}`: button replaced with "✓ Review submitted"

### Step 10 — Admin moderates review

`admin@marketplace.test` → `/admin/product-reviews` → Approve.

- ✅ Review now visible on the product page with verified-purchase badge

### Step 11 ✱ — Open `/admin/support-tickets/{id}` (BUG #3)

Admin → `/admin/support-tickets`.

- ✅ List loads without `LazyLoadingViolationException`
- Click any ticket
- ✅ **Detail page renders without `LazyLoadingViolationException`** (this is what failed in v9.1 and v9.2)
- ✅ Messages thread displays with author name per message

If you still see the exception, run:

```bash
php artisan tinker --execute='
  $page = new \App\Filament\Resources\SupportTicketResource\Pages\ViewSupportTicket();
  $t = \App\Models\SupportTicket::latest("id")->first();
  $r = $page->resolveRecord($t->id);
  echo "messages count: " . $r->messages->count() . "\n";
  echo "messages.user loaded: " . ($r->messages->first()->relationLoaded("user") ? "✓" : "✗") . "\n";
'
```

The `messages.user loaded` MUST print `✓`. If it prints `✗`, `composer dump-autoload -o` and restart PHP-FPM/serve.

### Step 12 — Confirm no lazy-loading error

```bash
php artisan test --filter='Phase9V93RegressionTest::it eager-loads' --stop-on-failure
```

- ✅ Test passes (it enables `Model::preventLazyLoading(true)` and traverses the record)

### Step 13 — Admin replies successfully

On the ticket view page, click "Reply" header action → type a reply → submit.

- ✅ Reply appears as a NEW row in the conversation thread
- ✅ Customer's original subject and body unchanged
- ✅ Ticket status flips to `answered`

---

## 3. Regression checks

| Surface | URL | Expected |
|---|---|---|
| Cart | `/cart` | 200, coupon input visible |
| Checkout | `/checkout` | 200, coupon line in summary |
| Customer order | `/orders/{id}` | 200, coupon line + per-item allocation visible |
| Vendor order | `/vendor/orders/{id}` | 200, Your portion with allocation breakdown |
| Admin tickets list | `/admin/support-tickets` | 200, no lazy-load error |
| Admin ticket detail | `/admin/support-tickets/{id}` | 200, no lazy-load error |
| Product detail | `/products/{slug}` | 200, review form when eligible |
| Public deals | `/deals` | 200, 2 promotion cards |
| Service detail | `/services/{slug}` | 200 with booking widget |

---

## 4. CI verdict

```
## 🎯 Phase 9 v9.3 Verification Result
### ✅ Phase 9 v9.3 PASSES — ready to approve Phase 10
```

Confirm these stepped green:

- All Phase 7 sub-checks (14)
- All Phase 8 sub-checks (20)
- All Phase 9 v9.0 + v9.1 sub-checks (9)
- All Phase 9 v9.3 sub-checks (5):
  - `order_items.coupon_allocation_minor` column exists
  - Customer + vendor + checkout all expose coupon block
  - Filament SupportTicket pages eager-load every relation
  - Phase 9 v9.3 Pest scenarios pass
  - **Coupon allocation reconciliation invariant** (the financial check)

---

## 5. Stop here

Do not begin Phase 10 until every step above is checked.
