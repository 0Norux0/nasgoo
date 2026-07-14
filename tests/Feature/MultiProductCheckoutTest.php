<?php

declare(strict_types=1);

/**
 * Phase 4 v5.7 — regression for:
 *   "Attempted to lazy load [vendor] on model [App\Models\Product] but lazy
 *    loading is disabled."
 *
 * The bug only fires with MULTIPLE cart items because Eloquent's strict-mode
 * lazy-load detector (`Model::preventLazyLoading()`) is specifically an N+1
 * detector — it triggers when a relation would be lazy-loaded for multiple
 * parents in a collection. With a single item, the same code path silently
 * lazy-loads and works.
 *
 * Root cause: CheckoutService::place() did
 *   $cart->loadMissing(['items.product', 'items.variant'])
 * but the order-items loop then read:
 *   $product->vendor                              ← lazy
 *   $product->vendor->currentPackage()            ← lazy x2 (activeSubscription, package)
 *   $this->commissions->forProduct($product)      ← reads vendor + currentPackage again
 *
 * v5.7 extends the loadMissing to eager-load the full chain. Also fixes the
 * same class of bug in CartController, CheckoutController, and
 * OrderLifecycleService::cancel().
 *
 * These tests run with `Model::shouldBeStrict(true)` so any lazy-load on any
 * relation in checkout/cart/cancel paths fails the test.
 */

use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\PaymentMethodsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\VendorPackagesSeeder;
use Illuminate\Database\Eloquent\Model;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(VendorPackagesSeeder::class);
    $this->seed(PaymentMethodsSeeder::class);

    // Strict mode catches any lazy-load that the eager-loads don't cover.
    Model::shouldBeStrict(true);
});

afterEach(function () {
    Model::shouldBeStrict(false);
});

/** Helper — customer with address + saved card-ready cart. */
function setupCustomerWithAddress(): User
{
    $customer = User::factory()->create();
    $customer->assignRole('customer');
    Address::factory()->for($customer)->default()->create([
        'country' => 'KW',
        'city'    => 'Kuwait City',
    ]);
    return $customer;
}

/** Helper — approved vendor + N published products with stock. */
function approvedVendorWithProducts(int $productCount = 1, int $stock = 10): array
{
    $vendorUser = User::factory()->create();
    $vendorUser->assignRole('vendor');
    $vendor = Vendor::factory()->approved()->for($vendorUser)->create();

    $products = [];
    for ($i = 0; $i < $productCount; $i++) {
        $products[] = Product::factory()->published()->create([
            'vendor_id'   => $vendor->id,
            'stock'       => $stock,
            'track_stock' => true,
            'price_minor' => 5000 + ($i * 1000),
        ]);
    }
    return [$vendor, $products];
}

/* ─────────── Single product baseline (regression: this should still work) ─────────── */

it('v5.7: single-product cart still places order successfully', function () {
    $customer = setupCustomerWithAddress();
    [, $products] = approvedVendorWithProducts(1);

    actingAs($customer)->post('/cart/items', ['product_id' => $products[0]->id, 'quantity' => 1]);

    $response = actingAs($customer)->post('/checkout', [
        'shipping_address_id' => $customer->addresses()->first()->id,
        'payment_method_slug' => 'cod',
    ]);

    expect($response->status())->not->toBe(500, 'lazy-load crash regressed');
    $response->assertRedirect();
    expect($customer->orders()->count())->toBe(1);
});

/* ─────────── Multi-product, same vendor (the screenshot's case) ─────────── */

it('v5.7: two products from the same vendor checkout without lazy-load crash', function () {
    $customer = setupCustomerWithAddress();
    [, $products] = approvedVendorWithProducts(2);

    actingAs($customer)->post('/cart/items', ['product_id' => $products[0]->id, 'quantity' => 1]);
    actingAs($customer)->post('/cart/items', ['product_id' => $products[1]->id, 'quantity' => 1]);

    // The crash was specifically here — multi-item cart triggers the N+1
    // lazy-load detector when CheckoutService::place reads $product->vendor.
    $response = actingAs($customer)->post('/checkout', [
        'shipping_address_id' => $customer->addresses()->first()->id,
        'payment_method_slug' => 'cod',
    ]);

    expect($response->status())->not->toBe(500);
    $response->assertRedirect();

    $order = $customer->orders()->latest()->first();
    expect($order)->not->toBeNull();
    expect($order->items()->count())->toBe(2);
    expect((int) $order->total_minor)->toBe(5000 + 6000);
});

it('v5.7: three products from the same vendor checkout without lazy-load crash', function () {
    $customer = setupCustomerWithAddress();
    [, $products] = approvedVendorWithProducts(3);

    foreach ($products as $p) {
        actingAs($customer)->post('/cart/items', ['product_id' => $p->id, 'quantity' => 1]);
    }

    $response = actingAs($customer)->post('/checkout', [
        'shipping_address_id' => $customer->addresses()->first()->id,
        'payment_method_slug' => 'cod',
    ]);

    expect($response->status())->not->toBe(500);
    $response->assertRedirect();
    expect($customer->orders()->latest()->first()->items()->count())->toBe(3);
});

/* ─────────── Multi-product, multi-vendor ─────────── */

