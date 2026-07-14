<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\RolesAndPermissionsSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function makeOrderVendor(): array {
    $user = User::factory()->create();
    $user->assignRole('vendor');
    $vendor = Vendor::factory()->approved()->for($user)->create();
    return [$user, $vendor];
}

it('vendor sees only orders containing their items', function () {
    [$user, $vendor] = makeOrderVendor();
    [$otherUser, $otherVendor] = makeOrderVendor();

    // Order with this vendor's item
    $myOrder = Order::factory()->paid()->create();
    OrderItem::factory()->for($myOrder)->state(['vendor_id' => $vendor->id])->create();

    // Order with a foreign vendor's item
    $foreignOrder = Order::factory()->paid()->create();
    OrderItem::factory()->for($foreignOrder)->state(['vendor_id' => $otherVendor->id])->create();

    expect(Order::forVendor($vendor->id)->pluck('id')->toArray())
        ->toContain($myOrder->id)
        ->not->toContain($foreignOrder->id);
});

it('vendor.orders.index returns vendor-scoped data', function () {
    [$user, $vendor] = makeOrderVendor();
    $myOrder = Order::factory()->paid()->create();
    OrderItem::factory()->for($myOrder)->state(['vendor_id' => $vendor->id])->create();

    [$_, $otherVendor] = makeOrderVendor();
    $foreign = Order::factory()->paid()->create();
    OrderItem::factory()->for($foreign)->state(['vendor_id' => $otherVendor->id])->create();

    $response = actingAs($user)->get('/vendor/orders');
    $response->assertSuccessful();
    $response->assertInertia(fn ($page) =>
        $page->where('orders.total', 1)
    );
});

it('vendor.orders.show 404s on a foreign order', function () {
    [$user] = makeOrderVendor();
    [$_, $otherVendor] = makeOrderVendor();

    $foreign = Order::factory()->paid()->create();
    OrderItem::factory()->for($foreign)->state(['vendor_id' => $otherVendor->id])->create();

    actingAs($user)->get("/vendor/orders/{$foreign->id}")->assertNotFound();
});

it('customer-self-cancel only allowed for pre-shipment statuses', function () {
    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $cancellable = Order::factory()->paid()->for($customer)->create();
    $delivered   = Order::factory()->delivered()->for($customer)->create();

    expect($customer->can('cancel', $cancellable))->toBeTrue();
    expect($customer->can('cancel', $delivered))->toBeFalse();
});

it('order policy view allows owner, admin, and item-vendor', function () {
    [$user, $vendor] = makeOrderVendor();
    $customer = User::factory()->create();
    $customer->assignRole('customer');
    $admin = User::factory()->create();
    $admin->assignRole('admin_staff');

    $order = Order::factory()->paid()->for($customer)->create();
    OrderItem::factory()->for($order)->state(['vendor_id' => $vendor->id])->create();

    expect($customer->can('view', $order))->toBeTrue();
    expect($admin->can('view', $order))->toBeTrue();
    expect($user->can('view', $order))->toBeTrue();

    // Foreign user should not see it
    $foreign = User::factory()->create();
    $foreign->assignRole('customer');
    expect($foreign->can('view', $order))->toBeFalse();
});
