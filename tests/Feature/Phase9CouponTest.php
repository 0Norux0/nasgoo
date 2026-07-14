<?php
declare(strict_types=1);

use App\Domain\Promotion\CouponValidator;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// v8.5 — all helpers prefixed p9_
function p9CouponCustomer(): User
{
    return User::factory()->create(['email' => 'p9-cust@test', 'role' => 'customer']);
}

function p9MakeCart(User $u, int $subtotal): Cart
{
    return Cart::create([
        'user_id' => $u->id,
        'currency' => 'KWD',
        'subtotal_minor' => $subtotal,
        'items_count' => 1,
    ]);
}

function p9MakeCoupon(array $overrides = []): Coupon
{
    return Coupon::create(array_merge([
        'code' => 'P9TEST',
        'discount_type' => 'percentage',
        'discount_value' => 10,
        'is_active' => true,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
        'usage_limit' => null,
        'per_user_limit' => 1,
        'currency' => 'KWD',
    ], $overrides));
}

it('a valid coupon applies correctly', function () {
    $user = p9CouponCustomer();
    $cart = p9MakeCart($user, 100000);   // 100 KWD
    p9MakeCoupon(['code' => 'VALID10', 'discount_value' => 10]);

    $r = CouponValidator::validate('VALID10', $cart, $user);
    expect($r['ok'])->toBeTrue();
    expect($r['reason'])->toBe(CouponValidator::OK);
    expect($r['discount_minor'])->toBe(10000);   // 10% of 100 KWD
});

it('coupon code is case-insensitive', function () {
    $user = p9CouponCustomer();
    $cart = p9MakeCart($user, 50000);
    p9MakeCoupon(['code' => 'UPPER']);

    $r = CouponValidator::validate('upper', $cart, $user);
    expect($r['ok'])->toBeTrue();
});

it('rejects an unknown coupon code', function () {
    $user = p9CouponCustomer();
    $cart = p9MakeCart($user, 50000);

    $r = CouponValidator::validate('NOSUCHCODE', $cart, $user);
    expect($r['ok'])->toBeFalse();
    expect($r['reason'])->toBe(CouponValidator::NOT_FOUND);
});

it('rejects an expired coupon', function () {
    $user = p9CouponCustomer();
    $cart = p9MakeCart($user, 50000);
    p9MakeCoupon([
        'code' => 'EXPIRED',
        'starts_at' => now()->subDays(10),
        'ends_at' => now()->subDay(),
    ]);

    $r = CouponValidator::validate('EXPIRED', $cart, $user);
    expect($r['ok'])->toBeFalse();
    expect($r['reason'])->toBe(CouponValidator::EXPIRED);
});

it('rejects when below minimum order amount', function () {
    $user = p9CouponCustomer();
    $cart = p9MakeCart($user, 10000);    // only 10 KWD
    p9MakeCoupon([
        'code' => 'NEEDS50',
        'min_order_minor' => 50000,      // requires 50 KWD
    ]);

    $r = CouponValidator::validate('NEEDS50', $cart, $user);
    expect($r['ok'])->toBeFalse();
    expect($r['reason'])->toBe(CouponValidator::MIN_ORDER_NOT_MET);
});

it('rejects when per-user limit is exhausted', function () {
    $user = p9CouponCustomer();
    $cart = p9MakeCart($user, 50000);
    $c = p9MakeCoupon(['code' => 'ONCEONLY', 'per_user_limit' => 1]);

    // First simulated usage
    CouponUsage::create([
        'coupon_id' => $c->id,
        'user_id' => $user->id,
        'discount_minor' => 5000,
        'currency' => 'KWD',
        'used_at' => now(),
    ]);

    $r = CouponValidator::validate('ONCEONLY', $cart, $user);
    expect($r['ok'])->toBeFalse();
    expect($r['reason'])->toBe(CouponValidator::PER_USER_LIMIT_REACHED);
});

it('rejects on currency mismatch', function () {
    $user = p9CouponCustomer();
    $cart = p9MakeCart($user, 50000);    // KWD cart
    p9MakeCoupon(['code' => 'USDONLY', 'currency' => 'USD']);

    $r = CouponValidator::validate('USDONLY', $cart, $user);
    expect($r['ok'])->toBeFalse();
    expect($r['reason'])->toBe(CouponValidator::CURRENCY_MISMATCH);
});

it('fixed-amount discount respects max_discount_minor cap', function () {
    $c = new Coupon([
        'discount_type' => 'percentage',
        'discount_value' => 50,
        'max_discount_minor' => 20000,    // cap at 20 KWD
    ]);

    expect($c->computeDiscountMinor(100000))->toBe(20000);   // 50 KWD would be 50%, capped at 20
});

it('demo SAVE10 coupon is created by seeder and usable', function () {
    $this->seed();
    $user = p9CouponCustomer();
    $cart = p9MakeCart($user, 100000);

    $r = CouponValidator::validate('SAVE10', $cart, $user);
    expect($r['ok'])->toBeTrue();
    // SAVE10 = 10% with 50 KWD cap → 10 KWD on 100 KWD cart
    expect($r['discount_minor'])->toBe(10000);
});

it('demo WELCOME5 coupon requires the 20 KWD min order', function () {
    $this->seed();
    $user = p9CouponCustomer();

    $smallCart = p9MakeCart($user, 10000);    // only 10 KWD
    $r1 = CouponValidator::validate('WELCOME5', $smallCart, $user);
    expect($r1['ok'])->toBeFalse();
    expect($r1['reason'])->toBe(CouponValidator::MIN_ORDER_NOT_MET);

    $smallCart->delete();
    $bigCart = p9MakeCart($user, 25000);
    $r2 = CouponValidator::validate('WELCOME5', $bigCart, $user);
    expect($r2['ok'])->toBeTrue();
    expect($r2['discount_minor'])->toBe(5000);   // 5 KWD fixed
});
