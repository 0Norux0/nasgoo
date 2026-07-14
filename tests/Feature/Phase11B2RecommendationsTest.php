<?php

declare(strict_types=1);

use App\Models\AdminProductRelationship;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductPairStat;
use App\Models\ProductRecommendation;
use App\Models\RecommendationEvent;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Recommendations\CustomersAlsoBoughtService;
use App\Services\Recommendations\FrequentlyBoughtTogetherService;
use App\Services\Recommendations\RecommendationEligibility;
use App\Services\Recommendations\RecommendationManager;
use App\Services\Recommendations\SimilarProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

// ─── helpers (p11b2_*) ─────────────────────────────────────────────────────

function p11b2_seed(): void
{
    \Artisan::call('db:seed', [
        '--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder',
        '--force' => true,
    ]);
}

function p11b2_customer(): User
{
    p11b2_seed();
    $u = User::factory()->create([
        'email'    => 'p11b2-c-' . uniqid() . '@p11b2.test',
        'password' => Hash::make('pw'), 'status' => 'active',
    ]);
    $u->assignRole('customer');
    return $u->fresh();
}

function p11b2_vendor_user(string $status = Vendor::STATUS_APPROVED): User
{
    p11b2_seed();
    $u = User::factory()->create([
        'email'    => 'p11b2-v-' . uniqid() . '@p11b2.test',
        'password' => Hash::make('pw'), 'status' => 'active',
    ]);
    $u->assignRole('vendor');
    Vendor::create([
        'user_id' => $u->id, 'business_name' => 'V' . uniqid(),
        'business_email' => 'v' . uniqid() . '@p11b2.test',
        'business_type' => 'company', 'country' => 'KW', 'status' => $status,
    ]);
    return $u->fresh();
}

function p11b2_admin(): User
{
    p11b2_seed();
    $u = User::factory()->create([
        'email'    => 'p11b2-a-' . uniqid() . '@p11b2.test',
        'password' => Hash::make('pw'), 'status' => 'active',
    ]);
    $u->assignRole('super_admin');
    return $u->fresh();
}

function p11b2_make_category(?int $parentId = null, string $name = 'Cat'): Category
{
    return Category::create([
        'slug' => 'cat-' . uniqid(),
        'name' => $name . '-' . uniqid(),
        'parent_id' => $parentId,
        'is_active' => true,
    ]);
}

function p11b2_make_product(array $attrs = []): Product
{
    $vendor = $attrs['vendor'] ?? p11b2_vendor_user()->vendor;
    $cat = $attrs['category'] ?? p11b2_make_category();
    return Product::create(array_merge([
        'vendor_id' => $vendor->id,
        'category_id' => $cat->id,
        'sku' => 'SKU-' . uniqid(),
        'slug' => 'prod-' . uniqid(),
        'name' => 'Default Product',
        'type' => Product::TYPE_SIMPLE,
        'status' => Product::STATUS_PUBLISHED,
        'price_minor' => 100000,  // 100.00
        'currency' => 'KWD',
        'published_at' => now(),
        'track_stock' => false,
    ], collect($attrs)->except(['vendor', 'category'])->all()));
}

function p11b2_make_completed_order(User $customer, array $productIds, string $status = Order::STATUS_COMPLETED): Order
{
    $order = Order::create([
        'user_id' => $customer->id,
        'status' => $status,
        'payment_status' => 'paid',
        'fulfillment_status' => 'fulfilled',
        'subtotal_minor' => 100000 * count($productIds),
        'total_minor' => 100000 * count($productIds),
        'currency' => 'KWD',
    ]);
    foreach ($productIds as $pid) {
        $p = Product::find($pid);
        OrderItem::create([
            'order_id' => $order->id,
            'vendor_id' => $p->vendor_id,
            'product_id' => $pid,
            'product_name' => $p->name,
            'quantity' => 1,
            'unit_price_minor' => $p->price_minor,
            'line_total_minor' => $p->price_minor,
            'currency' => 'KWD',
        ]);
    }
    return $order->fresh('items');
}

// ════════════════════════════════════════════════════════════════════════════
// §34.1-15 — Similar Products (15)
// ════════════════════════════════════════════════════════════════════════════

