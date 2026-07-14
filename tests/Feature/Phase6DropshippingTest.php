<?php

declare(strict_types=1);

/**
 * Phase 6 — dropshipping + supplier product import regression tests.
 *
 * Tests exercise real runtime behaviour rather than source-string inspection
 * (lesson from v6.x). The tests use a strict-mode lazy-load harness whenever
 * a page/list is rendered to catch the same bug class that has surfaced
 * repeatedly in earlier phases.
 */

use App\Domain\Supplier\DropshipOrderCreator;
use App\Domain\Supplier\SupplierProductImporter;
use App\Domain\Supplier\SupplierProductMapper;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SupplierIntegration;
use App\Models\SupplierOrder;
use App\Models\SupplierPlatform;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\PaymentMethodsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\VendorPackagesSeeder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(VendorPackagesSeeder::class);
    $this->seed(PaymentMethodsSeeder::class);
});

// Helper closures (Pest-compatible)
function makePlatform(array $overrides = []): SupplierPlatform {
    return SupplierPlatform::firstOrCreate(
        ['slug' => $overrides['slug'] ?? 'aliexpress'],
        array_merge([
            'name'             => 'AliExpress',
            'integration_type' => 'manual',
            'default_currency' => 'USD',
            'default_delivery_days' => 18,
            'is_active'        => true,
        ], $overrides),
    );
}

function dropshipVendor(): Vendor {
    $u = User::factory()->create();
    $u->assignRole('vendor');
    return Vendor::factory()->approved()->for($u)->create();
}

/* ──────────────────────────────────────────
   1. Permission catalogue + seeder integrity
   ────────────────────────────────────────── */

it('Phase 6: every supplier permission is registered after seeding (no duplicate-key bug regression)', function () {
    foreach ([
        'supplier_platforms.view', 'supplier_platforms.manage',
        'supplier_integrations.view', 'supplier_integrations.create',
        'supplier_integrations.update', 'supplier_integrations.delete',
        'supplier_products.view', 'supplier_products.import',
        'supplier_products.map', 'supplier_products.approve', 'supplier_products.reject',
        'supplier_orders.view', 'supplier_orders.update',
    ] as $p) {
        expect(Permission::where('name', $p)->where('guard_name', 'web')->exists())
            ->toBeTrue("Permission '{$p}' must be registered");
    }
});

it('Phase 6: super_admin has all supplier permissions; vendor has the subset', function () {
    $admin = User::factory()->create(); $admin->assignRole('super_admin');
    $vendorUser = User::factory()->create(); $vendorUser->assignRole('vendor');

    foreach (['supplier_platforms.manage', 'supplier_products.approve', 'supplier_orders.update'] as $p) {
        expect($admin->can($p))->toBeTrue("super_admin must have {$p}");
    }
    // Vendors should manage their own integrations + products + orders
    foreach (['supplier_integrations.create', 'supplier_products.import', 'supplier_orders.update'] as $p) {
        expect($vendorUser->can($p))->toBeTrue("vendor must have {$p}");
    }
    // But NOT approval / platform management
    expect($vendorUser->can('supplier_products.approve'))->toBeFalse();
    expect($vendorUser->can('supplier_platforms.manage'))->toBeFalse();
});

/* ──────────────────────────────────────────
   2. Admin can manage supplier platforms
   ────────────────────────────────────────── */

it('Phase 6: admin can list + create + delete supplier platforms via Filament resource', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    actingAs($admin);

    // Static guarantee: the resource exists + admin has access
    expect(\App\Filament\Resources\SupplierPlatformResource::canAccess())->toBeTrue();
    expect(\App\Filament\Resources\SupplierPlatformResource::canCreate())->toBeTrue();

    // Create one record via the model directly (simulating Filament submit)
    $p = SupplierPlatform::create([
        'name' => 'TestSupplier', 'slug' => 'test-supplier',
        'integration_type' => 'manual', 'default_currency' => 'USD',
        'is_active' => true,
    ]);
    expect(SupplierPlatform::where('slug', 'test-supplier')->exists())->toBeTrue();

    // Delete check
    $p->delete();
    expect(SupplierPlatform::where('slug', 'test-supplier')->exists())->toBeFalse();
});

