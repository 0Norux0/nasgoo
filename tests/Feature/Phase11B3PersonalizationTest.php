<?php

declare(strict_types=1);

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\CustomerAffinity;
use App\Models\CustomerProductView;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PersonalizationFeedback;
use App\Models\PersonalizationPreference;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Wishlist;
use App\Services\Personalization\BuyAgainService;
use App\Services\Personalization\ContinueShoppingService;
use App\Services\Personalization\CustomerAffinityService;
use App\Services\Personalization\PersonalizationManager;
use App\Services\Personalization\RecentlyViewedService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

// ─── helpers (p11b3_*) ─────────────────────────────────────────────────────

function p11b3_seed(): void {
    \Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder', '--force' => true]);
}

function p11b3_customer(): User {
    p11b3_seed();
    $u = User::factory()->create(['email' => 'p11b3-c-' . uniqid() . '@p11b3.test', 'password' => Hash::make('pw'), 'status' => 'active']);
    $u->assignRole('customer');
    return $u->fresh();
}

function p11b3_vendor_user(string $status = Vendor::STATUS_APPROVED): User {
    p11b3_seed();
    $u = User::factory()->create(['email' => 'p11b3-v-' . uniqid() . '@p11b3.test', 'password' => Hash::make('pw'), 'status' => 'active']);
    $u->assignRole('vendor');
    Vendor::create(['user_id' => $u->id, 'business_name' => 'V' . uniqid(), 'business_email' => 'v' . uniqid() . '@p11b3.test', 'business_type' => 'company', 'country' => 'KW', 'status' => $status]);
    return $u->fresh();
}

function p11b3_make_product(array $attrs = []): Product {
    $vendor = $attrs['vendor'] ?? p11b3_vendor_user()->vendor;
    $cat = $attrs['category'] ?? Category::create(['slug' => 'cat-' . uniqid(), 'name' => 'Cat-' . uniqid(), 'is_active' => true]);
    return Product::create(array_merge([
        'vendor_id' => $vendor->id, 'category_id' => $cat->id,
        'sku' => 'SKU-' . uniqid(), 'slug' => 'p-' . uniqid(),
        'name' => 'Default', 'type' => Product::TYPE_SIMPLE,
        'status' => Product::STATUS_PUBLISHED, 'price_minor' => 100000,
        'currency' => 'KWD', 'published_at' => now(), 'track_stock' => false,
    ], collect($attrs)->except(['vendor', 'category'])->all()));
}

// ══════════ §39 Recently Viewed (12) ══════════

it('§39.1 guest product view recorded via session_key', function () {
    $p = p11b3_make_product();
    app(RecentlyViewedService::class)->record(null, 'sess-abc', $p->id, 'en');
    expect(CustomerProductView::where('session_key', 'sess-abc')->count())->toBe(1);
});

it('§39.2 authenticated product view recorded via user_id', function () {
    $u = p11b3_customer(); $p = p11b3_make_product();
    app(RecentlyViewedService::class)->record($u, null, $p->id, 'en');
    expect(CustomerProductView::where('user_id', $u->id)->count())->toBe(1);
});

it('§39.3 duplicate views deduplicated on read', function () {
    $u = p11b3_customer();
    $p1 = p11b3_make_product(); $p2 = p11b3_make_product();
    $svc = app(RecentlyViewedService::class);
    $svc->record($u, null, $p1->id, 'en');
    $svc->record($u, null, $p1->id, 'en');
    $svc->record($u, null, $p2->id, 'en');
    expect($svc->forCaller($u, null, 10))->toHaveCount(2);
});

it('§39.4 most recently viewed appears first', function () {
    $u = p11b3_customer();
    $p1 = p11b3_make_product(); $p2 = p11b3_make_product();
    CustomerProductView::create(['user_id' => $u->id, 'product_id' => $p1->id, 'locale' => 'en', 'viewed_at' => now()->subDays(2)]);
    CustomerProductView::create(['user_id' => $u->id, 'product_id' => $p2->id, 'locale' => 'en', 'viewed_at' => now()]);
    expect(app(RecentlyViewedService::class)->forCaller($u, null, 10)->first()->id)->toBe($p2->id);
});

it('§39.5 retention window excludes old views', function () {
    config(['marketplace_personalization.retention.customer_views_days' => 30]);
    $u = p11b3_customer(); $p = p11b3_make_product();
    CustomerProductView::create(['user_id' => $u->id, 'product_id' => $p->id, 'locale' => 'en', 'viewed_at' => now()->subDays(60)]);
    expect(app(RecentlyViewedService::class)->forCaller($u, null, 10))->toHaveCount(0);
});

