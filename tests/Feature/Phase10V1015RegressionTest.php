<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

// ─── helpers ─────────────────────────────────────────────────────────────

function p1015_seed(): void
{
    \Artisan::call('db:seed', [
        '--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder',
        '--force' => true,
    ]);
}

function p1015_customer_with_password(string $email, string $password = 'password'): User
{
    p1015_seed();
    $u = User::factory()->create([
        'email'    => $email,
        'password' => Hash::make($password),
        'status'   => 'active',
    ]);
    $u->assignRole('customer');
    return $u->fresh();
}

function p1015_vendor_user_with_password(string $email, string $password = 'password'): User
{
    p1015_seed();
    $u = User::factory()->create([
        'email'    => $email,
        'password' => Hash::make($password),
        'status'   => 'active',
    ]);
    $u->assignRole('vendor');
    Vendor::create([
        'user_id' => $u->id, 'business_name' => 'V' . uniqid(),
        'business_email' => 'v' . uniqid() . '@p1015.test',
        'business_type' => 'company', 'country' => 'KW',
        'status' => Vendor::STATUS_APPROVED,
    ]);
    return $u->fresh();
}

function p1015_admin_with_password(string $email, string $password = 'password'): User
{
    p1015_seed();
    $u = User::factory()->create([
        'email'    => $email,
        'password' => Hash::make($password),
        'status'   => 'active',
    ]);
    $u->assignRole('super_admin');
    return $u->fresh();
}

// ─── §14.1 — Customer can open login page ───────────────────────────

it('customer can GET /login (HTTP 200, Inertia page renders)', function () {
    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Auth/Login'));
});

it('shared props render on /login even when no user is authenticated', function () {
    // The dev's report was "customer cannot log in at all" — that includes
    // the possibility the login page itself doesn't render. Verify
    // share() completes successfully for an unauthenticated request.
    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('app')
            ->has('marketplace')
            ->has('translations')
            ->has('seo')
            ->where('auth.user', null)
        );
});

// ─── §14.2 — Valid customer credentials authenticate ─────────────────

it('customer POSTs /login with valid credentials and is authenticated', function () {
    $u = p1015_customer_with_password('customer@p1015.test', 'secret-pw-1');

    $this->post('/login', [
        'email'    => 'customer@p1015.test',
        'password' => 'secret-pw-1',
    ])->assertRedirect('/');

    expect(auth()->check())->toBeTrue();
    expect(auth()->id())->toBe($u->id);
});

// ─── §14.3 — Session regenerated after login ─────────────────────────

it('session is regenerated after a successful customer login', function () {
    p1015_customer_with_password('customer-regen@p1015.test', 'secret-pw-2');

    $this->withSession(['_token' => 'oldtoken'])
        ->post('/login', [
            'email' => 'customer-regen@p1015.test', 'password' => 'secret-pw-2',
        ]);

    // After regenerate(), the session ID has changed — verify token rotated
    expect(session('_token'))->not->toBe('oldtoken');
});

// ─── §14.4 — Customer remains authenticated after redirect ──────────

it('customer remains authenticated when following the post-login redirect to /', function () {
    p1015_customer_with_password('customer-stick@p1015.test', 'secret-pw-3');

    // Follow the full POST → 302 → GET / flow
    $resp = $this->post('/login', [
        'email' => 'customer-stick@p1015.test', 'password' => 'secret-pw-3',
    ]);
    $resp->assertRedirect('/');

    // Now GET the redirect target. Customer must be authenticated AND the
    // page must return 200 (not 500 — that's what the dev's report indicated).
    $this->get('/')->assertOk();
    expect(auth()->check())->toBeTrue();
});

// ─── §14.5 — Customer reaches the intended landing page ──────────────

it('customer GET / after login renders 200 with the Welcome Inertia component', function () {
    $u = p1015_customer_with_password('customer-land@p1015.test', 'secret-pw-4');

    $this->actingAs($u)
        ->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Welcome'));
});

// ─── §14.6 — Invalid password is rejected ────────────────────────────

it('invalid password is rejected with a validation error', function () {
    p1015_customer_with_password('customer-bad@p1015.test', 'right-pw');

    $resp = $this->post('/login', [
        'email' => 'customer-bad@p1015.test',
        'password' => 'wrong-pw',
    ]);
    $resp->assertSessionHasErrors(['email']);
    expect(auth()->check())->toBeFalse();
});

// ─── §14.7 — Unknown account is rejected ─────────────────────────────

it('unknown email is rejected with a validation error', function () {
    p1015_seed();
    $resp = $this->post('/login', [
        'email' => 'does-not-exist@p1015.test',
        'password' => 'anything',
    ]);
    $resp->assertSessionHasErrors(['email']);
    expect(auth()->check())->toBeFalse();
});

// ─── §14.10 — Vendor login still works ──────────────────────────────

it('vendor can POST /login and is redirected to /vendor (not regressed by v10.15)', function () {
    p1015_vendor_user_with_password('vendor-stick@p1015.test', 'vendor-pw');

    $this->post('/login', [
        'email' => 'vendor-stick@p1015.test', 'password' => 'vendor-pw',
    ])->assertRedirect('/vendor');

    expect(auth()->check())->toBeTrue();
});