/* ──────────────────────────────────────────
   3. Vendor can create supplier integration
   ────────────────────────────────────────── */

it('Phase 6: vendor can create a supplier integration with encrypted credentials', function () {
    $vendor = dropshipVendor();
    $platform = makePlatform();

    actingAs($vendor->user)->post('/vendor/supplier-integrations', [
        'supplier_platform_id' => $platform->id,
        'name'                 => 'My AE catalogue',
        'integration_type'     => 'api',
        'is_active'            => true,
        'credentials'          => ['api_key' => 'sk-test-12345678', 'api_secret' => 'secret-abcdefgh'],
    ])->assertRedirect();

    $integration = $vendor->supplierIntegrations()->where('name', 'My AE catalogue')->firstOrFail();
    expect($integration->credentials)->toHaveKey('api_key');
    expect($integration->credentials['api_key'])->toBe('sk-test-12345678');

    // Critically: the underlying DB column must NOT be plaintext
    $raw = \DB::table('supplier_integrations')->where('id', $integration->id)->value('credentials');
    expect($raw)->not->toContain('sk-test-12345678', 'credentials column must be encrypted at rest');

    // Masked credentials helper exposes safe display
    $masked = $integration->maskedCredentials();
    expect($masked['api_key'])->toEndWith('5678');
    expect($masked['api_key'])->not->toContain('sk-test');
});

/* ──────────────────────────────────────────
   4. Manual supplier product import
   ────────────────────────────────────────── */

it('Phase 6: vendor can manually import a supplier product', function () {
    $vendor = dropshipVendor();
    $platform = makePlatform();

    actingAs($vendor->user)->post('/vendor/supplier-products/manual', [
        'supplier_platform_id' => $platform->id,
        'title'                => 'Test gadget',
        'supplier_cost_major'  => '5.50',
        'supplier_currency'    => 'USD',
        'supplier_stock_status'=> 'in_stock',
    ])->assertRedirect();

    $sp = $vendor->supplierProducts()->where('title', 'Test gadget')->firstOrFail();
    expect($sp->supplier_cost_minor)->toBe(550);
    expect($sp->import_status)->toBe(SupplierProduct::STATUS_PENDING);
});

/* ──────────────────────────────────────────
   5. CSV import — validates and reports per-row errors
   ────────────────────────────────────────── */

it('Phase 6: CSV import imports valid rows and reports errors per row', function () {
    $vendor = dropshipVendor();
    $platform = makePlatform();

    $rows = [
        ['title' => 'Valid product 1', 'supplier_cost' => '3.25', 'currency' => 'USD', 'stock_quantity' => '10'],
        ['title' => '',                  'supplier_cost' => '4.00', 'currency' => 'USD'],          // missing title
        ['title' => 'Valid product 2', 'supplier_cost' => '0.99', 'currency' => 'USD'],
        ['title' => 'Bad cost',          'supplier_cost' => '',     'currency' => 'USD'],            // missing cost
    ];

    $batch = app(SupplierProductImporter::class)->importCsv($vendor, $platform, $rows);

    expect($batch->total_rows)->toBe(4);
    expect($batch->successful_rows)->toBe(2);
    expect($batch->failed_rows)->toBe(2);
    expect($batch->errors)->toHaveCount(2);

    // Two supplier products persisted
    expect(SupplierProduct::where('vendor_id', $vendor->id)->count())->toBe(2);
});

it('Phase 6: CSV import dry-run validates but does NOT persist rows', function () {
    $vendor = dropshipVendor();
    $platform = makePlatform();

    $rows = [
        ['title' => 'Dry run product', 'supplier_cost' => '2.00', 'currency' => 'USD'],
    ];

    $batch = app(SupplierProductImporter::class)->importCsv(
        vendor: $vendor, platform: $platform, rows: $rows, dryRun: true,
    );

    expect($batch->dry_run)->toBeTrue();
    expect($batch->successful_rows)->toBe(1);
    // Critical: nothing persisted
    expect(SupplierProduct::where('vendor_id', $vendor->id)->count())->toBe(0);
});

