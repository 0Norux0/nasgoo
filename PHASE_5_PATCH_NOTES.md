# Phase 5 v6.0 — Patch Notes

**Scope:** Reviews, Wishlist, Vendor Wallet & Payout Foundation, Shipping Zones & Methods Foundation. Demo data, tests, CI, docs.

**Built on:** verified Phase 4 v5.8 (`Phase 4 v5.8 PASSES` green in CI).

**Phase 6:** not started.

---

## Files added

### Schema (4 migrations)
- `database/migrations/2026_01_05_000001_create_product_reviews_table.php`
- `database/migrations/2026_01_05_000002_create_wishlists_table.php`
- `database/migrations/2026_01_05_000003_create_vendor_payout_requests_table.php`
- `database/migrations/2026_01_05_000004_create_shipping_zones_and_methods_tables.php` (adds `orders.shipping_method_id` + `orders.shipping_method_name`)

### Models (5 new + 5 updated)
- New: `ProductReview`, `Wishlist`, `VendorPayoutRequest`, `ShippingZone`, `ShippingMethod`
- Updated: `Product` (reviews/approvedReviews/wishlistedBy), `User` (productReviews/wishlist/wishlistedProducts), `Vendor` (payoutRequests), `Order` (shippingMethod + fillable), `OrderItem` (reviews)

### Domain services (4)
- `App\Domain\Review\ReviewService` — approve/reject with rating rollup
- `App\Domain\Payout\VendorWalletService` — balance computation
- `App\Domain\Payout\PayoutService` — request/approve/reject/markPaid with audit logging
- `App\Domain\Shipping\ShippingResolver` — zone matching + method filtering

### Policies (3)
- `ProductReviewPolicy` — verified-purchase enforcement on `createFor()`
- `WishlistPolicy` — owner-scoped
- `VendorPayoutRequestPolicy` — vendor sees own, admin sees all

### Controllers (4 new + 1 extended)
- `App\Http\Controllers\WishlistController`
- `App\Http\Controllers\ReviewController`
- `App\Http\Controllers\Vendor\VendorWalletController`
- `App\Http\Controllers\Vendor\VendorReviewController`
- `CatalogController::show` — extended with `reviews` (approved + reviewable_purchases) + `is_wishlisted`
- `CheckoutController::show` — exposes `shipping_methods`
- `CheckoutController::place` — accepts `shipping_method_id`
- `CheckoutService::place` — resolves and snapshots shipping method onto order

### Filament admin (4 resources + 12 pages)
- `ProductReviewResource` with List/View/Edit pages + approve/reject row actions + navigation badge
- `VendorPayoutRequestResource` with List/View/Edit pages + approve/reject/markPaid actions + navigation badge
- `ShippingZoneResource` + List/Create/Edit
- `ShippingMethodResource` + List/Create/Edit

### React pages (3 new + 1 extended)
- `resources/js/Pages/Wishlist/Index.tsx`
- `resources/js/Pages/Vendor/Wallet.tsx`
- `resources/js/Pages/Vendor/Reviews/Index.tsx`
- `resources/js/Pages/Catalog/Show.tsx` — `ReviewsBlock`, `WriteReviewForm`, `WishlistButton` components added

### Routes (`routes/web.php`)
```php
// Authenticated customer
GET    /wishlist
POST   /wishlist/items
DELETE /wishlist/items/{product}
POST   /wishlist/clear
POST   /products/{slug}/reviews

// Approved vendor
GET    /vendor/wallet
POST   /vendor/wallet/payouts
GET    /vendor/reviews
```

### Seeder
`DemoSeeder` — 4 new methods: `seedShippingZonesAndMethods()`, `seedDeliveredOrderAndReview()`, `seedWishlist()`, `seedDemoPayoutRequest()`. Updated banner output. Idempotent throughout.

### Tests (4 files, 38 scenarios)
- `tests/Feature/ProductReviewTest.php` — 10
- `tests/Feature/WishlistTest.php` — 7
- `tests/Feature/VendorPayoutTest.php` — 10
- `tests/Feature/ShippingTest.php` — 11

### CI (`.github/workflows/ci.yml`)
- Verdict: `Phase 5 v6.0 PASSES — ready to approve Phase 6`
- New CI step: `Phase 5 — demo data ready (delivered order, review, wishlist, payout, shipping zones)` — asserts all Phase 5 demo artifacts via `php artisan tinker --execute`
- Four new rows in the per-file audit-map
- New row in the verdict coverage table summarizing Phase 5 coverage

### Docs
- `PHASE_5_REPORT.md` (new, full deliverable)
- `PHASE_5_PATCH_NOTES.md` (this file)
- `README.md` — header bumped to v6.0
- `TROUBLESHOOTING.md` — Phase 5 section appended

---

## Counts

| Metric | Phase 4 v5.8 | **Phase 5 v6.0** |
|---|---|---|
| Migrations | 20 | **24** |
| Test files | 45 | **49** |
| Test scenarios | 326 | **362** (+36 net) |
| PHP brace balance | 239/239 | **279/279** |
| TypeScript errors | 0 | **0** |

---

## Honest notes

- **Sandbox can't run PHP**, so these tests + the CI snippet are validated structurally (brace balance + reflection-style sanity checks). The first real exercise happens on your CI.
- **Service registration** — all new services (`ReviewService`, `PayoutService`, `VendorWalletService`, `ShippingResolver`) are plain classes resolved via Laravel's container (`app()` / constructor injection). No `bind()` calls needed.
- **Filament policies** — `canAccess()` on each new resource checks `super_admin` or `admin_staff` role; consistent with the existing `OrderResource` pattern.
- **Strict-mode lazy-loading prevention is still on** in non-production envs. All Phase 5 controllers `with()` or `loadMissing()` the relations they touch before iterating — same defensive pattern v5.7/v5.8 established.
- **No breaking changes** to Phase 4 behavior. Existing `shipping_minor` path still works for clients that don't send `shipping_method_id`.
- **Migration order:** Phase 5 migrations go strictly after Phase 4 migrations. `migrate:fresh --seed` is required; rolling back partially through Phase 5 migrations isn't tested.

---

## Stop discipline

Phase 6 has not been started. Reply **"approve Phase 6"** with your chosen scope only after the 14-step manual checklist passes and the CI verdict is green.
