<?php

declare(strict_types=1);

/**
 * Phase 4 v5.3 — verifies DemoSeeder produces the expected state for the
 * `migrate:fresh --seed` developer workflow.
 *
 * Phase 9 v9.4 — REPLACED the previous env-flip pattern (`app()->detectEnvironment(fn () => 'local')`)
 * with a scoped config flag. The env-flip re-enabled CSRF middleware, which
 * caused 419 responses on /cart/items and /checkout HTTP tests that ran in
 * the same Pest worker. The new approach narrows the opt-in to DemoSeeder's
 * own guard without polluting global state.
 */

use App\Models\Address;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorSubscription;
use Database\Seeders\AttributesSeeder;
use Database\Seeders\CategoriesSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\PaymentMethodsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\VendorPackagesSeeder;

beforeEach(function () {
    // Phase 9 v9.4 — opt-in to DemoSeeder via scoped config flag.
    // Env stays 'testing'; CSRF middleware stays disabled.
    config(['marketplace.allow_demo_seeder_in_testing' => true]);

    // DemoSeeder requires the foundation seeders to have run first.
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(VendorPackagesSeeder::class);
    $this->seed(CategoriesSeeder::class);
    $this->seed(AttributesSeeder::class);
    $this->seed(PaymentMethodsSeeder::class);
});

afterEach(function () {
    config(['marketplace.allow_demo_seeder_in_testing' => false]);
});

/* ─────────── Foundation users ─────────── */

it('demo: DatabaseSeeder creates the four demo accounts', function () {
    $this->seed(DatabaseSeeder::class);

    foreach (['admin', 'staff', 'vendor', 'customer'] as $role) {
        $u = User::where('email', "{$role}@marketplace.test")->first();
        expect($u)->not->toBeNull("{$role}@marketplace.test should exist");
        expect($u->email_verified_at)->not->toBeNull();
    }
});

/* ─────────── DemoSeeder: vendor profile ─────────── */

it('demo: the vendor account has an approved profile with an active Basic subscription', function () {
    $this->seed(DatabaseSeeder::class);

    $user = User::where('email', 'vendor@marketplace.test')->firstOrFail();
    $vendor = $user->vendor;

    expect($vendor)->not->toBeNull();
    expect($vendor->status)->toBe(Vendor::STATUS_APPROVED);
    expect($vendor->approved_at)->not->toBeNull();
    expect($vendor->business_name)->toBe('Demo Trading Co.');

    $sub = $vendor->activeSubscription;
    expect($sub)->not->toBeNull();
    expect($sub->status)->toBe(VendorSubscription::STATUS_ACTIVE);
    expect($sub->starts_at)->not->toBeNull();

    expect($vendor->currentPackage())->not->toBeNull();
    expect($vendor->currentPackage()->slug)->toBe('basic');
});

it('demo: pending vendor and rejected vendor exist with correct statuses', function () {
    $this->seed(DatabaseSeeder::class);

    $pending = User::where('email', 'pending-vendor@marketplace.test')->first();
    expect($pending)->not->toBeNull();
    expect($pending->vendor)->not->toBeNull();
    expect($pending->vendor->status)->toBe(Vendor::STATUS_PENDING);

    $rejected = User::where('email', 'rejected-vendor@marketplace.test')->first();
    expect($rejected)->not->toBeNull();
    expect($rejected->vendor)->not->toBeNull();
    expect($rejected->vendor->status)->toBe(Vendor::STATUS_REJECTED);
    expect($rejected->vendor->rejection_reason)->not->toBeEmpty();
});

/* ─────────── DemoSeeder: products ─────────── */

it('demo: vendor has at least 3 published products with stock (cart-ready)', function () {
    $this->seed(DatabaseSeeder::class);

    $vendor = User::where('email', 'vendor@marketplace.test')->firstOrFail()->vendor;
    $published = $vendor->products()
        ->where('status', Product::STATUS_PUBLISHED)
        ->where('stock', '>', 0)
        ->where('price_minor', '>', 0)
        ->get();

    expect($published->count())->toBeGreaterThanOrEqual(3);
    expect($published->every(fn ($p) => $p->currency === 'KWD'))->toBeTrue();
});

it('demo: vendor has one draft and one pending-review product to demonstrate workflow', function () {
    $this->seed(DatabaseSeeder::class);

    $vendor = User::where('email', 'vendor@marketplace.test')->firstOrFail()->vendor;
    expect($vendor->products()->where('status', Product::STATUS_DRAFT)->count())->toBeGreaterThanOrEqual(1);
    expect($vendor->products()->where('status', Product::STATUS_PENDING_REVIEW)->count())->toBeGreaterThanOrEqual(1);
});