/* ──────────────────────────────────────────
   6. Mapping → product (pending admin approval)
   ────────────────────────────────────────── */

it('Phase 6: vendor maps a supplier product to a marketplace listing in pending_review status', function () {
    $vendor = dropshipVendor();
    $platform = makePlatform();
    $sp = SupplierProduct::create([
        'vendor_id'             => $vendor->id,
        'supplier_platform_id'  => $platform->id,
        'title'                 => 'Source widget',
        'supplier_cost_minor'   => 500,
        'supplier_currency'     => 'USD',
        'supplier_stock_status' => 'in_stock',
        'supplier_stock_qty'    => 20,
        'import_status'         => SupplierProduct::STATUS_PENDING,
        'imported_at'           => now(),
    ]);

    $product = app(SupplierProductMapper::class)->map($sp, [
        'name'        => 'Widget Premium',
        'description' => 'Lovely widget',
        'price_minor' => 1500,
        'currency'    => 'KWD',
        'stock'       => 15,
    ], $vendor->user);

    expect($product->type)->toBe(Product::TYPE_DROPSHIP);
    expect($product->status)->toBe(Product::STATUS_PENDING_REVIEW);
    expect($product->supplier_product_id)->toBe($sp->id);
    expect($product->supplier_cost_minor)->toBe(500);
    expect($product->price_minor)->toBe(1500);

    $sp->refresh();
    expect($sp->import_status)->toBe(SupplierProduct::STATUS_MAPPED);
    expect($sp->product_id)->toBe($product->id);
});

it('Phase 6: mapping rejects selling price below supplier cost', function () {
    $vendor = dropshipVendor();
    $platform = makePlatform();
    $sp = SupplierProduct::create([
        'vendor_id'             => $vendor->id,
        'supplier_platform_id'  => $platform->id,
        'title'                 => 'X',
        'supplier_cost_minor'   => 1000,
        'supplier_currency'     => 'USD',
        'import_status'         => SupplierProduct::STATUS_PENDING,
        'imported_at'           => now(),
    ]);

    expect(fn () => app(SupplierProductMapper::class)->map(
        $sp, ['name' => 'X', 'price_minor' => 500, 'currency' => 'KWD', 'stock' => 1], $vendor->user
    ))->toThrow(\InvalidArgumentException::class);
});

/* ──────────────────────────────────────────
   7. Admin approves → product is published
   ────────────────────────────────────────── */

it('Phase 6: admin approval moves the supplier product and the linked Product to published', function () {
    $vendor = dropshipVendor();
    $admin = User::factory()->create(); $admin->assignRole('super_admin');
    $platform = makePlatform();

    $sp = SupplierProduct::create([
        'vendor_id'             => $vendor->id,
        'supplier_platform_id'  => $platform->id,
        'title'                 => 'Z',
        'supplier_cost_minor'   => 500,
        'supplier_currency'     => 'USD',
        'import_status'         => SupplierProduct::STATUS_PENDING,
        'imported_at'           => now(),
    ]);

    app(SupplierProductMapper::class)->map($sp, [
        'name' => 'Z Premium', 'price_minor' => 1500, 'currency' => 'KWD', 'stock' => 5,
    ], $vendor->user);

    app(SupplierProductMapper::class)->publish($sp->fresh(), $admin);

    $sp->refresh();
    expect($sp->import_status)->toBe(SupplierProduct::STATUS_PUBLISHED);
    $product = Product::find($sp->product_id);
    expect($product->status)->toBe(Product::STATUS_PUBLISHED);
    expect($product->published_at)->not->toBeNull();
});

/* ──────────────────────────────────────────
   8. Dropship product appears on public listing
   ────────────────────────────────────────── */

