<?php

declare(strict_types=1);

/**
 * Phase 5 — product reviews.
 *
 * Covers:
 *   - Customer can review a product they've actually purchased (delivered order)
 *   - Customer CANNOT review a product they haven't purchased
 *   - Reviews default to pending; admin can approve/reject
 *   - Approved reviews appear on the product page + roll up to rating_avg/rating_count
 *   - Rejected reviews do NOT count toward the rating
 *   - Customer cannot review the same purchase twice
 *   - Vendor sees reviews on their own products only
 */

use App\Domain\Review\ReviewService;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\PaymentMethodsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\VendorPackagesSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(VendorPackagesSeeder::class);
    $this->seed(PaymentMethodsSeeder::class);
});

/** Helper: customer with a delivered order_item for a published product. */
function makeDeliveredPurchase(): array
{
    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $vendorUser = User::factory()->create();
    $vendorUser->assignRole('vendor');
    $vendor = Vendor::factory()->approved()->for($vendorUser)->create();

    $product = Product::factory()->published()->create([
        'vendor_id' => $vendor->id, 'price_minor' => 5000, 'stock' => 10,
    ]);

    $order = Order::factory()->paid()->for($customer)->create([
        'delivered_at' => now()->subDays(5),
        'earnings_release_at' => now()->subDays(2),
    ]);
    $orderItem = OrderItem::factory()->for($order)->state([
        'product_id' => $product->id, 'vendor_id' => $vendor->id, 'quantity' => 1,
    ])->create();

    return [$customer, $vendor, $product, $orderItem];
}

/* ─────────── Submission ─────────── */

it('v6.0: customer can submit a review for a delivered purchase', function () {
    [$customer, , $product, $orderItem] = makeDeliveredPurchase();

    $response = actingAs($customer)->post("/products/{$product->slug}/reviews", [
        'order_item_id' => $orderItem->id,
        'rating'        => 5,
        'title'         => 'Great product',
        'body'          => 'Loved it.',
    ]);

    $response->assertRedirect();
    $review = ProductReview::where('user_id', $customer->id)->first();
    expect($review)->not->toBeNull();
    expect($review->status)->toBe(ProductReview::STATUS_PENDING);
    expect($review->is_verified_purchase)->toBeTrue();
    expect((int) $review->rating)->toBe(5);
});

it('v6.0: customer CANNOT review a product they have not purchased', function () {
    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $vendorUser = User::factory()->create();
    $vendorUser->assignRole('vendor');
    $vendor = Vendor::factory()->approved()->for($vendorUser)->create();
    $product = Product::factory()->published()->create(['vendor_id' => $vendor->id]);

    actingAs($customer)->post("/products/{$product->slug}/reviews", [
        'rating' => 5,
        'title'  => 'Hello',
    ])->assertSessionHasErrors('order_item_id');

    expect(ProductReview::count())->toBe(0);
});

it('v6.0: customer CANNOT review a product they bought but order is not delivered yet', function () {
    $customer = User::factory()->create();
    $customer->assignRole('customer');
    $vendorUser = User::factory()->create();
    $vendorUser->assignRole('vendor');
    $vendor = Vendor::factory()->approved()->for($vendorUser)->create();
    $product = Product::factory()->published()->create(['vendor_id' => $vendor->id]);

    $order = Order::factory()->paid()->for($customer)->create(['delivered_at' => null]);
    OrderItem::factory()->for($order)->state(['product_id' => $product->id, 'vendor_id' => $vendor->id])->create();

    actingAs($customer)->post("/products/{$product->slug}/reviews", [
        'rating' => 5,
    ])->assertSessionHasErrors('order_item_id');

    expect(ProductReview::count())->toBe(0);
});

it('v6.0: customer cannot submit a duplicate review for the same purchase', function () {
    [$customer, , $product, $orderItem] = makeDeliveredPurchase();

    actingAs($customer)->post("/products/{$product->slug}/reviews", [
        'order_item_id' => $orderItem->id, 'rating' => 5,
    ])->assertRedirect();

    actingAs($customer)->post("/products/{$product->slug}/reviews", [
        'order_item_id' => $orderItem->id, 'rating' => 1, 'title' => 'changed my mind',
    ]);

    expect(ProductReview::where('order_item_id', $orderItem->id)->count())->toBe(1);
});

