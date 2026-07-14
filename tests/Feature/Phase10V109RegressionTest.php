<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── helpers ─────────────────────────────────────────────────────────────

function p109_seed_roles_and_permissions(): void
{
    // The RolesAndPermissionsSeeder is the canonical source; calling it
    // ensures every test in this file has the real role/permission table.
    \Artisan::call('db:seed', [
        '--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder',
        '--force' => true,
    ]);
}

function p109_admin(string $role = 'super_admin'): User
{
    p109_seed_roles_and_permissions();
    $u = User::factory()->create(['status' => 'active']);
    $u->assignRole($role);
    return $u->fresh();
}

function p109_vendor(): User
{
    p109_seed_roles_and_permissions();
    $u = User::factory()->create(['status' => 'active']);
    $u->assignRole('vendor');
    return $u->fresh();
}

function p109_customer(): User
{
    p109_seed_roles_and_permissions();
    $u = User::factory()->create(['status' => 'active']);
    $u->assignRole('customer');
    return $u->fresh();
}

// ─── §9.1 — Admin with correct role can access /admin/reports ──

it('super_admin can access /admin/reports (HTTP 200)', function () {
    $admin = p109_admin('super_admin');
    $resp = $this->actingAs($admin)->get('/admin/reports');
    $resp->assertOk();
});

it('admin_staff can access /admin/reports (HTTP 200)', function () {
    $admin = p109_admin('admin_staff');
    $resp = $this->actingAs($admin)->get('/admin/reports');
    $resp->assertOk();
});

// ─── §9.4 — Vendor receives 403 ──

it('vendor receives 403 on /admin/reports', function () {
    $vendor = p109_vendor();
    $resp = $this->actingAs($vendor)->get('/admin/reports');
    $resp->assertForbidden();
});

// ─── §9.5 — Customer receives 403 ──

it('customer receives 403 on /admin/reports', function () {
    $customer = p109_customer();
    $resp = $this->actingAs($customer)->get('/admin/reports');
    $resp->assertForbidden();
});

// ─── §9.6 — Guest is redirected to login ──

it('guest is redirected to login from /admin/reports', function () {
    $resp = $this->get('/admin/reports');
    $resp->assertRedirect('/login');
});

// ─── §9.7 — Admin role receives the reports permission after seeding ──

it('seeded super_admin role holds reports.view permission', function () {
    p109_seed_roles_and_permissions();
    $role = \Spatie\Permission\Models\Role::where('name', 'super_admin')->where('guard_name', 'web')->first();
    expect($role)->not->toBeNull();
    expect($role->hasPermissionTo('reports.view'))->toBeTrue();
});

it('seeded admin_staff role holds reports.view permission', function () {
    p109_seed_roles_and_permissions();
    $role = \Spatie\Permission\Models\Role::where('name', 'admin_staff')->where('guard_name', 'web')->first();
    expect($role)->not->toBeNull();
    expect($role->hasPermissionTo('reports.view'))->toBeTrue();
});

// ─── §9.8 — Re-running the seeder remains idempotent ──

it('running RolesAndPermissionsSeeder twice does not duplicate rows', function () {
    p109_seed_roles_and_permissions();
    $rolesAfterFirst = \Spatie\Permission\Models\Role::count();
    $permsAfterFirst = \Spatie\Permission\Models\Permission::count();
    p109_seed_roles_and_permissions();
    expect(\Spatie\Permission\Models\Role::count())->toBe($rolesAfterFirst);
    expect(\Spatie\Permission\Models\Permission::count())->toBe($permsAfterFirst);
});

// ─── §9.9 — Permission guard matches the web guard ──

it('reports.view permission has guard_name = web (matches User default guard)', function () {
    p109_seed_roles_and_permissions();
    $p = \Spatie\Permission\Models\Permission::where('name', 'reports.view')->first();
    expect($p)->not->toBeNull();
    expect($p->guard_name)->toBe('web');
});

// ─── §9.10 — Admin report export is authorized correctly ──

it('admin can access /admin/reports/export.csv', function () {
    $admin = p109_admin('super_admin');
    $resp = $this->actingAs($admin)->get('/admin/reports/export.csv');
    // 200 (with CSV body) or a streamed response — anything not 403
    expect($resp->getStatusCode())->not->toBe(403);
});

it('vendor cannot access /admin/reports/export.csv (403)', function () {
    $vendor = p109_vendor();
    $resp = $this->actingAs($vendor)->get('/admin/reports/export.csv');
    $resp->assertForbidden();
});

// ─── §9.11 — Vendor reports access remains separate and working ──

it('vendor can access /vendor/reports (separate surface from admin reports)', function () {
    $vendor = p109_vendor();
    // The vendor needs a Vendor model attached. Most factories create users
    // but not vendors; create one explicitly.
    \App\Models\Vendor::create([
        'user_id'        => $vendor->id,
        'business_name'  => 'V'.uniqid(),
        'business_email' => 'v'.uniqid().'@p109.test',
        'business_type'  => 'company',
        'country'        => 'KW',
        'status'         => \App\Models\Vendor::STATUS_APPROVED,
    ]);
    $resp = $this->actingAs($vendor)->get('/vendor/reports');
    // Vendor reports is its own surface; must NOT 403 the vendor.
    expect($resp->getStatusCode())->not->toBe(403);
});

// ─── Cross-cutting ──

it('inactive admin user is denied on /admin/reports', function () {
    $admin = p109_admin('super_admin');
    $admin->update(['status' => 'suspended']);
    $resp = $this->actingAs($admin)->get('/admin/reports');
    // canManageAdminReports() returns false for non-active users → 403
    $resp->assertForbidden();
});

it('Gate::before grants super_admin every ability (defense in depth)', function () {
    $admin = p109_admin('super_admin');
    // Even an ability not explicitly defined as a Gate should pass for
    // super_admin via Gate::before — the v10.9 defense-in-depth pattern.
    expect(\Gate::forUser($admin)->allows('this-ability-does-not-exist'))->toBeTrue();
});

it('viewReports Gate uses the canonical canManageAdminReports method', function () {
    // The Gate must NOT have regressed back to hasPermissionTo('reports.view').
    // This is checked via the source string for belt-and-suspenders — the
    // runtime behavior is covered by the 200/403 tests above. We just want
    // a clear failure mode if a future refactor reverts the v10.9 collapse.
    $src = file_get_contents(app_path('Providers/AppServiceProvider.php'));
    expect($src)->toContain('canManageAdminReports()');
    expect($src)->not->toContain("hasPermissionTo('reports.view')");
});

it('VERSION reports Phase 10 v10.9', function () {
    expect(trim((string) file_get_contents(base_path('VERSION'))))->toBe('Phase 10 v10.9');
});
