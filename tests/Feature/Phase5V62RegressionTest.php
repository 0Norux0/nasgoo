<?php

declare(strict_types=1);

/**
 * Phase 5 v6.2 — regression tests for the developer-reported v6.1 issues:
 *   1. Admin order EDIT page lifecycle actions (v6.1 added them only to View)
 *   2. Demo vendor has positive available balance after migrate:fresh --seed
 *      (so the payout request form is reachable on the wallet page)
 */

use App\Domain\Order\OrderLifecycleService;
use App\Domain\Payment\PaymentService;
use App\Domain\Payout\VendorWalletService;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPayoutRequest;
use Database\Seeders\PaymentMethodsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\VendorPackagesSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(VendorPackagesSeeder::class);
    $this->seed(PaymentMethodsSeeder::class);
});

/* ───────────────────────────────────────────────────
   1. Admin Edit page has lifecycle header actions
   ─────────────────────────────────────────────────── */

it('v6.2: EditOrder page exposes all 7 lifecycle header actions in its source', function () {
    $reflector = new \ReflectionClass(\App\Filament\Resources\OrderResource\Pages\EditOrder::class);
    $method = $reflector->getMethod('getHeaderActions');
    expect($method->isProtected())->toBeTrue();

    // Body should reference each lifecycle action — assert by source inspection
    $src = file_get_contents($reflector->getFileName());
    foreach (['confirm', 'ship', 'deliver', 'cod_capture', 'capture_transfer', 'cancel', 'refund'] as $action) {
        expect($src)->toContain("Action::make('{$action}')",
            "EditOrder header actions missing '{$action}'");
    }
});

it('v6.2: lifecycle actions on EditOrder match the same OrderLifecycleService entry points', function () {
    $src = file_get_contents((new \ReflectionClass(\App\Filament\Resources\OrderResource\Pages\EditOrder::class))->getFileName());
    expect($src)->toContain('OrderLifecycleService');
    expect($src)->toContain('PaymentService');
    expect($src)->toContain('->confirm($this->record');
    expect($src)->toContain('->markShipped($this->record');
    expect($src)->toContain('->markDelivered($this->record');
    expect($src)->toContain('->cancel($this->record');
});

it('v6.2: marking an order delivered via the lifecycle service writes an order event + audit log', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $order = Order::factory()->for($customer)->paid()->create([
        'status' => Order::STATUS_SHIPPED,
        'shipped_at' => now()->subDay(),
    ]);
    OrderItem::factory()->for($order)->create();

    app(OrderLifecycleService::class)->markDelivered($order, $admin);

    $order->refresh();
    expect($order->status)->toBe(Order::STATUS_DELIVERED);
    expect($order->delivered_at)->not->toBeNull();

    // Order event recorded by the service (event_type='delivered', not 'order.delivered')
    expect($order->events()->where('event_type', 'delivered')->exists())->toBeTrue();

    // Audit log recorded by AuditLogger (action='order.delivered', subject is the Order)
    expect(\App\Models\AuditLog::where('model_type', Order::class)
        ->where('model_id', $order->id)
        ->where('action', 'order.delivered')
        ->exists())->toBeTrue();
});

it('v6.2: marking an order shipped via the lifecycle service flows through correctly', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $order = Order::factory()->for($customer)->paid()->create([
        'status' => Order::STATUS_PAID,
    ]);
    OrderItem::factory()->for($order)->create();

    app(OrderLifecycleService::class)->markShipped($order, null, $admin);

    expect($order->fresh()->status)->toBe(Order::STATUS_SHIPPED);
    expect($order->fresh()->shipped_at)->not->toBeNull();
});

it('v6.2: cancelling an order via the lifecycle service captures the reason', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $order = Order::factory()->for($customer)->create([
        'status' => Order::STATUS_PAID,
        'payment_status' => Order::PAY_PAID,
    ]);
    OrderItem::factory()->for($order)->create();

    app(OrderLifecycleService::class)->cancel($order, 'customer request', $admin);

    $order->refresh();
    expect($order->status)->toBe(Order::STATUS_CANCELLED);
    expect($order->cancelled_at)->not->toBeNull();
    expect($order->cancellation_reason)->toBe('customer request');
});

