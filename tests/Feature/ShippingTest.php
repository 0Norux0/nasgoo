<?php

declare(strict_types=1);

/**
 * Phase 5 — shipping zones, methods, resolver, and checkout integration.
 */

use App\Domain\Shipping\ShippingResolver;
use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
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

/* ─────────── Zone CRUD ─────────── */

it('v6.0: shipping zones can be created with countries + optional regions', function () {
    $zone = ShippingZone::create([
        'name'      => 'Kuwait Domestic',
        'countries' => ['KW'],
        'is_active' => true,
    ]);

    expect($zone->slug)->toBe('kuwait-domestic');     // auto-generated
    expect($zone->countries)->toBe(['KW']);
    expect($zone->is_active)->toBeTrue();
});

it('v6.0: zone.covers() matches countries case-insensitively', function () {
    $zone = ShippingZone::create([
        'name' => 'KW', 'countries' => ['KW'], 'is_active' => true,
    ]);
    expect($zone->covers('KW'))->toBeTrue();
    expect($zone->covers('kw'))->toBeTrue();
    expect($zone->covers('SA'))->toBeFalse();
});

it('v6.0: zone with regions requires region match', function () {
    $zone = ShippingZone::create([
        'name'      => 'Kuwait City Only',
        'countries' => ['KW'],
        'regions'   => ['Kuwait City'],
        'is_active' => true,
    ]);
    expect($zone->covers('KW', 'Kuwait City'))->toBeTrue();
    expect($zone->covers('KW', 'kuwait city'))->toBeTrue();   // case-insensitive
    expect($zone->covers('KW', 'Salmiya'))->toBeFalse();
    expect($zone->covers('KW'))->toBeFalse();                  // null region rejected
});

/* ─────────── Method types ─────────── */

it('v6.0: shipping methods can be created — flat rate, free, pickup', function () {
    $zone = ShippingZone::create(['name' => 'KW', 'countries' => ['KW']]);

    $flat = ShippingMethod::create([
        'shipping_zone_id' => $zone->id, 'name' => 'Standard',
        'type' => ShippingMethod::TYPE_FLAT_RATE, 'fee_minor' => 1500,
    ]);
    $free = ShippingMethod::create([
        'shipping_zone_id' => $zone->id, 'name' => 'Free over 30',
        'type' => ShippingMethod::TYPE_FREE, 'min_subtotal_minor' => 30000,
    ]);
    $pickup = ShippingMethod::create([
        'shipping_zone_id' => $zone->id, 'name' => 'Pickup',
        'type' => ShippingMethod::TYPE_PICKUP,
    ]);

    expect($flat->feeFor(10000))->toBe(1500);
    expect($free->feeFor(50000))->toBe(0);
    expect($pickup->feeFor(99999))->toBe(0);
});

it('v6.0: free shipping is NOT eligible below min_subtotal_minor', function () {
    $zone = ShippingZone::create(['name' => 'KW', 'countries' => ['KW']]);
    $free = ShippingMethod::create([
        'shipping_zone_id' => $zone->id, 'name' => 'Free over 30',
        'type' => ShippingMethod::TYPE_FREE, 'min_subtotal_minor' => 30000, 'is_active' => true,
    ]);

    expect($free->isEligibleFor(29999))->toBeFalse();
    expect($free->isEligibleFor(30000))->toBeTrue();
    expect($free->isEligibleFor(99999))->toBeTrue();
});

it('v6.0: inactive methods are never eligible', function () {
    $m = ShippingMethod::create([
        'name' => 'Disabled', 'type' => ShippingMethod::TYPE_FLAT_RATE,
        'fee_minor' => 1000, 'is_active' => false,
    ]);
    expect($m->isEligibleFor(10000))->toBeFalse();
});

/* ─────────── Resolver ─────────── */

it('v6.0: resolver finds the right zone by country', function () {
    $kw = ShippingZone::create(['name' => 'KW', 'countries' => ['KW'], 'is_active' => true]);
    ShippingZone::create(['name' => 'GCC', 'countries' => ['AE', 'SA'], 'is_active' => true]);

    expect(app(ShippingResolver::class)->resolveZone('KW')?->id)->toBe($kw->id);
    expect(app(ShippingResolver::class)->resolveZone('AE')?->name)->toBe('GCC');
    expect(app(ShippingResolver::class)->resolveZone('US'))->toBeNull();
});