it('v5.7: products from two different vendors checkout in a single order', function () {
    $customer = setupCustomerWithAddress();
    [$vendor1, $p1] = approvedVendorWithProducts(1);
    [$vendor2, $p2] = approvedVendorWithProducts(1);

    actingAs($customer)->post('/cart/items', ['product_id' => $p1[0]->id, 'quantity' => 1]);
    actingAs($customer)->post('/cart/items', ['product_id' => $p2[0]->id, 'quantity' => 1]);

    $response = actingAs($customer)->post('/checkout', [
        'shipping_address_id' => $customer->addresses()->first()->id,
        'payment_method_slug' => 'cod',
    ]);

    expect($response->status())->not->toBe(500);
    $response->assertRedirect();

    $order = $customer->orders()->latest()->first();
    expect($order->items()->count())->toBe(2);
    // order_items.vendor_id is the per-line vendor (no separate vendor_orders table)
    expect($order->items->pluck('vendor_id')->unique()->sort()->values()->all())
        ->toBe(collect([$vendor1->id, $vendor2->id])->sort()->values()->all());
});

/* ─────────── Checkout PAGE (GET) renders with multi-item cart ─────────── */

it('v5.7: /checkout page renders with multiple items (CheckoutController::show eager-loads)', function () {
    $customer = setupCustomerWithAddress();
    [, $products] = approvedVendorWithProducts(3);
    foreach ($products as $p) {
        actingAs($customer)->post('/cart/items', ['product_id' => $p->id, 'quantity' => 1]);
    }

    // Pre-v5.7 this 500'd because show()'s presenter touched $i->product->primaryImage
    // and $i->vendor->business_name lazily for 3 items.
    $response = actingAs($customer)->get('/checkout');
    expect($response->status())->not->toBe(500);
    $response->assertSuccessful();
});

it('v5.7: /cart page renders with multiple items (CartController::index eager-loads)', function () {
    $customer = setupCustomerWithAddress();
    [, $products] = approvedVendorWithProducts(3);
    foreach ($products as $p) {
        actingAs($customer)->post('/cart/items', ['product_id' => $p->id, 'quantity' => 1]);
    }

    $response = actingAs($customer)->get('/cart');
    expect($response->status())->not->toBe(500);
    $response->assertSuccessful();
});

/* ─────────── Cancel restock works with multiple items ─────────── */

it('v5.7: cancelling a multi-item order restocks every line without lazy-load', function () {
    $customer = setupCustomerWithAddress();
    [, $products] = approvedVendorWithProducts(2, stock: 10);

    foreach ($products as $p) {
        actingAs($customer)->post('/cart/items', ['product_id' => $p->id, 'quantity' => 2]);
    }
    actingAs($customer)->post('/checkout', [
        'shipping_address_id' => $customer->addresses()->first()->id,
        'payment_method_slug' => 'cod',
    ]);

    $order = $customer->orders()->latest()->first();
    expect($products[0]->fresh()->stock)->toBe(8);
    expect($products[1]->fresh()->stock)->toBe(8);

    // Fetch fresh (no caller-side eager-load) — must not lazy-load
    $fresh = Order::findOrFail($order->id);
    $service = app(\App\Domain\Order\OrderLifecycleService::class);
    $service->cancel($fresh, 'test cancellation');

    expect($products[0]->fresh()->stock)->toBe(10);
    expect($products[1]->fresh()->stock)->toBe(10);
});

/* ─────────── Vendor sees only their own items in /vendor/orders ─────────── */

it('v5.7: vendor sees only their own items on a multi-vendor order', function () {
    $customer = setupCustomerWithAddress();
    [$vendor1, $p1] = approvedVendorWithProducts(1);
    [$vendor2, $p2] = approvedVendorWithProducts(1);

    actingAs($customer)->post('/cart/items', ['product_id' => $p1[0]->id, 'quantity' => 1]);
    actingAs($customer)->post('/cart/items', ['product_id' => $p2[0]->id, 'quantity' => 1]);
    actingAs($customer)->post('/checkout', [
        'shipping_address_id' => $customer->addresses()->first()->id,
        'payment_method_slug' => 'cod',
    ]);
    $order = $customer->orders()->latest()->first();

    $response = actingAs($vendor1->user)->get("/vendor/orders/{$order->id}");
    expect($response->status())->not->toBe(500);
    $response->assertSuccessful()
        ->assertInertia(fn ($p) => $p->where('order.items', fn ($items) =>
            count($items) === 1 && $items[0]['product_name'] === $p1[0]->name
        ));
});

/* ─────────── Customer order detail shows all items ─────────── */

it('v5.7: customer order detail renders all items from a multi-vendor order', function () {
    $customer = setupCustomerWithAddress();
    [, $p1] = approvedVendorWithProducts(1);
    [, $p2] = approvedVendorWithProducts(1);

    actingAs($customer)->post('/cart/items', ['product_id' => $p1[0]->id, 'quantity' => 1]);
    actingAs($customer)->post('/cart/items', ['product_id' => $p2[0]->id, 'quantity' => 1]);
    actingAs($customer)->post('/checkout', [
        'shipping_address_id' => $customer->addresses()->first()->id,
        'payment_method_slug' => 'cod',
    ]);
    $order = $customer->orders()->latest()->first();

    $response = actingAs($customer)->get("/orders/{$order->id}");
    expect($response->status())->not->toBe(500);
    $response->assertSuccessful()
        ->assertInertia(fn ($pp) => $pp->where('order.items', fn ($items) => count($items) === 2));
});
