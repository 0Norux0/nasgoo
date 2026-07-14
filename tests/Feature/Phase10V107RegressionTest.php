<?php

declare(strict_types=1);

use App\Domain\Vendor\VendorFileResolver;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

// ─── helpers ─────────────────────────────────────────────────────────────

function p107_admin(): User
{
    $u = User::factory()->create();
    if (method_exists($u, 'assignRole')) {
        // spatie permission stack is initialized; assignRole works after roles seeded
        try { $u->assignRole('super_admin'); } catch (\Throwable) {}
    }
    return $u->fresh() ?? $u;
}

function p107_vendor(array $overrides = []): Vendor
{
    $u = User::factory()->create();
    return Vendor::create(array_merge([
        'user_id'        => $u->id,
        'business_name'  => 'Demo',
        'business_email' => 'demo@p107.test',
        'business_type'  => 'company',
        'country'        => 'KW',
        'status'         => Vendor::STATUS_PENDING,
    ], $overrides));
}

// ─── Path normalization tests ────────────────────────────────────────────

it('VendorFileResolver normalizes leading slash + storage prefix', function () {
    expect(VendorFileResolver::normalizePath('/storage/vendors/1/x.jpg'))->toBe('vendors/1/x.jpg');
    expect(VendorFileResolver::normalizePath('storage/vendors/1/x.jpg'))->toBe('vendors/1/x.jpg');
    expect(VendorFileResolver::normalizePath('public/vendors/1/x.jpg'))->toBe('vendors/1/x.jpg');
    expect(VendorFileResolver::normalizePath('storage/app/public/vendors/1/x.jpg'))->toBe('vendors/1/x.jpg');
    expect(VendorFileResolver::normalizePath('storage/app/private/vendors/1/x.jpg'))->toBe('vendors/1/x.jpg');
});

it('VendorFileResolver normalizes duplicated vendors/vendors/ prefix', function () {
    expect(VendorFileResolver::normalizePath('vendors/vendors/1/x.jpg'))->toBe('vendors/1/x.jpg');
    expect(VendorFileResolver::normalizePath('vendors/vendors/vendors/1/x.jpg'))->toBe('vendors/1/x.jpg');
});

it('VendorFileResolver converts Windows backslashes', function () {
    expect(VendorFileResolver::normalizePath('vendors\\1\\x.jpg'))->toBe('vendors/1/x.jpg');
});

it('VendorFileResolver REJECTS path traversal', function () {
    expect(VendorFileResolver::normalizePath('../../etc/passwd'))->toBeNull();
    expect(VendorFileResolver::normalizePath('vendors/1/../../../etc/passwd'))->toBeNull();
    expect(VendorFileResolver::normalizePath("vendors/1/x\0.jpg"))->toBeNull();
    expect(VendorFileResolver::normalizePath(''))->toBeNull();
});

it('VendorFileResolver isImage detects supported extensions', function () {
    foreach (['x.jpg', 'x.JPG', 'x.jpeg', 'x.png', 'x.webp', 'x.gif'] as $p) {
        expect(VendorFileResolver::isImage($p))->toBeTrue();
    }
    foreach (['x.pdf', 'x.txt', 'x.exe', 'x'] as $p) {
        expect(VendorFileResolver::isImage($p))->toBeFalse();
    }
});

// ─── Resolver: file actually exists scenarios ────────────────────────────

it('resolver finds JPG logo uploaded to the public disk (new architecture)', function () {
    Storage::fake('public');
    Storage::disk('public')->put('vendors/42/logo.jpg', 'jpgbytes');

    $v = p107_vendor(['logo_path' => 'vendors/42/logo.jpg']);
    $resolved = VendorFileResolver::resolve($v, 'logo');

    expect($resolved)->not->toBeNull();
    expect($resolved['disk'])->toBe('public');
    expect($resolved['path'])->toBe('vendors/42/logo.jpg');
    expect($resolved['is_image'])->toBeTrue();
    expect($resolved['is_canonical'])->toBeTrue();
});

it('resolver finds LEGACY JPG logo on the local disk via fallback', function () {
    // Pre-v10.7 the upload code wrote logo/banner to local. New resolver
    // still finds those files (legacy compatibility) and reports the
    // disk as non-canonical.
    Storage::fake('public');   // canonical = empty
    Storage::fake('vendors');
    Storage::fake('local');
    Storage::disk('local')->put('vendors/42/logo.jpg', 'jpgbytes');

    $v = p107_vendor(['logo_path' => 'vendors/42/logo.jpg']);
    $resolved = VendorFileResolver::resolve($v, 'logo');

    expect($resolved)->not->toBeNull();
    expect($resolved['disk'])->toBe('local');
    expect($resolved['is_canonical'])->toBeFalse();
});

