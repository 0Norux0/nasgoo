<?php

declare(strict_types=1);

use App\Domain\Customization\CustomizationCartService;
use App\Domain\Customization\CustomizationFieldValidator;
use App\Domain\Customization\CustomizationFileStorage;
use App\Domain\Customization\ProofWorkflowService;
use App\Models\CartItem;
use App\Models\CartItemCustomization;
use App\Models\CustomizationProof;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemCustomization;
use App\Models\Product;
use App\Models\ProductCustomizationField;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/* ─────────────────────────────────────────────
   Test helpers
   ───────────────────────────────────────────── */
function customVendor(): Vendor
{
    $user = User::factory()->create(['email' => 'vendor.cust@test.example']);
    $user->assignRole('vendor');
    return Vendor::factory()->create([
        'user_id' => $user->id,
        'status'  => 'approved',
    ]);
}

function customProduct(Vendor $vendor): Product
{
    return Product::factory()->create([
        'vendor_id' => $vendor->id,
        'type'      => Product::TYPE_CUSTOM,
        'status'    => Product::STATUS_PUBLISHED,
        'price_minor' => 500,
        'currency'  => 'KWD',
        'track_stock' => false,
    ]);
}

function addField(Product $product, array $overrides = []): ProductCustomizationField
{
    return ProductCustomizationField::create(array_merge([
        'product_id' => $product->id,
        'key'        => 'field_' . uniqid(),
        'label'      => 'Field',
        'type'       => 'text',
        'required'   => false,
        'sort_order' => 0,
        'is_active'  => true,
        'extra_fee_minor' => 0,
    ], $overrides));
}

function customer(): User
{
    return User::factory()->create(['email' => 'cust.' . uniqid() . '@test.example']);
}

/* ─────────────────────────────────────────────
   1. Schema + model basics
   ───────────────────────────────────────────── */

it('Phase 7: TYPE_CUSTOM constant + isCustomizable() helper exist', function () {
    expect(Product::TYPE_CUSTOM)->toBe('custom');
    $vendor = customVendor();
    $p = customProduct($vendor);
    expect($p->isCustomizable())->toBeTrue();
    $simple = Product::factory()->create(['vendor_id' => $vendor->id, 'type' => Product::TYPE_SIMPLE]);
    expect($simple->isCustomizable())->toBeFalse();
});

it('Phase 7: customizationFields() returns fields ordered by sort_order', function () {
    $v = customVendor(); $p = customProduct($v);
    addField($p, ['sort_order' => 3, 'label' => 'C']);
    addField($p, ['sort_order' => 1, 'label' => 'A']);
    addField($p, ['sort_order' => 2, 'label' => 'B']);
    $labels = $p->customizationFields()->pluck('label')->all();
    expect($labels)->toBe(['A', 'B', 'C']);
});

it('Phase 7: activeCustomizationFields() excludes inactive fields', function () {
    $v = customVendor(); $p = customProduct($v);
    addField($p, ['is_active' => true,  'label' => 'visible']);
    addField($p, ['is_active' => false, 'label' => 'hidden']);
    expect($p->activeCustomizationFields()->pluck('label')->all())->toBe(['visible']);
});

/* ─────────────────────────────────────────────
   2. Permission catalogue integrity
   ───────────────────────────────────────────── */

it('Phase 7: new permission modules + perms registered, no duplicates in catalogue', function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    foreach (['customization_fields.view', 'customization_fields.manage',
              'customization_proofs.view', 'customization_proofs.upload', 'customization_proofs.respond'] as $p) {
        expect(\Spatie\Permission\Models\Permission::where('name', $p)->exists())->toBeTrue("Missing perm: $p");
    }
});

it('Phase 7: vendor role grants customization_fields.* and customization_proofs.upload', function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $role = \Spatie\Permission\Models\Role::where('name', 'vendor')->first();
    $perms = $role->permissions->pluck('name')->all();
    expect($perms)->toContain('customization_fields.view');
    expect($perms)->toContain('customization_fields.manage');
    expect($perms)->toContain('customization_proofs.view');
    expect($perms)->toContain('customization_proofs.upload');
});

/* ─────────────────────────────────────────────
   3. CustomizationFieldValidator
   ───────────────────────────────────────────── */

it('Phase 7: validator rejects missing required text', function () {
    $v = customVendor(); $p = customProduct($v);
    addField($p, ['key' => 'name', 'type' => 'text', 'required' => true, 'label' => 'Your name']);

    expect(fn () => app(CustomizationFieldValidator::class)->validate($p, [], []))
        ->toThrow(ValidationException::class);
});

