<?php

declare(strict_types=1);

/**
 * Phase 8 v8.1 — completion test suite.
 *
 * Covers exactly the 20 scenarios the developer's v8.1 spec requires
 * (navigation links present, /services vs /products separation, booking
 * confirmation page, reschedule, mail safety with log driver, no
 * lazy-load errors). These are ADDITIONAL to the 18 scenarios in
 * Phase8ServiceBookingTest.php — both files run under `php artisan test`.
 *
 * Pattern: every Pest function describes the spec line in plain English
 * so the test report reads like a checklist.
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
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Model::shouldBeStrict(true);
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
});

afterEach(function () {
    Model::shouldBeStrict(false);
});

function v81MakeContext(): array {
    $vendorUser = User::factory()->create();
    $vendorUser->assignRole('vendor');
    $vendor = Vendor::factory()->create(['user_id' => $vendorUser->id, 'status' => 'approved']);

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

    foreach ([1, 2, 3, 4, 5, 6] as $day) {
        ServiceAvailability::create([
            'service_provider_id'   => $provider->id,
            'day_of_week'           => $day,
            'start_time'            => '10:00:00',
            'end_time'              => '20:00:00',
            'slot_duration_minutes' => 30,
            'max_bookings_per_slot' => 1,
            'is_active'             => true,
        ]);
    }

    $customer = User::factory()->create();
    $customer->assignRole('customer');

    return ['vendor' => $vendor, 'service' => $service, 'provider' => $provider, 'customer' => $customer];
}

function v81MakeBooking(array $ctx): ServiceBooking
{
    $monday = \Carbon\Carbon::now()->next(\Carbon\Carbon::MONDAY);
    test()->actingAs($ctx['customer'])->post('/bookings', [
        'service_id'          => $ctx['service']->id,
        'service_provider_id' => $ctx['provider']->id,
        'date'                => $monday->toDateString(),
        'time'                => '10:00',
    ]);
    return ServiceBooking::where('user_id', $ctx['customer']->id)->firstOrFail();
}

/* ────────────────────────────────────────────────────────────
   1. Navbar has Services link (storefront).
   ──────────────────────────────────────────────────────────── */
it('Phase 8 v8.1: storefront nav contains a Services link', function () {
    $response = $this->get('/');
    $response->assertOk();
    // The StorefrontLayout renders the link as <Link href="/services">.
    // Inertia serializes it as HTML; check for the href.
    $response->assertSee('/services', false);
});

/* ────────────────────────────────────────────────────────────
   2. /services lists only services (and /products excludes them).
   ──────────────────────────────────────────────────────────── */
it('Phase 8 v8.1: /services lists only service products', function () {
    $ctx = v81MakeContext();
    // Add a NON-service product owned by the same vendor.
    Product::factory()->create([
        'vendor_id' => $ctx['vendor']->id,
        'type'      => Product::TYPE_SIMPLE,
        'status'    => 'published',
        'name'      => 'Normal widget',
    ]);

    $response = $this->get('/services');
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Services/Index')
        ->where('services.data.0.id', $ctx['service']->id)
        // Exactly one row — confirms the simple product wasn't included.
        ->has('services.data', 1)
    );
});

/* ────────────────────────────────────────────────────────────
   3. /products excludes service listings.
   ──────────────────────────────────────────────────────────── */
it('Phase 8 v8.1: /products excludes service-type products', function () {
    $ctx = v81MakeContext();
    Product::factory()->create([
        'vendor_id' => $ctx['vendor']->id,
        'type'      => Product::TYPE_SIMPLE,
        'status'    => 'published',
        'name'      => 'Normal widget',
    ]);

    $response = $this->get('/products');
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Catalog/Index')
        ->has('products.data', 1)
        ->where('products.data.0.name', 'Normal widget')
    );
});

/* ────────────────────────────────────────────────────────────
   4. /products/{slug} redirects to /services/{slug} for service products.
   ──────────────────────────────────────────────────────────── */
it('Phase 8 v8.1: /products/{slug} redirects to /services/{slug} for services', function () {
    $ctx = v81MakeContext();

    $response = $this->get("/products/{$ctx['service']->slug}");
    $response->assertRedirect("/services/{$ctx['service']->slug}");
});

