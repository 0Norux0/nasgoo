<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

// ─── helpers ─────────────────────────────────────────────────────────────

function p11ah1_seed(): void
{
    \Artisan::call('db:seed', [
        '--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder',
        '--force' => true,
    ]);
}

function p11ah1_customer(): User
{
    p11ah1_seed();
    $u = User::factory()->create([
        'email'    => 'p11ah1-c-' . uniqid() . '@p11ah1.test',
        'password' => Hash::make('pw'),
        'status'   => 'active',
    ]);
    $u->assignRole('customer');
    return $u->fresh();
}

function p11ah1_vendor_user(): User
{
    p11ah1_seed();
    $u = User::factory()->create([
        'email'    => 'p11ah1-v-' . uniqid() . '@p11ah1.test',
        'password' => Hash::make('pw'),
        'status'   => 'active',
    ]);
    $u->assignRole('vendor');
    Vendor::create([
        'user_id' => $u->id, 'business_name' => 'V' . uniqid(),
        'business_email' => 'v' . uniqid() . '@p11ah1.test',
        'business_type' => 'company', 'country' => 'KW',
        'status' => Vendor::STATUS_APPROVED,
    ]);
    return $u->fresh();
}

function p11ah1_admin(): User
{
    p11ah1_seed();
    $u = User::factory()->create([
        'email'    => 'p11ah1-a-' . uniqid() . '@p11ah1.test',
        'password' => Hash::make('pw'),
        'status'   => 'active',
    ]);
    $u->assignRole('super_admin');
    return $u->fresh();
}

// ─── §16 — Pages still render (preservation gate) ────────────────────

it('GET / as guest still renders Welcome after Container migration', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Welcome'));
});

it('GET / as authenticated customer still renders Welcome', function () {
    $this->actingAs(p11ah1_customer())
        ->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Welcome'));
});

it('GET /products still renders 200 (catalog still works, container-app legacy still padded)', function () {
    $this->get('/products')->assertOk();
});

it('GET /vendor as vendor still renders 200', function () {
    $this->actingAs(p11ah1_vendor_user())->get('/vendor')->assertOk();
});

it('GET /admin/reports as super_admin still renders 200', function () {
    $this->actingAs(p11ah1_admin())->get('/admin/reports')->assertOk();
});

it('GET /cart as customer still renders 200', function () {
    $this->actingAs(p11ah1_customer())->get('/cart')->assertOk();
});

it('customer login flow still works (POST /login → 302 / → 200)', function () {
    $u = User::factory()->create([
        'email' => 'p11ah1-flow@p11ah1.test', 'password' => Hash::make('pw'), 'status' => 'active',
    ]);
    p11ah1_seed();
    $u->assignRole('customer');
    $this->post('/login', ['email' => 'p11ah1-flow@p11ah1.test', 'password' => 'pw'])
        ->assertRedirect('/');
    $this->get('/')->assertOk();
});

// ─── §1 — Container primitive present and correctly configured ──────

it('Container primitive file exists with the dev §1 padding scale', function () {
    $path = resource_path('js/Components/ui/v11/Container.tsx');
    expect(file_exists($path))->toBeTrue();
    $src = file_get_contents($path);
    // Dev §1 exact recommendation: mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8
    // Plus v11A.1 extension: xl:px-10
    foreach (['mx-auto', 'w-full', 'max-w-7xl', 'px-4', 'sm:px-6', 'lg:px-8', 'xl:px-10'] as $cls) {
        expect($src)->toContain($cls);
    }
});

// ─── §2 + §10 — v11A surfaces migrated to <Container> ───────────────

it('Welcome.tsx uses <Container>, NOT className="container-app"', function () {
    $src = file_get_contents(resource_path('js/Pages/Welcome.tsx'));
    expect($src)->toContain("from '@/Components/ui/v11/Container'");
    expect(substr_count($src, '<Container'))->toBeGreaterThanOrEqual(7);
    expect($src)->not->toContain('container-app');
});

it('StorefrontLayout uses <Container>, NOT className="container-app"', function () {
    $src = file_get_contents(resource_path('js/Layouts/StorefrontLayout.tsx'));
    expect($src)->toContain("from '@/Components/ui/v11/Container'");
    expect(substr_count($src, '<Container'))->toBeGreaterThanOrEqual(3);
    expect($src)->not->toContain('container-app');
});

// ─── §1 — Legacy .container-app CSS class also extended ─────────────

