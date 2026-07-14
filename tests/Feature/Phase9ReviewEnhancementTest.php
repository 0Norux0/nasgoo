<?php
declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function p9ReviewMakeVendor(string $email): array
{
    $u = User::factory()->create(['email' => $email, 'role' => 'vendor']);
    $v = Vendor::factory()->create(['user_id' => $u->id, 'status' => 'approved']);
    return [$u, $v];
}

function p9ReviewMakeProduct(Vendor $v): Product
{
    return Product::factory()->create([
        'vendor_id' => $v->id,
        'name' => 'Test product for review',
        'slug' => 'p9-test-product-' . $v->id,
        'price_minor' => 50000,
        'currency' => 'KWD',
    ]);
}

it('vendor can post a public response to a review on their own product', function () {
    [$vendorUser, $vendor] = p9ReviewMakeVendor('p9-rev-vendor@test');
    $product = p9ReviewMakeProduct($vendor);
    $customer = User::factory()->create(['role' => 'customer']);

    $review = ProductReview::create([
        'user_id' => $customer->id,
        'product_id' => $product->id,
        'rating' => 4,
        'body' => 'Good product, fast shipping.',
        'status' => 'approved',
        'is_verified_purchase' => true,
        'approved_at' => now(),
    ]);

    $this->actingAs($vendorUser);
    $this->post("/vendor/reviews/{$review->id}/respond", [
        'response' => 'Thanks so much for the review!',
    ])->assertRedirect();

    $review->refresh();
    expect($review->vendor_response)->toBe('Thanks so much for the review!');
    expect($review->vendor_responded_at)->not->toBeNull();
});

it('vendor cannot respond to a review on another vendor\'s product', function () {
    [$vendorUser1, $vendor1] = p9ReviewMakeVendor('p9-rev-vendor-a@test');
    [$vendorUser2, $vendor2] = p9ReviewMakeVendor('p9-rev-vendor-b@test');
    $product = p9ReviewMakeProduct($vendor2);     // belongs to vendor 2
    $customer = User::factory()->create(['role' => 'customer']);

    $review = ProductReview::create([
        'user_id' => $customer->id,
        'product_id' => $product->id,
        'rating' => 3,
        'body' => 'Mediocre.',
        'status' => 'approved',
        'is_verified_purchase' => true,
        'approved_at' => now(),
    ]);

    // Vendor 1 tries to respond to vendor 2's review → 403
    $this->actingAs($vendorUser1);
    $this->post("/vendor/reviews/{$review->id}/respond", [
        'response' => 'Sneaky!',
    ])->assertForbidden();

    expect($review->fresh()->vendor_response)->toBeNull();
});

it('demo seeder creates a review with a vendor response (verified purchase)', function () {
    $this->seed();
    $hasResponse = ProductReview::whereNotNull('vendor_response')->where('is_verified_purchase', true)->exists();
    expect($hasResponse)->toBeTrue();
});
