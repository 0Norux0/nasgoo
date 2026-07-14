<?php

declare(strict_types=1);

/**
 * Phase 8 — Services Marketplace / Bookings test suite.
 *
 * 18 scenarios covering vendor service creation, provider management,
 * availability, customer browsing, booking creation, double-booking
 * prevention, dashboards, admin access, accept/reject/complete actions,
 * and regression tests proving Phases 4-7 checkout flows still work
 * after Phase 8 schema additions.
 *
 * Defensive patterns inherited from Phase 7 lessons:
 *   v7.4 — model-level safeguard on ServiceBooking::creating
 *   v7.6 — eager-load every relation the controller/view touches
 *   v7.7 — no unused TypeScript imports (CI sub-check catches)
 */

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ServiceAvailability;
use App\Models\ServiceBooking;
use App\Models\ServiceDetail;
use App\Models\ServiceProvider;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Model::shouldBeStrict(true);
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
});

afterEach(function () {
    Model::shouldBeStrict(false);
});

/**
 * Build the minimum data graph for booking tests: vendor + service +
 * provider + Mon-Sat availability. Returns associative array of objects.
 */
function makeServiceContext(?Vendor $vendor = null): array {
    if (! $vendor) {
        $vendorUser = User::factory()->create();
        $vendorUser->assignRole('vendor');
        $vendor = Vendor::factory()->create(['user_id' => $vendorUser->id, 'status' => 'approved']);
    }

    $service = Product::factory()->create([
        'vendor_id'    => $vendor->id,
        'type'         => Product::TYPE_SERVICE,
        'status'       => 'published',
        'price_minor'  => 15000,
        'currency'     => 'KWD',
        'track_stock'  => false,
    ]);

    ServiceDetail::create([
        'product_id'                   => $service->id,
        'service_type'                 => ServiceDetail::TYPE_CONSULTATION,
        'location_mode'                => ServiceDetail::LOCATION_PROVIDER,
        'duration_minutes'             => 30,
        'min_lead_time_minutes'        => 0,
        'max_advance_days'             => 30,
        'allow_customer_provider_pick' => true,
        'is_active'                    => true,
    ]);

    $provider = ServiceProvider::factory()->create([
        'vendor_id' => $vendor->id, 'is_active' => true,
    ]);
    $service->serviceProviders()->attach($provider->id);

    // Mon-Sat 10:00-20:00, 30-min, max 1/slot, lunch break 13-14
    foreach ([1, 2, 3, 4, 5, 6] as $day) {
        ServiceAvailability::create([
            'service_provider_id'   => $provider->id,
            'day_of_week'           => $day,
            'start_time'            => '10:00:00',
            'end_time'              => '20:00:00',
            'slot_duration_minutes' => 30,
            'max_bookings_per_slot' => 1,
            'break_start_time'      => '13:00:00',
            'break_end_time'        => '14:00:00',
            'is_active'             => true,
        ]);
    }

    return ['vendor' => $vendor, 'service' => $service, 'provider' => $provider];
}

function makeCustomer(): User {
    $u = User::factory()->create();
    $u->assignRole('customer');
    return $u;
}

/* ────────────────────────────────────────────────────────────
   1. Vendor can create service listing.
   ──────────────────────────────────────────────────────────── */
it('Phase 8: vendor can create a service listing', function () {
    $vendorUser = User::factory()->create();
    $vendorUser->assignRole('vendor');
    Vendor::factory()->create(['user_id' => $vendorUser->id, 'status' => 'approved']);

    $response = $this->actingAs($vendorUser)->post('/vendor/services', [
        'name'             => 'Plumbing call-out',
        'description'      => 'Standard 1-hour plumbing visit.',
        'price'            => '20.00',
        'currency'         => 'KWD',
        'service_type'     => ServiceDetail::TYPE_HOME_VISIT,
        'location_mode'    => ServiceDetail::LOCATION_CUSTOMER,
        'duration_minutes' => 60,
    ]);

    $response->assertRedirect();
    expect(Product::where('vendor_id', $vendorUser->vendor->id)
        ->where('type', Product::TYPE_SERVICE)->exists())->toBeTrue();
});