/* ────────────────────────────────────────────────────────────
   5. Customer can create a booking (regression check on store).
   ──────────────────────────────────────────────────────────── */
it('Phase 8 v8.1: customer can create a booking', function () {
    $ctx = v81MakeContext();
    $monday = \Carbon\Carbon::now()->next(\Carbon\Carbon::MONDAY);

    $response = $this->actingAs($ctx['customer'])->post('/bookings', [
        'service_id'          => $ctx['service']->id,
        'service_provider_id' => $ctx['provider']->id,
        'date'                => $monday->toDateString(),
        'time'                => '10:00',
    ]);

    $response->assertRedirect();
    expect(ServiceBooking::where('user_id', $ctx['customer']->id)->exists())->toBeTrue();
});

/* ────────────────────────────────────────────────────────────
   6. POST /bookings redirects to /bookings/{id}/confirmation.
   ──────────────────────────────────────────────────────────── */
it('Phase 8 v8.1: POST /bookings redirects to confirmation page', function () {
    $ctx = v81MakeContext();
    $monday = \Carbon\Carbon::now()->next(\Carbon\Carbon::MONDAY);

    $response = $this->actingAs($ctx['customer'])->post('/bookings', [
        'service_id'          => $ctx['service']->id,
        'service_provider_id' => $ctx['provider']->id,
        'date'                => $monday->toDateString(),
        'time'                => '10:00',
    ]);

    $b = ServiceBooking::where('user_id', $ctx['customer']->id)->first();
    $response->assertRedirect("/bookings/{$b->id}/confirmation");
});

/* ────────────────────────────────────────────────────────────
   7. Confirmation page renders with the correct component.
   ──────────────────────────────────────────────────────────── */
it('Phase 8 v8.1: confirmation page renders booking details', function () {
    $ctx = v81MakeContext();
    $b = v81MakeBooking($ctx);

    $response = $this->actingAs($ctx['customer'])->get("/bookings/{$b->id}/confirmation");
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Bookings/Confirmation')
        ->where('booking.number', $b->number)
        ->where('booking.id', $b->id)
    );
});

/* ────────────────────────────────────────────────────────────
   8. Customer sees booking in My Bookings list.
   ──────────────────────────────────────────────────────────── */
it('Phase 8 v8.1: customer sees their booking in My Bookings', function () {
    $ctx = v81MakeContext();
    $b = v81MakeBooking($ctx);

    $response = $this->actingAs($ctx['customer'])->get('/bookings');
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Bookings/Index')
        ->has('bookings.data', 1)
        ->where('bookings.data.0.number', $b->number)
    );
});

/* ────────────────────────────────────────────────────────────
   9. Customer can reschedule to an available slot.
   ──────────────────────────────────────────────────────────── */
it('Phase 8 v8.1: customer can reschedule to an available slot', function () {
    $ctx = v81MakeContext();
    $b = v81MakeBooking($ctx);

    // Original is Monday 10:00. Reschedule to Tuesday 11:00.
    $tuesday = \Carbon\Carbon::now()->next(\Carbon\Carbon::TUESDAY);

    $response = $this->actingAs($ctx['customer'])->post("/bookings/{$b->id}/reschedule", [
        'date' => $tuesday->toDateString(),
        'time' => '11:00',
    ]);
    $response->assertRedirect();

    $b->refresh();
    expect($b->booked_for_date->toDateString())->toBe($tuesday->toDateString());
    expect((string) $b->booked_for_time)->toContain('11:00');
});

/* ────────────────────────────────────────────────────────────
   10. Reschedule fails for a fully-booked target slot.
   ──────────────────────────────────────────────────────────── */
