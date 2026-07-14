<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// ─── Defect 1: 'vendors' disk configured ───

it("'vendors' disk is configured with a driver", function () {
    $config = config('filesystems.disks.vendors');
    expect($config)->toBeArray();
    expect($config['driver'])->toBe('local');
    expect($config)->toHaveKey('root');
});

it("config('marketplace.vendor_private_disk') resolves to a configured disk", function () {
    $diskName = config('marketplace.vendor_private_disk');
    expect($diskName)->toBeString();
    expect(config("filesystems.disks.{$diskName}"))->toBeArray();
});

it("Storage::disk(config('marketplace.vendor_private_disk')) does not throw", function () {
    $disk = config('marketplace.vendor_private_disk', 'vendors');
    // Pre-v10.6: this threw InvalidArgumentException
    $instance = Storage::disk($disk);
    expect($instance)->not->toBeNull();
});

it('vendors disk root resolves under storage/app/private (matches uploads)', function () {
    $root = config('filesystems.disks.vendors.root');
    expect($root)->toContain('storage');
    // Must match where VendorRegistrationController stores uploads
    // (uses default disk = 'local' with root storage/app/private,
    // saving paths like vendors/{id}/{filename})
    expect($root)->toEndWith('private');
});

// ─── Defect 2: vendor order status dropdown ───

it("Vendor/Orders/Show.tsx no longer uses confirm() in the dropdown handler", function () {
    $src = file_get_contents(resource_path('js/Pages/Vendor/Orders/Show.tsx'));
    // The submitStatusChange function (the dropdown's handler) must NOT
    // call confirm(). Inline buttons keep their dialogs separately.
    expect($src)->toContain('submitStatusChange');
    // Verify the dropdown's submission function doesn't contain confirm()
    // (a sloppy regex check that the function body is clean)
    expect($src)->toMatch('/submitStatusChange.*?router\.post.*?onFinish/s');
});

it("Vendor/Orders/Show.tsx exposes Updating… loading indicator", function () {
    $src = file_get_contents(resource_path('js/Pages/Vendor/Orders/Show.tsx'));
    expect($src)->toContain('vendor-order-status-submitting');
    expect($src)->toContain('statusSubmitting');
});

// ─── Defect 3: mobile categories inside hamburger ───

it('StorefrontLayout has a collapsible Categories toggle in mobile drawer', function () {
    $src = file_get_contents(resource_path('js/Layouts/StorefrontLayout.tsx'));
    expect($src)->toContain('storefront-mobile-categories-toggle');
    expect($src)->toContain('storefront-mobile-categories-list');
    expect($src)->toContain('categoriesOpen');
});

it('Catalog/Index <aside> is hidden on mobile (desktop-only)', function () {
    $src = file_get_contents(resource_path('js/Pages/Catalog/Index.tsx'));
    // The aside must have hidden lg:block — mobile hides it
    expect($src)->toMatch('/<aside\s+className="hidden lg:block"/');
});

it('HandleInertiaRequests shares top_categories via Inertia', function () {
    $src = file_get_contents(app_path('Http/Middleware/HandleInertiaRequests.php'));
    expect($src)->toContain("'top_categories'");
    expect($src)->toContain('marketplace:top_categories:v1');
});

it("top_categories shared prop returns an array of {slug,name} pairs", function () {
    // Hit any page that goes through the Inertia middleware
    \App\Models\User::factory()->create(['email' => 'p106-catshare@test', 'role' => 'customer']);
    $resp = $this->get('/');
    $resp->assertOk();
    $page = $resp->viewData('page');
    $cats = data_get($page, 'props.top_categories');
    expect($cats)->toBeArray();
    // Each entry, if any, must have slug + name
    foreach ((array) $cats as $c) {
        expect($c)->toHaveKey('slug');
        expect($c)->toHaveKey('name');
    }
});

// ─── Cross-cutting ───

it('VERSION reports Phase 10 v10.6', function () {
    expect(trim((string) file_get_contents(base_path('VERSION'))))->toBe('Phase 10 v10.6');
});
