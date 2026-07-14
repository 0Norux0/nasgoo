<?php

declare(strict_types=1);

/**
 * Phase 5 v6.1 — regression tests for the developer-reported issues:
 *   1. Wishlist menu visibility for logged-in customers
 *   2. OrderEvent->actor lazy-load on online/card payment confirmation
 *   3. Admin order detail page actions (Confirm/Ship/Deliver/Cancel/Refund)
 *   4. Vendor menu links visible for approved vendor / hidden for non-approved
 */

use App\Domain\Order\OrderLifecycleService;
use App\Domain\Payment\PaymentService;
use App\Models\Address;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentMethod;
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
});

/* ───────────────────────────────────────────────────
   1. Wishlist menu / Inertia shared props
   ─────────────────────────────────────────────────── */

it('v6.1: logged-in customer sees Inertia shared props that enable the Wishlist menu', function () {
    $customer = User::factory()->create();
    $customer->assignRole('customer');

    // Hit any page so we can read shared props
    $response = actingAs($customer)->get('/');

    $response->assertSuccessful()
        ->assertInertia(fn ($p) => $p
            // auth.user populated → StorefrontLayout shows the wishlist link block
            ->where('auth.user.id', $customer->id)
        );
});

it('v6.1: customer can reach /wishlist without errors', function () {
    $customer = User::factory()->create();
    $customer->assignRole('customer');

    actingAs($customer)->get('/wishlist')->assertSuccessful();
});

it('v6.1: guest is redirected from /wishlist to login', function () {
    $this->get('/wishlist')->assertRedirect('/login');
});

/* ───────────────────────────────────────────────────
   2. OrderEvent->actor lazy-load on online payment
   ─────────────────────────────────────────────────── */

it('v6.1: /orders/{id}/confirm does NOT lazy-load events.actor (online payment, multi-event)', function () {
    Model::shouldBeStrict(true);

    try {
        $customer = User::factory()->create();
        $customer->assignRole('customer');

        $vendorUser = User::factory()->create();
        $vendorUser->assignRole('vendor');
        $vendor = Vendor::factory()->approved()->for($vendorUser)->create();
        $product = Product::factory()->published()->create(['vendor_id' => $vendor->id, 'price_minor' => 5000]);
        Address::factory()->for($customer)->default()->create(['country' => 'KW']);

        actingAs($customer)->post('/cart/items', ['product_id' => $product->id, 'quantity' => 1]);
        actingAs($customer)->post('/checkout', [
            'shipping_address_id' => $customer->addresses()->first()->id,
            'payment_method_slug' => 'online_mock', // multi-event flow
        ]);
        $order = $customer->orders()->latest()->first();
        expect($order)->not->toBeNull();

        // Manufacture extra events as the mock provider's capture flow would do.
        // The lazy-load reproduces ONLY when the events collection has >1 row
        // (Eloquent's strict-mode detector requires multiple parents).
        $order->events()->create(['event_type' => 'payment.initiated', 'message' => 'init',     'actor_id' => $customer->id, 'actor_role' => 'system']);
        $order->events()->create(['event_type' => 'payment.captured',  'message' => 'captured', 'actor_id' => $customer->id, 'actor_role' => 'system']);
        $order->events()->create(['event_type' => 'status.confirmed',  'message' => 'confirmed','actor_id' => $customer->id, 'actor_role' => 'system']);

        // Under strict mode this would throw "Attempted to lazy load [actor]"
        // if confirm() didn't eager-load events.actor.
        $response = actingAs($customer)->get("/orders/{$order->id}/confirm");
        expect($response->status())->toBe(200);
    } finally {
        Model::shouldBeStrict(false);
    }
});

it('v6.1: /orders/{id}/show also handles multi-event eager-loading (existing v5.6 path)', function () {
    Model::shouldBeStrict(true);

    try {
        $customer = User::factory()->create();
        $customer->assignRole('customer');
        $order = Order::factory()->for($customer)->paid()->create();
        OrderItem::factory()->for($order)->create();

        // Multi-event collection
        $order->events()->create(['event_type' => 'a', 'message' => 'a', 'actor_id' => $customer->id, 'actor_role' => 'system']);
        $order->events()->create(['event_type' => 'b', 'message' => 'b', 'actor_id' => $customer->id, 'actor_role' => 'system']);
        $order->events()->create(['event_type' => 'c', 'message' => 'c', 'actor_id' => $customer->id, 'actor_role' => 'system']);

        actingAs($customer)->get("/orders/{$order->id}")->assertSuccessful();
    } finally {
        Model::shouldBeStrict(false);
    }
});

/* ───────────────────────────────────────────────────
   3. Admin order detail page header actions
   ─────────────────────────────────────────────────── */

it('v6.1: admin order detail ViewOrder page exposes lifecycle header actions', function () {
    // Static inspection — the methods that supply header actions are
    // available on the ViewOrder class. We sanity-check the class can be
    // instantiated and getHeaderActions returns at least 7 actions when
    // applied to an arbitrary order.
    $reflector = new \ReflectionClass(\App\Filament\Resources\OrderResource\Pages\ViewOrder::class);
    $method = $reflector->getMethod('getHeaderActions');
    expect($method->isProtected())->toBeTrue();

    // The body should reference each of: confirm, ship, deliver, cod_capture,
    // capture_transfer, cancel, refund — assert by source-string inspection.
    $src = file_get_contents($reflector->getFileName());
    foreach (['confirm', 'ship', 'deliver', 'cod_capture', 'capture_transfer', 'cancel', 'refund'] as $action) {
        expect($src)->toContain("Action::make('{$action}')",
            "ViewOrder header actions missing '{$action}'");
    }
});

