<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// helpers — v8.5 unique-name guard (p101_)

function p101Admin(): User
{
    $u = User::factory()->create(['email' => 'p101-admin@test', 'role' => 'admin']);
    $u->assignRole('admin_staff');
    return $u;
}

function p101Vendor(string $email = 'p101-vendor@test'): array
{
    $u = User::factory()->create(['email' => $email, 'role' => 'vendor']);
    $v = Vendor::factory()->create(['user_id' => $u->id, 'status' => 'approved']);
    // Ensure the vendor has an active subscription with a package so vendor:approved + product policies work
    $package = \App\Models\VendorPackage::first() ?? \App\Models\VendorPackage::create([
        'name' => 'P101 Basic', 'slug' => 'p101-basic',
        'max_products' => 50, 'max_images_per_product' => 5,
        'default_admin_commission_percent' => 30, 'is_active' => true,
        'price_minor' => 0, 'currency' => 'KWD', 'billing_period' => 'monthly',
    ]);
    \App\Models\VendorSubscription::create([
        'vendor_id' => $v->id, 'vendor_package_id' => $package->id,
        'starts_at' => now(), 'status' => 'active',
        'amount_paid_minor' => 0, 'currency' => 'KWD',
    ]);
    return [$u, $v];
}

// ─────── BUG #4: product create no longer throws MassAssignmentException ───────

it('vendor product creation with NO images does not throw MassAssignmentException (v10.1 fix)', function () {
    [$user] = p101Vendor('p101-noimg@test');
    $this->actingAs($user);

    $resp = $this->post('/vendor/products', [
        'name'        => 'P101 Test Product',
        'type'        => Product::TYPE_SIMPLE,
        'price_minor' => 50000,
        'currency'    => 'KWD',
        'track_stock' => false,
    ]);
    expect($resp->status())->toBeIn([302, 200]); // redirect on success
    $p = Product::where('name', 'P101 Test Product')->first();
    expect($p)->not->toBeNull();
});

it('vendor product creation WITH images does not throw MassAssignmentException (v10.1 fix)', function () {
    Storage::fake('public');
    [$user] = p101Vendor('p101-img@test');
    $this->actingAs($user);

    $resp = $this->post('/vendor/products', [
        'name'        => 'P101 With Images',
        'type'        => Product::TYPE_SIMPLE,
        'price_minor' => 75000,
        'currency'    => 'KWD',
        'track_stock' => false,
        'images'      => [
            UploadedFile::fake()->image('a.jpg'),
            UploadedFile::fake()->image('b.png'),
        ],
    ]);
    // Must NOT throw 500 (which would happen pre-v10.1 with MassAssignmentException)
    expect($resp->status())->toBeIn([302, 200]);
    $p = Product::where('name', 'P101 With Images')->first();
    expect($p)->not->toBeNull();
    // The 'images' string is NOT a Product column; if pre-v10.1's bug was present,
    // we'd have thrown. The fix unset()s 'images' from $data before Product::create.
});

it('vendor product update with images does not throw MassAssignmentException (v10.1 fix)', function () {
    Storage::fake('public');
    [$user, $vendor] = p101Vendor('p101-upd@test');
    $p = Product::factory()->create([
        'vendor_id' => $vendor->id, 'status' => Product::STATUS_DRAFT,
        'slug' => 'p101-upd', 'price_minor' => 10000, 'currency' => 'KWD',
    ]);
    $this->actingAs($user);
    $resp = $this->patch("/vendor/products/{$p->id}", [
        'name'        => 'P101 Updated',
        'type'        => Product::TYPE_SIMPLE,
        'price_minor' => 12000,
        'currency'    => 'KWD',
        'track_stock' => false,
        'images'      => [UploadedFile::fake()->image('new.png')],
    ]);
    expect($resp->status())->toBeIn([302, 200]);
    expect(Product::find($p->id)->name)->toBe('P101 Updated');
});

// ─────── BUG #6/#7: report pages render (AdminLayout fix + nav presence) ───────

it('admin reports page renders without AdminLayout missing-component crash', function () {
    $this->actingAs(p101Admin());
    $resp = $this->get('/admin/reports');
    $resp->assertOk();
    // The Inertia component name is sent in the response; verify it's Admin/Reports/Index
    $body = $resp->getContent();
    expect($body)->toContain('Admin/Reports/Index');
});

it('VendorLayout has a Reports nav link (v10.1 fix)', function () {
    $src = file_get_contents(resource_path('js/Layouts/VendorLayout.tsx'));
    expect($src)->toContain('/vendor/reports');
    expect($src)->toContain('vendor-nav-reports');
});

