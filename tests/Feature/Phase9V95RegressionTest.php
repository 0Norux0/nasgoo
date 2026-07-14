<?php

declare(strict_types=1);

use App\Domain\Review\ReviewService;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// v8.5 — every helper prefixed `p95_` to avoid collisions.

function p95Customer(string $email = 'p95-cust@test'): User
{
    return User::factory()->create(['email' => $email, 'role' => 'customer']);
}

function p95Vendor(string $email): array
{
    $u = User::factory()->create(['email' => $email, 'role' => 'vendor']);
    $v = Vendor::factory()->create(['user_id' => $u->id, 'status' => 'approved']);
    return [$u, $v];
}

function p95Product(Vendor $v, string $slug = 'p95'): Product
{
    return Product::factory()->published()->create([
        'vendor_id'    => $v->id,
        'slug'         => $slug . '-' . $v->id,
        'name'         => 'Phase 9.5 test product',
        'price_minor'  => 50000,
        'currency'     => 'KWD',
        'rating_avg'   => 0,
        'rating_count' => 0,
    ]);
}

function p95DeliveredOrderItem(User $customer, Vendor $vendor, Product $product): OrderItem
{
    $order = Order::create([
        'number' => 'P95-' . substr(uniqid(), -6),
        'user_id' => $customer->id,
        'status' => 'completed', 'payment_status' => 'paid', 'fulfillment_status' => 'delivered',
        'currency' => 'KWD',
        'subtotal_minor' => 50000, 'shipping_minor' => 0, 'tax_minor' => 0,
        'discount_minor' => 0, 'total_minor' => 50000,
        'delivered_at' => now(),
    ]);
    return OrderItem::factory()->create([
        'order_id'         => $order->id,
        'vendor_id'        => $vendor->id,
        'product_id'       => $product->id,
        'product_name'     => $product->name,
        'quantity'         => 1,
        'unit_price_minor' => 50000,
        'line_total_minor' => 50000,
        'currency'         => 'KWD',
    ]);
}

//
// BUG #1 (the manually-confirmed bug): review approval under strict mode
//

it('ReviewService::approve runs cleanly when the ProductReview is loaded without its product relation (v9.5 strict-mode fix)', function () {
    [, $vendor]  = p95Vendor('p95-approve-vendor@test');
    $customer    = p95Customer('p95-approve-cust@test');
    $product     = p95Product($vendor, 'p95-approve');
    $orderItem   = p95DeliveredOrderItem($customer, $vendor, $product);

    // Create the review WITHOUT eager-loading product (the exact shape
    // the Filament list page would hand to the approve action).
    $review = ProductReview::create([
        'product_id'           => $product->id,
        'user_id'              => $customer->id,
        'order_item_id'        => $orderItem->id,
        'rating'               => 5,
        'title'                => 'Great',
        'body'                 => 'Loved it.',
        'status'               => ProductReview::STATUS_PENDING,
        'is_verified_purchase' => true,
    ]);

    // Re-fetch without `with('product')` — this matches the Filament
    // list-page row representation BEFORE v9.5's getEloquentQuery override.
    $reviewBare = ProductReview::findOrFail($review->id);
    expect($reviewBare->relationLoaded('product'))->toBeFalse();

    // Now turn ON strict lazy-load prevention to simulate the runtime
    // condition that triggered the bug.
    \Illuminate\Database\Eloquent\Model::preventLazyLoading(true);

    try {
        $admin = User::factory()->create(['email' => 'p95-admin@test', 'role' => 'admin']);
        $svc = app(ReviewService::class);

        // Pre-v9.5: this threw LazyLoadingViolationException →
        // transaction rolled back → review stayed pending.
        // Post-v9.5: loadMissing('product') in approve() prevents the throw.
        $approved = $svc->approve($reviewBare, $admin);

        expect($approved->status)->toBe(ProductReview::STATUS_APPROVED);
        expect($approved->approved_at)->not->toBeNull();

        // And the product's cached rating MUST be updated
        $product->refresh();
        expect($product->rating_avg)->toBe('5.00');   // decimal(3,2)
        expect($product->rating_count)->toBe(1);
    } finally {
        \Illuminate\Database\Eloquent\Model::preventLazyLoading(false);
    }
});

