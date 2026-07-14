<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── helpers ─────────────────────────────────────────────────────────────

function p1010_seed_roles_and_permissions(): void
{
    \Artisan::call('db:seed', [
        '--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder',
        '--force' => true,
    ]);
}

function p1010_admin(string $role = 'super_admin', string $status = 'active'): User
{
    p1010_seed_roles_and_permissions();
    $u = User::factory()->create(['status' => $status]);
    $u->assignRole($role);
    return $u->fresh();
}

function p1010_vendor(): User
{
    p1010_seed_roles_and_permissions();
    $u = User::factory()->create(['status' => 'active']);
    $u->assignRole('vendor');
    return $u->fresh();
}

function p1010_customer(): User
{
    p1010_seed_roles_and_permissions();
    $u = User::factory()->create(['status' => 'active']);
    $u->assignRole('customer');
    return $u->fresh();
}

// ─── §11.1+2+3 — Admin can access, returns 200 ──

it('super_admin reaches /admin/reports (HTTP 200) via direct guard', function () {
    $admin = p1010_admin('super_admin');
    $this->actingAs($admin)->get('/admin/reports')->assertOk();
});

it('admin_staff reaches /admin/reports (HTTP 200)', function () {
    $admin = p1010_admin('admin_staff');
    $this->actingAs($admin)->get('/admin/reports')->assertOk();
});

// ─── v10.10 specific: status != 'active' no longer blocks ──

it('admin with status != "active" can STILL access /admin/reports under v10.10', function () {
    $admin = p1010_admin('super_admin', 'enabled');
    $this->actingAs($admin)->get('/admin/reports')->assertOk();
});

it('admin with NULL status can STILL access /admin/reports under v10.10', function () {
    $admin = p1010_admin('super_admin');
    \DB::table('users')->where('id', $admin->id)->update(['status' => null]);
    $this->actingAs($admin->fresh())->get('/admin/reports')->assertOk();
});

// ─── v10.10 specific: broader role acceptance ──

it('user with custom "admin" role can access /admin/reports', function () {
    p1010_seed_roles_and_permissions();
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['status' => 'active']);
    $u->assignRole('admin');
    $this->actingAs($u->fresh())->get('/admin/reports')->assertOk();
});

it('user with custom "administrator" role can access /admin/reports', function () {
    p1010_seed_roles_and_permissions();
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'administrator', 'guard_name' => 'web']);
    $u = User::factory()->create(['status' => 'active']);
    $u->assignRole('administrator');
    $this->actingAs($u->fresh())->get('/admin/reports')->assertOk();
});

// ─── §11.6+7+8 — denial paths ──

it('vendor receives 403 on /admin/reports', function () {
    $this->actingAs(p1010_vendor())->get('/admin/reports')->assertForbidden();
});

it('customer receives 403 on /admin/reports', function () {
    $this->actingAs(p1010_customer())->get('/admin/reports')->assertForbidden();
});

it('guest is redirected to login from /admin/reports', function () {
    $this->get('/admin/reports')->assertRedirect('/login');
});

// ─── §11.11 — Export uses the SAME guard ──

it('admin can hit /admin/reports/export.csv (uses same guard)', function () {
    $admin = p1010_admin('super_admin');
    $resp = $this->actingAs($admin)->get('/admin/reports/export.csv');
    expect($resp->getStatusCode())->not->toBe(403);
});

it('vendor cannot hit /admin/reports/export.csv (403)', function () {
    $this->actingAs(p1010_vendor())->get('/admin/reports/export.csv')->assertForbidden();
});

// ─── §11.9 — Permission seeder idempotent ──

it('EnsureAdminReportsAccessSeeder is idempotent', function () {
    p1010_seed_roles_and_permissions();
    User::factory()->create(['email' => 'admin@marketplace.test', 'status' => 'active'])
        ->assignRole('super_admin');

    \Artisan::call('db:seed', [
        '--class' => 'Database\\Seeders\\EnsureAdminReportsAccessSeeder',
        '--force' => true,
    ]);
    $rolesAfterFirst = \Spatie\Permission\Models\Role::count();
    $userRolesAfterFirst = \DB::table('model_has_roles')->count();

    \Artisan::call('db:seed', [
        '--class' => 'Database\\Seeders\\EnsureAdminReportsAccessSeeder',
        '--force' => true,
    ]);
    expect(\Spatie\Permission\Models\Role::count())->toBe($rolesAfterFirst);
    expect(\DB::table('model_has_roles')->count())->toBe($userRolesAfterFirst);
});

// ─── §11.12 — Existing admin repaired by targeted seeder ──

it('EnsureAdminReportsAccessSeeder repairs an admin user without a role', function () {
    p1010_seed_roles_and_permissions();
    $admin = User::factory()->create(['email' => 'admin@marketplace.test', 'status' => null]);
    expect($admin->fresh()->canManageAdminReports())->toBeFalse();

    \Artisan::call('db:seed', [
        '--class' => 'Database\\Seeders\\EnsureAdminReportsAccessSeeder',
        '--force' => true,
    ]);

    $admin = $admin->fresh();
    expect($admin->status)->toBe('active');
    expect($admin->hasRole('super_admin'))->toBeTrue();
    expect($admin->canManageAdminReports())->toBeTrue();
});

// ─── reports:repair-access command ──

it('reports:repair-access command repairs the dev\'s actual admin', function () {
    p1010_seed_roles_and_permissions();
    $admin = User::factory()->create(['email' => 'admin@dev.test', 'status' => 'enabled']);

    expect($admin->fresh()->canManageAdminReports())->toBeFalse();

    \Artisan::call('reports:repair-access', [
        'email'        => 'admin@dev.test',
        '--no-confirm' => true,
    ]);

    $admin = $admin->fresh();
    expect($admin->status)->toBe('active');
    expect($admin->hasRole('super_admin'))->toBeTrue();
    expect($admin->canManageAdminReports())->toBeTrue();
});

it('reports:repair-access reports failure when user not found', function () {
    p1010_seed_roles_and_permissions();
    $code = \Artisan::call('reports:repair-access', [
        'email'        => 'does-not-exist@test.test',
        '--no-confirm' => true,
    ]);
    expect($code)->toBe(\Symfony\Component\Console\Command\Command::FAILURE);
});

// ─── reports:diagnose-access command ──

it('reports:diagnose-access runs successfully against an existing admin', function () {
    $admin = p1010_admin('super_admin');
    $code = \Artisan::call('reports:diagnose-access', ['email' => $admin->email]);
    expect($code)->toBe(\Symfony\Component\Console\Command\Command::SUCCESS);
    $output = \Artisan::output();
    expect($output)->toContain('canManageAdminReports():');
    expect($output)->toContain('true');
});

it('reports:diagnose-access flags the failing case', function () {
    p1010_seed_roles_and_permissions();
    $u = User::factory()->create(['email' => 'broken@test.test', 'status' => null]);
    \Artisan::call('reports:diagnose-access', ['email' => $u->email]);
    $output = \Artisan::output();
    expect($output)->toContain('DENIED');
});

// ─── Cross-cutting ──

it('VERSION reports Phase 10 v10.10', function () {
    expect(trim((string) file_get_contents(base_path('VERSION'))))->toBe('Phase 10 v10.10');
});
