<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
*/
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/health', fn () => response()->json(['status' => 'ok']));

// v3.3 — locale switching (anyone — guest or authenticated — can switch)
Route::post('/locale/{code}', [\App\Http\Controllers\LocaleController::class, 'update'])
    ->whereIn('code', ['en', 'ar', 'ur'])
    ->name('locale.update');

// Phase 11A v11A.4 §4 — live search suggestions (focused autocomplete endpoint).
Route::get('/search/suggestions', [\App\Http\Controllers\SearchSuggestionController::class, 'index'])
    ->middleware('throttle:120,1')
    ->name('search.suggestions');

// Phase 11B.1 §11 — authenticated users can clear their search history.
// Guest history lives in localStorage and is never sent to the server.
Route::delete('/search/recent', [\App\Http\Controllers\SearchRecentController::class, 'destroy'])
    ->middleware(['auth', 'throttle:30,1'])
    ->name('search.recent.destroy');

// Phase 11B.2 §21 — privacy-safe recommendation analytics ingestion.
// Rate limit: 60 events/min per IP to mitigate spam (dev §33). Open to
// guests + authenticated users; never logs PII.
Route::post('/recommendations/events', [\App\Http\Controllers\RecommendationEventsController::class, 'record'])
    ->middleware('throttle:60,1')
    ->name('recommendations.events');

/*
|--------------------------------------------------------------------------
| Guest authentication
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);

    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);

    Route::get('/forgot-password', [PasswordResetController::class, 'showRequest'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])->name('password.email');

    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update');
});

/*
|--------------------------------------------------------------------------
| Authenticated routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // Email verification
    Route::get('/verify-email', [EmailVerificationController::class, 'notice'])
        ->name('verification.notice');
    Route::get('/verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    // Vendor application (any authenticated user)
    Route::get('/vendor/apply',  [\App\Http\Controllers\Vendor\VendorRegistrationController::class, 'show'])
        ->name('vendor.apply');
    Route::post('/vendor/apply', [\App\Http\Controllers\Vendor\VendorRegistrationController::class, 'store']);
});

/*
|--------------------------------------------------------------------------
| Vendor area (auth + must have vendor profile)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'vendor'])->group(function () {
    Route::get('/vendor',         [\App\Http\Controllers\Vendor\VendorDashboardController::class, 'index'])->name('vendor.dashboard');
});

Route::middleware(['auth', 'vendor:approved'])->group(function () {
    Route::get ('/vendor/profile', [\App\Http\Controllers\Vendor\VendorDashboardController::class, 'showProfile'])->name('vendor.profile');
    Route::post('/vendor/profile', [\App\Http\Controllers\Vendor\VendorDashboardController::class, 'updateProfile']);

    // Phase 3 — vendor product CRUD
    Route::get   ('/vendor/products',                    [\App\Http\Controllers\Vendor\VendorProductController::class, 'index'])->name('vendor.products.index');
    Route::get   ('/vendor/products/create',             [\App\Http\Controllers\Vendor\VendorProductController::class, 'create'])->name('vendor.products.create');
    Route::post  ('/vendor/products',                    [\App\Http\Controllers\Vendor\VendorProductController::class, 'store'])->name('vendor.products.store');
    Route::get   ('/vendor/products/{product}/edit',     [\App\Http\Controllers\Vendor\VendorProductController::class, 'edit'])->name('vendor.products.edit');
    Route::post  ('/vendor/products/{product}',          [\App\Http\Controllers\Vendor\VendorProductController::class, 'update'])->name('vendor.products.update');
    Route::delete('/vendor/products/{product}',          [\App\Http\Controllers\Vendor\VendorProductController::class, 'destroy'])->name('vendor.products.destroy');
    Route::post  ('/vendor/products/{product}/submit',   [\App\Http\Controllers\Vendor\VendorProductController::class, 'submit'])->name('vendor.products.submit');

    // Phase 4 — vendor's view of orders
    Route::get ('/vendor/orders',                         [\App\Http\Controllers\Vendor\VendorOrderController::class, 'index'])->name('vendor.orders.index');
    Route::get ('/vendor/orders/{order}',                 [\App\Http\Controllers\Vendor\VendorOrderController::class, 'show'])->name('vendor.orders.show');
    Route::post('/vendor/orders/{order}/ship',            [\App\Http\Controllers\Vendor\VendorOrderController::class, 'ship'])->name('vendor.orders.ship');
    // Phase 9 v9.1 — vendor confirm + deliver actions (COD lifecycle)
    Route::post('/vendor/orders/{order}/confirm',         [\App\Http\Controllers\Vendor\VendorOrderController::class, 'confirm'])->name('vendor.orders.confirm');
    Route::post('/vendor/orders/{order}/deliver',         [\App\Http\Controllers\Vendor\VendorOrderController::class, 'deliver'])->name('vendor.orders.deliver');
});

/*
|--------------------------------------------------------------------------
| Public catalog (Phase 3)
|--------------------------------------------------------------------------
*/
Route::get('/products',         [\App\Http\Controllers\CatalogController::class, 'index'])->name('catalog.index');
Route::get('/products/{slug}',  [\App\Http\Controllers\CatalogController::class, 'show'])
    ->middleware(\App\Http\Middleware\RecordProductView::class)  // Phase 11B.3 §8
    ->name('catalog.show');

