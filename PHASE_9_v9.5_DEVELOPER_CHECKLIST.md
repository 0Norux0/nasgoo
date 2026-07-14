# Phase 9 v9.5 — Developer Testing Checklist

v9.5 fixes the manually-confirmed bug: approved reviews didn't appear on product pages because admin approval was rolling back under strict-mode lazy-load.

---

## 0. Apply v9.5

```bash
rm -rf vendor/composer/autoload_files.php .phpunit.cache bootstrap/cache/*.php
tar -xzf marketplace-phase-9-v9.5.tar.gz --strip-components=1 --overwrite
composer dump-autoload -o
cat VERSION                   # → Phase 9 v9.5
php artisan marketplace:version
```

---

## 1. Run the full verification

```bash
php artisan optimize:clear
php artisan migrate:fresh --seed
php artisan test --filter='Phase9V95'       # 6 new v9.5 scenarios
php artisan test --filter='Phase9'          # 55 total Phase 9 scenarios
php artisan test                            # full suite
npm ci
npm run typecheck                           # 0 errors
npm run build
```

---

## 2. The 11-step manual review flow

Test the developer-confirmed bug specifically.

### Step 1 — Login as customer

`customer@marketplace.test / password`. Land on home.

### Step 2 — Purchase a product

Add any non-service product (e.g. one from `vendor@marketplace.test`) to the cart. Visit `/cart` → "Proceed to checkout" → fill address (if needed) → submit. Redirected to `/orders/{id}/confirm`.

### Step 3 — Confirm/payment

For COD orders, payment status starts `pending`. (For testing, the seeded admin can flip it via `/admin/orders`, or vendor confirms.) Note the order id.

### Step 4 — Mark delivered

Log in as the vendor of the purchased product → `/vendor/orders/{id}` → click "Confirm order", then "Mark items shipped", then "Mark delivered". `delivered_at` is now set.

### Step 5 — Submit a review

Back as customer. Visit `/orders/{order_id}` → click "Write a Review →" on the delivered item → fill the form (rating + title + body) → submit.

Expected: flash message **"Thanks for your review! It will appear once approved."** Review row created with `status=pending`.

### Step 6 — Confirm customer sees pending status

Visit `/products/{slug}` (the product page). 

✅ **The "Write a Review" CTA is gone** (because the customer already reviewed this purchase).
- The review block shows "No reviews yet. Be the first to share your experience." — because **only approved reviews are public**.
- This is the documented behaviour. The customer does NOT see their own pending review on the public page; they see it in their account (planned feature) or trust the success flash.

### Step 7 — Login as admin

`admin@marketplace.test / password` → `/admin`.

### Step 8 — Approve the review (THIS IS THE v9.5 FIX)

`/admin/product-reviews` → find the pending review (filtered by status badge) → click the green "Approve" action icon → confirm.

✅ **Expected: green success notification "Review approved"** WITHOUT a `LazyLoadingViolationException` error toast in the corner.

**Before v9.5**: this step failed silently or showed a strict-mode error.
**After v9.5**: the transaction completes, the review's status flips to `approved`, and the product's `rating_avg` / `rating_count` are refreshed.

Verify in DB:

```bash
php artisan tinker --execute='
  $r = \App\Models\ProductReview::latest("id")->first();
  echo "status: " . $r->status . "\n";
  echo "approved_at: " . ($r->approved_at ?? "NULL") . "\n";
  $p = $r->product;
  echo "product.rating_avg: " . $p->rating_avg . "\n";
  echo "product.rating_count: " . $p->rating_count . "\n";
'
```

Expected output:
- `status: approved`
- `approved_at: 2026-XX-XX XX:XX:XX` (not null)
- `product.rating_avg: 5.00` (matches the rating the customer submitted)
- `product.rating_count: 1`

### Step 9 — Return to the product page

As any user (or unauthenticated). Visit `/products/{slug}`.

### Step 10 — Confirm review card appears

✅ **Expected: the approved review is now visible in the reviews block.** Each card shows:
- ★ rating (e.g. ★★★★★)
- title (if set)
- body
- "✓ Verified purchase" badge
- "<author_name> · <date>"

### Step 11 — Confirm rating + count update

The review block header (at the top of the reviews section) shows:

```
Reviews 5.0 ★ (1)
```

That `(1)` is `product.rating_count`, the `5.0` is `product.rating_avg`. Both updated in step 8.

---

## 3. Codex finding spot checks (v9.5 also covers these)

### Vendor commission fallback (v9.5 CI sub-check)

```bash
php artisan tinker --execute='
  foreach (\App\Models\Vendor::where("status","approved")->get() as $v) {
      $pct = $v->currentPackage()->default_admin_commission_percent ?? 0;
      echo $v->business_name . ": " . $pct . "%\n";
  }
'
```

Every approved vendor should print a non-zero percentage (10/20/30 depending on package).

### Cart-item vendor_id spoofing protection (v9.5 Pest scenario)

```bash
php artisan test --filter='cart-item vendor_id'
```

Test passes → server correctly derives vendor from product, ignores client `vendor_id`.

### MySQL ILIKE compatibility (v9.4 fix; v9.5 re-asserts)

```bash
curl -s 'http://localhost:8000/products?q=Demo' | head -5
# → 200 with rendered HTML. Pre-v9.4: "Unknown operator 'ILIKE'" on MySQL.
```

---

## 4. CI verdict

```
## 🎯 Phase 9 v9.5 Verification Result
### ✅ Phase 9 v9.5 PASSES — ready to approve Phase 10
```

Confirm green:

- Phase 7 sub-checks (14)
- Phase 8 sub-checks (20)
- Phase 9 v9.0/v9.1/v9.3/v9.4 sub-checks (19)
- Phase 9 v9.5 sub-checks (3):
  - ReviewService::approve eager-loads product (static)
  - Review lifecycle integration test (Pest)
  - Vendor package commission fallback (no 0%)

---

## 5. STOP — do not start Phase 10

After CI is green AND the 11-step flow above passes, send Phase 10 scope. v9.5 is the final correction in the v9.x stream.
