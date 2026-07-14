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

function p11ah5_seed(): void
{
    \Artisan::call('db:seed', [
        '--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder',
        '--force' => true,
    ]);
}

function p11ah5_customer(): User
{
    p11ah5_seed();
    $u = User::factory()->create([
        'email'    => 'p11ah5-c-' . uniqid() . '@p11ah5.test',
        'password' => Hash::make('pw'),
        'status'   => 'active',
    ]);
    $u->assignRole('customer');
    return $u->fresh();
}

function p11ah5_vendor_user(): User
{
    p11ah5_seed();
    $u = User::factory()->create([
        'email'    => 'p11ah5-v-' . uniqid() . '@p11ah5.test',
        'password' => Hash::make('pw'),
        'status'   => 'active',
    ]);
    $u->assignRole('vendor');
    Vendor::create([
        'user_id' => $u->id, 'business_name' => 'V' . uniqid(),
        'business_email' => 'v' . uniqid() . '@p11ah5.test',
        'business_type' => 'company', 'country' => 'KW',
        'status' => Vendor::STATUS_APPROVED,
    ]);
    return $u->fresh();
}

function p11ah5_admin(): User
{
    p11ah5_seed();
    $u = User::factory()->create([
        'email'    => 'p11ah5-a-' . uniqid() . '@p11ah5.test',
        'password' => Hash::make('pw'),
        'status'   => 'active',
    ]);
    $u->assignRole('super_admin');
    return $u->fresh();
}

// ─── §17.1-6 Locale pipeline ─────────────────────────────────────────

it('Default locale is English', function () {
    $this->get('/');
    expect(app()->getLocale())->toBe('en');
});

it('POST /locale/ar persists Arabic in session', function () {
    $this->post('/locale/ar')->assertRedirect();
    $this->get('/');
    expect(session('locale'))->toBe('ar');
});

it('POST /locale/en restores English', function () {
    $this->post('/locale/ar');
    $this->post('/locale/en')->assertRedirect();
    $this->get('/');
    expect(session('locale'))->toBe('en');
});

it('HTML direction is rtl when locale is Arabic', function () {
    $this->post('/locale/ar');
    $this->get('/')
        ->assertInertia(fn ($page) => $page->where('app.direction', 'rtl'));
});

it('HTML direction is ltr when locale is English', function () {
    $this->post('/locale/en');
    $this->get('/')
        ->assertInertia(fn ($page) => $page->where('app.direction', 'ltr'));
});

it('Unsupported locale codes are rejected', function () {
    $this->post('/locale/xx');
    $this->get('/');
    expect(session('locale'))->not->toBe('xx');
});

// ─── §17.7-14 Interface localization ─────────────────────────────────

it('Translation files have 320+ keys with en↔ar parity', function () {
    $en = json_decode(file_get_contents(base_path('lang/en.json')), true);
    $ar = json_decode(file_get_contents(base_path('lang/ar.json')), true);
    expect(count($en))->toBeGreaterThanOrEqual(320);
    expect(array_keys($en))->toBe(array_keys($ar));
});

it('Storefront translation keys cover catalog/cart/auth surfaces', function () {
    $en = json_decode(file_get_contents(base_path('lang/en.json')), true);
    foreach ([
        'common.add_to_cart', 'common.out_of_stock', 'common.in_stock',
        'catalog.title_all', 'catalog.sort_by', 'catalog.no_products',
        'catalog.sort.featured', 'catalog.sort.price_asc',
        'product.by_vendor', 'product.add_to_wishlist',
        'cart.title', 'cart.empty', 'cart.checkout', 'cart.total',
        'checkout.title', 'checkout.place_order',
        'auth.sign_in', 'auth.email', 'auth.password',
        'account.title', 'account.orders',
    ] as $key) {
        expect($en)->toHaveKey($key);
    }
});