it('v6.0: resolver prefers region-specific zone over country-wide', function () {
    $countryWide = ShippingZone::create([
        'name' => 'KW Country', 'countries' => ['KW'], 'is_active' => true, 'position' => 5,
    ]);
    $regionSpecific = ShippingZone::create([
        'name' => 'KW Kuwait City', 'countries' => ['KW'], 'regions' => ['Kuwait City'],
        'is_active' => true, 'position' => 10,
    ]);

    expect(app(ShippingResolver::class)->resolveZone('KW', 'Kuwait City')?->id)->toBe($regionSpecific->id);
    expect(app(ShippingResolver::class)->resolveZone('KW', 'Salmiya')?->id)->toBe($countryWide->id);
});

it('v6.0: resolver returns only eligible methods for the given subtotal', function () {
    $zone = ShippingZone::create(['name' => 'KW', 'countries' => ['KW'], 'is_active' => true]);
    ShippingMethod::create([
        'shipping_zone_id' => $zone->id, 'name' => 'Flat', 'type' => ShippingMethod::TYPE_FLAT_RATE,
        'fee_minor' => 1500, 'is_active' => true, 'position' => 1,
    ]);
    ShippingMethod::create([
        'shipping_zone_id' => $zone->id, 'name' => 'Free over 30',
        'type' => ShippingMethod::TYPE_FREE, 'min_subtotal_minor' => 30000,
        'is_active' => true, 'position' => 2,
    ]);

    // Subtotal under threshold: only flat rate available
    $methods = app(ShippingResolver::class)->availableFor('KW', null, 10000);
    expect($methods->count())->toBe(1);
    expect($methods->first()->name)->toBe('Flat');

    // Subtotal over threshold: both available
    $methods = app(ShippingResolver::class)->availableFor('KW', null, 50000);
    expect($methods->count())->toBe(2);
});

/* ─────────── Checkout integration ─────────── */

it('v6.0: checkout page exposes shipping methods for the user\'s default-address country', function () {
    $customer = User::factory()->create();
    $customer->assignRole('customer');
    Address::factory()->for($customer)->default()->create(['country' => 'KW']);

    $zone = ShippingZone::create(['name' => 'KW', 'countries' => ['KW'], 'is_active' => true]);
    ShippingMethod::create([
        'shipping_zone_id' => $zone->id, 'name' => 'Standard',
        'type' => ShippingMethod::TYPE_FLAT_RATE, 'fee_minor' => 1500, 'is_active' => true,
    ]);

    // Add a product to cart so checkout doesn't redirect
    $vendorUser = User::factory()->create(); $vendorUser->assignRole('vendor');
    $vendor = Vendor::factory()->approved()->for($vendorUser)->create();
    $product = Product::factory()->published()->create(['vendor_id' => $vendor->id]);
    actingAs($customer)->post('/cart/items', ['product_id' => $product->id, 'quantity' => 1]);

    actingAs($customer)->get('/checkout')
        ->assertSuccessful()
        ->assertInertia(fn ($p) => $p
            ->has('shipping_methods', 1)
            ->where('shipping_methods.0.name', 'Standard')
            ->where('shipping_methods.0.fee_minor', 1500)
        );
});

it('v6.0: placing an order with a shipping_method_id snapshots fee + name on the order', function () {
    $customer = User::factory()->create();
    $customer->assignRole('customer');
    $address = Address::factory()->for($customer)->default()->create(['country' => 'KW']);

    $zone = ShippingZone::create(['name' => 'KW', 'countries' => ['KW'], 'is_active' => true]);
    $method = ShippingMethod::create([
        'shipping_zone_id' => $zone->id, 'name' => 'Standard',
        'type' => ShippingMethod::TYPE_FLAT_RATE, 'fee_minor' => 1500,
        'currency' => 'KWD', 'is_active' => true,
    ]);

    $vendorUser = User::factory()->create(); $vendorUser->assignRole('vendor');
    $vendor = Vendor::factory()->approved()->for($vendorUser)->create();
    $product = Product::factory()->published()->create(['vendor_id' => $vendor->id, 'price_minor' => 5000]);
    actingAs($customer)->post('/cart/items', ['product_id' => $product->id, 'quantity' => 1]);

    actingAs($customer)->post('/checkout', [
        'shipping_address_id' => $address->id,
        'payment_method_slug' => 'cod',
        'shipping_method_id'  => $method->id,
    ])->assertRedirect();

    $order = $customer->orders()->latest()->first();
    expect($order->shipping_method_id)->toBe($method->id);
    expect($order->shipping_method_name)->toBe('Standard');
    expect((int) $order->shipping_minor)->toBe(1500);
    expect((int) $order->total_minor)->toBe(5000 + 1500);
});