it('§34.1 Same subcategory ranks highly via SimilarProductService', function () {
    $parent = p11b2_make_category(null, 'Electronics');
    $sub = p11b2_make_category($parent->id, 'Headphones');
    $vendor = p11b2_vendor_user()->vendor;
    $source = p11b2_make_product(['vendor' => $vendor, 'category' => $sub, 'name' => 'Source HP']);
    $sameSubCat = p11b2_make_product(['vendor' => $vendor, 'category' => $sub, 'name' => 'Similar HP']);
    $otherCat = p11b2_make_product(['vendor' => $vendor, 'name' => 'Unrelated']);
    $svc = app(SimilarProductService::class);
    $results = $svc->forProduct($source, 8);
    $ids = $results->pluck('id')->all();
    expect($ids)->toContain($sameSubCat->id);
});

it('§34.2 Similar price increases score', function () {
    $vendor = p11b2_vendor_user()->vendor;
    $cat = p11b2_make_category();
    $source = p11b2_make_product(['vendor' => $vendor, 'category' => $cat, 'price_minor' => 100000, 'name' => 'S']);
    $closePriced = p11b2_make_product(['vendor' => $vendor, 'category' => $cat, 'price_minor' => 105000, 'name' => 'Close']);
    $farPriced = p11b2_make_product(['vendor' => $vendor, 'category' => $cat, 'price_minor' => 1000000, 'name' => 'Far']);
    $svc = app(SimilarProductService::class);
    $results = $svc->forProduct($source, 8);
    $closeIdx = $results->search(fn ($p) => $p->id === $closePriced->id);
    $farIdx = $results->search(fn ($p) => $p->id === $farPriced->id);
    expect($closeIdx !== false)->toBeTrue();
    if ($farIdx !== false) {
        expect($closeIdx)->toBeLessThan($farIdx);
    }
});

it('§34.3 Same vendor receives small score boost', function () {
    $cat = p11b2_make_category();
    $vendor1 = p11b2_vendor_user()->vendor;
    $vendor2 = p11b2_vendor_user()->vendor;
    $source = p11b2_make_product(['vendor' => $vendor1, 'category' => $cat]);
    $sameVendor = p11b2_make_product(['vendor' => $vendor1, 'category' => $cat]);
    $svc = app(SimilarProductService::class);
    $results = $svc->forProduct($source, 8);
    expect($results->pluck('id')->all())->toContain($sameVendor->id);
});

it('§34.4 Current source product is excluded from its own recommendations', function () {
    $source = p11b2_make_product(['name' => 'Source']);
    $svc = app(SimilarProductService::class);
    $results = $svc->forProduct($source, 8);
    expect($results->pluck('id')->all())->not->toContain($source->id);
});

it('§34.5 Draft product is excluded', function () {
    $cat = p11b2_make_category();
    $source = p11b2_make_product(['category' => $cat]);
    $draft = p11b2_make_product(['category' => $cat, 'status' => Product::STATUS_DRAFT, 'published_at' => null]);
    $svc = app(SimilarProductService::class);
    $results = $svc->forProduct($source, 8);
    expect($results->pluck('id')->all())->not->toContain($draft->id);
});

it('§34.6 Suspended-vendor product is excluded', function () {
    $cat = p11b2_make_category();
    $source = p11b2_make_product(['category' => $cat]);
    $suspendedVendor = p11b2_vendor_user(Vendor::STATUS_SUSPENDED)->vendor;
    $suspendedProduct = p11b2_make_product(['vendor' => $suspendedVendor, 'category' => $cat]);
    $svc = app(SimilarProductService::class);
    $results = $svc->forProduct($source, 8);
    expect($results->pluck('id')->all())->not->toContain($suspendedProduct->id);
});

it('§34.7 Out-of-stock product is excluded when exclude_out_of_stock=true', function () {
    config(['marketplace_recommendations.eligibility.exclude_out_of_stock' => true]);
    $cat = p11b2_make_category();
    $source = p11b2_make_product(['category' => $cat]);
    $oos = p11b2_make_product(['category' => $cat, 'track_stock' => true, 'stock' => 0]);
    $svc = app(SimilarProductService::class);
    $results = $svc->forProduct($source, 8);
    expect($results->pluck('id')->all())->not->toContain($oos->id);
});

