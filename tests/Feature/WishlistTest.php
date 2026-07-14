<?php

declare(strict_types=1);

/**
 * Phase 5 — wishlist tests.
 */

use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Wishlist;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\VendorPackagesSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(VendorPackagesSeeder::class);
});

function makeProductForWishlist(): Product
{
    $vendorUser = User::factory()->create();
    $vendorUser->assignRole('vendor');
    $vendor = Vendor::factory()->approved()->for($vendorUser)->create();
    return Product::factory()->published()->create(['vendor_id' => $vendor->id]);
}

it('v6.0: customer can add a product to wishlist', function () {
    $customer = User::factory()->create();
    $customer->assignRole('customer');
    $product = makeProductForWishlist();

    actingAs($customer)->post('/wishlist/items', ['product_id' => $product->id])
        ->assertRedirect();

    expect(Wishlist::where('user_id', $customer->id)->where('product_id', $product->id)->exists())->toBeTrue();
});

it('v6.0: customer can remove a product from wishlist', function () {
    $customer = User::factory()->create();
    $customer->assignRole('customer');
    $product = makeProductForWishlist();

    Wishlist::create(['user_id' => $customer->id, 'product_id' => $product->id]);

    actingAs($customer)->delete("/wishlist/items/{$product->id}")
        ->assertRedirect();

    expect(Wishlist::where('user_id', $customer->id)->where('product_id', $product->id)->exists())->toBeFalse();
});

it('v6.0: duplicate wishlist entry is prevented (firstOrCreate)', function () {
    $customer = User::factory()->create();
    $customer->assignRole('customer');
    $product = makeProductForWishlist();

    actingAs($customer)->post('/wishlist/items', ['product_id' => $product->id]);
    actingAs($customer)->post('/wishlist/items', ['product_id' => $product->id]);
    actingAs($customer)->post('/wishlist/items', ['product_id' => $product->id]);

    expect(Wishlist::where('user_id', $customer->id)->where('product_id', $product->id)->count())->toBe(1);
});

it('v6.0: guest is redirected to login when hitting wishlist endpoints', function () {
    $product = makeProductForWishlist();

    $this->post('/wishlist/items', ['product_id' => $product->id])
        ->assertRedirect('/login');

    $this->get('/wishlist')->assertRedirect('/login');
});

it('v6.0: customer can view their wishlist with all their items', function () {
    $customer = User::factory()->create();
    $customer->assignRole('customer');
    $p1 = makeProductForWishlist();
    $p2 = makeProductForWishlist();

    Wishlist::create(['user_id' => $customer->id, 'product_id' => $p1->id]);
    Wishlist::create(['user_id' => $customer->id, 'product_id' => $p2->id]);

    actingAs($customer)->get('/wishlist')
        ->assertSuccessful()
        ->assertInertia(fn ($p) => $p->where('wishlist.total', 2));
});

it('v6.0: wishlist is scoped to the user — never leaks across accounts', function () {
    $owner = User::factory()->create(); $owner->assignRole('customer');
    $other = User::factory()->create(); $other->assignRole('customer');
    $product = makeProductForWishlist();

    Wishlist::create(['user_id' => $owner->id, 'product_id' => $product->id]);

    actingAs($other)->get('/wishlist')
        ->assertSuccessful()
        ->assertInertia(fn ($p) => $p->where('wishlist.total', 0));
});

it('v6.0: product detail page emits is_wishlisted for the current user', function () {
    $customer = User::factory()->create();
    $customer->assignRole('customer');
    $product = makeProductForWishlist();
    Wishlist::create(['user_id' => $customer->id, 'product_id' => $product->id]);

    actingAs($customer)->get("/products/{$product->slug}")
        ->assertInertia(fn ($p) => $p->where('is_wishlisted', true));
});
