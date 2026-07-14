<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

// ─── helpers ─────────────────────────────────────────────────────────────

function p1016_seed(): void
{
    \Artisan::call('db:seed', [
        '--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder',
        '--force' => true,
    ]);
}

function p1016_customer(): User
{
    p1016_seed();
    $u = User::factory()->create([
        'email' => 'p1016-customer-' . uniqid() . '@p1016.test',
        'password' => Hash::make('pw'),
        'status' => 'active',
    ]);
    $u->assignRole('customer');
    return $u->fresh();
}

function p1016_vendor_user(): User
{
    p1016_seed();
    $u = User::factory()->create([
        'email' => 'p1016-vendor-' . uniqid() . '@p1016.test',
        'password' => Hash::make('pw'),
        'status' => 'active',
    ]);
    $u->assignRole('vendor');
    Vendor::create([
        'user_id' => $u->id, 'business_name' => 'V' . uniqid(),
        'business_email' => 'v' . uniqid() . '@p1016.test',
        'business_type' => 'company', 'country' => 'KW',
        'status' => Vendor::STATUS_APPROVED,
    ]);
    return $u->fresh();
}

function p1016_admin(): User
{
    p1016_seed();
    $u = User::factory()->create([
        'email' => 'p1016-admin-' . uniqid() . '@p1016.test',
        'password' => Hash::make('pw'),
        'status' => 'active',
    ]);
    $u->assignRole('super_admin');
    return $u->fresh();
}

// ─── §11.1 — Guest can render the homepage ──────────────────────────

it('GET / as guest returns HTTP 200 with the Welcome Inertia component', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Welcome'));
});

// ─── §11.2 — Authenticated customer can render the homepage ─────────

it('GET / as authenticated customer returns HTTP 200 with the Welcome Inertia component', function () {
    $u = p1016_customer();
    $this->actingAs($u)
        ->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Welcome'));
});

// ─── §11.4 — auth.user contract matches frontend types ──────────────

it('shared auth.user contract does NOT include `permissions` (v10.11 §2 perf removal preserved)', function () {
    $u = p1016_customer();
    $this->actingAs($u)
        ->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('auth.user.id', $u->id)
            ->where('auth.user.email', $u->email)
            ->has('auth.user.roles')
            ->where('auth.user.is_admin', false)
            // permissions MUST NOT be in the share (v10.11 §2 perf removal)
            ->missing('auth.user.permissions')
        );
});

// ─── §11.5 — Customer without permissions renders ───────────────────

it('customer without a permissions property in the share still renders / without 500', function () {
    // This is the EXACT failure mode the dev reported. Pre-v10.16 the
    // Welcome.tsx component crashed because user.permissions was undefined.
    // The backend HTTP 200 — the React render exploded. We can't catch a
    // React runtime exception from PHPUnit, but we CAN verify:
    //   1. The backend HTTP response is 200 (was always true)
    //   2. The backend share does NOT include permissions
    //   3. The frontend source no longer does .permissions.length on a
    //      possibly-undefined value (verified by Phase 10 v10.16 CI sub-check)
    $u = p1016_customer();
    $resp = $this->actingAs($u)->get('/');
    $resp->assertOk();
    expect($resp->json('props.auth.user.permissions'))->toBeNull();
});

// ─── §11.6 — Guest with auth.user = null renders ────────────────────

it('guest with auth.user = null can render the homepage', function () {
    $resp = $this->get('/');
    $resp->assertOk();
    expect($resp->json('props.auth.user'))->toBeNull();
});

// ─── §11.7 — Optional props default-safe ─────────────────────────────

it('cart_summary is null for guests (defensive default-safe)', function () {
    $resp = $this->get('/');
    expect($resp->json('props.cart_summary'))->toBeNull();
});

it('top_categories is at minimum an array (never undefined) on /', function () {
    $resp = $this->get('/');
    $tc = $resp->json('props.top_categories');
    expect(is_array($tc))->toBeTrue();
});

// ─── §11.8 — Customer login redirects to a renderable homepage ──────

it('customer login flow ends at a renderable / (POST /login → 302 / → 200)', function () {
    $u = User::factory()->create([
        'email' => 'p1016-flow@p1016.test',
        'password' => Hash::make('pw'),
        'status' => 'active',
    ]);
    p1016_seed();
    $u->assignRole('customer');

    $this->post('/login', ['email' => 'p1016-flow@p1016.test', 'password' => 'pw'])
        ->assertRedirect('/');

    // Browser follows the redirect → GET /. Must be 200.
    $this->get('/')->assertOk();
});

// ─── §11.9 — Vendor homepage remains renderable ─────────────────────

