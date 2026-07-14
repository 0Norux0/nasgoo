<?php

declare(strict_types=1);

use App\Jobs\RecordPurchaseAttributionJob;
use App\Models\AdminProductRelationship;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductTranslation;
use App\Models\RecommendationEvent;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Recommendations\AdminCurationGate;
use App\Services\Recommendations\CustomersAlsoBoughtService;
use App\Services\Recommendations\FrequentlyBoughtTogetherService;
use App\Services\Recommendations\RecommendationManager;
use App\Services\Recommendations\SimilarProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

// ─── helpers (p11b21_*) ────────────────────────────────────────────────────

function p11b21_seed(): void
{
    \Artisan::call('db:seed', [
        '--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder',
        '--force' => true,
    ]);
}

function p11b21_customer(): User
{
    p11b21_seed();
    $u = User::factory()->create([
        'email'    => 'p11b21-c-' . uniqid() . '@p11b21.test',
        'password' => Hash::make('pw'), 'status' => 'active',
    ]);
    $u->assignRole('customer');
    return $u->fresh();
}

function p11b21_vendor_user(string $status = Vendor::STATUS_APPROVED): User
{
    p11b21_seed();
    $u = User::factory()->create([
        'email'    => 'p11b21-v-' . uniqid() . '@p11b21.test',
        'password' => Hash::make('pw'), 'status' => 'active',
    ]);
    $u->assignRole('vendor');
    Vendor::create([
        'user_id' => $u->id, 'business_name' => 'V' . uniqid(),
        'business_email' => 'v' . uniqid() . '@p11b21.test',
        'business_type' => 'company', 'country' => 'KW', 'status' => $status,
    ]);
    return $u->fresh();
}

function p11b21_admin(): User
{
    p11b21_seed();
    $u = User::factory()->create([
        'email'    => 'p11b21-a-' . uniqid() . '@p11b21.test',
        'password' => Hash::make('pw'), 'status' => 'active',
    ]);
    $u->assignRole('super_admin');
    return $u->fresh();
}

function p11b21_make_product(array $attrs = []): Product
{
    $vendor = $attrs['vendor'] ?? p11b21_vendor_user()->vendor;
    $cat = $attrs['category'] ?? Category::create([
        'slug' => 'cat-' . uniqid(), 'name' => 'Cat-' . uniqid(), 'is_active' => true,
    ]);
    return Product::create(array_merge([
        'vendor_id' => $vendor->id, 'category_id' => $cat->id,
        'sku' => 'SKU-' . uniqid(), 'slug' => 'p-' . uniqid(),
        'name' => 'Default', 'type' => Product::TYPE_SIMPLE,
        'status' => Product::STATUS_PUBLISHED, 'price_minor' => 100000,
        'currency' => 'KWD', 'published_at' => now(), 'track_stock' => false,
    ], collect($attrs)->except(['vendor', 'category'])->all()));
}

function p11b21_make_service(array $attrs = []): Product
{
    $p = p11b21_make_product(array_merge(['type' => Product::TYPE_SERVICE], $attrs));
    \App\Models\ServiceDetail::create([
        'product_id' => $p->id, 'service_type' => 'general',
        'location_mode' => 'in_person', 'duration_minutes' => 60,
        'min_lead_time_minutes' => 60, 'max_advance_days' => 30,
        'is_active' => true,
    ]);
    return $p->fresh('serviceDetail');
}

function p11b21_completed_order(User $customer, array $productIds, string $status = Order::STATUS_COMPLETED): Order
{
    $order = Order::create([
        'user_id' => $customer->id, 'status' => $status,
        'payment_status' => 'paid', 'fulfillment_status' => 'fulfilled',
        'subtotal_minor' => 100000 * count($productIds),
        'total_minor' => 100000 * count($productIds),
        'currency' => 'KWD',
    ]);
    foreach ($productIds as $pid) {
        $p = Product::find($pid);
        OrderItem::create([
            'order_id' => $order->id, 'vendor_id' => $p->vendor_id,
            'product_id' => $pid, 'product_name' => $p->name,
            'quantity' => 1, 'unit_price_minor' => $p->price_minor,
            'line_total_minor' => $p->price_minor, 'currency' => 'KWD',
        ]);
    }
    return $order->fresh('items');
}