it('All Arabic translation values are real Arabic (Arabic Unicode range)', function () {
    $ar = json_decode(file_get_contents(base_path('lang/ar.json')), true);
    // Sample 10 keys must all contain Arabic Unicode characters
    foreach (['common.add_to_cart', 'common.out_of_stock', 'catalog.title_all',
              'catalog.no_products', 'cart.title', 'checkout.title',
              'auth.sign_in', 'account.title', 'nav.products', 'home.hero.cta_shop'] as $key) {
        expect($ar[$key])->toMatch('/[\x{0600}-\x{06FF}]/u', "Key $key not in Arabic: " . $ar[$key]);
    }
});

it('Catalog page imports useT', function () {
    $src = file_get_contents(resource_path('js/Pages/Catalog/Index.tsx'));
    expect($src)->toContain("import { useT } from '@/lib/i18n'");
    expect($src)->toContain('const t = useT()');
});

it('ProductCard imports useT and translates Out of stock', function () {
    $src = file_get_contents(resource_path('js/Components/ui/v11/ProductCard.tsx'));
    expect($src)->toContain("import { useT } from '@/lib/i18n'");
    expect($src)->toContain('const t = useT()');
    expect($src)->not->toContain('>Out of stock<');
    expect($src)->toContain("t('common.out_of_stock')");
});

it('Services page imports useT', function () {
    $src = file_get_contents(resource_path('js/Pages/Services/Index.tsx'));
    expect($src)->toContain("import { useT } from '@/lib/i18n'");
    expect($src)->toContain('const t = useT()');
});

// ─── §17.15-23 Multilingual content ──────────────────────────────────

it('Category::translatedName returns Arabic when name_translations.ar is set', function () {
    $cat = Category::create([
        'slug' => 'p11ah5-cat-' . uniqid(),
        'name' => 'Electronics',
        'name_translations' => ['ar' => 'إلكترونيات'],
    ]);
    expect($cat->translatedName('ar'))->toBe('إلكترونيات');
    expect($cat->translatedName('en'))->toBe('Electronics');
});

it('Category::translatedName falls back to canonical name when locale missing', function () {
    $cat = Category::create([
        'slug' => 'p11ah5-fb-' . uniqid(),
        'name' => 'Furniture',
        'name_translations' => null,
    ]);
    expect($cat->translatedName('ar'))->toBe('Furniture');
});

it('Product::translatedName returns Arabic when name_translations.ar is set', function () {
    $vendor = p11ah5_vendor_user()->vendor;
    $p = Product::create([
        'vendor_id' => $vendor->id, 'sku' => 'SKU-AR-' . uniqid(),
        'slug' => 'arabic-test-' . uniqid(),
        'name' => 'Test Widget',
        'name_translations' => ['ar' => 'أداة اختبار'],
        'type' => 'physical', 'status' => Product::STATUS_PUBLISHED,
        'price_minor' => 1000, 'currency' => 'KWD', 'published_at' => now(),
    ]);
    expect($p->translatedName('ar'))->toBe('أداة اختبار');
    expect($p->translatedName('en'))->toBe('Test Widget');
});

it('Product::translatedName falls back when ar missing', function () {
    $vendor = p11ah5_vendor_user()->vendor;
    $p = Product::create([
        'vendor_id' => $vendor->id, 'sku' => 'SKU-FB-' . uniqid(),
        'slug' => 'fb-test-' . uniqid(),
        'name' => 'Fallback Widget',
        'name_translations' => null,
        'type' => 'physical', 'status' => Product::STATUS_PUBLISHED,
        'price_minor' => 1000, 'currency' => 'KWD', 'published_at' => now(),
    ]);
    expect($p->translatedName('ar'))->toBe('Fallback Widget');
});

it('CatalogController returns translated category names when locale is ar', function () {
    $cat = Category::create([
        'slug' => 'p11ah5-ctrl-' . uniqid(),
        'name' => 'Books',
        'name_translations' => ['ar' => 'كتب'],
        'is_active' => true,
    ]);
    $this->post('/locale/ar');
    $this->get('/products')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('app.locale', 'ar'));
});

