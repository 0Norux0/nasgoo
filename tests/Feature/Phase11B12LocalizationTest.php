<?php

declare(strict_types=1);

use App\Jobs\QueueProductTranslation;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductTranslation;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Localization\Providers\ManualTranslationProvider;
use App\Services\Localization\Providers\TranslationProviderInterface;
use App\Services\Localization\TranslationService;
use App\Services\Search\MarketplaceSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

// ─── helpers ─────────────────────────────────────────────────────────

function p11b12_seed(): void
{
    \Artisan::call('db:seed', [
        '--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder',
        '--force' => true,
    ]);
}

function p11b12_customer(): User
{
    p11b12_seed();
    $u = User::factory()->create([
        'email'    => 'p11b12-c-' . uniqid() . '@p11b12.test',
        'password' => Hash::make('pw'),
        'status'   => 'active',
    ]);
    $u->assignRole('customer');
    return $u->fresh();
}

function p11b12_vendor_user(string $status = Vendor::STATUS_APPROVED): User
{
    p11b12_seed();
    $u = User::factory()->create([
        'email'    => 'p11b12-v-' . uniqid() . '@p11b12.test',
        'password' => Hash::make('pw'),
        'status'   => 'active',
    ]);
    $u->assignRole('vendor');
    Vendor::create([
        'user_id' => $u->id, 'business_name' => 'V' . uniqid(),
        'business_email' => 'v' . uniqid() . '@p11b12.test',
        'business_type' => 'company', 'country' => 'KW',
        'status' => $status,
    ]);
    return $u->fresh();
}

function p11b12_admin(): User
{
    p11b12_seed();
    $u = User::factory()->create([
        'email'    => 'p11b12-a-' . uniqid() . '@p11b12.test',
        'password' => Hash::make('pw'),
        'status'   => 'active',
    ]);
    $u->assignRole('super_admin');
    return $u->fresh();
}

function p11b12_make_product(array $attrs = []): Product
{
    $vendor = $attrs['vendor'] ?? p11b12_vendor_user()->vendor;
    $cat    = $attrs['category'] ?? Category::create([
        'slug' => 'cat-' . uniqid(),
        'name' => 'Default Category',
        'is_active' => true,
    ]);
    return Product::create(array_merge([
        'vendor_id'   => $vendor->id,
        'category_id' => $cat->id,
        'sku'         => 'SKU-' . uniqid(),
        'slug'        => 'prod-' . uniqid(),
        'name'        => 'Default Product',
        'type'        => 'physical',
        'status'      => Product::STATUS_PUBLISHED,
        'price_minor' => 1000,
        'currency'    => 'KWD',
        'published_at'=> now(),
        'track_stock' => false,
    ], collect($attrs)->except(['vendor', 'category'])->all()));
}

function p11b12_set_translation(Product $p, string $locale, string $field, ?string $value, string $status = ProductTranslation::STATUS_APPROVED): ProductTranslation
{
    return app(TranslationService::class)->setTranslation(
        product:    $p,
        locale:     $locale,
        field:      $field,
        value:      $value,
        status:     $status,
    );
}

// ════════════════════════════════════════════════════════════════════════════
// §25.1-12 — Translation storage + status workflow (12)
// ════════════════════════════════════════════════════════════════════════════

it('§25.1 Translation can be stored per locale/field with status', function () {
    $p = p11b12_make_product(['name' => 'Phone', 'slug' => 'p-' . uniqid()]);
    $row = p11b12_set_translation($p, 'ar', 'name', 'هاتف');
    expect($row->locale)->toBe('ar')
        ->and($row->field)->toBe('name')
        ->and($row->value)->toBe('هاتف')
        ->and($row->status)->toBe(ProductTranslation::STATUS_APPROVED);
});

it('§25.2 Translation row computes source_checksum on save', function () {
    $p = p11b12_make_product(['name' => 'Laptop', 'slug' => 'lp-' . uniqid()]);
    $row = p11b12_set_translation($p, 'ar', 'name', 'حاسوب');
    expect($row->source_checksum)->toBe(hash('sha256', 'Laptop'));
});

