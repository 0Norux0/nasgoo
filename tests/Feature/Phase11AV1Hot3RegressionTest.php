<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

// ─── helpers ─────────────────────────────────────────────────────────────

function p11ah3_seed(): void
{
    \Artisan::call('db:seed', [
        '--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder',
        '--force' => true,
    ]);
}

function p11ah3_customer(): User
{
    p11ah3_seed();
    $u = User::factory()->create([
        'email'    => 'p11ah3-c-' . uniqid() . '@p11ah3.test',
        'password' => Hash::make('pw'),
        'status'   => 'active',
    ]);
    $u->assignRole('customer');
    return $u->fresh();
}

function p11ah3_vendor_user(): User
{
    p11ah3_seed();
    $u = User::factory()->create([
        'email'    => 'p11ah3-v-' . uniqid() . '@p11ah3.test',
        'password' => Hash::make('pw'),
        'status'   => 'active',
    ]);
    $u->assignRole('vendor');
    Vendor::create([
        'user_id' => $u->id, 'business_name' => 'V' . uniqid(),
        'business_email' => 'v' . uniqid() . '@p11ah3.test',
        'business_type' => 'company', 'country' => 'KW',
        'status' => Vendor::STATUS_APPROVED,
    ]);
    return $u->fresh();
}

function p11ah3_admin(): User
{
    p11ah3_seed();
    $u = User::factory()->create([
        'email'    => 'p11ah3-a-' . uniqid() . '@p11ah3.test',
        'password' => Hash::make('pw'),
        'status'   => 'active',
    ]);
    $u->assignRole('super_admin');
    return $u->fresh();
}

// ─── §17 — Pages still render (preservation gate) ────────────────────

it('GET / as guest still renders Welcome after v11A.3 card+contrast updates', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Welcome'));
});

it('GET / as authenticated customer still renders Welcome', function () {
    $this->actingAs(p11ah3_customer())->get('/')->assertOk();
});

it('GET /products still renders 200 (catalog card padding upgraded)', function () {
    $this->get('/products')->assertOk();
});

it('GET /services still renders 200 (services contrast upgraded)', function () {
    $this->get('/services')->assertOk();
});

it('GET /vendor as vendor still renders 200', function () {
    $this->actingAs(p11ah3_vendor_user())->get('/vendor')->assertOk();
});

it('GET /admin/reports still renders 200', function () {
    $this->actingAs(p11ah3_admin())->get('/admin/reports')->assertOk();
});

it('GET /cart still renders 200', function () {
    $this->actingAs(p11ah3_customer())->get('/cart')->assertOk();
});

// ─── §2 — Card body padding meets dev's spec ─────────────────────────

it('ProductCard primitive uses p-4 sm:p-5 body padding (16/20px per dev §2)', function () {
    $src = file_get_contents(resource_path('js/Components/ui/v11/ProductCard.tsx'));
    expect($src)->toContain('p-4 sm:p-5');
    // The old compressed pattern must be gone
    expect($src)->not->toContain("'p-3 sm:p-4'");
});

it('Catalog ProductCardView uses p-4 sm:p-5 body padding', function () {
    $src = file_get_contents(resource_path('js/Pages/Catalog/Index.tsx'));
    // Find the ProductCardView function block
    expect($src)->toMatch('/function ProductCardView.*?<div className="p-4 sm:p-5"/s');
});

// ─── §3 — Internal element rhythm (title-to-details 8px) ────────────

it('ProductCard primitive uses mt-2 for vendor name (8px title-to-details per dev §3)', function () {
    $src = file_get_contents(resource_path('js/Components/ui/v11/ProductCard.tsx'));
    // The vendor block under the title should have mt-2
    expect($src)->toMatch('/vendor_name &&\s*\(\s*<p className="mt-2/');
});

it('Catalog ProductCardView uses mt-2 for vendor (title-to-details rhythm)', function () {
    $src = file_get_contents(resource_path('js/Pages/Catalog/Index.tsx'));
    // Vendor line under title in catalog card uses mt-2
    expect($src)->toMatch('/by \{product\.vendor_name\}|"text-xs text-slate-600 mt-2/');
});

// ─── §8 — WCAG AA contrast standards ────────────────────────────────

it('ProductCard: line-through original price uses text-slate-500 not text-slate-400 (AA pass)', function () {
    $src = file_get_contents(resource_path('js/Components/ui/v11/ProductCard.tsx'));
    expect($src)->not->toMatch('/text-slate-400[^"]*line-through/');
    expect($src)->toMatch('/text-slate-500[^"]*line-through/');
});

it('Catalog: line-through original price uses text-slate-500 not text-slate-400', function () {
    $src = file_get_contents(resource_path('js/Pages/Catalog/Index.tsx'));
    expect($src)->not->toMatch('/text-slate-400[^"]*line-through/');
    expect($src)->toMatch('/text-slate-500[^"]*line-through/');
});

