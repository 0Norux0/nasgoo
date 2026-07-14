<?php

declare(strict_types=1);

use App\Domain\Reports\ReportsService;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── helpers ─────────────────────────────────────────────────────────────

function p1012_seed_roles_and_permissions(): void
{
    \Artisan::call('db:seed', [
        '--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder',
        '--force' => true,
    ]);
}

function p1012_admin(): User
{
    p1012_seed_roles_and_permissions();
    $u = User::factory()->create(['status' => 'active']);
    $u->assignRole('super_admin');
    return $u->fresh();
}

function p1012_make_user_with_role(string $roleName): User
{
    p1012_seed_roles_and_permissions();
    $u = User::factory()->create(['status' => 'active']);
    $u->assignRole($roleName);
    return $u->fresh();
}

// ─── §1+2 — /admin/reports loads with no SQL error ────────────────────

it('admin /admin/reports loads with HTTP 200 (no Unknown column role error)', function () {
    $admin = p1012_admin();
    // No customer users yet; query must still return 0, NOT a column-not-found error
    $resp = $this->actingAs($admin)->get('/admin/reports');
    $resp->assertOk();
});

it('admin /admin/reports loads when customer users exist', function () {
    $admin = p1012_admin();
    // Create 3 customers via the canonical Spatie path
    for ($i = 0; $i < 3; $i++) {
        p1012_make_user_with_role('customer');
    }
    $resp = $this->actingAs($admin)->get('/admin/reports');
    $resp->assertOk();
});

// ─── §3+4 — Replaced queries use Spatie role architecture ────────────

it('customers_total counts users with Spatie customer role', function () {
    p1012_seed_roles_and_permissions();
    p1012_make_user_with_role('customer');
    p1012_make_user_with_role('customer');
    p1012_make_user_with_role('vendor');

    $svc = app(ReportsService::class);
    $counts = $svc->marketplaceCounts();

    expect($counts['customers_total'])->toBe(2);
});

it('customers_total returns 0 when no customer users exist (no SQL exception)', function () {
    p1012_seed_roles_and_permissions();
    // Only an admin exists
    p1012_make_user_with_role('super_admin');

    $svc = app(ReportsService::class);
    $counts = $svc->marketplaceCounts();

    expect($counts['customers_total'])->toBe(0);
});

it('a user with multiple roles is not double-counted in customers_total', function () {
    p1012_seed_roles_and_permissions();
    // Edge case: a user with both customer AND admin_staff roles should
    // be counted once when querying for customers. Spatie's role() scope
    // joins model_has_roles -> roles where roles.name = ?; an INNER JOIN
    // on a single role name produces a single row per user. Verify.
    $u = User::factory()->create(['status' => 'active']);
    $u->assignRole('customer');
    $u->assignRole('admin_staff');

    $svc = app(ReportsService::class);
    $counts = $svc->marketplaceCounts();

    expect($counts['customers_total'])->toBe(1);
});

it('vendor counts hit the vendors table by status (not users.role)', function () {
    p1012_seed_roles_and_permissions();
    // Create 2 vendor users + 2 vendor records (1 approved, 1 pending)
    $u1 = p1012_make_user_with_role('vendor');
    Vendor::create([
        'user_id' => $u1->id, 'business_name' => 'A', 'business_email' => 'a@p1012.test',
        'business_type' => 'company', 'country' => 'KW', 'status' => Vendor::STATUS_APPROVED,
    ]);
    $u2 = p1012_make_user_with_role('vendor');
    Vendor::create([
        'user_id' => $u2->id, 'business_name' => 'B', 'business_email' => 'b@p1012.test',
        'business_type' => 'company', 'country' => 'KW', 'status' => Vendor::STATUS_PENDING,
    ]);

    $svc = app(ReportsService::class);
    $counts = $svc->marketplaceCounts();

    expect($counts['vendors_approved'])->toBe(1);
    expect($counts['vendors_pending'])->toBe(1);
});

// ─── §3 — Regression guard on the SQL pattern (source check) ──────────

it('ReportsService source no longer queries users.role column', function () {
    $src = file_get_contents(app_path('Domain/Reports/ReportsService.php'));
    // Pre-v10.12 had DB::table('users')->where('role', 'customer'). Verify
    // the regression pattern is gone. The Spatie scope is the fix.
    expect($src)->not->toMatch("/DB::table\\(['\\\"]users['\\\"]\\)->where\\(['\\\"]role['\\\"]/");
    expect($src)->not->toMatch("/User::where\\(['\\\"]role['\\\"]/");
    expect($src)->toContain("User::role('customer')");
});

// ─── §8 — Authorization unchanged ─────────────────────────────────────

it('vendor receives 403 on /admin/reports (auth preserved)', function () {
    $u = p1012_make_user_with_role('vendor');
    $this->actingAs($u)->get('/admin/reports')->assertForbidden();
});

it('customer receives 403 on /admin/reports (auth preserved)', function () {
    $u = p1012_make_user_with_role('customer');
    $this->actingAs($u)->get('/admin/reports')->assertForbidden();
});

it('guest is redirected from /admin/reports (auth preserved)', function () {
    $this->get('/admin/reports')->assertRedirect('/login');
});

// ─── §9.15 — No users.role column dependency ─────────────────────────

it('fresh migration creates a users table without a role column', function () {
    // The fix MUST work with the canonical schema — i.e. no migration
    // expecting users.role to exist.
    $columns = \Schema::getColumnListing('users');
    expect($columns)->not->toContain('role');
    // Sanity check: the canonical columns ARE there
    expect($columns)->toContain('id');
    expect($columns)->toContain('email');
    expect($columns)->toContain('status');
});

it('Spatie role-pivot tables exist (the actual role architecture)', function () {
    expect(\Schema::hasTable('roles'))->toBeTrue();
    expect(\Schema::hasTable('model_has_roles'))->toBeTrue();
    expect(\Schema::getColumnListing('roles'))->toContain('name');
});

// ─── §14 — Export uses the same corrected service ─────────────────────

it('admin /admin/reports/export.csv loads (uses ReportsService, must also be clean)', function () {
    $admin = p1012_admin();
    $resp = $this->actingAs($admin)->get('/admin/reports/export.csv');
    expect($resp->getStatusCode())->not->toBe(500);
    expect($resp->getStatusCode())->not->toBe(403);
});

// ─── Cross-cutting ───────────────────────────────────────────────────

it('VERSION reports Phase 10 v10.12', function () {
    expect(trim((string) file_get_contents(base_path('VERSION'))))->toBe('Phase 10 v10.12');
});

it('v10.11 §5 payout fix preserved', function () {
    // Belt-and-suspenders: ensure v10.12 doesn't regress v10.11
    $src = file_get_contents(app_path('Domain/Reports/ReportsService.php'));
    expect($src)->toContain('SUM(requested_amount_minor)');
    expect($src)->not->toContain('SUM(amount_minor)');
});