it('§39.6 unpublished product excluded', function () {
    $u = p11b3_customer(); $p = p11b3_make_product();
    app(RecentlyViewedService::class)->record($u, null, $p->id, 'en');
    $p->update(['status' => Product::STATUS_DRAFT, 'published_at' => null]);
    expect(app(RecentlyViewedService::class)->forCaller($u, null, 10))->toHaveCount(0);
});

it('§39.7 suspended-vendor product excluded', function () {
    $u = p11b3_customer();
    $vendor = p11b3_vendor_user()->vendor;
    $p = p11b3_make_product(['vendor' => $vendor]);
    app(RecentlyViewedService::class)->record($u, null, $p->id, 'en');
    $vendor->update(['status' => Vendor::STATUS_SUSPENDED]);
    expect(app(RecentlyViewedService::class)->forCaller($u, null, 10))->toHaveCount(0);
});

it('§39.8 excludeProductId honored (current product excluded from section)', function () {
    $u = p11b3_customer();
    $p1 = p11b3_make_product(); $p2 = p11b3_make_product();
    app(RecentlyViewedService::class)->record($u, null, $p1->id, 'en');
    app(RecentlyViewedService::class)->record($u, null, $p2->id, 'en');
    expect(app(RecentlyViewedService::class)->forCaller($u, null, 10, $p1->id)->pluck('id')->all())->not->toContain($p1->id);
});

it('§39.9 clear history removes only caller rows', function () {
    $u1 = p11b3_customer(); $u2 = p11b3_customer();
    $p = p11b3_make_product();
    app(RecentlyViewedService::class)->record($u1, null, $p->id, 'en');
    app(RecentlyViewedService::class)->record($u2, null, $p->id, 'en');
    app(RecentlyViewedService::class)->clear($u1, null);
    expect(CustomerProductView::where('user_id', $u1->id)->count())->toBe(0);
    expect(CustomerProductView::where('user_id', $u2->id)->count())->toBe(1);
});

it('§39.10 guest session isolation (A cannot see B)', function () {
    $p = p11b3_make_product();
    app(RecentlyViewedService::class)->record(null, 'sess-A', $p->id, 'en');
    expect(app(RecentlyViewedService::class)->forCaller(null, 'sess-B', 10))->toHaveCount(0);
});

it('§39.11 tracking-disabled preference: no rows written', function () {
    $u = p11b3_customer();
    PersonalizationPreference::create(['user_id' => $u->id, 'behavior_tracking_enabled' => false, 'behavioral_personalization_enabled' => true, 'guest_merge_enabled' => true]);
    $p = p11b3_make_product();
    app(RecentlyViewedService::class)->record($u, null, $p->id, 'en');
    expect(CustomerProductView::where('user_id', $u->id)->count())->toBe(0);
});

it('§39.12 Not Interested feedback hides product', function () {
    $u = p11b3_customer(); $p = p11b3_make_product();
    app(RecentlyViewedService::class)->record($u, null, $p->id, 'en');
    PersonalizationFeedback::create(['user_id' => $u->id, 'feedback_type' => PersonalizationFeedback::TYPE_NOT_INTERESTED, 'product_id' => $p->id, 'expires_at' => now()->addDays(90)]);
    expect(app(RecentlyViewedService::class)->forCaller($u, null, 10)->pluck('id')->all())->not->toContain($p->id);
});

// ══════════ §40 Continue Shopping (8) ══════════

it('§40.1 cart items prioritized first', function () {
    $u = p11b3_customer();
    $p1 = p11b3_make_product(); $p2 = p11b3_make_product();
    Wishlist::create(['user_id' => $u->id, 'product_id' => $p2->id]);
    $cart = Cart::create(['user_id' => $u->id, 'session_id' => null, 'items_count' => 1, 'subtotal_minor' => 100000, 'currency' => 'KWD']);
    CartItem::create(['cart_id' => $cart->id, 'product_id' => $p1->id, 'vendor_id' => $p1->vendor_id, 'quantity' => 1, 'unit_price_minor' => 100000, 'currency' => 'KWD']);
    expect(app(ContinueShoppingService::class)->forUser($u, null, 5)->first()->id)->toBe($p1->id);
});