/* ─────────── DemoSeeder: customer address (Phase 1 schema) ─────────── */

it('demo: customer has a default address using the real Phase 1 schema', function () {
    $this->seed(DatabaseSeeder::class);

    $customer = User::where('email', 'customer@marketplace.test')->firstOrFail();
    $addr = $customer->addresses()->where('is_default', true)->first();

    expect($addr)->not->toBeNull();
    expect($addr->country)->toBe('KW');
    expect($addr->city)->toBe('Kuwait City');
    // Phase 1 columns must be populated — not the phantom Western fields
    expect($addr->block)->not->toBeEmpty();
    expect($addr->street)->not->toBeEmpty();
    expect($addr->building)->not->toBeEmpty();
    expect($addr->phone)->not->toBeEmpty();
});

/* ─────────── End-to-end: demo customer + demo vendor product ─────────── */

it('demo: the demo customer can add a demo product to cart and reach checkout', function () {
    $this->seed(DatabaseSeeder::class);

    $customer = User::where('email', 'customer@marketplace.test')->firstOrFail();
    $product = Product::where('vendor_id', User::where('email', 'vendor@marketplace.test')->first()->vendor->id)
        ->where('status', Product::STATUS_PUBLISHED)
        ->where('stock', '>', 0)
        ->firstOrFail();

    actingAs($customer)->post('/cart/items', ['product_id' => $product->id, 'quantity' => 1])
        ->assertRedirect();

    $response = actingAs($customer)->get('/checkout');
    expect($response->status())->toBe(200);  // not 500 (TypeError) or 419 (CSRF)
});

it('demo: demo customer can place a COD order against a demo product', function () {
    $this->seed(DatabaseSeeder::class);

    $customer = User::where('email', 'customer@marketplace.test')->firstOrFail();
    $product = Product::where('status', Product::STATUS_PUBLISHED)
        ->where('stock', '>', 0)
        ->firstOrFail();

    actingAs($customer)->post('/cart/items', ['product_id' => $product->id, 'quantity' => 1]);

    $addr = $customer->addresses()->first();
    $response = actingAs($customer)->post('/checkout', [
        'shipping_address_id' => $addr->id,
        'payment_method_slug' => 'cod',
    ]);

    expect($response->status())->not->toBe(500);
    expect($response->status())->not->toBe(419);

    $order = $customer->orders()->latest()->first();
    expect($order)->not->toBeNull();
    // v5.4: DemoSeeder now creates a vendor-level commission rule (20%) which
    // is more specific than the Basic package default (30%), so it wins.
    expect((float) $order->items()->first()->commission_percent)->toBe(20.00);  // vendor rule beats package
});

/* ─────────── Reasonableness checks ─────────── */

it('demo: foundational seeds present (currencies, payment methods, vendor packages)', function () {
    $this->seed(DatabaseSeeder::class);

    expect(\App\Models\Currency::count())->toBeGreaterThanOrEqual(3);  // KWD/USD/AED at minimum
    expect(\App\Models\PaymentMethod::count())->toBe(3);
    expect(\App\Models\VendorPackage::count())->toBeGreaterThanOrEqual(3); // Basic/Standard/Pro
});

it('demo: DemoSeeder is idempotent — running twice produces the same row counts', function () {
    $this->seed(DatabaseSeeder::class);
    $vendorCount   = Vendor::count();
    $productCount  = Product::count();
    $addressCount  = Address::count();
    $userCount     = User::count();

    $this->seed(DemoSeeder::class);

    expect(Vendor::count())->toBe($vendorCount);
    expect(Product::count())->toBe($productCount);
    expect(Address::count())->toBe($addressCount);
    expect(User::count())->toBe($userCount);
});

it('demo: DemoSeeder is skipped under testing env when the opt-in flag is OFF (the default safety check)', function () {
    // Phase 9 v9.4 — verify the safety check works. v9.3 changed the env
    // to 'testing' here, but our beforeEach() already opts in via
    // marketplace.allow_demo_seeder_in_testing=true. Toggle it OFF for
    // this single test to exercise the skip path.
    config(['marketplace.allow_demo_seeder_in_testing' => false]);

    // Reset DB so we know what state we're in
    Vendor::where('user_id', User::where('email', 'vendor@marketplace.test')->value('id'))->delete();

    $this->seed(DemoSeeder::class);

    // No vendor row created — the seeder bailed out
    expect(
        Vendor::whereHas('user', fn ($q) => $q->where('email', 'vendor@marketplace.test'))->exists()
    )->toBeFalse();
});
