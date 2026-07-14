<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function p103Vendor(string $email = 'p103-vendor@test'): array
{
    $u = User::factory()->create(['email' => $email, 'role' => 'vendor']);
    $v = Vendor::factory()->create(['user_id' => $u->id, 'status' => 'approved']);
    $package = \App\Models\VendorPackage::first() ?? \App\Models\VendorPackage::create([
        'name' => 'P103 Basic', 'slug' => 'p103-basic',
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

// ─── Bug A: Filament Placeholder API valid (no disableLabel) ───

it('VendorResource does NOT use the deprecated Filament 2.x disableLabel() method', function () {
    $src = file_get_contents(app_path('Filament/Resources/VendorResource.php'));
    expect($src)->not->toContain('->disableLabel(');
    // v10.3 replaced with valid Filament 3.x extraAttributes call as a marker
    expect(substr_count($src, "'data-v103' => 'vendor-file-preview'"))->toBe(4);
});

// ─── Bug B/C: bulletproof model-level guard against images mass-assignment ───

it('Product::fill() strips images key bulletproof — even direct fill calls', function () {
    [, $vendor] = p103Vendor('p103-direct-fill@test');
    // Direct fill — should NOT throw, even though images is in $fillable check
    $p = new Product();
    $p->fill([
        'vendor_id' => $vendor->id,
        'name'      => 'P103 Direct Fill',
        'slug'      => 'p103-direct-fill',
        'sku'       => 'P103DF',
        'type'      => Product::TYPE_SIMPLE,
        'status'    => Product::STATUS_DRAFT,
        'price_minor' => 1000,
        'currency'  => 'KWD',
        'track_stock' => false,
        'stock'     => 0,
        'images'    => [['foo' => 'bar']],  // would throw without v10.3 guard
    ]);
    $p->save();
    expect(Product::find($p->id))->not->toBeNull();
});

it('Product::create() with images key in mass-assignment does NOT throw (v10.3 model guard)', function () {
    [, $vendor] = p103Vendor('p103-create-images@test');
    // This would have thrown MassAssignmentException pre-v10.3
    $p = Product::create([
        'vendor_id' => $vendor->id,
        'name'      => 'P103 Create With Images',
        'slug'      => 'p103-create-with-images',
        'sku'       => 'P103CWI',
        'type'      => Product::TYPE_SIMPLE,
        'status'    => Product::STATUS_DRAFT,
        'price_minor' => 2000,
        'currency'  => 'KWD',
        'track_stock' => false,
        'stock'     => 0,
        'images'    => ['uploaded-file-1', 'uploaded-file-2'],
    ]);
    expect($p->id)->toBeInt();
    expect($p->name)->toBe('P103 Create With Images');
    // Confirm 'images' was NOT persisted as a Product attribute
    expect($p->getAttributes())->not->toHaveKey('images');
});

it('Product::update() with images key in mass-assignment does NOT throw (v10.3 model guard)', function () {
    [, $vendor] = p103Vendor('p103-update-images@test');
    $p = Product::factory()->create([
        'vendor_id' => $vendor->id,
        'slug'      => 'p103-update-existing',
        'price_minor' => 1500,
        'currency'  => 'KWD',
    ]);
    // Pre-v10.3 this would throw
    $p->update([
        'name'   => 'P103 Updated With Images',
        'images' => ['file1', 'file2'],
    ]);
    expect(Product::find($p->id)->name)->toBe('P103 Updated With Images');
});

it('Vendor product create via HTTP with images does not throw (regression of v10.1 fix)', function () {
    Storage::fake('public');
    [$user] = p103Vendor('p103-http-images@test');
    $this->actingAs($user);
    $resp = $this->post('/vendor/products', [
        'name'        => 'P103 HTTP With Images',
        'type'        => Product::TYPE_SIMPLE,
        'price_minor' => 5000,
        'currency'    => 'KWD',
        'track_stock' => false,
        'images'      => [
            UploadedFile::fake()->image('a.jpg'),
            UploadedFile::fake()->image('b.png'),
        ],
    ]);
    expect($resp->status())->toBeIn([200, 302]);
    expect(Product::where('name', 'P103 HTTP With Images')->exists())->toBeTrue();
});

// ─── Defect 3: vendor order status dropdown present ───

it('Vendor order Show page exposes the status dropdown (v10.3 dev demand)', function () {
    $src = file_get_contents(resource_path('js/Pages/Vendor/Orders/Show.tsx'));
    expect($src)->toContain('vendor-order-status-dropdown');
    expect($src)->toContain('Update status:');
});

// ─── Defect 5: global mobile overflow guards present ───

it('Global CSS has mobile overflow guards (v10.3 defensive net)', function () {
    $src = file_get_contents(resource_path('css/app.css'));
    expect($src)->toContain('overflow-x-hidden');
    expect($src)->toContain('max-width: 100vw');
});

// ─── Defect 1/4: VendorResource form schema does not crash (no invalid Filament API) ───

it('VendorResource form definition can be retrieved without throwing (Filament 3 API validity)', function () {
    // Simulating the Filament panel asking the resource for its form. If
    // any method call in the schema is invalid (like the old
    // disableLabel(false)), this will throw a BadMethodCallException.
    $form = \App\Filament\Resources\VendorResource::form(
        new \Filament\Forms\Form(new class {
            public function getRecord() { return null; }
        })
    );
    expect($form)->toBeInstanceOf(\Filament\Forms\Form::class);
});