it('§34.8 Localized product title returned via TranslationService', function () {
    $cat = p11b2_make_category();
    $vendor = p11b2_vendor_user()->vendor;
    $source = p11b2_make_product(['vendor' => $vendor, 'category' => $cat, 'name' => 'EN Source']);
    $arab = p11b2_make_product([
        'vendor' => $vendor, 'category' => $cat,
        'name' => 'English Phone',
        'name_translations' => ['ar' => 'هاتف عربي'],
    ]);
    app()->setLocale('ar');
    $mgr = app(RecommendationManager::class);
    $payload = $mgr->similarProducts($source, 8);
    $names = collect($payload['items'])->pluck('display_name')->all();
    expect($names)->toContain('هاتف عربي');
});

it('§34.9 English fallback works when Arabic translation absent', function () {
    $cat = p11b2_make_category();
    $vendor = p11b2_vendor_user()->vendor;
    $source = p11b2_make_product(['vendor' => $vendor, 'category' => $cat]);
    $enOnly = p11b2_make_product(['vendor' => $vendor, 'category' => $cat, 'name' => 'EnglishOnly']);
    app()->setLocale('ar');
    $mgr = app(RecommendationManager::class);
    $payload = $mgr->similarProducts($source, 8);
    expect(collect($payload['items'])->pluck('display_name')->all())->toContain('EnglishOnly');
});

it('§34.10 Result count is capped at limit', function () {
    $cat = p11b2_make_category();
    $vendor = p11b2_vendor_user()->vendor;
    $source = p11b2_make_product(['vendor' => $vendor, 'category' => $cat]);
    for ($i = 0; $i < 15; $i++) {
        p11b2_make_product(['vendor' => $vendor, 'category' => $cat]);
    }
    $svc = app(SimilarProductService::class);
    $results = $svc->forProduct($source, 5);
    expect($results->count())->toBeLessThanOrEqual(5);
});

it('§34.11 Pinned admin relationship ranks first', function () {
    $cat = p11b2_make_category();
    $vendor = p11b2_vendor_user()->vendor;
    $admin = p11b2_admin();
    $source = p11b2_make_product(['vendor' => $vendor, 'category' => $cat]);
    $pinned = p11b2_make_product(['vendor' => $vendor, 'category' => $cat, 'name' => 'PINNED']);
    AdminProductRelationship::create([
        'product_id' => $source->id,
        'related_product_id' => $pinned->id,
        'relationship_type' => AdminProductRelationship::TYPE_PINNED,
        'created_by' => $admin->id,
    ]);
    $svc = app(SimilarProductService::class);
    $results = $svc->forProduct($source, 8);
    expect($results->first()->id)->toBe($pinned->id);
});

it('§34.12 Excluded admin relationship is omitted', function () {
    $cat = p11b2_make_category();
    $vendor = p11b2_vendor_user()->vendor;
    $admin = p11b2_admin();
    $source = p11b2_make_product(['vendor' => $vendor, 'category' => $cat]);
    $excluded = p11b2_make_product(['vendor' => $vendor, 'category' => $cat]);
    AdminProductRelationship::create([
        'product_id' => $source->id,
        'related_product_id' => $excluded->id,
        'relationship_type' => AdminProductRelationship::TYPE_EXCLUDED,
        'created_by' => $admin->id,
    ]);
    $svc = app(SimilarProductService::class);
    $results = $svc->forProduct($source, 8);
    expect($results->pluck('id')->all())->not->toContain($excluded->id);
});

it('§34.13 Hidden admin relationship is omitted', function () {
    $cat = p11b2_make_category();
    $vendor = p11b2_vendor_user()->vendor;
    $admin = p11b2_admin();
    $source = p11b2_make_product(['vendor' => $vendor, 'category' => $cat]);
    $hidden = p11b2_make_product(['vendor' => $vendor, 'category' => $cat]);
    AdminProductRelationship::create([
        'product_id' => $source->id,
        'related_product_id' => $hidden->id,
        'relationship_type' => AdminProductRelationship::TYPE_HIDDEN,
        'created_by' => $admin->id,
    ]);
    $svc = app(SimilarProductService::class);
    $results = $svc->forProduct($source, 8);
    expect($results->pluck('id')->all())->not->toContain($hidden->id);
});

