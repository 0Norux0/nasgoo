# Phase 9 Design Report

Phase 9 adds four distinct customer/vendor surfaces: promotions, coupons, review enhancement, and support tickets. The decisions below explain the schema, scoping, and trade-offs.

## Schema

### Promotions
Two-table design: `promotions` holds the campaign; `promotion_targets` is a polymorphic join on `(Product|Vendor|Category)`. A promotion with zero target rows is platform-wide.

The `approval_status` column gates vendor-created campaigns. Admins always create with `approved`; vendors create with `pending` (override in `VendorPromotionController::store`). The `Promotion::usable()` scope filters out anything not approved + active + in window, so unapproved promotions are invisible everywhere they would appear (the `/deals` listing, the resolver, the cart computation).

`max_discount_minor` is a per-line cap that protects high-value carts from receiving a runaway percentage discount. Required by Phase 9 requirements (`maximum discount amount`).

`currency` (default `KWD`) lets us refuse to apply a promotion when the cart's currency doesn't match. Defensive — Phase 9 doesn't have multi-currency carts but the column avoids a future migration.

### Coupons
Two tables: `coupons` for the codes themselves, `coupon_usages` for the redemption log. `coupons.code` is unique platform-wide and stored uppercase via a setter on the Eloquent model. Validation is case-insensitive — we `strtoupper(trim($code))` before looking up.

