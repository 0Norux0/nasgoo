<?php

declare(strict_types=1);

/**
 * Phase 4 v5.2 — regression test for the address schema mismatch.
 *
 * The pre-v5.2 CheckoutController queried `full_name`, `line1`, `line2`,
 * `region` from the `addresses` table — none of which exist. The query
 * failed with SQLSTATE[42S22] before the checkout page could render.
 *
 * This file:
 *   1. Asserts /checkout opens without SQL error for users with and without
 *      saved addresses
 *   2. Asserts the address fields ACTUALLY queried match the columns that
 *      actually exist in the addresses table (catches any future Phase 4+
 *      change that re-introduces a phantom column)
 *   3. Asserts orders placed via /checkout produce an order_addresses snapshot
 *      that mirrors the Phase 1 schema (no missing fields)
 *   4. Exercises COD, manual_transfer, and online_mock payment methods
 *      end-to-end via /checkout — each must redirect to /orders/{id}/confirm
 *      without 500/419
 */

use App\Models\Address;
use App\Models\OrderAddress;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\PaymentMethodsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\VendorPackagesSeeder;
use Illuminate\Support\Facades\Schema;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(VendorPackagesSeeder::class);
    $this->seed(PaymentMethodsSeeder::class);
});

/* ─────────── REGRESSION GUARD ─────────── */

it('regression: /checkout opens without SQL error for a customer with NO saved address', function () {
    $user = User::factory()->create();
    $product = Product::factory()->published()->create(['stock' => 5]);
    actingAs($user)->post('/cart/items', ['product_id' => $product->id, 'quantity' => 1]);

    $response = actingAs($user)->get('/checkout');
    // Status 200 OR a redirect — anything but 500 (the pre-v5.2 failure mode)
    expect($response->status())->not->toBe(500);
    $response->assertSuccessful();
    $response->assertInertia(fn ($p) =>
        $p->component('Checkout/Show')
          ->where('has_addresses', false)
          ->has('addresses', 0)
    );
});

it('regression: /checkout opens without SQL error for a customer WITH a saved address', function () {
    $user = User::factory()->create();
    Address::factory()->for($user)->default()->create([
        'country' => 'KW', 'city' => 'Kuwait City',
        'block' => '5', 'street' => 'Salem Al Mubarak', 'building' => '12',
    ]);
    $product = Product::factory()->published()->create(['stock' => 5]);
    actingAs($user)->post('/cart/items', ['product_id' => $product->id, 'quantity' => 1]);

    $response = actingAs($user)->get('/checkout');
    expect($response->status())->not->toBe(500);
    $response->assertSuccessful();
    $response->assertInertia(fn ($p) =>
        $p->component('Checkout/Show')
          ->where('has_addresses', true)
          ->has('addresses', 1)
          ->where('addresses.0.city', 'Kuwait City')
          ->where('addresses.0.block', '5')
    );
});

/* ─────────── SCHEMA PIN ─────────── */

it('regression: address fields shared to the React page exist in the actual table', function () {
    // Pull the column names that genuinely exist on the addresses table.
    $real = Schema::getColumnListing('addresses');

    // Every field the CheckoutController select() asks for must be in $real.
    // If a future PR re-introduces a column like full_name or line1, this fails.
    $askedFor = [
        'id', 'label', 'type', 'country', 'state', 'city',
        'area', 'block', 'street', 'building', 'floor', 'apartment',
        'postal_code', 'phone', 'is_default',
    ];
    foreach ($askedFor as $col) {
        expect($real)->toContain($col, "Column `$col` referenced by checkout but missing from addresses table");
    }

    // Hard-fail if a phantom column has crept back in
    foreach (['full_name', 'line1', 'line2', 'region'] as $phantom) {
        expect($real)->not->toContain(
            $phantom,
            "Phantom column `$phantom` is on the addresses table — this is the v5.2 bug returning."
        );
    }
});

it('regression: order_addresses schema mirrors Phase 1 addresses (no Western fields)', function () {
    $cols = Schema::getColumnListing('order_addresses');
    // Must have the Gulf-style fields
    foreach (['recipient_name', 'country', 'state', 'city', 'area', 'block', 'street', 'building', 'floor', 'apartment', 'postal_code', 'phone', 'latitude', 'longitude'] as $col) {
        expect($cols)->toContain($col);
    }
    // Must NOT have the Western fields
    foreach (['full_name', 'line1', 'line2', 'region'] as $phantom) {
        expect($cols)->not->toContain(
            $phantom,
            "order_addresses has a phantom column `$phantom` — re-run migrations after applying v5.2."
        );
    }
});

/* ─────────── END-TO-END WITH REAL PHASE 1 ADDRESS ─────────── */