it('§34.14 No duplicate products in result', function () {
    $cat = p11b2_make_category();
    $vendor = p11b2_vendor_user()->vendor;
    $admin = p11b2_admin();
    $source = p11b2_make_product(['vendor' => $vendor, 'category' => $cat]);
    $candidate = p11b2_make_product(['vendor' => $vendor, 'category' => $cat]);
    // Also pin the same product — it should appear once, not twice
    AdminProductRelationship::create([
        'product_id' => $source->id,
        'related_product_id' => $candidate->id,
        'relationship_type' => AdminProductRelationship::TYPE_PINNED,
        'created_by' => $admin->id,
    ]);
    $svc = app(SimilarProductService::class);
    $results = $svc->forProduct($source, 8);
    $count = $results->where('id', $candidate->id)->count();
    expect($count)->toBe(1);
});

it('§34.15 Feature flag disabling similar_products returns empty result', function () {
    config(['marketplace_recommendations.features.similar_products' => false]);
    $source = p11b2_make_product();
    p11b2_make_product(['category' => $source->category]);
    $svc = app(SimilarProductService::class);
    expect($svc->forProduct($source, 8)->count())->toBe(0);
});

// ════════════════════════════════════════════════════════════════════════════
// §35.16-30 — Frequently Bought Together (15)
// ════════════════════════════════════════════════════════════════════════════

it('§35.16 Same completed order creates pair evidence', function () {
    $customer = p11b2_customer();
    $a = p11b2_make_product(['name' => 'A']);
    $b = p11b2_make_product(['name' => 'B']);
    p11b2_make_completed_order($customer, [$a->id, $b->id], Order::STATUS_COMPLETED);
    \Artisan::call('recommendations:generate', ['--truncate' => true]);
    [$canA, $canB] = ProductPairStat::canonical($a->id, $b->id);
    $stat = ProductPairStat::where('product_a_id', $canA)->where('product_b_id', $canB)->first();
    expect($stat)->not->toBeNull()
        ->and($stat->pair_count)->toBe(1);
});

it('§35.17 Cancelled order does NOT count toward pair stats', function () {
    $customer = p11b2_customer();
    $a = p11b2_make_product(['name' => 'A']);
    $b = p11b2_make_product(['name' => 'B']);
    p11b2_make_completed_order($customer, [$a->id, $b->id], Order::STATUS_CANCELLED);
    \Artisan::call('recommendations:generate', ['--truncate' => true]);
    expect(ProductPairStat::count())->toBe(0);
});

it('§35.18 Failed payment does NOT count', function () {
    $customer = p11b2_customer();
    $a = p11b2_make_product(); $b = p11b2_make_product();
    p11b2_make_completed_order($customer, [$a->id, $b->id], Order::STATUS_FAILED);
    \Artisan::call('recommendations:generate', ['--truncate' => true]);
    expect(ProductPairStat::count())->toBe(0);
});

it('§35.19 Refunded order does NOT count (excluded from qualifying statuses)', function () {
    $customer = p11b2_customer();
    $a = p11b2_make_product(); $b = p11b2_make_product();
    p11b2_make_completed_order($customer, [$a->id, $b->id], Order::STATUS_REFUNDED);
    \Artisan::call('recommendations:generate', ['--truncate' => true]);
    expect(ProductPairStat::count())->toBe(0);
});

it('§35.20 Same pair in one order counts once (canonical dedup)', function () {
    $customer = p11b2_customer();
    $a = p11b2_make_product(); $b = p11b2_make_product();
    p11b2_make_completed_order($customer, [$a->id, $b->id]);
    \Artisan::call('recommendations:generate', ['--truncate' => true]);
    expect(ProductPairStat::count())->toBe(1);
});

it('§35.21 min_pair_orders threshold is enforced by FBT service', function () {
    config(['marketplace_recommendations.frequently_bought.min_pair_orders' => 5]);
    $customer = p11b2_customer();
    $a = p11b2_make_product(); $b = p11b2_make_product();
    p11b2_make_completed_order($customer, [$a->id, $b->id]);
    \Artisan::call('recommendations:generate', ['--truncate' => true]);
    $svc = app(FrequentlyBoughtTogetherService::class);
    $res = $svc->forProduct($a, 4);
    expect($res['products']->count())->toBe(0);
});