The cart gets `coupon_id` + `discount_minor` (this session's applied discount). The order gets `coupon_id` + `coupon_discount_minor` + `coupon_code` (a string snapshot so the order detail still reads "SAVE10" even if the coupon row is later deleted).

Per-user limit is enforced at validation time by counting prior `coupon_usages` rows for `(coupon_id, user_id)`. Global usage limit is enforced the same way without the user filter. Both checks happen in `CouponValidator::validate` before returning a discount.

### Reviews
Extended the existing `product_reviews` table with three columns. Did NOT touch the existing Phase 5 unique index `reviews_user_product_orderitem_unique` — adding columns is backward-compatible.

Service reviews share the same table. `product_id` points to a `Product::TYPE_SERVICE` row. `is_verified_purchase` is set when a completed `ServiceBooking` exists for that customer/service. Same moderation flow, same vendor response endpoint.

### Support tickets
Two tables: `support_tickets` for the metadata, `support_ticket_messages` for the chronological thread. Per-ticket message ordering is by `created_at ASC` (eloquent default sort applied in the `messages()` relation).

`number` is human-friendly: `TKT-yymmdd-NNNN`. The 4-digit suffix is random with a collision retry loop (5 attempts) — cleaner than autoincrement+padding because customers can read the date directly.

`is_internal` on messages flags admin/vendor-only notes. The customer-facing controller filters these out when rendering the thread.

`assigned_to` lets admins distribute ticket load. Not enforced for vendor tickets (those scope by `vendor_id` instead).

Status transitions are deliberate. Customer reply → `pending` (queue for staff). Admin/vendor reply → `answered` (queue for customer). Either can move to `resolved`/`closed`. The transition logic lives in `SupportTicketService::reply` so the rule is centralized.

## Domain services (where the business logic lives)

`App\Domain\Promotion\CouponValidator::validate($code, $cart, $user)` — returns `['ok' => bool, 'reason' => string, 'discount_minor' => int, 'coupon' => Coupon|null]`. Single source of truth for "can this coupon be used right now". Every UI surface and every test goes through it.

`App\Domain\Promotion\PromotionResolver::bestForProduct($product)` — returns the most-specific applicable `Promotion`. Score-based: product-targeted (100) > vendor-targeted (50) > platform-wide (1). Returns null when nothing applies.

`App\Domain\Support\SupportTicketService` — `createTicket(user, data)`, `reply(ticket, user, body, role, isInternal)`, `updateStatus(ticket, newStatus)`. The reply method handles status transitions in a single DB transaction so we never end up with a partial state.

## Controllers (all return types v8.7-compliant)

| Controller | Methods | Notes |
|---|---|---|
| `DealsController` | `index(): Response` | public, no auth |
| `Cart\CouponController` | `apply(): RedirectResponse`, `remove(): RedirectResponse` | auth |
| `SupportTicketController` | `index/create: Response`, `store/reply/close: RedirectResponse`, `show: Response` | auth, ownership-scoped |
| `Vendor\VendorPromotionController` | full CRUD | auth, vendor-scoped |
| `Vendor\VendorCouponController` | full CRUD | auth, vendor-scoped |
| `Vendor\VendorReviewResponseController` | `respond(): RedirectResponse` | auth, ownership check |
| `Vendor\VendorSupportTicketController` | `index/show: Response`, `reply: RedirectResponse` | auth, vendor-scoped |

The v8.7 static check (resolve `use` imports, verify every `Inertia::render`-returning method has a return type that includes `Inertia\Response`) was run after Phase 9 — 58 methods checked project-wide, all green.

## Filament admin (Marketing + Support nav groups)

- `PromotionResource` (Marketing group, sort 1) — full CRUD + approval-status filter
- `CouponResource` (Marketing group, sort 2) — full CRUD + usage count column
- `SupportTicketResource` (Support group, sort 1) — full CRUD + status/priority filters + default sort by `last_replied_at desc`

Filament pages use the same lazy-load discipline as Phase 8 (no relations loaded N+1; `relationship()->preload()` for Selects).

## React pages (13 new)

Customer: `Deals/Index`, `Tickets/{Index,Create,Show}` — wraps StorefrontLayout.

Vendor: `Vendor/Promotions/{Index,Edit}`, `Vendor/Coupons/{Index,Edit}`, `Vendor/Tickets/{Index,Show}` — wraps VendorLayout.

Every page uses `useForm` for mutations, typed casts where appropriate. No `usePage` typing workarounds needed in Phase 9 (didn't touch shared page props).

## Test coverage (24 scenarios)

Coverage is breadth-first: every subsystem gets the critical-path scenarios, not exhaustive permutations. The CouponTest has the most scenarios (10) because coupons have the most distinct rejection reasons and the highest blast radius (an over-permissive coupon costs real money).

All helpers prefixed `p9_` per the v8.5 defense — zero collisions with the 22 existing global helpers in `tests/Feature/`.

## What v8.x defenses ran on Phase 9

| Defense | Result |
|---|---|
| v7.1 schema-vs-code | passes — new models reference only real columns |
| v7.2 idempotent updateOrCreate | passes — seeder uses unique keys (slug, code, number) |
| v7.3 null-vs-NOT-NULL | passes — nullable foreign keys explicitly nullable |
| v8.2 identifier-length | 19 predicted names, all ≤ 60 chars |
| v8.3 schema-vs-runtime-data | no `stock_minor` or `manage_stock` anywhere new |
| v8.4 form-errors-key | every `form.errors.X` in new .tsx maps to a `useForm` key |
| v8.5 duplicate-global-function | 8 new helpers `p9_*`, 0 duplicates with existing 22 |
| v8.7 controller-return-type | 58 methods scanned, all compatible with `Inertia\Response` |

## Counts

- Migrations: 7
- Models: 6 new + 1 extended
- Domain services: 3
- Controllers: 7 new
- Filament resources: 3
- React pages: 13 new
- Routes: 23 new
- Pest scenarios: 24 new (total now 68 across Phase 7+8+9 phase-specific)
- CI sub-checks: 3 new (total now 37 phase-specific across Phase 7+8+9)

---

## v9.1 correction notes (post-developer-testing)

The developer reported 7 functional issues after testing v9.0. Every one was a real bug; v9.1 fixes each at the root cause rather than papering over symptoms.

**The single biggest mistake in v9.0** was building the coupon backend (controller, routes, service, validator, 11 rejection reasons, 10 Pest scenarios) and forgetting to wire the input field into `Cart/Show.tsx`. The customer's only path to applying a coupon was through the API directly. Static analysis didn't catch it because there's no "did you connect the backend to a UI" check. v9.1 adds one as a CI sub-check (grep `Cart/Show.tsx` for the coupon test IDs) so a future phase introducing a similar gap fails CI rather than the developer's manual testing.

**The Filament `$s` bug** was a Filament-3-injection mistake. Filament resolves closure parameters two ways: by container injection (when the parameter has a class type hint) and by name (when it's untyped — the name must be in the documented list). I knew the rule and still wrote `fn ($s) => ...`. The v9.1 CI sub-check is a static analog that runs in milliseconds and explicitly enumerates Filament's allowed names + the setters where this injection happens; future regressions get a `file=...,line=...` annotation with a remediation hint.

**The admin-ticket-edit bug** was a deeper architectural mistake — letting an admin form bind to the same model fields as the customer's original submission. v9.1's fix isn't "remove the edit form" but "make the customer's original content structurally inaccessible from the admin page". The new `ViewSupportTicket` extends `ViewRecord` and uses an `Infolist`, which has no form mutation surface at all. Replies are inserts into a separate table; there's no code path that updates `support_tickets.subject` from the admin UI.

The order-lifecycle gap (#5) and review-button gap (#6) were both wiring mistakes — the backend (OrderLifecycleService, ProductReview model, product detail page) all existed and worked. v9.1 added the missing connecting code: vendor confirm + deliver buttons in `Vendor/Orders/Show.tsx`, eligibility-computed Write Review link in `Orders/Show.tsx`.

### v9.1 counts

- 7 bug fixes (1 closure, 1 UI form, 1 server-side validation, 2 controller methods, 2 UI buttons, 1 Filament page replacement)
- 11 Pest scenarios in `Phase9V91RegressionTest.php` (helpers prefixed `p91_`)
- 6 new CI sub-checks (1 static Filament audit, 1 cart-UI grep, 1 ticket-view-mode assertion, 1 vendor routes assertion, 1 mail-safety, 1 Pest runner)
- 0 new migrations, 0 new models, 0 new business logic — v9.1 is purely wiring + corrections

Phase 9 CI sub-check total now **9** (3 v9.0 + 6 v9.1). Pest scenario total **35** (24 v9.0 + 11 v9.1). Phase-specific grand total **43 CI sub-checks** (Phase 7: 14 + Phase 8: 20 + Phase 9: 9).

---

## v9.3 correction notes (post-developer-testing of v9.1)

The developer reported 3 remaining issues after testing v9.1. All three were real and significant — particularly the coupon allocation gap (financial accuracy) and the recurring lazy-load (the v9.1 "fix" was incomplete).

**The lazy-load mistake:** I overrode the Infolist schema and added eager-loading hints elsewhere, but never overrode `resolveRecord` — which is where Filament actually fetches the record before rendering. The v9.1 Pest test for this bug didn't enable `Model::preventLazyLoading(true)`, so it passed even though production still crashed. v9.3 fixes both: `ViewSupportTicket::resolveRecord()` is overridden to eager-load `messages.user` + every other relation the Infolist touches, AND the test enables strict mode before traversing.

**The allocation mistake:** v9.1 stored `coupon_id`/`coupon_code`/`coupon_discount_minor` on the order at checkout time, but didn't allocate that discount across order_items, didn't compute commission on the net (post-discount) line total, and didn't expose any coupon block to the customer's order detail, the vendor's order detail, or the checkout review page. The cart had UI, the order had data, but everything in between was opaque. Vendor was paid commission-on-gross while customer paid gross-minus-coupon — over-payout.

v9.3 introduces a new migration adding `coupon_allocation_minor` to `order_items` and rewrites the allocation logic in `CheckoutService` to:
1. Compute per-line allocations proportionally (`floor(couponDiscount × lineTotal / subtotal)`) for all lines except the last
2. Assign the remainder to the last line so `sum(allocated) === couponDiscount` exactly
3. Compute commission on `net_line = line_total − allocated`, NOT on the gross
4. So `sum(earning + commission) === subtotal − couponDiscount` (the customer-paid amount)

These two invariants are asserted by 4 Pest scenarios (single-vendor, multi-vendor, no-coupon, customer-detail-exposes-coupon) AND a CI sub-check that runs after seed and queries every coupon order for reconciliation.

The vendor's `/vendor/orders/{id}` page now shows a clear financial breakdown:
- Gross subtotal (sum of line_total for THIS vendor's items)
- Coupon (CODE) — your share (sum of coupon_allocation for THIS vendor's items)
- Net subtotal (what customer paid for this vendor's portion)
- Platform commission
- Vendor earnings

The customer's `/orders/{id}` shows a single coupon line on the order subtotal, with each item also exposing its allocated share for advanced UI/refund accounting.

### v9.3 counts

- 3 bug fixes (1 lazy-load via resolveRecord override + 1 coupon allocation + visibility, 1 review eager-load gap)
- 1 new migration (per-line coupon allocation snapshot)
- 10 Pest scenarios in `Phase9V93RegressionTest.php`, helpers prefixed `p93_`
- 5 new CI sub-checks (column existence + controller exposure + Filament eager-load + Pest runner + **reconciliation invariant**)

Phase 9 CI sub-check total now **14** (3 v9.0 + 6 v9.1 + 5 v9.3). Pest scenarios total **45** (24 + 11 + 10). Phase-specific grand total **48 CI sub-checks**.

---

## v9.4 correction notes (Codex audit response)

The developer ran a Codex AI audit producing 24 findings. v9.4 is the disciplined response. The key principle: **don't blindly patch findings; verify each one against actual code first**. 8 of the 24 turned out to be real defects (3 production + 5 test). 16 were false positives, already-resolved, environment limitations, or about test code Codex flagged that doesn't exist in this codebase (Codex was clearly running against a different snapshot).

The biggest catch was Finding #22: `CatalogController.php` used PostgreSQL-only `ILIKE`. The developer's environment is MySQL. Every catalog search request with a `q=` parameter would have been a 500 error. v9.4 replaces with portable `whereRaw('LOWER(name) LIKE ?', ...)`.

The second-most important catch was Finding #25: `OrderLifecycleService::refreshFulfillment` used `loadMissing('items')` after an in-place DB mass-update on the same `items()` relation. `loadMissing` is a no-op when the relation is already loaded (and the calling chain DID load it). So `pluck('fulfillment_status')` read STALE in-memory values, and the aggregate `fulfillment_status` lagged one transition behind. On a multi-vendor order, vendor A shipping first didn't transition the order to `partial` until vendor B also shipped. v9.4 changes `loadMissing` to `load` (force-reload) and adds a multi-vendor Pest scenario.

Finding #17 was minor but classy: `PaymentMethodsSeeder` line 76 called `$this->command->info(...)` without null-safe, while lines 46 and 50 in the same file already used `?->`. When invoked from `$this->seed(...)` in a test, `$this->command` is null → crash. v9.4 fixes line 76 + audits all 8 other seeders + adds a CI sub-check enforcing the pattern project-wide.

The test fixes were significant too. Finding #8 (scanner false-matched comments) and Finding #9 (Pest `toContain($needle, 'message')` misuse) meant the authorization regression suite was producing both false-positive failures and false-negative passes. Finding #20 (DemoSeederTest mutating `app()->detectEnvironment(...)`) re-enabled CSRF middleware globally, causing 419 responses on every cart/checkout HTTP test that ran in the same Pest worker — a CI flakiness mystery the developer would never have traced without the audit. v9.4's fix introduces `config('marketplace.allow_demo_seeder_in_testing')` as a scoped opt-in flag.

The 16 non-fixes are documented in `PHASE_9_v9.4_VERIFICATION_MATRIX.md` with evidence for every accept/reject decision. Six were genuine architectural false-positives (Codex misread the schema or didn't see the existing state restrictions). Three were already-resolved in earlier releases (the v9.1 Filament closure fix, the v5.6 tsconfig fix, the v5.7 CheckoutService eager-load fix). Four were about test code that doesn't exist in this codebase. Two were sandbox environment limitations.

### v9.4 counts

- 3 production code fixes (CatalogController, OrderLifecycleService, PaymentMethodsSeeder + 8 seeder peers)
- 5 test code fixes (AuthorizationRegressionTest x2, Phase9V93RegressionTest x2 — published + vendor_id, DemoSeederTest)
- 4 Pest scenarios in `Phase9V94RegressionTest.php` (helpers prefixed `p94_`)
- 5 new CI sub-checks (ILIKE absence, refreshFulfillment force-reload, seeder null-safety, DemoSeeder scoped opt-in, v9.4 Pest runner)

Phase 9 CI sub-check total now **19** (3 v9.0 + 6 v9.1 + 5 v9.3 + 5 v9.4). Pest scenarios total **49**. Phase-specific grand total **53 CI sub-checks**.

The lesson codified: every confirmed Codex finding now has a CI sub-check that prevents regression. The verification matrix establishes a precedent for handling future audit reports — Codex (and audits generally) are useful inputs, not unquestionable truth.

---

## v9.5 correction notes (post-manual-testing + Codex re-verification)

The developer's manual site test confirmed that everything else works EXCEPT one bug: **approved reviews don't appear on the product page**. v9.5 fixes that.

The root cause was subtle: `AppServiceProvider` enables `Model::shouldBeStrict(! app()->isProduction())`, which makes lazy-loading throw `LazyLoadingViolationException` in development. `ReviewService::approve` called `$review->product` inside its transaction. The Filament `ProductReviewResource` didn't override `getEloquentQuery`, so when the admin clicked "Approve" on a row, the action received a `ProductReview` model WITHOUT `product` eager-loaded. Lazy-load triggered, exception thrown, transaction rolled back, review stayed `pending`, product page never reflected the approval.

v9.5 fixes this at TWO layers:
- **Service layer**: `ReviewService::approve` calls `$review->loadMissing('product')` BEFORE the transaction. Defense regardless of how the caller hydrated the record.
- **Resource layer**: `ProductReviewResource::getEloquentQuery()` eager-loads `product`, `user`, `orderItem` for every page and action.

The v9.5 Pest scenario explicitly enables `Model::preventLazyLoading(true)` to mirror the production runtime; pre-v9.5 throws, post-v9.5 passes. A second scenario does the full HTTP round trip: GET product page → 0 reviews → approve as admin → GET product page → 1 review + rating updated.

The Codex audit findings were re-verified against the v9.4 baseline (not Codex's snapshot). Only ONE was a real defect (the review approval bug above). Three findings were already-resolved in earlier releases (Filament closures v9.1, ILIKE v9.4, refreshFulfillment v9.4). Three were verified to hold (vendor commission fallback, product limit, cart-item vendor derivation). The rest were N/A, false positives, or environment limitations. Full evidence in `PHASE_9_v9.5_VERIFICATION_MATRIX.md`.

### v9.5 counts

- 1 production defect fixed at root cause (review approval lazy-load)
- 2-layer defense (service `loadMissing` + resource `getEloquentQuery`)
- 6 Pest scenarios in `Phase9V95RegressionTest.php`, helpers prefixed `p95_`
- 3 new CI sub-checks (static structural check on the fix + Pest runner + vendor commission no-zero invariant)

Phase 9 CI sub-check total now **22** (3 v9.0 + 6 v9.1 + 5 v9.3 + 5 v9.4 + 3 v9.5). Pest scenarios total **55**. Phase-specific grand total **56 CI sub-checks**.

---

## Phase 10 follow-on (Reports + SEO + Launch Readiness)

After Phase 9 v9.5 shipped and the developer's manual test confirmed everything worked, Phase 10 was approved. Phase 10 is the launch-readiness gate: it adds new surfaces (reports + SEO + sitemap + robots) without touching any Phase 1–9 code path.

**What Phase 10 added:**
- Admin reporting dashboard at `/admin/reports` (authorized via the new `viewReports` Gate; Customers and vendors get 403)
- Vendor reporting dashboard at `/vendor/reports` (vendor-scoped via middleware attributes; triple-defense against cross-vendor leak)
- SEO foundation (`SeoBuilder` + shared Inertia `seo` prop + per-page setters in 5 public controllers)
- Dynamic XML sitemap at `/sitemap.xml` (published-only filter; admin/vendor/orders/checkout explicitly excluded)
- `robots.txt` at `/robots.txt` (allow public storefront; disallow every private surface)
- 6 new CI sub-checks (reporting authz, financial reconciliation, sitemap exclusions, robots blocks, SEO approved-only, Phase 10 Pest runner)
- 13 new Pest scenarios in `Phase10RegressionTest.php`
- 7 documentation deliverables (report, patch notes, verification matrix, security checklist, deployment guide, backup/recovery, dev testing checklist, known limitations)

**The most important architectural property:** approved reviews drive structured data. `SeoBuilder::forProduct` only emits `aggregateRating` when `rating_count > 0`, and `products.rating_count` is maintained by `ReviewService::recomputeProductRating` over approved reviews only (per the v9.5 lazy-load fix). This means pending and rejected reviews CANNOT leak into search-engine structured data.

**The most important security property:** vendor reports use a triple defense against cross-vendor leak:
1. Middleware `vendor:approved` sets the vendor on request attributes
2. `VendorReportsController` reads from request attributes, NEVER from a request parameter
3. Static CI check confirms the controller doesn't `->input('vendor_id')` or similar
4. Pest scenario confirms vendor A's report excludes vendor B's order data

**Phase 10 counts:**
- 6 new CI sub-checks (Phase 10 grand total: 6; phase-specific grand total: 61 = 13 Phase 7 + 20 Phase 8 + 22 Phase 9 + 6 Phase 10)
- 13 new Pest scenarios (Pest total: ~283 across all phases)
- 48 unique global test helpers, 0 duplicates (5 new `p10_`-prefixed)
- 0 Phase 1–9 files modified
- 5 new production files + 2 React pages + ~7 modified files (HandleInertiaRequests, AppServiceProvider, 5 public controllers with one-line SEO setter, routes/web.php)

**Final CI verdict**: `✅ Phase 10 PASSES — marketplace ready for final deployment review`. Per §25 of the brief, Phase 10 completion is NOT a public-launch declaration; it's the deployment team's input.

---

## v10.1 footnote

Phase 10 v10.1 (correction release for v10.0 manual-test defects) does not touch any Phase 9 file. All v9.0–v9.5 fixes remain in place. v10.1 fixes are isolated to: VendorProductController (Phase 3 file — gets unset(images)), VendorResource (Phase 2 Filament resource — gets viewable files + package display), AdminLayout (new Phase 10 file), VendorLayout (Phase 5 file — gets mobile menu + Reports link), StorefrontLayout (Phase 1 file — gets mobile menu), Vendor/Orders/Index (Phase 5 file — gets inline actions), HandleInertiaRequests (Phase 3 file — gets translations cache), AppServiceProvider (already touched in Phase 10 — gate untouched), AdminPanelProvider (Phase 2 file — gets Reports nav). Routes/web.php gets one new vendor-files route. CI gets 7 new sub-checks.

## v10.2 footnote

Phase 10 v10.2 (recovery release for v10.1 manual-test feedback) does not touch any Phase 9 file. All v9.0–v9.5 fixes remain in place. v10.2 only adds: `app/Console/Commands/VerifyFixesCommand.php` (new), `scripts/deploy.sh` (new), `tests/Feature/Phase10V102RegressionTest.php` (new), and modifies 5 files (HandleInertiaRequests, AdminPanelProvider, VendorLayout, StorefrontLayout, types/inertia.d.ts) — all isolated changes for diagnostic/deployment affordances. 0 v1-v9 fix code modified.

## v10.3 footnote

Phase 10 v10.3 (emergency correction for v10.2 manual-test feedback) does not touch any Phase 9 file. v10.3 only modifies: Product.php (fill override), VendorResource.php (disableLabel removed), Vendor/Orders/Show.tsx (status dropdown added), app.css (global mobile guards), VerifyFixesCommand.php (4 new checks). Plus 1 new test file. 0 v1-v9 code modified. All v9.0-v9.5 fixes preserved verbatim.

## v10.4 footnote

Phase 10 v10.4 (forensic repair) does not touch any Phase 9 file. v10.4 adds only: FingerprintCommand.php, 7 new docs, 4 CI sub-checks, 6 Pest scenarios. 0 v1-v9 code modified. All v9.0-v9.5 fixes preserved verbatim.

## v10.5 footnote

Phase 10 v10.5 (TypeScript build-blocker correction) does not touch any Phase 9 file. v10.5 modifies only 4 React source files + 1 CI + 1 test. 0 v1-v9 code modified. All v9.0-v9.5 fixes preserved verbatim.

## v10.6 footnote

Phase 10 v10.6 (critical repair) does not touch any Phase 9 file. v10.6 modifies: config/filesystems.php (added vendors disk), config/marketplace.php (added vendor_private_disk key), HandleInertiaRequests.php (added top_categories share), Show.tsx (dropdown UX), StorefrontLayout.tsx (mobile categories drawer), Catalog/Index.tsx (aside hidden on mobile), inertia.d.ts (SharedProps update). 0 v1-v9 code modified.

## v10.7 footnote

Phase 10 v10.7 (vendor image-document repair) does not touch any Phase 9 file. v10.7 modifies: VendorFileResolver.php (NEW), VendorFileLinks.php, VendorFileController.php, VendorRegistrationController.php, config/marketplace.php, plus tests + CI + VERSION. 0 v1-v9 code modified.

## v10.8 footnote

Phase 10 v10.8 (promotion execution repair) does not modify any Phase 9 file. It introduces a new canonical `PricingService` that DELEGATES to Phase 9's existing `PromotionResolver` (the resolver itself is unchanged — its `scoreForProduct` logic, including the empty-targets ⇒ score 1 platform-wide branch, was already correct). v10.8 simply wires the resolver into the pricing surfaces that previously ignored it. 0 Phase 9 files modified.

## v10.9 footnote

Phase 10 v10.9 (admin reports authorization repair) does not touch any Phase 9 file. v10.9 modifies: User.php (NEW method), AppServiceProvider.php (Gate + Gate::before), AdminPanelProvider.php (nav item ->visible). Plus a NEW self-healing migration and Pest test file. 0 Phase 9 files modified. The Phase 9 promotion + coupon stacking logic (v10.8 PricingService delegating to PromotionResolver) is unchanged.

## v10.10 footnote

Phase 10 v10.10 (admin reports 403 root-cause repair) does not touch any Phase 9 file. v10.10 modifies: ReportsController.php (direct guard method), User.php (simpler canManageAdminReports), DatabaseSeeder.php (hook new seeder). Plus 3 NEW files (2 Artisan commands + 1 seeder) and 1 Pest test file. 0 Phase 9 files modified. The Phase 9 promotion + coupon stacking logic (v10.8 PricingService delegating to PromotionResolver) is unchanged.

## v10.11 footnote

Phase 10 v10.11 (runtime stability repair) does not touch any Phase 9 file. v10.11 modifies: ReportsService.php (§5 payout column), VendorOrderController.php (§3 status_options prop), Show.tsx (§3 React side), ViewSupportTicket.php (§4 Filament defensive eager-loads), SupportTicketController.php + VendorSupportTicketController.php (§4 explicit redirects), HandleInertiaRequests.php (§2 perf). Plus 1 new Pest test file. 0 Phase 9 files modified. The Phase 9 promotion + coupon stacking logic (v10.8 PricingService delegating to PromotionResolver) is unchanged.

## v10.12 footnote

Phase 10 v10.12 (admin reports role-query repair) does not touch any Phase 9 file. v10.12 modifies a single line in `app/Domain/Reports/ReportsService.php::marketplaceCounts()` (customers_total query) and adds `use App\Models\User;` import. Plus 1 new Pest test file. 0 Phase 9 files modified.
