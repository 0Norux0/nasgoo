<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('seeds the four canonical roles', function () {
    expect(Role::pluck('name')->toArray())
        ->toContain('super_admin')
        ->toContain('admin_staff')
        ->toContain('vendor')
        ->toContain('customer');
});

it('seeds all the required permissions', function () {
    $required = [
        'users.view', 'users.create', 'users.update', 'users.delete',
        'roles.view', 'roles.manage',
        'settings.view', 'settings.manage',
        'vendors.view', 'vendors.approve', 'vendors.suspend',
        'products.view', 'products.approve',
        'orders.view', 'orders.manage',
        'payouts.approve', 'commissions.manage',
        'audit_logs.view',
    ];

    $existing = Permission::pluck('name')->toArray();

    foreach ($required as $perm) {
        expect($existing)->toContain($perm);
    }
});

it('gives super_admin every permission', function () {
    $superAdmin = Role::where('name', 'super_admin')->first();
    expect($superAdmin->permissions()->count())
        ->toBe(Permission::count());
});

it('does NOT give admin_staff the privileged permissions', function () {
    $adminStaff = Role::where('name', 'admin_staff')->first();
    $perms = $adminStaff->permissions->pluck('name')->toArray();

    expect($perms)
        ->not->toContain('roles.manage')
        ->not->toContain('settings.manage')
        ->not->toContain('payouts.approve')
        ->not->toContain('commissions.manage')
        ->not->toContain('users.delete')
        ->not->toContain('vendor_subscriptions.manage');
});

it('restricts customer to read-only permissions', function () {
    $customer = Role::where('name', 'customer')->first();
    $perms = $customer->permissions->pluck('name')->toArray();

    foreach ($perms as $perm) {
        // Customers should only have *.view permissions
        expect(str_ends_with($perm, '.view'))->toBeTrue(
            "customer should not have permission '{$perm}'",
        );
    }
});

it('lets users gain abilities via assigned roles', function () {
    $user = User::factory()->create();
    expect($user->can('users.view'))->toBeFalse();

    $user->assignRole('admin_staff');
    $user->refresh();

    expect($user->can('users.view'))->toBeTrue()
        ->and($user->can('roles.manage'))->toBeFalse(); // explicitly excluded
});