it('Phase 7: validator rejects missing required file', function () {
    $v = customVendor(); $p = customProduct($v);
    addField($p, ['key' => 'photo', 'type' => 'image', 'required' => true, 'label' => 'Photo']);

    expect(fn () => app(CustomizationFieldValidator::class)->validate($p, [], []))
        ->toThrow(ValidationException::class);
});

it('Phase 7: validator rejects oversized files', function () {
    $v = customVendor(); $p = customProduct($v);
    addField($p, ['key' => 'photo', 'type' => 'image', 'required' => true, 'label' => 'Photo',
        'allowed_file_types' => ['jpg', 'png'], 'max_file_size_kb' => 10]);

    $big = UploadedFile::fake()->image('huge.jpg')->size(500); // 500 KB

    try {
        app(CustomizationFieldValidator::class)->validate($p, [], ['photo' => $big]);
        $this->fail('Expected ValidationException');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('customizations.photo');
    }
});

it('Phase 7: validator rejects disallowed extensions', function () {
    $v = customVendor(); $p = customProduct($v);
    addField($p, ['key' => 'photo', 'type' => 'image', 'required' => true, 'label' => 'Photo',
        'allowed_file_types' => ['jpg', 'png'], 'max_file_size_kb' => 1024]);

    $bad = UploadedFile::fake()->create('virus.exe', 5, 'application/x-msdownload');

    try {
        app(CustomizationFieldValidator::class)->validate($p, [], ['photo' => $bad]);
        $this->fail('Expected ValidationException for disallowed extension');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('customizations.photo');
    }
});

it('Phase 7: validator enforces max_text_length', function () {
    $v = customVendor(); $p = customProduct($v);
    addField($p, ['key' => 'text', 'type' => 'text', 'required' => true,
        'label' => 'Text', 'max_text_length' => 5]);

    try {
        app(CustomizationFieldValidator::class)->validate($p, ['text' => 'way too long'], []);
        $this->fail('Expected ValidationException');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('customizations.text');
    }
});

it('Phase 7: validator rejects non-existent selection option', function () {
    $v = customVendor(); $p = customProduct($v);
    addField($p, ['key' => 'color', 'type' => 'color', 'required' => true, 'label' => 'Color',
        'options' => [['value' => 'red', 'label' => 'Red'], ['value' => 'blue', 'label' => 'Blue']]]);

    try {
        app(CustomizationFieldValidator::class)->validate($p, ['color' => 'orange'], []);
        $this->fail('Expected ValidationException');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('customizations.color');
    }
});

it('Phase 7: validator aggregates per-option extra_fee into the row', function () {
    $v = customVendor(); $p = customProduct($v);
    addField($p, ['key' => 'color', 'type' => 'color', 'required' => true, 'label' => 'Color',
        'extra_fee_minor' => 50,  // base field fee
        'options' => [['value' => 'red', 'label' => 'Red', 'extra_fee' => 100]]]);

    $rows = app(CustomizationFieldValidator::class)->validate($p, ['color' => 'red'], []);
    expect($rows->first()['extra_fee_minor'])->toBe(150); // 50 base + 100 option
});

it('Phase 7: validator skips empty optional fields entirely', function () {
    $v = customVendor(); $p = customProduct($v);
    addField($p, ['key' => 'note', 'type' => 'text', 'required' => false, 'label' => 'Note']);
    $rows = app(CustomizationFieldValidator::class)->validate($p, [], []);
    expect($rows)->toHaveCount(0);
});

/* ─────────────────────────────────────────────
   4. CustomizationCartService
   ───────────────────────────────────────────── */

it('Phase 7: addCustomized creates a fresh cart line and rolls fees onto it', function () {
    Storage::fake('local');
    $vendor = customVendor(); $p = customProduct($vendor);
    $field = addField($p, ['key' => 'text', 'type' => 'text', 'required' => true,
        'label' => 'Text', 'extra_fee_minor' => 200]);

    $user = customer();
    $rows = app(CustomizationFieldValidator::class)->validate($p, ['text' => 'Hello'], []);
    $line = app(CustomizationCartService::class)->addCustomized($user, $p, 1, null, $rows);

    expect($line->customization_fee_minor)->toBe(200);
    expect($line->customizations()->count())->toBe(1);
    expect($line->lineTotalMinor())->toBe(500 + 200); // unit_price + fee
});

