<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

// ─── helpers ─────────────────────────────────────────────────────────────

function p11ah2_seed(): void
{
    \Artisan::call('db:seed', [
        '--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder',
        '--force' => true,
    ]);
}

function p11ah2_customer(): User
{
    p11ah2_seed();
    $u = User::factory()->create([
        'email'    => 'p11ah2-c-' . uniqid() . '@p11ah2.test',
        'password' => Hash::make('pw'),
        'status'   => 'active',
    ]);
    $u->assignRole('customer');
    return $u->fresh();
}

function p11ah2_vendor_user(): User
{
    p11ah2_seed();
    $u = User::factory()->create([
        'email'    => 'p11ah2-v-' . uniqid() . '@p11ah2.test',
        'password' => Hash::make('pw'),
        'status'   => 'active',
    ]);
    $u->assignRole('vendor');
    Vendor::create([
        'user_id' => $u->id, 'business_name' => 'V' . uniqid(),
        'business_email' => 'v' . uniqid() . '@p11ah2.test',
        'business_type' => 'company', 'country' => 'KW',
        'status' => Vendor::STATUS_APPROVED,
    ]);
    return $u->fresh();
}

function p11ah2_admin(): User
{
    p11ah2_seed();
    $u = User::factory()->create([
        'email'    => 'p11ah2-a-' . uniqid() . '@p11ah2.test',
        'password' => Hash::make('pw'),
        'status'   => 'active',
    ]);
    $u->assignRole('super_admin');
    return $u->fresh();
}

// ─── §14 — Pages still render (preservation gate) ────────────────────

it('GET / as guest still renders Welcome after v11A.2 container migration', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Welcome'));
});

it('GET / as authenticated customer still renders Welcome', function () {
    $this->actingAs(p11ah2_customer())->get('/')->assertOk();
});

it('GET /products still renders 200', function () {
    $this->get('/products')->assertOk();
});

it('GET /vendor as vendor still renders 200', function () {
    $this->actingAs(p11ah2_vendor_user())->get('/vendor')->assertOk();
});

it('GET /admin/reports as super_admin still renders 200', function () {
    $this->actingAs(p11ah2_admin())->get('/admin/reports')->assertOk();
});

it('GET /cart as customer still renders 200', function () {
    $this->actingAs(p11ah2_customer())->get('/cart')->assertOk();
});

it('customer login flow still works end-to-end', function () {
    $u = User::factory()->create([
        'email' => 'p11ah2-flow@p11ah2.test', 'password' => Hash::make('pw'), 'status' => 'active',
    ]);
    p11ah2_seed();
    $u->assignRole('customer');
    $this->post('/login', ['email' => 'p11ah2-flow@p11ah2.test', 'password' => 'pw'])
        ->assertRedirect('/');
    $this->get('/')->assertOk();
});

// ─── §4 — Container at canonical path (dev's recommended location) ───

it('Container exists at the canonical path Components/Layout/Container.tsx', function () {
    expect(file_exists(resource_path('js/Components/Layout/Container.tsx')))->toBeTrue();
});

it('Obsolete v11A.1 Container path is removed (no zombie file)', function () {
    expect(file_exists(resource_path('js/Components/ui/v11/Container.tsx')))->toBeFalse();
});

// ─── §7 — Static class string, NO dynamic construction ──────────────

it('Container uses the exact dev §4 literal class string', function () {
    $src = file_get_contents(resource_path('js/Components/Layout/Container.tsx'));
    expect($src)->toContain("'mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8 xl:px-10'");
});

it('Container has NO template literal interpolation in className (root-cause fix)', function () {
    $src = file_get_contents(resource_path('js/Components/Layout/Container.tsx'));
    // No ${...} interpolations anywhere — Tailwind JIT scanner could miss them
    expect($src)->not->toMatch('/\$\{[^}]+\}/');
});

it('Container uses default export per dev §4 sample', function () {
    $src = file_get_contents(resource_path('js/Components/Layout/Container.tsx'));
    expect($src)->toMatch('/^export default function Container/m');
});

// ─── §7 — Tailwind safelist guarantees critical classes in build ───

it('tailwind.config.js has a safelist entry', function () {
    $tw = file_get_contents(base_path('tailwind.config.js'));
    expect($tw)->toContain('safelist:');
});

it('Tailwind safelist contains every critical container utility', function () {
    $tw = file_get_contents(base_path('tailwind.config.js'));
    // Extract the safelist block
    preg_match('/safelist:\s*\[(.*?)\]/s', $tw, $m);
    expect($m[1] ?? '')->not->toBeEmpty();
    foreach ([
        "'mx-auto'", "'w-full'", "'max-w-7xl'",
        "'px-4'", "'sm:px-6'", "'lg:px-8'", "'xl:px-10'",
    ] as $cls) {
        expect($m[1])->toContain($cls);
    }
});

// ─── §3 — Active component chain uses the canonical Container ───────

it('Welcome.tsx imports Container from canonical path (NOT v11A.1 path)', function () {
    $src = file_get_contents(resource_path('js/Pages/Welcome.tsx'));
    expect($src)->toContain("from '@/Components/Layout/Container'");
    expect($src)->not->toContain("from '@/Components/ui/v11/Container'");
});

