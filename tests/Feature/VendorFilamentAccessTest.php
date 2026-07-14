<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\VendorPackagesSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(VendorPackagesSeeder::class);
});

it('lets super_admin reach the vendors list in Filament', function () {
    $u = User::factory()->create(['status' => 'active']);
    $u->assignRole('super_admin');
    actingAs($u)->get('/admin/vendors')->assertSuccessful();
});

it('forbids a vendor user from accessing the Filament admin panel entirely', function () {
    $u = User::factory()->create(['status' => 'active']);
    $u->assignRole('vendor');
    Vendor::factory()->approved()->for($u)->create();

    actingAs($u)->get('/admin/vendors')->assertForbidden();
});

it('forbids a customer from accessing the Filament vendor pages', function () {
    $u = User::factory()->create(['status' => 'active']);
    $u->assignRole('customer');
    actingAs($u)->get('/admin/vendor-packages')->assertForbidden();
});