it('v6.2: ViewOrder and EditOrder header-action sets are identical (no divergence)', function () {
    $editSrc = file_get_contents((new \ReflectionClass(\App\Filament\Resources\OrderResource\Pages\EditOrder::class))->getFileName());
    $viewSrc = file_get_contents((new \ReflectionClass(\App\Filament\Resources\OrderResource\Pages\ViewOrder::class))->getFileName());

    foreach (['confirm', 'ship', 'deliver', 'cod_capture', 'capture_transfer', 'cancel', 'refund'] as $action) {
        $needle = "Action::make('{$action}')";
        expect($editSrc)->toContain($needle, "Edit missing {$action}");
        expect($viewSrc)->toContain($needle, "View missing {$action}");
    }
});

/* ───────────────────────────────────────────────────
   2. Vendor wallet — payout request submission against
      factory-built balance (no DemoSeeder dependency)
   ─────────────────────────────────────────────────── */

it('v6.2: vendor with released earnings sees positive available_minor on the wallet page', function () {
    // Build a vendor with one delivered, paid, past-release order item
    // worth 10 KWD. Same setup the demo seeder produces, just factory-built
    // so we don't need to bypass DemoSeeder's env guard.
    $vendorUser = User::factory()->create();
    $vendorUser->assignRole('vendor');
    $vendor = Vendor::factory()->approved()->for($vendorUser)->create();

    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $order = Order::factory()->paid()->for($customer)->create([
        'delivered_at'        => now()->subDays(15),
        'earnings_release_at' => now()->subDays(8),  // past cooling-off
    ]);
    OrderItem::factory()->for($order)->state([
        'vendor_id'            => $vendor->id,
        'vendor_earning_minor' => 10000, // 10.000 KWD
    ])->create();

    actingAs($vendorUser)->get('/vendor/wallet')
        ->assertSuccessful()
        ->assertInertia(fn ($p) => $p
            ->where('wallet.available_minor', fn ($v) => is_int($v) && $v > 0)
        );
});

it('v6.2: vendor wallet page exposes the full breakdown shape that the v6.2 UI relies on', function () {
    $vendorUser = User::factory()->create();
    $vendorUser->assignRole('vendor');
    Vendor::factory()->approved()->for($vendorUser)->create();

    actingAs($vendorUser)->get('/vendor/wallet')
        ->assertSuccessful()
        ->assertInertia(fn ($p) => $p
            ->has('wallet', fn ($w) => $w
                ->where('available_minor', 0)
                ->has('lifetime_earnings')
                ->has('in_escrow')
                ->has('releasing')
                ->has('released')
                ->has('reserved')
                ->has('paid_out')
                ->has('available')
                ->has('pending')
                ->has('currency')
            )
        );
});

it('v6.2: vendor can submit a payout request against factory-built released earnings', function () {
    $vendorUser = User::factory()->create();
    $vendorUser->assignRole('vendor');
    $vendor = Vendor::factory()->approved()->for($vendorUser)->create();

    $customer = User::factory()->create();
    $customer->assignRole('customer');
    $order = Order::factory()->paid()->for($customer)->create([
        'delivered_at'        => now()->subDays(15),
        'earnings_release_at' => now()->subDays(8),
    ]);
    OrderItem::factory()->for($order)->state([
        'vendor_id'            => $vendor->id,
        'vendor_earning_minor' => 10000,
    ])->create();

    actingAs($vendorUser)->post('/vendor/wallet/payouts', [
        'amount_minor'        => 5000,
        'payout_method'       => 'bank_transfer',
        'iban'                => 'KW00TEST',
        'bank_name'           => 'Test Bank',
        'account_holder_name' => 'Test',
    ])->assertRedirect();

    expect($vendor->payoutRequests()->where('status', 'pending')->count())->toBe(1);
});