/*
|--------------------------------------------------------------------------
| Phase 4 — Cart, Checkout, Customer Orders (auth required)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    // Cart
    Route::get   ('/cart',                  [\App\Http\Controllers\CartController::class, 'show'])->name('cart.show');
    Route::post  ('/cart/items',            [\App\Http\Controllers\CartController::class, 'add'])->name('cart.add');
    // Phase 11B.2 §10 — batch add for Frequently Bought Together
    Route::post  ('/cart/items/batch',       [\App\Http\Controllers\CartController::class, 'addBatch'])->name('cart.add.batch');
    // Phase 7 — customer adds a customized product (separate route because
    // the request payload has a `customizations[]` map + file uploads)
    Route::post  ('/cart/items/customized', [\App\Http\Controllers\CustomizationCartController::class, 'add'])->name('cart.add.customized');
    Route::patch ('/cart/items/{item}',     [\App\Http\Controllers\CartController::class, 'update'])->name('cart.update');
    Route::delete('/cart/items/{item}',     [\App\Http\Controllers\CartController::class, 'remove'])->name('cart.remove');
    Route::post  ('/cart/clear',            [\App\Http\Controllers\CartController::class, 'clear'])->name('cart.clear');

    // Checkout (single-page)
    Route::get ('/checkout',                [\App\Http\Controllers\CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('/checkout',                [\App\Http\Controllers\CheckoutController::class, 'place'])->name('checkout.place');

    // Customer orders
    Route::get ('/orders',                  [\App\Http\Controllers\OrderController::class, 'index'])->name('orders.index');
    Route::get ('/orders/{order}',          [\App\Http\Controllers\OrderController::class, 'show'])->name('orders.show');

    // Phase 7 — customer responds to a design proof attached to their own order
    Route::post('/orders/{order}/items/{item}/proofs/{proof}/approve',
        [\App\Http\Controllers\CustomerProofResponseController::class, 'approve'])->name('orders.proofs.approve');
    Route::post('/orders/{order}/items/{item}/proofs/{proof}/reject',
        [\App\Http\Controllers\CustomerProofResponseController::class, 'reject'])->name('orders.proofs.reject');

    // Phase 7 — customer downloads their own private-disk customization file or a proof
    Route::get('/orders/{order}/items/{item}/files/{kind}/{rowId}',
        [\App\Http\Controllers\CustomizationFileController::class, 'show'])
        ->where('kind', 'customization|proof')
        ->name('orders.files.show');
    Route::get ('/orders/{order}/confirm',  [\App\Http\Controllers\OrderController::class, 'confirm'])->name('orders.confirm');
    Route::post('/orders/{order}/cancel',   [\App\Http\Controllers\OrderController::class, 'cancel'])->name('orders.cancel');

    // Phase 5 — wishlist
    Route::get   ('/wishlist',                 [\App\Http\Controllers\WishlistController::class, 'index'])->name('wishlist.index');
    Route::post  ('/wishlist/items',           [\App\Http\Controllers\WishlistController::class, 'store'])->name('wishlist.store');
    Route::delete('/wishlist/items/{product}', [\App\Http\Controllers\WishlistController::class, 'destroy'])->name('wishlist.destroy');
    Route::post  ('/wishlist/clear',           [\App\Http\Controllers\WishlistController::class, 'clear'])->name('wishlist.clear');

    // Phase 5 — reviews (customer submission only; reads come via catalog.show)
    Route::post  ('/products/{slug}/reviews',  [\App\Http\Controllers\ReviewController::class, 'store'])->name('reviews.store');
});

/*
|--------------------------------------------------------------------------
| Phase 5 — vendor wallet + reviews (approved vendor only)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'vendor:approved'])->group(function () {
    Route::get ('/vendor/wallet',          [\App\Http\Controllers\Vendor\VendorWalletController::class, 'show'])->name('vendor.wallet.show');
    Route::post('/vendor/wallet/payouts',  [\App\Http\Controllers\Vendor\VendorWalletController::class, 'requestPayout'])->name('vendor.wallet.payouts.request');

    // Phase 11B.4 v11B.4.2 Defect 1 fix — vendor.intelligence.* moved into
    // this vendor:approved group (previously they were under plain 'auth',
    // which let pending/rejected/suspended vendors reach the controller).
    // The 'vendor:approved' middleware blocks non-approved vendor accounts
    // before the controller runs.
    Route::get   ('/vendor/intelligence',
        [\App\Http\Controllers\Vendor\VendorIntelligenceController::class, 'index'])
        ->name('vendor.intelligence.index');
    Route::post  ('/vendor/intelligence/dismiss',
        [\App\Http\Controllers\Vendor\VendorIntelligenceController::class, 'dismiss'])
        ->middleware('throttle:60,1')
        ->name('vendor.intelligence.dismiss');
    Route::post  ('/vendor/intelligence/snooze',
        [\App\Http\Controllers\Vendor\VendorIntelligenceController::class, 'snooze'])
        ->middleware('throttle:60,1')
        ->name('vendor.intelligence.snooze');

    // v6.1 — /vendor/payouts is a dedicated alias for the payout request flow.
    // The page already lists payout history + the request form (same component
    // as /vendor/wallet), so the alias keeps the UX consistent with the
    // sidebar menu the dev expects.
    Route::get ('/vendor/payouts',         [\App\Http\Controllers\Vendor\VendorWalletController::class, 'show'])->name('vendor.payouts.index');
    Route::post('/vendor/payouts',         [\App\Http\Controllers\Vendor\VendorWalletController::class, 'requestPayout'])->name('vendor.payouts.store');

    Route::get ('/vendor/reviews',         [\App\Http\Controllers\Vendor\VendorReviewController::class, 'index'])->name('vendor.reviews.index');

    // Phase 10 — vendor reporting dashboard. All queries are vendor-scoped
    // via the resolved $vendor in the request attributes; the CSV export
    // streams chunks of order_items so large windows don't OOM.
    Route::get('/vendor/reports',            [\App\Http\Controllers\Vendor\VendorReportsController::class, 'index'])->name('vendor.reports.index');
    Route::get('/vendor/reports/export.csv', [\App\Http\Controllers\Vendor\VendorReportsController::class, 'exportCsv'])->name('vendor.reports.export');

    /*
    |--------------------------------------------------------------------------
    | Phase 6 — Supplier / Dropshipping vendor routes
    |--------------------------------------------------------------------------
    | All scoped to the authenticated approved vendor via the resolved
    | `vendor` request attribute. Controllers also filter by vendor_id at
    | the query layer; defense in depth.
    */
    // Integrations
    Route::get   ('/vendor/supplier-integrations',         [\App\Http\Controllers\Vendor\VendorSupplierIntegrationController::class, 'index'])->name('vendor.supplier-integrations.index');
    Route::post  ('/vendor/supplier-integrations',         [\App\Http\Controllers\Vendor\VendorSupplierIntegrationController::class, 'store'])->name('vendor.supplier-integrations.store');
    Route::patch ('/vendor/supplier-integrations/{id}',    [\App\Http\Controllers\Vendor\VendorSupplierIntegrationController::class, 'update'])->name('vendor.supplier-integrations.update');
    Route::delete('/vendor/supplier-integrations/{id}',    [\App\Http\Controllers\Vendor\VendorSupplierIntegrationController::class, 'destroy'])->name('vendor.supplier-integrations.destroy');

    // Supplier products — list, manual entry, CSV import, mapping
    Route::get ('/vendor/supplier-products',                [\App\Http\Controllers\Vendor\VendorSupplierProductController::class, 'index'])->name('vendor.supplier-products.index');
    Route::get ('/vendor/supplier-products/manual',         [\App\Http\Controllers\Vendor\VendorSupplierProductController::class, 'manualForm'])->name('vendor.supplier-products.manual.form');
    Route::post('/vendor/supplier-products/manual',         [\App\Http\Controllers\Vendor\VendorSupplierProductController::class, 'storeManual'])->name('vendor.supplier-products.manual.store');
    Route::get ('/vendor/supplier-products/csv',            [\App\Http\Controllers\Vendor\VendorSupplierProductController::class, 'csvForm'])->name('vendor.supplier-products.csv.form');
    Route::post('/vendor/supplier-products/csv',            [\App\Http\Controllers\Vendor\VendorSupplierProductController::class, 'csvImport'])->name('vendor.supplier-products.csv.import');
    Route::get ('/vendor/supplier-products/{id}/map',       [\App\Http\Controllers\Vendor\VendorSupplierProductController::class, 'mapForm'])->name('vendor.supplier-products.map.form');
    Route::post('/vendor/supplier-products/{id}/map',       [\App\Http\Controllers\Vendor\VendorSupplierProductController::class, 'storeMapping'])->name('vendor.supplier-products.map.store');

    // Import history (per-batch CSV error report)
    Route::get ('/vendor/supplier-imports/{id}',            [\App\Http\Controllers\Vendor\VendorSupplierProductController::class, 'importReport'])->name('vendor.supplier-imports.show');

    // Supplier orders
    Route::get  ('/vendor/supplier-orders',                  [\App\Http\Controllers\Vendor\VendorSupplierOrderController::class, 'index'])->name('vendor.supplier-orders.index');
    Route::get  ('/vendor/supplier-orders/{id}',             [\App\Http\Controllers\Vendor\VendorSupplierOrderController::class, 'show'])->name('vendor.supplier-orders.show');
    Route::patch('/vendor/supplier-orders/{id}',             [\App\Http\Controllers\Vendor\VendorSupplierOrderController::class, 'update'])->name('vendor.supplier-orders.update');
    Route::post ('/vendor/supplier-orders/{id}/transition',  [\App\Http\Controllers\Vendor\VendorSupplierOrderController::class, 'transition'])->name('vendor.supplier-orders.transition');

    /*
    |--------------------------------------------------------------------------
    | Phase 7 — Customizable products + proof workflow
    |--------------------------------------------------------------------------
    */
    // Customization field builder — vendor manages fields on their own products
    Route::get   ('/vendor/products/{productId}/customization-fields',
        [\App\Http\Controllers\Vendor\VendorCustomizationFieldController::class, 'index'])->name('vendor.products.customization-fields.index');
    Route::post  ('/vendor/products/{productId}/customization-fields',
        [\App\Http\Controllers\Vendor\VendorCustomizationFieldController::class, 'store'])->name('vendor.products.customization-fields.store');
    Route::patch ('/vendor/products/{productId}/customization-fields/{fieldId}',
        [\App\Http\Controllers\Vendor\VendorCustomizationFieldController::class, 'update'])->name('vendor.products.customization-fields.update');
    Route::delete('/vendor/products/{productId}/customization-fields/{fieldId}',
        [\App\Http\Controllers\Vendor\VendorCustomizationFieldController::class, 'destroy'])->name('vendor.products.customization-fields.destroy');

    // Proof workflow — vendor uploads + sends; downloads any private file for their own order_item
    Route::post('/vendor/orders/items/{orderItemId}/proofs',
        [\App\Http\Controllers\Vendor\VendorCustomizationProofController::class, 'upload'])->name('vendor.orders.proofs.upload');
    Route::post('/vendor/orders/items/{orderItemId}/proofs/{proofId}/send',
        [\App\Http\Controllers\Vendor\VendorCustomizationProofController::class, 'send'])->name('vendor.orders.proofs.send');
    Route::get('/vendor/orders/items/{orderItemId}/files/{kind}/{rowId}',
        [\App\Http\Controllers\Vendor\VendorCustomizationProofController::class, 'downloadFile'])
        ->where('kind', 'customization|proof')
        ->name('vendor.orders.files.show');
});

