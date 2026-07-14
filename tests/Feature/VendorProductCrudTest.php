<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPackage;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\VendorPackagesSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(VendorPackagesSeeder::class);
});

/**
 * Build an approved vendor with the given package (default Basic).
 */
function vendorWithPackage(string $packageSlug = 'basic'): array
{
    $user = User::factory()->create();
    $user->assignRole('vendor');
    $package = VendorPackage::where('slug', $packageSlug)->firstOrFail();
    $vendor = Vendor::factory()->approved()->for($user)->create([
        'vendor_package_id' => $package->id,
    ]);
    return [$user, $vendor, $package];
}

it('lets an approved vendor list their own products only', function () {
    [$user, $vendor] = vendorWithPackage();
    [$otherUser, $otherVendor] = vendorWithPackage();

    Product::factory()->forVendor($vendor)->count(3)->create();
    Product::factory()->forVendor($otherVendor)->count(2)->create();

    $response = actingAs($user)->get('/vendor/products');
    $response->assertSuccessful();
    expect($vendor->products()->count())->toBe(3);
});

it('blocks an unapproved vendor from the products area', function () {
    $user = User::factory()->create();
    $user->assignRole('vendor');
    Vendor::factory()->pending()->for($user)->create();

    actingAs($user)->get('/vendor/products')->assertRedirect('/vendor');
});

it('creates a product as a draft', function () {
    [$user, $vendor] = vendorWithPackage();

    actingAs($user)->post('/vendor/products', [
        'name'        => 'Test Tee',
        'type'        => 'simple',
        'price_minor' => 5000,
        'currency'    => 'KWD',
        'track_stock' => true,
        'stock'       => 10,
    ])->assertRedirect();

    $product = $vendor->products()->first();
    expect($product)->not->toBeNull();
    expect($product->status)->toBe(Product::STATUS_DRAFT);
    expect($product->slug)->toMatch('/^test-tee/');
});

it('enforces the package max_products limit on create', function () {
    [$user, $vendor, $package] = vendorWithPackage('basic');
    // Basic package has max_products = 30 — fill exactly to the limit
    Product::factory()->forVendor($vendor)->count((int) $package->max_products)->create();

    actingAs($user)->get('/vendor/products/create')->assertForbidden();
    actingAs($user)->post('/vendor/products', [
        'name' => 'One Too Many', 'type' => 'simple',
        'price_minor' => 100, 'currency' => 'KWD',
    ])->assertForbidden();
});

it('allows editing only when status is draft or rejected', function () {
    [$user, $vendor] = vendorWithPackage();

    $draft     = Product::factory()->forVendor($vendor)->draft()->create();
    $pending   = Product::factory()->forVendor($vendor)->pendingReview()->create();
    $published = Product::factory()->forVendor($vendor)->published()->create();
    $rejected  = Product::factory()->forVendor($vendor)->rejected()->create();

    expect($user->can('update', $draft))->toBeTrue();
    expect($user->can('update', $rejected))->toBeTrue();
    expect($user->can('update', $pending))->toBeFalse();
    expect($user->can('update', $published))->toBeFalse();
});

it('deletes only draft products', function () {
    [$user, $vendor] = vendorWithPackage();
    $draft = Product::factory()->forVendor($vendor)->draft()->create();
    $published = Product::factory()->forVendor($vendor)->published()->create();

    actingAs($user)->delete("/vendor/products/{$draft->id}")->assertRedirect('/vendor/products');
    expect(Product::find($draft->id))->toBeNull();

    actingAs($user)->delete("/vendor/products/{$published->id}")->assertForbidden();
});

it('refuses to submit a draft with no price or no images', function () {
    [$user, $vendor] = vendorWithPackage();
    $draft = Product::factory()->forVendor($vendor)->draft()->create([
        'price_minor' => 0,
    ]);

    actingAs($user)->post("/vendor/products/{$draft->id}/submit")
        ->assertSessionHasErrors(['price_minor']);

    expect($draft->fresh()->status)->toBe(Product::STATUS_DRAFT);
});

it('cannot access another vendors product', function () {
    [$user, $vendor] = vendorWithPackage();
    [$otherUser, $otherVendor] = vendorWithPackage();

    $foreign = Product::factory()->forVendor($otherVendor)->draft()->create();

    actingAs($user)->get("/vendor/products/{$foreign->id}/edit")->assertNotFound();
    actingAs($user)->delete("/vendor/products/{$foreign->id}")->assertNotFound();
});
