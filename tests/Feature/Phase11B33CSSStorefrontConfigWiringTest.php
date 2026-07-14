<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Settings\SiteSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

// ─── helpers (p11b33_*) ────────────────────────────────────────────────────

function p11b33_seed(): void
{
    \Artisan::call('db:seed', [
        '--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder',
        '--force' => true,
    ]);
}

function p11b33_super_admin(): User
{
    p11b33_seed();
    $u = User::factory()->create([
        'email' => 'p11b33-a-' . uniqid() . '@p11b33.test',
        'password' => Hash::make('pw'), 'status' => 'active',
    ]);
    $u->assignRole('super_admin');
    return $u->fresh();
}

// ════════════════════════════════════════════════════════════════════════════
// §3 §4 §23 CSS ROOT-CAUSE FIX — letter-by-letter wrap eliminated globally
// ════════════════════════════════════════════════════════════════════════════

it('§3.1 CSS: global overflow-wrap:anywhere on span/a/td/th is REMOVED', function () {
    $css = file_get_contents(base_path('resources/css/app.css'));
    // The bad ACTIVE rule was `p, span, a, td, th, li { overflow-wrap: anywhere; ... }`
    // Look for the active rule pattern (selector followed by { and the property).
    // The explanatory comment mentions the old rule — that's fine; we look for
    // the actual CSS declaration outside comment lines.
    $lines = explode("\n", $css);
    $activeRuleFound = false;
    foreach ($lines as $line) {
        $trimmed = ltrim($line);
        // Skip comment lines
        if (str_starts_with($trimmed, '*') || str_starts_with($trimmed, '/*')) continue;
        // Look for the old selector as an active rule
        if (str_contains($line, ', span, a, td, th, li {')
         || preg_match('/^\s*p\s*,\s*span\s*,\s*a\s*,\s*td\s*,\s*th\s*,\s*li\s*\{/', $line)) {
            $activeRuleFound = true;
            break;
        }
    }
    expect($activeRuleFound)->toBeFalse(
        'The overly aggressive global overflow-wrap rule is still active in app.css'
    );
});

it('§3.2 CSS: safer scoped wrap rule (p, li) is present', function () {
    $css = file_get_contents(base_path('resources/css/app.css'));
    expect($css)->toContain('overflow-wrap: break-word',
        'The v11B.3.3 scoped safer rule is missing');
    expect($css)->toContain('word-break: normal',
        'The v11B.3.3 word-break: normal reset is missing');
});

it('§3.3 CSS: .break-anywhere opt-in utility class exists', function () {
    $css = file_get_contents(base_path('resources/css/app.css'));
    expect($css)->toContain('.break-anywhere',
        'The .break-anywhere opt-in utility for long URLs/hashes is missing');
});

// ════════════════════════════════════════════════════════════════════════════
// §5 §22 CONTAINER APPLIED to product/cart/checkout — actually wired
// ════════════════════════════════════════════════════════════════════════════

it('§5.1 Cart/Show uses Container (not inline max-w-5xl)', function () {
    $body = file_get_contents(base_path('resources/js/Pages/Cart/Show.tsx'));
    expect($body)->toContain("import Container from '@/Components/Layout/Container'");
    expect($body)->toContain('<Container');
    // Old inline wrapper must be gone
    expect($body)->not->toContain('<div className="max-w-5xl mx-auto">',
        'Cart still has the pre-v11B.3.3 inline wrapper');
});

it('§5.2 Checkout/Show uses Container (not inline max-w-6xl)', function () {
    $body = file_get_contents(base_path('resources/js/Pages/Checkout/Show.tsx'));
    expect($body)->toContain("import Container from '@/Components/Layout/Container'");
    expect($body)->toContain('<Container');
    expect($body)->not->toContain('max-w-6xl mx-auto',
        'Checkout still has the pre-v11B.3.3 inline wrapper');
});

it('§5.3 Catalog/Show (product detail) uses Container', function () {
    $body = file_get_contents(base_path('resources/js/Pages/Catalog/Show.tsx'));
    expect($body)->toContain("import Container from '@/Components/Layout/Container'");
    expect($body)->toContain('<Container');
});

// ════════════════════════════════════════════════════════════════════════════
// §6 VENDOR ORDER — "Update status" wrap FIXED
// ════════════════════════════════════════════════════════════════════════════

it('§6.1 Vendor Order Show: "Update status" label has whitespace-nowrap', function () {
    $body = file_get_contents(base_path('resources/js/Pages/Vendor/Orders/Show.tsx'));
    // Look for the label span with whitespace-nowrap class
    expect($body)->toMatch(
        '/text-slate-500\s+whitespace-nowrap.*Update status:/su',
        'The "Update status:" label is not protected against letter-by-letter wrap'
    );
    expect($body)->toContain('vendor-order-update-status-label',
        'testid for the label span missing (needed for future regression tests)');
});