it('approved review appears in CatalogController::show reviews block (the end-to-end path)', function () {
    [, $vendor]  = p95Vendor('p95-e2e-vendor@test');
    $customer    = p95Customer('p95-e2e-cust@test');
    $product     = p95Product($vendor, 'p95-e2e');
    $orderItem   = p95DeliveredOrderItem($customer, $vendor, $product);
    $admin       = User::factory()->create(['email' => 'p95-e2e-admin@test', 'role' => 'admin']);

    $review = ProductReview::create([
        'product_id'           => $product->id,
        'user_id'              => $customer->id,
        'order_item_id'        => $orderItem->id,
        'rating'               => 4,
        'title'                => 'Solid',
        'body'                 => 'Worked well.',
        'status'               => ProductReview::STATUS_PENDING,
        'is_verified_purchase' => true,
    ]);

    // BEFORE approval — public product page shows zero approved reviews
    $resp1 = $this->get("/products/{$product->slug}");
    $resp1->assertOk();
    $reviewsBefore = $resp1->viewData('page')['props']['reviews']['items'] ?? [];
    expect(count($reviewsBefore))->toBe(0);
    expect($resp1->viewData('page')['props']['reviews']['rating_count'])->toBe(0);

    // Approve as admin (this is what was broken in v9.4)
    app(ReviewService::class)->approve($review->fresh(), $admin);

    // AFTER approval — public product page shows the approved review,
    // and the rating header updates.
    $resp2 = $this->get("/products/{$product->slug}");
    $resp2->assertOk();
    $reviewsAfter = $resp2->viewData('page')['props']['reviews']['items'] ?? [];
    expect(count($reviewsAfter))->toBe(1);
    expect($reviewsAfter[0]['title'])->toBe('Solid');
    expect($reviewsAfter[0]['rating'])->toBe(4);
    expect($resp2->viewData('page')['props']['reviews']['rating_count'])->toBe(1);
    expect($resp2->viewData('page')['props']['reviews']['rating_avg'])->toBe(4.0);
});

it('rating compares numerically as expected (no 5 vs 5.0 strict-format bug)', function () {
    // Codex finding #23: a test expected "5.0" while the JSON serializer
    // returned 5. v9.5's test uses tolerant comparison: rating is cast as
    // integer in the model, so the serialized value is the int 5. The
    // average IS a float (because of decimal:2 cast).
    [, $vendor] = p95Vendor('p95-num-vendor@test');
    $customer   = p95Customer('p95-num-cust@test');
    $product    = p95Product($vendor, 'p95-num');
    $orderItem  = p95DeliveredOrderItem($customer, $vendor, $product);
    $admin      = User::factory()->create(['email' => 'p95-num-admin@test', 'role' => 'admin']);

    $review = ProductReview::create([
        'product_id' => $product->id, 'user_id' => $customer->id,
        'order_item_id' => $orderItem->id, 'rating' => 5,
        'status' => ProductReview::STATUS_PENDING, 'is_verified_purchase' => true,
    ]);
    app(ReviewService::class)->approve($review, $admin);

    $resp = $this->get("/products/{$product->slug}");
    $r = $resp->viewData('page')['props']['reviews']['items'][0];

    // rating is integer; tolerant compare to both 5 and 5.0
    expect($r['rating'])->toEqual(5);
    expect((float) $r['rating'])->toEqual(5.0);
    // rating_avg IS a float (decimal:2 → cast)
    expect($resp->viewData('page')['props']['reviews']['rating_avg'])->toEqual(5.0);
});

it('rejected review never appears publicly + does not affect rating', function () {
    [, $vendor] = p95Vendor('p95-rej-vendor@test');
    $customer   = p95Customer('p95-rej-cust@test');
    $product    = p95Product($vendor, 'p95-rej');
    $orderItem  = p95DeliveredOrderItem($customer, $vendor, $product);
    $admin      = User::factory()->create(['email' => 'p95-rej-admin@test', 'role' => 'admin']);

    $review = ProductReview::create([
        'product_id' => $product->id, 'user_id' => $customer->id,
        'order_item_id' => $orderItem->id, 'rating' => 1,
        'body' => 'Bad', 'status' => ProductReview::STATUS_PENDING,
        'is_verified_purchase' => true,
    ]);
    app(ReviewService::class)->reject($review, $admin, 'Spam');

    $resp = $this->get("/products/{$product->slug}");
    $props = $resp->viewData('page')['props']['reviews'];
    expect(count($props['items']))->toBe(0);
    expect($props['rating_count'])->toBe(0);
    expect($props['rating_avg'])->toEqual(0.0);
});

//
// HIGH-PRIORITY CODEX VERIFICATIONS — these reproduce in the actual codebase
//

it('catalog search uses portable LOWER() LIKE (no PostgreSQL-only ILIKE) — re-asserted from v9.4', function () {
    $src = file_get_contents(app_path('Http/Controllers/CatalogController.php'));
    expect($src)->not->toContain("'ILIKE'");
    expect($src)->toContain('LOWER(name) LIKE');
});

it('cart-item vendor_id is derived server-side from the product, not from client input', function () {
    [, $vendor] = p95Vendor('p95-cart-vendor@test');
    $product    = p95Product($vendor, 'p95-cart');
    $customer   = p95Customer('p95-cart-cust@test');

    // Client tries to inject a different vendor_id — should be ignored
    $this->actingAs($customer);
    $this->post('/cart/items', [
        'product_id' => $product->id,
        'quantity' => 1,
        'vendor_id' => 99999,   // attacker tries to spoof
    ]);

    $cartItem = \App\Models\CartItem::query()
        ->where('product_id', $product->id)
        ->first();
    expect($cartItem)->not->toBeNull();
    // vendor_id MUST be the product's vendor, not the client's input
    expect($cartItem->vendor_id)->toBe($vendor->id);
});