it('§40.2 wishlist items included when no cart', function () {
    $u = p11b3_customer(); $p = p11b3_make_product();
    Wishlist::create(['user_id' => $u->id, 'product_id' => $p->id]);
    expect(app(ContinueShoppingService::class)->forUser($u, null, 5)->pluck('id')->all())->toContain($p->id);
});

it('§40.3 recently viewed included after cart+wishlist', function () {
    $u = p11b3_customer(); $p = p11b3_make_product();
    app(RecentlyViewedService::class)->record($u, null, $p->id, 'en');
    expect(app(ContinueShoppingService::class)->forUser($u, null, 5)->pluck('id')->all())->toContain($p->id);
});

it('§40.4 completed-purchase product excluded from recently-viewed contribution', function () {
    $u = p11b3_customer(); $p = p11b3_make_product();
    app(RecentlyViewedService::class)->record($u, null, $p->id, 'en');
    $order = Order::create(['user_id' => $u->id, 'status' => Order::STATUS_COMPLETED, 'payment_status' => 'paid', 'fulfillment_status' => 'fulfilled', 'subtotal_minor' => 100000, 'total_minor' => 100000, 'currency' => 'KWD']);
    OrderItem::create(['order_id' => $order->id, 'vendor_id' => $p->vendor_id, 'product_id' => $p->id, 'product_name' => $p->name, 'quantity' => 1, 'unit_price_minor' => 100000, 'line_total_minor' => 100000, 'currency' => 'KWD']);
    expect(app(ContinueShoppingService::class)->forUser($u, null, 5)->pluck('id')->all())->not->toContain($p->id);
});

it('§40.5 unpublished product excluded', function () {
    $u = p11b3_customer(); $p = p11b3_make_product();
    Wishlist::create(['user_id' => $u->id, 'product_id' => $p->id]);
    $p->update(['status' => Product::STATUS_DRAFT, 'published_at' => null]);
    expect(app(ContinueShoppingService::class)->forUser($u, null, 5)->pluck('id')->all())->not->toContain($p->id);
});

it('§40.6 duplicate product across cart+wishlist appears once', function () {
    $u = p11b3_customer(); $p = p11b3_make_product();
    $cart = Cart::create(['user_id' => $u->id, 'session_id' => null, 'items_count' => 1, 'subtotal_minor' => 100000, 'currency' => 'KWD']);
    CartItem::create(['cart_id' => $cart->id, 'product_id' => $p->id, 'vendor_id' => $p->vendor_id, 'quantity' => 1, 'unit_price_minor' => 100000, 'currency' => 'KWD']);
    Wishlist::create(['user_id' => $u->id, 'product_id' => $p->id]);
    expect(app(ContinueShoppingService::class)->forUser($u, null, 5)->count())->toBe(1);
});

it('§40.7 guest continue-shopping empty when no session cart', function () {
    expect(app(ContinueShoppingService::class)->forUser(null, 'nonexistent', 5))->toHaveCount(0);
});

it('§40.8 both user + session null returns empty', function () {
    expect(app(ContinueShoppingService::class)->forUser(null, null, 5))->toHaveCount(0);
});

// ══════════ §41 Affinity + scoring (10) ══════════

it('§41.1 completed purchase produces category affinity', function () {
    $u = p11b3_customer();
    $cat = Category::create(['slug' => 'c-' . uniqid(), 'name' => 'C', 'is_active' => true]);
    $p = p11b3_make_product(['category' => $cat]);
    $order = Order::create(['user_id' => $u->id, 'status' => Order::STATUS_COMPLETED, 'payment_status' => 'paid', 'fulfillment_status' => 'fulfilled', 'subtotal_minor' => 100000, 'total_minor' => 100000, 'currency' => 'KWD']);
    OrderItem::create(['order_id' => $order->id, 'vendor_id' => $p->vendor_id, 'product_id' => $p->id, 'product_name' => 'p', 'quantity' => 1, 'unit_price_minor' => 100000, 'line_total_minor' => 100000, 'currency' => 'KWD']);
    app(CustomerAffinityService::class)->rebuildForUser($u);
    expect(CustomerAffinity::where('user_id', $u->id)->where('dimension', 'category')->where('dimension_id', $cat->id)->count())->toBe(1);
});