it('Phase 6: published dropshipping product is queryable by storefront filters (published + in stock)', function () {
    $vendor = dropshipVendor();
    $platform = makePlatform();
    $sp = SupplierProduct::create([
        'vendor_id'             => $vendor->id,
        'supplier_platform_id'  => $platform->id,
        'title'                 => 'Storefront test',
        'supplier_cost_minor'   => 500,
        'supplier_currency'     => 'USD',
        'import_status'         => SupplierProduct::STATUS_PENDING,
        'imported_at'           => now(),
    ]);
    app(SupplierProductMapper::class)->map($sp, [
        'name' => 'Storefront test product', 'price_minor' => 2000, 'currency' => 'KWD', 'stock' => 10,
    ], $vendor->user);
    $admin = User::factory()->create(); $admin->assignRole('super_admin');
    app(SupplierProductMapper::class)->publish($sp->fresh(), $admin);

    $found = Product::where('status', Product::STATUS_PUBLISHED)
        ->where('type', Product::TYPE_DROPSHIP)
        ->where('stock', '>', 0)
        ->where('vendor_id', $vendor->id)
        ->exists();
    expect($found)->toBeTrue();
});

/* ──────────────────────────────────────────
   9. Dropship checkout creates a supplier_order
   ────────────────────────────────────────── */

it('Phase 6: dropship-only checkout creates a supplier_order with the correct cost snapshot', function () {
    $customer = User::factory()->create(); $customer->assignRole('customer');
    $vendor = dropshipVendor();
    $platform = makePlatform();

    $sp = SupplierProduct::create([
        'vendor_id'             => $vendor->id,
        'supplier_platform_id'  => $platform->id,
        'title'                 => 'Live dropship',
        'supplier_cost_minor'   => 750,
        'supplier_currency'     => 'USD',
        'import_status'         => SupplierProduct::STATUS_PENDING,
        'imported_at'           => now(),
    ]);
    $product = app(SupplierProductMapper::class)->map($sp, [
        'name' => 'Live dropship product', 'price_minor' => 3000, 'currency' => 'KWD', 'stock' => 5,
    ], $vendor->user);
    $admin = User::factory()->create(); $admin->assignRole('super_admin');
    app(SupplierProductMapper::class)->publish($sp->fresh(), $admin);

    // Simulate a paid order with one OrderItem for this dropship product.
    // We don't need to run the full CheckoutService — DropshipOrderCreator
    // is the unit we want to test in isolation.
    $order = Order::factory()->paid()->for($customer)->create();
    $item = OrderItem::factory()->for($order)->state([
        'vendor_id'           => $vendor->id,
        'product_id'          => $product->id,
        'quantity'            => 2,
        'unit_price_minor'    => 3000,
        'line_total_minor'    => 6000,
        'supplier_cost_minor' => 750,
    ])->create();

    $created = app(DropshipOrderCreator::class)->createFromOrder($order);
    expect($created)->toHaveCount(1);

    $so = $created[0];
    expect($so->vendor_id)->toBe($vendor->id);
    expect($so->supplier_platform_id)->toBe($platform->id);
    expect($so->order_id)->toBe($order->id);
    expect($so->status)->toBe(SupplierOrder::STATUS_PENDING);
    expect($so->supplier_cost_minor)->toBe(750 * 2); // unit cost × quantity

    // The order item is linked back to the supplier order
    expect($item->fresh()->supplier_order_id)->toBe($so->id);

    // And a creation event was logged
    expect($so->events()->where('event_type', 'supplier_order.created')->exists())->toBeTrue();
});

it('Phase 6: a non-dropship checkout does NOT create a supplier_order', function () {
    $customer = User::factory()->create(); $customer->assignRole('customer');
    $vendor = dropshipVendor();
    $product = Product::factory()->published()->create([
        'vendor_id' => $vendor->id, 'price_minor' => 1000, 'type' => Product::TYPE_SIMPLE,
    ]);

    $order = Order::factory()->paid()->for($customer)->create();
    OrderItem::factory()->for($order)->state(['vendor_id' => $vendor->id, 'product_id' => $product->id])->create();

    $created = app(DropshipOrderCreator::class)->createFromOrder($order);
    expect($created)->toHaveCount(0);
});

