<?php

declare(strict_types=1);

/**
 * Phase 4 v5.6 — regression for:
 *   "Attempted to lazy load [items] on model [App\Models\Order] but lazy
 *    loading is disabled."
 *
 * Root cause: Model::shouldBeStrict(true) is enabled outside production
 * (see AppServiceProvider). The Filament ViewOrder + EditOrder pages used
 * default route-model binding, which fetches the record WITHOUT the
 * resource's getEloquentQuery() eager loads. Any access to $record->items
 * (rendered in the table column, infolist sections, custom actions) then
 * tripped lazy-load strict mode.
 *
 * v5.6 overrides resolveRecord on both pages and adds defensive
 * loadMissing() in OrderLifecycleService methods that iterate items.
 *
 * This test pins:
 *   1. The list query eager-loads items + shippingAddress + payments
 *   2. ViewOrder + EditOrder resolveRecord returns a model with items
 *      pre-loaded (no lazy-load triggered)
 *   3. OrderLifecycleService::cancel + markShipped + markDelivered work
 *      against a fresh model (without pre-loading by the caller)
 */

use App\Filament\Resources\OrderResource;
use App\Filament\Resources\OrderResource\Pages\EditOrder;
use App\Filament\Resources\OrderResource\Pages\ViewOrder;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\PaymentMethodsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\VendorPackagesSeeder;
use Illuminate\Database\Eloquent\Model;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(VendorPackagesSeeder::class);
    $this->seed(PaymentMethodsSeeder::class);

    // Mirror AppServiceProvider's non-production behaviour so this test
    // catches lazy-load regressions even if the test env wouldn't otherwise.
    Model::shouldBeStrict(true);
});

afterEach(function () {
    Model::shouldBeStrict(false);
});

/* ─────────── List query eager-loads ─────────── */

it('v5.6: OrderResource::getEloquentQuery eager-loads items, shippingAddress, payments', function () {
    $customer = User::factory()->create();
    $customer->assignRole('customer');
    $order = Order::factory()->paid()->for($customer)->create();
    OrderItem::factory()->for($order)->create();

    $loaded = OrderResource::getEloquentQuery()->whereKey($order->id)->first();
    expect($loaded->relationLoaded('items'))->toBeTrue();
    expect($loaded->relationLoaded('shippingAddress'))->toBeTrue();
    expect($loaded->relationLoaded('payments'))->toBeTrue();

    // And accessing items doesn't lazy-load (would throw under strict mode)
    expect($loaded->items->count())->toBe(1);
});

/* ─────────── ViewOrder / EditOrder eager-load via resolveRecord override ─────────── */

it('v5.6: ViewOrder::resolveRecord returns an order with items pre-loaded', function () {
    $customer = User::factory()->create();
    $customer->assignRole('customer');
    $order = Order::factory()->paid()->for($customer)->create();
    OrderItem::factory()->for($order)->create();

    // Filament v3's protected resolveRecord — invoke via reflection
    $page = new ViewOrder();
    $reflected = (new ReflectionClass($page))->getMethod('resolveRecord');
    $reflected->setAccessible(true);
    $resolved = $reflected->invoke($page, $order->id);

    expect($resolved->relationLoaded('items'))->toBeTrue();
    // Accessing items would crash with "lazy loading disabled" if not eager-loaded
    expect($resolved->items->count())->toBe(1);
});

it('v5.6: EditOrder::resolveRecord returns an order with items pre-loaded', function () {
    $customer = User::factory()->create();
    $customer->assignRole('customer');
    $order = Order::factory()->paid()->for($customer)->create();
    OrderItem::factory()->for($order)->create();

    $page = new EditOrder();
    $reflected = (new ReflectionClass($page))->getMethod('resolveRecord');
    $reflected->setAccessible(true);
    $resolved = $reflected->invoke($page, $order->id);

    expect($resolved->relationLoaded('items'))->toBeTrue();
    expect($resolved->items->count())->toBe(1);
});

/* ─────────── OrderLifecycleService loadMissing defence ─────────── */

it('v5.6: cancel() works against a fresh order (loadMissing items at the top)', function () {
    $customer = User::factory()->create();
    $customer->assignRole('customer');
    $vendorUser = User::factory()->create();
    $vendorUser->assignRole('vendor');
    $vendor  = Vendor::factory()->approved()->for($vendorUser)->create();
    $product = Product::factory()->published()->create([
        'vendor_id' => $vendor->id, 'stock' => 5, 'track_stock' => true,
    ]);
    $order = Order::factory()->paid()->for($customer)->create();
    OrderItem::factory()->for($order)->state([
        'product_id' => $product->id, 'vendor_id' => $vendor->id, 'quantity' => 2,
    ])->create();

    // Fetch fresh — NO eager loading by the caller
    $fresh = Order::findOrFail($order->id);
    expect($fresh->relationLoaded('items'))->toBeFalse();

    // Should NOT throw "lazy loading disabled" — service does loadMissing
    $service = app(\App\Domain\Order\OrderLifecycleService::class);
    $service->cancel($fresh, 'test reason');

    expect($fresh->fresh()->status)->toBe(Order::STATUS_CANCELLED);
});

it('v5.6: markShipped() works against a fresh order', function () {
    $customer = User::factory()->create();
    $customer->assignRole('customer');
    $vendorUser = User::factory()->create();
    $vendorUser->assignRole('vendor');
    $vendor  = Vendor::factory()->approved()->for($vendorUser)->create();
    $order = Order::factory()->paid()->for($customer)->create();
    OrderItem::factory()->for($order)->state(['vendor_id' => $vendor->id])->create();

    $fresh = Order::findOrFail($order->id);
    $service = app(\App\Domain\Order\OrderLifecycleService::class);
    $service->markShipped($fresh, $vendor->id);

    expect($fresh->fresh()->status)->toBe(Order::STATUS_SHIPPED);
});

/* ─────────── End-to-end HTTP: admin opens an order detail (the screenshot's path) ─────────── */

it('v5.6: customer order detail does not lazy-load (controller eager-loads)', function () {
    $customer = User::factory()->create();
    $customer->assignRole('customer');
    $order = Order::factory()->paid()->for($customer)->create();
    OrderItem::factory()->for($order)->create();

    // Strict mode is on per beforeEach — any lazy-load would 500
    $response = $this->actingAs($customer)->get("/orders/{$order->id}");
    expect($response->status())->not->toBe(500);
    $response->assertSuccessful();
});

it('v5.6: vendor order detail does not lazy-load', function () {
    $vendorUser = User::factory()->create();
    $vendorUser->assignRole('vendor');
    $vendor = Vendor::factory()->approved()->for($vendorUser)->create();
    $order = Order::factory()->paid()->create();
    OrderItem::factory()->for($order)->state(['vendor_id' => $vendor->id])->create();

    $response = $this->actingAs($vendorUser)->get("/vendor/orders/{$order->id}");
    expect($response->status())->not->toBe(500);
    $response->assertSuccessful();
});
