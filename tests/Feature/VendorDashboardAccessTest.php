<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\VendorPackagesSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(VendorPackagesSeeder::class);
});

it('redirects guests to login when accessing /vendor', function () {
    get('/vendor')->assertRedirect('/login');
});

it('redirects users without a vendor profile to /vendor/apply', function () {
    $user = User::factory()->create();
    actingAs($user)->get('/vendor')->assertRedirect('/vendor/apply');
});

it('lets an approved vendor see their dashboard', function () {
    $user   = User::factory()->create();
    $user->assignRole('vendor');
    Vendor::factory()->approved()->for($user)->create();

    actingAs($user)->get('/vendor')->assertSuccessful();
});

it('lets pending vendors view the dashboard but blocks the profile editor', function () {
    $user = User::factory()->create();
    Vendor::factory()->pending()->for($user)->create();

    actingAs($user)->get('/vendor')->assertSuccessful();
    actingAs($user)->get('/vendor/profile')->assertRedirect('/vendor'); // approved-only middleware
});

it('blocks suspended vendors from the profile editor too', function () {
    $user = User::factory()->create();
    $user->assignRole('vendor');
    Vendor::factory()->suspended()->for($user)->create();

    actingAs($user)->get('/vendor/profile')->assertRedirect('/vendor');
});

it('redirects customers (no vendor profile) away from /vendor/profile', function () {
    $user = User::factory()->create();
    $user->assignRole('customer');
    actingAs($user)->get('/vendor/profile')->assertRedirect('/vendor/apply');
});