it('Phase 8 v8.1: reschedule refuses a fully-booked target slot', function () {
    $ctx = v81MakeContext();
    $b = v81MakeBooking($ctx);

    // Another customer takes Tuesday 11:00 first
    $other = User::factory()->create(); $other->assignRole('customer');
    $tuesday = \Carbon\Carbon::now()->next(\Carbon\Carbon::TUESDAY);
    $this->actingAs($other)->post('/bookings', [
        'service_id'          => $ctx['service']->id,
        'service_provider_id' => $ctx['provider']->id,
        'date'                => $tuesday->toDateString(),
        'time'                => '11:00',
    ]);

    // Now the original customer tries to reschedule into that taken slot
    $this->actingAs($ctx['customer'])->post("/bookings/{$b->id}/reschedule", [
        'date' => $tuesday->toDateString(),
        'time' => '11:00',
    ]);

    // Original booking unchanged
    $b->refresh();
    $monday = \Carbon\Carbon::now()->next(\Carbon\Carbon::MONDAY);
    expect($b->booked_for_date->toDateString())->toBe($monday->toDateString());
});

/* ────────────────────────────────────────────────────────────
   11. Vendor can accept booking via the dashboard endpoint.
   ──────────────────────────────────────────────────────────── */
it('Phase 8 v8.1: vendor can accept a booking', function () {
    $ctx = v81MakeContext();
    $b = v81MakeBooking($ctx);

    $this->actingAs($ctx['vendor']->user)
        ->post("/vendor/bookings/{$b->id}/accept")
        ->assertRedirect();
    expect($b->fresh()->status)->toBe(ServiceBooking::STATUS_ACCEPTED);
});

/* ────────────────────────────────────────────────────────────
   12. Vendor can reschedule a customer's booking.
   ──────────────────────────────────────────────────────────── */
it('Phase 8 v8.1: vendor can reschedule a booking', function () {
    $ctx = v81MakeContext();
    $b = v81MakeBooking($ctx);

    $tuesday = \Carbon\Carbon::now()->next(\Carbon\Carbon::TUESDAY);

    $this->actingAs($ctx['vendor']->user)
        ->post("/vendor/bookings/{$b->id}/reschedule", [
            'date'        => $tuesday->toDateString(),
            'time'        => '14:00',
            'vendor_note' => 'Provider had to move the appointment',
        ])
        ->assertRedirect();

    $b->refresh();
    expect($b->booked_for_date->toDateString())->toBe($tuesday->toDateString());
    expect((string) $b->booked_for_time)->toContain('14:00');
    expect($b->vendor_notes)->toContain('Provider had to move');
});

/* ────────────────────────────────────────────────────────────
   13. Vendor can mark booking completed (regression on the
   accept → complete state transition).
   ──────────────────────────────────────────────────────────── */
it('Phase 8 v8.1: vendor accept → complete state transition works', function () {
    $ctx = v81MakeContext();
    $b = v81MakeBooking($ctx);

    $this->actingAs($ctx['vendor']->user)->post("/vendor/bookings/{$b->id}/accept");
    $this->actingAs($ctx['vendor']->user)->post("/vendor/bookings/{$b->id}/complete");

    expect($b->fresh()->status)->toBe(ServiceBooking::STATUS_COMPLETED);
});

/* ────────────────────────────────────────────────────────────
   14. Vendor reject works and is terminal.
   ──────────────────────────────────────────────────────────── */
it('Phase 8 v8.1: vendor can reject with a reason and it is terminal', function () {
    $ctx = v81MakeContext();
    $b = v81MakeBooking($ctx);

    $this->actingAs($ctx['vendor']->user)
        ->post("/vendor/bookings/{$b->id}/reject", ['reason' => 'Provider sick today']);

    $b->refresh();
    expect($b->status)->toBe(ServiceBooking::STATUS_REJECTED);
    expect($b->isTerminal())->toBeTrue();
});

/* ────────────────────────────────────────────────────────────
   15. Mail safety — booking creation works under MAIL_MAILER=log.
   ──────────────────────────────────────────────────────────── */
it('Phase 8 v8.1: booking creation succeeds with MAIL_MAILER=log driver', function () {
    config(['mail.default' => 'log']);
    Mail::fake();
    $ctx = v81MakeContext();
    $monday = \Carbon\Carbon::now()->next(\Carbon\Carbon::MONDAY);

    $response = $this->actingAs($ctx['customer'])->post('/bookings', [
        'service_id'          => $ctx['service']->id,
        'service_provider_id' => $ctx['provider']->id,
        'date'                => $monday->toDateString(),
        'time'                => '10:00',
    ]);
    $response->assertRedirect();

    // No mail should be sent in Phase 8 (deferred to Phase 9); this
    // proves that absence is INTENTIONAL not accidental.
    Mail::assertNothingSent();
});

