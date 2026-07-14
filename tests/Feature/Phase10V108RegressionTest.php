<?php

declare(strict_types=1);

use App\Domain\Pricing\PricingService;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\PromotionTarget;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── helpers ─────────────────────────────────────────────────────────────

function p108_vendor(): Vendor
{
    $u = User::factory()->create();
    return Vendor::create([
        'user_id'        => $u->id,
        'business_name'  => 'V'.uniqid(),
        'business_email' => 'v'.uniqid().'@p108.test',
        'business_type'  => 'company',
        'country'        => 'KW',
        'status'         => Vendor::STATUS_APPROVED,
    ]);
}

function p108_product(int $priceMinor = 10000, ?Vendor $vendor = null, ?Category $category = null): Product
{
    $vendor ??= p108_vendor();
    $p = new Product();
    $p->vendor_id   = $vendor->id;
    $p->category_id = $category?->id;
    $p->name        = 'P'.uniqid();
    $p->slug        = 'p-'.uniqid();
    $p->type        = Product::TYPE_SIMPLE;
    $p->status      = Product::STATUS_PUBLISHED;
    $p->price_minor = $priceMinor;
    $p->currency    = 'KWD';
    $p->save();
    return $p;
}

function p108_promo(array $overrides = []): Promotion
{
    $admin = User::factory()->create();
    return Promotion::create(array_merge([
        'created_by'      => $admin->id,
        'title'           => 'Summer Flash Sale',
        'slug'            => 'sfs-'.uniqid(),
        'promotion_type'  => Promotion::TYPE_FLASH_SALE,
        'discount_type'   => Promotion::DISCOUNT_PERCENTAGE,
        'discount_value'  => '20',
        'starts_at'       => now()->subDay(),
        'ends_at'         => now()->addWeek(),
        'is_active'       => true,
        'approval_status' => Promotion::APPROVAL_APPROVED,
        'currency'        => 'KWD',
    ], $overrides));
}

// ─── §12.1 — Active global 20% promotion applies to all eligible products ─

it('global 20% promotion applies to every eligible product (no targets = platform-wide)', function () {
    p108_promo();   // no PromotionTargets created → platform-wide
    $p = p108_product(10000);   // 100.00 KWD

    $svc = app(PricingService::class);
    $dto = $svc->priceForProduct($p);

    expect($dto['original_minor'])->toBe(10000);
    expect($dto['discount_minor'])->toBe(2000);  // 20% of 10000
    expect($dto['final_minor'])->toBe(8000);
    expect($dto['promotion'])->not->toBeNull();
    expect($dto['promotion']['badge'])->toBe('20% OFF');
    expect($dto['final'])->toBe('80.00');
});

// ─── §12.2 — Product listing returns original and final prices ──────────

it('CatalogController index returns promotion-aware props per product', function () {
    p108_promo();
    p108_product(5000);

    $resp = $this->get('/products');
    $resp->assertOk();
    $props = $resp->viewData('page')['props'];
    $products = data_get($props, 'products.data');
    expect($products)->toBeArray();
    expect(count($products))->toBeGreaterThan(0);
    $card = $products[0];
    expect($card)->toHaveKey('final_price');
    expect($card)->toHaveKey('promotion');
    expect($card['promotion']['badge'])->toBe('20% OFF');
    expect($card['final_price'])->toBe('40.00');
});

// ─── §12.3 — Product detail returns the same promotional price ──────────

it('CatalogController show returns the same final_price as the listing', function () {
    p108_promo();
    $p = p108_product(15000);

    $resp = $this->get('/products/'.$p->slug);
    $resp->assertOk();
    $product = data_get($resp->viewData('page')['props'], 'product');
    expect($product['final_price'])->toBe('120.00');     // 150 − 20% = 120
    expect($product['promotion']['badge'])->toBe('20% OFF');
});

// ─── §12.4 — Promotion badge appears ─────────────────────────────────────