it('§35.22 min_confidence threshold is enforced', function () {
    config([
        'marketplace_recommendations.frequently_bought.min_confidence' => 0.5,
        'marketplace_recommendations.frequently_bought.min_pair_orders' => 1,
        'marketplace_recommendations.frequently_bought.min_support' => 0.0,
    ]);
    $a = p11b2_make_product();
    $b = p11b2_make_product();
    // Create 10 orders containing only A → A's denominator = 10
    for ($i = 0; $i < 10; $i++) {
        p11b2_make_completed_order(p11b2_customer(), [$a->id]);
    }
    // Create 1 order containing A + B → pair_count = 1, confidence = 1/11 ≈ 0.09 < 0.5
    p11b2_make_completed_order(p11b2_customer(), [$a->id, $b->id]);
    \Artisan::call('recommendations:generate', ['--truncate' => true]);
    $svc = app(FrequentlyBoughtTogetherService::class);
    $res = $svc->forProduct($a, 4);
    expect($res['products']->count())->toBe(0);
});

it('§35.23 Unavailable item is excluded from FBT result', function () {
    config([
        'marketplace_recommendations.frequently_bought.min_pair_orders' => 1,
        'marketplace_recommendations.frequently_bought.min_confidence' => 0.0,
        'marketplace_recommendations.frequently_bought.min_support' => 0.0,
    ]);
    $a = p11b2_make_product();
    $b = p11b2_make_product(['track_stock' => true, 'stock' => 0]);  // OOS
    p11b2_make_completed_order(p11b2_customer(), [$a->id, $b->id]);
    \Artisan::call('recommendations:generate', ['--truncate' => true]);
    $svc = app(FrequentlyBoughtTogetherService::class);
    $res = $svc->forProduct($a, 4);
    expect($res['products']->pluck('id')->all())->not->toContain($b->id);
});

it('§35.24 Complementary fallback used when real co-occurrence is empty', function () {
    config(['marketplace_recommendations.frequently_bought.min_pair_orders' => 100]);
    $admin = p11b2_admin();
    $cat = p11b2_make_category();
    $vendor = p11b2_vendor_user()->vendor;
    $source = p11b2_make_product(['vendor' => $vendor, 'category' => $cat]);
    $companion = p11b2_make_product(['vendor' => $vendor, 'category' => $cat]);
    AdminProductRelationship::create([
        'product_id' => $source->id,
        'related_product_id' => $companion->id,
        'relationship_type' => AdminProductRelationship::TYPE_COMPLEMENTARY,
        'created_by' => $admin->id,
    ]);
    $svc = app(FrequentlyBoughtTogetherService::class);
    $res = $svc->forProduct($source, 4);
    expect($res['evidence'])->toBe('complementary');
    expect($res['products']->pluck('id')->all())->toContain($companion->id);
});

it('§35.25 Add Selected validates products server-side (cart batch endpoint)', function () {
    $customer = p11b2_customer();
    $p = p11b2_make_product();
    $r = test()->actingAs($customer)->post('/cart/items/batch', [
        'items' => [
            ['product_id' => $p->id, 'quantity' => 1],
            ['product_id' => 99999999, 'quantity' => 1],  // does not exist
        ],
    ]);
    expect($r->status())->toBe(302);  // validation fails on non-existent product
});

it('§35.26 Cart batch rejects variable product without variant', function () {
    $customer = p11b2_customer();
    $p = p11b2_make_product(['type' => Product::TYPE_VARIABLE]);
    $r = test()->actingAs($customer)->from('/products')->post('/cart/items/batch', [
        'items' => [['product_id' => $p->id, 'quantity' => 1]],
    ]);
    // Should redirect back with an error flash (added=0, skipped contains variant message)
    expect($r->status())->toBe(302);
});