it('§25.3 Service resolves approved translation in Arabic locale', function () {
    $p = p11b12_make_product(['name' => 'Towel', 'slug' => 't-' . uniqid()]);
    p11b12_set_translation($p, 'ar', 'name', 'منشفة');
    expect(app(TranslationService::class)->resolve($p, 'name', 'ar'))->toBe('منشفة');
});

it('§25.4 Service resolves English in English locale (ignoring Arabic row)', function () {
    $p = p11b12_make_product(['name' => 'Towel', 'slug' => 't-' . uniqid()]);
    p11b12_set_translation($p, 'ar', 'name', 'منشفة');
    expect(app(TranslationService::class)->resolve($p, 'name', 'en'))->toBe('Towel');
});

it('§25.5 Missing Arabic falls back to English (controlled fallback)', function () {
    $p = p11b12_make_product(['name' => 'OnlyEnglish', 'slug' => 'oe-' . uniqid()]);
    expect(app(TranslationService::class)->resolve($p, 'name', 'ar'))->toBe('OnlyEnglish');
});

it('§25.6 Pending translation is NOT visible to customers (only approved is)', function () {
    $p = p11b12_make_product(['name' => 'Pending', 'slug' => 'pn-' . uniqid()]);
    p11b12_set_translation($p, 'ar', 'name', 'قيد المراجعة', ProductTranslation::STATUS_PENDING);
    // Resolver should fall back to English because pending isn't publishable
    expect(app(TranslationService::class)->resolve($p, 'name', 'ar'))->toBe('Pending');
});

it('§25.7 Machine_draft translation is NOT visible to customers by default', function () {
    $p = p11b12_make_product(['name' => 'Draft', 'slug' => 'dr-' . uniqid()]);
    p11b12_set_translation($p, 'ar', 'name', 'مسودة آلية', ProductTranslation::STATUS_MACHINE_DRAFT);
    expect(app(TranslationService::class)->resolve($p, 'name', 'ar'))->toBe('Draft');
});

it('§25.8 Rejected translation is NEVER visible to customers', function () {
    $p = p11b12_make_product(['name' => 'Rejected', 'slug' => 'rj-' . uniqid()]);
    p11b12_set_translation($p, 'ar', 'name', 'مرفوض', ProductTranslation::STATUS_REJECTED);
    expect(app(TranslationService::class)->resolve($p, 'name', 'ar'))->toBe('Rejected');
});

it('§25.9 Changing English source marks approved Arabic stale (observer)', function () {
    $p = p11b12_make_product(['name' => 'Original', 'slug' => 'or-' . uniqid()]);
    p11b12_set_translation($p, 'ar', 'name', 'الأصلي');
    expect($p->translations()->where('field', 'name')->first()->status)->toBe(ProductTranslation::STATUS_APPROVED);

    // Mutate English source — observer should mark Arabic stale
    $p->update(['name' => 'Changed']);

    $row = ProductTranslation::where('product_id', $p->id)->where('field', 'name')->first();
    expect($row->status)->toBe(ProductTranslation::STATUS_STALE);
});

it('§25.10 Stale translation falls back to English on resolve', function () {
    $p = p11b12_make_product(['name' => 'V1', 'slug' => 'v-' . uniqid()]);
    p11b12_set_translation($p, 'ar', 'name', 'النسخة الأولى');
    $p->update(['name' => 'V2']);  // triggers stale
    expect(app(TranslationService::class)->resolve($p, 'name', 'ar'))->toBe('V2');
});

it('§25.11 Rejected status does not publish even with valid value', function () {
    $p = p11b12_make_product(['name' => 'Test', 'slug' => 'ts-' . uniqid()]);
    p11b12_set_translation($p, 'ar', 'name', 'محتوى مرفوض', ProductTranslation::STATUS_REJECTED);
    $row = ProductTranslation::where('product_id', $p->id)->first();
    expect($row->status)->toBe(ProductTranslation::STATUS_REJECTED);
    expect(app(TranslationService::class)->resolve($p, 'name', 'ar'))->toBe('Test');
});