it('ProductCard: vendor metadata uses text-slate-600 not text-slate-500 (~7:1 on white)', function () {
    $src = file_get_contents(resource_path('js/Components/ui/v11/ProductCard.tsx'));
    // The vendor link wrap should be text-slate-600
    expect($src)->toContain('text-xs text-slate-600 truncate');
});

it('Welcome system-status strip uses text-slate-700 on bg-slate-100 (AA pass)', function () {
    $src = file_get_contents(resource_path('js/Pages/Welcome.tsx'));
    // Extract the homepage-system-status block
    preg_match('/data-testid="homepage-system-status"(.*?)<\/section>/s', $src, $m);
    expect($m[1] ?? '')->not->toBeEmpty();
    // Must NOT contain text-slate-500 in this section (4.2:1 on slate-100 = FAILS AA)
    expect($m[1])->not->toMatch('/text-slate-500\b/');
    // Must contain the AA-compliant slate-700
    expect($m[1])->toContain('text-slate-700');
});

it('Welcome hero illustration eyebrow uses text-slate-600 (was 500)', function () {
    $src = file_get_contents(resource_path('js/Pages/Welcome.tsx'));
    expect($src)->toMatch('/<p className="text-xs text-slate-600">Featured today/');
});

it('Services listing has no text-gray-400 (3.5:1 FAILS AA)', function () {
    $src = file_get_contents(resource_path('js/Pages/Services/Index.tsx'));
    expect($src)->not->toContain('text-gray-400');
});

it('Services listing has no text-gray-* (all migrated to slate-*)', function () {
    $src = file_get_contents(resource_path('js/Pages/Services/Index.tsx'));
    expect($src)->not->toMatch('/\btext-gray-\d+\b/');
});

// ─── v11A + v10.x preservation ──────────────────────────────────────

it('All 7 v11A homepage sections still present', function () {
    $src = file_get_contents(resource_path('js/Pages/Welcome.tsx'));
    foreach ([
        'homepage-hero', 'homepage-trust', 'homepage-categories',
        'homepage-featured-products', 'homepage-deals-banner',
        'homepage-services', 'homepage-how-it-works',
    ] as $section) {
        expect($src)->toContain("data-testid=\"$section\"");
    }
});

it('v11A.2 Container canonical path + safelist preserved', function () {
    expect(file_exists(resource_path('js/Components/Layout/Container.tsx')))->toBeTrue();
    expect(file_exists(resource_path('js/Components/ui/v11/Container.tsx')))->toBeFalse();
    $tw = file_get_contents(base_path('tailwind.config.js'));
    expect($tw)->toContain('safelist:');
    expect($tw)->toContain("'max-w-7xl'");
});

it('Welcome.tsx imports from canonical Container path', function () {
    $src = file_get_contents(resource_path('js/Pages/Welcome.tsx'));
    expect($src)->toContain("from '@/Components/Layout/Container'");
});

it('StorefrontLayout.tsx imports from canonical Container path', function () {
    $src = file_get_contents(resource_path('js/Layouts/StorefrontLayout.tsx'));
    expect($src)->toContain("from '@/Components/Layout/Container'");
});

it('v10.16 null-safe permissions preserved (no unsafe access)', function () {
    $src = file_get_contents(resource_path('js/Pages/Welcome.tsx'));
    expect($src)->not->toMatch('/user\.permissions\.length/');
    expect($src)->toMatch('/user\??\.?permissions \?\? \[\]/');
});

it('All v10.x backend fixes preserved (zero PHP modified in v11A.3)', function () {
    $mid = file_get_contents(app_path('Http/Middleware/HandleInertiaRequests.php'));
    expect(substr_count($mid, '$request->user()->getAllPermissions()->pluck'))->toBe(0);
    expect(substr_count($mid, 'defensive catch'))->toBe(5);
    expect(substr_count($mid, "str_starts_with(\$path, 'admin/')"))->toBe(2);

    $ctrl = file_get_contents(app_path('Http/Controllers/Admin/ReportsController.php'));
    expect(substr_count($ctrl, 'guardAdminReportsAccess'))->toBe(3);
});

it('TSX brace + paren balance after v11A.3 edits', function () {
    foreach ([
        resource_path('js/Components/ui/v11/ProductCard.tsx'),
        resource_path('js/Pages/Welcome.tsx'),
        resource_path('js/Pages/Catalog/Index.tsx'),
        resource_path('js/Pages/Services/Index.tsx'),
    ] as $f) {
        $src = file_get_contents($f);
        expect(substr_count($src, '{'))->toBe(substr_count($src, '}'));
        expect(substr_count($src, '('))->toBe(substr_count($src, ')'));
    }
});

// ─── Cross-cutting ──────────────────────────────────────────────────

it('VERSION reports Phase 11A v11A.3', function () {
    expect(trim((string) file_get_contents(base_path('VERSION'))))->toBe('Phase 11A v11A.3');
});