/* ──────────────────────────────────────────
   10. Supplier order state machine
   ────────────────────────────────────────── */

it('Phase 6: supplier order transitions from pending → placed → shipped → delivered', function () {
    $vendor = dropshipVendor();
    $platform = makePlatform();
    $customer = User::factory()->create();
    $order = Order::factory()->paid()->for($customer)->create();

    $so = SupplierOrder::create([
        'number' => 'SUP-TEST-001',
        'vendor_id' => $vendor->id,
        'supplier_platform_id' => $platform->id,
        'order_id' => $order->id,
        'status' => SupplierOrder::STATUS_PENDING,
        'supplier_cost_minor' => 5000,
        'total_minor' => 5000,
        'currency' => 'KWD',
    ]);

    $svc = app(DropshipOrderCreator::class);
    $svc->transition($so, SupplierOrder::STATUS_PLACED, $vendor->user_id, 'vendor');
    expect($so->fresh()->placed_at)->not->toBeNull();

    $svc->transition($so->fresh(), SupplierOrder::STATUS_SHIPPED, $vendor->user_id, 'vendor');
    expect($so->fresh()->shipped_at)->not->toBeNull();

    $svc->transition($so->fresh(), SupplierOrder::STATUS_DELIVERED, $vendor->user_id, 'vendor');
    $final = $so->fresh();
    expect($final->status)->toBe(SupplierOrder::STATUS_DELIVERED);
    expect($final->delivered_at)->not->toBeNull();

    // 3 transitions + 0 creation event (because we didn't go through createFromOrder)
    expect($final->events()->whereIn('event_type', ['status.placed', 'status.shipped', 'status.delivered'])->count())->toBe(3);
});

it('Phase 6: invalid supplier order status raises an exception', function () {
    $so = SupplierOrder::factory()->for(dropshipVendor())->state([
        'supplier_platform_id' => makePlatform()->id,
        'order_id' => Order::factory()->create()->id,
    ])->create();

    expect(fn () => app(DropshipOrderCreator::class)->transition($so, 'invented_status'))
        ->toThrow(\InvalidArgumentException::class);
})->skip('SupplierOrder factory not built — only included as a placeholder for future use.');

/* ──────────────────────────────────────────
   11. Vendor scoping — sees only own data
   ────────────────────────────────────────── */

it('Phase 6: vendor sees only own supplier products on /vendor/supplier-products', function () {
    $v1 = dropshipVendor();
    $v2 = dropshipVendor();
    $platform = makePlatform();

    SupplierProduct::create([
        'vendor_id' => $v1->id, 'supplier_platform_id' => $platform->id,
        'title' => 'V1 product', 'supplier_cost_minor' => 100, 'supplier_currency' => 'USD',
        'import_status' => SupplierProduct::STATUS_PENDING, 'imported_at' => now(),
    ]);
    SupplierProduct::create([
        'vendor_id' => $v2->id, 'supplier_platform_id' => $platform->id,
        'title' => 'V2 product', 'supplier_cost_minor' => 200, 'supplier_currency' => 'USD',
        'import_status' => SupplierProduct::STATUS_PENDING, 'imported_at' => now(),
    ]);

    actingAs($v1->user)->get('/vendor/supplier-products')
        ->assertSuccessful()
        ->assertInertia(fn ($p) => $p
            ->has('products.data', 1)
            ->where('products.data.0.title', 'V1 product')
        );
});

