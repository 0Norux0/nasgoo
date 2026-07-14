<?php

declare(strict_types=1);

use App\Domain\Cart\CartService;
use App\Domain\Order\CheckoutService;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\PaymentMethodsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PaymentMethodsSeeder::class);
});

it('places an order from a populated cart', function () {
    $user = User::factory()->create();
    $product = Product::factory()->published()->create(['price_minor' => 5000, 'stock' => 10]);

    app(CartService::class)->addItem($user, $product, 2);

    $order = app(CheckoutService::class)->place($user, [
        'shipping_address' => [
            'recipient_name' => 'Test User', 'phone' => '+96599999999',
            'country' => 'KW', 'city' => 'Kuwait City', 'block' => '1', 'street' => 'Test St',
        ],
        'payment_method_slug' => 'cod',
    ]);

    expect($order)->toBeInstanceOf(Order::class);
    expect($order->status)->toBe(Order::STATUS_PENDING_PAYMENT);
    expect($order->payment_status)->toBe(Order::PAY_PENDING);
    expect($order->subtotal_minor)->toBe(10000);
    expect($order->total_minor)->toBe(10000);
    expect($order->items()->count())->toBe(1);
    expect($order->items()->first()->quantity)->toBe(2);
    expect($order->items()->first()->product_name)->toBe($product->name);  // snapshot
    expect($order->shippingAddress)->not->toBeNull();
    expect($order->shippingAddress->city)->toBe('Kuwait City');
});

it('snapshots commission via CommissionResolver at order time', function () {
    $user = User::factory()->create();
    $product = Product::factory()->published()->create(['price_minor' => 5000, 'stock' => 10]);

    app(CartService::class)->addItem($user, $product, 2);

    $order = app(CheckoutService::class)->place($user, [
        'shipping_address' => [
            'recipient_name' => 'X', 'phone' => '+1',
            'country' => 'KW', 'city' => 'C', 'street' => 'L1',
        ],
        'payment_method_slug' => 'cod',
    ]);

    $item = $order->items()->first();
    // No explicit rule → package default commission (Basic=30% from VendorPackagesSeeder)
    expect((float) $item->commission_percent)->toBe(30.00);
    expect($item->commission_amount_minor)->toBe(3000);  // 30% of 10000
    expect($item->vendor_earning_minor)->toBe(7000);
    expect($order->platform_commission_minor)->toBe(3000);
    expect($order->vendor_earnings_minor)->toBe(7000);
});

it('decrements product stock and clears the cart on placement', function () {
    $user = User::factory()->create();
    $product = Product::factory()->published()->create(['price_minor' => 5000, 'stock' => 10]);
    app(CartService::class)->addItem($user, $product, 3);

    app(CheckoutService::class)->place($user, [
        'shipping_address' => ['recipient_name' => 'X', 'phone' => '+1', 'country' => 'KW', 'city' => 'C', 'street' => 'L'],
        'payment_method_slug' => 'cod',
    ]);

    expect($product->fresh()->stock)->toBe(7);
    expect($user->cart()->first()->items_count)->toBe(0);
});

it('refuses to place an order from an empty cart', function () {
    $user = User::factory()->create();
    expect(fn () => app(CheckoutService::class)->place($user, [
        'shipping_address' => ['recipient_name' => 'X', 'phone' => '+1', 'country' => 'KW', 'city' => 'C', 'street' => 'L'],
        'payment_method_slug' => 'cod',
    ]))->toThrow(RuntimeException::class);
});

it('refuses payment methods that do not support the cart currency', function () {
    // COD only supports KWD/AED per seed
    $user = User::factory()->create();
    $product = Product::factory()->published()->create(['currency' => 'PKR', 'price_minor' => 5000, 'stock' => 10]);
    app(CartService::class)->addItem($user, $product, 1);

    expect(fn () => app(CheckoutService::class)->place($user, [
        'shipping_address' => ['recipient_name' => 'X', 'phone' => '+1', 'country' => 'KW', 'city' => 'C', 'street' => 'L'],
        'payment_method_slug' => 'cod',
    ]))->toThrow(RuntimeException::class);
});

it('requires a shipping address', function () {
    $user = User::factory()->create();
    $product = Product::factory()->published()->create(['stock' => 10]);
    app(CartService::class)->addItem($user, $product, 1);

    expect(fn () => app(CheckoutService::class)->place($user, [
        'payment_method_slug' => 'cod',
    ]))->toThrow(RuntimeException::class);
});

it('snapshots both billing and shipping addresses, defaulting billing to shipping', function () {
    $user = User::factory()->create();
    $product = Product::factory()->published()->create(['stock' => 10]);
    app(CartService::class)->addItem($user, $product, 1);

    $order = app(CheckoutService::class)->place($user, [
        'shipping_address' => ['recipient_name' => 'A', 'phone' => '+1', 'country' => 'KW', 'city' => 'C', 'street' => 'L'],
        'payment_method_slug' => 'cod',
    ]);

    expect($order->addresses()->count())->toBe(2);
    expect($order->billingAddress)->not->toBeNull();
    expect($order->billingAddress->city)->toBe('C');
});

it('rejects an unpublished product if it was unpublished between cart-add and checkout', function () {
    $user = User::factory()->create();
    $product = Product::factory()->published()->create(['stock' => 10]);
    app(CartService::class)->addItem($user, $product, 1);

    // Simulate vendor archiving the product after add-to-cart
    $product->update(['status' => Product::STATUS_ARCHIVED]);

    expect(fn () => app(CheckoutService::class)->place($user, [
        'shipping_address' => ['recipient_name' => 'X', 'phone' => '+1', 'country' => 'KW', 'city' => 'C', 'street' => 'L'],
        'payment_method_slug' => 'cod',
    ]))->toThrow(RuntimeException::class);
});