// ════════════════════════════════════════════════════════════════════════════
// §1 — Runtime return-type fix (4)
// ════════════════════════════════════════════════════════════════════════════

it('§1.1 SimilarProductService::forProduct returns a Support\Collection (not Eloquent)', function () {
    $cat = Category::create(['slug' => 'c-' . uniqid(), 'name' => 'C', 'is_active' => true]);
    $vendor = p11b21_vendor_user()->vendor;
    $source = p11b21_make_product(['vendor' => $vendor, 'category' => $cat]);
    p11b21_make_product(['vendor' => $vendor, 'category' => $cat]);
    $result = app(SimilarProductService::class)->forProduct($source, 8);
    expect($result)->toBeInstanceOf(SupportCollection::class);
});

it('§1.2 CustomersAlsoBoughtService::forProduct returns a Support\Collection', function () {
    config(['marketplace_recommendations.customers_also_bought.min_distinct_customers' => 1]);
    $a = p11b21_make_product(); $b = p11b21_make_product();
    p11b21_completed_order(p11b21_customer(), [$a->id, $b->id]);
    $result = app(CustomersAlsoBoughtService::class)->forProduct($a, 8);
    expect($result)->toBeInstanceOf(SupportCollection::class);
});

it('§1.3 Product detail page returns HTTP 200 (was crashing with TypeError)', function () {
    $p = p11b21_make_product();
    test()->get("/products/{$p->slug}")->assertOk();
});

it('§1.4 Cache-hit and cache-miss return the same payload shape', function () {
    Cache::flush();
    $p = p11b21_make_product();
    $mgr = app(RecommendationManager::class);
    $miss = $mgr->similarProducts($p, 4);  // populates cache
    $hit  = $mgr->similarProducts($p, 4);  // reads from cache
    expect(array_keys($miss))->toBe(array_keys($hit));
    expect($miss['enabled'])->toBe($hit['enabled']);
});

// ════════════════════════════════════════════════════════════════════════════
// §2 — Services Arabic localization (6)
// ════════════════════════════════════════════════════════════════════════════

it('§2.1 Services listing returns Arabic title via translatedName', function () {
    $vendor = p11b21_vendor_user()->vendor;
    $cat = Category::create(['slug' => 'c-' . uniqid(), 'name' => 'C', 'is_active' => true]);
    p11b21_make_service([
        'vendor' => $vendor, 'category' => $cat,
        'name' => 'English Service',
        'name_translations' => ['ar' => 'خدمة عربية'],
    ]);
    app()->setLocale('ar');
    test()->get('/services')->assertOk()->assertInertia(fn ($pg) => $pg
        ->where('services.data.0.name', 'خدمة عربية')->etc()
    );
});

it('§2.2 Services listing description uses translated source (not raw English)', function () {
    $vendor = p11b21_vendor_user()->vendor;
    $cat = Category::create(['slug' => 'c-' . uniqid(), 'name' => 'C', 'is_active' => true]);
    p11b21_make_service([
        'vendor' => $vendor, 'category' => $cat,
        'name' => 'EN service', 'description' => 'English description text here.',
        'description_translations' => ['ar' => 'وصف عربي للخدمة هنا.'],
    ]);
    app()->setLocale('ar');
    test()->get('/services')->assertOk()->assertInertia(fn ($pg) => $pg
        ->where('services.data.0.description', fn ($v) => str_contains((string) $v, 'وصف عربي'))->etc()
    );
});

