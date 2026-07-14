<?php

declare(strict_types=1);

/**
 * Phase 4 v5.5 — regression for "Call to undefined method
 * App\Http\Controllers\OrderController::authorize()".
 *
 * Root cause: Laravel 11 ships the base Controller class empty by default.
 * Our controllers call $this->authorize(...) which requires the
 * AuthorizesRequests trait. v5.5 added the trait to app/Http/Controllers/
 * Controller.php — this file pins that fix.
 *
 * Three layers of coverage so this can never regress:
 *   1. Reflection assertion that AuthorizesRequests is on the base Controller
 *   2. The exact failing path from the developer's screenshot:
 *      GET /orders/{id}/confirm as the owner → 200, not 500
 *   3. Policy actually enforces ownership — foreign customer cannot view
 *      another customer's order; vendor cannot update another vendor's product
 */

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\PaymentMethodsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\VendorPackagesSeeder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(VendorPackagesSeeder::class);
    $this->seed(PaymentMethodsSeeder::class);
});

/* ─────────── Layer 1: static reflection ─────────── */

it('v5.5 / v9.4: base Controller must use AuthorizesRequests so $this->authorize() works', function () {
    // ROOT CAUSE (v9.4): the v5.5 version of this assertion was
    //   ->toContain(AuthorizesRequests::class, 'message')
    // where the second arg was meant as a failure-message hint. Pest's
    // toContain() treats EVERY arg as a value the collection must
    // contain, so this asserted that $traits also contained the literal
    // string 'Base Controller must use...' (impossible) → test always
    // failed. v9.4 strips the second arg; the description above carries
    // the context.
    $reflection = new ReflectionClass(\App\Http\Controllers\Controller::class);
    $traits     = array_keys($reflection->getTraits());
    expect($traits)->toContain(AuthorizesRequests::class);
});

it('v5.5: every controller calling $this->authorize() ultimately extends the base Controller', function () {
    $controllersDir = app_path('Http/Controllers');
    $callers = [];
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($controllersDir));
    foreach ($rii as $file) {
        if ($file->isDir() || $file->getExtension() !== 'php') continue;
        $src = file_get_contents($file->getPathname());

        // Phase 9 v9.4 — strip comments before scanning so the scanner
        // doesn't match `$this->authorize(` in docblocks or inline notes.
        // Without this strip, every controller whose comment mentions the
        // call (eg. "the v5.5 fix added AuthorizesRequests so
        // $this->authorize() works") was falsely flagged. Use PHP's own
        // token_get_all to drop T_COMMENT and T_DOC_COMMENT.
        $stripped = '';
        foreach (token_get_all($src) as $token) {
            if (is_array($token)) {
                if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) continue;
                $stripped .= $token[1];
            } else {
                $stripped .= $token;
            }
        }
        if (! str_contains($stripped, '$this->authorize(')) continue;

        if (! preg_match('/namespace\s+([^;]+);/', $src, $m)) continue;
        $fqn = trim($m[1]) . '\\' . $file->getBasename('.php');
        if (! class_exists($fqn)) continue;

        $callers[] = $fqn;
        expect(is_subclass_of($fqn, Controller::class))
            ->toBeTrue("{$fqn} calls \$this->authorize() but does not extend the base Controller");
    }
    expect($callers)->not->toBe([], 'sanity: the test should find at least one $this->authorize() caller');
});

/* ─────────── Layer 2: the exact failing path from the screenshot ─────────── */

it('v5.5: GET /orders/{id}/confirm returns 200 for the owner (was 500 in v5.4)', function () {
    $customer = User::factory()->create();
    $customer->assignRole('customer');
    Address::factory()->for($customer)->default()->create(['country' => 'KW', 'city' => 'Kuwait City']);
    $vendorUser = User::factory()->create();
    $vendorUser->assignRole('vendor');
    $vendor = Vendor::factory()->approved()->for($vendorUser)->create();
    $product = Product::factory()->published()->create([
        'vendor_id' => $vendor->id, 'stock' => 5, 'price_minor' => 5000,
    ]);

    // Place a real order through the checkout flow so we exercise the same
    // code path the developer hit.
    actingAs($customer)->post('/cart/items', ['product_id' => $product->id, 'quantity' => 1]);
    $response = actingAs($customer)->post('/checkout', [
        'shipping_address_id' => $customer->addresses()->first()->id,
        'payment_method_slug' => 'cod',
    ]);
    $response->assertRedirect();
    $order = $customer->orders()->latest()->first();

    // The screenshot's exact URL pattern: /orders/{id}/confirm
    $confirm = actingAs($customer)->get("/orders/{$order->id}/confirm");
    expect($confirm->status())
        ->not->toBe(500, 'OrderController::confirm() crashed — authorize() trait missing?')
        ->toBe(200);
});

