<?php

declare(strict_types=1);

use App\Domain\Order\OrderLifecycleService;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// v8.5 — every helper prefixed `p94_` to avoid collisions with existing
// helpers (p9_, p91_, p93_, and the 22 from prior phases).

function p94Customer(string $email = 'p94-cust@test'): User
{
    return User::factory()->create(['email' => $email, 'role' => 'customer']);
}

function p94Vendor(string $email): array
{
    $u = User::factory()->create(['email' => $email, 'role' => 'vendor']);
    $v = Vendor::factory()->create(['user_id' => $u->id, 'status' => 'approved']);
    return [$u, $v];
}

function p94Product(Vendor $v, string $slug, int $price = 50000): Product
{
    return Product::factory()->published()->create([
        'vendor_id'   => $v->id,
        'slug'        => $slug . '-' . $v->id,
        'name'        => 'Phase 9.4 test product',
        'price_minor' => $price,
        'currency'    => 'KWD',
    ]);
}

//
// FINDING #25: refreshFulfillment must re-read items AFTER mass-update,
//              not rely on loadMissing (which is a no-op when relation
//              is already loaded). Without v9.4's fix, partial shipment
//              of a multi-item multi-vendor order leaves the order-level
//              fulfillment_status one transition behind.
//

it('multi-vendor order: partial ship updates aggregate fulfillment correctly (v9.4 stale-read fix)', function () {
    [, $vA] = p94Vendor('p94-mv-A@test');
    [, $vB] = p94Vendor('p94-mv-B@test');

    $order = Order::create([
        'number' => 'P94-MV-001',
        'user_id' => p94Customer('p94-mv-cust@test')->id,
        'status' => 'paid', 'payment_status' => 'paid',
        'fulfillment_status' => 'unfulfilled',
        'currency' => 'KWD',
        'subtotal_minor' => 100000, 'shipping_minor' => 0, 'tax_minor' => 0,
        'discount_minor' => 0, 'total_minor' => 100000,
    ]);
    OrderItem::factory()->create([
        'order_id' => $order->id, 'vendor_id' => $vA->id,
        'product_name' => 'A', 'quantity' => 1,
        'unit_price_minor' => 60000, 'line_total_minor' => 60000,
        'currency' => 'KWD', 'fulfillment_status' => 'unfulfilled',
    ]);
    OrderItem::factory()->create([
        'order_id' => $order->id, 'vendor_id' => $vB->id,
        'product_name' => 'B', 'quantity' => 1,
        'unit_price_minor' => 40000, 'line_total_minor' => 40000,
        'currency' => 'KWD', 'fulfillment_status' => 'unfulfilled',
    ]);

    $svc = app(OrderLifecycleService::class);

    // Vendor A ships their item only — order should be PARTIAL, not FULFILLED.
    $svc->markShipped($order->fresh(), $vA->id);
    expect($order->fresh()->fulfillment_status)->toBe(Order::FUL_PARTIAL);

    // Now vendor B ships — order should be FULFILLED.
    $svc->markShipped($order->fresh(), $vB->id);
    expect($order->fresh()->fulfillment_status)->toBe(Order::FUL_FULFILLED);
});

//
// FINDING #22: ILIKE is PostgreSQL-only. Codex was right — line 54 of
//              CatalogController used it; MySQL would have thrown
//              "Unknown operator". v9.4 uses LOWER(name) LIKE LOWER(?)
//              which is portable.
//

it('catalog search uses portable case-insensitive LIKE, not PostgreSQL-only ILIKE', function () {
    // Static-source assertion — the offending line was:
    //   $query->where('name', 'ILIKE', '%' . str_replace('%', '\\%', $q) . '%');
    // The fix replaces it with whereRaw('LOWER(name) LIKE ?', [...]).
    $src = file_get_contents(app_path('Http/Controllers/CatalogController.php'));
    expect($src)->not->toContain("'ILIKE'");
    expect($src)->toContain('LOWER(name) LIKE');
});

it('catalog search runs without DB error against the configured driver', function () {
    [, $v] = p94Vendor('p94-cat-vendor@test');
    p94Product($v, 'p94-blue-shirt');
    p94Product($v, 'p94-red-shirt');

    // GET /products?q=shirt MUST execute without a SQL error on any driver
    $resp = $this->get('/products?q=shirt');
    $resp->assertOk();
});

//
// FINDING #17: PaymentMethodsSeeder $this->command can be null when
//              invoked outside artisan db:seed (eg. directly from a test
//              or a service-provider boot). v9.4 makes every command
//              output call null-safe.
//

it('PaymentMethodsSeeder runs cleanly when invoked without a console command (null $this->command)', function () {
    // The `$this->seed()` test helper does NOT set $this->command on
    // the seeder (it's only set when run via artisan db:seed). Before
    // v9.4, the final ->info() call crashed with "Call to a member
    // function info() on null". Now it's `?->info` and the seeder
    // completes silently.
    expect(fn () => $this->seed(\Database\Seeders\PaymentMethodsSeeder::class))
        ->not->toThrow(Error::class);

    // Verify the seeder did its job
    expect(\App\Models\PaymentMethod::count())->toBeGreaterThan(0);
});
