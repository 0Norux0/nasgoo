<?php

declare(strict_types=1);

/**
 * Phase 4 v5.4 — Place Order flow coverage mapped to the developer checklist.
 *
 * Several of these overlap existing tests (Phase4HttpFlowTest, CheckoutTest,
 * ControllerReturnTypeRegressionTest) but are grouped here under explicit
 * "v5.4 place order" names so the CI per-file audit cleanly maps to the
 * developer's request list (items: COD/BT/Mock order creation, validation
 * visible, cart clears, stock decreases, customer/admin/vendor see the order).
 */

use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
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

/** Customer with a saved address + one published product in the cart. */
function readyCustomer(int $stock = 5, int $price = 5000): array
{
    $customer = User::factory()->create();
    $customer->assignRole('customer');
    Address::factory()->for($customer)->default()->create(['country' => 'KW', 'city' => 'Kuwait City']);

    $vendorUser = User::factory()->create();
    $vendorUser->assignRole('vendor');
    $vendor = Vendor::factory()->approved()->for($vendorUser)->create();
    $product = Product::factory()->published()->create([
        'vendor_id' => $vendor->id, 'stock' => $stock, 'price_minor' => $price, 'track_stock' => true,
    ]);

    actingAs($customer)->post('/cart/items', ['product_id' => $product->id, 'quantity' => 1]);

    return [$customer, $vendor, $product];
}

/* ─────────── Order creation per provider ─────────── */

it('v5.4 place order: COD creates an order and redirects to confirm', function () {
    [$customer] = readyCustomer();
    $addr = $customer->addresses()->first();

    actingAs($customer)->post('/checkout', [
        'shipping_address_id' => $addr->id,
        'payment_method_slug' => 'cod',
    ])->assertRedirect();

    $order = $customer->orders()->latest()->first();
    expect($order)->not->toBeNull();
    expect($order->payments()->first()->reference)->toStartWith('COD-');
});

it('v5.4 place order: Bank Transfer creates a pending order with BT reference', function () {
    [$customer] = readyCustomer();

    actingAs($customer)->post('/checkout', [
        'shipping_address_id' => $customer->addresses()->first()->id,
        'payment_method_slug' => 'manual_transfer',
    ])->assertRedirect();

    $order = $customer->orders()->latest()->first();
    expect($order->payment_status)->toBe(Order::PAY_PENDING);
    expect($order->payments()->first()->reference)->toStartWith('BT-');
});

it('v5.4 place order: Mock Online captures immediately and marks paid', function () {
    [$customer] = readyCustomer();

    actingAs($customer)->post('/checkout', [
        'shipping_address_id' => $customer->addresses()->first()->id,
        'payment_method_slug' => 'online_mock',
    ])->assertRedirect();

    $order = $customer->orders()->latest()->first();
    expect($order->payment_status)->toBe(Order::PAY_PAID);
});

/* ─────────── Validation is visible (item 10) ─────────── */

it('v5.4 place order: missing payment method returns a visible validation error', function () {
    [$customer] = readyCustomer();

    actingAs($customer)->post('/checkout', [
        'shipping_address_id' => $customer->addresses()->first()->id,
        // no payment_method_slug
    ])->assertSessionHasErrors('payment_method_slug');

    // No order created
    expect($customer->orders()->count())->toBe(0);
});

it('v5.4 place order: an unknown payment method does not silently succeed', function () {
    [$customer] = readyCustomer();

    $response = actingAs($customer)->post('/checkout', [
        'shipping_address_id' => $customer->addresses()->first()->id,
        'payment_method_slug' => 'nonexistent_method',
    ]);

    // Either a validation error or a flashed domain error — never a created order
    expect($customer->orders()->count())->toBe(0);
    expect($response->status())->not->toBe(500);
});

/* ─────────── Cart clears + stock decreases (items 11, 12) ─────────── */

it('v5.4 place order: cart clears and stock decreases after success', function () {
    [$customer, , $product] = readyCustomer(stock: 5);

    actingAs($customer)->post('/checkout', [
        'shipping_address_id' => $customer->addresses()->first()->id,
        'payment_method_slug' => 'cod',
    ])->assertRedirect();

    expect($customer->fresh()->cart()->first()->items_count)->toBe(0);
    expect($product->fresh()->stock)->toBe(4);
});

/* ─────────── Visibility: customer / vendor / admin (items 13, 14, 15) ─────────── */

it('v5.4 place order: the order appears in the customer order list', function () {
    [$customer] = readyCustomer();
    actingAs($customer)->post('/checkout', [
        'shipping_address_id' => $customer->addresses()->first()->id,
        'payment_method_slug' => 'cod',
    ]);

    actingAs($customer)->get('/orders')
        ->assertSuccessful()
        ->assertInertia(fn ($p) => $p->where('orders.total', 1));
});

it('v5.4 place order: the order appears in the vendor order list', function () {
    [$customer, $vendor] = readyCustomer();
    actingAs($customer)->post('/checkout', [
        'shipping_address_id' => $customer->addresses()->first()->id,
        'payment_method_slug' => 'cod',
    ]);

    actingAs($vendor->user)->get('/vendor/orders')
        ->assertSuccessful()
        ->assertInertia(fn ($p) => $p->where('orders.total', 1));
});

it('v5.4 place order: the order is visible to admin via the OrderResource query', function () {
    [$customer] = readyCustomer();
    actingAs($customer)->post('/checkout', [
        'shipping_address_id' => $customer->addresses()->first()->id,
        'payment_method_slug' => 'cod',
    ]);

    $order = $customer->orders()->latest()->first();

    // Admins see all orders — the Filament resource query is unscoped.
    $adminVisible = \App\Filament\Resources\OrderResource::getEloquentQuery()
        ->whereKey($order->id)
        ->exists();

    expect($adminVisible)->toBeTrue();
});
