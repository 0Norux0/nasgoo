<?php

declare(strict_types=1);

/**
 * Phase 7 v7.6 — Lazy-load regression tests for the checkout / order flow.
 *
 * Bug history (developer reports):
 *   v7.5: checkout crashed with `Attempted to lazy load [customizations] on
 *   model [App\Models\OrderItem] but lazy loading is disabled.` after a
 *   successful payment redirect to /orders/{id}/confirm. Root cause:
 *   OrderController::confirm eager-loaded 'items' but not 'items.customizations'
 *   or 'items.latestProof', yet the present() helper iterates both. This is
 *   the same present() helper used by OrderController::show, which DID
 *   eager-load them — only confirm was missing.
 *
 * v7.6 fixes (4 sites, defense-in-depth):
 *   1. OrderController::confirm — added items.customizations + items.latestProof
 *   2. CheckoutService::place return - now eager-loads them on the returned order
 *   3. DropshipOrderCreator::createFromOrder - includes customizations in loadMissing
 *   4. Filament Admin OrderResource::getEloquentQuery - adds the same relations
 *
 * These scenarios assert each site is safe under Model::shouldBeStrict().
 */

use App\Models\User;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemCustomization;
use App\Models\CustomizationProof;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Strict mode is the project-wide default in non-prod (see
    // AppServiceProvider). Re-enable here in case a prior test relaxed it.
    Model::shouldBeStrict(true);
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
});

afterEach(function () {
    Model::shouldBeStrict(false);
});

/**
 * Build the minimum data graph for a customized-order test: a customer + a
 * vendor + a customizable Product + an Order with one OrderItem that has
 * customizations + a SENT proof. Returns the order id.
 */
function makeCustomizedOrder(): array {
    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $vendorUser = User::factory()->create();
    $vendorUser->assignRole('vendor');
    $vendor = Vendor::factory()->create(['user_id' => $vendorUser->id, 'status' => 'approved']);

    $product = Product::factory()->create([
        'vendor_id' => $vendor->id,
        'type'      => Product::TYPE_CUSTOM,
        'price_minor' => 1500,
    ]);

    $order = Order::factory()->create([
        'user_id' => $customer->id, 'currency' => 'KWD',
        'status' => 'placed', 'payment_status' => 'paid',
    ]);

    $item = OrderItem::factory()->create([
        'order_id'  => $order->id, 'vendor_id' => $vendor->id, 'product_id' => $product->id,
        'product_name' => $product->name, 'product_sku' => $product->sku ?? 'TEST-001',
        'quantity'  => 1, 'unit_price_minor' => 1500, 'line_total_minor' => 1750,
        'currency'  => 'KWD',
        'customization_fee_minor' => 250,
        'customization_status'    => OrderItem::CUST_PENDING,
    ]);

    OrderItemCustomization::create([
        'order_item_id' => $item->id,
        'field_key' => 'photo', 'field_label' => 'Your photo', 'field_type' => 'image',
        'value' => null, 'file_path' => 'customizations/x/y.png',
        'file_original_name' => 'y.png', 'file_mime' => 'image/png',
        'file_size_bytes' => 100, 'extra_fee_minor' => 0,
    ]);
    OrderItemCustomization::create([
        'order_item_id' => $item->id,
        'field_key' => 'text', 'field_label' => 'Text', 'field_type' => 'text',
        'value' => 'Hello', 'extra_fee_minor' => 250,
    ]);

    CustomizationProof::create([
        'order_item_id' => $item->id, 'vendor_id' => $vendor->id,
        'file_path' => 'customization-proofs/1/1/proof.png',
        'file_original_name' => 'proof.png', 'file_mime' => 'image/png',
        'file_size_bytes' => 100,
        'status' => CustomizationProof::STATUS_SENT,
        'sent_at' => now(),
    ]);

    return ['customer' => $customer, 'order' => $order, 'item' => $item, 'vendor' => $vendor];
}

/* ─────────────────────────────────────────────
   1. The direct v7.5 → v7.6 bug repro
   ───────────────────────────────────────────── */