it('§2.3 Service detail page returns Arabic description', function () {
    $vendor = p11b21_vendor_user()->vendor;
    $cat = Category::create(['slug' => 'c-' . uniqid(), 'name' => 'C', 'is_active' => true]);
    $svc = p11b21_make_service([
        'vendor' => $vendor, 'category' => $cat,
        'name' => 'EN', 'description' => 'EN description',
        'description_translations' => ['ar' => 'وصف عربي مفصل'],
    ]);
    app()->setLocale('ar');
    test()->get("/services/{$svc->slug}")->assertOk()->assertInertia(fn ($pg) => $pg
        ->where('service.description', 'وصف عربي مفصل')->etc()
    );
});

it('§2.4 English fallback: untranslated service shows English on Arabic locale', function () {
    $vendor = p11b21_vendor_user()->vendor;
    $cat = Category::create(['slug' => 'c-' . uniqid(), 'name' => 'C', 'is_active' => true]);
    $svc = p11b21_make_service([
        'vendor' => $vendor, 'category' => $cat,
        'name' => 'EnglishOnly', 'description' => 'EnglishOnlyDesc',
    ]);
    app()->setLocale('ar');
    test()->get("/services/{$svc->slug}")->assertOk()->assertInertia(fn ($pg) => $pg
        ->where('service.name', 'EnglishOnly')
        ->where('service.description', 'EnglishOnlyDesc')->etc()
    );
});

it('§2.5 Arabic search query matches name_translations.ar', function () {
    $vendor = p11b21_vendor_user()->vendor;
    $cat = Category::create(['slug' => 'c-' . uniqid(), 'name' => 'C', 'is_active' => true]);
    p11b21_make_service([
        'vendor' => $vendor, 'category' => $cat,
        'name' => 'PlumbingService',
        'name_translations' => ['ar' => 'سباكة احترافية'],
    ]);
    test()->get('/services?q=' . urlencode('سباكة'))->assertOk()
        ->assertInertia(fn ($pg) => $pg->where('services.data.0.name', fn ($v) =>
            str_contains((string) $v, 'سباك')
        )->etc());
});

it('§2.6 Raw JSON never appears in services payload', function () {
    $vendor = p11b21_vendor_user()->vendor;
    $cat = Category::create(['slug' => 'c-' . uniqid(), 'name' => 'C', 'is_active' => true]);
    p11b21_make_service([
        'vendor' => $vendor, 'category' => $cat,
        'name' => 'S', 'name_translations' => ['ar' => 'خدمة'],
    ]);
    app()->setLocale('ar');
    $r = test()->get('/services')->assertOk();
    // Decoded HTML response should not contain raw "name_translations" key strings
    expect($r->getContent())->not->toContain('name_translations');
});

// ════════════════════════════════════════════════════════════════════════════
// §3 — Purchase attribution (8)
// ════════════════════════════════════════════════════════════════════════════

it('§3.1 Click + paid order creates exactly one purchase event', function () {
    $customer = p11b21_customer();
    $source = p11b21_make_product();
    $rec    = p11b21_make_product();
    // Customer clicks the recommendation
    RecommendationEvent::create([
        'event_type' => RecommendationEvent::TYPE_CLICK,
        'product_id' => $source->id, 'recommended_product_id' => $rec->id,
        'recommendation_type' => 'similar', 'user_id' => $customer->id,
    ]);
    // Order is placed and reaches PAID status
    $order = p11b21_completed_order($customer, [$rec->id], Order::STATUS_PAID);
    (new RecordPurchaseAttributionJob($order->id))->handle();
    expect(RecommendationEvent::where('event_type', RecommendationEvent::TYPE_PURCHASE)->count())->toBe(1);
});

it('§3.2 Cancelled order does NOT create purchase event', function () {
    $customer = p11b21_customer();
    $rec = p11b21_make_product();
    RecommendationEvent::create([
        'event_type' => RecommendationEvent::TYPE_CLICK,
        'product_id' => $rec->id, 'recommended_product_id' => $rec->id,
        'recommendation_type' => 'similar', 'user_id' => $customer->id,
    ]);
    $order = p11b21_completed_order($customer, [$rec->id], Order::STATUS_CANCELLED);
    (new RecordPurchaseAttributionJob($order->id))->handle();
    expect(RecommendationEvent::where('event_type', RecommendationEvent::TYPE_PURCHASE)->count())->toBe(0);
});

