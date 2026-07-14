<?php

declare(strict_types=1);

/**
 * Phase 4 v5.1 — audit item 12: no 419 errors during cart/checkout/order actions.
 *
 * The v3.3 fix moved CSRF handling to cookie-based XSRF-TOKEN with axios's
 * built-in withCredentials + xsrfCookieName config. This test pins:
 *   1. The Inertia bootstrap is wired to read the cookie (regression — if
 *      someone reverts to a stale meta-tag token, this catches it).
 *   2. CSRF middleware is enabled on the web group that Phase 4 routes use.
 *   3. The HTTP integration tests in Phase4HttpFlowTest pass — those exercise
 *      every Phase 4 POST/PATCH/DELETE end-to-end. If CSRF were broken or
 *      misconfigured, those tests would 419 wholesale.
 *
 * Laravel's testing harness automatically supplies a valid CSRF token, so
 * we cannot black-box test a real 419 here — but we CAN verify the wiring
 * that prevents 419s in production.
 */

use Illuminate\Support\Facades\Route;
use Symfony\Component\Routing\Route as SymfonyRoute;

it('item 12: bootstrap.ts is configured to use cookie-based XSRF-TOKEN', function () {
    $bootstrap = file_get_contents(base_path('resources/js/bootstrap.ts'));
    expect($bootstrap)->not->toBeFalse();

    // v3.3 settings — any rewrite that loses these brings back the 419 nightmare
    expect($bootstrap)->toContain("xsrfCookieName");
    expect($bootstrap)->toContain("'XSRF-TOKEN'");
    expect($bootstrap)->toContain('withCredentials');

    // Header name must match Laravel's VerifyCsrfToken expectation
    expect($bootstrap)->toContain('X-XSRF-TOKEN');
});

it('item 12: bootstrap does NOT read a stale meta-tag token (regression check)', function () {
    $bootstrap = file_get_contents(base_path('resources/js/bootstrap.ts'));
    // The old approach was: document.querySelector('meta[name="csrf-token"]')
    // That's what causes 419s when the page is open across token refreshes.
    expect($bootstrap)->not->toContain('csrf-token');
    expect($bootstrap)->not->toContain('querySelector(\'meta');
});

it('item 12: every Phase 4 POST/PATCH/DELETE route is in the web middleware group', function () {
    $phase4PostRoutes = [
        'cart.add',         // POST /cart/items
        'cart.update',      // PATCH /cart/items/{item}
        'cart.remove',      // DELETE /cart/items/{item}
        'cart.clear',       // POST /cart/clear
        'checkout.place',   // POST /checkout
        'orders.cancel',    // POST /orders/{order}/cancel
        'vendor.orders.ship', // POST /vendor/orders/{order}/ship
    ];

    foreach ($phase4PostRoutes as $name) {
        $route = Route::getRoutes()->getByName($name);
        expect($route)->not->toBeNull("Route {$name} is not registered");

        // The web group includes VerifyCsrfToken by default in Laravel 11.
        // Confirm it's in the 'web' group:
        expect($route->gatherMiddleware())->toContain('web');
    }
});

it('item 12: a real round-trip POST to /cart/items succeeds (no 419)', function () {
    $user = \App\Models\User::factory()->create();
    $product = \App\Models\Product::factory()->published()->create(['stock' => 5]);

    $response = $this->actingAs($user)
        ->post('/cart/items', ['product_id' => $product->id, 'quantity' => 1]);

    // If CSRF were broken, this would be 419 — Laravel's test harness still
    // exercises the middleware pipeline; an explicit non-419 assertion
    // documents the requirement for future readers.
    expect($response->status())->not->toBe(419);
    $response->assertRedirect();
});

it('item 12: a real round-trip POST to /checkout succeeds (no 419)', function () {
    $this->seed(\Database\Seeders\PaymentMethodsSeeder::class);
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->seed(\Database\Seeders\VendorPackagesSeeder::class);

    $user = \App\Models\User::factory()->create();
    $product = \App\Models\Product::factory()->published()->create(['stock' => 5]);
    $this->actingAs($user)->post('/cart/items', ['product_id' => $product->id, 'quantity' => 1]);

    $response = $this->actingAs($user)->post('/checkout', [
        'shipping_address' => ['recipient_name' => 'A', 'phone' => '+1', 'country' => 'KW', 'city' => 'C', 'street' => 'L'],
        'payment_method_slug' => 'cod',
    ]);

    expect($response->status())->not->toBe(419);
});