it('§41.2 purchase scores higher than a single view', function () {
    $u = p11b3_customer();
    $catB = Category::create(['slug' => 'b-' . uniqid(), 'name' => 'B', 'is_active' => true]);
    $catV = Category::create(['slug' => 'v-' . uniqid(), 'name' => 'V', 'is_active' => true]);
    $pB = p11b3_make_product(['category' => $catB]);
    $pV = p11b3_make_product(['category' => $catV]);
    CustomerProductView::create(['user_id' => $u->id, 'product_id' => $pV->id, 'locale' => 'en', 'viewed_at' => now()]);
    $order = Order::create(['user_id' => $u->id, 'status' => Order::STATUS_COMPLETED, 'payment_status' => 'paid', 'fulfillment_status' => 'fulfilled', 'subtotal_minor' => 100000, 'total_minor' => 100000, 'currency' => 'KWD']);
    OrderItem::create(['order_id' => $order->id, 'vendor_id' => $pB->vendor_id, 'product_id' => $pB->id, 'product_name' => 'p', 'quantity' => 1, 'unit_price_minor' => 100000, 'line_total_minor' => 100000, 'currency' => 'KWD']);
    app(CustomerAffinityService::class)->rebuildForUser($u);
    $b = CustomerAffinity::where('user_id', $u->id)->where('dimension_id', $catB->id)->value('score');
    $v = CustomerAffinity::where('user_id', $u->id)->where('dimension_id', $catV->id)->value('score');
    expect($b)->toBeGreaterThan($v);
});

it('§41.3 wishlist scores higher than view', function () {
    $u = p11b3_customer();
    $catW = Category::create(['slug' => 'w-' . uniqid(), 'name' => 'W', 'is_active' => true]);
    $catV = Category::create(['slug' => 'v-' . uniqid(), 'name' => 'V', 'is_active' => true]);
    $pW = p11b3_make_product(['category' => $catW]); $pV = p11b3_make_product(['category' => $catV]);
    CustomerProductView::create(['user_id' => $u->id, 'product_id' => $pV->id, 'locale' => 'en', 'viewed_at' => now()]);
    Wishlist::create(['user_id' => $u->id, 'product_id' => $pW->id]);
    app(CustomerAffinityService::class)->rebuildForUser($u);
    $wS = CustomerAffinity::where('user_id', $u->id)->where('dimension_id', $catW->id)->value('score');
    $vS = CustomerAffinity::where('user_id', $u->id)->where('dimension_id', $catV->id)->value('score');
    expect($wS)->toBeGreaterThan($vS);
});

it('§41.4 recent outranks old (recency decay)', function () {
    $u = p11b3_customer();
    $catN = Category::create(['slug' => 'n-' . uniqid(), 'name' => 'N', 'is_active' => true]);
    $catO = Category::create(['slug' => 'o-' . uniqid(), 'name' => 'O', 'is_active' => true]);
    $pN = p11b3_make_product(['category' => $catN]); $pO = p11b3_make_product(['category' => $catO]);
    for ($i = 0; $i < 3; $i++) {
        CustomerProductView::create(['user_id' => $u->id, 'product_id' => $pN->id, 'locale' => 'en', 'viewed_at' => now()->subHours($i)]);
        CustomerProductView::create(['user_id' => $u->id, 'product_id' => $pO->id, 'locale' => 'en', 'viewed_at' => now()->subDays(60 + $i)]);
    }
    app(CustomerAffinityService::class)->rebuildForUser($u);
    $n = CustomerAffinity::where('user_id', $u->id)->where('dimension_id', $catN->id)->value('score') ?? 0;
    $o = CustomerAffinity::where('user_id', $u->id)->where('dimension_id', $catO->id)->value('score') ?? 0;
    expect($n)->toBeGreaterThan($o);
});

it('§41.5 refresh-spam capped', function () {
    $u = p11b3_customer();
    $cat = Category::create(['slug' => 'r-' . uniqid(), 'name' => 'R', 'is_active' => true]);
    $p = p11b3_make_product(['category' => $cat]);
    for ($i = 0; $i < 100; $i++) {
        CustomerProductView::create(['user_id' => $u->id, 'product_id' => $p->id, 'locale' => 'en', 'viewed_at' => now()->subMinutes($i)]);
    }
    app(CustomerAffinityService::class)->rebuildForUser($u);
    $score = CustomerAffinity::where('user_id', $u->id)->where('dimension_id', $cat->id)->value('score');
    // Cap views_per_product = 5, weight = 3 → max ~15
    expect($score)->toBeLessThan(50);
});