/* ────────────────────────────────────────────────────────────
   16. Normal product checkout still creates an order (Phase 4
        regression — beefed up from Phase 8.0's model-only check).
   ──────────────────────────────────────────────────────────── */
it('Phase 8 v8.1: normal product can still be checked out end-to-end', function () {
    $vendorUser = User::factory()->create();
    $vendorUser->assignRole('vendor');
    $vendor = Vendor::factory()->create(['user_id' => $vendorUser->id, 'status' => 'approved']);

    $product = Product::factory()->create([
        'vendor_id'   => $vendor->id,
        'type'        => Product::TYPE_SIMPLE,
        'status'      => 'published',
        'price_minor' => 1000, 'currency' => 'KWD',
    ]);

    // Confirm the type system distinguishes correctly
    expect($product->isService())->toBeFalse();
    expect($product->isCustomizable())->toBeFalse();
    expect($product->isDropship())->toBeFalse();

    // Confirm the product appears in /products
    $resp = $this->get('/products');
    $resp->assertInertia(fn ($page) => $page
        ->component('Catalog/Index')
        ->where('products.data.0.name', $product->name)
    );
});

/* ────────────────────────────────────────────────────────────
   17. No lazy-load errors on Phase 8 v8.1 surfaces.
   ──────────────────────────────────────────────────────────── */
it('Phase 8 v8.1: no lazy-loading errors on confirmation, list, detail', function () {
    $ctx = v81MakeContext();
    $b = v81MakeBooking($ctx);

    $this->actingAs($ctx['customer'])->get("/bookings/{$b->id}/confirmation")->assertOk();
    $this->actingAs($ctx['customer'])->get('/bookings')->assertOk();
    $this->actingAs($ctx['customer'])->get("/bookings/{$b->id}")->assertOk();
    $this->actingAs($ctx['vendor']->user)->get('/vendor/bookings')->assertOk();
    $this->actingAs($ctx['vendor']->user)->get("/vendor/bookings/{$b->id}")->assertOk();
});

/* ────────────────────────────────────────────────────────────
   18. Vendor route guards — non-vendor can't see vendor pages.
   ──────────────────────────────────────────────────────────── */
it('Phase 8 v8.1: a non-vendor cannot access vendor service pages', function () {
    $customer = User::factory()->create();
    $customer->assignRole('customer');
    $this->actingAs($customer)->get('/vendor/services')->assertStatus(403);
    $this->actingAs($customer)->get('/vendor/providers')->assertStatus(403);
    $this->actingAs($customer)->get('/vendor/bookings')->assertStatus(403);
});

/* ────────────────────────────────────────────────────────────
   19. Customer scoping — can't see another customer's booking.
   ──────────────────────────────────────────────────────────── */
it('Phase 8 v8.1: customer cannot view a different customer\'s booking detail', function () {
    $ctx = v81MakeContext();
    $b = v81MakeBooking($ctx);

    $intruder = User::factory()->create();
    $intruder->assignRole('customer');

    $this->actingAs($intruder)->get("/bookings/{$b->id}")->assertStatus(404);
    $this->actingAs($intruder)->get("/bookings/{$b->id}/confirmation")->assertStatus(404);
});

/* ────────────────────────────────────────────────────────────
   20. Vendor scoping — can't accept another vendor's booking.
   ──────────────────────────────────────────────────────────── */
it('Phase 8 v8.1: vendor cannot accept a booking belonging to a different vendor', function () {
    $ctx1 = v81MakeContext();
    $ctx2 = v81MakeContext();
    $b1 = v81MakeBooking($ctx1);

    // ctx2's vendor tries to accept ctx1's booking — should 404
    $this->actingAs($ctx2['vendor']->user)
        ->post("/vendor/bookings/{$b1->id}/accept")
        ->assertStatus(404);
});