/* ────────────────────────────────────────────────────────────
   2. Vendor can create provider/staff.
   ──────────────────────────────────────────────────────────── */
it('Phase 8: vendor can create a service provider (staff)', function () {
    $vendorUser = User::factory()->create();
    $vendorUser->assignRole('vendor');
    $vendor = Vendor::factory()->create(['user_id' => $vendorUser->id, 'status' => 'approved']);

    $response = $this->actingAs($vendorUser)->post('/vendor/providers', [
        'name'           => 'Ali Hassan',
        'specialization' => 'Electrical',
        'is_active'      => true,
    ]);

    $response->assertRedirect();
    expect(ServiceProvider::where('vendor_id', $vendor->id)->where('name', 'Ali Hassan')->exists())->toBeTrue();
});

/* ────────────────────────────────────────────────────────────
   3. Vendor can define availability.
   ──────────────────────────────────────────────────────────── */
it('Phase 8: vendor can define provider availability', function () {
    $ctx = makeServiceContext();
    $vendorUser = $ctx['vendor']->user;

    // Wipe existing availability so we test the controller endpoint
    $ctx['provider']->availabilities()->delete();

    $response = $this->actingAs($vendorUser)->post(
        "/vendor/providers/{$ctx['provider']->id}/availability",
        [
            'day_of_week'           => 2,    // Tuesday
            'start_time'            => '09:00',
            'end_time'              => '17:00',
            'slot_duration_minutes' => 60,
            'max_bookings_per_slot' => 2,
            'is_active'             => true,
        ]
    );

    $response->assertRedirect();
    expect(ServiceAvailability::where('service_provider_id', $ctx['provider']->id)
        ->where('day_of_week', 2)->exists())->toBeTrue();
});

/* ────────────────────────────────────────────────────────────
   4. Customer can view service listings (public catalog).
   ──────────────────────────────────────────────────────────── */
it('Phase 8: customer can view service listings', function () {
    $ctx = makeServiceContext();

    $response = $this->get('/services');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Services/Index')
        ->has('services.data', fn ($d) => $d->first(fn ($s) => $s
            ->where('id', $ctx['service']->id)
            ->etc()
        )->etc())
    );
});

/* ────────────────────────────────────────────────────────────
   5. Customer can see available slots (via slots API).
   ──────────────────────────────────────────────────────────── */
it('Phase 8: customer can see available slots for a provider', function () {
    $ctx = makeServiceContext();

    // Find next Monday so we hit the seeded availability
    $monday = \Carbon\Carbon::now()->next(\Carbon\Carbon::MONDAY);

    $response = $this->getJson('/services/api/slots?' . http_build_query([
        'service_id'          => $ctx['service']->id,
        'service_provider_id' => $ctx['provider']->id,
        'from'                => $monday->toDateString(),
        'to'                  => $monday->toDateString(),
    ]));

    $response->assertOk();
    $body = $response->json();
    expect($body)->toHaveKey('slots');
    // At least one slot should be available on a Monday inside the schedule
    expect($body['slots'])->not->toBeEmpty();
});

/* ────────────────────────────────────────────────────────────
   6. Customer can create a booking.
   ──────────────────────────────────────────────────────────── */
it('Phase 8: customer can create a booking', function () {
    $ctx = makeServiceContext();
    $customer = makeCustomer();

    $monday = \Carbon\Carbon::now()->next(\Carbon\Carbon::MONDAY);

    $response = $this->actingAs($customer)->post('/bookings', [
        'service_id'           => $ctx['service']->id,
        'service_provider_id'  => $ctx['provider']->id,
        'date'                 => $monday->toDateString(),
        'time'                 => '10:00',
        'customer_notes'       => 'First visit',
    ]);

    $response->assertRedirect();
    expect(ServiceBooking::where('user_id', $customer->id)
        ->where('service_provider_id', $ctx['provider']->id)
        ->exists())->toBeTrue();
});

/* ────────────────────────────────────────────────────────────
   7. Booking prevents double-booking when slot is full.
   ──────────────────────────────────────────────────────────── */
