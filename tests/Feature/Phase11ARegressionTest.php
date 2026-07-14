<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

// ─── helpers ─────────────────────────────────────────────────────────────

function p11a_seed(): void
{
    \Artisan::call('db:seed', [
        '--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder',
        '--force' => true,
    ]);
}

function p11a_customer(): User
{
    p11a_seed();
    $u = User::factory()->create([
        'email'    => 'p11a-customer-' . uniqid() . '@p11a.test',
        'password' => Hash::make('pw'),
        'status'   => 'active',
    ]);
    $u->assignRole('customer');
    return $u->fresh();
}

function p11a_vendor_user(): User
{
    p11a_seed();
    $u = User::factory()->create([
        'email'    => 'p11a-vendor-' . uniqid() . '@p11a.test',
        'password' => Hash::make('pw'),
        'status'   => 'active',
    ]);
    $u->assignRole('vendor');
    Vendor::create([
        'user_id' => $u->id, 'business_name' => 'V' . uniqid(),
        'business_email' => 'v' . uniqid() . '@p11a.test',
        'business_type' => 'company', 'country' => 'KW',
        'status' => Vendor::STATUS_APPROVED,
    ]);
    return $u->fresh();
}

function p11a_admin(): User
{
    p11a_seed();
    $u = User::factory()->create([
        'email'    => 'p11a-admin-' . uniqid() . '@p11a.test',
        'password' => Hash::make('pw'),
        'status'   => 'active',
    ]);
    $u->assignRole('super_admin');
    return $u->fresh();
}

// ─── §2 — Preservation: all v10 surfaces still render ────────────────

it('GET / as guest renders Welcome (no React render crash)', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Welcome'));
});

it('GET / as authenticated customer renders Welcome', function () {
    $this->actingAs(p11a_customer())
        ->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Welcome'));
});

it('GET / as vendor renders Welcome', function () {
    $this->actingAs(p11a_vendor_user())
        ->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Welcome'));
});

it('GET /products (catalog) still renders 200', function () {
    $this->get('/products')->assertOk();
});

it('GET /vendor as authenticated vendor still renders 200', function () {
    $this->actingAs(p11a_vendor_user())->get('/vendor')->assertOk();
});

it('GET /admin/reports as super_admin still renders 200', function () {
    $this->actingAs(p11a_admin())->get('/admin/reports')->assertOk();
});

it('GET /cart as customer still renders 200', function () {
    $this->actingAs(p11a_customer())->get('/cart')->assertOk();
});

// ─── Auth still works (preservation gate from v10.15) ────────────────

it('customer login flow still works (POST /login → 302 / → 200)', function () {
    $u = User::factory()->create([
        'email'    => 'p11a-flow@p11a.test',
        'password' => Hash::make('pw'),
        'status'   => 'active',
    ]);
    p11a_seed();
    $u->assignRole('customer');

    $this->post('/login', ['email' => 'p11a-flow@p11a.test', 'password' => 'pw'])
        ->assertRedirect('/');
    $this->get('/')->assertOk();
});

it('logout still works', function () {
    $this->actingAs(p11a_customer())->post('/logout')->assertRedirect('/');
    expect(auth()->check())->toBeFalse();
});

// ─── §3 — Design system tokens present in source ─────────────────────

it('Tailwind config has the v11A Sapphire Trust design tokens', function () {
    $tw = file_get_contents(base_path('tailwind.config.js'));
    foreach (['brand:', 'accent:', 'gold:', 'ink:', 'shadow-card', 'shadow-hero'] as $token) {
        expect($tw)->toContain($token);
    }
});

it('v11 component primitives exist and use the design tokens', function () {
    foreach (['Button.tsx', 'primitives.tsx', 'ProductCard.tsx'] as $f) {
        expect(file_exists(resource_path("js/Components/ui/v11/$f")))->toBeTrue();
    }
    $button = file_get_contents(resource_path('js/Components/ui/v11/Button.tsx'));
    expect($button)->toContain('bg-brand-800');
    expect($button)->toContain('bg-accent-600');
});

// ─── §5 — Homepage sections per dev's §5 list ────────────────────────

it('homepage source has all required §5 sections', function () {
    $w = file_get_contents(resource_path('js/Pages/Welcome.tsx'));
    foreach ([
        'homepage-hero',
        'homepage-trust',
        'homepage-categories',
        'homepage-featured-products',
        'homepage-deals-banner',
        'homepage-services',
        'homepage-how-it-works',
    ] as $section) {
        expect($w)->toContain("data-testid=\"$section\"");
    }
});