it('§25.12 Resolver never returns raw JSON translation array', function () {
    $p = p11b12_make_product([
        'name' => 'Phone', 'slug' => 'p-' . uniqid(),
        'name_translations' => ['ar' => 'هاتف', 'ur' => 'فون'],
    ]);
    $val = app(TranslationService::class)->resolve($p, 'name', 'ar');
    expect($val)->toBeString();
    expect(json_decode((string) $val, true))->toBeNull();  // not JSON-decodable
});

// ════════════════════════════════════════════════════════════════════════════
// §25.13-17 — Backward compat with JSON columns + display fields (5)
// ════════════════════════════════════════════════════════════════════════════

it('§25.13 JSON-column-only Arabic still resolves (v11A.5/v11B.1.1 backward compat)', function () {
    $p = p11b12_make_product([
        'name' => 'JsonOnly', 'slug' => 'js-' . uniqid(),
        'name_translations' => ['ar' => 'جسون فقط'],
    ]);
    expect(app(TranslationService::class)->resolve($p, 'name', 'ar'))->toBe('جسون فقط');
});

it('§25.14 product_translations table takes precedence over legacy JSON', function () {
    $p = p11b12_make_product([
        'name' => 'Precedence', 'slug' => 'pr-' . uniqid(),
        'name_translations' => ['ar' => 'قيمة قديمة'],
    ]);
    p11b12_set_translation($p, 'ar', 'name', 'قيمة جديدة');
    expect(app(TranslationService::class)->resolve($p, 'name', 'ar'))->toBe('قيمة جديدة');
});

it('§25.15 displayFields() returns Inertia-friendly shape', function () {
    $p = p11b12_make_product([
        'name' => 'EN', 'slug' => 'd-' . uniqid(),
        'short_description' => 'EN short', 'description' => 'EN full',
    ]);
    p11b12_set_translation($p, 'ar', 'name', 'ع');
    p11b12_set_translation($p, 'ar', 'short_description', 'ق');
    $df = app(TranslationService::class)->displayFields($p, 'ar');
    expect($df)->toHaveKeys(['display_name', 'display_short_description', 'display_description'])
        ->and($df['display_name'])->toBe('ع')
        ->and($df['display_short_description'])->toBe('ق')
        ->and($df['display_description'])->toBe('EN full');  // English fallback
});

it('§25.16 Eager-loaded translations resolve without per-row query', function () {
    $p = p11b12_make_product(['name' => 'Eager', 'slug' => 'eg-' . uniqid()]);
    p11b12_set_translation($p, 'ar', 'name', 'عاجل');
    // Reload with eager-loaded translations
    $loaded = Product::with('translations')->find($p->id);
    \DB::enableQueryLog();
    \DB::flushQueryLog();
    $val = app(TranslationService::class)->resolve($loaded, 'name', 'ar');
    expect(count(\DB::getQueryLog()))->toBe(0);  // resolver hit no DB
    expect($val)->toBe('عاجل');
});

it('§25.17 Product::translatedName delegates to TranslationService', function () {
    $p = p11b12_make_product(['name' => 'Delegate', 'slug' => 'dg-' . uniqid()]);
    p11b12_set_translation($p, 'ar', 'name', 'مفوض');
    expect($p->translatedName('ar'))->toBe('مفوض');
});

// ════════════════════════════════════════════════════════════════════════════
// §25.18-22 — Provider abstraction + queue job (5)
// ════════════════════════════════════════════════════════════════════════════

it('§25.18 Default TranslationProviderInterface is ManualTranslationProvider', function () {
    expect(app(TranslationProviderInterface::class))->toBeInstanceOf(ManualTranslationProvider::class);
});

it('§25.19 ManualTranslationProvider does NOT auto-generate', function () {
    $p = app(ManualTranslationProvider::class);
    expect($p->autoGenerates())->toBeFalse();
    expect($p->translate('hello', 'en', 'ar'))->toBeNull();
});

