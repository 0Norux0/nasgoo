<?php
declare(strict_types=1);

use App\Models\Promotion;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// v8.5 — prefix all helpers with p9_ to avoid collisions
function p9PromotionAdmin(): User
{
    return User::factory()->create(['email' => 'p9-admin@test', 'role' => 'admin']);
}

function p9PromotionVendor(): array
{
    $u = User::factory()->create(['email' => 'p9-vendor@test', 'role' => 'vendor']);
    $v = Vendor::factory()->create(['user_id' => $u->id, 'status' => 'approved']);
    return [$u, $v];
}

it('admin can create a platform-wide promotion', function () {
    $p = Promotion::create([
        'title' => 'Test sale',
        'slug' => 'p9-test-sale',
        'promotion_type' => Promotion::TYPE_FLASH_SALE,
        'discount_type' => Promotion::DISCOUNT_PERCENTAGE,
        'discount_value' => 25,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
        'is_active' => true,
        'approval_status' => Promotion::APPROVAL_APPROVED,
        'currency' => 'KWD',
    ]);

    expect($p->fresh()->approval_status)->toBe(Promotion::APPROVAL_APPROVED);
    expect(Promotion::usable()->where('id', $p->id)->exists())->toBeTrue();
});

it('vendor-created promotion needs admin approval before being usable', function () {
    [$user, $vendor] = p9PromotionVendor();

    $this->actingAs($user);
    $this->post('/vendor/promotions', [
        'title' => 'Vendor sale',
        'promotion_type' => 'flash_sale',
        'discount_type' => 'percentage',
        'discount_value' => 10,
        'is_active' => true,
    ])->assertRedirect('/vendor/promotions');

    $p = Promotion::where('vendor_id', $vendor->id)->first();
    expect($p)->not->toBeNull();
    expect($p->approval_status)->toBe(Promotion::APPROVAL_PENDING);
    // Pending promotion is NOT in the usable scope
    expect(Promotion::usable()->where('id', $p->id)->exists())->toBeFalse();
});

it('promotion computeDiscountMinor handles percentage and respects max_discount cap', function () {
    $p = new Promotion([
        'discount_type' => Promotion::DISCOUNT_PERCENTAGE,
        'discount_value' => 50,
        'max_discount_minor' => 30000,    // cap at 30 KWD
    ]);

    expect($p->computeDiscountMinor(100000))->toBe(30000);   // 50% of 100 = 50, capped at 30
    expect($p->computeDiscountMinor(50000))->toBe(25000);    // 50% of 50 = 25, under cap
});

it('promotion fixed_amount discount never exceeds line subtotal', function () {
    $p = new Promotion([
        'discount_type' => Promotion::DISCOUNT_FIXED,
        'discount_value' => 100000,    // 100 KWD off
    ]);

    expect($p->computeDiscountMinor(50000))->toBe(50000);    // can't discount more than the line
});

it('expired promotion is excluded from the usable scope', function () {
    $expired = Promotion::create([
        'title' => 'Expired',
        'slug' => 'p9-expired',
        'promotion_type' => 'flash_sale',
        'discount_type' => 'percentage',
        'discount_value' => 10,
        'starts_at' => now()->subDays(10),
        'ends_at' => now()->subDay(),
        'is_active' => true,
        'approval_status' => 'approved',
        'currency' => 'KWD',
    ]);

    expect(Promotion::usable()->where('id', $expired->id)->exists())->toBeFalse();
});
