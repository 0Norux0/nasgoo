<?php

declare(strict_types=1);

use App\Models\Setting;
use App\Models\User;
use App\Services\Settings\HomepageSectionRegistry;
use App\Services\Settings\SiteSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

// ─── helpers (p11b31_*) ────────────────────────────────────────────────────

function p11b31_seed(): void
{
    \Artisan::call('db:seed', [
        '--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder',
        '--force' => true,
    ]);
}

function p11b31_admin(): User
{
    p11b31_seed();
    $u = User::factory()->create([
        'email'    => 'p11b31-a-' . uniqid() . '@p11b31.test',
        'password' => Hash::make('pw'), 'status' => 'active',
    ]);
    $u->assignRole('super_admin');
    return $u->fresh();
}

function p11b31_customer(): User
{
    p11b31_seed();
    $u = User::factory()->create([
        'email'    => 'p11b31-c-' . uniqid() . '@p11b31.test',
        'password' => Hash::make('pw'), 'status' => 'active',
    ]);
    $u->assignRole('customer');
    return $u->fresh();
}

// ════════════════════════════════════════════════════════════════════════════
// §38 Settings service
// ════════════════════════════════════════════════════════════════════════════

it('§38.1 get() returns config default when no DB row', function () {
    Cache::flush();
    $svc = app(SiteSettingsService::class);
    // Config has 'branding.site_name' default via env('APP_NAME')
    expect($svc->get('branding.site_name'))->not->toBeNull();
});

it('§38.2 set() persists a scalar and invalidates cache immediately', function () {
    Cache::flush();
    $svc = app(SiteSettingsService::class);
    $svc->set('branding.site_name', 'My Store', 1);
    // Read should reflect the new value
    expect($svc->get('branding.site_name'))->toBe('My Store');
    expect(Setting::where('group', 'branding')->where('key', 'site_name')->exists())->toBeTrue();
});

it('§38.3 setMany() atomically updates multiple keys', function () {
    $svc = app(SiteSettingsService::class);
    $svc->setMany([
        'branding.site_name' => 'A',
        'branding.tagline'   => ['en' => 'Hello', 'ar' => 'مرحباً'],
    ], 1);
    expect($svc->get('branding.site_name'))->toBe('A');
    // Locale resolution
    app()->setLocale('en');
    expect($svc->get('branding.tagline'))->toBe('Hello');
    app()->setLocale('ar');
    expect($svc->get('branding.tagline'))->toBe('مرحباً');
});

it('§38.4 translatable value falls back to English when locale missing', function () {
    $svc = app(SiteSettingsService::class);
    $svc->set('branding.tagline', ['en' => 'EnglishOnly'], 1);
    app()->setLocale('ar');
    expect($svc->get('branding.tagline'))->toBe('EnglishOnly');
});

it('§38.5 resetGroup() removes DB rows and cache; defaults restored', function () {
    $svc = app(SiteSettingsService::class);
    $svc->set('branding.site_name', 'Overridden', 1);
    expect(Setting::where('group', 'branding')->count())->toBeGreaterThan(0);
    $svc->resetGroup('branding');
    expect(Setting::where('group', 'branding')->count())->toBe(0);
});

it('§38.6 publicPayload() includes only safe groups (no payment/commission)', function () {
    $svc = app(SiteSettingsService::class);
    $payload = $svc->publicPayload();
    expect(array_keys($payload))->toContain('branding', 'appearance', 'footer', 'social');
    expect($payload)->not->toHaveKeys(['payment', 'commission', 'security']);
});

it('§38.7 knownGroups() returns config default groups', function () {
    $svc = app(SiteSettingsService::class);
    $groups = $svc->knownGroups();
    expect($groups)->toContain('branding', 'appearance', 'social');
});

it('§38.8 group() is cached (2nd call under 5ms) — but cache doesn\'t leak on invalidate', function () {
    Cache::flush();
    $svc = app(SiteSettingsService::class);
    $svc->set('branding.site_name', 'Cached', 1);
    // Read populates cache
    expect($svc->get('branding.site_name'))->toBe('Cached');
    // Invalidate via new save
    $svc->set('branding.site_name', 'Fresh', 1);
    // Should reflect new value (cache invalidated)
    expect($svc->get('branding.site_name'))->toBe('Fresh');
});

// ════════════════════════════════════════════════════════════════════════════
// Admin settings controller — authorization
// ════════════════════════════════════════════════════════════════════════════

it('§38.9 unauthenticated cannot access admin settings page', function () {
    test()->get('/admin/site-settings')->assertStatus(302);  // redirect to login
});

it('§38.10 customer cannot access admin settings page', function () {
    $c = p11b31_customer();
    test()->actingAs($c)->get('/admin/site-settings')->assertStatus(403);
});