it('Phase 7 v7.6: /orders/{id}/confirm renders without lazy-load error for a customized order', function () {
    $ctx = makeCustomizedOrder();

    $response = $this->actingAs($ctx['customer'])->get("/orders/{$ctx['order']->id}/confirm");

    // v7.5 produced HTTP 500 with `Attempted to lazy load [customizations]`.
    // v7.6 must render cleanly.
    expect($response->status())->toBeLessThan(500);
    $response->assertOk();
});

it('Phase 7 v7.6: /orders/{id} (show) still renders without lazy-load error', function () {
    $ctx = makeCustomizedOrder();

    $response = $this->actingAs($ctx['customer'])->get("/orders/{$ctx['order']->id}");

    expect($response->status())->toBeLessThan(500);
    $response->assertOk();
});

/* ─────────────────────────────────────────────
   2. Defense-in-depth at each fix site
   ───────────────────────────────────────────── */

it('Phase 7 v7.6: OrderController::confirm eager-loads items.customizations + items.latestProof (static)', function () {
    $src = file_get_contents(app_path('Http/Controllers/OrderController.php'));

    // Isolate the confirm method
    preg_match('/public function confirm\(.*?\n    \}/s', $src, $m);
    $confirmBody = $m[0] ?? '';

    expect($confirmBody)->toContain("'items.customizations'");
    expect($confirmBody)->toContain("'items.latestProof'");
});

it('Phase 7 v7.6: CheckoutService::place returned order has customizations + latestProof eager-loaded (static)', function () {
    $src = file_get_contents(app_path('Domain/Order/CheckoutService.php'));
    expect($src)->toMatch("/\\\$order->fresh\\(\\[[^\\]]*'items\\.customizations'/s");
    expect($src)->toMatch("/\\\$order->fresh\\(\\[[^\\]]*'items\\.latestProof'/s");
});

it('Phase 7 v7.6: DropshipOrderCreator::createFromOrder loads items.customizations (static)', function () {
    $src = file_get_contents(app_path('Domain/Supplier/DropshipOrderCreator.php'));
    expect($src)->toMatch("/loadMissing\\(\\[[^\\]]*'items\\.customizations'/s");
});

it('Phase 7 v7.6: Filament OrderResource::getEloquentQuery includes items.customizations + items.latestProof (static)', function () {
    $src = file_get_contents(app_path('Filament/Resources/OrderResource.php'));
    expect($src)->toContain("'items.customizations'");
    expect($src)->toContain("'items.latestProof'");
});

/* ─────────────────────────────────────────────
   3. Touch each relation on a real order without
   manually loading — proves eager-load works
   ───────────────────────────────────────────── */

it('Phase 7 v7.6: simulating present() under strict mode does not throw for a freshly-fetched order with eager-load', function () {
    $ctx = makeCustomizedOrder();

    // Mirror OrderController::confirm's fetch + present iteration
    $o = Order::with([
        'items', 'items.customizations', 'items.latestProof',
        'addresses', 'shippingAddress',
        'events.actor:id,name', 'payments',
    ])->findOrFail($ctx['order']->id);

    // Iterate the way present() does. If any relation lazy-loads, this throws.
    $payload = $o->items->map(fn ($i) => [
        'customizations' => $i->customizations->map(fn ($c) => $c->field_key)->all(),
        'latest_proof'   => $i->latestProof?->status,
    ])->all();

    expect($payload)->toHaveCount(1);
    expect($payload[0]['customizations'])->toBe(['photo', 'text']);
    expect($payload[0]['latest_proof'])->toBe(CustomizationProof::STATUS_SENT);
});

it('Phase 7 v7.6: simulating present() WITHOUT the v7.6 eager-load DOES throw under strict mode (proves the test catches the bug)', function () {
    $ctx = makeCustomizedOrder();

    // The v7.5 (buggy) eager-load list — missing customizations + latestProof
    $o = Order::with([
        'items', 'addresses', 'shippingAddress',
        'events.actor:id,name', 'payments',
    ])->findOrFail($ctx['order']->id);

    expect(fn () => $o->items->first()->customizations->all())
        ->toThrow(\Illuminate\Database\LazyLoadingViolationException::class);
});