/*
|--------------------------------------------------------------------------
| Public vendor storefront placeholder
|--------------------------------------------------------------------------
*/
Route::get('/vendors/{slug}', [\App\Http\Controllers\Vendor\VendorStorefrontController::class, 'show'])
    ->name('vendor.storefront');

/*
|--------------------------------------------------------------------------
| Phase 8 — Services Marketplace + Bookings
|--------------------------------------------------------------------------
|
| Three sets of routes:
|   - Customer-facing service catalog (public + guest browsable)
|   - Customer-facing bookings (auth-only)
|   - Vendor-facing services + providers + availability + bookings (vendor role)
|
| All vendor routes require role:vendor. Customer routes require auth except
| catalog browse + service detail which work for guests too.
*/

// Customer-facing service catalog — public browsing
Route::get('/services',           [\App\Http\Controllers\ServiceCatalogController::class, 'index'])->name('services.index');
Route::get('/services/{slug}',    [\App\Http\Controllers\ServiceCatalogController::class, 'show'])->name('services.show');
Route::get('/services/api/slots', [\App\Http\Controllers\ServiceCatalogController::class, 'slots'])->name('services.slots');

// Customer bookings — auth required
Route::middleware(['auth'])->group(function () {
    Route::get   ('/bookings',                       [\App\Http\Controllers\BookingController::class, 'index'])->name('bookings.index');
    // Phase 8 v8.1 — booking confirmation page (post-creation). Distinct
    // route name so it can be the redirect target without conflicting with
    // bookings.show's URL.
    Route::get   ('/bookings/{id}/confirmation',     [\App\Http\Controllers\BookingController::class, 'confirmation'])->name('bookings.confirmation');
    Route::get   ('/bookings/{id}',                  [\App\Http\Controllers\BookingController::class, 'show'])->name('bookings.show');
    Route::post  ('/bookings',                       [\App\Http\Controllers\BookingController::class, 'store'])->name('bookings.store');
    Route::post  ('/bookings/{id}/cancel',           [\App\Http\Controllers\BookingController::class, 'cancel'])->name('bookings.cancel');
    // Phase 8 v8.1 — customer can request a reschedule (basic version: free
    // pick + immediate apply if slot is open). See ServiceBookingService::reschedule.
    Route::post  ('/bookings/{id}/reschedule',       [\App\Http\Controllers\BookingController::class, 'reschedule'])->name('bookings.reschedule');
});

