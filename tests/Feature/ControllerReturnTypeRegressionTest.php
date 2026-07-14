<?php

declare(strict_types=1);

/**
 * Phase 4 v5.3 — regression test for the TypeError that broke /checkout.
 *
 * Pre-v5.3 CheckoutController::show() was typed as Symfony Response, but
 * Inertia\Response is NOT a Symfony Response subclass — it's a Responsable.
 * When the cart wasn't empty, the method tried to return Inertia\Response,
 * PHP refused, and the user saw a 500 TypeError.
 *
 * This file:
 *   1. Pins all Inertia-returning controller methods to a compatible type
 *   2. Hits /checkout via real HTTP and asserts no 500
 *   3. Asserts checkout succeeds end-to-end for each payment provider
 *      (COD, manual_transfer, online_mock) — same coverage the developer
 *      asked for in the v5.3 brief
 *   4. Asserts the empty-cart redirect path still works (the OTHER branch
 *      of the return-type union)
 */

use App\Models\Address;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\PaymentMethodsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\VendorPackagesSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(VendorPackagesSeeder::class);
    $this->seed(PaymentMethodsSeeder::class);
});

/* ─────────── STATIC RETURN-TYPE ASSERTIONS ─────────── */

it('v5.3: CheckoutController::show declares a return type compatible with Inertia\\Response', function () {
    $method = (new ReflectionClass(\App\Http\Controllers\CheckoutController::class))->getMethod('show');
    $rt = $method->getReturnType();
    expect($rt)->not->toBeNull('CheckoutController::show must have a return type');

    // The type MUST include Inertia\Response (the bug was declaring only Symfony Response).
    $names = $rt instanceof ReflectionUnionType
        ? array_map(fn ($t) => $t->getName(), $rt->getTypes())
        : ($rt instanceof ReflectionNamedType ? [$rt->getName()] : []);

    expect($names)->toContain(\Inertia\Response::class);
    // The other branch must be a RedirectResponse so the empty-cart bounce
    // is also legal.
    expect($names)->toContain(\Illuminate\Http\RedirectResponse::class);
});

it('v5.3: no controller method calls Inertia::render() with a return type that excludes Inertia\\Response', function () {
    $controllerDir = app_path('Http/Controllers');
    $offenders = [];

    foreach (\Symfony\Component\Finder\Finder::create()->files()->in($controllerDir)->name('*.php') as $file) {
        $src = $file->getContents();
        if (! str_contains($src, 'Inertia::render')) {
            continue;
        }

        // Resolve the class FQN
        if (! preg_match('/namespace\s+([^;]+);/', $src, $m)) continue;
        $fqn = trim($m[1]) . '\\' . $file->getFilenameWithoutExtension();
        if (! class_exists($fqn)) continue;

        $rc = new ReflectionClass($fqn);
        foreach ($rc->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() !== $fqn) continue;
            // Pull the method source by line range
            $start = $method->getStartLine();
            $end   = $method->getEndLine();
            if ($start === false || $end === false) continue;
            $lines = file($method->getFileName());
            $body = implode('', array_slice($lines, $start - 1, $end - $start + 1));
            if (! str_contains($body, 'Inertia::render')) continue;

            $rt = $method->getReturnType();
            if (! $rt) continue; // no declared type is fine — PHP won't enforce

            $names = $rt instanceof ReflectionUnionType
                ? array_map(fn ($t) => $t->getName(), $rt->getTypes())
                : ($rt instanceof ReflectionNamedType ? [$rt->getName()] : []);

            $hasInertia = false;
            foreach ($names as $n) {
                if ($n === \Inertia\Response::class) { $hasInertia = true; break; }
            }
            if (! $hasInertia) {
                $offenders[] = "{$fqn}::{$method->getName()}() returns Inertia but declares " . implode('|', $names);
            }
        }
    }

    expect($offenders)->toBe(
        [],
        "Methods calling Inertia::render() must declare a return type that includes Inertia\\Response.\n"
        . "Offenders:\n  " . implode("\n  ", $offenders)
    );
});

/* ─────────── REAL HTTP REGRESSION ─────────── */