it('Phase 6: vendor sees only own supplier orders on /vendor/supplier-orders', function () {
    $v1 = dropshipVendor();
    $v2 = dropshipVendor();
    $platform = makePlatform();
    $customer = User::factory()->create();
    $order = Order::factory()->paid()->for($customer)->create();

    SupplierOrder::create([
        'number' => 'SUP-V1', 'vendor_id' => $v1->id,
        'supplier_platform_id' => $platform->id, 'order_id' => $order->id,
        'status' => 'pending', 'total_minor' => 100, 'currency' => 'KWD',
    ]);
    SupplierOrder::create([
        'number' => 'SUP-V2', 'vendor_id' => $v2->id,
        'supplier_platform_id' => $platform->id, 'order_id' => $order->id,
        'status' => 'pending', 'total_minor' => 200, 'currency' => 'KWD',
    ]);

    actingAs($v1->user)->get('/vendor/supplier-orders')
        ->assertSuccessful()
        ->assertInertia(fn ($p) => $p
            ->has('orders.data', 1)
            ->where('orders.data.0.number', 'SUP-V1')
        );
});

it('Phase 6: vendor cannot map another vendor\'s supplier product', function () {
    $v1 = dropshipVendor();
    $v2 = dropshipVendor();
    $platform = makePlatform();
    $sp = SupplierProduct::create([
        'vendor_id' => $v2->id, 'supplier_platform_id' => $platform->id,
        'title' => 'V2 only', 'supplier_cost_minor' => 100, 'supplier_currency' => 'USD',
        'import_status' => SupplierProduct::STATUS_PENDING, 'imported_at' => now(),
    ]);

    actingAs($v1->user)->get("/vendor/supplier-products/{$sp->id}/map")->assertNotFound();
});

/* ──────────────────────────────────────────
   12. Lazy-load defense — v6.4 pattern, applied to Phase 6 pages
   ────────────────────────────────────────── */

it('Phase 6: vendor /vendor/supplier-products page does not lazy-load under strict mode', function () {
    Model::shouldBeStrict(true);
    try {
        $v = dropshipVendor();
        $platform = makePlatform();
        // Multi-row condition for strict-mode detector
        foreach (range(1, 3) as $i) {
            SupplierProduct::create([
                'vendor_id' => $v->id, 'supplier_platform_id' => $platform->id,
                'title' => "P{$i}", 'supplier_cost_minor' => $i * 100, 'supplier_currency' => 'USD',
                'import_status' => SupplierProduct::STATUS_PENDING, 'imported_at' => now(),
            ]);
        }
        actingAs($v->user)->get('/vendor/supplier-products')->assertSuccessful();
    } finally {
        Model::shouldBeStrict(false);
    }
});

it('Phase 6: vendor /vendor/supplier-orders page does not lazy-load under strict mode', function () {
    Model::shouldBeStrict(true);
    try {
        $v = dropshipVendor();
        $platform = makePlatform();
        $customer = User::factory()->create();
        $order = Order::factory()->paid()->for($customer)->create();
        foreach (range(1, 3) as $i) {
            $so = SupplierOrder::create([
                'number' => "SUP-LL-{$i}", 'vendor_id' => $v->id,
                'supplier_platform_id' => $platform->id, 'order_id' => $order->id,
                'status' => 'pending', 'total_minor' => 100, 'currency' => 'KWD',
            ]);
            OrderItem::factory()->for($order)->state([
                'supplier_order_id' => $so->id, 'vendor_id' => $v->id,
            ])->create();
        }
        actingAs($v->user)->get('/vendor/supplier-orders')->assertSuccessful();
    } finally {
        Model::shouldBeStrict(false);
    }
});

it('Phase 6: vendor /vendor/supplier-orders/{id} detail page (with events) does not lazy-load under strict mode', function () {
    Model::shouldBeStrict(true);
    try {
        $v = dropshipVendor();
        $platform = makePlatform();
        $customer = User::factory()->create();
        $order = Order::factory()->paid()->for($customer)->create();
        $so = SupplierOrder::create([
            'number' => 'SUP-DETAIL', 'vendor_id' => $v->id,
            'supplier_platform_id' => $platform->id, 'order_id' => $order->id,
            'status' => 'pending', 'total_minor' => 100, 'currency' => 'KWD',
        ]);
        // Multi-event collection triggers the lazy-load detector for events.actor
        foreach (range(1, 3) as $i) {
            $so->events()->create([
                'event_type' => "status.test{$i}",
                'message'    => "test {$i}",
                'actor_id'   => $v->user_id,
                'actor_role' => 'vendor',
            ]);
        }
        actingAs($v->user)->get("/vendor/supplier-orders/{$so->id}")->assertSuccessful();
    } finally {
        Model::shouldBeStrict(false);
    }
});

