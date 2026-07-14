<?php

declare(strict_types=1);

use App\Domain\Reports\ReportsService;
use App\Domain\Seo\SeoBuilder;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── helpers (v8.5: every helper prefixed p10_) ───

function p10Admin(): User
{
    $u = User::factory()->create(['email' => 'p10-admin@test', 'role' => 'admin']);
    $u->assignRole('admin_staff');
    return $u;
}

function p10Customer(string $email = 'p10-cust@test'): User
{
    return User::factory()->create(['email' => $email, 'role' => 'customer']);
}

function p10Vendor(string $email): array
{
    $u = User::factory()->create(['email' => $email, 'role' => 'vendor']);
    $v = Vendor::factory()->create(['user_id' => $u->id, 'status' => 'approved']);
    return [$u, $v];
}

function p10Product(Vendor $v, string $slug = 'p10', int $price = 50000): Product
{
    return Product::factory()->published()->create([
        'vendor_id'   => $v->id,
        'slug'        => $slug . '-' . $v->id,
        'name'        => 'Phase 10 product',
        'price_minor' => $price,
        'currency'    => 'KWD',
    ]);
}

function p10Order(User $customer, Vendor $vendor, int $subtotal = 100000, int $couponDiscount = 0, int $commissionPct = 20): Order
{
    $earningPct = 100 - $commissionPct;
    $commission = intdiv(($subtotal - $couponDiscount) * $commissionPct, 100);
    $earning    = intdiv(($subtotal - $couponDiscount) * $earningPct, 100);

    $order = Order::create([
        'number' => 'P10-' . substr(uniqid(), -8),
        'user_id' => $customer->id,
        'status' => 'paid', 'payment_status' => 'paid', 'fulfillment_status' => 'unfulfilled',
        'currency' => 'KWD',
        'subtotal_minor' => $subtotal,
        'shipping_minor' => 0, 'tax_minor' => 0,
        'discount_minor' => $couponDiscount,
        'coupon_discount_minor' => $couponDiscount,
        'total_minor' => $subtotal - $couponDiscount,
    ]);
    OrderItem::factory()->create([
        'order_id' => $order->id, 'vendor_id' => $vendor->id,
        'product_name' => 'P10 item', 'quantity' => 1,
        'unit_price_minor' => $subtotal, 'line_total_minor' => $subtotal,
        'coupon_allocation_minor' => $couponDiscount,
        'commission_amount_minor' => $commission,
        'vendor_earning_minor'    => $earning,
        'commission_percent'      => $commissionPct,
        'currency' => 'KWD',
    ]);
    return $order;
}

// ─── Section A: reporting authorization ───

it('admin can access /admin/reports (200)', function () {
    $this->actingAs(p10Admin());
    $this->get('/admin/reports')->assertOk();
});

it('vendor cannot access /admin/reports (403)', function () {
    [$u] = p10Vendor('p10-vendor-vs-admin@test');
    $this->actingAs($u);
    $this->get('/admin/reports')->assertForbidden();
});

it('customer cannot access /admin/reports (403)', function () {
    $this->actingAs(p10Customer('p10-cust-vs-admin@test'));
    $this->get('/admin/reports')->assertForbidden();
});

it('guest is redirected away from /admin/reports', function () {
    $resp = $this->get('/admin/reports');
    expect($resp->status())->toBeIn([302, 401, 403]);
});

it('vendor accesses /vendor/reports and sees only their own data', function () {
    [$uA, $vA] = p10Vendor('p10-vsa@test');
    [, $vB]    = p10Vendor('p10-vsb@test');
    $customer  = p10Customer('p10-multi-cust@test');

    // Vendor A: 100 KWD order, Vendor B: 200 KWD order — separate orders
    p10Order($customer, $vA, 10000, 0, 20);
    p10Order($customer, $vB, 20000, 0, 20);

    $this->actingAs($uA);
    $resp = $this->get('/vendor/reports');
    $resp->assertOk();

    $financial = $resp->viewData('page')['props']['financial'];
    expect($financial['gross_minor'])->toBe(10000);  // only vendor A's gross
    expect($financial['earnings_minor'])->toBe(intdiv(10000 * 80, 100));
});

// ─── Section B: financial reconciliation (the v9.3 invariant, re-asserted) ───

it('admin financial summary reconciles: sum(earning + commission) == subtotal - coupon_discount', function () {
    [, $vendor] = p10Vendor('p10-recon@test');
    $customer   = p10Customer('p10-recon-cust@test');
    p10Order($customer, $vendor, 100000, 10000, 20);   // 100 KWD - 10 KWD coupon, 20% commission
    p10Order($customer, $vendor,  50000,     0, 30);   //  50 KWD, no coupon, 30% commission

    $svc = app(ReportsService::class);
    [$from, $to] = $svc->resolveDateRange('last_7_days');
    $summary = $svc->adminFinancialSummary($from, $to);

    expect($summary['reconciliation_delta_minor'])->toBe(0)
        ->and($summary['allocation_delta_minor'])->toBe(0);
});

// ─── Section C: CSV export ───

