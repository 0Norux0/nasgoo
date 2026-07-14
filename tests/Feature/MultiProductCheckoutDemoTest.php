<?php

declare(strict_types=1);

/**
 * Phase 4 v5.8 — HARD regression test that reproduces the developer's exact
 * reported flow:
 *   1. Run the real demo seeders (including DemoSeeder which sets up
 *      vendor@marketplace.test + vendor2@marketplace.test).
 *   2. Sign in as customer@marketplace.test (real demo account, not factory).
 *   3. Add multiple products from the actual demo catalog.
 *   4. POST /checkout with COD.
 *   5. Assert NO lazy-load exception is thrown.
 *
 * This test exists because v5.7 added the eager-load chain to
 * CheckoutService::place() and the dev still reported the bug — meaning either
 * the v5.7 chain wasn't reached on the real demo path, or the upstream loads
 * were stale. v5.8 adds per-iteration defensive loadMissing in the snapshot
 * loop and inside CommissionResolver::forProduct so the bug can't return
 * regardless of how the cart got hydrated.
 *
 * Every test runs with Model::shouldBeStrict(true).
 */

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Database\Seeders\PaymentMethodsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\VendorPackagesSeeder;
use Illuminate\Database\Eloquent\Model;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(VendorPackagesSeeder::class);
    $this->seed(PaymentMethodsSeeder::class);

    // Re-create demo accounts the way DatabaseSeeder does in dev. We skip the
    // env guard so DemoSeeder actually runs under testing env.
    $admin = User::firstOrCreate(['email' => 'admin@marketplace.test'], [
        'name' => 'Admin', 'password' => bcrypt('password'), 'email_verified_at' => now(), 'status' => 'active',
    ]);
    if (! $admin->hasRole('super_admin')) {
        $admin->assignRole('super_admin');
    }
    $vendor = User::firstOrCreate(['email' => 'vendor@marketplace.test'], [
        'name' => 'Demo Vendor', 'password' => bcrypt('password'), 'email_verified_at' => now(), 'status' => 'active',
    ]);
    if (! $vendor->hasRole('vendor')) {
        $vendor->assignRole('vendor');
    }
    $customer = User::firstOrCreate(['email' => 'customer@marketplace.test'], [
        'name' => 'Demo Customer', 'password' => bcrypt('password'), 'email_verified_at' => now(), 'status' => 'active',
    ]);
    if (! $customer->hasRole('customer')) {
        $customer->assignRole('customer');
    }

    // Categories the demo products need
    \App\Models\Category::firstOrCreate(
        ['slug' => 'electronics'],
        ['name' => 'Electronics', 'is_active' => true, 'position' => 1],
    );
    \App\Models\Category::firstOrCreate(
        ['slug' => 'fashion'],
        ['name' => 'Fashion', 'is_active' => true, 'position' => 2],
    );

    // Force DemoSeeder to run by setting APP_ENV temporarily (it self-guards
    // against testing env). We test the actual demo data path the dev hits.
    config(['app.env' => 'local']);
    $this->artisan('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);
    config(['app.env' => 'testing']);

    // Strict-mode catches any lazy-load anywhere in the request
    Model::shouldBeStrict(true);
});

afterEach(function () {
    Model::shouldBeStrict(false);
});

/* ─────────── The exact bug — 2 products from same vendor, demo data path ─────────── */

it('v5.8 [HARD]: customer@marketplace.test adds 2 demo products → checkout → no lazy-load crash', function () {
    $customer = User::where('email', 'customer@marketplace.test')->firstOrFail();
    $address = $customer->addresses()->where('is_default', true)->first();
    expect($address)->not->toBeNull('Demo customer must have a default address');

    // The actual demo products vendor@marketplace.test publishes
    $products = Product::where('status', 'published')
        ->whereHas('vendor', fn ($q) => $q->where('slug', 'demo-trading-co'))
        ->take(2)
        ->get();
    expect($products->count())->toBe(2, 'Demo vendor must have ≥2 published products for this test');

    foreach ($products as $p) {
        actingAs($customer)->post('/cart/items', ['product_id' => $p->id, 'quantity' => 1]);
    }

    // The crash from the dev's reproduction. Under strict mode this would
    // throw "Attempted to lazy load [vendor] on model [Product]" if v5.8's
    // defenses didn't cover the real path.
    $response = actingAs($customer)->post('/checkout', [
        'shipping_address_id' => $address->id,
        'payment_method_slug' => 'cod',
    ]);

    expect($response->status())->not->toBe(500, 'lazy-load crash returned — v5.8 defenses insufficient');
    $response->assertRedirect();

    $order = $customer->orders()->latest()->first();
    expect($order)->not->toBeNull();
    expect($order->items()->count())->toBe(2);
});

/* ─────────── Multi-vendor demo path ─────────── */

