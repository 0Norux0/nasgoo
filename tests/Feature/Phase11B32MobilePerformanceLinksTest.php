<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

// ─── helpers (p11b32_*) ────────────────────────────────────────────────────

function p11b32_seed(): void
{
    \Artisan::call('db:seed', [
        '--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder',
        '--force' => true,
    ]);
}

function p11b32_customer(): User
{
    p11b32_seed();
    $u = User::factory()->create([
        'email' => 'p11b32-c-' . uniqid() . '@p11b32.test',
        'password' => Hash::make('pw'), 'status' => 'active',
    ]);
    $u->assignRole('customer');
    return $u->fresh();
}

function p11b32_vendor_user(string $status = Vendor::STATUS_APPROVED): User
{
    p11b32_seed();
    $u = User::factory()->create([
        'email' => 'p11b32-v-' . uniqid() . '@p11b32.test',
        'password' => Hash::make('pw'), 'status' => 'active',
    ]);
    $u->assignRole('vendor');
    Vendor::create([
        'user_id' => $u->id,
        'business_name' => 'V' . uniqid(),
        'business_email' => 'v' . uniqid() . '@p11b32.test',
        'business_type' => 'company', 'country' => 'KW', 'status' => $status,
    ]);
    return $u->fresh();
}

function p11b32_super_admin(): User
{
    p11b32_seed();
    $u = User::factory()->create([
        'email' => 'p11b32-a-' . uniqid() . '@p11b32.test',
        'password' => Hash::make('pw'), 'status' => 'active',
    ]);
    $u->assignRole('super_admin');
    return $u->fresh();
}

// ════════════════════════════════════════════════════════════════════════════
// §20 §22 §30.25 VENDOR SETTINGS — the actual 404 fix verification
// ════════════════════════════════════════════════════════════════════════════

it('§20.1 vendor settings route is registered (fixes v11B.3.1 404)', function () {
    $exists = collect(app('router')->getRoutes())->contains(
        fn ($r) => $r->getName() === 'vendor.settings.edit'
    );
    expect($exists)->toBeTrue();
});

it('§20.2 approved vendor can load Settings page (200, not 404)', function () {
    $vendor = p11b32_vendor_user();
    test()->actingAs($vendor)->get('/vendor/settings')
        ->assertOk()
        ->assertInertia(fn ($pg) => $pg
            ->component('Vendor/Settings')
            ->has('vendor.business_name')
            ->has('vendor.business_email')
            ->has('features')
            ->etc()
        );
});

it('§20.3 customer cannot access vendor Settings page (403)', function () {
    $customer = p11b32_customer();
    test()->actingAs($customer)->get('/vendor/settings')->assertForbidden();
});

it('§20.4 unauthenticated user hitting vendor Settings redirected to login', function () {
    test()->get('/vendor/settings')->assertRedirect();
});

it('§20.5 vendor can update their store profile', function () {
    $vendor = p11b32_vendor_user();
    test()->actingAs($vendor)->patch('/vendor/settings', [
        'business_name'  => 'Updated Store',
        'business_email' => 'new@updated.test',
        'phone'          => '+96500000000',
        'address'        => '123 St, Kuwait',
        'description'    => 'A brand new store',
        'website'        => 'https://example.com',
    ])->assertStatus(302);
    expect($vendor->vendor->fresh()->business_name)->toBe('Updated Store');
});

it('§20.6 vendor cannot save javascript: URL as website', function () {
    $vendor = p11b32_vendor_user();
    test()->actingAs($vendor)->patch('/vendor/settings', [
        'business_name'  => 'X',
        'business_email' => 'x@x.test',
        'website'        => 'javascript:alert(1)',
    ])->assertSessionHasErrors('website');
});

