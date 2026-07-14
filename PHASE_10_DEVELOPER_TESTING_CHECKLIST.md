# Phase 10 — Final Developer Testing Checklist

This is the final manual acceptance checklist before deployment review. Run every item below in order. Don't skip — issues found in production are 100× more expensive than issues found in this pass.

**Test accounts** (after `migrate:fresh --seed`, all passwords = `password`):

| Email | Role |
|---|---|
| `admin@marketplace.test` | super_admin |
| `staff@marketplace.test` | admin_staff |
| `vendor@marketplace.test` | vendor (Demo Trading Co., approved) |
| `vendor2@marketplace.test` | vendor (Coastal Goods, approved) |
| `customer@marketplace.test` | customer |
| `pending-vendor@marketplace.test` | vendor (status: pending) |
| `rejected-vendor@marketplace.test` | vendor (status: rejected) |

---

## 0. Prerequisites

```bash
cd /var/www/marketplace
git pull
composer install
npm ci
php artisan optimize:clear
php artisan migrate:fresh --seed
php artisan test                # full suite green
npm run typecheck               # 0 errors
npm run build                   # 0 errors
php artisan serve               # or use Nginx
```

✅ Every command above completes cleanly.

---

## 1. Authentication

| Step | Expected | Result |
|---|---|---|
| Visit `/` as guest | Storefront loads, header shows Login | |
| Visit `/products/<any-slug>` as guest | Product detail loads (guest browsing enabled) | |
| Visit `/checkout` as guest | Redirected to `/login` (guest checkout NOT enabled) | |
| Click Register, fill form, submit | Account created, logged in, on `/` | |
| Logout via header menu | Back on `/` as guest | |
| Login as `customer@marketplace.test / password` | Logged in as customer | |
| Try logging in with wrong password | Error message, still on `/login` | |
| Login → Logout → Login with same account | Works cleanly, no session pollution | |

---

## 2. Vendor application + admin approval

| Step | Account | Expected |
|---|---|---|
| Register a new account | (any new email) | Customer by default |
| Visit `/vendor/apply`, fill business form, submit | new customer | Form accepted, vendor created with status=pending |
| Visit `/vendor/dashboard` while pending | new vendor | Redirected or shown "Application pending review" |
| Log in as `admin@marketplace.test`, go to `/admin` | super_admin | Filament panel loads |
| Open Vendors → see the pending vendor | super_admin | Pending vendor visible |
| Click Approve | super_admin | Vendor becomes approved; subscription row created with default package |
| Verify in tinker | — | `Vendor::find(N)->currentPackage()` returns a package with non-zero `default_admin_commission_percent` |
| Log back in as the newly-approved vendor | — | `/vendor/dashboard` loads; vendor sidebar visible |

---

## 3. Vendor product CRUD

As `vendor@marketplace.test`:

| Step | Expected |
|---|---|
| `/vendor/products` | List page loads, existing seeded products visible |
| Click `Create product` | Form loads |
| Submit a new product (draft) | Created with status=draft |
| Edit the draft, change name | Saved cleanly |
| Submit for review | Status becomes pending_approval |
| Log in as admin, open Products in Filament, approve the product | Status becomes published |
| Visit `/products/<slug>` as guest | Product visible publicly |
| Back as vendor, try to delete a published product | 403 / forbidden — `ProductPolicy::delete` restricts to drafts |
| Try to edit a published product's price | 403 — only draft/rejected products can be vendor-edited |

### Vendor package product limit

| Step | Expected |
|---|---|
| Note the vendor's current package `max_products` (e.g. 50 for Basic) | |
| Attempt to create more products than max via the form | 403 from `VendorProductController::store` |
| Verify the limit check is server-side, not just hiding the Create button | Inspect the network response on form submit: should be 403 if over limit |

---

## 4. Catalog browsing + search

As guest:

