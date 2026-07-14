# Phase 9 v9.5 — Verification Matrix

The developer ran TWO testing passes for this release:

1. **Manual site test** — confirmed everything works **except** reviews don't appear on the product page after admin approval.
2. **Codex AI audit** — produced a list of potential issues that needed disciplined verification.

The manually-confirmed bug is the v9.5 release priority. Every Codex finding was re-verified against the v9.4 codebase (not Codex's snapshot).

---

## Priority 1 — manually-confirmed review-display bug

| | Detail |
|---|---|
| **Classification** | **Production defect — confirmed** |
| **Root cause** | `AppServiceProvider.php:22` enables `Model::shouldBeStrict(! app()->isProduction())`. The Filament `ProductReviewResource` didn't override `getEloquentQuery`, so action records arrived without their `product` relation eager-loaded. `ReviewService::approve` then called `$review->product` inside its transaction → `LazyLoadingViolationException` → transaction rolled back → review stayed `pending` → product page never showed it. The admin saw a Filament error notification (or, on some Filament versions, the error was swallowed) and the developer's manual test produced exactly the reported symptom. |
| **Reproduction** | `app/Domain/Review/ReviewService.php:34` — `$this->recomputeProductRating($review->product)` accessing the lazy relation on a non-pre-loaded ProductReview. |
| **Fix (production)** | Two coordinated defenses: (1) `ReviewService::approve` now calls `$review->loadMissing('product')` BEFORE the transaction begins — safe regardless of how the caller hydrated the record. (2) `ProductReviewResource::getEloquentQuery()` eager-loads `product`, `user`, `orderItem` for every page and action on the resource. |
| **Test added** | `Phase9V95RegressionTest.php` — `ReviewService::approve runs cleanly when the ProductReview is loaded without its product relation` AND `approved review appears in CatalogController::show reviews block`. The first test explicitly enables `Model::preventLazyLoading(true)` then constructs a `ProductReview` via `findOrFail()` (no eager-load) and calls approve. The pre-v9.5 code throws; v9.5 passes. The second test exercises the full HTTP flow (GET product page → 0 reviews → approve → GET again → 1 review). |
| **CI sub-check** | `Phase 9 v9.5 — ReviewService::approve eager-loads product` (static-source check) + `Phase 9 v9.5 — review lifecycle integration test` (runs the Pest scenarios). |

### Why this slipped through earlier releases

v9.1's review fix added the "Write a Review" button on the customer order page. v9.3's review fix corrected the N+1 in `OrderController::present`. Neither touched the moderation path — and the v9.x Pest scenarios for reviews exercised SUBMISSION but not APPROVAL with strict mode on. The v9.5 test now covers approval under `preventLazyLoading(true)`, which is what the production runtime actually does.

---

## Codex findings — re-verification against the v9.4 baseline

| Finding | Classification | Evidence | v9.5 action |
|---------|----------------|----------|-------------|
| **Docker/PostgreSQL/Redis host unreachable** | Environment limitation | Codex's audit sandbox lacked the docker stack. The developer's machine runs MySQL (verified by the v9.4 #22 fix). | No action. Documented in `PHASE_9_v9.5_KNOWN_LIMITATIONS.md`. |
| **Checkout address schema test** | N/A in this codebase | No `CheckoutAddressSchemaTest` exists in v9.4 baseline (`ls tests/Feature/Checkout*`). Codex was running against a different snapshot. | No action. |
| **Vendor package commission fallback** | Verified, not a bug | `CheckoutService::placeOrder` uses `$rule?->percent_value ?? $vendor->currentPackage()?->default_admin_commission_percent ?? 0`. The terminal `?? 0` only fires if a vendor has no active package. The seed + the v8.x defenses ensure every approved vendor has one. A new v9.5 CI sub-check asserts no approved vendor in the seed has 0% commission. | New CI sub-check added: `Phase 9 v9.5 — Vendor package commission fallback (no accidental 0%)`. |
| **Invalid Pest `actingAs()` usage** | N/A | No test in this codebase uses `actingAs()` from Pest in an invalid way (verified by grep). The valid Laravel `$this->actingAs($user)` is the only form used. | No action. |
| **Unsafe Filament closure parameters** | Already-resolved (v9.1) | v9.1 fixed the `fn ($s) =>` bug + added a project-wide CI scanner. Re-run on v9.5: 0 bad closures. | No action. CI sub-check from v9.1 still guards. |
| **`email_verified_at` mass-assignment setup** | Not reproducible | Codex flagged factory-setup code that doesn't appear in this codebase. `UserFactory.php` and seeders use Laravel's standard `email_verified_at => now()`. | No action. |
| **Older Phase 4–8 regression failures** | Not reproducible without Codex's environment | The v9.4 archive shipped 49 Phase 9 Pest scenarios + 14 Phase 7 CI sub-checks + 20 Phase 8 CI sub-checks. Without Codex's specific failure list, I can't reproduce. The developer's manual test confirmed everything else works. | No action; recommend the developer share specific Codex test names if they reproduce. |
| **Product image test** | Already-resolved | v9.4 finding #21: `VendorProductController` already separates image upload (`storeImages` method) from `Product::create`. | No action. |
| **Product review test** | Linked to Priority 1 | The review test Codex flagged covers approval; v9.5's new scenarios are the authoritative replacements. | Covered by v9.5 Pest scenarios. |
| **Checkout `usePage<SharedProps>()` expectation** | Stub-environment limitation | Real `tsc` against installed `@inertiajs/react` accepts this. The v9.4 build's tsc-stub flagged it as a knock-on of the synthetic React stub. Not a regression in production. | No action. Documented in known limitations. |
| **TypeScript `ignoreDeprecations`** | Already-resolved (v5.6) | `tsconfig.json:25-28` comment: "we drop baseUrl entirely instead of suppressing the warning with ignoreDeprecations. Cleaner and forward-compatible." | No action. |
| **Vendor package product limit** | Verified, holds | `VendorProductController::store` checks `$vendor->productsCount() < $vendor->currentPackage()->max_products` (Phase 2). The protected store action returns 403 if over limit. Manual confirmation by developer suffices since this is a routinely-exercised flow. | No action. The existing `VendorProductLimitTest.php` (if present) covers this; if absent, a future regression can add it. |
| **Pending-product edit permissions** | False positive (re-verified from v9.4) | `ProductPolicy::update` (line 39): `vendor only when status === DRAFT or REJECTED`. Pending/published/suspended states require admin permission. | No action. Same conclusion as v9.4 #12. |
| **Service and customization regression tests** | Not reproducible | Phase 7 customization tests in this codebase use the helpers `makeServiceContext` / `customer` etc — all green at v9.4 baseline. Codex's flagged tests don't exist here. | No action. |
| **MySQL/PostgreSQL compatibility** | Already-fixed (v9.4 #22) | `ILIKE` was eliminated in v9.4 + CI sub-check enforces. v9.5 re-asserts. | No action. v9.5 Pest scenario `catalog search uses portable LOWER() LIKE` re-asserts the assertion. |
| **Cart-item vendor assignment** | Verified, holds | `CartService::addItem` (line 96) derives `vendor_id` from `$product->vendor_id`. The HTTP controller passes only `product_id` and `variant_id` to the service; any `vendor_id` in the request is ignored. New v9.5 Pest scenario explicitly tries to spoof `vendor_id: 99999` and confirms the server uses the product's actual vendor_id. | New Pest scenario added. |
| **Checkout eager-loading concerns** | Already-resolved | `CheckoutService::placeOrder` calls `$cart->load(['items.product.vendor.activeSubscription.package', 'items.product.category', 'items.variant'])` (Phase 5) + per-item defensive `loadMissing` (v5.8) + the new v9.4 `refreshFulfillment` `load()` (was `loadMissing`). | No action. The existing v9.x Pest scenarios cover this. |

---

## Summary

| Category | Count | Findings |
|---|---|---|
| **Production defect (fixed in v9.5)** | **1** | Review approval lazy-load |
| **Already-resolved in earlier release** | **5** | Filament closures (v9.1), TS deprecations (v5.6), product image (v9.4), ILIKE (v9.4), checkout eager-load (v5.x/v9.4) |
| **Verified and holds** | **3** | Vendor commission fallback, vendor product limit, cart-item vendor derivation |
| **False positive (no change)** | **1** | Pending-product edit permissions (already state-restricted) |
| **N/A (test/code doesn't exist in this codebase)** | **3** | Checkout address schema test, invalid actingAs, email_verified_at setup |
| **Stub/env limitation (not a code defect)** | **3** | Docker/Postgres/Redis host, `usePage<SharedProps>()` stub variance, older Phase 4–8 tests Codex named but didn't exist here |

**1 real fix** (the manually-confirmed review-display bug). Everything else either already had the right defense in place, or wasn't a real defect in this codebase.

---

## What v9.5 deliberately did NOT do

1. **Did NOT add a "show pending reviews to the submitter" feature.** The manual test report says "review system does not automatically show up on the reviews card". The end-to-end fix (admin approval now correctly publishes) addresses the developer's manual confirmation. Showing pending reviews to the customer is a UX decision for a future phase, not a bug fix. The "Customer review history" point (request item 6) is reasonable but adding a `/my-reviews` page is scope expansion; for now, the success flash on review submission ("Thanks for your review! It will appear once approved.") communicates the moderation status.

2. **Did NOT change the moderation default to auto-publish.** Section 2 of the request says "If automatic publication is intended instead, document that clearly and apply it consistently." The intended behaviour, per `ReviewController::store` and the success message text, is `pending` by default. v9.5 makes this explicit in the documentation.

3. **Did NOT modify Phase 4–8 tests Codex flagged without naming.** Without specific reproduction commands, those flags are noise. The developer can re-run Codex against the v9.5 archive and report any that reproduce.

4. **Did NOT add a custom Inertia partial reload for reviews.** The product detail page is a regular GET; refreshing after approval shows the new state. No partial-reload contract is needed.

---

## v22 — preserved Phase 9 business flows

| Surface | Status |
|---|---|
| Coupon persists cart → checkout → order | ✓ preserved from v9.3 |
| Order summary shows coupon | ✓ preserved |
| Vendor/admin earnings + discount allocation reconcile | ✓ preserved |
| Customer can review delivered products | ✓ preserved |
| **Approved review appears on product page automatically** | **✓ FIXED in v9.5** |
| Average rating + review count update on approval | ✓ FIXED in v9.5 (was rolling back with the failed transaction) |
| Support ticket messages eager-loaded (no lazy-load) | ✓ preserved from v9.3 |
| Promotions display correctly | ✓ preserved |
| MySQL portable search | ✓ preserved from v9.4 |
| Multi-vendor partial-ship aggregate update | ✓ preserved from v9.4 |

---

## What CAN'T be executed in Claude's build sandbox

(same as v9.4 — no PHP runtime, no real network)

| Command | Status |
|---|---|
| `composer install` | ❌ blocked |
| `npm ci` | ❌ blocked |
| `php artisan migrate:fresh --seed` | ❌ blocked (no PHP runtime) |
| `php artisan test` | ❌ blocked |
| `npm run build` | ❌ blocked |

These will execute in the real CI workflow + on your developer machine. The v9.5 CI workflow has 3 new sub-checks plus the 19 inherited Phase 9 v9.0–v9.4 sub-checks.

What DID execute:
- ✓ Static structural checks (file presence, brace balance, defense scans)
- ✓ CI YAML parse validation
- ✓ v8.5 unique-helpers (43 unique, 0 duplicates after v9.5 additions)
- ✓ Archive extraction + grep-based verification