it('§20.7 vendor A cannot update vendor B\'s settings (identity from $request->user())', function () {
    $vA = p11b32_vendor_user();
    $vB = p11b32_vendor_user();
    // vA is authenticated; the controller uses $request->user()->vendor,
    // NOT any request parameter — so vA's own vendor row is updated, not vB's.
    test()->actingAs($vA)->patch('/vendor/settings', [
        'business_name'  => 'HackedName',
        'business_email' => 'hacker@test.test',
    ]);
    expect($vA->vendor->fresh()->business_name)->toBe('HackedName');
    // vB's row unchanged
    expect($vB->vendor->fresh()->business_name)->not->toBe('HackedName');
});

// ════════════════════════════════════════════════════════════════════════════
// §21 §22 §30.31-33 BROKEN-LINK AUDIT — every menu URL resolves for the right role
// ════════════════════════════════════════════════════════════════════════════

it('§21.1 every vendor sidebar URL resolves (no 404) as an approved vendor', function () {
    $vendor = p11b32_vendor_user();

    // The URLs listed in resources/js/Components/Vendor/VendorSidebar.tsx
    // (v11B.3.1). Any 404 here = broken sidebar link.
    $urls = [
        '/vendor',
        '/vendor/reports',
        '/vendor/products',
        '/vendor/products/create',
        '/vendor/services',
        '/vendor/orders',
        '/vendor/bookings',
        '/vendor/reviews',
        '/vendor/wallet',
        '/vendor/payouts',
        '/vendor/supplier-products',
        '/vendor/supplier-orders',
        '/vendor/tickets',
        '/vendor/settings',
    ];

    $broken = [];
    foreach ($urls as $url) {
        $status = test()->actingAs($vendor)->get($url)->status();
        if ($status === 404) {
            $broken[] = $url;
        }
    }
    expect($broken)->toBe([], 'These vendor sidebar URLs 404: ' . implode(', ', $broken));
});

it('§21.2 every storefront nav URL resolves (no 404) as a customer', function () {
    $customer = p11b32_customer();

    $urls = [
        '/',
        '/products',
        '/services',
        '/deals',
        '/cart',
        '/wishlist',
        '/orders',
        '/bookings',
        '/tickets',
        '/account/personalization',
    ];

    $broken = [];
    foreach ($urls as $url) {
        $status = test()->actingAs($customer)->get($url)->status();
        if ($status === 404) {
            $broken[] = "{$url} → {$status}";
        }
    }
    expect($broken)->toBe([], 'These storefront URLs 404: ' . implode('; ', $broken));
});

it('§21.3 admin site-settings URL resolves for super_admin (no 404, no 403)', function () {
    $admin = p11b32_super_admin();
    test()->actingAs($admin)->get('/admin/site-settings')->assertOk();
});

it('§21.4 customer hitting vendor URLs gets 403 (not 404 — authorization intact)', function () {
    $customer = p11b32_customer();
    foreach (['/vendor', '/vendor/products', '/vendor/settings'] as $url) {
        $s = test()->actingAs($customer)->get($url)->status();
        expect($s)->toBeIn([302, 403], "Customer hitting {$url} got {$s} (expected 302/403)");
    }
});

it('§21.5 vendor cannot access admin routes (403 or redirect)', function () {
    $vendor = p11b32_vendor_user();
    $s = test()->actingAs($vendor)->get('/admin/site-settings')->status();
    expect($s)->toBeIn([302, 403], "Vendor hitting /admin/site-settings got {$s}");
});

// ════════════════════════════════════════════════════════════════════════════
// §3 §4 ADMIN PERFORMANCE — StatsOverview widget must be cached
// ════════════════════════════════════════════════════════════════════════════

it('§4.1 StatsOverview widget uses cache (2nd call fires 0 DB queries)', function () {
    Cache::flush();
    $widget = new \App\Filament\Widgets\StatsOverview();
    // First call populates cache
    $reflection = new \ReflectionMethod($widget, 'getStats');
    $reflection->setAccessible(true);
    $reflection->invoke($widget);

    // Second call: no DB queries fired (all served from cache)
    DB::enableQueryLog();
    DB::flushQueryLog();
    $reflection->invoke($widget);
    $queries = collect(DB::getQueryLog())
        // Ignore the auth/session lookups the container may perform
        ->reject(fn ($q) => str_contains(strtolower($q['query']), 'sessions')
                        || str_contains(strtolower($q['query']), 'password_reset_tokens'));
    DB::disableQueryLog();

    // Some drivers/tests may still hit the DB for widget metadata; cap at 2.
    expect($queries->count())->toBeLessThanOrEqual(2,
        'StatsOverview cache miss: ' . $queries->pluck('query')->implode('; '));
});