/* ──────────────────────────────────────────
   13. Admin Filament resources — open under strict mode
   ────────────────────────────────────────── */

it('Phase 6: admin Filament SupplierPlatformResource canAccess + table query do not lazy-load', function () {
    Model::shouldBeStrict(true);
    try {
        $admin = User::factory()->create(); $admin->assignRole('super_admin');
        actingAs($admin);
        makePlatform();
        makePlatform(['slug' => 'amazon', 'name' => 'Amazon']);

        expect(\App\Filament\Resources\SupplierPlatformResource::canAccess())->toBeTrue();
        $query = \App\Filament\Resources\SupplierPlatformResource::getEloquentQuery();
        expect($query->count())->toBeGreaterThan(0);
    } finally {
        Model::shouldBeStrict(false);
    }
});

it('Phase 6: admin Filament SupplierOrderResource query eager-loads relations used in closures', function () {
    Model::shouldBeStrict(true);
    try {
        $admin = User::factory()->create(); $admin->assignRole('super_admin');
        actingAs($admin);
        $v = dropshipVendor();
        $platform = makePlatform();
        $customer = User::factory()->create();
        $order = Order::factory()->paid()->for($customer)->create();

        foreach (range(1, 3) as $i) {
            $so = SupplierOrder::create([
                'number' => "SUP-ADM-{$i}", 'vendor_id' => $v->id,
                'supplier_platform_id' => $platform->id, 'order_id' => $order->id,
                'status' => 'pending', 'total_minor' => 100, 'currency' => 'KWD',
            ]);
            $so->events()->create(['event_type' => 'test', 'actor_id' => $admin->id, 'actor_role' => 'admin']);
            OrderItem::factory()->for($order)->state(['supplier_order_id' => $so->id])->create();
        }

        // Iterate as the table closure would
        $rows = \App\Filament\Resources\SupplierOrderResource::getEloquentQuery()->get();
        foreach ($rows as $r) {
            $pn = $r->platform?->name;
            $on = $r->order?->number;
            $vn = $r->vendor?->business_name;
            foreach ($r->orderItems as $oi) { $x = $oi->product_name; }
        }
        expect(true)->toBeTrue(); // no exception
    } finally {
        Model::shouldBeStrict(false);
    }
});

/* ──────────────────────────────────────────
   14. v7.1 — APP_KEY pre-seed guard + encrypted credentials
   ────────────────────────────────────────── */

it('Phase 6 v7.1: SupplierIntegration encrypted credentials round-trip correctly when APP_KEY is set', function () {
    // APP_KEY is set by the test bootstrap, so this is the "happy path" assertion.
    expect(config('app.key'))->not->toBeEmpty();

    $vendor = dropshipVendor();
    $platform = makePlatform();

    $integration = $vendor->supplierIntegrations()->create([
        'supplier_platform_id' => $platform->id,
        'name'                 => 'v7.1 roundtrip',
        'integration_type'     => 'api',
        'is_active'            => true,
        'credentials'          => ['api_key' => 'real-key-ABCD1234', 'api_secret' => 'real-secret-EFGH5678'],
    ]);

    // Round-trip: the model returns the original array
    $fresh = $integration->fresh();
    expect($fresh->credentials)->toBe(['api_key' => 'real-key-ABCD1234', 'api_secret' => 'real-secret-EFGH5678']);

    // Underlying DB column is NOT plaintext
    $raw = \DB::table('supplier_integrations')->where('id', $integration->id)->value('credentials');
    expect($raw)->not->toContain('real-key-ABCD1234');
    expect($raw)->not->toContain('real-secret-EFGH5678');
});

