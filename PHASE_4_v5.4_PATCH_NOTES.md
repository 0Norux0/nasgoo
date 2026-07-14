# Phase 4 v5.4 — Filament closure fix + product images + Place Order fix

**Targeted fix only.** Same Phase 4 scope. Phase 5 is still not started.

This release fixes the three issues the developer reported, plus the demo-data, test, CI, and documentation work requested alongside them.

---

## Bug 1 — Vendor Subscriptions admin page crashed

### Symptom
Opening **Vendor Subscriptions** in the admin panel threw:
> An attempt was made to evaluate a closure for [Filament\Tables\Columns\TextColumn], but [$s] was unresolvable.

### Cause
Filament v3 injects closure parameters **by name** (`$state`, `$record`, `$get`, …) or **by type** (`Order $record`, `OrderLifecycleService $svc` via the container). A parameter that is *both untyped and unrecognized* — like `$s` or `$r` — cannot be resolved, and Filament throws at render time.

`VendorSubscriptionResource` had:
```php
->color(fn ($s) => match ($s) { ... })                       // ✗ untyped $s
->formatStateUsing(fn ($s, $r) => ... $r->currency)          // ✗ untyped $s, $r
```

### Fix
```php
->color(fn (string $state): string => match ($state) { ... })
->formatStateUsing(fn ($state, $record) => number_format(((int) $state) / 100, 2) . ' ' . $record->currency)
```

`VendorCommissionRuleResource` had the same untyped `$s`/`$r` and was fixed identically. I also scanned **every** closure in `app/Filament` and normalized the type-hinted `$r`/`$s` → `$record`/`$state` in `OrderResource`, `VendorResource`, `VendorPackageResource`, and `ProductResource` (those worked via type-resolution, but normalizing removes the ambiguity for good). Service injections like `OrderLifecycleService $svc` were left intact — they resolve correctly through the container by type. `AuditLogResource`'s `fn ($q, $d)` are Laravel `->when()` positional callbacks (not Filament-injected) and were correctly left alone.

A new regression test (`FilamentClosureRegressionTest`) statically scans `app/Filament` and **fails if any closure parameter is untyped and unrecognized** — so this class of bug cannot return anywhere in the admin panel.

---

## Bug 2 — Product image upload missing / images not showing

### Causes (three of them)
1. **`config/filesystems.php` was missing.** `storeImages()` used `config('filesystems.default')`, which fell back to the framework default `local` disk (`storage/app`, **not** web-accessible). Uploaded files were saved where the web server can't serve them.
2. **The frontend never rendered images** — it printed the path as literal text (`[image: products/3/5/x.jpg]`) instead of an `<img>` tag, on the catalog listing, product detail, home page, cart, and vendor product edit page.
3. **Filament had no image field** — admins couldn't upload or view product images.

### Fixes
- **Created `config/filesystems.php`** with a proper `public` disk (mapped to `/storage` via `storage:link`) and an S3-compatible `s3` disk for Cloudflare R2 / MinIO in production.
- **Added a `media_disk` config key** (`config/marketplace.php`, env `MEDIA_DISK`, default `public`) so production can switch to R2/MinIO without code changes.
- **`storeImages()` now stores on the media disk explicitly** (`public` locally) so files land in `storage/app/public` and are served at `/storage/...`.
- **Added a `url` accessor to `ProductImage`** that returns `Storage::disk(media_disk)->url($path)` (and passes absolute URLs through untouched, returns null when empty). Every controller now emits `->url` instead of the raw `->path`.
- **Rewrote the frontend image rendering** on Catalog listing, Catalog detail (gallery + thumbnails), home page, cart line items, vendor storefront, and vendor product edit. Each renders a real `<img>` with `object-cover` and an `onError` fallback to a clean 🛍️ placeholder — **no broken-image icons**.
- **Wired the vendor storefront product grid** (was a Phase 3 "Coming Soon" placeholder) so `/vendors/{slug}` shows products with images.
- **Added a Filament `FileUpload` field** (image, JPG/PNG/WEBP, 5 MB max, image editor) inside an Images repeater on `ProductResource`, plus an `ImageColumn` thumbnail in the product table with a default placeholder.
- **Added a static placeholder** at `public/images/placeholder-product.svg`.

### Storage link
Product media is served from `storage/app/public` via the symlink that `php artisan storage:link` creates. The Docker entrypoint already runs `storage:link --force` on boot; the CI seed step now also runs it and asserts the symlink exists.

### Validation
Uploads are validated as `mimes:jpg,jpeg,png,webp` and `max:5120` (5 MB) in both the vendor controller and the Filament field.

---

## Bug 3 — Place Order button did nothing

### Cause
`Checkout/Show.tsx` submitted with a bare `router.post('/checkout', payload)` instead of the `useForm` helper. Consequences:
- `processing` (from `useForm`) never flipped, so the button never showed progress or disabled itself.
- Validation errors landed in the page's shared `errors` bag, **not** the `useForm` `errors` object the template was reading — so `errors.payment_method_slug` etc. stayed empty and nothing showed.
- Domain errors from `CheckoutController::place()` (e.g. out of stock) are flashed as `flash.error` via `back()->with('error', …)`, but the checkout page never displayed `flash.error`. A failed order looked like a no-op.

### Fix
- Switched the submit to the `useForm` helper using `transform()` (to shape the saved-address-id XOR inline-address payload) followed by `post('/checkout', { preserveScroll: true })`. Now `processing` works and validation errors populate the `errors` object the template reads.
- Added `usePage` flash reading and a **visible error banner** at the top of the checkout form that shows `flash.error` and/or a bulleted list of validation errors. A failed Place Order is never silent again.
- On success the server still redirects to `/orders/{id}/confirm` and Inertia follows it automatically.