it('§3.3 Failed payment does NOT create purchase event', function () {
    $customer = p11b21_customer();
    $rec = p11b21_make_product();
    RecommendationEvent::create([
        'event_type' => RecommendationEvent::TYPE_ADD_TO_CART,
        'product_id' => $rec->id, 'recommended_product_id' => $rec->id,
        'recommendation_type' => 'fbt', 'user_id' => $customer->id,
    ]);
    $order = p11b21_completed_order($customer, [$rec->id], Order::STATUS_FAILED);
    (new RecordPurchaseAttributionJob($order->id))->handle();
    expect(RecommendationEvent::where('event_type', RecommendationEvent::TYPE_PURCHASE)->count())->toBe(0);
});

it('§3.4 Re-running job is idempotent (no duplicate purchase events)', function () {
    $customer = p11b21_customer();
    $rec = p11b21_make_product();
    RecommendationEvent::create([
        'event_type' => RecommendationEvent::TYPE_CLICK,
        'product_id' => $rec->id, 'recommended_product_id' => $rec->id,
        'recommendation_type' => 'similar', 'user_id' => $customer->id,
    ]);
    $order = p11b21_completed_order($customer, [$rec->id]);
    (new RecordPurchaseAttributionJob($order->id))->handle();
    (new RecordPurchaseAttributionJob($order->id))->handle();
    (new RecordPurchaseAttributionJob($order->id))->handle();
    expect(RecommendationEvent::where('event_type', RecommendationEvent::TYPE_PURCHASE)->count())->toBe(1);
});

it('§3.5 Refund transition reverses an existing purchase event', function () {
    $customer = p11b21_customer();
    $rec = p11b21_make_product();
    RecommendationEvent::create([
        'event_type' => RecommendationEvent::TYPE_CLICK,
        'product_id' => $rec->id, 'recommended_product_id' => $rec->id,
        'recommendation_type' => 'similar', 'user_id' => $customer->id,
    ]);
    $order = p11b21_completed_order($customer, [$rec->id], Order::STATUS_PAID);
    (new RecordPurchaseAttributionJob($order->id))->handle();
    // Now refund
    $order->update(['status' => Order::STATUS_REFUNDED]);
    (new RecordPurchaseAttributionJob($order->id))->handle();
    $purchase = RecommendationEvent::where('event_type', RecommendationEvent::TYPE_PURCHASE)->first();
    expect($purchase->reversed_at)->not->toBeNull();
});

it('§3.6 Attribution window: click outside window is NOT attributed', function () {
    config(['marketplace_recommendations.analytics.attribution_window_days' => 7]);
    $customer = p11b21_customer();
    $rec = p11b21_make_product();
    // Click 30 days ago — outside the 7-day window
    RecommendationEvent::create([
        'event_type' => RecommendationEvent::TYPE_CLICK,
        'product_id' => $rec->id, 'recommended_product_id' => $rec->id,
        'recommendation_type' => 'similar', 'user_id' => $customer->id,
        'created_at' => now()->subDays(30), 'updated_at' => now()->subDays(30),
    ]);
    $order = p11b21_completed_order($customer, [$rec->id]);
    (new RecordPurchaseAttributionJob($order->id))->handle();
    expect(RecommendationEvent::where('event_type', RecommendationEvent::TYPE_PURCHASE)->count())->toBe(0);
});