// Vendor-facing services + providers + availability + bookings
Route::middleware(['auth', 'role:vendor'])->group(function () {

    // Services CRUD (Phase 8 — only the new TYPE_SERVICE products)
    Route::get   ('/vendor/services',                  [\App\Http\Controllers\Vendor\VendorServiceController::class, 'index'])->name('vendor.services.index');
    Route::get   ('/vendor/services/create',           [\App\Http\Controllers\Vendor\VendorServiceController::class, 'create'])->name('vendor.services.create');
    Route::post  ('/vendor/services',                  [\App\Http\Controllers\Vendor\VendorServiceController::class, 'store'])->name('vendor.services.store');
    Route::get   ('/vendor/services/{id}/edit',        [\App\Http\Controllers\Vendor\VendorServiceController::class, 'edit'])->name('vendor.services.edit');
    Route::patch ('/vendor/services/{id}',             [\App\Http\Controllers\Vendor\VendorServiceController::class, 'update'])->name('vendor.services.update');

    // Service providers (staff) CRUD
    Route::get   ('/vendor/providers',                 [\App\Http\Controllers\Vendor\VendorServiceProviderController::class, 'index'])->name('vendor.providers.index');
    Route::post  ('/vendor/providers',                 [\App\Http\Controllers\Vendor\VendorServiceProviderController::class, 'store'])->name('vendor.providers.store');
    Route::patch ('/vendor/providers/{id}',            [\App\Http\Controllers\Vendor\VendorServiceProviderController::class, 'update'])->name('vendor.providers.update');
    Route::delete('/vendor/providers/{id}',            [\App\Http\Controllers\Vendor\VendorServiceProviderController::class, 'destroy'])->name('vendor.providers.destroy');

    // Provider availability + blocked dates
    Route::get   ('/vendor/providers/{providerId}/availability',
        [\App\Http\Controllers\Vendor\VendorAvailabilityController::class, 'show'])->name('vendor.providers.availability');
    Route::post  ('/vendor/providers/{providerId}/availability',
        [\App\Http\Controllers\Vendor\VendorAvailabilityController::class, 'upsertAvailability'])->name('vendor.providers.availability.upsert');
    Route::post  ('/vendor/providers/{providerId}/blocked-dates',
        [\App\Http\Controllers\Vendor\VendorAvailabilityController::class, 'blockDate'])->name('vendor.providers.blocked.store');
    Route::delete('/vendor/providers/{providerId}/blocked-dates/{blockedId}',
        [\App\Http\Controllers\Vendor\VendorAvailabilityController::class, 'unblockDate'])->name('vendor.providers.blocked.destroy');

    // Vendor bookings dashboard
    Route::get   ('/vendor/bookings',                  [\App\Http\Controllers\Vendor\VendorBookingController::class, 'index'])->name('vendor.bookings.index');
    Route::get   ('/vendor/bookings/{id}',             [\App\Http\Controllers\Vendor\VendorBookingController::class, 'show'])->name('vendor.bookings.show');
    Route::post  ('/vendor/bookings/{id}/accept',      [\App\Http\Controllers\Vendor\VendorBookingController::class, 'accept'])->name('vendor.bookings.accept');
    Route::post  ('/vendor/bookings/{id}/reject',      [\App\Http\Controllers\Vendor\VendorBookingController::class, 'reject'])->name('vendor.bookings.reject');
    Route::post  ('/vendor/bookings/{id}/complete',    [\App\Http\Controllers\Vendor\VendorBookingController::class, 'complete'])->name('vendor.bookings.complete');
    // Phase 8 v8.1 — vendor can also reschedule (eg. when a customer phones
    // in to reschedule and the vendor records it via the dashboard).
    Route::post  ('/vendor/bookings/{id}/reschedule',  [\App\Http\Controllers\Vendor\VendorBookingController::class, 'reschedule'])->name('vendor.bookings.reschedule');
});