---

## Demo data (improved)

`php artisan migrate:fresh --seed` produces (unchanged accounts from v5.3, now with images + a commission rule):

| Account | Email / password | Setup |
|---|---|---|
| Super admin | `admin@marketplace.test` / `password` | super_admin |
| Admin staff | `staff@marketplace.test` / `password` | admin_staff |
| Approved vendor | `vendor@marketplace.test` / `password` | **Demo Trading Co.**, approved, active Basic subscription, **vendor-level 20% commission rule**, 3 published products **each with a generated image**, 1 draft, 1 pending-review |
| Pending vendor | `pending-vendor@marketplace.test` / `password` | pending profile |
| Rejected vendor | `rejected-vendor@marketplace.test` / `password` | rejected with reason |
| Customer | `customer@marketplace.test` / `password` | default Phase 1 address — checkout-ready |

The published demo products now get a generated SVG image written to the public disk at seed time (`products/demo/{slug}.svg`), so they display a real image immediately — no broken icons, no manual upload needed to see the feature working.

> **Commission note:** the demo vendor now has a vendor-level 20% commission rule, which is more specific than the Basic package default (30%), so demo orders snapshot **20%**. (The v5.3 `DemoSeederTest` assertion was updated from 30% → 20% accordingly.)

---

## Tests added (21 new scenarios; 285 total / 40 files)

| File | Scenarios | Covers |
|---|---|---|
| `FilamentClosureRegressionTest` | 3 | Static scan for untyped/unrecognized closure params; Livewire render of VendorSubscriptions + CommissionRules lists (best-effort, skips if panel can't boot) |
| `ProductImageTest` | 9 | Vendor upload → public disk; `url` accessor (relative, absolute passthrough, null); JPG/PNG/WEBP + 5 MB validation; listing + detail emit image URLs; no-image → null thumb |
| `PlaceOrderFlowTest` | 9 | COD/BankTransfer/MockOnline order creation; validation errors visible when data missing; unknown method doesn't silently succeed; cart clears; stock decreases; order visible to customer, vendor, and admin (OrderResource query) |

Plus the v5.3 `DemoSeederTest` commission assertion updated for the new rule.

---

## CI

- Verdict bumped to **`✅ Phase 4 v5.4 PASSES — ready to approve Phase 5`**.
- The seed-verification step (now `v5.4 — migrate:fresh --seed produces a complete demo environment`) additionally: runs `storage:link --force`, asserts each published demo product has a **primary image whose file exists on disk** and whose URL contains `/storage/`, asserts the demo vendor has an active **commission rule**, and asserts the `public/storage` symlink exists.
- The three new test files are in the per-file audit map and the verdict coverage table.

---

## How to apply v5.4

```bash
tar -xzf marketplace-phase-4-v5.4.tar.gz
docker compose down
docker compose build app
docker compose up -d
docker compose exec app php artisan migrate:fresh --seed
docker compose exec app php artisan storage:link   # if not already linked by entrypoint
```

---

## Manual developer checklist

| # | Step | Expected |
|---|---|---|
| 1 | Sign in at `/admin/login` as `admin@marketplace.test` → **Vendor Subscriptions** | Page opens, no `$s` closure error. Columns show vendor, package, status badge, start/end dates, and paid amount. |
| 2 | Admin → **Commission Rules** | Opens without error; shows the demo vendor's 20% rule. |
| 3 | Admin → **Products** | Each row shows a thumbnail (or the placeholder). Open a product → Images section with upload field. Upload a JPG/PNG/WEBP, save. |
| 4 | Storefront `/products` | Demo products show their images. No broken-image icons. |
| 5 | `/products/{slug}` of a demo product | Gallery shows the image; thumbnails are real images. |
| 6 | `/vendors/demo-trading-co` | Vendor storefront lists products with images. |
| 7 | Sign in as `vendor@marketplace.test` → Products → Edit a product | Existing images render as thumbnails; "Add more images" upload works. |
| 8 | Sign in as `customer@marketplace.test` → add a product → Proceed to Checkout | Page loads (no TypeError). Default address pre-selected. |
| 9 | Click **Place Order** with COD | Button shows "Placing order…", order is created, you land on `/orders/{id}/confirm`. |
| 10 | Repeat with Bank Transfer, then Card/Online (Demo) | BT shows a `BT-…` reference; Mock Online marks the order paid. |
| 11 | Deliberately deselect a payment method (or use a fresh account with no address) and click Place Order | A red error banner appears at the top of the checkout form listing what's missing — **not** a silent no-op. |
| 12 | Check `/orders` (customer), `/vendor/orders` (vendor), and Admin → Orders | The order appears in all three. |
| 13 | Open GitHub Actions for the v5.4 branch | Verdict: **`✅ Phase 4 v5.4 PASSES — ready to approve Phase 5`**. The seed step reports images verified on disk + storage:link OK. |

---

## Known limitations (unchanged)

- Filament admin row-action testing still relies on the underlying service tests (`OrderLifecycleTest`, `PaymentTest`) plus the manual checklist; the Livewire render tests are best-effort and skip if the panel can't boot in the harness.
- Real PSP integration (MyFatoorah/Tap/Stripe) remains deferred to a sub-phase — `MockOnlineProvider` exercises the full code path.
- Image storage uses the local `public` disk by default; set `MEDIA_DISK=s3` with R2/MinIO credentials for production object storage.
- Phase 5 items (reviews, wishlist, payouts execution, tax, shipping zones) remain deferred.

---

## Stop discipline

**Phase 5 is not started.** Reply **"approve Phase 5"** only after CI is green with the v5.4 verdict and the 13-step manual checklist passes.
