<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('allows super_admin to access the Filament admin panel', function () {
    $user = User::factory()->create(['status' => 'active']);
    $user->assignRole('super_admin');

    actingAs($user)
        ->get('/admin')
        ->assertSuccessful();
});

it('allows admin_staff to access the Filament admin panel', function () {
    $user = User::factory()->create(['status' => 'active']);
    $user->assignRole('admin_staff');

    actingAs($user)
        ->get('/admin')
        ->assertSuccessful();
});

it('forbids vendor users from accessing the Filament admin panel', function () {
    $user = User::factory()->create(['status' => 'active']);
    $user->assignRole('vendor');

    actingAs($user)
        ->get('/admin')
        ->assertForbidden();
});

it('forbids customer users from accessing the Filament admin panel', function () {
    $user = User::factory()->create(['status' => 'active']);
    $user->assignRole('customer');

    actingAs($user)
        ->get('/admin')
        ->assertForbidden();
});

it('forbids suspended super_admin from accessing the Filament admin panel', function () {
    $user = User::factory()->create(['status' => 'suspended']);
    $user->assignRole('super_admin');

    actingAs($user)
        ->get('/admin')
        ->assertForbidden();
});

it('redirects unauthenticated visitors to the login page', function () {
    get('/admin')->assertRedirect();
});
