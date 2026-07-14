<?php

declare(strict_types=1);

use App\Domain\Cart\CartService;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('creates a cart on first add and snapshots the unit price', function () {
    $user = User::factory()->create();
    $product = Product::factory()->published()->create(['price_minor' => 5000, 'stock' => 10]);

    $item = app(CartService::class)->addItem($user, $product, 2);

    expect($item->quantity)->toBe(2);
    expect($item->unit_price_minor)->toBe(5000);
    expect($user->cart()->first()->items_count)->toBe(2);
    expect($user->cart()->first()->subtotal_minor)->toBe(10000);
});

it('collapses duplicate adds onto the same cart_items row', function () {
    $user = User::factory()->create();
    $product = Product::factory()->published()->create(['price_minor' => 5000, 'stock' => 10]);

    $svc = app(CartService::class);
    $svc->addItem($user, $product, 2);
    $svc->addItem($user, $product, 3);

    expect($user->cart()->first()->items()->count())->toBe(1);
    expect($user->cart()->first()->items()->first()->quantity)->toBe(5);
});

it('updates quantity in place', function () {
    $user = User::factory()->create();
    $product = Product::factory()->published()->create(['price_minor' => 5000, 'stock' => 10]);

    $svc = app(CartService::class);
    $item = $svc->addItem($user, $product, 1);
    $updated = $svc->updateQuantity($user, $item->id, 4);

    expect($updated->quantity)->toBe(4);
    expect($user->cart()->first()->subtotal_minor)->toBe(20000);
});

it('removes items', function () {
    $user = User::factory()->create();
    $product = Product::factory()->published()->create(['stock' => 10]);

    $svc = app(CartService::class);
    $item = $svc->addItem($user, $product, 1);
    $svc->removeItem($user, $item->id);

    expect($user->cart()->first()->items_count)->toBe(0);
    expect($user->cart()->first()->items()->count())->toBe(0);
});

it('clears the cart', function () {
    $user = User::factory()->create();
    $svc = app(CartService::class);
    $svc->addItem($user, Product::factory()->published()->create(['stock' => 10]), 1);
    $svc->addItem($user, Product::factory()->published()->create(['stock' => 10]), 1);

    $svc->clear($user);

    expect($user->cart()->first()->items_count)->toBe(0);
});

it('rejects unpublished products', function () {
    $user = User::factory()->create();
    $product = Product::factory()->draft()->create();

    expect(fn () => app(CartService::class)->addItem($user, $product, 1))
        ->toThrow(RuntimeException::class);
});

it('rejects quantity > stock for simple product', function () {
    $user = User::factory()->create();
    $product = Product::factory()->published()->create(['stock' => 3, 'track_stock' => true]);

    expect(fn () => app(CartService::class)->addItem($user, $product, 5))
        ->toThrow(RuntimeException::class);
});

it('rejects mixed-currency cart', function () {
    $user = User::factory()->create();
    $kwd = Product::factory()->published()->create(['currency' => 'KWD', 'stock' => 10]);
    $usd = Product::factory()->published()->create(['currency' => 'USD', 'stock' => 10]);

    $svc = app(CartService::class);
    $svc->addItem($user, $kwd, 1);
    expect(fn () => $svc->addItem($user, $usd, 1))
        ->toThrow(RuntimeException::class);
});

it('validates variants belong to the product and are active', function () {
    $user = User::factory()->create();
    $product = Product::factory()->variable()->published()->create(['stock' => 0]);
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id, 'stock' => 5, 'price_minor' => 3000, 'is_active' => true,
    ]);

    $svc = app(CartService::class);
    $item = $svc->addItem($user, $product, 1, $variant->id);
    expect($item->variant_id)->toBe($variant->id);
    expect($item->unit_price_minor)->toBe(3000);

    // foreign variant id rejected
    $other = ProductVariant::factory()->create(['stock' => 5]);
    expect(fn () => $svc->addItem($user, $product, 1, $other->id))
        ->toThrow(InvalidArgumentException::class);
});

it('respects variant stock not parent product stock', function () {
    $user = User::factory()->create();
    $product = Product::factory()->variable()->published()->create(['stock' => 999]);
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id, 'stock' => 2, 'price_minor' => 1000, 'is_active' => true,
    ]);

    expect(fn () => app(CartService::class)->addItem($user, $product, 5, $variant->id))
        ->toThrow(RuntimeException::class);
});