// ──────────────────────────────────────────────────────────────────────────
// Phase 9 — Public deals page (open to guests + authed customers)
// ──────────────────────────────────────────────────────────────────────────
Route::get('/deals', [\App\Http\Controllers\DealsController::class, 'index'])->name('deals.index');

Route::middleware(['auth'])->group(function () {
    // Phase 9 — Cart coupon application
    Route::post  ('/cart/coupon',   [\App\Http\Controllers\Cart\CouponController::class, 'apply'])->name('cart.coupon.apply');
    Route::delete('/cart/coupon',   [\App\Http\Controllers\Cart\CouponController::class, 'remove'])->name('cart.coupon.remove');

    // Phase 9 — Customer support tickets
    Route::get   ('/tickets',                   [\App\Http\Controllers\SupportTicketController::class, 'index'])->name('tickets.index');
    Route::get   ('/tickets/create',            [\App\Http\Controllers\SupportTicketController::class, 'create'])->name('tickets.create');
    Route::post  ('/tickets',                   [\App\Http\Controllers\SupportTicketController::class, 'store'])->name('tickets.store');
    Route::get   ('/tickets/{ticket}',          [\App\Http\Controllers\SupportTicketController::class, 'show'])->name('tickets.show');
    Route::post  ('/tickets/{ticket}/reply',    [\App\Http\Controllers\SupportTicketController::class, 'reply'])->name('tickets.reply');
    Route::post  ('/tickets/{ticket}/close',    [\App\Http\Controllers\SupportTicketController::class, 'close'])->name('tickets.close');

    // Phase 9 — Vendor promotions / coupons / reviews-response / tickets
    Route::get   ('/vendor/promotions',                  [\App\Http\Controllers\Vendor\VendorPromotionController::class, 'index'])->name('vendor.promotions.index');
    Route::get   ('/vendor/promotions/create',           [\App\Http\Controllers\Vendor\VendorPromotionController::class, 'create'])->name('vendor.promotions.create');
    Route::post  ('/vendor/promotions',                  [\App\Http\Controllers\Vendor\VendorPromotionController::class, 'store'])->name('vendor.promotions.store');
    Route::get   ('/vendor/promotions/{promotion}/edit', [\App\Http\Controllers\Vendor\VendorPromotionController::class, 'edit'])->name('vendor.promotions.edit');
    Route::patch ('/vendor/promotions/{promotion}',      [\App\Http\Controllers\Vendor\VendorPromotionController::class, 'update'])->name('vendor.promotions.update');
    Route::delete('/vendor/promotions/{promotion}',      [\App\Http\Controllers\Vendor\VendorPromotionController::class, 'destroy'])->name('vendor.promotions.destroy');

    Route::get   ('/vendor/coupons',                     [\App\Http\Controllers\Vendor\VendorCouponController::class, 'index'])->name('vendor.coupons.index');
    Route::get   ('/vendor/coupons/create',              [\App\Http\Controllers\Vendor\VendorCouponController::class, 'create'])->name('vendor.coupons.create');
    Route::post  ('/vendor/coupons',                     [\App\Http\Controllers\Vendor\VendorCouponController::class, 'store'])->name('vendor.coupons.store');
    Route::get   ('/vendor/coupons/{coupon}/edit',       [\App\Http\Controllers\Vendor\VendorCouponController::class, 'edit'])->name('vendor.coupons.edit');
    Route::patch ('/vendor/coupons/{coupon}',            [\App\Http\Controllers\Vendor\VendorCouponController::class, 'update'])->name('vendor.coupons.update');
    Route::delete('/vendor/coupons/{coupon}',            [\App\Http\Controllers\Vendor\VendorCouponController::class, 'destroy'])->name('vendor.coupons.destroy');

    Route::post  ('/vendor/reviews/{review}/respond',    [\App\Http\Controllers\Vendor\VendorReviewResponseController::class, 'respond'])->name('vendor.reviews.respond');

    Route::get   ('/vendor/tickets',                     [\App\Http\Controllers\Vendor\VendorSupportTicketController::class, 'index'])->name('vendor.tickets.index');
    Route::get   ('/vendor/tickets/{ticket}',            [\App\Http\Controllers\Vendor\VendorSupportTicketController::class, 'show'])->name('vendor.tickets.show');
    Route::post  ('/vendor/tickets/{ticket}/reply',      [\App\Http\Controllers\Vendor\VendorSupportTicketController::class, 'reply'])->name('vendor.tickets.reply');

    // Phase 11B.3 v11B.3.2 §20 — vendor Settings page (fixes the 404 the sidebar
    // linked to in v11B.3.1). Uses vendor guard from the outer group middleware.
    Route::get   ('/vendor/settings',
        [\App\Http\Controllers\Vendor\VendorSettingsController::class, 'edit'])
        ->name('vendor.settings.edit');
    Route::patch ('/vendor/settings',
        [\App\Http\Controllers\Vendor\VendorSettingsController::class, 'update'])
        ->middleware('throttle:20,1')
        ->name('vendor.settings.update');

    // Phase 11B.4 v11B.4.2 Defect 1 fix — vendor.intelligence.* routes were
    // moved into the vendor:approved group below. See lines ~186+.
});