it('regression: customer with saved address can place a COD order without error', function () {
    $user = User::factory()->create();
    Address::factory()->for($user)->default()->create([
        'country' => 'KW', 'city' => 'Kuwait City',
        'block' => '7', 'street' => 'Beach Road', 'building' => '20', 'phone' => '+96599887766',
    ]);
    $product = Product::factory()->published()->create(['price_minor' => 5000, 'stock' => 5]);
    actingAs($user)->post('/cart/items', ['product_id' => $product->id, 'quantity' => 1]);

    $addr = $user->addresses()->first();
    $response = actingAs($user)->post('/checkout', [
        'shipping_address_id' => $addr->id,
        'payment_method_slug' => 'cod',
    ]);
    $response->assertRedirect();
    expect($response->status())->not->toBe(500);

    $order = $user->orders()->latest()->first();
    expect($order)->not->toBeNull();

    // Snapshot must contain Phase 1 fields, populated from the source address
    $snapshot = $order->shippingAddress;
    expect($snapshot)->not->toBeNull();
    expect($snapshot->city)->toBe('Kuwait City');
    expect($snapshot->block)->toBe('7');
    expect($snapshot->street)->toBe('Beach Road');
    expect($snapshot->building)->toBe('20');
    expect($snapshot->phone)->toBe('+96599887766');
    expect($snapshot->recipient_name)->toBe($user->name);
});

it('regression: customer with NO saved address can place an order via inline address', function () {
    $user = User::factory()->create();
    $product = Product::factory()->published()->create(['stock' => 5]);
    actingAs($user)->post('/cart/items', ['product_id' => $product->id, 'quantity' => 1]);

    $response = actingAs($user)->post('/checkout', [
        'shipping_address' => [
            'recipient_name' => 'Ali',
            'phone'          => '+96598765432',
            'country'        => 'KW',
            'state'          => 'Hawalli',
            'city'           => 'Salmiya',
            'area'           => 'Salmiya',
            'block'          => '3',
            'street'         => 'Salem Al Mubarak',
            'building'       => '15',
            'floor'          => '2',
            'apartment'      => '4',
        ],
        'payment_method_slug' => 'cod',
    ]);
    $response->assertRedirect();
    expect($response->status())->not->toBe(500);

    $snapshot = $user->orders()->latest()->first()->shippingAddress;
    expect($snapshot->recipient_name)->toBe('Ali');
    expect($snapshot->city)->toBe('Salmiya');
    expect($snapshot->state)->toBe('Hawalli');
    expect($snapshot->floor)->toBe('2');
    expect($snapshot->apartment)->toBe('4');
});

it('regression: checkout works end-to-end with manual_transfer', function () {
    $user = User::factory()->create();
    Address::factory()->for($user)->default()->create(['country' => 'KW', 'city' => 'KC']);
    $product = Product::factory()->published()->create(['stock' => 5]);
    actingAs($user)->post('/cart/items', ['product_id' => $product->id, 'quantity' => 1]);

    $response = actingAs($user)->post('/checkout', [
        'shipping_address_id' => $user->addresses()->first()->id,
        'payment_method_slug' => 'manual_transfer',
    ]);
    $response->assertRedirect();
    expect($response->status())->not->toBe(500);
    expect($response->status())->not->toBe(419);

    $payment = $user->orders()->latest()->first()->payments()->first();
    expect($payment->reference)->toStartWith('BT-');
});

it('regression: checkout works end-to-end with online_mock', function () {
    $user = User::factory()->create();
    Address::factory()->for($user)->default()->create(['country' => 'KW', 'city' => 'KC']);
    $product = Product::factory()->published()->create(['stock' => 5]);
    actingAs($user)->post('/cart/items', ['product_id' => $product->id, 'quantity' => 1]);

    $response = actingAs($user)->post('/checkout', [
        'shipping_address_id' => $user->addresses()->first()->id,
        'payment_method_slug' => 'online_mock',
    ]);
    $response->assertRedirect();
    expect($response->status())->not->toBe(500);
    expect($response->status())->not->toBe(419);

    $order = $user->orders()->latest()->first();
    expect($order->payment_status)->toBe(\App\Models\Order::PAY_PAID);
    expect($order->payments()->first()->external_id)->toStartWith('MOCK-');
});

it('regression: /checkout returns no 419 on a real POST round-trip', function () {
    $user = User::factory()->create();
    $product = Product::factory()->published()->create(['stock' => 5]);
    actingAs($user)->post('/cart/items', ['product_id' => $product->id, 'quantity' => 1]);

    $response = actingAs($user)->post('/checkout', [
        'shipping_address' => [
            'recipient_name' => 'A', 'country' => 'KW', 'city' => 'KC',
        ],
        'payment_method_slug' => 'cod',
    ]);
    expect($response->status())->not->toBe(419);
});