it('GET /vendor as authenticated vendor returns 200 (no regression from v10.16)', function () {
    $this->actingAs(p1016_vendor_user())->get('/vendor')->assertOk();
});

// ─── §11.10 — Admin homepage remains renderable ─────────────────────

it('admin reports surface remains renderable (no regression from v10.16)', function () {
    $this->actingAs(p1016_admin())->get('/admin/reports')->assertOk();
});

// ─── §3 + §8 — Frontend source has no unsafe permissions access ─────

it('Welcome.tsx has no direct user.permissions.length / .map / .filter / .reduce access', function () {
    $src = file_get_contents(resource_path('js/Pages/Welcome.tsx'));
    // The exact unsafe patterns that crashed React on a blank homepage
    expect($src)->not->toMatch('/user\.permissions\.length/');
    expect($src)->not->toMatch('/user\.permissions\.map\(/');
    expect($src)->not->toMatch('/user\.permissions\.filter\(/');
    expect($src)->not->toMatch('/user\.permissions\.reduce\(/');
    expect($src)->not->toMatch('/user\.permissions\.forEach\(/');
});

it('Welcome.tsx defensively normalizes permissions via nullish coalescing', function () {
    $src = file_get_contents(resource_path('js/Pages/Welcome.tsx'));
    expect($src)->toContain('user.permissions ?? []');
});

it('Welcome.tsx has the v10.16 §4 marker (regression-guard the fix)', function () {
    $src = file_get_contents(resource_path('js/Pages/Welcome.tsx'));
    expect($src)->toContain('Phase 10 v10.16 §4');
});

// ─── §5 + §11.11 — TypeScript contract reflects backend ─────────────

it('AuthUser.permissions is declared optional in inertia.d.ts (matches runtime)', function () {
    $dts = file_get_contents(resource_path('js/types/inertia.d.ts'));
    // Must have the optional form
    expect($dts)->toMatch('/permissions\?: string\[\]/');
    // Must NOT have the required form
    expect($dts)->not->toMatch('/^\s+permissions: string\[\];/m');
});

// ─── §9 — Performance optimization preserved ────────────────────────

it('v10.11 §2 perf preserved: getAllPermissions absent from default share', function () {
    $mid = file_get_contents(app_path('Http/Middleware/HandleInertiaRequests.php'));
    expect(substr_count($mid, '$request->user()->getAllPermissions()->pluck'))->toBe(0);
});

it('v10.15 defensive wrappings still in place (5 markers + HomeController)', function () {
    $mid = file_get_contents(app_path('Http/Middleware/HandleInertiaRequests.php'));
    foreach ([
        'auth.user share closure failed',
        'cart_summary share closure failed',
        'top_categories share closure failed',
        'translations cache failed',
        'app.version cache failed',
    ] as $marker) {
        expect($mid)->toContain($marker);
    }
    $hc = file_get_contents(app_path('Http/Controllers/HomeController.php'));
    expect($hc)->toContain('homepage health cache failed');
});

// ─── Preservation: v10.14 perf optimizations ────────────────────────

it('v10.14 scope-aware closures preserved', function () {
    $mid = file_get_contents(app_path('Http/Middleware/HandleInertiaRequests.php'));
    expect(substr_count($mid, "str_starts_with(\$path, 'admin/')"))->toBe(2);
    expect(substr_count($mid, "str_starts_with(\$path, 'vendor/')"))->toBe(2);
});

it('v10.14 indexes migration preserved', function () {
    expect(file_exists(database_path('migrations/2026_06_21_000001_add_phase10_v1014_performance_indexes.php')))->toBeTrue();
});

// ─── Cross-cutting ──────────────────────────────────────────────────

it('VERSION reports Phase 10 v10.16', function () {
    expect(trim((string) file_get_contents(base_path('VERSION'))))->toBe('Phase 10 v10.16');
});

it('v10.0-v10.15 preservation: every prior fix marker intact', function () {
    $reports = file_get_contents(app_path('Domain/Reports/ReportsService.php'));
    expect($reports)->toContain("User::role('customer')");
    expect(substr_count($reports, 'SUM(requested_amount_minor)'))->toBeGreaterThanOrEqual(2);

    $vLayout = file_get_contents(resource_path('js/Layouts/VendorLayout.tsx'));
    expect($vLayout)->toContain('ReportsIcon');
    expect($vLayout)->toContain('vendor-nav-reports');

    $dash = file_get_contents(resource_path('js/Pages/Vendor/Dashboard.tsx'));
    expect($dash)->toContain('vendor-dashboard-reports-cta');

    $reportsCtrl = file_get_contents(app_path('Http/Controllers/Admin/ReportsController.php'));
    expect(substr_count($reportsCtrl, 'guardAdminReportsAccess'))->toBe(3);
});