// ──────────────────────────────────────────────────────────────────────────
// Phase 10 — Admin reporting dashboard (Inertia, not Filament). Authorized
// via the `viewReports` Gate which checks Spatie's `reports.view` permission.
// Customers and vendors hitting these routes get 403.
// ──────────────────────────────────────────────────────────────────────────
Route::middleware(['auth'])->group(function () {
    Route::get('/admin/reports',            [\App\Http\Controllers\Admin\ReportsController::class, 'index'])->name('admin.reports.index');
    Route::get('/admin/reports/export.csv', [\App\Http\Controllers\Admin\ReportsController::class, 'exportOrdersCsv'])->name('admin.reports.export');

    // Phase 10 v10.1 — secure signed-URL route for admin to view private
    // vendor uploads (license, ID). Signature is enforced by the controller
    // via $request->hasValidSignature() (the 'signed' middleware would
    // duplicate this). Admin role is double-checked inside.
    Route::get('/admin/vendor-files/{vendor}/{kind}', [\App\Http\Controllers\Admin\VendorFileController::class, 'show'])
        ->name('admin.vendor-files.show');
});

// ──────────────────────────────────────────────────────────────────────────
// Phase 10 — Public SEO surfaces. Sitemap is fully dynamic; robots.txt is
// served as a route so HTTPS-aware sitemap URL can be injected at runtime.
// ──────────────────────────────────────────────────────────────────────────
Route::get('/sitemap.xml', [\App\Http\Controllers\Public\SitemapController::class, 'index'])->name('public.sitemap');
Route::get('/robots.txt',  [\App\Http\Controllers\Public\RobotsController::class, 'index'])->name('public.robots');

