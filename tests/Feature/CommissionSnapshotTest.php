<?php

declare(strict_types=1);

use App\Domain\Cart\CartService;
use App\Domain\Order\CheckoutService;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorCommissionRule;
use Database\Seeders\PaymentMethodsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\VendorPackagesSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(VendorPackagesSeeder::class);
    $this->seed(PaymentMethodsSeeder::class);
});

it('uses CommissionResolver result at place time and snapshots it on order_items', function () {
    $user = User::factory()->create();
    $vendor = Vendor::factory()->approved()->create();
    $product = Product::factory()->forVendor($vendor)->published()->create([
        'price_minor' => 10000, 'stock' => 10, 'currency' => 'KWD',
    ]);

    // Custom vendor rule: 15%
    VendorCommissionRule::create([
        'scope' => VendorCommissionRule::SCOPE_VENDOR, 'vendor_id' => $vendor->id,
        'commission_type' => VendorCommissionRule::TYPE_PERCENT, 'percent_value' => 15,
        'product_type' => 'any', 'payment_method' => 'any', 'is_active' => true, 'priority' => 0,
    ]);

    app(CartService::class)->addItem($user, $product, 2);
    $order = app(CheckoutService::class)->place($user, [
        'shipping_address' => ['recipient_name' => 'X', 'phone' => '+1', 'country' => 'KW', 'city' => 'C', 'street' => 'L'],
        'payment_method_slug' => 'cod',
    ]);

    $item = $order->items()->first();
    expect((float) $item->commission_percent)->toBe(15.00);
    expect($item->commission_amount_minor)->toBe(3000); // 15% of 20000
    expect($item->vendor_earning_minor)->toBe(17000);
});

it('later commission-rule changes do not retroactively modify placed orders', function () {
    $user = User::factory()->create();
    $vendor = Vendor::factory()->approved()->create();
    $product = Product::factory()->forVendor($vendor)->published()->create([
        'price_minor' => 10000, 'stock' => 10,
    ]);

    $rule = VendorCommissionRule::create([
        'scope' => VendorCommissionRule::SCOPE_VENDOR, 'vendor_id' => $vendor->id,
        'commission_type' => VendorCommissionRule::TYPE_PERCENT, 'percent_value' => 20,
        'product_type' => 'any', 'payment_method' => 'any', 'is_active' => true, 'priority' => 0,
    ]);

    app(CartService::class)->addItem($user, $product, 1);
    $order = app(CheckoutService::class)->place($user, [
        'shipping_address' => ['recipient_name' => 'X', 'phone' => '+1', 'country' => 'KW', 'city' => 'C', 'street' => 'L'],
        'payment_method_slug' => 'cod',
    ]);
    $itemBefore = $order->items()->first();
    expect((float) $itemBefore->commission_percent)->toBe(20.00);

    // Now admin changes the rule to 50% — should not touch this order
    $rule->update(['percent_value' => 50]);

    $itemAfter = $order->fresh()->items()->first();
    expect((float) $itemAfter->commission_percent)->toBe(20.00);
    expect($itemAfter->commission_amount_minor)->toBe(2000);
});

it('falls back to vendor.package.default_admin_commission_percent when no rule matches', function () {
    $user = User::factory()->create();
    // ProductFactory auto-attaches Basic package (30% commission) via withActivePackage()
    $product = Product::factory()->published()->create(['price_minor' => 10000, 'stock' => 10]);
    app(CartService::class)->addItem($user, $product, 1);

    $order = app(CheckoutService::class)->place($user, [
        'shipping_address' => ['recipient_name' => 'X', 'phone' => '+1', 'country' => 'KW', 'city' => 'C', 'street' => 'L'],
        'payment_method_slug' => 'cod',
    ]);

    expect((float) $order->items()->first()->commission_percent)->toBe(30.00);
});