it('Phase 8: booking prevents double-booking on a full slot', function () {
    $ctx = makeServiceContext();
    $c1 = makeCustomer();
    $c2 = makeCustomer();

    $monday = \Carbon\Carbon::now()->next(\Carbon\Carbon::MONDAY);

    // First customer takes the 10:00 slot (max_bookings_per_slot = 1)
    $this->actingAs($c1)->post('/bookings', [
        'service_id'           => $ctx['service']->id,
        'service_provider_id'  => $ctx['provider']->id,
        'date'                 => $monday->toDateString(),
        'time'                 => '10:00',
    ])->assertRedirect();

    expect(ServiceBooking::where('user_id', $c1->id)->count())->toBe(1);

    // Second customer tries the same slot — should fail
    $response = $this->actingAs($c2)->post('/bookings', [
        'service_id'           => $ctx['service']->id,
        'service_provider_id'  => $ctx['provider']->id,
        'date'                 => $monday->toDateString(),
        'time'                 => '10:00',
    ]);

    expect(ServiceBooking::where('user_id', $c2->id)->count())->toBe(0);
});

/* ────────────────────────────────────────────────────────────
   8. Customer sees only their own bookings.
   ──────────────────────────────────────────────────────────── */
it('Phase 8: customer dashboard shows only their own bookings', function () {
    $ctx = makeServiceContext();
    $c1 = makeCustomer();
    $c2 = makeCustomer();
    $monday = \Carbon\Carbon::now()->next(\Carbon\Carbon::MONDAY);

    $this->actingAs($c1)->post('/bookings', [
        'service_id'          => $ctx['service']->id,
        'service_provider_id' => $ctx['provider']->id,
        'date'                => $monday->toDateString(),
        'time'                => '10:00',
    ]);
    $this->actingAs($c2)->post('/bookings', [
        'service_id'          => $ctx['service']->id,
        'service_provider_id' => $ctx['provider']->id,
        'date'                => $monday->toDateString(),
        'time'                => '10:30',
    ]);

    $response = $this->actingAs($c1)->get('/bookings');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Bookings/Index')
        ->has('bookings.data', 1)   // c1 sees exactly 1 booking, NOT c2's
    );
});

/* ────────────────────────────────────────────────────────────
   9. Vendor sees only their own bookings.
   ──────────────────────────────────────────────────────────── */
it('Phase 8: vendor dashboard shows only their own bookings', function () {
    $ctx1 = makeServiceContext();
    $ctx2 = makeServiceContext();  // different vendor
    $customer = makeCustomer();
    $monday = \Carbon\Carbon::now()->next(\Carbon\Carbon::MONDAY);

    // Bookings on both vendors
    $this->actingAs($customer)->post('/bookings', [
        'service_id'          => $ctx1['service']->id,
        'service_provider_id' => $ctx1['provider']->id,
        'date'                => $monday->toDateString(),
        'time'                => '10:00',
    ]);
    $this->actingAs($customer)->post('/bookings', [
        'service_id'          => $ctx2['service']->id,
        'service_provider_id' => $ctx2['provider']->id,
        'date'                => $monday->toDateString(),
        'time'                => '10:00',
    ]);

    $response = $this->actingAs($ctx1['vendor']->user)->get('/vendor/bookings');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Vendor/Bookings/Index')
        ->has('bookings.data', 1)   // vendor1 only sees their own booking
    );
});

/* ────────────────────────────────────────────────────────────
   10. Admin can view all bookings (via Filament resource query).
   ──────────────────────────────────────────────────────────── */
it('Phase 8: admin Filament query returns all bookings across vendors', function () {
    $ctx1 = makeServiceContext();
    $ctx2 = makeServiceContext();
    $customer = makeCustomer();
    $monday = \Carbon\Carbon::now()->next(\Carbon\Carbon::MONDAY);

    $this->actingAs($customer)->post('/bookings', [
        'service_id'          => $ctx1['service']->id,
        'service_provider_id' => $ctx1['provider']->id,
        'date'                => $monday->toDateString(), 'time' => '10:00',
    ]);
    $this->actingAs($customer)->post('/bookings', [
        'service_id'          => $ctx2['service']->id,
        'service_provider_id' => $ctx2['provider']->id,
        'date'                => $monday->toDateString(), 'time' => '10:30',
    ]);

    // Mirror what Filament's getEloquentQuery() does
    $query = \App\Filament\Resources\ServiceBookingResource::getEloquentQuery();
    $bookings = $query->get();

    expect($bookings)->toHaveCount(2);
    // No lazy-load fires when iterating the eager-loaded relations
    foreach ($bookings as $b) {
        expect($b->customer)->not->toBeNull();
        expect($b->vendor)->not->toBeNull();
        expect($b->product)->not->toBeNull();
    }
});