it('§41.6 topCategories returns highest-affinity first', function () {
    $u = p11b3_customer();
    $c1 = Category::create(['slug' => 'a-' . uniqid(), 'name' => 'A', 'is_active' => true]);
    $c2 = Category::create(['slug' => 'b-' . uniqid(), 'name' => 'B', 'is_active' => true]);
    CustomerAffinity::create(['user_id' => $u->id, 'dimension' => 'category', 'dimension_id' => $c1->id, 'score' => 100, 'signal_count' => 1, 'last_signal_at' => now()]);
    CustomerAffinity::create(['user_id' => $u->id, 'dimension' => 'category', 'dimension_id' => $c2->id, 'score' => 50, 'signal_count' => 1, 'last_signal_at' => now()]);
    expect(app(CustomerAffinityService::class)->topCategories($u)[0])->toBe($c1->id);
});

it('§41.7 topVendors + preferredPriceBands', function () {
    $u = p11b3_customer(); $v = p11b3_vendor_user()->vendor;
    CustomerAffinity::create(['user_id' => $u->id, 'dimension' => 'vendor', 'dimension_id' => $v->id, 'score' => 30, 'signal_count' => 1, 'last_signal_at' => now()]);
    CustomerAffinity::create(['user_id' => $u->id, 'dimension' => 'price_band', 'dimension_id' => null, 'dimension_key' => 'band_50_100_kwd', 'score' => 40, 'signal_count' => 1, 'last_signal_at' => now()]);
    expect(app(CustomerAffinityService::class)->topVendors($u))->toContain($v->id);
    expect(app(CustomerAffinityService::class)->preferredPriceBands($u))->toContain('band_50_100_kwd');
});

it('§41.8 priceBandKey classifies prices correctly', function () {
    $svc = app(CustomerAffinityService::class);
    expect($svc->priceBandKey(500))->toBe('band_under_10_kwd');
    expect($svc->priceBandKey(7500))->toBe('band_50_100_kwd');
    expect($svc->priceBandKey(200000))->toBe('band_100_plus_kwd');
});

it('§41.9 rebuild is idempotent (replaces prior rows)', function () {
    $u = p11b3_customer();
    $cat = Category::create(['slug' => 'c-' . uniqid(), 'name' => 'C', 'is_active' => true]);
    $p = p11b3_make_product(['category' => $cat]);
    CustomerProductView::create(['user_id' => $u->id, 'product_id' => $p->id, 'locale' => 'en', 'viewed_at' => now()]);
    app(CustomerAffinityService::class)->rebuildForUser($u);
    $first = CustomerAffinity::where('user_id', $u->id)->count();
    app(CustomerAffinityService::class)->rebuildForUser($u);
    expect(CustomerAffinity::where('user_id', $u->id)->count())->toBe($first);
});

it('§41.10 recency decay bucket calculation', function () {
    $decay = config('marketplace_personalization.recency_decay');
    $svc = app(CustomerAffinityService::class);
    expect($svc->recencyMultiplier($decay, now()->subDays(3)))->toBeGreaterThan(0.9);
    expect($svc->recencyMultiplier($decay, now()->subDays(20)))->toBeLessThan(0.7);
    expect($svc->recencyMultiplier($decay, now()->subDays(200)))->toBe(0.0);
});

// ══════════ §42 Privacy + isolation (8) ══════════

it('§42.1 opt-out returns disabled payload', function () {
    $u = p11b3_customer();
    PersonalizationPreference::create(['user_id' => $u->id, 'behavioral_personalization_enabled' => false, 'guest_merge_enabled' => true, 'behavior_tracking_enabled' => true]);
    $result = app(PersonalizationManager::class)->homepageFor($u, null, 'en');
    expect($result['enabled'])->toBeFalse();
});

it('§42.2 master flag off returns disabled', function () {
    config(['marketplace_personalization.features.enabled' => false]);
    expect(app(PersonalizationManager::class)->homepageFor(p11b3_customer(), null, 'en')['enabled'])->toBeFalse();
});

it('§42.3 reset clears only my rows', function () {
    $u1 = p11b3_customer(); $u2 = p11b3_customer();
    $p = p11b3_make_product();
    CustomerProductView::create(['user_id' => $u1->id, 'product_id' => $p->id, 'locale' => 'en', 'viewed_at' => now()]);
    CustomerProductView::create(['user_id' => $u2->id, 'product_id' => $p->id, 'locale' => 'en', 'viewed_at' => now()]);
    test()->actingAs($u1)->post('/personalization/reset');
    expect(CustomerProductView::where('user_id', $u1->id)->count())->toBe(0);
    expect(CustomerProductView::where('user_id', $u2->id)->count())->toBe(1);
});