it('PricingService produces a "20% OFF" badge for a 20% percentage promotion', function () {
    p108_promo(['discount_value' => '20']);
    $p = p108_product(10000);

    $dto = app(PricingService::class)->priceForProduct($p);
    expect($dto['promotion']['badge'])->toBe('20% OFF');
});

it('PricingService produces a "5.00 KWD OFF" badge for a fixed-amount promotion', function () {
    p108_promo([
        'discount_type'  => Promotion::DISCOUNT_FIXED,
        'discount_value' => '500',     // 5.00 KWD
    ]);
    $p = p108_product(10000);

    $dto = app(PricingService::class)->priceForProduct($p);
    expect($dto['promotion']['badge'])->toBe('5.00 KWD OFF');
});

// ─── §12.5+6 — Cart applies promotion; summary shows it separately ───────

it('cart breakdown returns promotion_total_minor, subtotal_after_promotion, and per-line promotion', function () {
    p108_promo();
    $p1 = p108_product(5000);
    $p2 = p108_product(6000);

    $user = User::factory()->create();
    $cart = Cart::create(['user_id' => $user->id, 'currency' => 'KWD', 'subtotal_minor' => 11000, 'items_count' => 2]);
    $cart->items()->create(['product_id' => $p1->id, 'vendor_id' => $p1->vendor_id, 'quantity' => 1, 'unit_price_minor' => 5000, 'currency' => 'KWD']);
    $cart->items()->create(['product_id' => $p2->id, 'vendor_id' => $p2->vendor_id, 'quantity' => 1, 'unit_price_minor' => 6000, 'currency' => 'KWD']);

    $b = app(PricingService::class)->priceForCart($cart, $user);
    expect($b['subtotal_minor'])->toBe(11000);
    expect($b['promotion_total_minor'])->toBe(2200);     // 20% of 11000
    expect($b['subtotal_after_promotion_minor'])->toBe(8800);
    expect($b['payable_minor'])->toBe(8800);              // no coupon
    expect(count($b['lines']))->toBe(2);
    foreach ($b['lines'] as $ln) {
        expect($ln['promotion'])->not->toBeNull();
        expect($ln['line_promotion_minor'])->toBeGreaterThan(0);
    }
});

// ─── §12.7+9 — Promotion + coupon stacking (dev §7 rule) ─────────────────

it('promotion is applied BEFORE coupon and coupon applies to post-promotion subtotal', function () {
    p108_promo();
    $p = p108_product(11000);   // 110.00

    // 10% coupon (matches dev's "SAVE10" — but we read its rule, don't assume)
    $coupon = Coupon::create([
        'code'           => 'SAVE10',
        'discount_type'  => 'percentage',
        'discount_value' => '10',
        'currency'       => 'KWD',
        'is_active'      => true,
        'per_user_limit' => 99,
    ]);

    $user = User::factory()->create();
    $cart = Cart::create(['user_id' => $user->id, 'currency' => 'KWD', 'subtotal_minor' => 11000, 'items_count' => 1, 'coupon_id' => $coupon->id, 'discount_minor' => 0]);
    $cart->items()->create(['product_id' => $p->id, 'vendor_id' => $p->vendor_id, 'quantity' => 1, 'unit_price_minor' => 11000, 'currency' => 'KWD']);

    $b = app(PricingService::class)->priceForCart($cart, $user);
    expect($b['subtotal_minor'])->toBe(11000);
    expect($b['promotion_total_minor'])->toBe(2200);              // 20% of 11000
    expect($b['subtotal_after_promotion_minor'])->toBe(8800);     // dev §7 step 2
    expect($b['coupon_discount_minor'])->toBe(880);                // 10% of 8800 — NOT 10% of 11000
    expect($b['payable_minor'])->toBe(7920);                       // 8800 − 880
});

// ─── §12.12 — Expired promotion does not apply ──────────────────────────