it('homepage uses the v11 ProductCard primitive (not ad-hoc markup)', function () {
    $w = file_get_contents(resource_path('js/Pages/Welcome.tsx'));
    expect($w)->toContain("from '@/Components/ui/v11/ProductCard'");
});

// ─── §7 — StorefrontLayout redesign markers ─────────────────────────

it('StorefrontLayout has the v11A redesign markers', function () {
    $l = file_get_contents(resource_path('js/Layouts/StorefrontLayout.tsx'));
    expect($l)->toContain('storefront-header-v11a');
    expect($l)->toContain('storefront-footer-v11a');
    expect($l)->toContain('storefront-search');
});

it('StorefrontLayout preserves v10.6 mobile Categories drawer', function () {
    $l = file_get_contents(resource_path('js/Layouts/StorefrontLayout.tsx'));
    expect($l)->toContain('mobile-categories-toggle');
    expect($l)->toContain('mobile-categories-list');
});

it('StorefrontLayout exports both named AND default', function () {
    $l = file_get_contents(resource_path('js/Layouts/StorefrontLayout.tsx'));
    // Named for new pages
    expect($l)->toMatch('/export function StorefrontLayout/');
    // Default for legacy pages (Catalog, Orders, Bookings, Services)
    expect($l)->toMatch('/export default StorefrontLayout/');
});

// ─── §15 — Accessibility: WCAG-relevant markers present ──────────────

it('CSS has the prefers-reduced-motion rule', function () {
    $css = file_get_contents(resource_path('css/app.css'));
    expect($css)->toContain('prefers-reduced-motion: reduce');
});

it('Button primitive has focus-visible:ring (WCAG 2.4.7)', function () {
    $b = file_get_contents(resource_path('js/Components/ui/v11/Button.tsx'));
    expect($b)->toContain('focus-visible:ring');
});

// ─── v10.x preservation — ALL fixes must remain ─────────────────────

it('v10.16 null-safe permissions normalize PRESERVED through v11A redesign', function () {
    $w = file_get_contents(resource_path('js/Pages/Welcome.tsx'));
    expect($w)->toMatch('/user\??\.?permissions \?\? \[\]/');
    // The unsafe pattern must NOT have come back
    expect($w)->not->toMatch('/user\.permissions\.length/');
});

it('v10.15 defensive share() wrappings preserved (no PHP files modified)', function () {
    $mid = file_get_contents(app_path('Http/Middleware/HandleInertiaRequests.php'));
    foreach ([
        'auth.user share closure failed',
        'cart_summary share closure failed',
        'top_categories share closure failed',
    ] as $marker) {
        expect($mid)->toContain($marker);
    }
});

it('v10.14 scope-aware closures + indexes preserved', function () {
    $mid = file_get_contents(app_path('Http/Middleware/HandleInertiaRequests.php'));
    expect(substr_count($mid, "str_starts_with(\$path, 'admin/')"))->toBe(2);
    expect(file_exists(database_path('migrations/2026_06_21_000001_add_phase10_v1014_performance_indexes.php')))->toBeTrue();
});

it('v10.11 §2 permissions removed from share, v10.11 §5 SUM fix, v10.12 Spatie scope, v10.13 vendor reports nav — all preserved', function () {
    $mid = file_get_contents(app_path('Http/Middleware/HandleInertiaRequests.php'));
    expect(substr_count($mid, '$request->user()->getAllPermissions()->pluck'))->toBe(0); // v10.11 §2

    $reports = file_get_contents(app_path('Domain/Reports/ReportsService.php'));
    expect(substr_count($reports, 'SUM(requested_amount_minor)'))->toBeGreaterThanOrEqual(2); // v10.11 §5
    expect($reports)->toContain("User::role('customer')"); // v10.12

    $vLayout = file_get_contents(resource_path('js/Layouts/VendorLayout.tsx'));
    expect($vLayout)->toContain('ReportsIcon');  // v10.13
});

it('v10.10 admin reports direct guard preserved (3 occurrences)', function () {
    $ctrl = file_get_contents(app_path('Http/Controllers/Admin/ReportsController.php'));
    expect(substr_count($ctrl, 'guardAdminReportsAccess'))->toBe(3);
});

// ─── Cross-cutting ──────────────────────────────────────────────────

it('VERSION reports Phase 11A', function () {
    expect(trim((string) file_get_contents(base_path('VERSION'))))->toBe('Phase 11A');
});