it('v5.3: GET /checkout returns 200 (was 500 with TypeError in v5.0-v5.2)', function () {
    $user = User::factory()->create();
    $product = Product::factory()->published()->create(['stock' => 5]);
    actingAs($user)->post('/cart/items', ['product_id' => $product->id, 'quantity' => 1]);

    $response = actingAs($user)->get('/checkout');

    // 200 = the page rendered through Inertia. 500 was the v5.0-v5.2 TypeError.
    expect($response->status())
        ->not->toBe(500, 'The v5.0-v5.2 TypeError has returned — check CheckoutController::show return type')
        ->toBe(200);

    $response->assertInertia(fn ($p) => $p->component('Checkout/Show'));
});

it('v5.3: GET /checkout with empty cart redirects to /cart (the OTHER branch of the union return type)', function () {
    $user = User::factory()->create();

    $response = actingAs($user)->get('/checkout');

    expect($response->status())->not->toBe(500);
    $response->assertRedirect('/cart');
});

/* ─────────── END-TO-END WITH EACH PAYMENT METHOD ─────────── */

it('v5.3: COD checkout works end-to-end', function () {
    $user = User::factory()->create();
    Address::factory()->for($user)->default()->create(['country' => 'KW', 'city' => 'Kuwait City']);
    $product = Product::factory()->published()->create(['stock' => 5, 'price_minor' => 5000]);
    actingAs($user)->post('/cart/items', ['product_id' => $product->id, 'quantity' => 1]);

    $response = actingAs($user)->post('/checkout', [
        'shipping_address_id' => $user->addresses()->first()->id,
        'payment_method_slug' => 'cod',
    ]);

    expect($response->status())->not->toBe(500);
    expect($response->status())->not->toBe(419);
    $response->assertRedirect();

    $order = $user->orders()->latest()->first();
    expect($order)->not->toBeNull();
    expect($order->payments()->first()->reference)->toStartWith('COD-');
});

it('v5.3: Manual bank transfer checkout works end-to-end', function () {
    $user = User::factory()->create();
    Address::factory()->for($user)->default()->create(['country' => 'KW', 'city' => 'KC']);
    $product = Product::factory()->published()->create(['stock' => 5]);
    actingAs($user)->post('/cart/items', ['product_id' => $product->id, 'quantity' => 1]);

    $response = actingAs($user)->post('/checkout', [
        'shipping_address_id' => $user->addresses()->first()->id,
        'payment_method_slug' => 'manual_transfer',
    ]);

    expect($response->status())->not->toBe(500);
    expect($response->status())->not->toBe(419);
    $response->assertRedirect();

    $payment = $user->orders()->latest()->first()->payments()->first();
    expect($payment->reference)->toStartWith('BT-');
});

it('v5.3: Mock online payment checkout works end-to-end', function () {
    $user = User::factory()->create();
    Address::factory()->for($user)->default()->create(['country' => 'KW', 'city' => 'KC']);
    $product = Product::factory()->published()->create(['stock' => 5]);
    actingAs($user)->post('/cart/items', ['product_id' => $product->id, 'quantity' => 1]);

    $response = actingAs($user)->post('/checkout', [
        'shipping_address_id' => $user->addresses()->first()->id,
        'payment_method_slug' => 'online_mock',
    ]);

    expect($response->status())->not->toBe(500);
    expect($response->status())->not->toBe(419);
    $response->assertRedirect();

    $order = $user->orders()->latest()->first();
    expect($order->payment_status)->toBe(\App\Models\Order::PAY_PAID);
    expect($order->payments()->first()->external_id)->toStartWith('MOCK-');
});

it('v5.3: customer with cart can access checkout (smoke)', function () {
    $user = User::factory()->create();
    $product = Product::factory()->published()->create(['stock' => 5]);
    actingAs($user)->post('/cart/items', ['product_id' => $product->id, 'quantity' => 1]);

    $r = actingAs($user)->get('/checkout');
    expect($r->status())->toBe(200);
});

it('v5.3: customer without cart is redirected (no SQL error, no TypeError)', function () {
    $user = User::factory()->create();
    actingAs($user)->get('/checkout')->assertRedirect('/cart');
});
