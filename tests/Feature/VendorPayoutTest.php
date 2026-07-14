<?php

declare(strict_types=1);

/**
 * Phase 5 — vendor wallet + payout request flow.
 *
 * Wallet balance is computed from order_items (vendor_earning_minor) + the
 * payout_requests table, so these tests exercise the real money math.
 */

use App\Domain\Payout\PayoutService;
use App\Domain\Payout\VendorWalletService;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPayoutRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\VendorPackagesSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(VendorPackagesSeeder::class);
});

/**
 * Seed a vendor with one delivered, paid, past-release order_item earning
 * `$earningMinor` for the vendor — this maps into `available_balance`.
 */
function vendorWithReleasedEarnings(int $earningMinor): Vendor
{
    $vendorUser = User::factory()->create();
    $vendorUser->assignRole('vendor');
    $vendor = Vendor::factory()->approved()->for($vendorUser)->create();

    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $order = Order::factory()->paid()->for($customer)->create([
        'delivered_at'        => now()->subDays(10),
        'earnings_release_at' => now()->subDays(3), // released already
    ]);
    OrderItem::factory()->for($order)->state([
        'vendor_id'             => $vendor->id,
        'vendor_earning_minor'  => $earningMinor,
    ])->create();

    return $vendor;
}

/* ─────────── Wallet view ─────────── */

it('v6.0: vendor can view wallet with computed balances', function () {
    $vendor = vendorWithReleasedEarnings(10000);

    actingAs($vendor->user)->get('/vendor/wallet')
        ->assertSuccessful()
        ->assertInertia(fn ($p) => $p
            ->where('wallet.available_minor', 10000)
        );
});

it('v6.0: balance breakdown distinguishes in-escrow / releasing / released', function () {
    $vendorUser = User::factory()->create();
    $vendorUser->assignRole('vendor');
    $vendor = Vendor::factory()->approved()->for($vendorUser)->create();

    $customer = User::factory()->create();
    $customer->assignRole('customer');

    // In escrow: paid but not delivered
    $o1 = Order::factory()->paid()->for($customer)->create(['delivered_at' => null]);
    OrderItem::factory()->for($o1)->state(['vendor_id' => $vendor->id, 'vendor_earning_minor' => 1000])->create();

    // Releasing: delivered, but earnings_release_at in future
    $o2 = Order::factory()->paid()->for($customer)->create([
        'delivered_at' => now()->subDay(), 'earnings_release_at' => now()->addDays(5),
    ]);
    OrderItem::factory()->for($o2)->state(['vendor_id' => $vendor->id, 'vendor_earning_minor' => 2000])->create();

    // Released
    $o3 = Order::factory()->paid()->for($customer)->create([
        'delivered_at' => now()->subDays(10), 'earnings_release_at' => now()->subDays(3),
    ]);
    OrderItem::factory()->for($o3)->state(['vendor_id' => $vendor->id, 'vendor_earning_minor' => 5000])->create();

    $balance = app(VendorWalletService::class)->balanceFor($vendor);
    expect($balance['in_escrow_minor'])->toBe(1000);
    expect($balance['releasing_minor'])->toBe(2000);
    expect($balance['released_minor'])->toBe(5000);
    expect($balance['available_balance_minor'])->toBe(5000);
    expect($balance['lifetime_earnings_minor'])->toBe(8000);
});

/* ─────────── Request flow ─────────── */

it('v6.0: vendor can request a payout up to their available balance', function () {
    $vendor = vendorWithReleasedEarnings(10000);

    actingAs($vendor->user)->post('/vendor/wallet/payouts', [
        'amount_minor' => 8000,
        'payout_method' => 'bank_transfer',
        'iban' => 'KW00DEMO0000', 'bank_name' => 'Demo Bank', 'account_holder_name' => 'Test',
    ])->assertRedirect();

    $req = $vendor->payoutRequests()->first();
    expect($req)->not->toBeNull();
    expect($req->status)->toBe(VendorPayoutRequest::STATUS_PENDING);
    expect($req->requested_amount_minor)->toBe(8000);
});

it('v6.0: payout request exceeding available balance is rejected', function () {
    $vendor = vendorWithReleasedEarnings(10000);

    expect(fn () => app(PayoutService::class)->request($vendor, 20000, []))
        ->toThrow(RuntimeException::class);

    expect($vendor->payoutRequests()->count())->toBe(0);
});

it('v6.0: pending+approved requests reduce available balance (no double-spend)', function () {
    $vendor = vendorWithReleasedEarnings(10000);

    app(PayoutService::class)->request($vendor, 6000, []);

    $balance = app(VendorWalletService::class)->balanceFor($vendor);
    expect($balance['reserved_for_payout_minor'])->toBe(6000);
    expect($balance['available_balance_minor'])->toBe(4000);

    // Try requesting another 6000 → should fail
    expect(fn () => app(PayoutService::class)->request($vendor, 6000, []))
        ->toThrow(RuntimeException::class);
});

/* ─────────── Admin moderation ─────────── */

it('v6.0: admin can approve a pending payout request', function () {
    $vendor = vendorWithReleasedEarnings(10000);
    $request = app(PayoutService::class)->request($vendor, 5000, []);
    $admin = User::factory()->create(); $admin->assignRole('super_admin');

    $result = app(PayoutService::class)->approve($request, $admin, 'looks good');

    expect($result->status)->toBe(VendorPayoutRequest::STATUS_APPROVED);
    expect($result->approved_at)->not->toBeNull();
    expect($result->processed_by)->toBe($admin->id);
});

it('v6.0: admin can reject a pending payout request', function () {
    $vendor = vendorWithReleasedEarnings(10000);
    $request = app(PayoutService::class)->request($vendor, 5000, []);
    $admin = User::factory()->create(); $admin->assignRole('super_admin');

    $result = app(PayoutService::class)->reject($request, $admin, 'unverified bank details');

    expect($result->status)->toBe(VendorPayoutRequest::STATUS_REJECTED);
    expect($result->rejection_reason)->toBe('unverified bank details');

    // Rejected amount no longer reserves balance
    $balance = app(VendorWalletService::class)->balanceFor($vendor);
    expect($balance['reserved_for_payout_minor'])->toBe(0);
    expect($balance['available_balance_minor'])->toBe(10000);
});

it('v6.0: admin can mark an approved request as paid with a transfer reference', function () {
    $vendor = vendorWithReleasedEarnings(10000);
    $request = app(PayoutService::class)->request($vendor, 5000, []);
    $admin = User::factory()->create(); $admin->assignRole('super_admin');

    app(PayoutService::class)->approve($request, $admin);
    $result = app(PayoutService::class)->markPaid($request, $admin, 'SWIFT-TX-12345');

    expect($result->status)->toBe(VendorPayoutRequest::STATUS_PAID);
    expect($result->transfer_reference)->toBe('SWIFT-TX-12345');
    expect($result->paid_at)->not->toBeNull();
});

/* ─────────── Scoping ─────────── */

it('v6.0: vendor sees only their own payout records on the wallet page', function () {
    $vendor1 = vendorWithReleasedEarnings(10000);
    $vendor2 = vendorWithReleasedEarnings(10000);

    app(PayoutService::class)->request($vendor1, 1000, []);
    app(PayoutService::class)->request($vendor1, 2000, []);
    app(PayoutService::class)->request($vendor2, 5000, []);

    actingAs($vendor1->user)->get('/vendor/wallet')
        ->assertInertia(fn ($p) => $p->has('history', 2));

    actingAs($vendor2->user)->get('/vendor/wallet')
        ->assertInertia(fn ($p) => $p->has('history', 1));
});