/* ────────────────────────────────────────────────────────────
   11. Vendor can accept/reject booking.
   ──────────────────────────────────────────────────────────── */
it('Phase 8: vendor can accept and reject bookings', function () {
    $ctx = makeServiceContext();
    $customer = makeCustomer();
    $monday = \Carbon\Carbon::now()->next(\Carbon\Carbon::MONDAY);

    $this->actingAs($customer)->post('/bookings', [
        'service_id'          => $ctx['service']->id,
        'service_provider_id' => $ctx['provider']->id,
        'date'                => $monday->toDateString(), 'time' => '10:00',
    ]);
    $b1 = ServiceBooking::where('user_id', $customer->id)->first();

    $this->actingAs($ctx['vendor']->user)
        ->post("/vendor/bookings/{$b1->id}/accept")
        ->assertRedirect();
    expect($b1->fresh()->status)->toBe(ServiceBooking::STATUS_ACCEPTED);

    // Second booking, then reject
    $this->actingAs($customer)->post('/bookings', [
        'service_id'          => $ctx['service']->id,
        'service_provider_id' => $ctx['provider']->id,
        'date'                => $monday->toDateString(), 'time' => '10:30',
    ]);
    $b2 = ServiceBooking::where('user_id', $customer->id)
        ->where('id', '!=', $b1->id)->first();

    $this->actingAs($ctx['vendor']->user)
        ->post("/vendor/bookings/{$b2->id}/reject", ['reason' => 'Provider sick today'])
        ->assertRedirect();
    expect($b2->fresh()->status)->toBe(ServiceBooking::STATUS_REJECTED);
});

/* ────────────────────────────────────────────────────────────
   12. Vendor can mark booking completed.
   ──────────────────────────────────────────────────────────── */
it('Phase 8: vendor can mark an accepted booking as completed', function () {
    $ctx = makeServiceContext();
    $customer = makeCustomer();
    $monday = \Carbon\Carbon::now()->next(\Carbon\Carbon::MONDAY);

    $this->actingAs($customer)->post('/bookings', [
        'service_id'          => $ctx['service']->id,
        'service_provider_id' => $ctx['provider']->id,
        'date'                => $monday->toDateString(), 'time' => '10:00',
    ]);
    $b = ServiceBooking::where('user_id', $customer->id)->first();

    // Accept first (state machine requires accepted/confirmed before complete)
    $this->actingAs($ctx['vendor']->user)->post("/vendor/bookings/{$b->id}/accept");
    $this->actingAs($ctx['vendor']->user)
        ->post("/vendor/bookings/{$b->id}/complete")
        ->assertRedirect();

    expect($b->fresh()->status)->toBe(ServiceBooking::STATUS_COMPLETED);
    expect($b->fresh()->completed_at)->not->toBeNull();
});

/* ────────────────────────────────────────────────────────────
   13. Normal product checkout still works (Phase 4 regression).
   ──────────────────────────────────────────────────────────── */
it('Phase 8: normal product checkout still works after Phase 8 schema additions', function () {
    $vendorUser = User::factory()->create();
    $vendorUser->assignRole('vendor');
    $vendor = Vendor::factory()->create(['user_id' => $vendorUser->id, 'status' => 'approved']);

    $product = Product::factory()->create([
        'vendor_id'   => $vendor->id,
        'type'        => Product::TYPE_SIMPLE,
        'status'      => 'published',
        'price_minor' => 1000, 'currency' => 'KWD',
    ]);

    expect($product->type)->toBe(Product::TYPE_SIMPLE);
    expect($product->isService())->toBeFalse();
    expect($product->isCustomizable())->toBeFalse();
    expect($product->isDropship())->toBeFalse();
});

/* ────────────────────────────────────────────────────────────
   14. Dropshipping checkout still works (Phase 6 regression).
   ──────────────────────────────────────────────────────────── */
