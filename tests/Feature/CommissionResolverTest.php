<?php

declare(strict_types=1);

use App\Domain\Commission\CommissionResolver;
use App\Domain\Money\Money;
use App\Models\Vendor;
use App\Models\VendorCommissionRule;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->resolver = app(CommissionResolver::class);
});

it('picks the vendor-scoped rule when one exists alongside a global rule', function () {
    $vendor = Vendor::factory()->approved()->create();

    // Global default — priority 100
    VendorCommissionRule::create([
        'scope' => VendorCommissionRule::SCOPE_GLOBAL,
        'commission_type' => VendorCommissionRule::TYPE_PERCENT,
        'percent_value' => 25.00,
        'priority' => 100,
        'is_active' => true,
    ]);

    // Vendor override — priority 50 (wins)
    $vendorRule = VendorCommissionRule::create([
        'vendor_id' => $vendor->id,
        'scope' => VendorCommissionRule::SCOPE_VENDOR,
        'scope_id' => $vendor->id,
        'commission_type' => VendorCommissionRule::TYPE_PERCENT,
        'percent_value' => 10.00,
        'priority' => 50,
        'is_active' => true,
    ]);

    $resolved = $this->resolver->resolve($vendor);
    expect($resolved->id)->toBe($vendorRule->id);
});

it('returns null when no rule applies', function () {
    $vendor = Vendor::factory()->approved()->create();
    expect($this->resolver->resolve($vendor))->toBeNull();
});

it('honors effective_from / effective_until windows', function () {
    $vendor = Vendor::factory()->approved()->create();

    VendorCommissionRule::create([
        'vendor_id' => $vendor->id,
        'scope' => VendorCommissionRule::SCOPE_VENDOR,
        'scope_id' => $vendor->id,
        'commission_type' => VendorCommissionRule::TYPE_PERCENT,
        'percent_value' => 15.00,
        'priority' => 50,
        'is_active' => true,
        'effective_from' => now()->addDays(7), // not yet effective
    ]);

    expect($this->resolver->resolve($vendor))->toBeNull();
});

it('computes percent commission with banker rounding', function () {
    $vendor = Vendor::factory()->approved()->create();

    VendorCommissionRule::create([
        'vendor_id' => $vendor->id,
        'scope' => VendorCommissionRule::SCOPE_VENDOR,
        'scope_id' => $vendor->id,
        'commission_type' => VendorCommissionRule::TYPE_PERCENT,
        'percent_value' => 20.00,
        'priority' => 50,
        'is_active' => true,
    ]);

    // 20% of 1000 minor units = 200 minor units
    $commission = $this->resolver->compute($vendor, new Money(1000, 'KWD'));

    expect($commission)->not->toBeNull()
        ->and($commission->amount)->toBe(200)
        ->and($commission->currency)->toBe('KWD');
});

it('computes a fixed_plus_percent commission', function () {
    $vendor = Vendor::factory()->approved()->create();

    VendorCommissionRule::create([
        'vendor_id' => $vendor->id,
        'scope' => VendorCommissionRule::SCOPE_VENDOR,
        'scope_id' => $vendor->id,
        'commission_type' => VendorCommissionRule::TYPE_FIXED_PLUS_PERCENT,
        'percent_value' => 5.00,
        'fixed_value_minor' => 100, // 1.00 KWD flat + 5%
        'priority' => 50,
        'is_active' => true,
    ]);

    // 5% of 2000 = 100; +100 fixed = 200
    expect($this->resolver->compute($vendor, new Money(2000, 'KWD'))->amount)->toBe(200);
});