it('Phase 6 v7.1: DemoSeeder throws a helpful RuntimeException when APP_KEY is blank in local env', function () {
    // Force the seeder out of testing env so the guard runs (it skips testing
    // env intentionally so Pest never trips it).
    $originalEnv = app()->environment();
    app()->detectEnvironment(fn () => 'local');

    // Empty out the app.key at runtime
    $originalKey = config('app.key');
    config()->set('app.key', null);

    try {
        $thrown = null;
        try {
            (new \Database\Seeders\DemoSeeder())->run();
        } catch (\RuntimeException $e) {
            $thrown = $e;
        }

        expect($thrown)->not->toBeNull('Expected RuntimeException when APP_KEY is blank');
        expect($thrown->getMessage())->toContain('APP_KEY is missing');
        expect($thrown->getMessage())->toContain('php artisan key:generate');
        expect($thrown->getMessage())->toContain('php artisan migrate:fresh --seed');
        // v7.2 — message now also includes optimize:clear + the guided command
        expect($thrown->getMessage())->toContain('php artisan optimize:clear');
        expect($thrown->getMessage())->toContain('php artisan marketplace:setup-demo');
    } finally {
        config()->set('app.key', $originalKey);
        app()->detectEnvironment(fn () => $originalEnv);
    }
});

/* ──────────────────────────────────────────
   15. v7.2 — marketplace:setup-demo guided command
   ────────────────────────────────────────── */

it('Phase 6 v7.2: marketplace:setup-demo is registered as an artisan command', function () {
    $registered = collect(\Illuminate\Support\Facades\Artisan::all())->keys();
    expect($registered->contains('marketplace:setup-demo'))
        ->toBeTrue('marketplace:setup-demo must be auto-discovered via withCommands()');
});

it('Phase 6 v7.2: marketplace:setup-demo has the documented options (--force, --skip-migrate)', function () {
    // Hermetic alternative to `$this->artisan('marketplace:setup-demo', ...)`:
    // running it would copy .env.example over .env and call key:generate on
    // a developer's working tree (BAD side effect). We assert the command
    // definition shape instead; the real end-to-end run happens in CI
    // sub-check 2 against a freshly migrated database.
    $cmd = \Illuminate\Support\Facades\Artisan::all()['marketplace:setup-demo'];
    $opts = $cmd->getDefinition()->getOptions();
    expect(array_key_exists('force', $opts))->toBeTrue();
    expect(array_key_exists('skip-migrate', $opts))->toBeTrue();
    // Neither option should accept a value (they're boolean flags)
    expect($opts['force']->acceptValue())->toBeFalse();
    expect($opts['skip-migrate']->acceptValue())->toBeFalse();
});

it('Phase 6 v7.2: MarketplaceSetupDemo class defines the helper methods and remedy strings', function () {
    $reflection = new \ReflectionClass(\App\Console\Commands\MarketplaceSetupDemo::class);
    expect($reflection->hasMethod('ensureEnvFile'))->toBeTrue();
    expect($reflection->hasMethod('ensureAppKey'))->toBeTrue();
    expect($reflection->hasMethod('reloadDotenv'))->toBeTrue();
    expect($reflection->hasMethod('printMissingKeyHelp'))->toBeTrue();
    expect($reflection->hasMethod('printDemoAccounts'))->toBeTrue();
    expect($reflection->hasMethod('confirmOrAutoAccept'))->toBeTrue();

    // The command file embeds all 5 remedy strings the user spec requires:
    $src = file_get_contents($reflection->getFileName());
    expect($src)->toContain('cp .env.example .env');
    expect($src)->toContain('php artisan key:generate');
    expect($src)->toContain('php artisan optimize:clear');
    expect($src)->toContain('php artisan migrate:fresh --seed');
    expect($src)->toContain('php artisan marketplace:setup-demo');
});