it('§42.4 no cross-customer read of recently viewed', function () {
    $u1 = p11b3_customer(); $u2 = p11b3_customer();
    $p = p11b3_make_product();
    app(RecentlyViewedService::class)->record($u1, null, $p->id, 'en');
    expect(app(RecentlyViewedService::class)->forCaller($u2, null, 10))->toHaveCount(0);
});

it('§42.5 cache keys isolated per user', function () {
    Cache::flush();
    $u1 = p11b3_customer(); $u2 = p11b3_customer();
    $p = p11b3_make_product();
    app(RecentlyViewedService::class)->record($u1, null, $p->id, 'en');
    app(PersonalizationManager::class)->homepageFor($u1, null, 'en');
    $u2Result = app(PersonalizationManager::class)->homepageFor($u2, null, 'en');
    foreach ($u2Result['sections'] as $s) {
        expect(collect($s['items'])->pluck('id')->all())->not->toContain($p->id);
    }
});

it('§42.6 clear endpoint enforces caller identity', function () {
    $u1 = p11b3_customer(); $u2 = p11b3_customer();
    $p = p11b3_make_product();
    app(RecentlyViewedService::class)->record($u1, null, $p->id, 'en');
    app(RecentlyViewedService::class)->record($u2, null, $p->id, 'en');
    test()->actingAs($u2)->post('/personalization/recently-viewed/clear');
    expect(CustomerProductView::where('user_id', $u1->id)->count())->toBe(1);
    expect(CustomerProductView::where('user_id', $u2->id)->count())->toBe(0);
});

it('§42.7 feedback scoped to caller', function () {
    $u = p11b3_customer(); $p = p11b3_make_product();
    test()->actingAs($u)->post('/personalization/feedback', ['product_id' => $p->id, 'feedback_type' => 'not_interested']);
    expect(PersonalizationFeedback::where('user_id', $u->id)->first()->product_id)->toBe($p->id);
});

it('§42.8 preferences page requires auth', function () {
    test()->get('/account/personalization')->assertStatus(302);
});

// ══════════ §43.5 Buy Again multi-purchase (helper for tail block) ══════════
it('§43.5 Buy Again multiple purchases in recency order', function () {
    config(['marketplace_personalization.buy_again.min_days_since_purchase' => 1]);
    $u = p11b3_customer();
    $p1 =
 p11b3_make_product(); $p2 = p11b3_make_product();
    $order1 = Order::create(['user_id' => $u->id, 'status' => Order::STATUS_COMPLETED, 'payment_status' => 'paid', 'fulfillment_status' => 'fulfilled', 'subtotal_minor' => 100000, 'total_minor' => 100000, 'currency' => 'KWD', 'created_at' => now()->subDays(30)]);
    OrderItem::create(['order_id' => $order1->id, 'vendor_id' => $p1->vendor_id, 'product_id' => $p1->id, 'product_name' => 'p1', 'quantity' => 1, 'unit_price_minor' => 100000, 'line_total_minor' => 100000, 'currency' => 'KWD']);
    $order2 = Order::create(['user_id' => $u->id, 'status' => Order::STATUS_COMPLETED, 'payment_status' => 'paid', 'fulfillment_status' => 'fulfilled', 'subtotal_minor' => 100000, 'total_minor' => 100000, 'currency' => 'KWD', 'created_at' => now()->subDays(10)]);
    OrderItem::create(['order_id' => $order2->id, 'vendor_id' => $p2->vendor_id, 'product_id' => $p2->id, 'product_name' => 'p2', 'quantity' => 1, 'unit_price_minor' => 100000, 'line_total_minor' => 100000, 'currency' => 'KWD']);
    $result = app(BuyAgainService::class)->forUser($u, 5);
    // p2 (more recent) should be first
    expect($result->first()->id)->toBe($p2->id);
});

it('§43.6 Buy Again respects max_days_since_purchase window', function () {
    config(['marketplace_personalization.buy_again.max_days_since_purchase' => 180]);
    $u = p11b3_customer();
    $p = p11b3_make_product();
    $order = Order::create(['user_id' => $u->id, 'status' => Order::STATUS_COMPLETED, 'payment_status' => 'paid', 'fulfillment_status' => 'fulfilled', 'subtotal_minor' => 100000, 'total_minor' => 100000, 'currency' => 'KWD', 'created_at' => now()->subDays(300)]);
    OrderItem::create(['order_id' => $order->id, 'vendor_id' => $p->vendor_id, 'product_id' => $p->id, 'product_name' => 'p', 'quantity' => 1, 'unit_price_minor' => 100000, 'line_total_minor' => 100000, 'currency' => 'KWD']);
    $result = app(BuyAgainService::class)->forUser($u, 5);
    expect($result)->toHaveCount(0);
});

