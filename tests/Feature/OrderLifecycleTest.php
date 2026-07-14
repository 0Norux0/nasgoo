<?php

declare(strict_types=1);

use App\Domain\Order\OrderLifecycleService;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Vendor;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('markPaid is idempotent and stamps paid_at', function () {
    $order = Order::factory()->pendingPayment()->create();
    $svc = app(OrderLifecycleService::class);

    $svc->markPaid($order);
    $first = $order->fresh();
    expect($first->payment_status)->toBe(Order::PAY_PAID);
    expect($first->paid_at)->not->toBeNull();
    expect($first->status)->toBe(Order::STATUS_PAID);

    $paidAt = $first->paid_at;
    // Second call should not change state
    $svc->markPaid($order);
    expect($order->fresh()->paid_at->equalTo($paidAt))->toBeTrue();
});

it('confirm advances status', function () {
    $order = Order::factory()->paid()->create();
    app(OrderLifecycleService::class)->confirm($order);

    $fresh = $order->fresh();
    expect($fresh->status)->toBe(Order::STATUS_CONFIRMED);
    expect($fresh->confirmed_at)->not->toBeNull();
});

it('markShipped aggregates fulfillment from per-vendor items', function () {
    $order = Order::factory()->paid()->create();
    $vendor1 = Vendor::factory()->approved()->create();
    $vendor2 = Vendor::factory()->approved()->create();
    OrderItem::factory()->for($order)->state(['vendor_id' => $vendor1->id])->create();
    OrderItem::factory()->for($order)->state(['vendor_id' => $vendor2->id])->create();

    $svc = app(OrderLifecycleService::class);

    // Vendor 1 ships their items
    $svc->markShipped($order, $vendor1->id);
    expect($order->fresh()->fulfillment_status)->toBe(Order::FUL_PARTIAL);
    expect($order->fresh()->status)->toBe(Order::STATUS_PAID); // not yet shipped overall

    // Vendor 2 ships theirs — order should now be fully shipped
    $svc->markShipped($order, $vendor2->id);
    expect($order->fresh()->fulfillment_status)->toBe(Order::FUL_FULFILLED);
    expect($order->fresh()->status)->toBe(Order::STATUS_SHIPPED);
    expect($order->fresh()->shipped_at)->not->toBeNull();
});

it('markDelivered sets earnings_release_at to +7 days', function () {
    $order = Order::factory()->shipped()->create();
    app(OrderLifecycleService::class)->markDelivered($order);

    $fresh = $order->fresh();
    expect($fresh->status)->toBe(Order::STATUS_DELIVERED);
    expect($fresh->delivered_at)->not->toBeNull();
    expect($fresh->earnings_release_at)->not->toBeNull();
    expect($fresh->earnings_release_at->diffInDays(now()))->toBe(7);
});

it('cancel restocks the products', function () {
    $product = Product::factory()->published()->create(['stock' => 5, 'track_stock' => true]);
    $order = Order::factory()->paid()->create();
    OrderItem::factory()->for($order)->state(['product_id' => $product->id, 'quantity' => 3])->create();

    app(OrderLifecycleService::class)->cancel($order, 'customer-changed-mind');

    expect($order->fresh()->status)->toBe(Order::STATUS_CANCELLED);
    expect($product->fresh()->stock)->toBe(8);
});

it('refuses to cancel a delivered order', function () {
    $order = Order::factory()->delivered()->create();
    expect(fn () => app(OrderLifecycleService::class)->cancel($order, 'too-late'))
        ->toThrow(RuntimeException::class);
});

it('writes an order_event row for each transition', function () {
    $order = Order::factory()->pendingPayment()->create();
    $svc = app(OrderLifecycleService::class);

    $svc->markPaid($order);
    $svc->confirm($order);

    expect($order->fresh()->events()->count())->toBeGreaterThanOrEqual(2);
    expect($order->fresh()->events()->pluck('event_type')->toArray())
        ->toContain('paid', 'confirmed');
});
