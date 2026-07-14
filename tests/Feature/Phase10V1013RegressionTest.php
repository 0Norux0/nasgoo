<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── helpers ─────────────────────────────────────────────────────────────

function p1013_seed(): void
{
    \Artisan::call('db:seed', [
        '--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder',
        '--force' => true,
    ]);
}

function p1013_approved_vendor(): array
{
    p1013_seed();
    $u = User::factory()->create(['status' => 'active']);
    $u->assignRole('vendor');
    $v = Vendor::create([
        'user_id'        => $u->id,
        'business_name'  => 'V' . uniqid(),
        'business_email' => 'v' . uniqid() . '@p1013.test',
        'business_type'  => 'company',
        'country'        => 'KW',
        'status'         => Vendor::STATUS_APPROVED,
    ]);
    return [$u->fresh(), $v->fresh()];
}

function p1013_pending_vendor(): array
{
    p1013_seed();
    $u = User::factory()->create(['status' => 'active']);
    $u->assignRole('vendor');
    $v = Vendor::create([
        'user_id'        => $u->id,
        'business_name'  => 'P' . uniqid(),
        'business_email' => 'p' . uniqid() . '@p1013.test',
        'business_type'  => 'company',
        'country'        => 'KW',
        'status'         => Vendor::STATUS_PENDING,
    ]);
    return [$u->fresh(), $v->fresh()];
}

function p1013_customer(): User
{
    p1013_seed();
    $u = User::factory()->create(['status' => 'active']);
    $u->assignRole('customer');
    return $u->fresh();
}

function p1013_admin(): User
{
    p1013_seed();
    $u = User::factory()->create(['status' => 'active']);
    $u->assignRole('super_admin');
    return $u->fresh();
}

function p1013_seed_order_for_vendor(Vendor $vendor, int $gross = 5000): Order
{
    $customerUser = User::factory()->create();
    $order = Order::create([
        'number'             => 'O' . uniqid(),
        'user_id'            => $customerUser->id,
        'status'             => Order::STATUS_PAID,
        'payment_status'     => 'paid',
        'fulfillment_status' => Order::FUL_UNFULFILLED,
        'currency'           => 'KWD',
        'subtotal_minor'     => $gross,
        'shipping_minor'     => 0,
        'tax_minor'          => 0,
        'discount_minor'     => 0,
        'total_minor'        => $gross,
    ]);
    OrderItem::create([
        'order_id'                => $order->id,
        'vendor_id'               => $vendor->id,
        'product_id'              => null,
        'product_name'            => 'p',
        'quantity'                => 1,
        'unit_price_minor'        => $gross,
        'line_total_minor'        => $gross,
        'commission_amount_minor' => (int) ($gross * 0.2),
        'vendor_earning_minor'    => (int) ($gross * 0.8),
        'commission_percent'      => 20,
        'fulfillment_status'      => OrderItem::FUL_UNFULFILLED,
    ]);
    return $order;
}

// ─── §8.1+2+3 — approved vendor opens Reports ────────────────────────

it('approved vendor reaches /vendor/reports with HTTP 200', function () {
    [$u] = p1013_approved_vendor();
    $this->actingAs($u)->get('/vendor/reports')->assertOk();
});

it('approved vendor sees the Reports page (Inertia component name)', function () {
    [$u] = p1013_approved_vendor();
    $this->actingAs($u)->get('/vendor/reports')
        ->assertInertia(fn ($page) => $page->component('Vendor/Reports/Index'));
});

// ─── §8.4+5+6 — vendor data isolation ────────────────────────────────

it('vendor sees only their own sales totals', function () {
    [$uA, $vendorA] = p1013_approved_vendor();
    // Wipe whatever was seeded by helpers so DB is deterministic
    p1013_seed_order_for_vendor($vendorA, 10000); // 100.00
    p1013_seed_order_for_vendor($vendorA, 5000);  // 50.00

    [, $vendorB] = p1013_approved_vendor();
    p1013_seed_order_for_vendor($vendorB, 99000); // 990.00 — must NOT appear in A's report

    $this->actingAs($uA)->get('/vendor/reports')
        ->assertInertia(fn ($page) => $page
            ->where('financial.gross_minor', 15000) // only A's totals
        );
});

it('vendor sees zero earnings when they have no orders', function () {
    [$u] = p1013_approved_vendor();
    // No order seeded — vendor's reports should show 0
    $this->actingAs($u)->get('/vendor/reports')
        ->assertInertia(fn ($page) => $page
            ->where('financial.gross_minor', 0)
            ->where('financial.order_count', 0)
        );
});

// ─── §8.7 — Vendor cannot access another vendor's data ───────────────