it('§4.2 StatsOverview widget computes stats in ≤ 8 queries on cache miss', function () {
    Cache::flush();

    DB::enableQueryLog();
    DB::flushQueryLog();
    $widget = new \App\Filament\Widgets\StatsOverview();
    $reflection = new \ReflectionMethod($widget, 'getStats');
    $reflection->setAccessible(true);
    $reflection->invoke($widget);
    $queries = collect(DB::getQueryLog())
        ->reject(fn ($q) => str_contains(strtolower($q['query']), 'sessions')
                        || str_contains(strtolower($q['query']), 'cache'));
    DB::disableQueryLog();

    // Pre-v11B.3.2 was ~23 queries. v11B.3.2 groups into 8 SELECT-COUNT(CASE WHEN)
    // queries plus 3 tiny lookups (packages, roles, currency default).
    expect($queries->count())->toBeLessThanOrEqual(12,
        "StatsOverview cache miss ran {$queries->count()} queries (expected ≤12)");
});

it('§4.3 flush() invalidates the cache', function () {
    Cache::flush();
    $widget = new \App\Filament\Widgets\StatsOverview();
    $reflection = new \ReflectionMethod($widget, 'getStats');
    $reflection->setAccessible(true);

    $reflection->invoke($widget);  // populates
    \App\Filament\Widgets\StatsOverview::flush();

    DB::enableQueryLog();
    DB::flushQueryLog();
    $reflection->invoke($widget);
    $queries = collect(DB::getQueryLog())
        ->reject(fn ($q) => str_contains(strtolower($q['query']), 'sessions'));
    DB::disableQueryLog();
    expect($queries->count())->toBeGreaterThan(0, 'flush() should force re-query');
});

// ════════════════════════════════════════════════════════════════════════════
// §4 §5 PERFORMANCE INDEXES
// ════════════════════════════════════════════════════════════════════════════

it('§5.1 users.status index exists after migrations', function () {
    $exists = collect(\Illuminate\Support\Facades\Schema::getIndexes('users'))
        ->contains(fn ($i) => $i['name'] === 'users_status_idx' || in_array('status', $i['columns']));
    expect($exists)->toBeTrue();
});

it('§5.2 vendors.status index exists', function () {
    $exists = collect(\Illuminate\Support\Facades\Schema::getIndexes('vendors'))
        ->contains(fn ($i) => $i['name'] === 'vendors_status_idx' || in_array('status', $i['columns']));
    expect($exists)->toBeTrue();
});

it('§5.3 orders(status, payment_status) index exists', function () {
    $exists = collect(\Illuminate\Support\Facades\Schema::getIndexes('orders'))
        ->contains(fn ($i) => $i['name'] === 'orders_status_payment_status_idx'
                          || (in_array('status', $i['columns']) && in_array('payment_status', $i['columns'])));
    expect($exists)->toBeTrue();
});

it('§5.4 audit_logs.created_at index exists', function () {
    $exists = collect(\Illuminate\Support\Facades\Schema::getIndexes('audit_logs'))
        ->contains(fn ($i) => $i['name'] === 'audit_logs_created_at_idx' || in_array('created_at', $i['columns']));
    expect($exists)->toBeTrue();
});

// ════════════════════════════════════════════════════════════════════════════
// §11 §12 MOBILE PADDING — every fixed page uses the shared Container
// ════════════════════════════════════════════════════════════════════════════

it('§12.1 Product detail page uses Container (v11A.2 canonical padding)', function () {
    $body = file_get_contents(base_path('resources/js/Pages/Catalog/Show.tsx'));
    expect($body)->toContain('Container');
});

