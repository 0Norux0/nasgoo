<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\VendorPackagesSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(VendorPackagesSeeder::class);
});

/*
 * v3.3 — Login redirect behavior.
 *
 * Public /login is for CUSTOMERS and VENDORS only.
 * Admins must use Filament's /admin/login (a separate flow).
 * The admin-on-/login tests from v3.2 have been replaced with
 * AuthSeparationAndCsrfTest::"rejects super_admin attempts to use the
 * public /login endpoint" and friends.
 */

it('redirects an approved vendor to /vendor after login', function () {
    $user = User::factory()->create(['email' => 'vendor@x.test', 'password' => bcrypt('secret123')]);
    $user->assignRole('vendor');
    Vendor::factory()->approved()->for($user)->create();

    post('/login', ['email' => 'vendor@x.test', 'password' => 'secret123'])
        ->assertRedirect('/vendor');
});

it('redirects a vendor with a profile but no role to /vendor', function () {
    // Edge case — vendor profile exists, role not yet assigned (e.g. between
    // application submission and admin approval). Still better UX to send
    // them to /vendor so they see their pending-status banner.
    $user = User::factory()->create(['email' => 'pend@x.test', 'password' => bcrypt('secret123')]);
    $user->assignRole('customer');
    Vendor::factory()->pending()->for($user)->create();

    post('/login', ['email' => 'pend@x.test', 'password' => 'secret123'])
        ->assertRedirect('/vendor');
});

it('redirects a plain customer to the homepage after login', function () {
    $user = User::factory()->create(['email' => 'cust@x.test', 'password' => bcrypt('secret123')]);
    $user->assignRole('customer');

    post('/login', ['email' => 'cust@x.test', 'password' => 'secret123'])
        ->assertRedirect('/');
});

it('honors the intended URL after login for customers', function () {
    $user = User::factory()->create(['email' => 'cust@x.test', 'password' => bcrypt('secret123')]);
    $user->assignRole('customer');

    // Simulate the guest-blocked /vendor/apply visit storing the intended URL
    get('/vendor/apply')->assertRedirect('/login');

    post('/login', ['email' => 'cust@x.test', 'password' => 'secret123'])
        ->assertRedirect('/vendor/apply');
});

/* ───────────────────── Suspended account guard ───────────────────── */

it('blocks suspended users from logging in', function () {
    $user = User::factory()->create([
        'email' => 'sus@x.test',
        'password' => bcrypt('secret123'),
        'status' => 'suspended',
    ]);
    $user->assignRole('customer');

    post('/login', ['email' => 'sus@x.test', 'password' => 'secret123'])
        ->assertSessionHasErrors('email');

    expect(auth()->check())->toBeFalse();
});

it('blocks banned users from logging in', function () {
    $user = User::factory()->create([
        'email' => 'ban@x.test',
        'password' => bcrypt('secret123'),
        'status' => 'banned',
    ]);
    $user->assignRole('customer');

    post('/login', ['email' => 'ban@x.test', 'password' => 'secret123'])
        ->assertSessionHasErrors('email');

    expect(auth()->check())->toBeFalse();
});