it('expired promotion does not apply', function () {
    p108_promo(['ends_at' => now()->subDay()]);
    $p = p108_product(10000);

    $dto = app(PricingService::class)->priceForProduct($p);
    expect($dto['promotion'])->toBeNull();
    expect($dto['discount_minor'])->toBe(0);
    expect($dto['final_minor'])->toBe(10000);
});

// ─── §12.13 — Inactive promotion does not apply ──────────────────────────

it('inactive promotion does not apply', function () {
    p108_promo(['is_active' => false]);
    $p = p108_product(10000);

    $dto = app(PricingService::class)->priceForProduct($p);
    expect($dto['promotion'])->toBeNull();
});

// ─── §12.14 — Future promotion does not apply ────────────────────────────

it('future promotion does not apply', function () {
    p108_promo(['starts_at' => now()->addDay()]);
    $p = p108_product(10000);

    $dto = app(PricingService::class)->priceForProduct($p);
    expect($dto['promotion'])->toBeNull();
});

// ─── §12.15 — Unapproved promotion does not apply ────────────────────────

it('unapproved (pending) promotion does not apply', function () {
    p108_promo(['approval_status' => Promotion::APPROVAL_PENDING]);
    $p = p108_product(10000);

    $dto = app(PricingService::class)->priceForProduct($p);
    expect($dto['promotion'])->toBeNull();
});

it('rejected promotion does not apply', function () {
    p108_promo(['approval_status' => Promotion::APPROVAL_REJECTED]);
    $p = p108_product(10000);

    $dto = app(PricingService::class)->priceForProduct($p);
    expect($dto['promotion'])->toBeNull();
});

// ─── §12.16 — Product-specific restrictions are respected ────────────────

it('product-specific promotion only applies to its targeted product', function () {
    $promo = p108_promo(['promotion_type' => Promotion::TYPE_PRODUCT_SPECIFIC]);
    $matched = p108_product(10000);
    $other   = p108_product(10000);

    PromotionTarget::create([
        'promotion_id'    => $promo->id,
        'targetable_type' => Product::class,
        'targetable_id'   => $matched->id,
    ]);

    $svc = app(PricingService::class);
    expect($svc->priceForProduct($matched)['promotion'])->not->toBeNull();
    expect($svc->priceForProduct($other)['promotion'])->toBeNull();
});

it('product-specific promotion beats platform-wide for the matching product', function () {
    // Platform-wide 10%
    p108_promo(['discount_value' => '10']);
    // Product-specific 30%
    $specific = p108_promo([
        'promotion_type'  => Promotion::TYPE_PRODUCT_SPECIFIC,
        'discount_value'  => '30',
    ]);
    $p = p108_product(10000);
    PromotionTarget::create([
        'promotion_id'    => $specific->id,
        'targetable_type' => Product::class,
        'targetable_id'   => $p->id,
    ]);

    $dto = app(PricingService::class)->priceForProduct($p);
    expect($dto['discount_minor'])->toBe(3000);  // 30%, not 10%
});

// ─── §12.17 — Multi-product cart totals reconcile ────────────────────────

it('multi-product cart with platform-wide promo: sum(line_promotion) == promotion_total_minor', function () {
    p108_promo();
    $p1 = p108_product(2500);
    $p2 = p108_product(4500);
    $p3 = p108_product(8000);

    $user = User::factory()->create();
    $cart = Cart::create(['user_id' => $user->id, 'currency' => 'KWD', 'subtotal_minor' => 15000, 'items_count' => 3]);
    foreach ([$p1, $p2, $p3] as $p) {
        $cart->items()->create(['product_id' => $p->id, 'vendor_id' => $p->vendor_id, 'quantity' => 1, 'unit_price_minor' => $p->price_minor, 'currency' => 'KWD']);
    }

    $b = app(PricingService::class)->priceForCart($cart, $user);
    $sumLine = 0;
    foreach ($b['lines'] as $ln) {
        $sumLine += $ln['line_promotion_minor'];
    }
    expect($sumLine)->toBe($b['promotion_total_minor']);
});

