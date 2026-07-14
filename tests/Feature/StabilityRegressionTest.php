<?php

declare(strict_types=1);

/**
 * Phase 4 v5.6 — stability regressions.
 *
 * Covers the four issues the developer hit after v5.5:
 *   - lazy-loading violations on Order->items (Filament + controllers)
 *   - Order detail / confirm pages must not trigger preventLazyLoading()
 *   - Storage::disk('public') uploads must be world-readable (0644)
 *   - SharedProps satisfies Inertia v2's PageProps constraint
 */

use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\PaymentMethodsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\VendorPackagesSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(VendorPackagesSeeder::class);
    $this->seed(PaymentMethodsSeeder::class);
});

/**
 * Each test in this file runs with strict mode on, mirroring AppServiceProvider's
 * `Model::shouldBeStrict(! app()->isProduction())` in non-production. This is the
 * exact condition that surfaced the dev's lazy-loading bug.
 */
function withStrictModels(callable $fn): mixed
{
    Model::shouldBeStrict(true);
    try {
        return $fn();
    } finally {
        Model::shouldBeStrict(false);
    }
}

/* ─────────── Lazy-loading guard — controllers ─────────── */

it('v5.6: GET /orders/{id}/confirm does not lazy-load any relation', function () {
    $customer = User::factory()->create();
    $customer->assignRole('customer');
    Address::factory()->for($customer)->default()->create(['country' => 'KW', 'city' => 'Kuwait City']);

    $vendorUser = User::factory()->create();
    $vendorUser->assignRole('vendor');
    $vendor = Vendor::factory()->approved()->for($vendorUser)->create();
    $product = Product::factory()->published()->create([
        'vendor_id' => $vendor->id, 'stock' => 5, 'price_minor' => 5000,
    ]);

    actingAs($customer)->post('/cart/items', ['product_id' => $product->id, 'quantity' => 1]);
    actingAs($customer)->post('/checkout', [
        'shipping_address_id' => $customer->addresses()->first()->id,
        'payment_method_slug' => 'cod',
    ])->assertRedirect();

    $order = $customer->orders()->latest()->first();

    // Strict mode is what trips lazy loading. The route MUST not lazy-load.
    withStrictModels(function () use ($customer, $order) {
        $response = actingAs($customer)->get("/orders/{$order->id}/confirm");
        expect($response->status())
            ->not->toBe(500, 'Lazy loading guard fired — eager-load the missing relation')
            ->toBe(200);
    });
});

it('v5.6: GET /orders/{id} does not lazy-load any relation', function () {
    $customer = User::factory()->create();
    $customer->assignRole('customer');
    $order = Order::factory()->paid()->for($customer)->create();
    \App\Models\OrderItem::factory()->for($order)->create();

    withStrictModels(function () use ($customer, $order) {
        actingAs($customer)->get("/orders/{$order->id}")->assertSuccessful();
    });
});

it('v5.6: GET /vendor/orders/{id} does not lazy-load any relation', function () {
    $vendorUser = User::factory()->create();
    $vendorUser->assignRole('vendor');
    $vendor = Vendor::factory()->approved()->for($vendorUser)->create();

    $order = Order::factory()->paid()->create();
    \App\Models\OrderItem::factory()->for($order)->state(['vendor_id' => $vendor->id])->create();

    withStrictModels(function () use ($vendorUser, $order) {
        actingAs($vendorUser)->get("/vendor/orders/{$order->id}")->assertSuccessful();
    });
});

it('v5.6: order cancel does not lazy-load items.product/items.variant for restock', function () {
    $customer = User::factory()->create();
    $customer->assignRole('customer');
    $vendorUser = User::factory()->create();
    $vendorUser->assignRole('vendor');
    $vendor = Vendor::factory()->approved()->for($vendorUser)->create();
    $product = Product::factory()->published()->create([
        'vendor_id' => $vendor->id, 'stock' => 5, 'price_minor' => 5000, 'track_stock' => true,
    ]);

    $order = Order::factory()->paid()->for($customer)->create();
    \App\Models\OrderItem::factory()->for($order)->state([
        'product_id' => $product->id, 'vendor_id' => $vendor->id, 'quantity' => 2,
    ])->create();

    withStrictModels(function () use ($customer, $order, $product) {
        actingAs($customer)->post("/orders/{$order->id}/cancel", ['reason' => 'test'])
            ->assertRedirect();
        expect($order->fresh()->status)->toBe(Order::STATUS_CANCELLED);
        expect($product->fresh()->stock)->toBe(7);  // restocked
    });
});