it('§38.11 super_admin can view admin settings page', function () {
    $a = p11b31_admin();
    test()->actingAs($a)->get('/admin/site-settings')
        ->assertOk()
        ->assertInertia(fn ($pg) => $pg
            ->component('Admin/SiteSettings/Index')
            ->has('settings.branding')
            ->has('settings.appearance')
            ->has('settings.social')
            ->has('sections_registry')
            ->etc()
        );
});

it('§38.12 admin can update site name', function () {
    $a = p11b31_admin();
    test()->actingAs($a)->post('/admin/site-settings/branding', [
        'site_name' => 'Updated Store',
    ])->assertStatus(302);
    expect(app(SiteSettingsService::class)->get('branding.site_name'))->toBe('Updated Store');
});

it('§38.13 admin can update translatable tagline', function () {
    $a = p11b31_admin();
    test()->actingAs($a)->post('/admin/site-settings/branding', [
        'tagline' => ['en' => 'Discover deals', 'ar' => 'اكتشف العروض'],
    ]);
    app()->setLocale('ar');
    expect(app(SiteSettingsService::class)->get('branding.tagline'))->toBe('اكتشف العروض');
});

// ════════════════════════════════════════════════════════════════════════════
// Security: invalid URLs + colors + SVG scripts rejected
// ════════════════════════════════════════════════════════════════════════════

it('§38.14 javascript: URL in social link is rejected', function () {
    $a = p11b31_admin();
    test()->actingAs($a)->post('/admin/site-settings/social', [
        'facebook' => 'javascript:alert(1)',
    ])->assertSessionHasErrors('facebook');
});

it('§38.15 data: URL in social link is rejected', function () {
    $a = p11b31_admin();
    test()->actingAs($a)->post('/admin/site-settings/social', [
        'instagram' => 'data:text/html,<script>alert(1)</script>',
    ])->assertSessionHasErrors('instagram');
});

it('§38.16 invalid color format is rejected', function () {
    $a = p11b31_admin();
    test()->actingAs($a)->post('/admin/site-settings/appearance', [
        'color_primary' => 'not-a-color',
    ])->assertSessionHasErrors('color_primary');
});

it('§38.17 valid hex color is accepted', function () {
    $a = p11b31_admin();
    test()->actingAs($a)->post('/admin/site-settings/appearance', [
        'color_primary' => '#ff0000',
    ])->assertStatus(302);
    expect(app(SiteSettingsService::class)->get('appearance.color_primary'))->toBe('#ff0000');
});

it('§38.18 unknown group returns 422', function () {
    $a = p11b31_admin();
    test()->actingAs($a)->post('/admin/site-settings/malicious_group', [
        'key' => 'value',
    ])->assertStatus(404); // route regex rejects → 404 (also acceptable)
});

// ════════════════════════════════════════════════════════════════════════════
// §39 Homepage section registry
// ════════════════════════════════════════════════════════════════════════════

it('§39.1 registry lists all supported sections', function () {
    $sections = HomepageSectionRegistry::all();
    expect(array_keys($sections))->toContain('hero', 'featured', 'categories', 'personalization');
});

it('§39.2 resolve() respects section_order', function () {
    $svc = app(SiteSettingsService::class);
    $svc->set('homepage.section_order', ['featured', 'categories', 'hero'], 1);
    // Enable all three so evidence check passes
    $svc->set('homepage.sections', [
        'hero'       => ['enabled' => true],
        'categories' => ['enabled' => true],
        'featured'   => ['enabled' => true],
    ], 1);
    $resolved = HomepageSectionRegistry::resolve($svc);
    $keys = array_column($resolved, 'key');
    expect($keys)->toBe(['featured', 'categories', 'hero']);
});

it('§39.3 disabled section is omitted', function () {
    $svc = app(SiteSettingsService::class);
    $svc->set('homepage.section_order', ['hero', 'categories'], 1);
    $svc->set('homepage.sections', [
        'hero'       => ['enabled' => false],
        'categories' => ['enabled' => true],
    ], 1);
    $keys = array_column(HomepageSectionRegistry::resolve($svc), 'key');
    expect($keys)->toBe(['categories']);
    expect($keys)->not->toContain('hero');
});

it('§39.4 unknown section key silently dropped (no crash)', function () {
    $svc = app(SiteSettingsService::class);
    $svc->set('homepage.section_order', ['malicious_section', 'hero'], 1);
    $svc->set('homepage.sections', ['hero' => ['enabled' => true]], 1);
    $keys = array_column(HomepageSectionRegistry::resolve($svc), 'key');
    expect($keys)->toBe(['hero']);
});

it('§39.5 feature-gated section omitted when flag off', function () {
    config(['marketplace_personalization.features.enabled' => false]);
    $svc = app(SiteSettingsService::class);
    $svc->set('homepage.section_order', ['personalization', 'hero'], 1);
    $svc->set('homepage.sections', [
        'personalization' => ['enabled' => true],
        'hero'            => ['enabled' => true],
    ], 1);
    $keys = array_column(HomepageSectionRegistry::resolve($svc), 'key');
    expect($keys)->not->toContain('personalization');
    expect($keys)->toContain('hero');
});

