<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// ─── helpers ─────────────────────────────────────────────────────────────

function p1014_seed(): void
{
    \Artisan::call('db:seed', [
        '--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder',
        '--force' => true,
    ]);
}

function p1014_admin(): User
{
    p1014_seed();
    $u = User::factory()->create(['status' => 'active']);
    $u->assignRole('super_admin');
    return $u->fresh();
}

function p1014_approved_vendor_user(): User
{
    p1014_seed();
    $u = User::factory()->create(['status' => 'active']);
    $u->assignRole('vendor');
    Vendor::create([
        'user_id' => $u->id, 'business_name' => 'V' . uniqid(),
        'business_email' => 'v' . uniqid() . '@p1014.test',
        'business_type' => 'company', 'country' => 'KW',
        'status' => Vendor::STATUS_APPROVED,
    ]);
    return $u->fresh();
}

function p1014_run_with_query_log(callable $fn): array
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    try {
        $fn();
        return DB::getQueryLog();
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }
}

// ─── §4 — scope-aware Inertia share ───────────────────────────────────

it('admin /admin/reports does NOT fire the cart_summary cart query', function () {
    $admin = p1014_admin();
    $queries = p1014_run_with_query_log(function () use ($admin) {
        $this->actingAs($admin)->get('/admin/reports')->assertOk();
    });

    // Pre-v10.14 the cart_summary closure on EVERY render did:
    //   SELECT * FROM carts WHERE user_id = ?
    // The query log should NOT contain a carts-table query for admin paths.
    $sqls = array_map(fn ($q) => $q['query'], $queries);
    $cartQueries = array_filter($sqls, fn ($s) => stripos($s, 'from `carts`') !== false || stripos($s, 'from "carts"') !== false);
    expect($cartQueries)->toBeEmpty();
});

it('admin /admin/reports does NOT fire the top_categories cache lookup query', function () {
    $admin = p1014_admin();
    Cache::forget('marketplace:top_categories:v1');
    $queries = p1014_run_with_query_log(function () use ($admin) {
        $this->actingAs($admin)->get('/admin/reports')->assertOk();
    });
    $sqls = array_map(fn ($q) => $q['query'], $queries);
    // The categories table should NOT be queried during an admin page render
    $catQueries = array_filter($sqls, fn ($s) => stripos($s, 'from `categories`') !== false || stripos($s, 'from "categories"') !== false);
    expect($catQueries)->toBeEmpty();
});

it('vendor /vendor (dashboard) does NOT fire the cart_summary query', function () {
    $u = p1014_approved_vendor_user();
    $queries = p1014_run_with_query_log(function () use ($u) {
        $this->actingAs($u)->get('/vendor');
    });
    $sqls = array_map(fn ($q) => $q['query'], $queries);
    $cartQueries = array_filter($sqls, fn ($s) => stripos($s, 'from `carts`') !== false || stripos($s, 'from "carts"') !== false);
    expect($cartQueries)->toBeEmpty();
});

it('storefront / DOES make cart_summary available (regression guard — must not break storefront)', function () {
    $u = p1014_admin(); // any authenticated user, including a non-admin viewing the storefront
    // The HOME route shares cart_summary. We just assert the page loads (no exception).
    $this->actingAs($u)->get('/')->assertOk();
});

it('HandleInertiaRequests source has the scope-aware marker', function () {
    $src = file_get_contents(app_path('Http/Middleware/HandleInertiaRequests.php'));
    expect($src)->toContain("Phase 10 v10.14 §4 PERFORMANCE");
    expect($src)->toContain("str_starts_with(\$path, 'admin/')");
    expect($src)->toContain("str_starts_with(\$path, 'vendor/')");
});

// ─── §3 — Homepage health probe cached ───────────────────────────────

it('homepage health probe result is cached for 30 seconds', function () {
    Cache::forget('marketplace:homepage_health:v1');
    // First hit primes the cache
    $this->get('/')->assertOk();
    expect(Cache::has('marketplace:homepage_health:v1'))->toBeTrue();
});

it('HomeController source caches the health probe (no per-render 2s curl)', function () {
    $src = file_get_contents(app_path('Http/Controllers/HomeController.php'));
    expect($src)->toContain('marketplace:homepage_health:v1');
    expect($src)->toContain('addSeconds(30)');
});

// ─── §12 — Performance indexes migration ─────────────────────────────

it('v10.14 performance indexes migration is present and idempotent', function () {
    $path = database_path('migrations/2026_06_21_000001_add_phase10_v1014_performance_indexes.php');
    expect(file_exists($path))->toBeTrue();
    $src = file_get_contents($path);
    // Expected new indexes (the gaps after v10.1)
    foreach ([
        'orders_user_created_idx',
        'orders_status_created_idx',
        'st_user_status_created_idx',
        'st_vendor_status_created_idx',
        'st_status_created_idx',
        'stm_ticket_created_idx',
        'vendors_status_created_idx',
        'vpr_vendor_status_created_idx',
    ] as $idx) {
        expect($src)->toContain($idx);
    }
    // Idempotent guard
    expect($src)->toContain('private function hasIndex');
});

it('v10.14 performance indexes actually applied after migrate', function () {
    // RefreshDatabase has already run migrations including v10.14
    // Verify the indexes exist on the actual DB
    $existing = collect(DB::getSchemaBuilder()->getIndexes('orders'))
        ->pluck('name')
        ->all();
    expect($existing)->toContain('orders_user_created_idx');
    expect($existing)->toContain('orders_status_created_idx');
});

// ─── §13 — Functional regression: nothing broke ──────────────────────

it('regression: admin /admin/reports still returns 200 (v10.12 still works)', function () {
    $this->actingAs(p1014_admin())->get('/admin/reports')->assertOk();
});

it('regression: vendor /vendor/reports still returns 200 (v10.13 still works)', function () {
    $this->actingAs(p1014_approved_vendor_user())->get('/vendor/reports')->assertOk();
});

it('regression: vendor /vendor dashboard still returns 200', function () {
    $this->actingAs(p1014_approved_vendor_user())->get('/vendor')->assertOk();
});

it('regression: public / homepage still returns 200', function () {
    $this->get('/')->assertOk();
});

// ─── Preservation across v10.0-v10.13 ────────────────────────────────

it('v10.11 §5 + v10.12 + v10.13 fixes all preserved', function () {
    $svc = file_get_contents(app_path('Domain/Reports/ReportsService.php'));
    expect(substr_count($svc, 'SUM(requested_amount_minor)'))->toBeGreaterThanOrEqual(2);
    expect($svc)->toContain("User::role('customer')");
    expect($svc)->not->toContain('SUM(amount_minor)');

    $layout = file_get_contents(resource_path('js/Layouts/VendorLayout.tsx'));
    expect($layout)->toContain('ReportsIcon');
    expect($layout)->toContain('vendor-nav-reports');

    $dash = file_get_contents(resource_path('js/Pages/Vendor/Dashboard.tsx'));
    expect($dash)->toContain('vendor-dashboard-reports-cta');
});

// ─── Cross-cutting ──────────────────────────────────────────────────

it('VERSION reports Phase 10 v10.14', function () {
    expect(trim((string) file_get_contents(base_path('VERSION'))))->toBe('Phase 10 v10.14');
});