// ════════════════════════════════════════════════════════════════════════════
// §7 STOREFRONT LAYOUT actually consumes siteSettings.branding
// ════════════════════════════════════════════════════════════════════════════

it('§7.1 StorefrontLayout imports and reads siteSettings from usePage', function () {
    $body = file_get_contents(base_path('resources/js/Layouts/StorefrontLayout.tsx'));
    expect($body)->toContain('siteSettings',
        'StorefrontLayout does not destructure siteSettings from usePage props');
    expect($body)->toContain('brand.site_name',
        'StorefrontLayout does not read branding.site_name');
});

it('§7.2 Storefront brand mark uses siteSettings.branding.site_name in header', function () {
    // Set a custom site name via SiteSettingsService, then render homepage
    // and confirm the DOM contains that custom name.
    $svc = app(SiteSettingsService::class);
    $svc->set('branding.site_name', 'MyCustomStoreV11B33', 1);

    $response = test()->get('/')->assertOk();
    $content = $response->getContent();

    expect($content)->toContain('MyCustomStoreV11B33',
        'Custom site name not rendered on / — siteSettings not wired to StorefrontLayout');
});

it('§7.3 Storefront brand logo uses siteSettings.branding.logo_url when set', function () {
    $svc = app(SiteSettingsService::class);
    $svc->set('branding.logo_url', '/images/my-custom-logo-v11b33.svg', 1);
    $svc->set('branding.site_name', 'X', 1);   // ensure name is set too

    $response = test()->get('/')->assertOk();
    $content = $response->getContent();

    expect($content)->toContain('my-custom-logo-v11b33.svg',
        'Custom logo URL not rendered — StorefrontLayout not using siteSettings.branding.logo_url');
});

it('§7.4 Footer copyright respects siteSettings.footer.copyright', function () {
    $svc = app(SiteSettingsService::class);
    $svc->set('footer.copyright', 'CustomCopyright2026', 1);

    $response = test()->get('/')->assertOk();
    expect($response->getContent())->toContain('CustomCopyright2026',
        'Custom copyright not rendered in footer');
});

it('§7.5 Footer social icons render when siteSettings.social.* is set', function () {
    $svc = app(SiteSettingsService::class);
    $svc->set('social.facebook', 'https://facebook.com/myshop-v11b33', 1);

    $response = test()->get('/')->assertOk();
    $content = $response->getContent();

    expect($content)->toContain('facebook.com/myshop-v11b33',
        'Facebook URL not rendered — storefront-footer-social missing');
});

// ════════════════════════════════════════════════════════════════════════════
// §8 HOMEPAGE SECTIONS respect admin toggle from siteSettings.homepage
// ════════════════════════════════════════════════════════════════════════════

it('§8.1 Welcome imports isSectionEnabled helper reading siteSettings.homepage.sections', function () {
    $body = file_get_contents(base_path('resources/js/Pages/Welcome.tsx'));
    expect($body)->toContain('isSectionEnabled',
        'Welcome does not have a helper to check per-section enabled');
    expect($body)->toContain('siteSettings?.homepage?.sections',
        'Welcome does not read siteSettings.homepage.sections');
});

it('§8.2 Featured Categories section wrapped in isSectionEnabled check', function () {
    $body = file_get_contents(base_path('resources/js/Pages/Welcome.tsx'));
    expect($body)->toMatch(
        '/isSectionEnabled\(.categories.\).*top_categories\.length > 0/s',
        'Categories section not wrapped in admin enable toggle'
    );
});

it('§8.3 Featured Products section respects toggle', function () {
    $body = file_get_contents(base_path('resources/js/Pages/Welcome.tsx'));
    expect($body)->toContain("isSectionEnabled('featured')",
        'Featured Products section not gated by admin toggle');
});

it('§8.4 Services section respects toggle', function () {
    $body = file_get_contents(base_path('resources/js/Pages/Welcome.tsx'));
    expect($body)->toContain("isSectionEnabled('services')",
        'Services section not gated by admin toggle');
});

// ════════════════════════════════════════════════════════════════════════════
// §12 CSS CUSTOM PROPERTIES injected server-side from appearance settings
// ════════════════════════════════════════════════════════════════════════════

it('§12.1 Blade layout has appearance CSS var injection block', function () {
    $blade = file_get_contents(base_path('resources/views/app.blade.php'));
    expect($blade)->toContain('SiteSettingsService',
        'Blade layout does not call SiteSettingsService for appearance');
    expect($blade)->toContain('v11b33-appearance-vars',
        'Blade layout does not have the appearance-vars <style> block');
});

