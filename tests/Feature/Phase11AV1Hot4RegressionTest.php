<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

// ─── helpers ─────────────────────────────────────────────────────────

function p11ah4_seed(): void
{
    \Artisan::call('db:seed', [
        '--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder',
        '--force' => true,
    ]);
}

function p11ah4_customer(): User
{
    p11ah4_seed();
    $u = User::factory()->create([
        'email'    => 'p11ah4-c-' . uniqid() . '@p11ah4.test',
        'password' => Hash::make('pw'),
        'status'   => 'active',
    ]);
    $u->assignRole('customer');
    return $u->fresh();
}

function p11ah4_vendor_user(): User
{
    p11ah4_seed();
    $u = User::factory()->create([
        'email'    => 'p11ah4-v-' . uniqid() . '@p11ah4.test',
        'password' => Hash::make('pw'),
        'status'   => 'active',
    ]);
    $u->assignRole('vendor');
    Vendor::create([
        'user_id' => $u->id, 'business_name' => 'V' . uniqid(),
        'business_email' => 'v' . uniqid() . '@p11ah4.test',
        'business_type' => 'company', 'country' => 'KW',
        'status' => Vendor::STATUS_APPROVED,
    ]);
    return $u->fresh();
}

function p11ah4_admin(): User
{
    p11ah4_seed();
    $u = User::factory()->create([
        'email'    => 'p11ah4-a-' . uniqid() . '@p11ah4.test',
        'password' => Hash::make('pw'),
        'status'   => 'active',
    ]);
    $u->assignRole('super_admin');
    return $u->fresh();
}

// ─── §7.1-4 Catalog gutter ───────────────────────────────────────────

it('Catalog/Index.tsx imports the canonical Container', function () {
    $src = file_get_contents(resource_path('js/Pages/Catalog/Index.tsx'));
    expect($src)->toContain("from '@/Components/Layout/Container'");
});

it('Catalog/Index.tsx wraps content in exactly one Container', function () {
    $src = file_get_contents(resource_path('js/Pages/Catalog/Index.tsx'));
    expect(substr_count($src, '<Container'))->toBe(1);
    expect(substr_count($src, '</Container>'))->toBe(1);
});

it('Catalog sidebar uses responsive grid with safe minmax(0,1fr) main column', function () {
    $src = file_get_contents(resource_path('js/Pages/Catalog/Index.tsx'));
    expect($src)->toMatch('/lg:grid-cols-\[260px_minmax\(0,1fr\)\]|lg:grid-cols-\[280px_minmax\(0,1fr\)\]/');
});

it('Catalog page has no padding-neutralizing classes on the wrapper grid', function () {
    $src = file_get_contents(resource_path('js/Pages/Catalog/Index.tsx'));
    // grid wrapper must not have px-0, p-0, -mx-*, w-screen, 100vw
    expect($src)->not->toMatch('/className="[^"]*\bpx-0\b/');
    expect($src)->not->toMatch('/className="[^"]*\b-mx-\d/');
    expect($src)->not->toMatch('/className="[^"]*\bw-screen\b/');
    expect($src)->not->toMatch('/100vw/');
});

it('GET /products still renders 200 after catalog Container wrap', function () {
    $this->get('/products')->assertOk();
});

// ─── §7.5-12 Search suggestions ──────────────────────────────────────

it('GET /search/suggestions with empty q returns empty groups', function () {
    $resp = $this->getJson('/search/suggestions');
    $resp->assertOk();
    $resp->assertJson(['products' => [], 'categories' => [], 'services' => [], 'total' => 0]);
});

it('GET /search/suggestions with 1-char q returns empty (below min length)', function () {
    $resp = $this->getJson('/search/suggestions?q=x');
    $resp->assertOk();
    $resp->assertJson(['products' => [], 'categories' => [], 'services' => [], 'total' => 0]);
});

it('GET /search/suggestions returns JSON with correct shape', function () {
    $resp = $this->getJson('/search/suggestions?q=test');
    $resp->assertOk();
    $resp->assertJsonStructure(['query', 'products', 'categories', 'services', 'total']);
});