// ─── §14.11 — Admin login (via /admin/login, separate flow) ─────────

it('admin attempting /login is rejected with the "must use /admin/login" message', function () {
    // v3.3 design: admins MUST use /admin/login (Filament panel). /login
    // explicitly rejects them with a validation error. Confirm this is
    // preserved in v10.15.
    p1015_admin_with_password('admin-stop@p1015.test', 'admin-pw');

    $resp = $this->post('/login', [
        'email' => 'admin-stop@p1015.test', 'password' => 'admin-pw',
    ]);
    $resp->assertSessionHasErrors(['email']);
    expect(auth()->check())->toBeFalse();
});

// ─── §14.12 — Logout works ──────────────────────────────────────────

it('customer logout invalidates the session', function () {
    $u = p1015_customer_with_password('customer-logout@p1015.test', 'pw');
    $this->actingAs($u)->post('/logout')->assertRedirect('/');
    expect(auth()->check())->toBeFalse();
});

// ─── §14.13+14 — Customer cannot access vendor/admin routes ─────────

it('customer cannot access /vendor routes', function () {
    $u = p1015_customer_with_password('customer-cantvendor@p1015.test', 'pw');
    $resp = $this->actingAs($u)->get('/vendor');
    expect($resp->getStatusCode())->toBeIn([302, 403]);
});

it('customer cannot access /admin/reports', function () {
    $u = p1015_customer_with_password('customer-cantadmin@p1015.test', 'pw');
    $resp = $this->actingAs($u)->get('/admin/reports');
    expect($resp->getStatusCode())->toBeIn([302, 403]);
});

// ─── §14.15+16 — Shared Inertia props render after customer login ───

it('all shared Inertia props render correctly after customer login (no exception)', function () {
    $u = p1015_customer_with_password('customer-shared@p1015.test', 'pw');

    $this->actingAs($u)
        ->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('app.version')
            ->has('app.locale')
            ->has('translations')
            ->where('auth.user.id', $u->id)
            ->where('auth.user.email', 'customer-shared@p1015.test')
            ->has('auth.user.roles')
            ->where('auth.user.is_admin', false)
            // cart_summary is keyed even when null — assert structure
            ->has('cart_summary')
            ->has('top_categories')
        );
});

// ─── §11 + v10.15 — Defensive wrapping survives even if a downstream throws ──

it('v10.15 source has try/catch wrapping on EVERY share() closure', function () {
    $src = file_get_contents(app_path('Http/Middleware/HandleInertiaRequests.php'));
    foreach ([
        'auth.user share closure failed (Phase 10 v10.15 defensive catch)',
        'cart_summary share closure failed (Phase 10 v10.15 defensive catch)',
        'top_categories share closure failed (Phase 10 v10.15 defensive catch)',
        'translations cache failed (Phase 10 v10.15 defensive catch)',
        'app.version cache failed (Phase 10 v10.15 defensive catch)',
    ] as $marker) {
        expect($src)->toContain($marker);
    }
});

it('v10.15 HomeController health probe has try/catch fallback to direct probes', function () {
    $src = file_get_contents(app_path('Http/Controllers/HomeController.php'));
    expect($src)->toContain('homepage health cache failed (Phase 10 v10.15 defensive catch)');
    // v10.14 cache key preserved
    expect($src)->toContain('marketplace:homepage_health:v1');
});

// ─── Preservation: v10.14 optimizations remain in place ─────────────

it('v10.14 scope-aware admin/vendor exclusion still active (cart_summary + top_categories)', function () {
    $src = file_get_contents(app_path('Http/Middleware/HandleInertiaRequests.php'));
    expect(substr_count($src, "str_starts_with(\$path, 'admin/')"))->toBe(2);
    expect(substr_count($src, "str_starts_with(\$path, 'vendor/')"))->toBe(2);
});

it('v10.14 perf indexes migration still present', function () {
    expect(file_exists(database_path('migrations/2026_06_21_000001_add_phase10_v1014_performance_indexes.php')))->toBeTrue();
});

// ─── Cross-cutting ──────────────────────────────────────────────────

it('VERSION reports Phase 10 v10.15', function () {
    expect(trim((string) file_get_contents(base_path('VERSION'))))->toBe('Phase 10 v10.15');
});

it('v10.0-v10.14 preservation: every prior fix marker intact', function () {
    $reports = file_get_contents(app_path('Domain/Reports/ReportsService.php'));
    expect($reports)->toContain("User::role('customer')");          // v10.12
    expect(substr_count($reports, 'SUM(requested_amount_minor)'))->toBeGreaterThanOrEqual(2); // v10.11 §5

    $vendorLayout = file_get_contents(resource_path('js/Layouts/VendorLayout.tsx'));
    expect($vendorLayout)->toContain('ReportsIcon');                // v10.13
    expect($vendorLayout)->toContain('vendor-nav-reports');

    $dashboard = file_get_contents(resource_path('js/Pages/Vendor/Dashboard.tsx'));
    expect($dashboard)->toContain('vendor-dashboard-reports-cta');  // v10.13

    $reportsCtrl = file_get_contents(app_path('Http/Controllers/Admin/ReportsController.php'));
    expect(substr_count($reportsCtrl, 'guardAdminReportsAccess'))->toBe(3); // v10.10
});