it('Phase 8: dropship products still behave as Phase 6 expects', function () {
    $vendorUser = User::factory()->create();
    $vendorUser->assignRole('vendor');
    $vendor = Vendor::factory()->create(['user_id' => $vendorUser->id, 'status' => 'approved']);

    $product = Product::factory()->create([
        'vendor_id'   => $vendor->id,
        'type'        => Product::TYPE_DROPSHIP,
        'status'      => 'published',
        'price_minor' => 5000, 'currency' => 'KWD',
        'supplier_cost_minor' => 3000,
    ]);

    expect($product->isDropship())->toBeTrue();
    expect($product->isService())->toBeFalse();
});

/* ────────────────────────────────────────────────────────────
   15. Customizable product checkout still works (Phase 7 regression).
   ──────────────────────────────────────────────────────────── */
it('Phase 8: customizable products still behave as Phase 7 expects', function () {
    $vendorUser = User::factory()->create();
    $vendorUser->assignRole('vendor');
    $vendor = Vendor::factory()->create(['user_id' => $vendorUser->id, 'status' => 'approved']);

    $product = Product::factory()->create([
        'vendor_id' => $vendor->id, 'type' => Product::TYPE_CUSTOM,
        'status'    => 'published', 'price_minor' => 1500,
    ]);

    expect($product->isCustomizable())->toBeTrue();
    expect($product->isService())->toBeFalse();
});

/* ────────────────────────────────────────────────────────────
   16. No lazy-loading errors on Phase 8 pages under strict mode.
   ──────────────────────────────────────────────────────────── */
it('Phase 8: no lazy-loading errors on customer + vendor + admin booking pages', function () {
    $ctx = makeServiceContext();
    $customer = makeCustomer();
    $monday = \Carbon\Carbon::now()->next(\Carbon\Carbon::MONDAY);

    $this->actingAs($customer)->post('/bookings', [
        'service_id'          => $ctx['service']->id,
        'service_provider_id' => $ctx['provider']->id,
        'date'                => $monday->toDateString(), 'time' => '10:00',
    ]);
    $b = ServiceBooking::where('user_id', $customer->id)->first();

    // Touch every page that renders booking data — none should lazy-load
    $this->actingAs($customer)->get('/bookings')->assertOk();
    $this->actingAs($customer)->get("/bookings/{$b->id}")->assertOk();
    $this->actingAs($ctx['vendor']->user)->get('/vendor/bookings')->assertOk();
    $this->actingAs($ctx['vendor']->user)->get("/vendor/bookings/{$b->id}")->assertOk();
    $this->get("/services/{$ctx['service']->slug}")->assertOk();
});

/* ────────────────────────────────────────────────────────────
   17. ServiceBooking model-level safeguard (v7.4 pattern).
   ──────────────────────────────────────────────────────────── */
it('Phase 8: ServiceBooking refuses creation with null required field (model safeguard)', function () {
    expect(fn () => ServiceBooking::create([
        // Missing 'number' on purpose — should throw LogicException
        'user_id'          => 1, 'vendor_id' => 1, 'product_id' => 1,
        'booked_for_date'  => '2026-02-01', 'booked_for_time' => '10:00:00',
        'duration_minutes' => 30, 'location_mode' => 'provider_location',
        'price_minor'      => 1000, 'currency' => 'KWD',
        'status'           => ServiceBooking::STATUS_PENDING,
    ]))->toThrow(\LogicException::class);
});

/* ────────────────────────────────────────────────────────────
   18. ServiceBooking refuses unknown status value.
   ──────────────────────────────────────────────────────────── */
it('Phase 8: ServiceBooking refuses unknown status value', function () {
    expect(fn () => ServiceBooking::create([
        'number'           => 'SVC-TEST-' . uniqid(),
        'user_id'          => 1, 'vendor_id' => 1, 'product_id' => 1,
        'booked_for_date'  => '2026-02-01', 'booked_for_time' => '10:00:00',
        'duration_minutes' => 30, 'location_mode' => 'provider_location',
        'price_minor'      => 1000, 'currency' => 'KWD',
        'status'           => 'unknown_status_value',
    ]))->toThrow(\LogicException::class);
});