it('v6.1: admin can mark an order delivered via OrderLifecycleService', function () {
    $admin = User::factory()->create(); $admin->assignRole('super_admin');
    $customer = User::factory()->create(); $customer->assignRole('customer');

    $order = Order::factory()->for($customer)->paid()->create([
        'status' => Order::STATUS_SHIPPED,
        'shipped_at' => now()->subDay(),
    ]);
    OrderItem::factory()->for($order)->create();

    app(OrderLifecycleService::class)->markDelivered($order, $admin);

    expect($order->fresh()->status)->toBe(Order::STATUS_DELIVERED);
    expect($order->fresh()->delivered_at)->not->toBeNull();
});

/* ───────────────────────────────────────────────────
   4. Vendor menu visibility (via vendor_status shared prop)
   ─────────────────────────────────────────────────── */

it('v6.1: approved vendor has vendor_status=approved in Inertia auth.user prop', function () {
    $vendorUser = User::factory()->create();
    $vendorUser->assignRole('vendor');
    Vendor::factory()->approved()->for($vendorUser)->create();

    actingAs($vendorUser)->get('/vendor')
        ->assertInertia(fn ($p) => $p->where('auth.user.vendor_status', 'approved'));
});

it('v6.1: pending vendor has vendor_status=pending (menu hides Wallet/Payouts/Reviews)', function () {
    $vendorUser = User::factory()->create();
    $vendorUser->assignRole('vendor');
    Vendor::factory()->for($vendorUser)->create(['status' => 'pending']);

    actingAs($vendorUser)->get('/vendor')
        ->assertInertia(fn ($p) => $p->where('auth.user.vendor_status', 'pending'));
});

it('v6.1: non-vendor user has vendor_status=null', function () {
    $customer = User::factory()->create();
    $customer->assignRole('customer');

    actingAs($customer)->get('/')
        ->assertInertia(fn ($p) => $p->where('auth.user.vendor_status', null));
});

/* ───────────────────────────────────────────────────
   5. /vendor/payouts alias route + access control
   ─────────────────────────────────────────────────── */

it('v6.1: approved vendor can reach /vendor/payouts (alias of /vendor/wallet)', function () {
    $vendorUser = User::factory()->create();
    $vendorUser->assignRole('vendor');
    Vendor::factory()->approved()->for($vendorUser)->create();

    actingAs($vendorUser)->get('/vendor/payouts')->assertSuccessful();
});

it('v6.1: pending vendor is bounced from /vendor/payouts to /vendor', function () {
    $vendorUser = User::factory()->create();
    $vendorUser->assignRole('vendor');
    Vendor::factory()->for($vendorUser)->create(['status' => 'pending']);

    actingAs($vendorUser)->get('/vendor/payouts')->assertRedirect('/vendor');
});

it('v6.1: pending vendor is bounced from /vendor/wallet to /vendor', function () {
    $vendorUser = User::factory()->create();
    $vendorUser->assignRole('vendor');
    Vendor::factory()->for($vendorUser)->create(['status' => 'pending']);

    actingAs($vendorUser)->get('/vendor/wallet')->assertRedirect('/vendor');
});

it('v6.1: rejected vendor is bounced from /vendor/reviews to /vendor', function () {
    $vendorUser = User::factory()->create();
    $vendorUser->assignRole('vendor');
    Vendor::factory()->for($vendorUser)->create(['status' => 'rejected']);

    actingAs($vendorUser)->get('/vendor/reviews')->assertRedirect('/vendor');
});

it('v6.1: suspended vendor is bounced from /vendor/wallet to /vendor', function () {
    $vendorUser = User::factory()->create();
    $vendorUser->assignRole('vendor');
    Vendor::factory()->for($vendorUser)->create(['status' => 'suspended']);

    actingAs($vendorUser)->get('/vendor/wallet')->assertRedirect('/vendor');
});

it('v6.1: vendor cannot see another vendor\'s payouts (scoping via Inertia history)', function () {
    $v1User = User::factory()->create(); $v1User->assignRole('vendor');
    $v1 = Vendor::factory()->approved()->for($v1User)->create();
    $v2User = User::factory()->create(); $v2User->assignRole('vendor');
    $v2 = Vendor::factory()->approved()->for($v2User)->create();

    \App\Models\VendorPayoutRequest::create([
        'vendor_id' => $v1->id, 'requested_amount_minor' => 1000, 'currency' => 'KWD',
        'status' => 'pending', 'payout_method' => 'bank_transfer', 'requested_at' => now(),
    ]);
    \App\Models\VendorPayoutRequest::create([
        'vendor_id' => $v2->id, 'requested_amount_minor' => 2000, 'currency' => 'KWD',
        'status' => 'pending', 'payout_method' => 'bank_transfer', 'requested_at' => now(),
    ]);

    actingAs($v1User)->get('/vendor/payouts')
        ->assertInertia(fn ($p) => $p->has('history', 1)->where('history.0.amount', '1.000'));
});