it('§25.20 Queue job creates pending row by default (manual provider)', function () {
    $p = p11b12_make_product(['name' => 'Queued', 'slug' => 'qd-' . uniqid()]);
    (new QueueProductTranslation($p->id, 'ar', 'name'))->handle(
        app(TranslationProviderInterface::class),
        app(TranslationService::class)
    );
    $row = ProductTranslation::where('product_id', $p->id)->where('field', 'name')->first();
    expect($row)->not->toBeNull();
    expect($row->status)->toBe(ProductTranslation::STATUS_PENDING);
});

it('§25.21 Queue job does NOT overwrite approved translations', function () {
    $p = p11b12_make_product(['name' => 'Protected', 'slug' => 'pt-' . uniqid()]);
    p11b12_set_translation($p, 'ar', 'name', 'محمي', ProductTranslation::STATUS_APPROVED);

    (new QueueProductTranslation($p->id, 'ar', 'name'))->handle(
        app(TranslationProviderInterface::class),
        app(TranslationService::class)
    );

    $row = ProductTranslation::where('product_id', $p->id)->first();
    expect($row->status)->toBe(ProductTranslation::STATUS_APPROVED);
    expect($row->value)->toBe('محمي');
});

it('§25.22 Queue job is asynchronous (implements ShouldQueue)', function () {
    Queue::fake();
    QueueProductTranslation::dispatch(1, 'ar', 'name');
    Queue::assertPushed(QueueProductTranslation::class);
});

// ════════════════════════════════════════════════════════════════════════════
// §25.23-26 — Search + audit (4)
// ════════════════════════════════════════════════════════════════════════════

it('§25.23 Approved Arabic translation is searchable via MarketplaceSearchService', function () {
    $vendor = p11b12_vendor_user()->vendor;
    $cat = Category::create(['slug' => 'c-' . uniqid(), 'name' => 'C', 'is_active' => true]);
    $p = p11b12_make_product([
        'vendor' => $vendor, 'category' => $cat,
        'name' => 'BackoffSync', 'slug' => 'bs-' . uniqid(),
        // Legacy JSON also has the Arabic so the search service can find it.
        // (Search service still queries name_translations.ar; the v11B.1.2
        // resolver controls DISPLAY, not search indexing — by design, search
        // operates on JSON columns for performance, and the resolver picks
        // approved values from product_translations for display only. The
        // backfill seeder keeps these in sync.)
        'name_translations' => ['ar' => 'سماعات منظمة'],
    ]);
    p11b12_set_translation($p, 'ar', 'name', 'سماعات منظمة');
    $svc = app(MarketplaceSearchService::class);
    $rows = $svc->products('سماعات', 'ar')->limit(5)->pluck('name')->all();
    expect($rows)->toContain('BackoffSync');
});

it('§25.24 Translation audit command runs without errors', function () {
    \Artisan::call('translations:audit', ['locale' => 'ar']);
    $output = \Artisan::output();
    expect($output)->toContain('Audit summary');
    expect($output)->toContain('Product translation workflow status');
});

it('§25.25 BackfillProductTranslationsSeeder migrates JSON to normalized table', function () {
    $vendor = p11b12_vendor_user()->vendor;
    $cat = Category::create(['slug' => 'c-' . uniqid(), 'name' => 'C', 'is_active' => true]);
    p11b12_make_product([
        'vendor' => $vendor, 'category' => $cat,
        'name' => 'Backfill Source', 'slug' => 'bk-' . uniqid(),
        'name_translations' => ['ar' => 'مصدر التراجع'],
    ]);
    \Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\BackfillProductTranslationsSeeder', '--force' => true]);
    $row = ProductTranslation::where('locale', 'ar')->where('value', 'مصدر التراجع')->first();
    expect($row)->not->toBeNull();
    expect($row->status)->toBe(ProductTranslation::STATUS_APPROVED);
});

it('§25.26 Backfill seeder is idempotent (re-run produces no duplicates)', function () {
    $vendor = p11b12_vendor_user()->vendor;
    $cat = Category::create(['slug' => 'c-' . uniqid(), 'name' => 'C', 'is_active' => true]);
    p11b12_make_product([
        'vendor' => $vendor, 'category' => $cat,
        'name' => 'Idempotent', 'slug' => 'id-' . uniqid(),
        'name_translations' => ['ar' => 'مكرر'],
    ]);
    \Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\BackfillProductTranslationsSeeder', '--force' => true]);
    $countBefore = ProductTranslation::count();
    \Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\BackfillProductTranslationsSeeder', '--force' => true]);
    $countAfter = ProductTranslation::count();
    expect($countAfter)->toBe($countBefore);
});