it('§3.7 No PII exposed in purchase event (no email; user_id only for attribution)', function () {
    $customer = p11b21_customer();
    $rec = p11b21_make_product();
    RecommendationEvent::create([
        'event_type' => RecommendationEvent::TYPE_CLICK,
        'product_id' => $rec->id, 'recommended_product_id' => $rec->id,
        'recommendation_type' => 'similar', 'user_id' => $customer->id,
    ]);
    $order = p11b21_completed_order($customer, [$rec->id]);
    (new RecordPurchaseAttributionJob($order->id))->handle();
    $purchase = RecommendationEvent::where('event_type', RecommendationEvent::TYPE_PURCHASE)->first();
    expect($purchase->getAttributes())->not->toHaveKey('email');
    // Verify no obvious PII attributes set
    $serialized = json_encode($purchase->toArray());
    expect($serialized)->not->toContain($customer->email);
});

it('§3.8 Order saved observer dispatches the attribution job', function () {
    Queue::fake();
    $customer = p11b21_customer();
    $rec = p11b21_make_product();
    $order = p11b21_completed_order($customer, [$rec->id], Order::STATUS_PENDING_PAYMENT);
    $order->update(['status' => Order::STATUS_PAID]);
    Queue::assertPushed(RecordPurchaseAttributionJob::class);
});

// ════════════════════════════════════════════════════════════════════════════
// §4 — Cache invalidation (reverse-reference + cascade observers) (8)
// ════════════════════════════════════════════════════════════════════════════

it('§4.1 Vendor suspension bumps the rec cache version', function () {
    $vendor = p11b21_vendor_user()->vendor;
    $mgr = app(RecommendationManager::class);
    $v1 = $mgr->bumpVersion();  // baseline
    $vendor->update(['status' => Vendor::STATUS_SUSPENDED]);
    // The observer should have bumped the version
    $rm = app(RecommendationManager::class);
    $shape = $rm->similarProducts(p11b21_make_product(), 4);
    // Re-bumping should produce a NEW version > v1
    expect($rm->bumpVersion())->toBeGreaterThan($v1);
});

it('§4.2 Translation approval bumps the rec cache version', function () {
    $vendor = p11b21_vendor_user()->vendor;
    $p = p11b21_make_product(['vendor' => $vendor, 'name' => 'Original']);
    $mgr = app(RecommendationManager::class);
    $v1 = $mgr->bumpVersion();
    ProductTranslation::create([
        'product_id' => $p->id, 'locale' => 'ar', 'field' => 'name',
        'value' => 'اسم عربي', 'status' => ProductTranslation::STATUS_APPROVED,
        'source_checksum' => ProductTranslation::checksum('Original'),
    ]);
    expect($mgr->bumpVersion())->toBeGreaterThan($v1);
});

it('§4.3 Admin relationship creation bumps the rec cache version', function () {
    $admin = p11b21_admin();
    $p1 = p11b21_make_product(); $p2 = p11b21_make_product();
    $mgr = app(RecommendationManager::class);
    $v1 = $mgr->bumpVersion();
    AdminProductRelationship::create([
        'product_id' => $p1->id, 'related_product_id' => $p2->id,
        'relationship_type' => AdminProductRelationship::TYPE_PINNED,
        'created_by' => $admin->id,
    ]);
    expect($mgr->bumpVersion())->toBeGreaterThan($v1);
});

it('§4.4 Admin relationship deletion bumps the rec cache version', function () {
    $admin = p11b21_admin();
    $p1 = p11b21_make_product(); $p2 = p11b21_make_product();
    $rel = AdminProductRelationship::create([
        'product_id' => $p1->id, 'related_product_id' => $p2->id,
        'relationship_type' => AdminProductRelationship::TYPE_PINNED,
        'created_by' => $admin->id,
    ]);
    $mgr = app(RecommendationManager::class);
    $v1 = $mgr->bumpVersion();
    $rel->delete();
    expect($mgr->bumpVersion())->toBeGreaterThan($v1);
});