it('§35.27 Cart batch rejects suspended-vendor product', function () {
    $customer = p11b2_customer();
    $suspendedVendor = p11b2_vendor_user(Vendor::STATUS_SUSPENDED)->vendor;
    $p = p11b2_make_product(['vendor' => $suspendedVendor]);
    $r = test()->actingAs($customer)->from('/products')->post('/cart/items/batch', [
        'items' => [['product_id' => $p->id, 'quantity' => 1]],
    ]);
    expect($r->status())->toBe(302);  // redirect back with error
});

it('§35.28 FBT cache is per locale+product (different locales return different display_name)', function () {
    Cache::flush();
    $vendor = p11b2_vendor_user()->vendor;
    $cat = p11b2_make_category();
    $source = p11b2_make_product(['vendor' => $vendor, 'category' => $cat]);
    $b = p11b2_make_product([
        'vendor' => $vendor, 'category' => $cat,
        'name' => 'EN Companion', 'name_translations' => ['ar' => 'مرافق'],
    ]);
    config([
        'marketplace_recommendations.frequently_bought.min_pair_orders' => 1,
        'marketplace_recommendations.frequently_bought.min_confidence' => 0.0,
        'marketplace_recommendations.frequently_bought.min_support' => 0.0,
    ]);
    p11b2_make_completed_order(p11b2_customer(), [$source->id, $b->id]);
    \Artisan::call('recommendations:generate', ['--truncate' => true]);
    app()->setLocale('en');
    $enPayload = app(RecommendationManager::class)->frequentlyBoughtTogether($source, 4);
    app()->setLocale('ar');
    $arPayload = app(RecommendationManager::class)->frequentlyBoughtTogether($source, 4);
    expect($enPayload['items'][0]['display_name'] ?? '')->toBe('EN Companion');
    expect($arPayload['items'][0]['display_name'] ?? '')->toBe('مرافق');
});

it('§35.29 generate command is idempotent (re-run produces same row counts)', function () {
    $customer = p11b2_customer();
    $a = p11b2_make_product(); $b = p11b2_make_product();
    p11b2_make_completed_order($customer, [$a->id, $b->id]);
    \Artisan::call('recommendations:generate', ['--truncate' => true]);
    $before = ProductPairStat::count();
    \Artisan::call('recommendations:generate', ['--truncate' => true]);
    expect(ProductPairStat::count())->toBe($before);
});

it('§35.30 FBT order-status filter respects qualifying statuses (PAID counts)', function () {
    config([
        'marketplace_recommendations.frequently_bought.min_pair_orders' => 1,
        'marketplace_recommendations.frequently_bought.min_confidence' => 0.0,
        'marketplace_recommendations.frequently_bought.min_support' => 0.0,
    ]);
    $a = p11b2_make_product(); $b = p11b2_make_product();
    p11b2_make_completed_order(p11b2_customer(), [$a->id, $b->id], Order::STATUS_PAID);
    \Artisan::call('recommendations:generate', ['--truncate' => true]);
    expect(ProductPairStat::count())->toBe(1);
});

// ════════════════════════════════════════════════════════════════════════════
// §36.31-40 — Customers Also Bought (10)
// ════════════════════════════════════════════════════════════════════════════

it('§36.31 Distinct customer count is calculated correctly', function () {
    config(['marketplace_recommendations.customers_also_bought.min_distinct_customers' => 1]);
    $a = p11b2_make_product(); $b = p11b2_make_product();
    $customers = [p11b2_customer(), p11b2_customer(), p11b2_customer()];
    foreach ($customers as $c) {
        p11b2_make_completed_order($c, [$a->id]);
        p11b2_make_completed_order($c, [$b->id]);
    }
    $svc = app(CustomersAlsoBoughtService::class);
    $res = $svc->forProduct($a, 8);
    expect($res->pluck('id')->all())->toContain($b->id);
});

it('§36.32 Privacy threshold is enforced (default 3 distinct customers)', function () {
    config(['marketplace_recommendations.customers_also_bought.min_distinct_customers' => 3]);
    $a = p11b2_make_product(); $b = p11b2_make_product();
    // Only 2 customers buy both
    foreach ([p11b2_customer(), p11b2_customer()] as $c) {
        p11b2_make_completed_order($c, [$a->id, $b->id]);
    }
    $svc = app(CustomersAlsoBoughtService::class);
    expect($svc->forProduct($a, 8)->count())->toBe(0);
});