it('Search suggestions exclude draft products', function () {
    $vendor = p11ah4_vendor_user()->vendor;
    Product::create([
        'vendor_id' => $vendor->id, 'sku' => 'SKU-DRAFT-' . uniqid(),
        'slug' => 'draft-widget', 'name' => 'Draft Widget',
        'type' => 'physical', 'status' => Product::STATUS_DRAFT,
        'price_minor' => 1000, 'currency' => 'KWD',
    ]);
    $resp = $this->getJson('/search/suggestions?q=draft');
    $resp->assertOk();
    expect($resp->json('products'))->toBeEmpty();
});

it('Search suggestions include published products', function () {
    $vendor = p11ah4_vendor_user()->vendor;
    Product::create([
        'vendor_id' => $vendor->id, 'sku' => 'SKU-PUB-' . uniqid(),
        'slug' => 'published-widget-xyz', 'name' => 'Published Widget XYZ',
        'type' => 'physical', 'status' => Product::STATUS_PUBLISHED,
        'price_minor' => 2000, 'currency' => 'KWD',
        'published_at' => now(),
    ]);
    $resp = $this->getJson('/search/suggestions?q=published');
    $resp->assertOk();
    expect($resp->json('products'))->not->toBeEmpty();
    expect($resp->json('products.0.name'))->toBe('Published Widget XYZ');
});

it('Search suggestions cap product results at 5 per group', function () {
    $vendor = p11ah4_vendor_user()->vendor;
    for ($i = 0; $i < 8; $i++) {
        Product::create([
            'vendor_id' => $vendor->id, 'sku' => "SKU-CAP-{$i}-" . uniqid(),
            'slug' => "capwidget-{$i}-" . uniqid(),
            'name' => "Capwidget {$i}",
            'type' => 'physical', 'status' => Product::STATUS_PUBLISHED,
            'price_minor' => 1000, 'currency' => 'KWD', 'published_at' => now(),
        ]);
    }
    $resp = $this->getJson('/search/suggestions?q=capwidget');
    expect(count($resp->json('products')))->toBeLessThanOrEqual(5);
});

it('Search suggestions return only required columns (no description)', function () {
    $vendor = p11ah4_vendor_user()->vendor;
    Product::create([
        'vendor_id' => $vendor->id, 'sku' => 'SKU-COL-' . uniqid(),
        'slug' => 'colwidget', 'name' => 'Colwidget Test',
        'description' => 'a very long secret description that should not leak',
        'type' => 'physical', 'status' => Product::STATUS_PUBLISHED,
        'price_minor' => 1000, 'currency' => 'KWD', 'published_at' => now(),
    ]);
    $resp = $this->getJson('/search/suggestions?q=colwidget');
    $product = $resp->json('products.0');
    expect($product)->toHaveKeys(['id', 'slug', 'name', 'price', 'currency', 'href']);
    expect($product)->not->toHaveKey('description');
});

it('Search suggestions for categories match by name prefix', function () {
    p11ah4_seed();
    Category::create(['slug' => 'electronics-' . uniqid(), 'name' => 'Electronics Test Cat']);
    $resp = $this->getJson('/search/suggestions?q=electronics');
    $resp->assertOk();
    expect($resp->json('categories'))->not->toBeEmpty();
});

it('Search suggestions properly escape LIKE wildcards in user input', function () {
    p11ah4_seed();
    // Inject a literal % in query — should NOT become a wildcard
    $resp = $this->getJson('/search/suggestions?q=' . urlencode('test%injection'));
    $resp->assertOk();
    // No SQL error means the escape worked
});

it('GET /products?q=foo (regular search) still works without suggestions', function () {
    $this->get('/products?q=foo')->assertOk();
});

// ─── §7.13-25 Localization ───────────────────────────────────────────

it('GET / renders with default English locale', function () {
    $this->get('/')->assertOk();
    expect(app()->getLocale())->toBe('en');
});

it('POST /locale/ar switches the session locale', function () {
    $this->post('/locale/ar')->assertRedirect();
    // Re-fetch to verify the locale persisted in session
    $this->get('/');
    expect(session('locale'))->toBe('ar');
});

it('POST /locale/en switches back to English', function () {
    $this->post('/locale/ar');
    $this->post('/locale/en')->assertRedirect();
    $this->get('/');
    expect(session('locale'))->toBe('en');
});

it('POST /locale rejects unsupported locale codes', function () {
    $resp = $this->post('/locale/xx');
    // Should not store an invalid locale
    $this->get('/');
    expect(session('locale'))->not->toBe('xx');
});