it('§12.2 Custom color from admin appears as CSS variable in rendered HTML', function () {
    $svc = app(SiteSettingsService::class);
    $svc->set('appearance.color_primary', '#abcdef', 1);

    $response = test()->get('/')->assertOk();
    $content = $response->getContent();

    expect($content)->toContain('--color-primary: #abcdef',
        'appearance.color_primary not injected as CSS var');
});

it('§12.3 Non-hex color value is rejected by regex — not injected', function () {
    // Admin can't save a non-hex due to controller validation,
    // but this test verifies the Blade also filters (defense in depth)
    $svc = app(SiteSettingsService::class);
    // Directly force a bad value into the DB to simulate compromise
    \App\Models\Setting::updateOrCreate(
        ['group' => 'appearance', 'key' => 'color_primary'],
        ['value' => ['v' => 'javascript:alert(1)'], 'type' => 'string']
    );
    // Bust cache
    $svc->flushAll();

    $response = test()->get('/')->assertOk();
    expect($response->getContent())->not->toContain('javascript:alert(1)',
        'Non-hex value must be filtered out of CSS var injection');
});

it('§12.4 browser_theme_color emits <meta name="theme-color">', function () {
    $svc = app(SiteSettingsService::class);
    $svc->set('appearance.browser_theme_color', '#123456', 1);

    $response = test()->get('/')->assertOk();
    expect($response->getContent())->toContain('name="theme-color" content="#123456"',
        'browser_theme_color not emitted as meta tag');
});

// ════════════════════════════════════════════════════════════════════════════
// §11 SOCIAL LINKS validated + rendered
// ════════════════════════════════════════════════════════════════════════════

it('§11.1 Admin rejects javascript: URL in social link', function () {
    $a = p11b33_super_admin();
    test()->actingAs($a)->post('/admin/site-settings/social', [
        'facebook' => 'javascript:alert(1)',
    ])->assertSessionHasErrors('facebook');
});

it('§11.2 Valid social URL saves + renders', function () {
    $svc = app(SiteSettingsService::class);
    $svc->set('social.instagram', 'https://instagram.com/verified-shop', 1);

    $response = test()->get('/')->assertOk();
    expect($response->getContent())->toContain('instagram.com/verified-shop');
});

// ════════════════════════════════════════════════════════════════════════════
// §26 REGRESSION — everything from v11B.3.2 and earlier still intact
// ════════════════════════════════════════════════════════════════════════════

it('§26.1 Homepage renders 200', function () {
    test()->get('/')->assertOk();
});

it('§26.2 Product listing renders 200', function () {
    test()->get('/products')->assertOk();
});

it('§26.3 Cart page renders 200 (Container wrap must not break render)', function () {
    test()->get('/cart')->assertOk();
});

it('§26.4 Login renders 200 (siteSettings share must not break auth pages)', function () {
    test()->get('/login')->assertOk();
});

it('§26.5 v11B.3.2 vendor Settings route preserved', function () {
    $exists = collect(app('router')->getRoutes())->contains(
        fn ($r) => $r->getName() === 'vendor.settings.edit'
    );
    expect($exists)->toBeTrue('v11B.3.2 vendor.settings.edit route lost');
});

it('§26.6 v11B.3.2 StatsOverview cache preserved', function () {
    $body = file_get_contents(base_path('app/Filament/Widgets/StatsOverview.php'));
    expect($body)->toContain('Cache::remember');
    expect($body)->toContain('COUNT(CASE WHEN');
});

it('§26.7 v11B.3.1 SiteSettingsService preserved', function () {
    expect(file_exists(base_path('app/Services/Settings/SiteSettingsService.php')))->toBeTrue();
});

it('§26.8 v11B.3.1 responsive Orders/Bookings/Tickets preserved', function () {
    foreach (['Orders/Index.tsx', 'Bookings/Index.tsx', 'Tickets/Index.tsx'] as $p) {
        $body = file_get_contents(base_path("resources/js/Pages/{$p}"));
        expect($body)->toContain('ResponsiveDataList');
    }
});

it('§26.9 v11B.3 PersonalizationManager preserved', function () {
    expect(file_exists(base_path('app/Services/Personalization/PersonalizationManager.php')))->toBeTrue();
});

it('§26.10 v11B.2.2 canonical pricing preserved', function () {
    expect(file_get_contents(base_path('app/Domain/Pricing/PricingService.php')))
        ->toContain('priceProductWithQuantity');
});

it('§26.11 v10.13 vendor-nav-reports testid preserved', function () {
    expect(file_get_contents(base_path('resources/js/Layouts/VendorLayout.tsx')))
        ->toContain('vendor-nav-reports');
});
