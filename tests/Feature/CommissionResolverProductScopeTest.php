<?php

declare(strict_types=1);

use App\Domain\Commission\CommissionResolver;
use App\Models\Category;
use App\Models\Product;
use App\Models\Vendor;
use App\Models\VendorCommissionRule;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\VendorPackagesSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(VendorPackagesSeeder::class);
});

it('resolves product-scoped rules ahead of category, vendor, package, global', function () {
    $vendor = Vendor::factory()->approved()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->forVendor($vendor)->forCategory($category)->published()->create();

    // 4 candidate rules — product scope should win
    VendorCommissionRule::create([
        'scope' => VendorCommissionRule::SCOPE_GLOBAL, 'scope_id' => null,
        'commission_type' => VendorCommissionRule::TYPE_PERCENT, 'percent_value' => 30,
        'product_type' => 'any', 'payment_method' => 'any', 'is_active' => true, 'priority' => 0,
    ]);
    VendorCommissionRule::create([
        'scope' => VendorCommissionRule::SCOPE_VENDOR, 'vendor_id' => $vendor->id,
        'commission_type' => VendorCommissionRule::TYPE_PERCENT, 'percent_value' => 20,
        'product_type' => 'any', 'payment_method' => 'any', 'is_active' => true, 'priority' => 0,
    ]);
    VendorCommissionRule::create([
        'scope' => VendorCommissionRule::SCOPE_CATEGORY, 'scope_id' => $category->id,
        'commission_type' => VendorCommissionRule::TYPE_PERCENT, 'percent_value' => 15,
        'product_type' => 'any', 'payment_method' => 'any', 'is_active' => true, 'priority' => 0,
    ]);
    VendorCommissionRule::create([
        'scope' => VendorCommissionRule::SCOPE_PRODUCT, 'scope_id' => $product->id,
        'commission_type' => VendorCommissionRule::TYPE_PERCENT, 'percent_value' => 5,
        'product_type' => 'any', 'payment_method' => 'any', 'is_active' => true, 'priority' => 0,
    ]);

    $rule = app(CommissionResolver::class)->forProduct($product);

    expect($rule)->not->toBeNull();
    expect($rule->scope)->toBe(VendorCommissionRule::SCOPE_PRODUCT);
    expect((float) $rule->percent_value)->toBe(5.0);
});

it('falls through to category when no product-scoped rule exists', function () {
    $vendor = Vendor::factory()->approved()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->forVendor($vendor)->forCategory($category)->published()->create();

    VendorCommissionRule::create([
        'scope' => VendorCommissionRule::SCOPE_VENDOR, 'vendor_id' => $vendor->id,
        'commission_type' => VendorCommissionRule::TYPE_PERCENT, 'percent_value' => 20,
        'product_type' => 'any', 'payment_method' => 'any', 'is_active' => true, 'priority' => 0,
    ]);
    VendorCommissionRule::create([
        'scope' => VendorCommissionRule::SCOPE_CATEGORY, 'scope_id' => $category->id,
        'commission_type' => VendorCommissionRule::TYPE_PERCENT, 'percent_value' => 15,
        'product_type' => 'any', 'payment_method' => 'any', 'is_active' => true, 'priority' => 0,
    ]);

    $rule = app(CommissionResolver::class)->forProduct($product);

    expect($rule->scope)->toBe(VendorCommissionRule::SCOPE_CATEGORY);
    expect((float) $rule->percent_value)->toBe(15.0);
});

it('falls through to vendor when no product/category rule exists', function () {
    $vendor = Vendor::factory()->approved()->create();
    $product = Product::factory()->forVendor($vendor)->published()->create();

    VendorCommissionRule::create([
        'scope' => VendorCommissionRule::SCOPE_VENDOR, 'vendor_id' => $vendor->id,
        'commission_type' => VendorCommissionRule::TYPE_PERCENT, 'percent_value' => 18,
        'product_type' => 'any', 'payment_method' => 'any', 'is_active' => true, 'priority' => 0,
    ]);

    $rule = app(CommissionResolver::class)->forProduct($product);

    expect($rule->scope)->toBe(VendorCommissionRule::SCOPE_VENDOR);
    expect((float) $rule->percent_value)->toBe(18.0);
});

it('returns null when no rule matches', function () {
    $vendor = Vendor::factory()->approved()->create();
    $product = Product::factory()->forVendor($vendor)->published()->create();

    expect(app(CommissionResolver::class)->forProduct($product))->toBeNull();
});

it('respects is_active=false on rules', function () {
    $vendor = Vendor::factory()->approved()->create();
    $product = Product::factory()->forVendor($vendor)->published()->create();

    VendorCommissionRule::create([
        'scope' => VendorCommissionRule::SCOPE_PRODUCT, 'scope_id' => $product->id,
        'commission_type' => VendorCommissionRule::TYPE_PERCENT, 'percent_value' => 5,
        'product_type' => 'any', 'payment_method' => 'any', 'is_active' => false, 'priority' => 0,
    ]);
    VendorCommissionRule::create([
        'scope' => VendorCommissionRule::SCOPE_VENDOR, 'vendor_id' => $vendor->id,
        'commission_type' => VendorCommissionRule::TYPE_PERCENT, 'percent_value' => 20,
        'product_type' => 'any', 'payment_method' => 'any', 'is_active' => true, 'priority' => 0,
    ]);

    $rule = app(CommissionResolver::class)->forProduct($product);

    expect($rule->scope)->toBe(VendorCommissionRule::SCOPE_VENDOR);
});