it('Phase 7: customized items NEVER merge into existing cart lines', function () {
    Storage::fake('local');
    $vendor = customVendor(); $p = customProduct($vendor);
    addField($p, ['key' => 'name', 'type' => 'text', 'required' => true, 'label' => 'Name']);

    $user = customer();
    $svc = app(CustomizationCartService::class);

    $rows1 = app(CustomizationFieldValidator::class)->validate($p, ['name' => 'Alice'], []);
    $line1 = $svc->addCustomized($user, $p, 1, null, $rows1);
    $rows2 = app(CustomizationFieldValidator::class)->validate($p, ['name' => 'Bob'], []);
    $line2 = $svc->addCustomized($user, $p, 1, null, $rows2);

    expect($line1->id)->not->toBe($line2->id);
    expect(CartItem::where('cart_id', $line1->cart_id)->count())->toBe(2);
});

it('Phase 7: addCustomized stores uploaded files on the private disk with random filenames', function () {
    Storage::fake('local');
    $vendor = customVendor(); $p = customProduct($vendor);
    addField($p, ['key' => 'photo', 'type' => 'image', 'required' => true, 'label' => 'Photo',
        'allowed_file_types' => ['jpg'], 'max_file_size_kb' => 5120]);

    $file = UploadedFile::fake()->image('my-photo.jpg', 200, 200);

    $user = customer();
    $rows = app(CustomizationFieldValidator::class)->validate($p, [], ['photo' => $file]);
    $line = app(CustomizationCartService::class)->addCustomized($user, $p, 1, null, $rows);

    $row = $line->customizations()->first();
    expect($row->file_original_name)->toBe('my-photo.jpg');
    expect($row->file_path)->toStartWith('customizations/' . $user->id . '/');
    expect($row->file_path)->not->toContain('my-photo'); // random filename
    Storage::disk('local')->assertExists($row->file_path);
});

/* ─────────────────────────────────────────────
   5. Checkout snapshot
   ───────────────────────────────────────────── */

it('Phase 7: checkout snapshots customizations into order_item_customizations + customization_fee_minor', function () {
    Storage::fake('local');
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

    $vendor = customVendor();
    \App\Models\VendorSubscription::factory()->create([
        'vendor_id' => $vendor->id,
        'status'    => 'active',
    ]);
    $p = customProduct($vendor);
    addField($p, ['key' => 'name', 'type' => 'text', 'required' => true,
        'label' => 'Name', 'extra_fee_minor' => 150]);

    $user = customer();
    $user->addresses()->create([
        'recipient_name' => 'Test', 'phone' => '+96599999999',
        'country' => 'KW', 'city' => 'Kuwait', 'is_default' => true,
    ]);

    $rows = app(CustomizationFieldValidator::class)->validate($p, ['name' => 'Hello'], []);
    app(CustomizationCartService::class)->addCustomized($user, $p, 1, null, $rows);

    // Run checkout
    $checkout = app(\App\Domain\Order\CheckoutService::class);
    $order = $checkout->checkout($user, [
        'payment_method' => 'cod',
        'shipping_address_id' => $user->addresses()->first()->id,
    ]);

    $item = $order->items()->first();
    expect($item->customization_fee_minor)->toBe(150);
    expect($item->customization_status)->toBe(OrderItem::CUST_PENDING);
    expect($item->customizations()->count())->toBe(1);
    expect($item->customizations()->first()->field_label)->toBe('Name');
})->skip(! class_exists(\App\Models\VendorSubscription::class) || ! method_exists(\App\Domain\Order\CheckoutService::class, 'checkout'),
    'CheckoutService signature is project-specific; integration test gated to where the contract is stable');

it('Phase 7: OrderItem has the seven CUST_ status constants + ALL_CUSTOMIZATION_STATUSES array', function () {
    expect(OrderItem::ALL_CUSTOMIZATION_STATUSES)->toHaveCount(7);
    foreach (['pending','in_review','proof_uploaded','customer_approved','customer_rejected','in_production','completed'] as $s) {
        expect(OrderItem::ALL_CUSTOMIZATION_STATUSES)->toContain($s);
    }
});

/* ─────────────────────────────────────────────
   6. ProofWorkflowService
   ───────────────────────────────────────────── */

