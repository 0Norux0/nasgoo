<?php

declare(strict_types=1);

/**
 * Phase 4 v5.1 — end-to-end HTTP integration coverage.
 *
 * Each `it()` name carries an "item N:" prefix mapping it to the 14 audit
 * items the developer requested verification for:
 *
 *   1. Cart add/update/remove
 *   2. Checkout page loading
 *   3. COD order creation
 *   4. Manual bank transfer order creation
 *   5. Mock online payment order creation
 *   6. Customer order list and detail page
 *   7. Admin order actions (lifecycle service - admin row actions delegate here)
 *   8. Vendor order listing and shipping action
 *   9. Stock decrease after order placement     (already covered: CheckoutTest)
 *  10. Stock restoration after cancellation     (already covered: OrderLifecycleTest)
 *  11. Payment method seeding                   (covered: PaymentMethodsSeederTest)
 *  12. No 419 errors during cart/checkout/order (covered: Phase4CsrfTest)
 *
 * Filament admin row actions (item 7's confirm/ship/deliver/cancel/refund) are
 * thin wrappers around OrderLifecycleService + PaymentService — the underlying
 * methods are already covered by OrderLifecycleTest + PaymentTest. Filament
 * Livewire action testing requires a separate harness (deferred to Phase 5+).
 */

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\PaymentMethodsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\VendorPackagesSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\patch;
use function Pest\Laravel\post;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(VendorPackagesSeeder::class);
    $this->seed(PaymentMethodsSeeder::class);
});

/* ─────────── Item 1: cart add/update/remove (HTTP) ─────────── */

it('item 1: GET /cart redirects guest to login', function () {
    get('/cart')->assertRedirect('/login');
});

it('item 1: GET /cart renders empty state for logged-in user', function () {
    $user = User::factory()->create();
    actingAs($user)->get('/cart')
        ->assertSuccessful()
        ->assertInertia(fn ($p) => $p->component('Cart/Show')
            ->where('cart.items_count', 0));
});

it('item 1: POST /cart/items adds a line and updates the badge', function () {
    $user = User::factory()->create();
    $product = Product::factory()->published()->create(['price_minor' => 5000, 'stock' => 10]);

    actingAs($user)
        ->post('/cart/items', ['product_id' => $product->id, 'quantity' => 2])
        ->assertRedirect();

    expect($user->fresh()->cart()->first()->items_count)->toBe(2);
    expect($user->fresh()->cart()->first()->subtotal_minor)->toBe(10000);
});

it('item 1: PATCH /cart/items/{id} updates quantity', function () {
    $user = User::factory()->create();
    $product = Product::factory()->published()->create(['price_minor' => 5000, 'stock' => 10]);
    actingAs($user)->post('/cart/items', ['product_id' => $product->id, 'quantity' => 1]);
    $item = $user->fresh()->cart()->first()->items()->first();

    actingAs($user)->patch("/cart/items/{$item->id}", ['quantity' => 4])->assertRedirect();
    expect($item->fresh()->quantity)->toBe(4);
});

it('item 1: DELETE /cart/items/{id} removes the line', function () {
    $user = User::factory()->create();
    $product = Product::factory()->published()->create(['stock' => 10]);
    actingAs($user)->post('/cart/items', ['product_id' => $product->id, 'quantity' => 1]);
    $item = $user->fresh()->cart()->first()->items()->first();

    actingAs($user)->delete("/cart/items/{$item->id}")->assertRedirect();
    expect($user->fresh()->cart()->first()->items()->count())->toBe(0);
});

it('item 1: POST /cart/clear empties the cart', function () {
    $user = User::factory()->create();
    $product = Product::factory()->published()->create(['stock' => 10]);
    actingAs($user)->post('/cart/items', ['product_id' => $product->id, 'quantity' => 2]);

    actingAs($user)->post('/cart/clear')->assertRedirect('/cart');
    expect($user->fresh()->cart()->first()->items_count)->toBe(0);
});

it('item 1: rejects add of unpublished product with flash error', function () {
    $user = User::factory()->create();
    $draft = Product::factory()->draft()->create();

    actingAs($user)
        ->post('/cart/items', ['product_id' => $draft->id, 'quantity' => 1])
        ->assertRedirect()
        ->assertSessionHas('error');
    expect($user->fresh()->cart?->items_count ?? 0)->toBe(0);
});

/* ─────────── Item 2: checkout page loading ─────────── */

it('item 2: GET /checkout redirects guest to login', function () {
    get('/checkout')->assertRedirect('/login');
});

it('item 2: GET /checkout redirects to /cart when empty', function () {
    $user = User::factory()->create();
    actingAs($user)->get('/checkout')->assertRedirect('/cart');
});