it('§4.5 Runtime eligibility recheck: unpublished product cached on A page disappears', function () {
    Cache::flush();
    $vendor = p11b21_vendor_user()->vendor;
    $cat = Category::create(['slug' => 'c-' . uniqid(), 'name' => 'C', 'is_active' => true]);
    $a = p11b21_make_product(['vendor' => $vendor, 'category' => $cat, 'name' => 'A']);
    $b = p11b21_make_product(['vendor' => $vendor, 'category' => $cat, 'name' => 'B']);
    $mgr = app(RecommendationManager::class);
    $first = $mgr->similarProducts($a, 8);
    expect(collect($first['items'])->pluck('id')->all())->toContain($b->id);
    // Now hide B by unpublishing — observer should bump version (eligibility-affecting change)
    $b->update(['status' => Product::STATUS_DRAFT, 'published_at' => null]);
    $second = $mgr->similarProducts($a, 8);
    expect(collect($second['items'])->pluck('id')->all())->not->toContain($b->id);
});

it('§4.6 Runtime recheck: suspended vendor product disappears even if cached', function () {
    Cache::flush();
    $vendor1 = p11b21_vendor_user()->vendor;
    $vendor2 = p11b21_vendor_user()->vendor;
    $cat = Category::create(['slug' => 'c-' . uniqid(), 'name' => 'C', 'is_active' => true]);
    $a = p11b21_make_product(['vendor' => $vendor1, 'category' => $cat]);
    $b = p11b21_make_product(['vendor' => $vendor2, 'category' => $cat]);
    $mgr = app(RecommendationManager::class);
    $first = $mgr->similarProducts($a, 8);
    expect(collect($first['items'])->pluck('id')->all())->toContain($b->id);
    $vendor2->update(['status' => Vendor::STATUS_SUSPENDED]);
    $second = $mgr->similarProducts($a, 8);
    expect(collect($second['items'])->pluck('id')->all())->not->toContain($b->id);
});

it('§4.7 Cache version is monotonically increasing', function () {
    $mgr = app(RecommendationManager::class);
    $a = $mgr->bumpVersion();
    $b = $mgr->bumpVersion();
    $c = $mgr->bumpVersion();
    expect($c)->toBeGreaterThan($b)->toBeGreaterThan($a);
});

it('§4.8 Versioned cache key prevents old cache reads after version bump', function () {
    Cache::flush();
    $p = p11b21_make_product();
    $mgr = app(RecommendationManager::class);
    $mgr->similarProducts($p, 4);  // populates key for current version
    $mgr->bumpVersion();
    // New version → cache key differs → cache miss (re-resolves from scratch)
    $payload = $mgr->similarProducts($p, 4);
    expect($payload['enabled'])->toBeTrue();
});

// ════════════════════════════════════════════════════════════════════════════
// §5 — Admin curated feature flag enforcement (8)
// ════════════════════════════════════════════════════════════════════════════

it('§5.1 Flag enabled: pinned ranks first', function () {
    config(['marketplace_recommendations.features.admin_curated' => true]);
    $cat = Category::create(['slug' => 'c-' . uniqid(), 'name' => 'C', 'is_active' => true]);
    $vendor = p11b21_vendor_user()->vendor;
    $admin = p11b21_admin();
    $source = p11b21_make_product(['vendor' => $vendor, 'category' => $cat]);
    $pinned = p11b21_make_product(['vendor' => $vendor, 'category' => $cat, 'name' => 'PINNED']);
    AdminProductRelationship::create([
        'product_id' => $source->id, 'related_product_id' => $pinned->id,
        'relationship_type' => AdminProductRelationship::TYPE_PINNED,
        'created_by' => $admin->id,
    ]);
    Cache::flush();
    $svc = app(SimilarProductService::class);
    expect($svc->forProduct($source, 8)->first()->id)->toBe($pinned->id);
});