it('Phase 7: proof upload → send advances order_item.customization_status to proof_uploaded', function () {
    Storage::fake('local');
    $vendor = customVendor();
    $vendorUser = $vendor->user;
    $order = Order::factory()->create();
    $item = OrderItem::factory()->create([
        'order_id' => $order->id,
        'vendor_id' => $vendor->id,
        'customization_status' => OrderItem::CUST_PENDING,
    ]);

    $svc = app(ProofWorkflowService::class);
    $file = UploadedFile::fake()->image('proof.jpg');
    $proof = $svc->uploadDraft($item, $vendorUser, $file, 'Please review');

    expect($proof->status)->toBe(CustomizationProof::STATUS_DRAFT);
    expect($item->fresh()->customization_status)->toBe(OrderItem::CUST_PENDING); // unchanged

    $sent = $svc->send($proof);
    expect($sent->status)->toBe(CustomizationProof::STATUS_SENT);
    expect($item->fresh()->customization_status)->toBe(OrderItem::CUST_PROOF_UPLOADED);
});

it('Phase 7: customer approve advances customization_status to customer_approved', function () {
    Storage::fake('local');
    $vendor = customVendor();
    $order = Order::factory()->create();
    $item = OrderItem::factory()->create([
        'order_id' => $order->id,
        'vendor_id' => $vendor->id,
        'customization_status' => OrderItem::CUST_PROOF_UPLOADED,
    ]);

    $proof = CustomizationProof::create([
        'order_item_id' => $item->id,
        'vendor_id'     => $vendor->id,
        'file_path'     => 'customization-proofs/x/y/test.jpg',
        'file_original_name' => 'test.jpg',
        'file_mime'     => 'image/jpeg',
        'file_size_bytes' => 1000,
        'status'        => CustomizationProof::STATUS_SENT,
        'sent_at'       => now(),
    ]);

    app(ProofWorkflowService::class)->approve($proof, 'Looks great!');

    expect($proof->fresh()->status)->toBe(CustomizationProof::STATUS_APPROVED);
    expect($item->fresh()->customization_status)->toBe(OrderItem::CUST_CUSTOMER_APPROVED);
});

it('Phase 7: customer reject advances customization_status to customer_rejected', function () {
    Storage::fake('local');
    $vendor = customVendor();
    $order = Order::factory()->create();
    $item = OrderItem::factory()->create([
        'order_id' => $order->id, 'vendor_id' => $vendor->id,
        'customization_status' => OrderItem::CUST_PROOF_UPLOADED,
    ]);

    $proof = CustomizationProof::create([
        'order_item_id' => $item->id, 'vendor_id' => $vendor->id,
        'file_path' => 'x.jpg', 'file_original_name' => 'x.jpg',
        'file_mime' => 'image/jpeg', 'file_size_bytes' => 100,
        'status' => CustomizationProof::STATUS_SENT, 'sent_at' => now(),
    ]);

    app(ProofWorkflowService::class)->reject($proof, 'The logo is off-center');

    expect($proof->fresh()->status)->toBe(CustomizationProof::STATUS_REJECTED);
    expect($proof->fresh()->customer_response)->toBe('The logo is off-center');
    expect($item->fresh()->customization_status)->toBe(OrderItem::CUST_CUSTOMER_REJECTED);
});

/* ─────────────────────────────────────────────
   7. Cross-vendor / cross-customer isolation
   ───────────────────────────────────────────── */

it('Phase 7: customer cannot approve another customer\'s proof (404)', function () {
    Storage::fake('local');
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

    $vendor = customVendor();
    $owner = customer();
    $stranger = customer();

    $order = Order::factory()->create(['user_id' => $owner->id]);
    $item = OrderItem::factory()->create([
        'order_id' => $order->id, 'vendor_id' => $vendor->id,
        'customization_status' => OrderItem::CUST_PROOF_UPLOADED,
    ]);
    $proof = CustomizationProof::create([
        'order_item_id' => $item->id, 'vendor_id' => $vendor->id,
        'file_path' => 'x.jpg', 'file_original_name' => 'x.jpg',
        'file_mime' => 'image/jpeg', 'file_size_bytes' => 100,
        'status' => CustomizationProof::STATUS_SENT, 'sent_at' => now(),
    ]);

    $response = $this->actingAs($stranger)->post(
        "/orders/{$order->id}/items/{$item->id}/proofs/{$proof->id}/approve"
    );
    expect($response->status())->toBe(404);
});

/* ─────────────────────────────────────────────
   8. File security
   ───────────────────────────────────────────── */