/* ─────────── Moderation + rating rollup ─────────── */

it('v6.0: approving a review updates product rating_avg + rating_count', function () {
    [$customer, , $product, $orderItem] = makeDeliveredPurchase();
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $review = ProductReview::create([
        'product_id' => $product->id, 'user_id' => $customer->id, 'order_item_id' => $orderItem->id,
        'rating' => 4, 'status' => ProductReview::STATUS_PENDING, 'is_verified_purchase' => true,
    ]);

    app(ReviewService::class)->approve($review, $admin);

    $product->refresh();
    expect((float) $product->rating_avg)->toBe(4.0);
    expect((int) $product->rating_count)->toBe(1);
});

it('v6.0: rejected reviews do NOT count toward the rating', function () {
    [$customer, , $product, $orderItem] = makeDeliveredPurchase();
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $review = ProductReview::create([
        'product_id' => $product->id, 'user_id' => $customer->id, 'order_item_id' => $orderItem->id,
        'rating' => 1, 'status' => ProductReview::STATUS_PENDING, 'is_verified_purchase' => true,
    ]);

    app(ReviewService::class)->reject($review, $admin, 'spam');

    $product->refresh();
    expect((float) $product->rating_avg)->toBe(0.0);
    expect((int) $product->rating_count)->toBe(0);
});

it('v6.0: approved reviews appear on the product detail page', function () {
    [$customer, , $product, $orderItem] = makeDeliveredPurchase();
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $review = ProductReview::create([
        'product_id' => $product->id, 'user_id' => $customer->id, 'order_item_id' => $orderItem->id,
        'rating' => 5, 'title' => 'visible!', 'status' => ProductReview::STATUS_PENDING, 'is_verified_purchase' => true,
    ]);
    app(ReviewService::class)->approve($review, $admin);

    $response = $this->get("/products/{$product->slug}");
    $response->assertSuccessful()
        ->assertInertia(fn ($p) => $p
            ->where('reviews.rating_avg', 5.0)
            ->where('reviews.rating_count', 1)
            ->where('reviews.items.0.title', 'visible!')
        );
});

it('v6.0: pending reviews do NOT appear on the product detail page', function () {
    [$customer, , $product, $orderItem] = makeDeliveredPurchase();

    ProductReview::create([
        'product_id' => $product->id, 'user_id' => $customer->id, 'order_item_id' => $orderItem->id,
        'rating' => 5, 'title' => 'hidden!', 'status' => ProductReview::STATUS_PENDING, 'is_verified_purchase' => true,
    ]);

    $this->get("/products/{$product->slug}")
        ->assertInertia(fn ($p) => $p->where('reviews.rating_count', 0));
});

/* ─────────── Vendor visibility ─────────── */

it('v6.0: vendor sees only reviews on their own products', function () {
    [$customer1, $vendor1, $product1, $orderItem1] = makeDeliveredPurchase();
    [$customer2, $vendor2, $product2, $orderItem2] = makeDeliveredPurchase();

    ProductReview::create([
        'product_id' => $product1->id, 'user_id' => $customer1->id, 'order_item_id' => $orderItem1->id,
        'rating' => 5, 'title' => 'mine', 'status' => ProductReview::STATUS_PENDING, 'is_verified_purchase' => true,
    ]);
    ProductReview::create([
        'product_id' => $product2->id, 'user_id' => $customer2->id, 'order_item_id' => $orderItem2->id,
        'rating' => 1, 'title' => 'foreign', 'status' => ProductReview::STATUS_PENDING, 'is_verified_purchase' => true,
    ]);

    actingAs($vendor1->user)->get('/vendor/reviews')
        ->assertSuccessful()
        ->assertInertia(fn ($p) => $p->where('reviews.total', 1));
});
