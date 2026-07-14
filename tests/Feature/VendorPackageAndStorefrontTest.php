<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPackage;
use Database\Seeders\VendorPackagesSeeder;

use function Pest\Laravel\get;

it('seeds Basic, Standard, and Professional packages with the right commission percentages', function () {
    $this->seed(VendorPackagesSeeder::class);

    $packages = VendorPackage::orderBy('sort_order')->get();
    expect($packages)->toHaveCount(3);

    expect($packages->pluck('slug')->toArray())
        ->toBe(['basic', 'standard', 'professional']);

    $byslug = $packages->keyBy('slug');
    expect((int) $byslug['basic']->default_admin_commission_percent)->toBe(30)
        ->and((int) $byslug['standard']->default_admin_commission_percent)->toBe(20)
        ->and((int) $byslug['professional']->default_admin_commission_percent)->toBe(10);
});

it('marks Professional with the full feature matrix and Basic with none of the premium features', function () {
    $this->seed(VendorPackagesSeeder::class);

    $pro   = VendorPackage::where('slug', 'professional')->firstOrFail();
    $basic = VendorPackage::where('slug', 'basic')->firstOrFail();

    expect($pro->allow_video)->toBeTrue()
        ->and($pro->allow_3d)->toBeTrue()
        ->and($pro->allow_dropshipping)->toBeTrue()
        ->and($pro->allow_customization)->toBeTrue()
        ->and($pro->allow_featured_vendor)->toBeTrue()
        ->and($pro->max_products)->toBeNull(); // unlimited

    expect($basic->allow_video)->toBeFalse()
        ->and($basic->allow_3d)->toBeFalse()
        ->and($basic->allow_featured_vendor)->toBeFalse()
        ->and($basic->max_products)->toBe(25);
});

it('returns the public storefront page for an approved vendor', function () {
    $vendor = Vendor::factory()->approved()->create([
        'business_name' => 'Test Shop',
        'slug'          => 'test-shop',
    ]);

    get("/vendors/{$vendor->slug}")->assertSuccessful();
});

it('returns 404 for a pending vendor storefront', function () {
    $vendor = Vendor::factory()->pending()->create(['slug' => 'pending-shop']);
    get("/vendors/{$vendor->slug}")->assertNotFound();
});

it('returns 404 for a suspended vendor storefront', function () {
    $vendor = Vendor::factory()->suspended()->create(['slug' => 'suspended-shop']);
    get("/vendors/{$vendor->slug}")->assertNotFound();
});

it('returns 404 for a non-existent vendor slug', function () {
    get('/vendors/does-not-exist')->assertNotFound();
});