it('StorefrontLayout.tsx imports Container from canonical path', function () {
    $src = file_get_contents(resource_path('js/Layouts/StorefrontLayout.tsx'));
    expect($src)->toContain("from '@/Components/Layout/Container'");
    expect($src)->not->toContain("from '@/Components/ui/v11/Container'");
});

it('Welcome.tsx uses <Container> 9 times for the 7 sections + 2 nested', function () {
    $src = file_get_contents(resource_path('js/Pages/Welcome.tsx'));
    expect(substr_count($src, '<Container'))->toBe(9);
    expect($src)->not->toContain('container-app');
});

it('StorefrontLayout.tsx uses <Container> for header utility-bar + main header + footer', function () {
    $src = file_get_contents(resource_path('js/Layouts/StorefrontLayout.tsx'));
    expect(substr_count($src, '<Container'))->toBe(3);
    expect($src)->not->toContain('container-app');
});

// ─── §6 — No spacing-neutralizing classes in active surfaces ────────

it('Welcome.tsx has no padding-neutralizing classes on storefront content', function () {
    $src = file_get_contents(resource_path('js/Pages/Welcome.tsx'));
    // Allow `top-0`, `gap-0.5`, `0.5` numerics — block only the dangerous ones:
    // standalone `px-0`/`p-0` classes, `-mx-*` negative margins on storefront content,
    // `w-screen` on inner content, `100vw` width.
    expect($src)->not->toMatch('/className="[^"]*\bpx-0\b/');
    expect($src)->not->toMatch('/className="[^"]*\bp-0\b/');
    expect($src)->not->toMatch('/className="[^"]*\b-mx-\d/');
    expect($src)->not->toMatch('/className="[^"]*\bw-screen\b/');
    expect($src)->not->toMatch('/100vw/');
});

it('StorefrontLayout.tsx has no padding-neutralizing classes on chrome content', function () {
    $src = file_get_contents(resource_path('js/Layouts/StorefrontLayout.tsx'));
    expect($src)->not->toMatch('/className="[^"]*\bpx-0\b/');
    expect($src)->not->toMatch('/className="[^"]*\bp-0\b/');
    expect($src)->not->toMatch('/className="[^"]*\b-mx-\d/');
    expect($src)->not->toMatch('/className="[^"]*\bw-screen\b/');
});

// ─── v11A preservation (no regression to the redesign) ──────────────

it('All 7 v11A homepage sections still present after v11A.2', function () {
    $src = file_get_contents(resource_path('js/Pages/Welcome.tsx'));
    foreach ([
        'homepage-hero', 'homepage-trust', 'homepage-categories',
        'homepage-featured-products', 'homepage-deals-banner',
        'homepage-services', 'homepage-how-it-works',
    ] as $section) {
        expect($src)->toContain("data-testid=\"$section\"");
    }
});

it('v11A StorefrontLayout testids + v10.6 mobile drawer preserved', function () {
    $src = file_get_contents(resource_path('js/Layouts/StorefrontLayout.tsx'));
    expect($src)->toContain('storefront-header-v11a');
    expect($src)->toContain('storefront-footer-v11a');
    expect($src)->toContain('storefront-search');
    expect($src)->toContain('mobile-categories-toggle');
});

it('v10.16 null-safe permissions preserved (no unsafe access)', function () {
    $src = file_get_contents(resource_path('js/Pages/Welcome.tsx'));
    expect($src)->not->toMatch('/user\.permissions\.length/');
    expect($src)->toMatch('/user\??\.?permissions \?\? \[\]/');
});

it('All v10.x backend fixes preserved (zero PHP modified in v11A.2)', function () {
    $mid = file_get_contents(app_path('Http/Middleware/HandleInertiaRequests.php'));
    expect(substr_count($mid, '$request->user()->getAllPermissions()->pluck'))->toBe(0);
    expect(substr_count($mid, 'defensive catch'))->toBe(5);
    expect(substr_count($mid, "str_starts_with(\$path, 'admin/')"))->toBe(2);

    $ctrl = file_get_contents(app_path('Http/Controllers/Admin/ReportsController.php'));
    expect(substr_count($ctrl, 'guardAdminReportsAccess'))->toBe(3);
});

it('Welcome.tsx + StorefrontLayout pass brace/paren balance', function () {
    foreach ([
        resource_path('js/Pages/Welcome.tsx'),
        resource_path('js/Layouts/StorefrontLayout.tsx'),
        resource_path('js/Components/Layout/Container.tsx'),
    ] as $f) {
        $src = file_get_contents($f);
        expect(substr_count($src, '{'))->toBe(substr_count($src, '}'));
        expect(substr_count($src, '('))->toBe(substr_count($src, ')'));
    }
});

// ─── Cross-cutting ──────────────────────────────────────────────────

it('VERSION reports Phase 11A v11A.2', function () {
    expect(trim((string) file_get_contents(base_path('VERSION'))))->toBe('Phase 11A v11A.2');
});