it('item 2: GET /checkout renders with cart, addresses, and payment methods', function () {
    $user = User::factory()->create();
    $product = Product::factory()->published()->create(['stock' => 10]);
    actingAs($user)->post('/cart/items', ['product_id' => $product->id, 'quantity' => 1]);

    actingAs($user)
        ->get('/checkout')
        ->assertSuccessful()
        ->assertInertia(fn ($p) =>
            $p->component('Checkout/Show')
              ->has('cart.items')
              ->has('payment_methods')
              ->has('addresses')
        );
});

/* ─────────── Item 3: COD order creation end-to-end ─────────── */

it('item 3: COD checkout creates an order and redirects to /orders/{id}/confirm', function () {
    $user = User::factory()->create();
    $product = Product::factory()->published()->create(['price_minor' => 5000, 'stock' => 10]);
    actingAs($user)->post('/cart/items', ['product_id' => $product->id, 'quantity' => 2]);

    $response = actingAs($user)->post('/checkout', [
        'shipping_address' => [
            'recipient_name' => 'A', 'phone' => '+1', 'country' => 'KW', 'city' => 'C', 'street' => 'L',
        ],
        'payment_method_slug' => 'cod',
    ]);

    $order = Order::where('user_id', $user->id)->latest()->first();
    expect($order)->not->toBeNull();
    expect($order->total_minor)->toBe(10000);
    $response->assertRedirect("/orders/{$order->id}/confirm");

    // COD leaves order pending — money moves only when delivered
    expect($order->payment_status)->toBe(Order::PAY_PENDING);
    expect($order->payments()->first()->method_slug)->toBe('cod');
    expect($order->payments()->first()->reference)->toStartWith('COD-');
});

/* ─────────── Item 4: manual bank transfer order creation ─────────── */

it('item 4: manual_transfer checkout creates a pending order with bank reference', function () {
    $user = User::factory()->create();
    $product = Product::factory()->published()->create(['stock' => 10]);
    actingAs($user)->post('/cart/items', ['product_id' => $product->id, 'quantity' => 1]);

    actingAs($user)->post('/checkout', [
        'shipping_address' => ['recipient_name' => 'A', 'phone' => '+1', 'country' => 'KW', 'city' => 'C', 'street' => 'L'],
        'payment_method_slug' => 'manual_transfer',
    ])->assertRedirect();

    $order = Order::where('user_id', $user->id)->latest()->first();
    expect($order->payment_status)->toBe(Order::PAY_PENDING);
    expect($order->payments()->first()->reference)->toStartWith('BT-');
});

/* ─────────── Item 5: mock online payment order creation ─────────── */

it('item 5: online_mock checkout captures immediately and order is paid', function () {
    $user = User::factory()->create();
    $product = Product::factory()->published()->create(['stock' => 10]);
    actingAs($user)->post('/cart/items', ['product_id' => $product->id, 'quantity' => 1]);

    actingAs($user)->post('/checkout', [
        'shipping_address' => ['recipient_name' => 'A', 'phone' => '+1', 'country' => 'KW', 'city' => 'C', 'street' => 'L'],
        'payment_method_slug' => 'online_mock',
    ])->assertRedirect();

    $order = Order::where('user_id', $user->id)->latest()->first();
    expect($order->payment_status)->toBe(Order::PAY_PAID);
    expect($order->payments()->first()->external_id)->toStartWith('MOCK-');
});

/* ─────────── Item 6: customer order list + detail page ─────────── */

it('item 6: GET /orders renders the customer order list', function () {
    $user = User::factory()->create();
    Order::factory()->paid()->for($user)->count(3)->create();

    actingAs($user)->get('/orders')
        ->assertSuccessful()
        ->assertInertia(fn ($p) =>
            $p->component('Orders/Index')
              ->where('orders.total', 3)
        );
});

it('item 6: GET /orders/{id} renders the order detail for its owner', function () {
    $user = User::factory()->create();
    $order = Order::factory()->paid()->for($user)->create();

    actingAs($user)->get("/orders/{$order->id}")
        ->assertSuccessful()
        ->assertInertia(fn ($p) =>
            $p->component('Orders/Show')
              ->where('order.id', $order->id)
              ->has('order.events')
        );
});

it('item 6: GET /orders/{id} forbidden for a foreign customer', function () {
    $user = User::factory()->create();
    $foreign = User::factory()->create();
    $foreign->assignRole('customer');
    $order = Order::factory()->paid()->for($user)->create();

    actingAs($foreign)->get("/orders/{$order->id}")->assertForbidden();
});

it('item 6: GET /orders/{id}/confirm renders the celebratory page', function () {
    $user = User::factory()->create();
    $order = Order::factory()->paid()->for($user)->create();

    actingAs($user)->get("/orders/{$order->id}/confirm")
        ->assertSuccessful()
        ->assertInertia(fn ($p) => $p->component('Orders/Confirm'));
});

/* ─────────── Item 7: customer order cancellation (admin actions delegate to lifecycle service — already tested) ─────────── */