it('v5.8 [HARD]: customer adds 1 product from each demo vendor → checkout → 2-line multi-vendor order', function () {
    $customer = User::where('email', 'customer@marketplace.test')->firstOrFail();
    $address = $customer->addresses()->where('is_default', true)->firstOrFail();

    $p1 = Product::where('status', 'published')
        ->whereHas('vendor', fn ($q) => $q->where('slug', 'demo-trading-co'))
        ->first();
    $p2 = Product::where('status', 'published')
        ->whereHas('vendor', fn ($q) => $q->where('slug', 'coastal-goods'))
        ->first();

    expect($p1)->not->toBeNull('Demo Trading Co. must have a published product');
    expect($p2)->not->toBeNull('Coastal Goods (vendor2) must have a published product');

    actingAs($customer)->post('/cart/items', ['product_id' => $p1->id, 'quantity' => 1]);
    actingAs($customer)->post('/cart/items', ['product_id' => $p2->id, 'quantity' => 1]);

    $response = actingAs($customer)->post('/checkout', [
        'shipping_address_id' => $address->id,
        'payment_method_slug' => 'cod',
    ]);

    expect($response->status())->not->toBe(500);
    $response->assertRedirect();

    $order = $customer->orders()->latest()->first();
    expect($order->items()->count())->toBe(2);
    expect($order->items->pluck('vendor_id')->unique()->count())->toBe(2);
});

/* ─────────── Confirmation page renders for the multi-product order ─────────── */

it('v5.8 [HARD]: /orders/{id}/confirm renders after multi-product checkout', function () {
    $customer = User::where('email', 'customer@marketplace.test')->firstOrFail();
    $address = $customer->addresses()->where('is_default', true)->firstOrFail();
    $products = Product::where('status', 'published')->limit(2)->get();

    foreach ($products as $p) {
        actingAs($customer)->post('/cart/items', ['product_id' => $p->id, 'quantity' => 1]);
    }
    actingAs($customer)->post('/checkout', [
        'shipping_address_id' => $address->id,
        'payment_method_slug' => 'cod',
    ]);
    $order = $customer->orders()->latest()->firstOrFail();

    // The Inertia redirect target — confirms full round-trip works
    $confirm = actingAs($customer)->get("/orders/{$order->id}/confirm");
    expect($confirm->status())->not->toBe(500);
    $confirm->assertSuccessful();
});

/* ─────────── /cart and /checkout GET pages with multi-item cart ─────────── */

it('v5.8 [HARD]: /cart and /checkout GET render with multi-vendor multi-item cart', function () {
    $customer = User::where('email', 'customer@marketplace.test')->firstOrFail();

    // Add 1 product from each vendor
    foreach ([
        Product::whereHas('vendor', fn ($q) => $q->where('slug', 'demo-trading-co'))->first(),
        Product::whereHas('vendor', fn ($q) => $q->where('slug', 'coastal-goods'))->first(),
    ] as $p) {
        actingAs($customer)->post('/cart/items', ['product_id' => $p->id, 'quantity' => 1]);
    }

    actingAs($customer)->get('/cart')->assertSuccessful();
    actingAs($customer)->get('/checkout')->assertSuccessful();
});

/* ─────────── Vendor/admin views on the multi-vendor order ─────────── */

it('v5.8 [HARD]: each vendor sees only their own line on a multi-vendor order', function () {
    $customer = User::where('email', 'customer@marketplace.test')->firstOrFail();
    $address  = $customer->addresses()->where('is_default', true)->firstOrFail();

    $p1 = Product::whereHas('vendor', fn ($q) => $q->where('slug', 'demo-trading-co'))->first();
    $p2 = Product::whereHas('vendor', fn ($q) => $q->where('slug', 'coastal-goods'))->first();

    actingAs($customer)->post('/cart/items', ['product_id' => $p1->id, 'quantity' => 1]);
    actingAs($customer)->post('/cart/items', ['product_id' => $p2->id, 'quantity' => 1]);
    actingAs($customer)->post('/checkout', [
        'shipping_address_id' => $address->id,
        'payment_method_slug' => 'cod',
    ]);
    $order = $customer->orders()->latest()->firstOrFail();

    $v1 = User::where('email', 'vendor@marketplace.test')->firstOrFail();
    $v2 = User::where('email', 'vendor2@marketplace.test')->firstOrFail();

    actingAs($v1)->get("/vendor/orders/{$order->id}")
        ->assertSuccessful()
        ->assertInertia(fn ($p) => $p->where('order.items', fn ($items) => count($items) === 1));

    actingAs($v2)->get("/vendor/orders/{$order->id}")
        ->assertSuccessful()
        ->assertInertia(fn ($p) => $p->where('order.items', fn ($items) => count($items) === 1));
});

it('v5.8 [HARD]: admin sees all items on a multi-vendor order via Filament resource query', function () {
    $customer = User::where('email', 'customer@marketplace.test')->firstOrFail();
    $address  = $customer->addresses()->where('is_default', true)->firstOrFail();

    $p1 = Product::whereHas('vendor', fn ($q) => $q->where('slug', 'demo-trading-co'))->first();
    $p2 = Product::whereHas('vendor', fn ($q) => $q->where('slug', 'coastal-goods'))->first();

    actingAs($customer)->post('/cart/items', ['product_id' => $p1->id, 'quantity' => 1]);
    actingAs($customer)->post('/cart/items', ['product_id' => $p2->id, 'quantity' => 1]);
    actingAs($customer)->post('/checkout', [
        'shipping_address_id' => $address->id,
        'payment_method_slug' => 'cod',
    ]);
    $order = $customer->orders()->latest()->firstOrFail();

    // The Filament admin query is unscoped — admin sees all items
    $loaded = \App\Filament\Resources\OrderResource::getEloquentQuery()
        ->whereKey($order->id)
        ->first();
    expect($loaded->items->count())->toBe(2);
});