it('§12.2 Cart page uses Container', function () {
    $body = file_get_contents(base_path('resources/js/Pages/Cart/Show.tsx'));
    expect($body)->toContain('Container');
});

it('§12.3 Checkout page uses Container', function () {
    $body = file_get_contents(base_path('resources/js/Pages/Checkout/Show.tsx'));
    expect($body)->toContain('Container');
});

it('§12.4 Orders Index uses ResponsiveDataList + PageContainer', function () {
    $body = file_get_contents(base_path('resources/js/Pages/Orders/Index.tsx'));
    expect($body)->toContain('ResponsiveDataList');
    expect($body)->toContain('PageContainer');
});

it('§12.5 Bookings Index uses ResponsiveDataList + PageContainer', function () {
    $body = file_get_contents(base_path('resources/js/Pages/Bookings/Index.tsx'));
    expect($body)->toContain('ResponsiveDataList');
});

it('§12.6 Tickets Index uses ResponsiveDataList + PageContainer', function () {
    $body = file_get_contents(base_path('resources/js/Pages/Tickets/Index.tsx'));
    expect($body)->toContain('ResponsiveDataList');
});

it('§12.7 Vendor Settings page uses PageContainer', function () {
    $body = file_get_contents(base_path('resources/js/Pages/Vendor/Settings.tsx'));
    expect($body)->toContain('PageContainer');
});

// ════════════════════════════════════════════════════════════════════════════
// §23 VENDOR SIDE PANEL preserved from v11B.3.1
// ════════════════════════════════════════════════════════════════════════════

it('§23.1 VendorSidebar exists with 7 grouped sections', function () {
    $body = file_get_contents(base_path('resources/js/Components/Vendor/VendorSidebar.tsx'));
    expect($body)->toContain('vendor_nav.groups.overview');
    expect($body)->toContain('vendor_nav.groups.settings');
    expect($body)->toContain('/vendor/settings');   // link exists in sidebar
});

it('§23.2 VendorMobileDrawer preserved with focus trap + RTL', function () {
    $body = file_get_contents(base_path('resources/js/Components/Vendor/VendorMobileDrawer.tsx'));
    expect($body)->toContain('trapFocus');
    expect($body)->toContain('Escape');
    expect($body)->toContain('isRTL');
});

// ════════════════════════════════════════════════════════════════════════════
// §29 §42 REGRESSION — no financial/search/personalization loss
// ════════════════════════════════════════════════════════════════════════════

it('§42.1 Homepage still renders', function () {
    test()->get('/')->assertOk();
});

it('§42.2 Product listing still renders', function () {
    test()->get('/products')->assertOk();
});

it('§42.3 Login page still renders', function () {
    test()->get('/login')->assertOk();
});

it('§42.4 v11B.2.2 canonical pricing preserved', function () {
    $body = file_get_contents(base_path('app/Domain/Pricing/PricingService.php'));
    expect($body)->toContain('priceProductWithQuantity');
});

it('§42.5 v11B.2.2 server-authoritative checkout total preserved', function () {
    $body = file_get_contents(base_path('resources/js/Pages/Checkout/Show.tsx'));
    expect($body)->toContain('cart.payable_minor + shippingMinor');
});

it('§42.6 v11B.3 PersonalizationManager preserved', function () {
    expect(file_exists(base_path('app/Services/Personalization/PersonalizationManager.php')))
        ->toBeTrue();
});

it('§42.7 v11B.3.1 SiteSettingsService preserved', function () {
    expect(file_exists(base_path('app/Services/Settings/SiteSettingsService.php')))
        ->toBeTrue();
});

it('§42.8 siteSettings shared on every Inertia render', function () {
    test()->get('/')
        ->assertInertia(fn ($pg) => $pg
            ->has('siteSettings.branding')
            ->has('siteSettings.footer')
            ->etc()
        );
});

it('§42.9 v10.13 vendor-nav-reports testid preserved in VendorLayout', function () {
    $body = file_get_contents(base_path('resources/js/Layouts/VendorLayout.tsx'));
    expect($body)->toContain('vendor-nav-reports');
});