it('v5.5: GET /orders/{id} returns 200 for the owner (covers all three authorize() calls in OrderController)', function () {
    $customer = User::factory()->create();
    $customer->assignRole('customer');
    $order = Order::factory()->paid()->for($customer)->create();

    actingAs($customer)->get("/orders/{$order->id}")
        ->assertSuccessful()
        ->assertInertia(fn ($p) => $p->component('Orders/Show'));
});

/* ─────────── Layer 3: policies actually enforce ownership ─────────── */

it('v5.5: foreign customer cannot view another customer\'s order (policy enforces)', function () {
    $owner   = User::factory()->create();
    $owner->assignRole('customer');
    $foreign = User::factory()->create();
    $foreign->assignRole('customer');
    $order = Order::factory()->paid()->for($owner)->create();

    // Both routes the OrderPolicy::view() gates
    actingAs($foreign)->get("/orders/{$order->id}")->assertForbidden();
    actingAs($foreign)->get("/orders/{$order->id}/confirm")->assertForbidden();
});

it('v5.5: foreign customer cannot cancel another customer\'s order', function () {
    $owner   = User::factory()->create();
    $owner->assignRole('customer');
    $foreign = User::factory()->create();
    $foreign->assignRole('customer');
    $order = Order::factory()->paid()->for($owner)->create();

    actingAs($foreign)->post("/orders/{$order->id}/cancel", ['reason' => 'nope'])
        ->assertForbidden();
});

it('v5.5: vendor cannot update another vendor\'s product (ProductPolicy::update)', function () {
    $vendor1User = User::factory()->create();
    $vendor1User->assignRole('vendor');
    $vendor1 = Vendor::factory()->approved()->for($vendor1User)->create();

    $vendor2User = User::factory()->create();
    $vendor2User->assignRole('vendor');
    $vendor2 = Vendor::factory()->approved()->for($vendor2User)->create();

    $foreignProduct = Product::factory()->create(['vendor_id' => $vendor2->id]);

    // POST /vendor/products/{product} is the update route
    $response = actingAs($vendor1User)->post("/vendor/products/{$foreignProduct->id}", [
        'name'        => 'Hijacked',
        'type'        => Product::TYPE_SIMPLE,
        'price_minor' => 1000,
        'currency'    => 'KWD',
    ]);
    expect($response->status())->toBeIn([403, 404]); // policy denies OR route-model-binding scopes hide it
    expect($foreignProduct->fresh()->name)->not->toBe('Hijacked');
});

it('v5.5: vendor cannot ship another vendor\'s order', function () {
    $vendor1User = User::factory()->create();
    $vendor1User->assignRole('vendor');
    Vendor::factory()->approved()->for($vendor1User)->create();

    $vendor2User = User::factory()->create();
    $vendor2User->assignRole('vendor');
    $vendor2 = Vendor::factory()->approved()->for($vendor2User)->create();
    $order = Order::factory()->paid()->create();
    \App\Models\OrderItem::factory()->for($order)->state(['vendor_id' => $vendor2->id])->create();

    actingAs($vendor1User)->post("/vendor/orders/{$order->id}/ship")
        ->assertStatus(404);  // VendorOrderController scopes by vendor — foreign order = not found
});

/* ─────────── Admin access ─────────── */

it('v5.5: admin can view any order via the Filament OrderResource query', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $customer = User::factory()->create();
    $customer->assignRole('customer');
    $order = Order::factory()->paid()->for($customer)->create();

    // The OrderPolicy::before() short-circuits to true for admins
    expect($admin->can('view', $order))->toBeTrue();

    // And the Filament resource query is unscoped
    expect(
        \App\Filament\Resources\OrderResource::getEloquentQuery()
            ->whereKey($order->id)
            ->exists()
    )->toBeTrue();
});