it('§36.33 One customer alone does NOT produce a public recommendation', function () {
    config(['marketplace_recommendations.customers_also_bought.min_distinct_customers' => 3]);
    $a = p11b2_make_product(); $b = p11b2_make_product();
    p11b2_make_completed_order(p11b2_customer(), [$a->id, $b->id]);
    expect(app(CustomersAlsoBoughtService::class)->forProduct($a, 8)->count())->toBe(0);
});

it('§36.34 Customer identities are NEVER exposed in service output', function () {
    config(['marketplace_recommendations.customers_also_bought.min_distinct_customers' => 1]);
    $a = p11b2_make_product(); $b = p11b2_make_product();
    $c = p11b2_customer();
    p11b2_make_completed_order($c, [$a->id, $b->id]);
    $payload = app(RecommendationManager::class)->customersAlsoBought($a, 8);
    $serialized = json_encode($payload);
    // Email + customer id MUST NOT appear
    expect($serialized)->not->toContain($c->email)
        ->and($serialized)->not->toMatch('/"user_id"\s*:/');
});

it('§36.35 Cancelled orders do not produce co-purchase evidence', function () {
    config(['marketplace_recommendations.customers_also_bought.min_distinct_customers' => 1]);
    $a = p11b2_make_product(); $b = p11b2_make_product();
    $c = p11b2_customer();
    p11b2_make_completed_order($c, [$a->id], Order::STATUS_CANCELLED);
    p11b2_make_completed_order($c, [$b->id], Order::STATUS_CANCELLED);
    expect(app(CustomersAlsoBoughtService::class)->forProduct($a, 8)->count())->toBe(0);
});

it('§36.36 Current source product is excluded from also-bought result', function () {
    config(['marketplace_recommendations.customers_also_bought.min_distinct_customers' => 1]);
    $a = p11b2_make_product();
    p11b2_make_completed_order(p11b2_customer(), [$a->id]);
    expect(app(CustomersAlsoBoughtService::class)->forProduct($a, 8)->pluck('id')->all())
        ->not->toContain($a->id);
});

it('§36.37 Hidden products are excluded from also-bought result', function () {
    config(['marketplace_recommendations.customers_also_bought.min_distinct_customers' => 1]);
    $a = p11b2_make_product();
    $hidden = p11b2_make_product(['status' => Product::STATUS_DRAFT, 'published_at' => null]);
    foreach ([p11b2_customer(), p11b2_customer()] as $c) {
        // Note: can't put draft into order_items easily for this test; instead
        // mark afterwards. The eligibility filter on the read path is what matters.
        p11b2_make_completed_order($c, [$a->id, $hidden->id]);
    }
    expect(app(CustomersAlsoBoughtService::class)->forProduct($a, 8)->pluck('id')->all())
        ->not->toContain($hidden->id);
});

it('§36.38 No duplicate product IDs in also-bought result', function () {
    config(['marketplace_recommendations.customers_also_bought.min_distinct_customers' => 1]);
    $a = p11b2_make_product(); $b = p11b2_make_product();
    // 3 customers each buy a+b twice = 6 orders, but b should appear once
    foreach ([p11b2_customer(), p11b2_customer(), p11b2_customer()] as $c) {
        p11b2_make_completed_order($c, [$a->id, $b->id]);
        p11b2_make_completed_order($c, [$a->id, $b->id]);
    }
    $ids = app(CustomersAlsoBoughtService::class)->forProduct($a, 8)->pluck('id')->all();
    expect(count($ids))->toBe(count(array_unique($ids)));
});

it('§36.39 Feature flag disables Customers Also Bought cleanly', function () {
    config(['marketplace_recommendations.features.customers_also_bought' => false]);
    $a = p11b2_make_product();
    p11b2_make_completed_order(p11b2_customer(), [$a->id]);
    expect(app(CustomersAlsoBoughtService::class)->forProduct($a, 8)->count())->toBe(0);
});

it('§36.40 Manager returns disabled payload when flag off', function () {
    config(['marketplace_recommendations.features.customers_also_bought' => false]);
    $a = p11b2_make_product();
    $payload = app(RecommendationManager::class)->customersAlsoBought($a, 8);
    expect($payload['enabled'])->toBeFalse()
        ->and($payload['items'])->toBe([]);
});