it('resolver finds PDF license on the private vendors disk (regression — PDF flow preserved)', function () {
    Storage::fake('vendors');
    Storage::disk('vendors')->put('vendors/42/license.pdf', 'pdfbytes');

    $v = p107_vendor(['license_document_path' => 'vendors/42/license.pdf']);
    $resolved = VendorFileResolver::resolve($v, 'license_document');

    expect($resolved)->not->toBeNull();
    expect($resolved['disk'])->toBe('vendors');
    expect($resolved['is_image'])->toBeFalse();
});

it('resolver finds JPG license_document on the private disk', function () {
    Storage::fake('vendors');
    Storage::disk('vendors')->put('vendors/42/license.jpg', 'jpgbytes');

    $v = p107_vendor(['license_document_path' => 'vendors/42/license.jpg']);
    $resolved = VendorFileResolver::resolve($v, 'license_document');

    expect($resolved)->not->toBeNull();
    expect($resolved['disk'])->toBe('vendors');
    expect($resolved['is_image'])->toBeTrue();
});

it('resolver finds PNG id_document on the private disk', function () {
    Storage::fake('vendors');
    Storage::disk('vendors')->put('vendors/42/id.png', 'pngbytes');

    $v = p107_vendor(['id_document_path' => 'vendors/42/id.png']);
    $resolved = VendorFileResolver::resolve($v, 'id_document');

    expect($resolved)->not->toBeNull();
    expect($resolved['disk'])->toBe('vendors');
    expect($resolved['is_image'])->toBeTrue();
});

it('resolver returns null when no file is recorded', function () {
    $v = p107_vendor();
    expect(VendorFileResolver::resolve($v, 'logo'))->toBeNull();
});

it('resolver returns null when file path is recorded but file is missing on every disk', function () {
    Storage::fake('public');
    Storage::fake('vendors');
    Storage::fake('local');

    $v = p107_vendor(['logo_path' => 'vendors/42/missing.png']);
    expect(VendorFileResolver::resolve($v, 'logo'))->toBeNull();
});

it('resolver normalizes a legacy stored path with leading slash', function () {
    Storage::fake('vendors');
    Storage::disk('vendors')->put('vendors/42/id.png', 'x');

    // Store the path with an accidental leading slash — should still resolve
    $v = p107_vendor(['id_document_path' => '/vendors/42/id.png']);
    $resolved = VendorFileResolver::resolve($v, 'id_document');
    expect($resolved)->not->toBeNull();
    expect($resolved['path'])->toBe('vendors/42/id.png');
});

// ─── Controller behavior ─────────────────────────────────────────────────

it('admin can open a JPG logo through the signed file route', function () {
    Storage::fake('public');
    Storage::disk('public')->put('vendors/42/logo.jpg', file_get_contents(
        UploadedFile::fake()->image('logo.jpg', 200, 200)->getRealPath()
    ));

    $v = p107_vendor(['logo_path' => 'vendors/42/logo.jpg']);
    $admin = p107_admin();

    $url = URL::temporarySignedRoute(
        'admin.vendor-files.show',
        now()->addMinutes(5),
        ['vendor' => $v->id, 'kind' => 'logo']
    );

    $resp = $this->actingAs($admin)->get($url);
    $resp->assertOk();
    expect($resp->headers->get('Content-Type'))->toContain('image/');
});

it('admin can open a PDF license through the signed file route (regression)', function () {
    Storage::fake('vendors');
    Storage::disk('vendors')->put('vendors/42/license.pdf', '%PDF-1.4');

    $v = p107_vendor(['license_document_path' => 'vendors/42/license.pdf']);
    $admin = p107_admin();

    $url = URL::temporarySignedRoute(
        'admin.vendor-files.show',
        now()->addMinutes(5),
        ['vendor' => $v->id, 'kind' => 'license_document']
    );

    $resp = $this->actingAs($admin)->get($url);
    $resp->assertOk();
});

it('non-admin gets 403 on the signed file route', function () {
    Storage::fake('public');
    Storage::disk('public')->put('vendors/42/logo.jpg', 'x');

    $v = p107_vendor(['logo_path' => 'vendors/42/logo.jpg']);
    $u = User::factory()->create();  // not an admin

    $url = URL::temporarySignedRoute(
        'admin.vendor-files.show',
        now()->addMinutes(5),
        ['vendor' => $v->id, 'kind' => 'logo']
    );

    $resp = $this->actingAs($u)->get($url);
    $resp->assertForbidden();
});

it('missing file returns a controlled 404 (not a crash)', function () {
    Storage::fake('vendors');

    $v = p107_vendor(['id_document_path' => 'vendors/42/ghost.png']);
    $admin = p107_admin();

    $url = URL::temporarySignedRoute(
        'admin.vendor-files.show',
        now()->addMinutes(5),
        ['vendor' => $v->id, 'kind' => 'id_document']
    );

    $resp = $this->actingAs($admin)->get($url);
    $resp->assertNotFound();
});

// ─── Cross-cutting ───────────────────────────────────────────────────────

it('VERSION reports Phase 10 v10.7', function () {
    expect(trim((string) file_get_contents(base_path('VERSION'))))->toBe('Phase 10 v10.7');
});