// ──────────────────────────────────────────────────────────────────────────
// Phase 11B.3 — Personalization: privacy controls + feedback.
// Split into public (feedback + clear-recently-viewed can be used by guests
// via their session) and auth-only (preferences page).
// ──────────────────────────────────────────────────────────────────────────
Route::post('/personalization/recently-viewed/clear',
    [\App\Http\Controllers\PersonalizationController::class, 'clearRecentlyViewed'])
    ->middleware('throttle:20,1')
    ->name('personalization.recently-viewed.clear');

Route::post('/personalization/feedback',
    [\App\Http\Controllers\PersonalizationController::class, 'feedback'])
    ->middleware('throttle:60,1')
    ->name('personalization.feedback');

Route::post('/personalization/reset',
    [\App\Http\Controllers\PersonalizationController::class, 'reset'])
    ->middleware('throttle:10,1')
    ->name('personalization.reset');

Route::middleware(['auth'])->group(function () {
    Route::get('/account/personalization',
        [\App\Http\Controllers\PersonalizationController::class, 'settings'])
        ->name('account.personalization.settings');
    Route::post('/account/personalization',
        [\App\Http\Controllers\PersonalizationController::class, 'updatePreferences'])
        ->middleware('throttle:20,1')
        ->name('account.personalization.update');
});