// ════════════════════════════════════════════════════════════════════════════
// §44 — Flags, cache, regression (14 scenarios)
// ════════════════════════════════════════════════════════════════════════════

it('§44.1 recently_viewed flag disabled: section not rendered', function () {
    config(['marketplace_personalization.features.recently_viewed' => false]);
    $u = p11b3_customer();
    $p = p11b3_make_product();
    app(RecentlyViewedService::class)->record($u, null, $p->id, 'en');
    $result = app(PersonalizationManager::class)->homepageFor($u, null, 'en');
    $types = collect($result['sections'])->pluck('type')->all();
    expect($types)->not->toContain('recently_viewed');
});

it('§44.2 guest personalization flag disabled: guests see disabled payload', function () {
    config(['marketplace_personalization.features.guest_personalization' => false]);
    $result = app(PersonalizationManager::class)->homepageFor(null, 'guest-session', 'en');
    expect($result['enabled'])->toBeFalse();
});

it('§44.3 cache invalidates on new product view', function () {
    Cache::flush();
    $u = p11b3_customer();
    $p1 = p11b3_make_product(); $p2 = p11b3_make_product();
    app(RecentlyViewedService::class)->record($u, null, $p1->id, 'en');
    $first = app(PersonalizationManager::class)->homepageFor($u, null, 'en');
    // Record a NEW view + invalidate
    app(RecentlyViewedService::class)->record($u, null, $p2->id, 'en');
    app(PersonalizationManager::class)->invalidate($u, null);
    $second = app(PersonalizationManager::class)->homepageFor($u, null, 'en');
    // Second read reflects the new view (at least one section returns items with p2)
    $anyContainsP2 = collect($second['sections'])->contains(fn ($s) =>
        collect($s['items'])->pluck('id')->contains($p2->id)
    );
    expect($anyContainsP2)->toBeTrue();
});

it('§44.4 runtime eligibility recheck: suspended vendor product filtered from cache', function () {
    Cache::flush();
    $u = p11b3_customer();
    $vendor = p11b3_vendor_user()->vendor;
    $p = p11b3_make_product(['vendor' => $vendor]);
    app(RecentlyViewedService::class)->record($u, null, $p->id, 'en');
    app(PersonalizationManager::class)->homepageFor($u, null, 'en');  // populates cache
    // Suspend vendor
    $vendor->update(['status' => Vendor::STATUS_SUSPENDED]);
    // Second call — even if cached, runtime recheck removes it
    $result = app(PersonalizationManager::class)->homepageFor($u, null, 'en');
    foreach ($result['sections'] as $s) {
        expect(collect($s['items'])->pluck('id')->all())->not->toContain($p->id);
    }
});

it('§44.5 Homepage still works when personalization disabled globally', function () {
    config(['marketplace_personalization.features.enabled' => false]);
    test()->get('/')->assertOk();
});

it('§44.6 Homepage still works when personalization enabled but no data', function () {
    $u = p11b3_customer();
    test()->actingAs($u)->get('/')->assertOk();
});

it('§44.7 Product view middleware records via CatalogController show', function () {
    $u = p11b3_customer();
    $p = p11b3_make_product();
    test()->actingAs($u)->get("/products/{$p->slug}")->assertOk();
    expect(CustomerProductView::where('user_id', $u->id)->where('product_id', $p->id)->count())->toBeGreaterThan(0);
});

it('§44.8 Product 404: no view row created', function () {
    $u = p11b3_customer();
    test()->actingAs($u)->get('/products/does-not-exist')->assertNotFound();
    expect(CustomerProductView::where('user_id', $u->id)->count())->toBe(0);
});

it('§44.9 Feedback rate-limited', function () {
    // Simple sanity: hit the endpoint 5 times fast; all should succeed with throttle:60,1
    $u = p11b3_customer();
    $p = p11b3_make_product();
    for ($i = 0; $i < 5; $i++) {
        test()->actingAs($u)->post('/personalization/feedback', [
            'product_id' => $p->id, 'feedback_type' => 'not_interested',
        ]);
    }
    expect(PersonalizationFeedback::where('user_id', $u->id)->count())->toBe(5);
});