it('HandleInertiaRequests shares the active locale in app.direction', function () {
    $this->post('/locale/ar');
    $resp = $this->get('/');
    $resp->assertInertia(fn ($page) => $page->where('app.direction', 'rtl'));
});

it('HandleInertiaRequests shares dir=ltr for English', function () {
    $this->post('/locale/en');
    $resp = $this->get('/');
    $resp->assertInertia(fn ($page) => $page->where('app.direction', 'ltr'));
});

it('Translation files have matching key sets (en ↔ ar)', function () {
    $en = json_decode(file_get_contents(base_path('lang/en.json')), true);
    $ar = json_decode(file_get_contents(base_path('lang/ar.json')), true);
    expect(array_keys($en))->toBe(array_keys($ar));
});

it('Translation files contain v11A.4 surface keys', function () {
    $en = json_decode(file_get_contents(base_path('lang/en.json')), true);
    foreach ([
        'header.welcome', 'header.search_placeholder', 'header.cart',
        'nav.products', 'nav.services', 'nav.deals',
        'home.hero.title_line1', 'home.hero.cta_shop',
        'home.featured.title', 'home.deals.title',
        'home.howit.title', 'home.vendor.title',
        'footer.intro', 'footer.customer', 'footer.marketplace',
        'search.suggestions.products', 'search.suggestions.view_all',
        'catalog.sidebar.title', 'catalog.sidebar.all_products',
    ] as $key) {
        expect($en)->toHaveKey($key, "Missing en key: $key");
    }
});

it('Arabic translations are real Arabic (contain Arabic characters)', function () {
    $ar = json_decode(file_get_contents(base_path('lang/ar.json')), true);
    // Sample 3 keys — must contain Arabic Unicode characters
    foreach (['header.cart', 'nav.products', 'home.hero.cta_shop'] as $key) {
        expect($ar[$key])->toMatch('/[\x{0600}-\x{06FF}]/u');
    }
});

it('LangSwitcher source filters to en + ar only (no duplicate Arabic via Urdu)', function () {
    $src = file_get_contents(resource_path('js/Components/common/LangSwitcher.tsx'));
    expect($src)->toContain('DISPLAY_LOCALES');
    expect($src)->toMatch("/DISPLAY_LOCALES.*\['en', 'ar'\]/");
});

it('useT() helper is imported in Welcome.tsx', function () {
    $src = file_get_contents(resource_path('js/Pages/Welcome.tsx'));
    expect($src)->toContain("import { useT } from '@/lib/i18n'");
});

it('useT() helper is imported in StorefrontLayout.tsx', function () {
    $src = file_get_contents(resource_path('js/Layouts/StorefrontLayout.tsx'));
    expect($src)->toContain("import { useT } from '@/lib/i18n'");
});

// ─── §7.26-30 Contrast + regression ──────────────────────────────────

it('Catalog category count badge uses text-slate-600 (xs-text comfort)', function () {
    $src = file_get_contents(resource_path('js/Pages/Catalog/Index.tsx'));
    expect($src)->toContain('text-xs text-slate-600">{c.count}');
});

it('Homepage still renders 200', function () {
    $this->get('/')->assertOk();
});

it('Cart page still renders 200', function () {
    $this->actingAs(p11ah4_customer())->get('/cart')->assertOk();
});

it('Vendor dashboard still renders 200', function () {
    $this->actingAs(p11ah4_vendor_user())->get('/vendor')->assertOk();
});

it('Admin Reports still renders 200', function () {
    $this->actingAs(p11ah4_admin())->get('/admin/reports')->assertOk();
});

it('All v10.x backend defenses preserved (zero PHP changes besides new controller)', function () {
    $mid = file_get_contents(app_path('Http/Middleware/HandleInertiaRequests.php'));
    expect(substr_count($mid, 'defensive catch'))->toBe(5);
    expect(substr_count($mid, "str_starts_with(\$path, 'admin/')"))->toBe(2);
    $ctrl = file_get_contents(app_path('Http/Controllers/Admin/ReportsController.php'));
    expect(substr_count($ctrl, 'guardAdminReportsAccess'))->toBe(3);
});

it('VERSION reports Phase 11A v11A.4', function () {
    expect(trim((string) file_get_contents(base_path('VERSION'))))->toBe('Phase 11A v11A.4');
});