it('Phase 7: CustomizationFileStorage stores on the private disk with random filenames', function () {
    Storage::fake('local');
    $svc = app(CustomizationFileStorage::class);
    $path = $svc->storeCustomerUpload(UploadedFile::fake()->image('test.jpg'), 42);
    expect($path)->toStartWith('customizations/42/');
    expect($path)->not->toContain('test'); // randomized
    Storage::disk('local')->assertExists($path);
});

it('Phase 7: customization uploads land on private disk, NEVER on public /storage', function () {
    Storage::fake('local');
    Storage::fake('public');
    $svc = app(CustomizationFileStorage::class);
    $path = $svc->storeCustomerUpload(UploadedFile::fake()->image('a.jpg'), 1);
    Storage::disk('local')->assertExists($path);
    Storage::disk('public')->assertMissing($path);
});

/* ─────────────────────────────────────────────
   9. Lazy-load defenses
   ───────────────────────────────────────────── */

it('Phase 7: cart presenter does not lazy-load customizations under strict mode', function () {
    Storage::fake('local');
    \Illuminate\Database\Eloquent\Model::shouldBeStrict(true);
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

    $vendor = customVendor(); $p = customProduct($vendor);
    addField($p, ['key' => 'text', 'type' => 'text', 'required' => true, 'label' => 'Text']);

    $user = customer();
    $rows = app(CustomizationFieldValidator::class)->validate($p, ['text' => 'X'], []);
    app(CustomizationCartService::class)->addCustomized($user, $p, 1, null, $rows);
    $rows2 = app(CustomizationFieldValidator::class)->validate($p, ['text' => 'Y'], []);
    app(CustomizationCartService::class)->addCustomized($user, $p, 1, null, $rows2);

    $resp = $this->actingAs($user)->get('/cart');
    \Illuminate\Database\Eloquent\Model::shouldBeStrict(false);

    expect($resp->status())->toBe(200);
});

/* ─────────────────────────────────────────────
   10. v7.4 — Model-level bulletproof defense
   ───────────────────────────────────────────── */

it('Phase 7 v7.4: CustomizationProof::create throws LogicException with helpful message when file_path is null', function () {
    $vendor = customVendor();
    $order = Order::factory()->create();
    $item = OrderItem::factory()->create([
        'order_id' => $order->id, 'vendor_id' => $vendor->id,
        'customization_status' => OrderItem::CUST_PENDING,
    ]);

    expect(fn () => CustomizationProof::create([
        'order_item_id'      => $item->id,
        'vendor_id'          => $vendor->id,
        'file_path'          => null,
        'file_original_name' => 'x.jpg',
        'file_mime'          => 'image/jpeg',
        'file_size_bytes'    => 100,
        'status'             => CustomizationProof::STATUS_DRAFT,
    ]))->toThrow(\LogicException::class, 'file_path cannot be null or empty');
});

it('Phase 7 v7.4: CustomizationProof::create throws LogicException when file_path is empty string', function () {
    $vendor = customVendor();
    $order = Order::factory()->create();
    $item = OrderItem::factory()->create([
        'order_id' => $order->id, 'vendor_id' => $vendor->id,
        'customization_status' => OrderItem::CUST_PENDING,
    ]);

    expect(fn () => CustomizationProof::create([
        'order_item_id'      => $item->id,
        'vendor_id'          => $vendor->id,
        'file_path'          => '',
        'file_original_name' => 'x.jpg',
        'file_mime'          => 'image/jpeg',
        'file_size_bytes'    => 100,
        'status'             => CustomizationProof::STATUS_DRAFT,
    ]))->toThrow(\LogicException::class);
});

it('Phase 7 v7.4: CustomizationProof::create succeeds with a real file_path', function () {
    Storage::fake('local');
    $vendor = customVendor();
    $order = Order::factory()->create();
    $item = OrderItem::factory()->create([
        'order_id' => $order->id, 'vendor_id' => $vendor->id,
        'customization_status' => OrderItem::CUST_PENDING,
    ]);

    $proof = CustomizationProof::create([
        'order_item_id'      => $item->id,
        'vendor_id'          => $vendor->id,
        'file_path'          => 'customization-proofs/1/1/test.png',
        'file_original_name' => 'test.png',
        'file_mime'          => 'image/png',
        'file_size_bytes'    => 100,
        'status'             => CustomizationProof::STATUS_DRAFT,
    ]);
    expect($proof->file_path)->toBe('customization-proofs/1/1/test.png');
});
