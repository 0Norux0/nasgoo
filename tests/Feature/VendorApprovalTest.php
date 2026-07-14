<?php

declare(strict_types=1);

use App\Domain\Vendor\VendorApprovalService;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorCommissionRule;
use App\Models\VendorPackage;
use App\Models\VendorSubscription;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\VendorPackagesSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(VendorPackagesSeeder::class);
    $this->service = app(VendorApprovalService::class);
});

it('flips a pending vendor to approved and sets timestamps', function () {
    $admin  = User::factory()->create();
    $admin->assignRole('super_admin');
    $this->actingAs($admin);

    $user   = User::factory()->create();
    $vendor = Vendor::factory()->pending()->for($user)->create();
    $pkg    = VendorPackage::where('slug', 'standard')->firstOrFail();

    $result = $this->service->approve($vendor, $pkg);

    expect($result->status)->toBe(Vendor::STATUS_APPROVED)
        ->and($result->approved_at)->not->toBeNull()
        ->and($result->approved_by)->toBe($admin->id);
});

it('assigns the vendor role on approval', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $this->actingAs($admin);

    $user   = User::factory()->create();
    $vendor = Vendor::factory()->pending()->for($user)->create();
    $pkg    = VendorPackage::where('slug', 'basic')->firstOrFail();

    expect($user->hasRole('vendor'))->toBeFalse();

    $this->service->approve($vendor, $pkg);

    expect($user->fresh()->hasRole('vendor'))->toBeTrue();
});

it('creates an active subscription on approval', function () {
    $admin = User::factory()->create()->assignRole('super_admin');
    $this->actingAs($admin);

    $vendor = Vendor::factory()->pending()->create();
    $pkg    = VendorPackage::where('slug', 'professional')->firstOrFail();

    $this->service->approve($vendor, $pkg);

    $sub = $vendor->fresh()->activeSubscription;
    expect($sub)->not->toBeNull()
        ->and($sub->status)->toBe(VendorSubscription::STATUS_ACTIVE)
        ->and($sub->vendor_package_id)->toBe($pkg->id)
        ->and($sub->ends_at)->not->toBeNull(); // monthly package
});

it('creates a default vendor-scoped commission rule on approval', function () {
    $admin = User::factory()->create()->assignRole('super_admin');
    $this->actingAs($admin);

    $vendor = Vendor::factory()->pending()->create();
    $pkg    = VendorPackage::where('slug', 'professional')->firstOrFail();

    $this->service->approve($vendor, $pkg);

    $rule = $vendor->fresh()->commissionRules()->first();
    expect($rule)->not->toBeNull()
        ->and($rule->scope)->toBe(VendorCommissionRule::SCOPE_VENDOR)
        ->and((float) $rule->percent_value)->toBe((float) $pkg->default_admin_commission_percent);
});

it('writes an audit log entry on approval and rejection', function () {
    $admin = User::factory()->create()->assignRole('super_admin');
    $this->actingAs($admin);

    $vendor = Vendor::factory()->pending()->create();
    $pkg    = VendorPackage::where('slug', 'basic')->firstOrFail();

    $this->service->approve($vendor, $pkg);
    expect(AuditLog::where('action', 'vendor.approved')->count())->toBe(1);

    $other = Vendor::factory()->pending()->create();
    $this->service->reject($other, 'Missing license document.');
    expect(AuditLog::where('action', 'vendor.rejected')->count())->toBe(1);
});

it('saves rejection reason and notifies the user', function () {
    $admin = User::factory()->create()->assignRole('super_admin');
    $this->actingAs($admin);

    $vendor = Vendor::factory()->pending()->create();
    $result = $this->service->reject($vendor, 'Insufficient documents.');

    expect($result->status)->toBe(Vendor::STATUS_REJECTED)
        ->and($result->rejection_reason)->toBe('Insufficient documents.');
});
