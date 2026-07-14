<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPackage;
use App\Models\VendorSubscription;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\VendorPackagesSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(VendorPackagesSeeder::class);
});

it('lets an authenticated user submit a vendor application', function () {
    $user = User::factory()->create();
    $basic = VendorPackage::where('slug', 'basic')->firstOrFail();

    actingAs($user)
        ->post('/vendor/apply', [
            'business_name'     => 'Acme Trading',
            'business_email'    => 'acme@example.com',
            'business_type'     => 'company',
            'country'           => 'KW',
            'vendor_package_id' => $basic->id,
            'agree_terms'       => true,
        ])
        ->assertRedirect('/vendor');

    $vendor = Vendor::where('user_id', $user->id)->first();
    expect($vendor)->not->toBeNull()
        ->and($vendor->status)->toBe(Vendor::STATUS_PENDING)
        ->and($vendor->business_name)->toBe('Acme Trading')
        ->and($vendor->slug)->not->toBeEmpty();
});

it('creates a pending subscription tied to the chosen package on application', function () {
    $user = User::factory()->create();
    $std  = VendorPackage::where('slug', 'standard')->firstOrFail();

    actingAs($user)->post('/vendor/apply', [
        'business_name'     => 'Test Shop',
        'business_email'    => 'shop@example.com',
        'business_type'     => 'individual',
        'country'           => 'KW',
        'vendor_package_id' => $std->id,
        'agree_terms'       => true,
    ]);

    $vendor = Vendor::where('user_id', $user->id)->firstOrFail();
    $sub    = $vendor->subscriptions()->first();

    expect($sub)->not->toBeNull()
        ->and($sub->vendor_package_id)->toBe($std->id)
        ->and($sub->status)->toBe(VendorSubscription::STATUS_PENDING);
});

it('rejects application submission without terms acceptance', function () {
    $user = User::factory()->create();
    $basic = VendorPackage::where('slug', 'basic')->firstOrFail();

    actingAs($user)
        ->post('/vendor/apply', [
            'business_name'     => 'Acme',
            'business_email'    => 'a@b.com',
            'business_type'     => 'individual',
            'country'           => 'KW',
            'vendor_package_id' => $basic->id,
            // agree_terms missing
        ])
        ->assertSessionHasErrors('agree_terms');

    expect(Vendor::count())->toBe(0);
});

it('redirects existing vendors away from the application form', function () {
    $user   = User::factory()->create();
    Vendor::factory()->for($user)->create();

    actingAs($user)->get('/vendor/apply')->assertRedirect('/vendor');
});