// ─── §12.18 — Multi-vendor cart totals reconcile ─────────────────────────

it('multi-vendor cart: promotion applies per-line regardless of vendor', function () {
    p108_promo();
    $v1 = p108_vendor();
    $v2 = p108_vendor();
    $p1 = p108_product(5000, $v1);
    $p2 = p108_product(5000, $v2);

    $user = User::factory()->create();
    $cart = Cart::create(['user_id' => $user->id, 'currency' => 'KWD', 'subtotal_minor' => 10000, 'items_count' => 2]);
    $cart->items()->create(['product_id' => $p1->id, 'vendor_id' => $v1->id, 'quantity' => 1, 'unit_price_minor' => 5000, 'currency' => 'KWD']);
    $cart->items()->create(['product_id' => $p2->id, 'vendor_id' => $v2->id, 'quantity' => 1, 'unit_price_minor' => 5000, 'currency' => 'KWD']);

    $b = app(PricingService::class)->priceForCart($cart, $user);
    expect($b['promotion_total_minor'])->toBe(2000);  // 20% of 10000
    foreach ($b['lines'] as $ln) {
        expect($ln['line_promotion_minor'])->toBe(1000);
    }
});

// ─── §12.19 — Rounding is deterministic ──────────────────────────────────

it('rounding is deterministic and floor-based (matches Promotion::computeDiscountMinor)', function () {
    // Use a price that produces a fractional minor on 20%: 333 minor × 20% = 66.6 minor → floor to 66
    p108_promo();
    $p = p108_product(333);

    $svc = app(PricingService::class);
    $a = $svc->priceForProduct($p);
    $b = $svc->priceForProduct($p);
    expect($a['discount_minor'])->toBe($b['discount_minor']);    // deterministic
    expect($a['discount_minor'])->toBe(66);                       // floor(333 * 20 / 100)
    expect($a['final_minor'])->toBe(267);
});

// ─── §12.20 — No lazy-load violation ─────────────────────────────────────

it('priceForCart does not lazy-load relations when preventLazyLoading is on', function () {
    \Illuminate\Database\Eloquent\Model::preventLazyLoading(true);
    try {
        p108_promo();
        $p = p108_product(5000);
        $user = User::factory()->create();
        $cart = Cart::create(['user_id' => $user->id, 'currency' => 'KWD', 'subtotal_minor' => 5000, 'items_count' => 1]);
        $cart->items()->create(['product_id' => $p->id, 'vendor_id' => $p->vendor_id, 'quantity' => 1, 'unit_price_minor' => 5000, 'currency' => 'KWD']);

        // PricingService::priceForCart loadMissing's everything it needs; this
        // call must NOT throw LazyLoadingViolationException.
        $b = app(PricingService::class)->priceForCart($cart, $user);
        expect($b['promotion_total_minor'])->toBe(1000);
    } finally {
        \Illuminate\Database\Eloquent\Model::preventLazyLoading(false);
    }
});

// ─── Cross-cutting ───────────────────────────────────────────────────────

it('VERSION reports Phase 10 v10.8', function () {
    expect(trim((string) file_get_contents(base_path('VERSION'))))->toBe('Phase 10 v10.8');
});

// ─── §12.8 — Order stores promotion snapshot (migration columns honored) ─

it('OrderItem and Order have the v10.8 promotion snapshot columns in fillable', function () {
    // Smoke test of the model contract — every checkout path must be able
    // to mass-assign these without a MassAssignmentException firing.
    expect((new \App\Models\Order())->getFillable())->toContain('promotion_discount_minor');
    foreach (['promotion_id', 'promotion_name', 'promotion_discount_minor', 'original_unit_price_minor'] as $col) {
        expect((new \App\Models\OrderItem())->getFillable())->toContain($col);
    }
});