it('item 7+10: POST /orders/{id}/cancel succeeds and restocks the product', function () {
    $user = User::factory()->create();
    $product = Product::factory()->published()->create(['stock' => 5, 'track_stock' => true]);
    $order = Order::factory()->paid()->for($user)->create();
    OrderItem::factory()->for($order)->state(['product_id' => $product->id, 'quantity' => 2])->create();

    actingAs($user)->post("/orders/{$order->id}/cancel", ['reason' => 'changed mind'])
        ->assertRedirect();

    expect($order->fresh()->status)->toBe(Order::STATUS_CANCELLED);
    expect($product->fresh()->stock)->toBe(7); // restocked (item 10)
});

it('item 7: cancel rejected when order is delivered', function () {
    $user = User::factory()->create();
    $order = Order::factory()->delivered()->for($user)->create();
    OrderItem::factory()->for($order)->create();

    actingAs($user)->post("/orders/{$order->id}/cancel", ['reason' => 'too late'])
        ->assertForbidden();
});

it('item 7: cancel requires a reason', function () {
    $user = User::factory()->create();
    $order = Order::factory()->paid()->for($user)->create();
    OrderItem::factory()->for($order)->create();

    actingAs($user)->post("/orders/{$order->id}/cancel", [])
        ->assertSessionHasErrors(['reason']);
});

/* ─────────── Item 8: vendor order listing + shipping action ─────────── */

it('item 8: GET /vendor/orders returns vendor-scoped orders', function () {
    $user = User::factory()->create();
    $user->assignRole('vendor');
    $vendor = Vendor::factory()->approved()->for($user)->create();

    $myOrder = Order::factory()->paid()->create();
    OrderItem::factory()->for($myOrder)->state(['vendor_id' => $vendor->id])->create();

    // a foreign order shouldn't appear
    $other = User::factory()->create();
    $other->assignRole('vendor');
    $otherVendor = Vendor::factory()->approved()->for($other)->create();
    $foreignOrder = Order::factory()->paid()->create();
    OrderItem::factory()->for($foreignOrder)->state(['vendor_id' => $otherVendor->id])->create();

    actingAs($user)->get('/vendor/orders')
        ->assertSuccessful()
        ->assertInertia(fn ($p) => $p->where('orders.total', 1));
});

it('item 8: GET /vendor/orders/{id} returns only vendor items + commission breakdown', function () {
    $user = User::factory()->create();
    $user->assignRole('vendor');
    $vendor = Vendor::factory()->approved()->for($user)->create();

    $order = Order::factory()->paid()->create();
    OrderItem::factory()->for($order)->state([
        'vendor_id' => $vendor->id, 'line_total_minor' => 10000,
        'commission_amount_minor' => 2000, 'vendor_earning_minor' => 8000,
    ])->create();

    actingAs($user)->get("/vendor/orders/{$order->id}")
        ->assertSuccessful()
        ->assertInertia(fn ($p) =>
            $p->component('Vendor/Orders/Show')
              ->where('order.vendor_subtotal', '100.00')
              ->where('order.vendor_earnings', '80.00')
        );
});

it('item 8: POST /vendor/orders/{id}/ship marks the vendor items shipped', function () {
    $user = User::factory()->create();
    $user->assignRole('vendor');
    $vendor = Vendor::factory()->approved()->for($user)->create();

    $order = Order::factory()->paid()->create();
    OrderItem::factory()->for($order)->state(['vendor_id' => $vendor->id])->create();

    actingAs($user)->post("/vendor/orders/{$order->id}/ship")->assertRedirect();

    expect($order->fresh()->fulfillment_status)->toBe(Order::FUL_FULFILLED);
    expect($order->fresh()->status)->toBe(Order::STATUS_SHIPPED);
    expect($order->fresh()->shipped_at)->not->toBeNull();
});

it('item 8: vendor cannot ship a foreign vendor\'s order', function () {
    $user = User::factory()->create();
    $user->assignRole('vendor');
    Vendor::factory()->approved()->for($user)->create();

    $foreignUser = User::factory()->create();
    $foreignUser->assignRole('vendor');
    $foreignVendor = Vendor::factory()->approved()->for($foreignUser)->create();

    $foreignOrder = Order::factory()->paid()->create();
    OrderItem::factory()->for($foreignOrder)->state(['vendor_id' => $foreignVendor->id])->create();

    actingAs($user)->post("/vendor/orders/{$foreignOrder->id}/ship")
        ->assertNotFound();
});

/* ─────────── Item 9: stock decrease after order placement (HTTP-level confirmation) ─────────── */

it('item 9: stock decrements after a checkout call', function () {
    $user = User::factory()->create();
    $product = Product::factory()->published()->create(['stock' => 10, 'track_stock' => true]);
    actingAs($user)->post('/cart/items', ['product_id' => $product->id, 'quantity' => 3]);

    actingAs($user)->post('/checkout', [
        'shipping_address' => ['recipient_name' => 'A', 'phone' => '+1', 'country' => 'KW', 'city' => 'C', 'street' => 'L'],
        'payment_method_slug' => 'cod',
    ]);

    expect($product->fresh()->stock)->toBe(7);
});