| Step | Expected |
|---|---|
| `/products` | Catalog loads with 24 per page |
| Filter by a category | URL gets `?category=...`, results narrow |
| `/products?q=demo` | Returns matching products (case-insensitive — Phase 9 v9.4 MySQL fix) |
| `/products?q=DEMO` | Same results — proves case-insensitive |
| Visit a product detail | Image, price, vendor link, description visible |
| Sort by price ascending / descending | Order changes |
| Sort by featured | Featured products on top |

### SEO sanity check

| Step | Expected |
|---|---|
| View source on `/` | `<title>` includes app name; `<meta name="description">` present |
| View source on `/products/<slug>` | `<title>` includes product name; `<link rel="canonical">` present; `<script type="application/ld+json">` with Product schema |
| View source on `/products/<slug>` for a reviewed product | aggregateRating block present in JSON-LD |
| View source on `/products/<slug>` for a never-reviewed product | NO aggregateRating block (not "0 stars") |
| GET `/sitemap.xml` | Returns `application/xml`, includes the slug |
| GET `/robots.txt` | Returns `text/plain`, includes `Disallow: /admin` and `Sitemap:` line |
| GET `/sitemap.xml` after creating a draft product | New draft NOT in sitemap |

---

## 5. Cart and Checkout

As `customer@marketplace.test`:

| Step | Expected |
|---|---|
| Add a product to cart | Cart count increases |
| Add a second product from a different vendor | Multi-vendor cart works |
| Visit `/cart` | Both line items visible with line subtotals |
| Apply a valid coupon | Discount applied, subtotal+coupon line shown |
| Apply an invalid coupon | Error shown, no discount |
| Apply an expired coupon | Error shown |
| Click checkout | Address form shown |
| Submit without an address | Validation error |
| Add an address, select payment method, place order | Redirect to order confirmation page |
| Verify the order in DB | `subtotal_minor` + `coupon_discount_minor` + `commission_amount_minor` + `vendor_earning_minor` + `coupon_allocation_minor` all reconcile per v9.3 invariant |

### Financial reconciliation (the v9.3 + Phase 10 invariant)

After placing a multi-vendor + coupon order:

```sql
SELECT o.id, o.number, o.subtotal_minor, o.coupon_discount_minor,
       SUM(oi.coupon_allocation_minor) as alloc_sum,
       SUM(oi.commission_amount_minor + oi.vendor_earning_minor) as net_sum,
       (o.subtotal_minor - o.coupon_discount_minor) as expected_net
FROM orders o JOIN order_items oi ON oi.order_id = o.id
WHERE o.id = <last order id>
GROUP BY o.id;
```

✅ `alloc_sum == coupon_discount_minor`
✅ `net_sum == expected_net`

---

## 6. Orders lifecycle (multi-vendor)

Continue from §5. As `customer@marketplace.test`:

| Step | Expected |
|---|---|
| Visit `/orders` | The just-placed order is listed with status=paid |
| Click into the order | All items shown |
| Check the order shows the applied coupon | Yes, with discount amount |

As `vendor@marketplace.test`:

| Step | Expected |
|---|---|
| Visit `/vendor/orders` | Only the lines from THIS vendor's products shown (NOT vendor2's) |
| Open the order, mark items shipped | Status flips |
| Refresh `/orders` as customer | Fulfillment shows as **partial** (not "unfulfilled") — Phase 9 v9.4 fix |

As `vendor2@marketplace.test`, mark vendor2's items shipped. Then refresh as customer:

| Step | Expected |
|---|---|
| `/orders/<id>` | Fulfillment now **fulfilled** |

As `admin@marketplace.test`:

| Step | Expected |
|---|---|
| `/admin` → Orders → the order | Mark delivered |
| Vendor earnings record updated (after the 7-day release period or via admin action) | Visible in vendor wallet |

---

## 7. Reviews (the Phase 9 v9.5 fix)

As `customer@marketplace.test`, after a delivered order:

| Step | Expected |
|---|---|
| `/orders/<id>` | "Write a Review" link shown for delivered items |
| Click and submit a 5-star review | Success flash: "Thanks for your review! It will appear once approved." |
| `/products/<slug>` | No review visible (pending) |

As `admin@marketplace.test`:

| Step | Expected |
|---|---|
| `/admin/product-reviews` | Pending review visible |
| Click Approve | Green success notification — NO LazyLoadingViolationException error (the Phase 9 v9.5 fix) |
| Verify in tinker: `$r = ProductReview::latest('id')->first(); $r->status` | "approved" |
| Verify: `$r->product->rating_avg`, `$r->product->rating_count` | 5.00 and 1 |
| Visit `/products/<slug>` | ⭐ approved review now visible WITH the new aggregate rating |

### Rejected review

| Step | Expected |
|---|---|
| Submit a second review (different product / different order) | pending |
| As admin, reject with reason "Spam" | review.status = "rejected" |
| `/products/<slug>` | rejected review NOT visible, rating unchanged |

### Duplicate prevention

| Step | Expected |
|---|---|
| As same customer, try to review the same order item again | 400 / error "You've already reviewed this purchase" |

---

## 8. Promotions + coupons

As `vendor@marketplace.test`:

| Step | Expected |
|---|---|
| `/vendor/coupons/create` | Form loads |
| Create a percentage coupon, save | Coupon visible in vendor coupons list |
| `/vendor/promotions/create` | Form loads |
| Create a 10%-off-products promotion | Saved |
| `/deals` (as guest or customer) | Discounted products visible |

As `customer@marketplace.test`:

| Step | Expected |
|---|---|
| Add a product covered by the vendor's promotion to cart | Promo line shown |
| Apply the vendor's coupon code | Coupon discount applied; promo + coupon stack correctly |
| Place the order | Order summary shows both promo and coupon |
| Verify per-item commission + earning + allocation reconcile | See §5 reconciliation check |

---

## 9. Services + bookings

As `vendor@marketplace.test`:

| Step | Expected |
|---|---|
| `/vendor/services` | Existing service listing visible |
| Create a new service | Status=draft |
| Submit for review, admin approves | Status=published |
| Visit `/services/<slug>` | Service detail page renders |

As `customer@marketplace.test`:

| Step | Expected |
|---|---|
| `/services/<slug>` | Available slots visible |
| Pick a slot, book | Booking confirmation page |
| `/bookings` | Booking listed |
| `/bookings/<id>` | Booking detail shown |
| Request reschedule | Form opens; submit → new slot saved |
| Request cancel | Booking status → cancelled |

As `vendor@marketplace.test`:

| Step | Expected |
|---|---|
| `/vendor/bookings` | All bookings for this vendor's services |
| Open a pending booking, accept | Status → confirmed |
| Mark complete | Status → completed |

---

## 10. Support tickets

As `customer@marketplace.test`:

| Step | Expected |
|---|---|
| `/tickets/create` | Form loads |
| Submit a ticket with subject + body | Ticket created, status=open |
| `/tickets` | Ticket visible |
| Add a reply | New message appears below the original |
| Original message remains immutable | Confirm via inspect: edit endpoints exist only for replies, not for the first message |

As `admin@marketplace.test`:

| Step | Expected |
|---|---|
| `/admin` → Support Tickets → open the ticket | Loads cleanly (NO LazyLoadingViolationException — Phase 9 v9.3 fix) |
| Reply as admin | Customer's `/tickets/<id>` shows the admin reply |
| Close the ticket | Status → closed |

---

## 11. Dropshipping (Phase 6)

As `vendor@marketplace.test` (Demo Trading Co. has dropship setup in the seed):

| Step | Expected |
|---|---|
| `/vendor/suppliers` | Supplier accounts visible |
| `/vendor/supplier-products` | Supplier product catalog visible |
| Map a supplier product to a vendor product | Saved |
| When a customer orders the vendor product | Supplier order row created automatically |
| Track via supplier order page | Tracking field works |

---

## 12. Customization (Phase 7)

As `vendor@marketplace.test`:

| Step | Expected |
|---|---|
| Create a custom-type product with customization fields | Saved |
| Customer ordering this product sees the customization form | Yes |

As customer:

| Step | Expected |
|---|---|
| Add a custom product to cart | Customization form shown |
| Submit text + file upload | Stored in customization snapshot |
| Place order | Customization persists in order_items |