it('.container-app CSS class includes the v11A.1 xl:px-10 step', function () {
    $css = file_get_contents(resource_path('css/app.css'));
    // Find the .container-app definition and verify xl:px-10 is in it
    expect($css)->toMatch('/\.container-app\s*\{[^}]*xl:px-10[^}]*\}/');
});

it('Legacy .container-app CSS still has mobile through desktop padding (preservation)', function () {
    $css = file_get_contents(resource_path('css/app.css'));
    // Find the .container-app definition and verify ALL the breakpoints
    $matched = preg_match('/\.container-app\s*\{([^}]*)\}/', $css, $m);
    expect($matched)->toBe(1);
    foreach (['mx-auto', 'max-w-7xl', 'px-4', 'sm:px-6', 'lg:px-8', 'xl:px-10'] as $cls) {
        expect($m[1])->toContain($cls);
    }
});

// ─── §3 — Homepage sections still have section-level spacing ────────

it('Homepage sections still have vertical padding (py-* on Container)', function () {
    $src = file_get_contents(resource_path('js/Pages/Welcome.tsx'));
    // Each non-utility section should have a Container with py-N class
    expect($src)->toMatch('/<Container className="(?:[^"]*\s)?py-\d+/');
});

// ─── §4 — Mobile drawer has internal padding ─────────────────────────

it('Mobile drawer nav block has internal horizontal padding (px-4)', function () {
    $src = file_get_contents(resource_path('js/Layouts/StorefrontLayout.tsx'));
    // The nav list block should be wrapped in a px-4 padded container
    expect($src)->toMatch('/<div className="px-4 py-3"/');
});

// ─── §16 — All v11A markers preserved (regression-guard the redesign) ───

it('v11A homepage sections (7) still present after v11A.1', function () {
    $src = file_get_contents(resource_path('js/Pages/Welcome.tsx'));
    foreach ([
        'homepage-hero', 'homepage-trust', 'homepage-categories',
        'homepage-featured-products', 'homepage-deals-banner',
        'homepage-services', 'homepage-how-it-works',
    ] as $section) {
        expect($src)->toContain("data-testid=\"$section\"");
    }
});

it('v11A StorefrontLayout testids + v10.6 mobile drawer preserved', function () {
    $src = file_get_contents(resource_path('js/Layouts/StorefrontLayout.tsx'));
    expect($src)->toContain('storefront-header-v11a');
    expect($src)->toContain('storefront-footer-v11a');
    expect($src)->toContain('storefront-search');
    expect($src)->toContain('mobile-categories-toggle');
    expect($src)->toContain('mobile-categories-list');
});

it('v10.16 null-safe permissions pattern preserved through v11A.1', function () {
    $src = file_get_contents(resource_path('js/Pages/Welcome.tsx'));
    expect($src)->toMatch('/user\??\.?permissions \?\? \[\]/');
    expect($src)->not->toMatch('/user\.permissions\.length/');
});

// ─── v10.x preservation (full chain) ───────────────────────────────

it('All v10.x backend fixes preserved (no PHP modified in v11A.1)', function () {
    $mid = file_get_contents(app_path('Http/Middleware/HandleInertiaRequests.php'));
    // v10.11 §2 — permissions removed from share
    expect(substr_count($mid, '$request->user()->getAllPermissions()->pluck'))->toBe(0);
    // v10.14 scope-aware
    expect(substr_count($mid, "str_starts_with(\$path, 'admin/')"))->toBe(2);
    // v10.15 defensive (5)
    expect(substr_count($mid, 'defensive catch'))->toBe(5);

    $reports = file_get_contents(app_path('Domain/Reports/ReportsService.php'));
    expect(substr_count($reports, 'SUM(requested_amount_minor)'))->toBeGreaterThanOrEqual(2);
    expect($reports)->toContain("User::role('customer')");

    $ctrl = file_get_contents(app_path('Http/Controllers/Admin/ReportsController.php'));
    expect(substr_count($ctrl, 'guardAdminReportsAccess'))->toBe(3);
});

it('Both Welcome.tsx and StorefrontLayout still pass brace balance check', function () {
    foreach ([
        resource_path('js/Pages/Welcome.tsx'),
        resource_path('js/Layouts/StorefrontLayout.tsx'),
    ] as $f) {
        $src = file_get_contents($f);
        expect(substr_count($src, '{'))->toBe(substr_count($src, '}'));
        expect(substr_count($src, '('))->toBe(substr_count($src, ')'));
    }
});

// ─── Cross-cutting ──────────────────────────────────────────────────

it('VERSION reports Phase 11A v11A.1', function () {
    expect(trim((string) file_get_contents(base_path('VERSION'))))->toBe('Phase 11A v11A.1');
});