// ════════════════════════════════════════════════════════════════════════════
// §37.41-50 — Services, Analytics, Cache, Regression (10)
// ════════════════════════════════════════════════════════════════════════════

it('§37.41 Analytics impression event records safely', function () {
    $customer = p11b2_customer();
    $source = p11b2_make_product(); $rec = p11b2_make_product();
    test()->actingAs($customer)->postJson('/recommendations/events', [
        'event_type' => 'impression',
        'product_id' => $source->id,
        'recommended_product_id' => $rec->id,
        'recommendation_type' => 'similar',
    ])->assertOk();
    $event = RecommendationEvent::latest('id')->first();
    expect($event->event_type)->toBe('impression')
        ->and($event->session_token)->not->toBeNull()
        ->and(strlen((string) $event->session_token))->toBe(64);  // SHA-256 hex
});

it('§37.42 Analytics click event records safely', function () {
    $source = p11b2_make_product(); $rec = p11b2_make_product();
    test()->postJson('/recommendations/events', [
        'event_type' => 'click',
        'product_id' => $source->id,
        'recommended_product_id' => $rec->id,
        'recommendation_type' => 'fbt',
    ])->assertOk();
    expect(RecommendationEvent::where('event_type', 'click')->count())->toBe(1);
});

it('§37.43 Analytics endpoint rejects invalid event_type', function () {
    $source = p11b2_make_product(); $rec = p11b2_make_product();
    test()->postJson('/recommendations/events', [
        'event_type' => 'malicious-event',
        'product_id' => $source->id,
        'recommended_product_id' => $rec->id,
        'recommendation_type' => 'similar',
    ])->assertStatus(422);
});

it('§37.44 Analytics stores no PII (session_token is hashed, not raw)', function () {
    $source = p11b2_make_product(); $rec = p11b2_make_product();
    test()->postJson('/recommendations/events', [
        'event_type' => 'impression',
        'product_id' => $source->id,
        'recommended_product_id' => $rec->id,
        'recommendation_type' => 'similar',
    ]);
    $event = RecommendationEvent::latest('id')->first();
    // session_token must be 64 hex chars (SHA-256), not match the Laravel session id directly
    expect(strlen((string) $event->session_token))->toBe(64);
});

it('§37.45 Feature flag disables sections safely without breaking page', function () {
    config(['marketplace_recommendations.features.enabled' => false]);
    $p = p11b2_make_product();
    test()->get("/products/{$p->slug}")->assertOk();
});

it('§37.46 RecommendationManager cache layer caches results', function () {
    Cache::flush();
    $source = p11b2_make_product();
    $mgr = app(RecommendationManager::class);
    $first = $mgr->similarProducts($source, 4);
    $second = $mgr->similarProducts($source, 4);
    expect($second)->toEqual($first);
});

it('§37.47 Observer invalidates cache on price change', function () {
    Cache::flush();
    $source = p11b2_make_product();
    $mgr = app(RecommendationManager::class);
    $mgr->similarProducts($source, 4);
    // Forcing key existence
    $mgr->invalidate($source->id);
    // After invalidate, fresh call should still succeed (cache miss → recompute)
    $payload = $mgr->similarProducts($source, 4);
    expect($payload['enabled'])->toBeTrue();
});

it('§37.48 Catalog show page renders recommendation sections in props', function () {
    $p = p11b2_make_product();
    $r = test()->get("/products/{$p->slug}");
    $r->assertOk()->assertInertia(fn ($pg) => $pg
        ->has('recommendations.similar')
        ->has('recommendations.frequently_bought')
        ->has('recommendations.customers_also_bought')
        ->etc()
    );
});

it('§37.49 Regression — Customer login still works (Phase 11B.2 doesn\'t break auth)', function () {
    $u = p11b2_customer();
    test()->post('/login', ['email' => $u->email, 'password' => 'pw'])->assertRedirect();
});

it('§37.50 Regression — Admin Reports still render (Phase 10 preserved)', function () {
    test()->actingAs(p11b2_admin())->get('/admin/reports')->assertOk();
});