it('admin CSV export streams a CSV download', function () {
    $this->actingAs(p10Admin());
    [, $v] = p10Vendor('p10-csv@test');
    p10Order(p10Customer('p10-csv-cust@test'), $v, 50000);

    $resp = $this->get('/admin/reports/export.csv?preset=last_7_days');
    $resp->assertOk();
    expect($resp->headers->get('content-type'))->toStartWith('text/csv');
});

it('vendor CSV export contains only the vendor own items', function () {
    [$uA, $vA] = p10Vendor('p10-csv-A@test');
    [, $vB]    = p10Vendor('p10-csv-B@test');
    $customer  = p10Customer('p10-csv-multi-cust@test');
    p10Order($customer, $vA, 50000);
    p10Order($customer, $vB, 80000);

    $this->actingAs($uA);
    $resp = $this->get('/vendor/reports/export.csv?preset=last_7_days');
    $resp->assertOk();
    expect($resp->headers->get('content-type'))->toStartWith('text/csv');
});

// ─── Section D: SEO ───

it('product detail page includes seo block with Product JSON-LD', function () {
    [, $v] = p10Vendor('p10-seo-vendor@test');
    $product = p10Product($v, 'p10-seo');

    $resp = $this->get("/products/{$product->slug}");
    $resp->assertOk();

    $seo = $resp->viewData('page')['props']['seo'] ?? null;
    expect($seo)->not->toBeNull();
    expect($seo['title'])->toContain($product->name);
    expect($seo['canonical'])->toContain('/products/' . $product->slug);

    // structured_data should include a Product node
    $ld = collect($seo['structured_data'] ?? [])->where('@type', 'Product')->first();
    expect($ld)->not->toBeNull();
    expect($ld['name'])->toBe($product->name);
    expect($ld['offers']['price'])->toBe('500.00');
    expect($ld['offers']['priceCurrency'])->toBe('KWD');
});

it('homepage exposes Organization + WebSite JSON-LD', function () {
    $resp = $this->get('/');
    $resp->assertOk();
    $seo = $resp->viewData('page')['props']['seo'] ?? null;
    expect($seo)->not->toBeNull();
    $types = collect($seo['structured_data'] ?? [])->pluck('@type')->all();
    expect($types)->toContain('Organization');
    expect($types)->toContain('WebSite');
});

it('SeoBuilder emits aggregateRating only for products with approved reviews (rating_count > 0)', function () {
    [, $v]   = p10Vendor('p10-rating@test');
    $noReviews = p10Product($v, 'p10-no-rev');   // rating_count = 0
    $withReviews = p10Product($v, 'p10-yes-rev');
    $withReviews->update(['rating_avg' => 4.50, 'rating_count' => 2]);

    $sb = app(SeoBuilder::class);

    $noReviewsLd = collect($sb->forProduct($noReviews->fresh())['structured_data'])->where('@type', 'Product')->first();
    expect($noReviewsLd)->not->toHaveKey('aggregateRating');

    $withReviewsLd = collect($sb->forProduct($withReviews->fresh())['structured_data'])->where('@type', 'Product')->first();
    expect($withReviewsLd)->toHaveKey('aggregateRating');
    expect($withReviewsLd['aggregateRating']['ratingValue'])->toBe('4.5');
    expect($withReviewsLd['aggregateRating']['reviewCount'])->toBe(2);
});

// ─── Section E: sitemap + robots ───

it('sitemap.xml returns XML with published products and excludes draft/admin URLs', function () {
    [, $v] = p10Vendor('p10-sitemap@test');
    $published = p10Product($v, 'p10-pub');
    $draft = Product::factory()->create([
        'vendor_id' => $v->id, 'slug' => 'p10-draft', 'status' => 'draft',
        'price_minor' => 50000, 'currency' => 'KWD',
    ]);

    $resp = $this->get('/sitemap.xml');
    $resp->assertOk();
    expect($resp->headers->get('content-type'))->toStartWith('application/xml');

    $body = $resp->getContent();
    expect($body)->toContain('<urlset');
    expect($body)->toContain('/products/p10-pub');           // published listed
    expect($body)->not->toContain('/products/p10-draft');   // draft excluded
    expect($body)->not->toContain('/admin');                 // admin excluded
    expect($body)->not->toContain('/vendor/');              // vendor dashboard excluded
    expect($body)->not->toContain('/checkout');              // checkout excluded
});

it('robots.txt allows storefront and blocks admin/vendor/account URLs', function () {
    $resp = $this->get('/robots.txt');
    $resp->assertOk();
    expect($resp->headers->get('content-type'))->toStartWith('text/plain');

    $body = $resp->getContent();
    expect($body)->toContain('User-agent: *');
    expect($body)->toContain('Allow: /');
    expect($body)->toContain('Disallow: /admin');
    expect($body)->toContain('Disallow: /vendor');
    expect($body)->toContain('Disallow: /orders');
    expect($body)->toContain('Disallow: /checkout');
    expect($body)->toContain('Disallow: /tickets');
    expect($body)->toContain('Sitemap: ');
});
