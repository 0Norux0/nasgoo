<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── v10.2 defensive checks ───

it('VendorLayout has Reports in baseItems (visible to all vendor users)', function () {
    $src = file_get_contents(resource_path('js/Layouts/VendorLayout.tsx'));
    // The "moved into baseItems" comment is a fix marker
    expect($src)->toContain('Reports moved into baseItems');
    // And the link must be inside baseItems, not approvedItems
    $base = strpos($src, 'const baseItems');
    $approved = strpos($src, 'const approvedItems');
    $reportsLink = strpos($src, "'/vendor/reports'");
    expect($base !== false && $approved !== false && $reportsLink !== false)->toBeTrue();
    // Reports link must come BEFORE the approvedItems declaration
    expect($reportsLink)->toBeLessThan($approved);
});

it('AdminPanelProvider Reports nav uses hasAnyRole (resilient to stale Spatie cache)', function () {
    $src = file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));
    expect($src)->toContain("hasAnyRole(['super_admin', 'admin_staff'])");
    // Must NOT use ->can() for visibility (that goes through Spatie's cache)
    expect($src)->not->toContain("->can('viewReports')");
});

it('Marketplace version is exposed via shared Inertia props', function () {
    $src = file_get_contents(app_path('Http/Middleware/HandleInertiaRequests.php'));
    expect($src)->toContain("'version'");
    expect($src)->toContain('marketplace:version');
});

it('StorefrontLayout renders the version banner', function () {
    $src = file_get_contents(resource_path('js/Layouts/StorefrontLayout.tsx'));
    expect($src)->toContain('app-version-banner');
    expect($src)->toContain('app.version');
});

it('marketplace:verify-fixes command is registered', function () {
    $result = \Illuminate\Support\Facades\Artisan::call('list');
    $output = \Illuminate\Support\Facades\Artisan::output();
    expect($output)->toContain('marketplace:verify-fixes');
});

it('marketplace:verify-fixes returns success against the v10.2 working tree', function () {
    // This test runs inside the v10.2 working tree, so every check should pass
    $exit = \Illuminate\Support\Facades\Artisan::call('marketplace:verify-fixes');
    expect($exit)->toBe(0);
});

it('scripts/deploy.sh exists and covers every cache layer', function () {
    $path = base_path('scripts/deploy.sh');
    expect(is_file($path))->toBeTrue();
    expect(is_executable($path))->toBeTrue();
    $contents = file_get_contents($path);
    foreach (['npm run build', 'optimize:clear', 'filament:cache-components', 'permission:cache-reset', 'route:cache', 'view:cache', 'config:cache'] as $needle) {
        expect($contents)->toContain($needle);
    }
});

it('Shared app prop includes version key with VERSION file content', function () {
    // Request any Inertia page and confirm the version is in the shared props
    \App\Models\User::factory()->create(['email' => 'p102-ver@test', 'role' => 'customer']);
    $resp = $this->get('/');
    $resp->assertOk();
    $page = $resp->viewData('page');
    $version = data_get($page, 'props.app.version');
    expect($version)->not->toBeNull();
    // Should match the VERSION file content
    $expected = trim((string) file_get_contents(base_path('VERSION')));
    expect($version)->toBe($expected);
});