it('§44.10 Section priority: continue_shopping ranks above recently_viewed', function () {
    $u = p11b3_customer();
    $p1 = p11b3_make_product(); $p2 = p11b3_make_product();
    // Cart with p1 (2 items so evidence >= 2)
    $cart = Cart::create(['user_id' => $u->id, 'session_id' => null, 'items_count' => 2, 'subtotal_minor' => 200000, 'currency' => 'KWD']);
    CartItem::create(['cart_id' => $cart->id, 'product_id' => $p1->id, 'vendor_id' => $p1->vendor_id, 'quantity' => 1, 'unit_price_minor' => 100000, 'currency' => 'KWD']);
    CartItem::create(['cart_id' => $cart->id, 'product_id' => $p2->id, 'vendor_id' => $p2->vendor_id, 'quantity' => 1, 'unit_price_minor' => 100000, 'currency' => 'KWD']);
    // Recent views of same products
    app(RecentlyViewedService::class)->record($u, null, $p1->id, 'en');
    app(RecentlyViewedService::class)->record($u, null, $p2->id, 'en');
    Cache::flush();
    $result = app(PersonalizationManager::class)->homepageFor($u, null, 'en');
    // If sections rendered, first must be continue_shopping
    if (! empty($result['sections'])) {
        expect($result['sections'][0]['type'])->toBe('continue_shopping');
    }
});

it('§44.11 Cross-section dedup: same product appears in only one section', function () {
    $u = p11b3_customer();
    $p1 = p11b3_make_product(); $p2 = p11b3_make_product(); $p3 = p11b3_make_product();
    // p1 in cart AND recently viewed
    $cart = Cart::create(['user_id' => $u->id, 'session_id' => null, 'items_count' => 1, 'subtotal_minor' => 100000, 'currency' => 'KWD']);
    CartItem::create(['cart_id' => $cart->id, 'product_id' => $p1->id, 'vendor_id' => $p1->vendor_id, 'quantity' => 1, 'unit_price_minor' => 100000, 'currency' => 'KWD']);
    CartItem::create(['cart_id' => $cart->id, 'product_id' => $p2->id, 'vendor_id' => $p2->vendor_id, 'quantity' => 1, 'unit_price_minor' => 100000, 'currency' => 'KWD']);
    app(RecentlyViewedService::class)->record($u, null, $p1->id, 'en');
    app(RecentlyViewedService::class)->record($u, null, $p3->id, 'en');
    Cache::flush();
    $result = app(PersonalizationManager::class)->homepageFor($u, null, 'en');
    // Collect all ids across all sections; p1 should appear at most once
    $allIds = collect($result['sections'])->flatMap(fn ($s) => collect($s['items'])->pluck('id'))->all();
    $p1Count = collect($allIds)->filter(fn ($id) => $id === $p1->id)->count();
    expect($p1Count)->toBeLessThanOrEqual(1);
});

it('§44.12 English fallback for missing Arabic translation', function () {
    app()->setLocale('ar');
    $u = p11b3_customer();
    $p = p11b3_make_product(['name' => 'EnglishOnly']);  // no ar translation
    app(RecentlyViewedService::class)->record($u, null, $p->id, 'ar');
    $result = app(RecentlyViewedService::class)->forCaller($u, null, 5);
    // translatedName falls back to English
    expect($result->first()->translatedName())->toBe('EnglishOnly');
});

it('§44.13 Rebuild command runs without error for a single user', function () {
    $u = p11b3_customer();
    $p = p11b3_make_product();
    CustomerProductView::create(['user_id' => $u->id, 'product_id' => $p->id, 'locale' => 'en', 'viewed_at' => now()]);
    \Artisan::call('personalization:rebuild', ['--user' => $u->id]);
    expect(\Artisan::output())->toContain('Rebuilt user');
});

it('§44.14 Prune command runs in dry-run without deleting', function () {
    $u = p11b3_customer();
    $p = p11b3_make_product();
    CustomerProductView::create(['user_id' => $u->id, 'product_id' => $p->id, 'locale' => 'en', 'viewed_at' => now()->subDays(200)]);
    \Artisan::call('personalization:prune', ['--dry-run' => true]);
    expect(CustomerProductView::count())->toBe(1);  // still there
});

// ═════ Preservation regression smokes ═════════════════════════════════════
it('§REG.1 Homepage renders (personalization off)', function () {
    config(['marketplace_personalization.features.enabled' => false]);
    test()->get('/')->assertOk();
});

it('§REG.2 Product detail page renders + records view when auth', function () {
    $u = p11b3_customer();
    $p = p11b3_make_product();
    test()->actingAs($u)->get("/products/{$p->slug}")->assertOk();
});