it('AdminPanelProvider registers a Reports navigation item (v10.1 fix)', function () {
    $src = file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));
    expect($src)->toContain('Reports Dashboard');
    expect($src)->toContain('/admin/reports');
    expect($src)->toContain('viewReports');
});

// ─────── BUG #5: vendor order list page exposes action buttons inline ───────

it('vendor order list page has inline action button testids for confirm/ship/deliver', function () {
    $src = file_get_contents(resource_path('js/Pages/Vendor/Orders/Index.tsx'));
    expect($src)->toContain('row-confirm-');
    expect($src)->toContain('row-ship-');
    expect($src)->toContain('row-deliver-');
});

// ─────── #2: vendor file viewer is secured by signature + admin role ───────

it('VendorFileController rejects requests without valid signature (signed URL guard)', function () {
    [, $vendor] = p101Vendor('p101-files-vendor@test');
    $admin = p101Admin();
    $this->actingAs($admin);

    // Hit the route WITHOUT a signature → should 403
    $resp = $this->get("/admin/vendor-files/{$vendor->id}/license_document");
    expect($resp->status())->toBe(403);
});

it('VendorFileController rejects non-admin users even WITH valid signature', function () {
    [$nonAdminUser, $vendor] = p101Vendor('p101-files-nonadmin@test');
    $this->actingAs($nonAdminUser);

    $url = \Illuminate\Support\Facades\URL::temporarySignedRoute(
        'admin.vendor-files.show',
        now()->addMinutes(30),
        ['vendor' => $vendor->id, 'kind' => 'license_document'],
    );
    // Signature OK but role wrong → 403 from controller layer
    $resp = $this->get($url);
    expect($resp->status())->toBe(403);
});

it('VendorFileController rejects unknown file kinds', function () {
    [, $vendor] = p101Vendor('p101-files-badkind@test');
    $admin = p101Admin();
    $this->actingAs($admin);

    $url = \Illuminate\Support\Facades\URL::temporarySignedRoute(
        'admin.vendor-files.show',
        now()->addMinutes(30),
        ['vendor' => $vendor->id, 'kind' => 'arbitrary_field'],
    );
    $resp = $this->get($url);
    expect($resp->status())->toBe(404);
});

// ─────── #3: admin sees vendor's selected package ───────

it('VendorResource Filament form displays the latest requested package (v10.1 fix)', function () {
    $src = file_get_contents(app_path('Filament/Resources/VendorResource.php'));
    // The placeholder section must exist
    expect($src)->toContain('requested_package');
    expect($src)->toContain('Vendor-selected package');
    expect($src)->toContain('latest_requested_package');  // table column
});

// ─────── BUG #8: sitemap.xml exists + returns XML ───────

it('GET /sitemap.xml returns valid XML with correct content type', function () {
    $resp = $this->get('/sitemap.xml');
    $resp->assertOk();
    expect($resp->headers->get('content-type'))->toStartWith('application/xml');
    expect($resp->getContent())->toContain('<urlset');
});

// ─────── #1: performance — translations are cached ───────

it('Inertia translations payload is cached (v10.1 performance fix)', function () {
    \Illuminate\Support\Facades\Cache::flush();
    $reads = 0;
    \Illuminate\Support\Facades\Event::listen(
        \Illuminate\Cache\Events\CacheHit::class,
        function ($e) use (&$reads) { if (str_contains($e->key, 'inertia:translations:')) { $reads++; } }
    );

    // First request populates the cache (CacheMissed event); second request gets a hit.
    $this->get('/');   // miss
    $this->get('/');   // hit
    expect($reads)->toBeGreaterThanOrEqual(1);
});

// ─────── Mobile responsive: layouts emit the mobile toggle test IDs ───────

it('VendorLayout includes mobile menu toggle (v10.1 responsive fix)', function () {
    $src = file_get_contents(resource_path('js/Layouts/VendorLayout.tsx'));
    expect($src)->toContain('vendor-mobile-menu');
    expect($src)->toContain('lg:hidden');  // hamburger visibility
});

it('StorefrontLayout includes mobile menu toggle (v10.1 responsive fix)', function () {
    $src = file_get_contents(resource_path('js/Layouts/StorefrontLayout.tsx'));
    expect($src)->toContain('storefront-mobile-toggle');
    expect($src)->toContain('storefront-mobile-menu');
    expect($src)->toContain('md:hidden');
});