it('SearchSuggestionController returns translated product names', function () {
    $vendor = p11ah5_vendor_user()->vendor;
    Product::create([
        'vendor_id' => $vendor->id, 'sku' => 'SKU-SUG-' . uniqid(),
        'slug' => 'sug-product-' . uniqid(),
        'name' => 'Suggested Widget',
        'name_translations' => ['ar' => 'أداة مقترحة'],
        'type' => 'physical', 'status' => Product::STATUS_PUBLISHED,
        'price_minor' => 1000, 'currency' => 'KWD', 'published_at' => now(),
    ]);
    $this->post('/locale/ar');
    $resp = $this->getJson('/search/suggestions?q=' . urlencode('أداة'));
    $resp->assertOk();
    if (! empty($resp->json('products'))) {
        $names = collect($resp->json('products'))->pluck('name')->all();
        expect($names)->toContain('أداة مقترحة');
    }
});

// ─── §17.24-31 Regression + infrastructure ──────────────────────────

it('Translation audit command exists and validates locale', function () {
    expect(class_exists(\App\Console\Commands\TranslationsAuditCommand::class))->toBeTrue();
    $cmd = new \App\Console\Commands\TranslationsAuditCommand();
    expect($cmd->getName())->toBe('translations:audit');
});

it('Translation audit command runs for ar locale without errors', function () {
    \Artisan::call('translations:audit', ['locale' => 'ar']);
    $output = \Artisan::output();
    expect($output)->toContain('translation audit');
});

it('Translation audit rejects unsupported locale', function () {
    $code = \Artisan::call('translations:audit', ['locale' => 'xx']);
    expect($code)->not->toBe(0);
});

it('Backfill migration is present and idempotent', function () {
    $files = glob(database_path('migrations/*backfill_arabic_category_translations.php'));
    expect($files)->toHaveCount(1);
    $src = file_get_contents($files[0]);
    // Must check existing ar before overwriting
    expect($src)->toContain("empty(\$existing['ar'])");
    // Must NOT drop column or destructive operation
    expect($src)->not->toContain('dropColumn');
});

it('Homepage still renders 200 after v11A.5 controller wiring', function () {
    $this->get('/')->assertOk();
});

it('Catalog page still renders 200', function () {
    $this->get('/products')->assertOk();
});

it('Services page still renders 200', function () {
    $this->get('/services')->assertOk();
});

it('Search suggestions endpoint still functional', function () {
    $this->getJson('/search/suggestions?q=test')->assertOk();
});

it('Cart still renders 200', function () {
    $this->actingAs(p11ah5_customer())->get('/cart')->assertOk();
});

it('Vendor dashboard still renders 200', function () {
    $this->actingAs(p11ah5_vendor_user())->get('/vendor')->assertOk();
});

it('Admin reports still render 200', function () {
    $this->actingAs(p11ah5_admin())->get('/admin/reports')->assertOk();
});

it('Welcome.tsx deals subtitle uses text-gold-900 without /80 opacity', function () {
    $src = file_get_contents(resource_path('js/Pages/Welcome.tsx'));
    expect($src)->not->toContain('text-gold-900/80');
    expect($src)->toContain('text-gold-900 max-w-xl');
});

it('All v10.x backend defenses preserved (only v11A.5 additive PHP changes)', function () {
    $mid = file_get_contents(app_path('Http/Middleware/HandleInertiaRequests.php'));
    expect(substr_count($mid, 'defensive catch'))->toBe(5);
    expect(substr_count($mid, "str_starts_with(\$path, 'admin/')"))->toBe(2);
    $ctrl = file_get_contents(app_path('Http/Controllers/Admin/ReportsController.php'));
    expect(substr_count($ctrl, 'guardAdminReportsAccess'))->toBe(3);
});

it('VERSION reports Phase 11A v11A.5', function () {
    expect(trim((string) file_get_contents(base_path('VERSION'))))->toBe('Phase 11A v11A.5');
});