it('vendor cannot pass ?vendor_id to read another vendor\'s data', function () {
    [$uA, $vendorA] = p1013_approved_vendor();
    p1013_seed_order_for_vendor($vendorA, 5000);

    [, $vendorB] = p1013_approved_vendor();
    p1013_seed_order_for_vendor($vendorB, 99000);

    // Vendor A tries to inject vendor B's id via query param
    $this->actingAs($uA)->get("/vendor/reports?vendor_id={$vendorB->id}")
        ->assertInertia(fn ($page) => $page
            ->where('financial.gross_minor', 5000) // STILL only A's totals; the param is ignored
        );
});

// ─── §8.8+9 — customer + guest ───────────────────────────────────────

it('customer receives 403 (or redirect to apply) for /vendor/reports', function () {
    $u = p1013_customer();
    $resp = $this->actingAs($u)->get('/vendor/reports');
    // EnsureVendor middleware redirects customers (no vendor profile) to /vendor/apply
    // OR they get 403 from role gate, depending on which middleware fires first.
    expect($resp->getStatusCode())->toBeIn([302, 403]);
});

it('guest is redirected to login from /vendor/reports', function () {
    $this->get('/vendor/reports')->assertRedirect('/login');
});

// ─── §8.10 — Admin Reports remain working (v10.12 regression guard) ──

it('admin /admin/reports still loads (v10.12 preserved)', function () {
    $admin = p1013_admin();
    $this->actingAs($admin)->get('/admin/reports')->assertOk();
});

// ─── §8.11 — Vendor Reports date filter works ────────────────────────

it('vendor Reports date preset query updates the filter prop', function () {
    [$u] = p1013_approved_vendor();
    $this->actingAs($u)->get('/vendor/reports?preset=last_7_days')
        ->assertInertia(fn ($page) => $page
            ->where('filter.preset', 'last_7_days')
        );
});

// ─── §8.12 — Vendor Reports CSV export is scoped ─────────────────────

it('vendor Reports export.csv is reachable for approved vendor', function () {
    [$u] = p1013_approved_vendor();
    $resp = $this->actingAs($u)->get('/vendor/reports/export.csv');
    // 200 (success) or any 2xx; NOT 403/404/500
    expect($resp->getStatusCode())->toBeLessThan(400);
});

it('vendor Reports export.csv is denied to a customer', function () {
    $u = p1013_customer();
    $resp = $this->actingAs($u)->get('/vendor/reports/export.csv');
    expect($resp->getStatusCode())->toBeIn([302, 403]);
});

// ─── §8.13+14+15 — Navigation visibility & active state ──────────────

it('VendorLayout source includes the Reports nav link in baseItems', function () {
    $src = file_get_contents(resource_path('js/Layouts/VendorLayout.tsx'));
    expect($src)->toContain("vendor-nav-reports");
    expect($src)->toContain("{ href: '/vendor/reports', label: 'Reports'");
});

it('VendorLayout source includes ReportsIcon SVG component', function () {
    $src = file_get_contents(resource_path('js/Layouts/VendorLayout.tsx'));
    expect($src)->toContain('ReportsIcon');
    expect($src)->toContain("icon: 'reports'");
});

it('VendorLayout source uses isActive helper for active-state styling', function () {
    $src = file_get_contents(resource_path('js/Layouts/VendorLayout.tsx'));
    expect($src)->toContain('isActive');
    expect($src)->toContain('text-indigo-700');
});

it('Vendor Dashboard source has prominent Reports CTA card', function () {
    $src = file_get_contents(resource_path('js/Pages/Vendor/Dashboard.tsx'));
    expect($src)->toContain('vendor-dashboard-reports-cta');
    expect($src)->toContain('/vendor/reports');
    expect($src)->toContain('View My Reports');
});

// ─── §6 — Pending/rejected/suspended access policy (existing) ────────

it('pending vendor is redirected from /vendor/reports (v10.13 documents existing behavior)', function () {
    [$u] = p1013_pending_vendor();
    // EnsureVendor with 'approved' parameter redirects pending vendors to /vendor
    $resp = $this->actingAs($u)->get('/vendor/reports');
    expect($resp->getStatusCode())->toBeIn([302, 403]);
});

// ─── Cross-cutting ──────────────────────────────────────────────────

it('VERSION reports Phase 10 v10.13', function () {
    expect(trim((string) file_get_contents(base_path('VERSION'))))->toBe('Phase 10 v10.13');
});

it('v10.12 customers_total Spatie fix preserved', function () {
    $src = file_get_contents(app_path('Domain/Reports/ReportsService.php'));
    expect($src)->toContain("User::role('customer')");
    expect($src)->not->toMatch("/DB::table\\(['\\\"]users['\\\"]\\)->where\\(['\\\"]role['\\\"]/");
});

it('v10.11 SUM(requested_amount_minor) payout fix preserved', function () {
    $src = file_get_contents(app_path('Domain/Reports/ReportsService.php'));
    expect(substr_count($src, 'SUM(requested_amount_minor)'))->toBeGreaterThanOrEqual(2);
    expect($src)->not->toContain('SUM(amount_minor)');
});