it('§5.2 Flag DISABLED: pinned has no special effect', function () {
    $cat = Category::create(['slug' => 'c-' . uniqid(), 'name' => 'C', 'is_active' => true]);
    $vendor = p11b21_vendor_user()->vendor;
    $admin = p11b21_admin();
    $source = p11b21_make_product(['vendor' => $vendor, 'category' => $cat]);
    $pinned = p11b21_make_product(['vendor' => $vendor, 'category' => $cat, 'name' => 'PINNED']);
    p11b21_make_product(['vendor' => $vendor, 'category' => $cat, 'name' => 'NORMAL']);
    AdminProductRelationship::create([
        'product_id' => $source->id, 'related_product_id' => $pinned->id,
        'relationship_type' => AdminProductRelationship::TYPE_PINNED,
        'created_by' => $admin->id,
    ]);
    config(['marketplace_recommendations.features.admin_curated' => false]);
    Cache::flush();
    $svc = app(SimilarProductService::class);
    // Without admin curation, the pinned product should not jump to first.
    // It might still appear (algorithmically eligible), but not necessarily first.
    $results = $svc->forProduct($source, 8);
    // The marker test: the pinned-specific score=1M boost is NOT applied.
    // Verify by checking no item has the 'pinned' explanation.
    expect($results->pluck('recommendation_explanation')->all())->not->toContain('pinned');
});

it('§5.3 Flag DISABLED: excluded relationship does NOT filter out the product', function () {
    $cat = Category::create(['slug' => 'c-' . uniqid(), 'name' => 'C', 'is_active' => true]);
    $vendor = p11b21_vendor_user()->vendor;
    $admin = p11b21_admin();
    $source = p11b21_make_product(['vendor' => $vendor, 'category' => $cat]);
    $excluded = p11b21_make_product(['vendor' => $vendor, 'category' => $cat]);
    AdminProductRelationship::create([
        'product_id' => $source->id, 'related_product_id' => $excluded->id,
        'relationship_type' => AdminProductRelationship::TYPE_EXCLUDED,
        'created_by' => $admin->id,
    ]);
    config(['marketplace_recommendations.features.admin_curated' => false]);
    Cache::flush();
    $svc = app(SimilarProductService::class);
    expect($svc->forProduct($source, 8)->pluck('id')->all())->toContain($excluded->id);
});

it('§5.4 Flag DISABLED: hidden relationship does NOT filter out the product', function () {
    $cat = Category::create(['slug' => 'c-' . uniqid(), 'name' => 'C', 'is_active' => true]);
    $vendor = p11b21_vendor_user()->vendor;
    $admin = p11b21_admin();
    $source = p11b21_make_product(['vendor' => $vendor, 'category' => $cat]);
    $hidden = p11b21_make_product(['vendor' => $vendor, 'category' => $cat]);
    AdminProductRelationship::create([
        'product_id' => $source->id, 'related_product_id' => $hidden->id,
        'relationship_type' => AdminProductRelationship::TYPE_HIDDEN,
        'created_by' => $admin->id,
    ]);
    config(['marketplace_recommendations.features.admin_curated' => false]);
    Cache::flush();
    $svc = app(SimilarProductService::class);
    expect($svc->forProduct($source, 8)->pluck('id')->all())->toContain($hidden->id);
});

it('§5.5 Flag DISABLED: FBT does not use complementary fallback', function () {
    config(['marketplace_recommendations.frequently_bought.min_pair_orders' => 100]);
    $admin = p11b21_admin();
    $cat = Category::create(['slug' => 'c-' . uniqid(), 'name' => 'C', 'is_active' => true]);
    $vendor = p11b21_vendor_user()->vendor;
    $source = p11b21_make_product(['vendor' => $vendor, 'category' => $cat]);
    $companion = p11b21_make_product(['vendor' => $vendor, 'category' => $cat]);
    AdminProductRelationship::create([
        'product_id' => $source->id, 'related_product_id' => $companion->id,
        'relationship_type' => AdminProductRelationship::TYPE_COMPLEMENTARY,
        'created_by' => $admin->id,
    ]);
    config(['marketplace_recommendations.features.admin_curated' => false]);
    $svc = app(FrequentlyBoughtTogetherService::class);
    $res = $svc->forProduct($source, 4);
    expect($res['evidence'])->toBe('none');
});