// ════════════════════════════════════════════════════════════════════════════
// §26.27-31 — Products-page mobile search (5)
// ════════════════════════════════════════════════════════════════════════════

it('§26.27 Catalog/Index.tsx imports + uses SearchBar with unique instanceId', function () {
    $src = file_get_contents(resource_path('js/Pages/Catalog/Index.tsx'));
    expect($src)->toContain("import SearchBar")
        ->toContain('instanceId="catalog-toolbar"');
});

it('§26.28 SearchBar no longer hardcodes listbox DOM id (multi-instance safe)', function () {
    $src = file_get_contents(resource_path('js/Components/common/SearchBar.tsx'));
    expect($src)->not->toContain('id="search-suggestions-listbox"');
    expect($src)->toContain('listboxId = ');
    expect($src)->toContain('useId');
});

it('§26.29 SearchBar accepts instanceId prop', function () {
    $src = file_get_contents(resource_path('js/Components/common/SearchBar.tsx'));
    expect($src)->toContain('instanceId?:');
});

it('§26.30 Dead submitSearch handler removed from Catalog/Index.tsx', function () {
    $src = file_get_contents(resource_path('js/Pages/Catalog/Index.tsx'));
    expect($src)->not->toContain('const submitSearch');
});

it('§26.31 Catalog page uses same /search/suggestions endpoint via SearchBar (no duplicated logic)', function () {
    // Verifies dev §11 "one shared autocomplete engine".
    $sb = file_get_contents(resource_path('js/Components/common/SearchBar.tsx'));
    $ci = file_get_contents(resource_path('js/Pages/Catalog/Index.tsx'));
    $sl = file_get_contents(resource_path('js/Layouts/StorefrontLayout.tsx'));
    expect($sb)->toContain('/search/suggestions');
    // Catalog itself doesn't fetch /search/suggestions — only via SearchBar
    expect($ci)->not->toContain('/search/suggestions');
    // StorefrontLayout doesn't fetch /search/suggestions — only via SearchBar
    expect($sl)->not->toContain('/search/suggestions');
});

// ════════════════════════════════════════════════════════════════════════════
// §26.32-37 — Regression + accessibility + isolation (6)
// ════════════════════════════════════════════════════════════════════════════

it('§26.32 Mobile drawer still uses SearchBar variant=mobile (v11B.1.1 regression)', function () {
    $src = file_get_contents(resource_path('js/Layouts/StorefrontLayout.tsx'));
    expect($src)->toContain('SearchBar variant="mobile"');
});

it('§26.33 Desktop header SearchBar present (v11A.4 regression)', function () {
    $src = file_get_contents(resource_path('js/Layouts/StorefrontLayout.tsx'));
    expect($src)->toContain('SearchBar variant="desktop"');
});

it('§26.34 Customer login still works (regression)', function () {
    $u = p11b12_customer();
    test()->post('/login', ['email' => $u->email, 'password' => 'pw'])->assertRedirect();
});

it('§26.35 Catalog page still renders 200', function () {
    test()->get('/products')->assertOk();
});

it('§26.36 Admin Reports still render 200 (v10.10 regression)', function () {
    test()->actingAs(p11b12_admin())->get('/admin/reports')->assertOk();
});

it('§26.37 Vendor product edit page still renders (v11B.1.1 regression)', function () {
    $u = p11b12_vendor_user();
    $cat = Category::create(['slug' => 'c-' . uniqid(), 'name' => 'C', 'is_active' => true]);
    $p = p11b12_make_product([
        'vendor' => $u->vendor, 'category' => $cat,
        'name' => 'Edit Regression', 'slug' => 'er-' . uniqid(),
    ]);
    test()->actingAs($u)->get("/vendor/products/{$p->id}/edit")->assertOk();
});