/* ─────────── Lazy-loading guard — Filament admin ─────────── */

it('v5.6: OrderResource query eager-loads items for the admin table', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $order = Order::factory()->paid()->create();
    \App\Models\OrderItem::factory()->for($order)->count(2)->create();

    // The default Filament query must already include 'items' or the table
    // column $record->items->sum('quantity') would lazy-load.
    $query = \App\Filament\Resources\OrderResource::getEloquentQuery();
    $eagerLoads = array_keys($query->getEagerLoads());

    expect($eagerLoads)->toContain('items', 'shippingAddress', 'payments');

    // Walk the records under strict mode and access ->items — must not throw
    withStrictModels(function () use ($query) {
        $records = $query->limit(5)->get();
        foreach ($records as $rec) {
            // This is exactly what the OrderResource table column does
            expect($rec->items->sum('quantity'))->toBeGreaterThanOrEqual(0);
        }
    });
});

/* ─────────── Storage permissions — files must be world-readable ─────────── */

it('v5.6: public disk uploads land with world-readable file permissions', function () {
    // Use the real local-driver Storage (not faked) so we can assert actual file mode.
    config(['filesystems.disks.public.root' => sys_get_temp_dir() . '/v5.6-storage-test-' . uniqid()]);
    config(['marketplace.media_disk' => 'public']);

    $file = UploadedFile::fake()->image('t.jpg', 200, 200);
    $path = $file->store('products/admin', 'public');

    $absolute = Storage::disk('public')->path($path);
    expect(file_exists($absolute))->toBeTrue();

    // 0644 = world-readable. The 4th octal digit (others) must include read (4).
    $mode = fileperms($absolute) & 0777;
    $othersBits = $mode & 0007;
    expect($othersBits & 4)
        ->toBeGreaterThan(0, sprintf(
            'Uploaded file has mode 0%o — not readable by the web server user. '
            . 'Check config/filesystems.php "permissions" block.',
            $mode
        ));

    // Clean up
    unlink($absolute);
});

it('v5.6: public disk URL generation returns an absolute /storage/... path', function () {
    config(['marketplace.media_disk' => 'public']);

    Storage::fake('public');
    $path = UploadedFile::fake()->image('a.webp')->store('products/x/1', 'public');

    $image = new \App\Models\ProductImage(['path' => $path]);
    expect($image->url)
        ->toBeString()
        ->toContain('/storage/')
        ->toContain($path);
});

/* ─────────── Inertia v2 PageProps constraint ─────────── */

it('v5.6: SharedProps type has the index signature usePage<T extends PageProps> needs', function () {
    // The TS type file declares `[key: string]: unknown` on SharedProps.
    // This test pins it via a textual assertion so a future PR that removes
    // the index signature breaks CI (and the TypeScript build).
    $src = file_get_contents(base_path('resources/js/types/inertia.d.ts'));
    expect($src)->toContain('[key: string]: unknown');
    expect($src)->toContain('export interface SharedProps');
});

it('v5.6: Checkout/Show.tsx uses SharedProps for usePage typing (no inline object)', function () {
    $src = file_get_contents(base_path('resources/js/Pages/Checkout/Show.tsx'));
    expect($src)->toContain(
        'usePage<SharedProps>()',
        'Checkout/Show.tsx must call usePage<SharedProps>() — an inline object type '
        . 'does not satisfy Inertia v2 PageProps constraint.'
    );
});

/* ─────────── tsconfig sanity ─────────── */

it('v5.6: tsconfig does not set the invalid ignoreDeprecations value', function () {
    $src = file_get_contents(base_path('tsconfig.json'));
    // Either removed entirely (preferred) or set to a valid TS 5.x value
    if (str_contains($src, 'ignoreDeprecations')) {
        $allowed = ['"5.0"'];
        $hasValid = false;
        foreach ($allowed as $v) {
            if (preg_match('/"ignoreDeprecations"\s*:\s*' . preg_quote($v, '/') . '/', $src)) {
                $hasValid = true; break;
            }
        }
        expect($hasValid)->toBeTrue('ignoreDeprecations must be unset or set to "5.0"');
    }
    // The strict checks must NOT be silently turned off
    expect($src)->toContain('"strict": true');
    expect($src)->toContain('"noUnusedLocals": true');
    expect($src)->toContain('"noUnusedParameters": true');
});