As vendor:

| Step | Expected |
|---|---|
| `/vendor/orders/<id>` | Sees customization fields the customer submitted |
| Upload a proof file | Proof saved; customer notified |

As customer:

| Step | Expected |
|---|---|
| `/orders/<id>` | Proof file link visible (signed URL, private storage) |
| Approve the proof | Status flips |

---

## 13. Phase 10 Reports

As `admin@marketplace.test`:

| Step | Expected |
|---|---|
| `/admin/reports` | Page loads |
| KPI cards show non-zero values (after the manual tests above generated orders) | yes |
| Date filter → "last 7 days" → Apply | Page reloads with the filter applied |
| Date filter → Custom → from=2026-01-01, to=today → Apply | Custom range applied |
| Click "Download CSV" | CSV file downloads, opens cleanly in Excel/Sheets |
| Verify CSV has UTF-8 BOM | First 3 bytes are EF BB BF (works in Excel without manual encoding setup) |
| Daily revenue chart shows a sparkline | yes |
| Top vendors table populates | yes |
| Top products table populates | yes |
| Reconciliation banner is NOT shown | yes (means invariants hold) |

As `vendor@marketplace.test`:

| Step | Expected |
|---|---|
| `/vendor/reports` | Vendor's own reports load |
| KPIs are vendor-scoped (not whole-marketplace numbers) | yes |
| Daily chart shows only this vendor's orders | yes |
| Download CSV → contains only this vendor's order items | yes; no rows for vendor2's products |

**Cross-vendor leak test**:

| Step | Expected |
|---|---|
| Try `/vendor/reports?vendor_id=<vendor2's id>` (URL manipulation) | The vendor_id param is IGNORED — vendor sees only their own data |

As `customer@marketplace.test`:

| Step | Expected |
|---|---|
| Try `/admin/reports` directly | 403 forbidden |
| Try `/vendor/reports` directly | 403 / redirected (customer is not a vendor) |

---

## 14. Mobile responsiveness

Open the storefront on a mobile viewport (DevTools → 375×667 or actual phone). Check:

- [ ] Header menu collapses to a hamburger
- [ ] Product cards stack to 1 column
- [ ] Cart page is readable; quantity controls usable
- [ ] Checkout address form fits without horizontal scroll
- [ ] Vendor / admin dashboards are usable (some tables may need horizontal scroll — that's acceptable)
- [ ] Reports page KPI cards stack 2-wide on small screens

---

## 15. Accessibility spot-check

- [ ] Tab through the registration form — every field reachable, focus rings visible
- [ ] Submit the registration form without filling required fields — error messages associated with their inputs
- [ ] Product page main image has `alt` text (vendor-provided if available, else product name as fallback)
- [ ] Headings on every page form a sensible hierarchy (h1 → h2 → h3)
- [ ] Color contrast on the primary CTA button passes WCAG AA (the indigo on white in the codebase does)
- [ ] No important action is hover-only (drop-down menus, etc.)

---

## 16. Production configuration

Before going live:

- [ ] `APP_ENV=production` in `.env`
- [ ] `APP_DEBUG=false`
- [ ] `APP_KEY` set (generated)
- [ ] `APP_URL` is the HTTPS URL
- [ ] DB connection uses production credentials (NOT `127.0.0.1:3307` from dev)
- [ ] Redis uses production credentials
- [ ] `MAIL_MAILER` is `smtp` (NOT `log` or `mailpit`)
- [ ] `SESSION_SECURE_COOKIE=true`
- [ ] `SESSION_DOMAIN=` is your domain
- [ ] HTTPS works; HTTP redirects to HTTPS
- [ ] `php artisan storage:link` has been run
- [ ] Queue worker is running (`systemctl status marketplace-queue`)
- [ ] Scheduler cron is installed
- [ ] Nightly backup cron is installed
- [ ] No `DemoSeeder` has been run in production
- [ ] `/sitemap.xml` returns 200 over HTTPS
- [ ] `/robots.txt` returns 200 over HTTPS with the correct sitemap URL

---

## 17. CI green light

- [ ] GitHub Actions CI run on the deployed commit shows `✅ Phase 10 PASSES — marketplace ready for final deployment review`
- [ ] All Phase 7 (14), Phase 8 (20), Phase 9 (22), Phase 10 (6) sub-checks individually green
- [ ] Full Pest suite passed

---

## 18. Final go/no-go

If everything above checks out:

✅ **Phase 10 is complete. The marketplace is ready for the deployment team's final review.**

Per the user's stop instruction (§25), this is NOT a public-launch declaration. The deployment team should:
1. Schedule the live deployment window
2. Communicate to vendors and existing test customers
3. Run the deployment per `PHASE_10_DEPLOYMENT_GUIDE.md`
4. Take a backup before flipping DNS
5. Monitor `storage/logs/` and queue health for the first 24-48 hours

---

## v10.1 — re-test section (the 11 defects this release fixes)

After applying the v10.1 archive, before any other testing, verify each of these:

### #4 — Product creation no longer crashes

As `vendor@marketplace.test`:

- ☐ `/vendor/products/create` opens
- ☐ Fill the form WITHOUT uploading images → Save → no MassAssignmentException
- ☐ Fill the form WITH 1 image → Save → no MassAssignmentException; product created; image visible on the edit page
- ☐ Fill the form WITH multiple images → Save → all images stored
- ☐ Edit an existing draft, add more images → Save → no crash

Run targeted Pest: `php artisan test --filter='vendor product creation'`

### #6 — Admin reports findable and rendering

As `admin@marketplace.test`:

- ☐ `/admin` loads (Filament panel)
- ☐ Sidebar shows a "Reports" group with "Reports Dashboard" item
- ☐ Click → navigates to `/admin/reports` and the page RENDERS (no blank screen, no JS error in console)
- ☐ KPI cards show non-zero values after some test orders
- ☐ Date filter "last 7 days" → Apply → reload with filter
- ☐ Download CSV → opens in Excel without garbled characters
- ☐ Reconciliation banner NOT shown

### #7 — Vendor reports findable

As `vendor@marketplace.test`:

- ☐ Vendor sidebar shows "Reports" link
- ☐ Click → `/vendor/reports` loads
- ☐ KPIs reflect only this vendor's data
- ☐ Try URL manipulation: `/vendor/reports?vendor_id=999` → still your own data (param ignored)
- ☐ Download CSV → contains only your order items

### #5 — Vendor order status from the list

As `vendor@marketplace.test`:

- ☐ `/vendor/orders` shows your orders WITH inline action buttons in the Actions column
- ☐ Click Confirm on a paid pending order → status flips
- ☐ Click Ship on a paid unfulfilled order → status flips
- ☐ Click Deliver on a shipped order → status flips
- ☐ Customer at `/orders/<id>` sees the updated status

### #2, #3, #9 — Admin vendor view

As `admin@marketplace.test`:

- ☐ `/admin/vendors` table shows a "Requested package" column with the package name + status badge
- ☐ Open a vendor record → "Vendor-selected package" section shows package name + price + commission + max products + "Selected on" date
- ☐ "Documents" section shows actual previews (thumbnails for images, download links for PDFs) — NOT raw paths
- ☐ Click an ID document link → opens in a new tab via signed URL
- ☐ Copy the signed URL, open in incognito (not logged in) → 403 (because signature is bound to user/permissions)
- ☐ Wait 30 minutes, click the original link → 403 (expired signature)

### #8 — /sitemap.xml

- ☐ `curl -i https://your-domain.example/sitemap.xml` returns 200
- ☐ Content-Type: application/xml
- ☐ Body contains `<urlset` and at least one `<url><loc>` for a published product
- ☐ If 404: check `TROUBLESHOOTING.md` v10.1 section (most likely nginx `try_files` config)

### #10 — Mobile responsive

Open `/`, `/products`, `/cart`, `/vendor`, `/admin/reports` at 320 / 375 / 768 px:

- ☐ No horizontal page overflow
- ☐ Hamburger button visible at < md (storefront) / < lg (vendor)
- ☐ Hamburger opens a drawer with all nav links
- ☐ Drawer closes when a link is clicked
- ☐ Touch targets are easily tappable (no missed taps)

Use `PHASE_10_v10.1_RESPONSIVE_TESTING_CHECKLIST.md` for the exhaustive list.

### #1 — Performance

- ☐ Refresh `/`, `/products`, `/cart` multiple times. Each subsequent load should feel faster than the first (translations cache hit)
- ☐ Reports queries return in < 1s on the seed dataset (with the new indexes)
- ☐ Optional: install Laravel Debugbar locally + verify query counts on each page; share findings if any remain unexpectedly high

### #11 — Known limitations documented

- ☐ Read `PHASE_10_KNOWN_LIMITATIONS.md` — v10.1 update section
- ☐ Verify deferred items match your understanding of what was postponed
- ☐ If items 16/24/26/28 were referring to different scope than documented, message back so we can re-document accurately

---

## v10.2 — re-test instructions (post second-manual-test feedback)

The v10.1 fixes are provably in the source (`grep` against the shipped archive on disk confirms every fix marker). The v10.2 release adds diagnostic and deployment affordances to ensure those fixes reach runtime.

**Do NOT skip `scripts/deploy.sh`.** It is the single most important command in this release.

### Recommended sequence

```bash
cd /var/www/marketplace                                         # or wherever the app lives
mysqldump marketplace > backup-pre-v102-$(date +%Y%m%d).sql      # safety net
tar -xzf /path/to/marketplace-phase-10-v10.2.tar.gz --strip-components=1 --overwrite
./scripts/deploy.sh                                              # full cache invalidation + Vite rebuild
```

If `deploy.sh` exits non-zero, STOP. Read the failed step output; fix that before going further.

### Confirm v10.2 is live

```bash
cat VERSION                                # → Phase 10 v10.2
php artisan marketplace:verify-fixes       # → 15 ✓ lines, exit 0
```

Visit any storefront page; the footer must show `· v Phase 10 v10.2`.

### If marketplace:verify-fixes shows ✗ on any line

The deployed source is NOT v10.2. Common causes:
- Archive was extracted to a sibling directory, not the running app's directory
- `git pull` or another deploy overwrote the extracted files
- Permissions issue prevented the extract from writing certain files

Re-extract the v10.2 archive over the actual app directory, then re-run `./scripts/deploy.sh`.

### If verify-fixes is all ✓ but the dev still sees the v10.0 defects

This means the code is correct but a cache layer hasn't been invalidated. In order, try:
1. `sudo systemctl restart php8.3-fpm` (flush OPcache)
2. Browser hard-refresh (Ctrl+Shift+R) + Network tab → check Disable cache
3. Purge CDN/proxy cache (Cloudflare etc.)
4. `php artisan queue:restart` (kill running workers; they pick up new code only after restart)
5. Reboot the server (last resort — eliminates all caches)

### The 16 mandatory browser actions

All listed in `PHASE_10_v10.2_DEVELOPER_CHECKLIST.md`. Run through them; mark each ☐.

If any single ☐ fails after the deploy completes successfully and `verify-fixes` is all ✓, share:
- The exact action that failed
- Output of `php artisan route:list | grep -E 'reports|sitemap|vendor-files'`
- Browser DevTools console screenshot
- The compiled JS file name visible in DevTools → Network tab (proves whether `npm run build` produced new output)

Then I can target the specific runtime issue in v10.3.

---

## v10.3 — re-test instructions

Run `PHASE_10_v10.3_DEVELOPER_CHECKLIST.md` for the 25-step v10.3-specific verification. The v10.2 checklist remains valid for items not changed in v10.3.

Key v10.3 items to re-test:
- Defect 1: admin opens vendor edit → form RENDERS (was crashing pre-v10.3)
- Defect 2: vendor + admin product creation with images works
- Defect 3: vendor order Show page has the dropdown
- Defect 5: mobile pages don't overflow horizontally (use `document.documentElement.scrollWidth > window.innerWidth` console check)