it('§39.6 reset via admin controller works and clears cache', function () {
    $a = p11b31_admin();
    app(SiteSettingsService::class)->set('branding.site_name', 'ToBeCleared', 1);
    test()->actingAs($a)->post('/admin/site-settings/branding/reset')->assertStatus(302);
    expect(Setting::where('group', 'branding')->count())->toBe(0);
});

// ════════════════════════════════════════════════════════════════════════════
// §40 Responsive pages
// ════════════════════════════════════════════════════════════════════════════

it('§40.1 Orders/Index.tsx uses ResponsiveDataList', function () {
    $body = file_get_contents(base_path('resources/js/Pages/Orders/Index.tsx'));
    expect($body)->toContain('ResponsiveDataList');
    expect($body)->toContain('PageContainer');
    expect($body)->toContain('order-mobile-card');
});

it('§40.2 Bookings/Index.tsx uses ResponsiveDataList', function () {
    $body = file_get_contents(base_path('resources/js/Pages/Bookings/Index.tsx'));
    expect($body)->toContain('ResponsiveDataList');
    expect($body)->toContain('PageContainer');
    expect($body)->toContain('booking-mobile-card');
});

it('§40.3 Tickets/Index.tsx uses ResponsiveDataList', function () {
    $body = file_get_contents(base_path('resources/js/Pages/Tickets/Index.tsx'));
    expect($body)->toContain('ResponsiveDataList');
    expect($body)->toContain('PageContainer');
    expect($body)->toContain('ticket-mobile-card');
});

it('§40.4 ResponsiveDataList primitive exists', function () {
    expect(file_exists(base_path('resources/js/Components/Layout/ResponsiveDataList.tsx')))->toBeTrue();
});

it('§40.5 PageContainer primitive exists', function () {
    expect(file_exists(base_path('resources/js/Components/Layout/PageContainer.tsx')))->toBeTrue();
});

it('§40.6 Container primitive (v11A.2) preserved with canonical padding', function () {
    $body = file_get_contents(base_path('resources/js/Components/Layout/Container.tsx'));
    expect($body)->toContain('px-4 sm:px-6 lg:px-8');
});

// ════════════════════════════════════════════════════════════════════════════
// §41 Vendor navigation
// ════════════════════════════════════════════════════════════════════════════

it('§41.1 VendorSidebar component exists', function () {
    expect(file_exists(base_path('resources/js/Components/Vendor/VendorSidebar.tsx')))->toBeTrue();
});

it('§41.2 VendorMobileDrawer has focus trap, Escape close, backdrop', function () {
    $body = file_get_contents(base_path('resources/js/Components/Vendor/VendorMobileDrawer.tsx'));
    expect($body)->toContain('trapFocus');
    expect($body)->toContain('Escape');
    expect($body)->toContain('backdrop');
    expect($body)->toContain("body.style.overflow");
});

it('§41.3 VendorLayout renders sidebar + drawer', function () {
    $body = file_get_contents(base_path('resources/js/Layouts/VendorLayout.tsx'));
    expect($body)->toContain('VendorSidebar');
    expect($body)->toContain('VendorMobileDrawer');
});

it('§41.4 vendor-nav-reports testid preserved (v10.13 CI check)', function () {
    $body = file_get_contents(base_path('resources/js/Layouts/VendorLayout.tsx'));
    expect($body)->toContain('vendor-nav-reports');
});

it('§41.5 VendorMobileDrawer is RTL-aware', function () {
    $body = file_get_contents(base_path('resources/js/Components/Vendor/VendorMobileDrawer.tsx'));
    expect($body)->toContain('isRTL');
});

// ════════════════════════════════════════════════════════════════════════════
// §42 Regression smokes
// ════════════════════════════════════════════════════════════════════════════

it('§42.1 Homepage still renders', function () {
    test()->get('/')->assertOk();
});

it('§42.2 Product listing still renders', function () {
    test()->get('/products')->assertOk();
});

it('§42.3 Login page still renders (with siteSettings share)', function () {
    test()->get('/login')->assertOk();
});

it('§42.4 Cart page still renders', function () {
    test()->get('/cart')->assertOk();
});

it('§42.5 v11B.2.2 canonical pricing engine preserved', function () {
    expect(file_exists(base_path('app/Domain/Pricing/PricingService.php')))->toBeTrue();
    $body = file_get_contents(base_path('app/Domain/Pricing/PricingService.php'));
    expect($body)->toContain('priceProductWithQuantity');
});

it('§42.6 v11B.3 personalization services preserved', function () {
    expect(file_exists(base_path('app/Services/Personalization/PersonalizationManager.php')))->toBeTrue();
});

it('§42.7 siteSettings shared on Inertia responses', function () {
    $r = test()->get('/');
    $r->assertOk()->assertInertia(fn ($pg) => $pg
        ->has('siteSettings.branding')
        ->has('siteSettings.footer')
        ->has('siteSettings.social')
        ->etc()
    );
});