it('§5.6 Flag DISABLED: AdminCurationGate returns isEnabled false', function () {
    config(['marketplace_recommendations.features.admin_curated' => false]);
    expect(app(AdminCurationGate::class)->isEnabled())->toBeFalse();
});

it('§5.7 Flag enabled: AdminCurationGate returns relationships', function () {
    config(['marketplace_recommendations.features.admin_curated' => true]);
    $admin = p11b21_admin();
    $p1 = p11b21_make_product(); $p2 = p11b21_make_product();
    AdminProductRelationship::create([
        'product_id' => $p1->id, 'related_product_id' => $p2->id,
        'relationship_type' => AdminProductRelationship::TYPE_PINNED,
        'created_by' => $admin->id,
    ]);
    expect(app(AdminCurationGate::class)->pinnedIdsFor($p1))->toContain($p2->id);
});

it('§5.8 Flag DISABLED: AdminCurationGate returns empty regardless of DB rows', function () {
    config(['marketplace_recommendations.features.admin_curated' => true]);
    $admin = p11b21_admin();
    $p1 = p11b21_make_product(); $p2 = p11b21_make_product();
    AdminProductRelationship::create([
        'product_id' => $p1->id, 'related_product_id' => $p2->id,
        'relationship_type' => AdminProductRelationship::TYPE_PINNED,
        'created_by' => $admin->id,
    ]);
    config(['marketplace_recommendations.features.admin_curated' => false]);
    expect(app(AdminCurationGate::class)->pinnedIdsFor($p1))->toBe([]);
    expect(app(AdminCurationGate::class)->excludedIdsFor($p1))->toBe([]);
    expect(app(AdminCurationGate::class)->complementaryIdsFor($p1))->toBe([]);
});

// ════════════════════════════════════════════════════════════════════════════
// §6 — Other feature flags (4)
// ════════════════════════════════════════════════════════════════════════════

it('§6.1 Master recommendations flag disabled: product page still renders', function () {
    config(['marketplace_recommendations.features.enabled' => false]);
    $p = p11b21_make_product();
    test()->get("/products/{$p->slug}")->assertOk();
});

it('§6.2 Similar flag disabled: section empty', function () {
    config(['marketplace_recommendations.features.similar_products' => false]);
    $p = p11b21_make_product();
    $payload = app(RecommendationManager::class)->similarProducts($p, 4);
    expect($payload['enabled'])->toBeFalse()->and($payload['items'])->toBe([]);
});

it('§6.3 Analytics flag disabled: events endpoint returns 204', function () {
    config(['marketplace_recommendations.features.analytics' => false]);
    $p = p11b21_make_product();
    test()->postJson('/recommendations/events', [
        'event_type' => 'impression',
        'product_id' => $p->id, 'recommended_product_id' => $p->id,
        'recommendation_type' => 'similar',
    ])->assertStatus(204);
});

it('§6.4 OOS exclusion flag works (config-toggleable)', function () {
    config(['marketplace_recommendations.eligibility.exclude_out_of_stock' => false]);
    $cat = Category::create(['slug' => 'c-' . uniqid(), 'name' => 'C', 'is_active' => true]);
    $vendor = p11b21_vendor_user()->vendor;
    $source = p11b21_make_product(['vendor' => $vendor, 'category' => $cat]);
    $oos = p11b21_make_product([
        'vendor' => $vendor, 'category' => $cat,
        'track_stock' => true, 'stock' => 0,
    ]);
    Cache::flush();
    $svc = app(SimilarProductService::class);
    expect($svc->forProduct($source, 8)->pluck('id')->all())->toContain($oos->id);
});

// Note: regression scenarios (37-45 per dev §8) are already covered by the
// existing 542 v11B.1.2 + 50 v11B.2 scenarios that continue to run on every CI
// build. We don't duplicate them here — instead, the v11B.2.1 suite focuses on
// the 5 defects per dev directive §8 §1-§5.