// ──────────────────────────────────────────────────────────────────────────
// Phase 11B.3 v11B.3.1 §13 §47 — Admin site settings.
// super_admin only (also enforced inside the controller for defense in depth).
// ──────────────────────────────────────────────────────────────────────────
Route::middleware(['auth'])->group(function () {
    Route::get   ('/admin/site-settings',
        [\App\Http\Controllers\Admin\SiteSettingsController::class, 'index'])
        ->name('admin.site-settings.index');

    Route::post  ('/admin/site-settings/upload-image',
        [\App\Http\Controllers\Admin\SiteSettingsController::class, 'uploadImage'])
        ->middleware('throttle:30,1')
        ->name('admin.site-settings.upload');

    Route::post  ('/admin/site-settings/{group}',
        [\App\Http\Controllers\Admin\SiteSettingsController::class, 'update'])
        ->middleware('throttle:20,1')
        ->where('group', 'branding|appearance|header|homepage|footer|contact|social|seo|mobile|vendor_intelligence')
        ->name('admin.site-settings.update');

    Route::post  ('/admin/site-settings/{group}/reset',
        [\App\Http\Controllers\Admin\SiteSettingsController::class, 'reset'])
        ->middleware('throttle:10,1')
        ->where('group', 'branding|appearance|header|homepage|footer|contact|social|seo|mobile|vendor_intelligence')
        ->name('admin.site-settings.reset');

    // Phase 11B.4 §19 §41 — admin vendor intelligence overview.
    // super_admin gated inside the controller (defense in depth).
    Route::get   ('/admin/vendor-intelligence',
        [\App\Http\Controllers\Admin\VendorIntelligenceController::class, 'index'])
        ->name('admin.vendor-intelligence.index');
});

/*
|--------------------------------------------------------------------------
| Phase 12.3 — License activation
|--------------------------------------------------------------------------
|
| The license middleware exempts these routes so the owner can always
| reach the activation UI (see config/license.php exempt_route_names).
*/

// Public status endpoint — reveals only "available" / "not available".
Route::get('/license/status',
    [\App\Http\Controllers\Admin\LicenseController::class, 'publicStatus'])
    ->name('license.status');

// Admin-only activation UI (super_admin gated inside the controller).
Route::middleware('auth')->group(function () {
    Route::get ('/admin/license',
        [\App\Http\Controllers\Admin\LicenseController::class, 'index'])
        ->name('admin.license.index');

    Route::post('/admin/license/activate',
        [\App\Http\Controllers\Admin\LicenseController::class, 'activate'])
        ->middleware('throttle:10,1')
        ->name('admin.license.activate');
});
