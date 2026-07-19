<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorCommissionRule;
use App\Models\VendorPackage;
use App\Models\VendorSubscription;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Phase 4 v5.3 — demo data so `php artisan migrate:fresh --seed` produces
 * a ready-to-test environment.
 *
 * Creates:
 *   - An approved vendor profile + active Basic subscription for vendor@marketplace.test
 *   - 3 published products with stock (so cart works immediately)
 *   - 1 draft product + 1 pending-review product (to demonstrate the workflow)
 *   - A pending-vendor user + a rejected-vendor user (different emails)
 *   - A default Phase 1 address for customer@marketplace.test
 *
 * IMPORTANT: this seeder is skipped under `testing` env so PHPUnit tests
 * that call `$this->seed(SomeSeeder::class)` aren't surprised by demo rows.
 * It only fires when the developer runs `php artisan migrate:fresh --seed`
 * locally (or in CI's seed step).
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // Phase 9 v9.4 — scoped opt-in for tests.
        //
        // The pre-v9.4 guard was `if (app()->environment('testing')) return;`
        // which forced DemoSeederTest to mutate the WHOLE app env to 'local'
        // for the duration of each test. That re-enabled CSRF middleware
        // (since 'local' is not 'testing'), and subsequent HTTP requests
        // to /cart/items + /checkout returned 419.
        //
        // v9.4 introduces a scoped config flag. The seeder still skips by
        // default under testing, but tests can opt-in narrowly:
        //
        //   config(['marketplace.allow_demo_seeder_in_testing' => true]);
        //   $this->seed(DemoSeeder::class);
        //   config(['marketplace.allow_demo_seeder_in_testing' => false]);
        //
        // No env mutation, no CSRF side-effect on other tests.
        if (app()->environment('testing') && ! config('marketplace.allow_demo_seeder_in_testing', false)) {
            $this->command?->info('DemoSeeder skipped under testing env (set marketplace.allow_demo_seeder_in_testing to opt in).');
            return;
        }
        if (! app()->environment(['local', 'development', 'testing'])) {
            $this->command?->warn('DemoSeeder skipped (only runs in local/development/testing-with-opt-in envs).');
            return;
        }

        // ────────────────────────────────────────────────────────────────
        // Phase 6 v7.1 — APP_KEY pre-seed guard.
        //
        // Phase 6 seeds a SupplierIntegration whose `credentials` column is
        // cast as `encrypted:array`. If APP_KEY is missing, Laravel's
        // encrypter throws Illuminate\Encryption\MissingAppKeyException
        // partway through the seed, leaving the database half-populated
        // and the developer with a cryptic stack trace.
        //
        // We trip a clear, actionable error here BEFORE any data is
        // touched. (Skipped under testing env above — Pest creates an
        // APP_KEY automatically via the test bootstrap; this guard only
        // protects fresh local installs.)
        // ────────────────────────────────────────────────────────────────
        if (blank(config('app.key'))) {
            throw new \RuntimeException(implode("\n", [
                '',
                '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━',
                'APP_KEY is missing. Phase 6 cannot seed encrypted supplier',
                'credentials without it.',
                '',
                'Run these commands exactly, in order:',
                '',
                '  cp .env.example .env',
                '  php artisan key:generate',
                '  php artisan optimize:clear',
                '  php artisan migrate:fresh --seed',
                '',
                'Or run the foolproof guided command (does all of the above):',
                '',
                '  php artisan marketplace:setup-demo',
                '',
                'Note: the command is --seed (no trailing dot).',
                '"--seed." with a dot is rejected by Laravel.',
                '',
                'See PHASE_6_v7.2_PATCH_NOTES.md and TROUBLESHOOTING.md.',
                '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━',
                '',
            ]));
        }

        $this->seedApprovedVendor();
        $this->seedSecondApprovedVendor();  // v5.7: enables multi-vendor checkout demo
        $this->seedPendingVendor();
        $this->seedRejectedVendor();
        $this->seedCustomerAddress();
        $this->seedDemoProducts();
        $this->seedCommissionRule();

        // Phase 5 demo data
        $this->seedShippingZonesAndMethods();
        $this->seedDeliveredOrderAndReview();
        $this->seedActionableOrdersForAdmin(); // v6.3 — orders in paid/confirmed/shipped statuses for admin button testing
        $this->seedWishlist();
        $this->seedDemoPayoutRequest();

        // Phase 6 demo data
        $this->seedSupplierPlatforms();
        $this->seedSupplierIntegrationsAndProducts();
        $this->seedDropshippingOrder();

        // Phase 7 — customizable products + sample customization order + sent proof
        $this->seedCustomizableProductsAndOrder();

        // Phase 8 — services marketplace foundation: 2 services, 2 providers,
        // weekly availability, 1 demo booking. Idempotent via updateOrCreate
        // keyed on real unique indexes (v7.2 lesson).
        $this->seedServicesAndBookings();

        $this->command?->newLine();
        $this->command?->info('═══════════════════════════════════════════════════════════');
        $this->command?->info('Demo data ready. Test accounts:');
        $this->command?->info('  Admin    → admin@marketplace.test / password');
        $this->command?->info('  Staff    → staff@marketplace.test / password');
        $this->command?->info('  Vendor   → vendor@marketplace.test / password   (approved, has products)');
        $this->command?->info('  Vendor 2 → vendor2@marketplace.test / password  (v5.7: second vendor for multi-vendor checkout)');
        $this->command?->info('  Customer → customer@marketplace.test / password (has default address)');
        $this->command?->info('  Pending vendor → pending-vendor@marketplace.test / password');
        $this->command?->info('  Rejected vendor → rejected-vendor@marketplace.test / password');
        $this->command?->info('═══════════════════════════════════════════════════════════');
        $this->command?->info('Phase 5 ready: 1 delivered order + 1 pending review + 2 wishlist items + 1 payout request awaiting admin approval.');
        $this->command?->info('Sign in as customer → visit /wishlist, /orders, /products/{slug} to leave a review.');
        $this->command?->info('Sign in as vendor → visit /vendor/wallet to see balance + request payout, /vendor/reviews to see incoming.');
    }

    /**
     * Approved vendor with active Basic subscription. The vendor user is
     * already created by DatabaseSeeder — we just attach the Vendor profile
     * and subscription if they don't exist yet.
     */
    private function seedApprovedVendor(): void
    {
        $user = User::where('email', 'vendor@marketplace.test')->first();
        if (! $user) {
            $this->command?->warn('vendor@marketplace.test missing — run DatabaseSeeder first.');
            return;
        }

        $basic = VendorPackage::where('slug', 'basic')->first();
        if (! $basic) {
            $this->command?->warn('Basic package not seeded — VendorPackagesSeeder must run first.');
            return;
        }

        /** @var Vendor $vendor */
        $vendor = Vendor::firstOrCreate(
            ['user_id' => $user->id],
            [
                'business_name'  => 'Demo Trading Co.',
                'slug'           => 'demo-trading-co',
                'business_email' => 'shop@demo-trading.test',
                'business_phone' => '+96522334455',
                'business_type'  => 'company',
                'description'    => 'A demo vendor used to exercise the marketplace workflows end-to-end.',
                'owner_name'     => $user->name,
                'country'        => 'KW',
                'city'           => 'Kuwait City',
                'status'         => Vendor::STATUS_APPROVED,
                'approved_at'    => now(),
            ],
        );

        // Active subscription so `currentPackage()` resolves and commission
        // falls back to package default in CheckoutService.
        VendorSubscription::firstOrCreate(
            ['vendor_id' => $vendor->id, 'status' => VendorSubscription::STATUS_ACTIVE],
            [
                'vendor_package_id' => $basic->id,
                'starts_at'         => now()->subDays(30),
            ],
        );
    }

    /**
     * v5.7 — second approved vendor with one published product. Enables the
     * developer to test multi-VENDOR checkout (two products from different
     * vendors in the same cart → order_items split correctly + each vendor
     * sees only their own line in /vendor/orders).
     */
    private function seedSecondApprovedVendor(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'vendor2@marketplace.test'],
            [
                'name'              => 'Coastal Goods',
                'password'          => Hash::make('password'),
                'email_verified_at' => now(),
                'status'            => 'active',
            ],
        );
        if (! $user->hasRole('vendor')) {
            $user->assignRole('vendor');
        }

        $basic = VendorPackage::where('slug', 'basic')->first();
        if (! $basic) {
            return;
        }

        /** @var Vendor $vendor */
        $vendor = Vendor::firstOrCreate(
            ['user_id' => $user->id],
            [
                'business_name'  => 'Coastal Goods',
                'slug'           => 'coastal-goods',
                'business_email' => 'shop@coastal-goods.test',
                'business_phone' => '+96522567890',
                'business_type'  => 'individual',
                'description'    => 'Second demo vendor for multi-vendor checkout testing.',
                'owner_name'     => $user->name,
                'country'        => 'KW',
                'city'           => 'Salmiya',
                'status'         => Vendor::STATUS_APPROVED,
                'approved_at'    => now(),
            ],
        );

        VendorSubscription::firstOrCreate(
            ['vendor_id' => $vendor->id, 'status' => VendorSubscription::STATUS_ACTIVE],
            [
                'vendor_package_id' => $basic->id,
                'starts_at'         => now()->subDays(15),
            ],
        );
    }

    private function seedPendingVendor(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'pending-vendor@marketplace.test'],
            [
                'name'              => 'Pending Vendor',
                'password'          => Hash::make('password'),
                'email_verified_at' => now(),
                'status'            => 'active',
            ],
        );
        if (! $user->hasRole('vendor')) {
            $user->assignRole('vendor');
        }

        Vendor::firstOrCreate(
            ['user_id' => $user->id],
            [
                'business_name'  => 'Awaiting Review Shop',
                'slug'           => 'awaiting-review-shop',
                'business_email' => 'shop@awaiting-review.test',
                'business_phone' => '+96523344556',
                'business_type'  => 'individual',
                'country'        => 'KW',
                'city'           => 'Salmiya',
                'status'         => Vendor::STATUS_PENDING,
            ],
        );
    }

    private function seedRejectedVendor(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'rejected-vendor@marketplace.test'],
            [
                'name'              => 'Rejected Vendor',
                'password'          => Hash::make('password'),
                'email_verified_at' => now(),
                'status'            => 'active',
            ],
        );
        if (! $user->hasRole('vendor')) {
            $user->assignRole('vendor');
        }

        Vendor::firstOrCreate(
            ['user_id' => $user->id],
            [
                'business_name'    => 'Rejected Demo',
                'slug'             => 'rejected-demo',
                'business_email'   => 'shop@rejected-demo.test',
                'business_phone'   => '+96524455667',
                'business_type'    => 'individual',
                'country'          => 'KW',
                'city'             => 'Hawalli',
                'status'           => Vendor::STATUS_REJECTED,
                'rejection_reason' => 'Demo rejection — used to test the rejected vendor flow.',
            ],
        );
    }

    /**
     * Default Phase 1 address for the demo customer. Schema mirrors the
     * `addresses` table (Gulf-style fields).
     */
    private function seedCustomerAddress(): void
    {
        $customer = User::where('email', 'customer@marketplace.test')->first();
        if (! $customer) return;

        Address::firstOrCreate(
            ['user_id' => $customer->id, 'label' => 'Home'],
            [
                'type'        => 'shipping',
                'country'     => 'KW',
                'state'       => 'Al Asimah',
                'city'        => 'Kuwait City',
                'area'        => 'Salmiya',
                'block'       => '7',
                'street'      => 'Beach Road',
                'building'    => '15',
                'floor'       => '3',
                'apartment'   => '4',
                'postal_code' => '13001',
                'phone'       => '+96599887766',
                'is_default'  => true,
            ],
        );
    }

    /**
     * 3 published products with stock + 1 draft + 1 pending. The published
     * ones are cart-ready so the customer can add to cart and check out
     * immediately after seeding.
     */
    private function seedDemoProducts(): void
    {
        $vendor = User::where('email', 'vendor@marketplace.test')->first()?->vendor;
        if (! $vendor) return;

        $electronics = Category::where('slug', 'electronics')->first();
        $fashion     = Category::where('slug', 'fashion')->first();

        $published = [
            [
                'name'        => 'Wireless Bluetooth Headphones',
                'sku'         => 'DEMO-HEAD-001',
                'category_id' => $electronics?->id,
                'price_minor' => 12500,    // 12.500 KWD
                'stock'       => 25,
                'short_description' => 'Crisp audio, 20-hour battery, comfortable over-ear fit.',
                'featured'    => true,
            ],
            [
                'name'        => 'Cotton T-Shirt — Classic Fit',
                'sku'         => 'DEMO-TSHIRT-001',
                'category_id' => $fashion?->id,
                'price_minor' => 3500,     // 3.500 KWD
                'stock'       => 80,
                'short_description' => '100% combed cotton, pre-shrunk, sizes S to XXL.',
                'featured'    => false,
            ],
            [
                'name'        => 'Stainless Steel Water Bottle',
                'sku'         => 'DEMO-BOTTLE-001',
                'category_id' => null,
                'price_minor' => 4750,     // 4.750 KWD
                'stock'       => 50,
                'short_description' => 'Double-walled, keeps drinks cold 24 hours.',
                'featured'    => true,
            ],
        ];

        $palette = ['#6366f1', '#0ea5e9', '#10b981'];
        foreach ($published as $i => $data) {
            $slug = Str::slug($data['name']);
            /** @var Product $product */
            $product = Product::firstOrCreate(
                ['slug' => $slug],
                array_merge($data, [
                    'vendor_id'    => $vendor->id,
                    'type'         => Product::TYPE_SIMPLE,
                    'status'       => Product::STATUS_PUBLISHED,
                    'currency'     => 'KWD',
                    'track_stock'  => true,
                    'approved_at'  => now(),
                    'published_at' => now(),
                ]),
            );

            // Always ensure the demo file exists. A server deploy can keep the
            // database rows while losing storage/app/public, which otherwise
            // leaves valid product_images.path values pointing at missing files.
            $color = $palette[$i % count($palette)];
            $disk = config('marketplace.media_disk', 'public');
            $path = "products/demo/{$slug}.svg";

            if (! Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->put($path, $this->placeholderSvg($data['name'], $color));
            }

            $product->images()->firstOrCreate(
                ['path' => $path],
                [
                    'alt_text'   => $data['name'],
                    'position'   => 1,
                    'is_primary' => ! $product->images()->where('is_primary', true)->exists(),
                ],
            );
        }

        // 1 draft (vendor still editing)
        Product::firstOrCreate(
            ['slug' => 'demo-draft-product'],
            [
                'vendor_id'    => $vendor->id,
                'name'         => 'Draft Product (vendor still editing)',
                'sku'          => 'DEMO-DRAFT-001',
                'type'         => Product::TYPE_SIMPLE,
                'status'       => Product::STATUS_DRAFT,
                'price_minor'  => 0,
                'currency'     => 'KWD',
                'track_stock'  => true,
                'stock'        => 0,
            ],
        );

        // 1 pending review (vendor submitted, admin hasn't approved)
        Product::firstOrCreate(
            ['slug' => 'demo-pending-review-product'],
            [
                'vendor_id'    => $vendor->id,
                'name'         => 'Pending Review Product',
                'sku'          => 'DEMO-PENDING-001',
                'type'         => Product::TYPE_SIMPLE,
                'status'       => Product::STATUS_PENDING_REVIEW,
                'price_minor'  => 2500,
                'currency'     => 'KWD',
                'track_stock'  => true,
                'stock'        => 10,
                'short_description' => 'Awaiting admin approval.',
            ],
        );

        $this->command?->info('Demo products seeded: 3 published (cart-ready, with images) + 1 draft + 1 pending review.');

        // v5.7 — second vendor's product for multi-vendor checkout testing.
        $vendor2 = User::where('email', 'vendor2@marketplace.test')->first()?->vendor;
        if ($vendor2) {
            $product = Product::firstOrCreate(
                ['slug' => 'handwoven-beach-towel'],
                [
                    'vendor_id'   => $vendor2->id,
                    'name'        => 'Handwoven Beach Towel',
                    'sku'         => 'CG-TOWEL-001',
                    'category_id' => $fashion?->id,
                    'type'        => Product::TYPE_SIMPLE,
                    'status'      => Product::STATUS_PUBLISHED,
                    'price_minor' => 6500,   // 6.500 KWD
                    'currency'    => 'KWD',
                    'track_stock' => true,
                    'stock'       => 30,
                    'short_description' => 'Soft cotton, traditional Gulf weave, generously sized.',
                    'featured'    => false,
                    'published_at' => now(),
                ],
            );

            $disk = config('marketplace.media_disk', 'public');
            $path = "products/demo/{$product->slug}.svg";

            if (! Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->put($path, $this->placeholderSvg($product->name, '#f59e0b'));
            }

            $product->images()->firstOrCreate(
                ['path' => $path],
                [
                    'alt_text'   => $product->name,
                    'position'   => 1,
                    'is_primary' => ! $product->images()->where('is_primary', true)->exists(),
                ],
            );

            $this->command?->info('Second demo vendor (Coastal Goods) seeded with 1 published product for multi-vendor checkout testing.');
        }
    }

    /**
     * Generate a simple, self-contained SVG so demo products have a real
     * displayable image without shipping binary asset files. Browsers render
     * SVG via <img src> natively.
     */
    private function placeholderSvg(string $name, string $color): string
    {
        $initial = strtoupper(mb_substr(trim($name), 0, 1));
        $safeName = htmlspecialchars(mb_substr($name, 0, 28), ENT_QUOTES);

        return <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" width="600" height="600" viewBox="0 0 600 600">
          <rect width="600" height="600" fill="{$color}"/>
          <circle cx="300" cy="250" r="120" fill="rgba(255,255,255,0.18)"/>
          <text x="300" y="300" font-family="system-ui, sans-serif" font-size="160" fill="#ffffff" text-anchor="middle" font-weight="700">{$initial}</text>
          <text x="300" y="430" font-family="system-ui, sans-serif" font-size="30" fill="rgba(255,255,255,0.9)" text-anchor="middle">{$safeName}</text>
        </svg>
        SVG;
    }

    /**
     * A default commission rule for the demo vendor (vendor-level, applies to
     * all their products) so the admin commission UI has data and CheckoutService
     * has an explicit rule to resolve. 20% beats the package default of 30%.
     */
    private function seedCommissionRule(): void
    {
        $vendor = User::where('email', 'vendor@marketplace.test')->first()?->vendor;
        if (! $vendor) {
            return;
        }

        VendorCommissionRule::firstOrCreate(
            ['vendor_id' => $vendor->id, 'scope' => 'vendor', 'scope_id' => null, 'product_type' => 'any'],
            [
                'payment_method'    => 'any',
                'calculation_base'  => 'selling_price',
                'commission_type'   => 'percent',
                'percent_value'     => 20.0000,
                'fixed_value_minor' => null,
                'currency'          => 'KWD',
                'priority'          => 10,
                'is_active'         => true,
            ],
        );

        $this->command?->info('Demo commission rule seeded: vendor-level 20%.');
    }

    /**
     * Phase 5 — shipping zones + methods.
     * Two zones (Kuwait domestic + GCC) with three methods total.
     */
    private function seedShippingZonesAndMethods(): void
    {
        $kuwait = \App\Models\ShippingZone::firstOrCreate(
            ['slug' => 'kuwait-domestic'],
            [
                'name'        => 'Kuwait Domestic',
                'countries'   => ['KW'],
                'regions'     => null,
                'is_active'   => true,
                'position'    => 1,
                'description' => 'Same-country delivery anywhere in Kuwait.',
            ],
        );

        \App\Models\ShippingMethod::firstOrCreate(
            ['shipping_zone_id' => $kuwait->id, 'slug' => 'flat-rate-kuwait'],
            [
                'name'      => 'Standard delivery',
                'type'      => \App\Models\ShippingMethod::TYPE_FLAT_RATE,
                'fee_minor' => 1500, // 1.500 KWD
                'currency'  => 'KWD',
                'eta_label' => '1-3 business days',
                'is_active' => true,
                'position'  => 1,
            ],
        );

        \App\Models\ShippingMethod::firstOrCreate(
            ['shipping_zone_id' => $kuwait->id, 'slug' => 'free-over-30-kwd'],
            [
                'name'               => 'Free shipping (orders ≥ 30 KWD)',
                'type'               => \App\Models\ShippingMethod::TYPE_FREE,
                'fee_minor'          => 0,
                'currency'           => 'KWD',
                'min_subtotal_minor' => 30000,
                'eta_label'          => '2-4 business days',
                'is_active'          => true,
                'position'           => 2,
            ],
        );

        \App\Models\ShippingMethod::firstOrCreate(
            ['shipping_zone_id' => $kuwait->id, 'slug' => 'pickup-kuwait-city'],
            [
                'name'      => 'Vendor pickup (Kuwait City)',
                'type'      => \App\Models\ShippingMethod::TYPE_PICKUP,
                'fee_minor' => 0,
                'currency'  => 'KWD',
                'eta_label' => 'Ready next business day',
                'is_active' => true,
                'position'  => 3,
            ],
        );

        $gcc = \App\Models\ShippingZone::firstOrCreate(
            ['slug' => 'gcc-cross-border'],
            [
                'name'        => 'GCC cross-border',
                'countries'   => ['AE', 'SA', 'BH', 'QA', 'OM'],
                'is_active'   => true,
                'position'    => 2,
                'description' => 'Delivery to UAE, Saudi Arabia, Bahrain, Qatar, Oman.',
            ],
        );

        \App\Models\ShippingMethod::firstOrCreate(
            ['shipping_zone_id' => $gcc->id, 'slug' => 'flat-rate-gcc'],
            [
                'name'      => 'GCC courier',
                'type'      => \App\Models\ShippingMethod::TYPE_FLAT_RATE,
                'fee_minor' => 5000, // 5.000 KWD
                'currency'  => 'KWD',
                'eta_label' => '3-7 business days',
                'is_active' => true,
                'position'  => 1,
            ],
        );

        $this->command?->info('Phase 5: 2 shipping zones (Kuwait Domestic + GCC) with 4 methods seeded.');
    }

    /**
     * Phase 5 — a completed/delivered order so the customer can leave reviews.
     * Creates 1 order with 1 line item (the cheapest demo product), payment
     * status paid, fulfillment delivered, earnings_release_at in the past.
     * Plus 1 pending review awaiting admin moderation.
     */
    /**
     * Phase 5 v6.2 — seed THREE delivered orders for the demo customer so the
     * vendor wallet has a meaningful available balance for payout testing.
     *
     * Why three? The wallet math is:
     *   available = released − reserved_for_payout − paid_out
     *
     * `released` requires payment_status=paid AND delivered_at IS NOT NULL
     * AND earnings_release_at <= now(). All three demo orders fit that.
     *
     * A separate `seedDemoPayoutRequest` then reserves ~5 KWD, so the demo
     * vendor still sees positive `available` and the payout form is testable.
     *
     * Also seeds one approved verified-purchase review so the product page
     * has a rating to display.
     */
    private function seedDeliveredOrderAndReview(): void
    {
        $customer = User::where('email', 'customer@marketplace.test')->first();
        $vendor   = User::where('email', 'vendor@marketplace.test')->first()?->vendor;
        if (! $customer || ! $vendor) {
            return;
        }

        // Pick the demo vendor's published products. Need at least one.
        $products = $vendor->products()->where('status', 'published')->orderBy('price_minor')->take(3)->get();
        if ($products->isEmpty()) {
            return;
        }

        // Idempotent: skip if we already seeded the demo delivered orders.
        if ($customer->orders()->where('number', 'like', 'DEMO-DELIVERED-%')->count() >= 3) {
            return;
        }

        $address = $customer->addresses()->where('is_default', true)->first();
        if (! $address) {
            return;
        }

        $now = now();

        // Each delivered order: paid, delivered well in the past, earnings
        // already released. We deliberately scale quantities so the vendor
        // earns a comfortable available balance (~40-60 KWD) — plenty for
        // payout testing even after the seeded pending request reserves some.
        $blueprints = [
            ['days_ago' => 25, 'qty' => 2, 'product' => $products[0]],
            ['days_ago' => 18, 'qty' => 3, 'product' => $products[count($products) > 1 ? 1 : 0]],
            ['days_ago' => 12, 'qty' => 1, 'product' => $products[count($products) > 2 ? 2 : 0]],
        ];

        $firstOrder       = null;
        $firstOrderItem   = null;
        $firstProductSeen = null;

        foreach ($blueprints as $idx => $bp) {
            /** @var \App\Models\Product $product */
            $product   = $bp['product'];
            $qty       = $bp['qty'];
            $daysAgo   = $bp['days_ago'];
            $unitPrice = (int) $product->price_minor;
            $lineTotal = $unitPrice * $qty;
            $commission = (int) round($lineTotal * 0.20);
            $vendorEarning = $lineTotal - $commission;

            $order = \App\Models\Order::create([
                'number'                    => 'DEMO-DELIVERED-' . str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT) . '-' . $now->format('YmdHis'),
                'user_id'                   => $customer->id,
                'status'                    => \App\Models\Order::STATUS_DELIVERED,
                'payment_status'            => \App\Models\Order::PAY_PAID,
                'fulfillment_status'        => \App\Models\Order::FUL_FULFILLED,
                'currency'                  => 'KWD',
                'subtotal_minor'            => $lineTotal,
                'shipping_minor'            => 0,
                'tax_minor'                 => 0,
                'discount_minor'            => 0,
                'total_minor'               => $lineTotal,
                'platform_commission_minor' => $commission,
                'vendor_earnings_minor'     => $vendorEarning,
                // Paid → confirmed → shipped → delivered chain ending $daysAgo days back
                'paid_at'                   => $now->copy()->subDays($daysAgo + 5),
                'confirmed_at'              => $now->copy()->subDays($daysAgo + 4),
                'shipped_at'                => $now->copy()->subDays($daysAgo + 2),
                'delivered_at'              => $now->copy()->subDays($daysAgo),
                // Earnings released (i.e. release_at in the past) for all three:
                // 7-day cooling-off period elapsed long ago.
                'earnings_release_at'       => $now->copy()->subDays(max(1, $daysAgo - 7)),
            ]);

            $item = \App\Models\OrderItem::create([
                'order_id'                => $order->id,
                'vendor_id'               => $vendor->id,
                'product_id'              => $product->id,
                'product_name'            => $product->name,
                'product_sku'             => $product->sku,
                'quantity'                => $qty,
                'unit_price_minor'        => $unitPrice,
                'line_total_minor'        => $lineTotal,
                'currency'                => 'KWD',
                'commission_percent'      => 20.00,
                'commission_amount_minor' => $commission,
                'vendor_earning_minor'    => $vendorEarning,
                'fulfillment_status'      => \App\Models\OrderItem::FUL_FULFILLED,
            ]);

            \App\Models\OrderAddress::create([
                'order_id'       => $order->id,
                'type'           => 'shipping',
                'recipient_name' => $customer->name,
                'phone'          => $address->phone,
                'country'        => $address->country,
                'state'          => $address->state,
                'city'           => $address->city,
                'area'           => $address->area,
                'block'          => $address->block,
                'street'         => $address->street,
                'building'       => $address->building,
                'floor'          => $address->floor,
                'apartment'      => $address->apartment,
                'postal_code'    => $address->postal_code,
            ]);

            \App\Models\Payment::create([
                'order_id'     => $order->id,
                'method_slug'  => 'cod',
                'provider'     => 'cod',
                'status'       => 'captured',
                'amount_minor' => $lineTotal,
                'currency'     => 'KWD',
                'reference'    => 'COD-' . $order->number,
                'captured_at'  => $now->copy()->subDays($daysAgo + 5),
            ]);

            if ($firstOrder === null) {
                $firstOrder       = $order;
                $firstOrderItem   = $item;
                $firstProductSeen = $product;
            }
        }

        // 1 approved verified-purchase review on the first product so the
        // product detail page has a visible rating.
        if ($firstProductSeen && $firstOrderItem) {
            \App\Models\ProductReview::firstOrCreate(
                ['product_id' => $firstProductSeen->id, 'user_id' => $customer->id, 'order_item_id' => $firstOrderItem->id],
                [
                    'rating'               => 5,
                    'title'                => 'Excellent quality',
                    'body'                 => 'Arrived quickly and exactly as described. Recommended.',
                    'status'               => \App\Models\ProductReview::STATUS_APPROVED,
                    'is_verified_purchase' => true,
                    'approved_at'          => $now->copy()->subDays(2),
                ],
            );
            // Recompute product rating
            app(\App\Domain\Review\ReviewService::class)->recomputeProductRating($firstProductSeen);
        }

        $this->command?->info('Phase 5 v6.2: 3 delivered demo orders seeded + 1 approved verified-purchase review.');
    }

    /**
     * Phase 5 — pre-seed two wishlist entries for the customer.
     */
    private function seedWishlist(): void
    {
        $customer = User::where('email', 'customer@marketplace.test')->first();
        if (! $customer) {
            return;
        }
        $products = \App\Models\Product::where('status', 'published')->limit(2)->get();
        foreach ($products as $p) {
            \App\Models\Wishlist::firstOrCreate([
                'user_id'    => $customer->id,
                'product_id' => $p->id,
            ]);
        }
        $this->command?->info('Phase 5: customer wishlist seeded with ' . $products->count() . ' product(s).');
    }

    /**
     * Phase 5 — a pending payout request from the demo vendor, sitting in
     * admin's queue.
     */
    private function seedDemoPayoutRequest(): void
    {
        $vendor = User::where('email', 'vendor@marketplace.test')->first()?->vendor;
        if (! $vendor) {
            return;
        }
        // Only seed if vendor has earnings AND no existing pending request
        $available = app(\App\Domain\Payout\VendorWalletService::class)->balanceFor($vendor)['available_balance_minor'];
        if ($available <= 0) {
            return;
        }
        if ($vendor->payoutRequests()->where('status', 'pending')->exists()) {
            return;
        }

        // v6.2 — reserve only a SMALL portion of the available balance so the
        // remaining `available - reserved` is still positive and the vendor
        // payout form is testable on the demo page right out of the box.
        // Previously this reserved up to 5 KWD which sometimes consumed the
        // entire available balance and hid the form.
        $reservation = min(
            (int) floor($available / 4),  // at most 25% of available
            2000,                          // cap at 2 KWD
        );
        $reservation = max(1000, $reservation); // floor at 1 KWD if available > 0

        \App\Models\VendorPayoutRequest::create([
            'vendor_id'              => $vendor->id,
            'requested_amount_minor' => $reservation,
            'currency'               => 'KWD',
            'status'                 => 'pending',
            'payout_method'          => 'bank_transfer',
            'payout_details'         => [
                'iban'                => 'KW00DEMO0000000000000001',
                'bank_name'           => 'National Bank of Kuwait',
                'account_holder_name' => 'Demo Trading Co.',
            ],
            'requested_at'           => now()->subDay(),
        ]);

        $this->command?->info('Phase 5 v6.2: 1 pending payout request seeded ('
            . number_format($reservation / 100, 3) . ' KWD); '
            . number_format(($available - $reservation) / 100, 3) . ' KWD remains available for vendor to test new payout request.');
    }

    /**
     * v6.3 — seed orders in actionable statuses so the admin can immediately
     * test the lifecycle buttons after `migrate:fresh --seed`:
     *
     *   - 1 order in `paid` (admin sees Confirm + Ship + Cancel + Refund)
     *   - 1 order in `confirmed` (admin sees Ship + Cancel + Refund)
     *   - 1 order in `shipped` (admin sees Deliver + Cancel + Refund)
     *   - 1 order with `payment_status=pending` + COD method (admin sees Mark COD paid)
     *
     * Previously the demo seeder created only delivered orders → admin saw
     * NO action buttons on any seeded order because the visibility predicates
     * (status === paid|confirmed|shipped) never matched.
     */
    private function seedActionableOrdersForAdmin(): void
    {
        $customer = User::where('email', 'customer@marketplace.test')->first();
        $vendor   = User::where('email', 'vendor@marketplace.test')->first()?->vendor;
        if (! $customer || ! $vendor) {
            return;
        }
        $product = $vendor->products()->where('status', 'published')->orderBy('price_minor')->first();
        $address = $customer->addresses()->where('is_default', true)->first();
        if (! $product || ! $address) {
            return;
        }

        // Idempotent — don't re-seed
        if ($customer->orders()->where('number', 'like', 'DEMO-ACTIONABLE-%')->exists()) {
            return;
        }

        $now = now();
        $price = (int) $product->price_minor;
        $commission = (int) round($price * 0.20);
        $vendorEarning = $price - $commission;

        $blueprints = [
            [
                'tag'             => 'PAID',
                'status'          => \App\Models\Order::STATUS_PAID,
                'payment_status'  => \App\Models\Order::PAY_PAID,
                'fulfillment'     => \App\Models\Order::FUL_UNFULFILLED,
                'paid_at'         => $now->copy()->subHours(6),
                'confirmed_at'    => null,
                'shipped_at'      => null,
                'delivered_at'    => null,
                'payment_method'  => 'cod',
                'payment_status_row' => 'captured',
            ],
            [
                'tag'             => 'CONFIRMED',
                'status'          => \App\Models\Order::STATUS_CONFIRMED,
                'payment_status'  => \App\Models\Order::PAY_PAID,
                'fulfillment'     => \App\Models\Order::FUL_UNFULFILLED,
                'paid_at'         => $now->copy()->subDays(1),
                'confirmed_at'    => $now->copy()->subHours(12),
                'shipped_at'      => null,
                'delivered_at'    => null,
                'payment_method'  => 'online_mock',
                'payment_status_row' => 'captured',
            ],
            [
                'tag'             => 'SHIPPED',
                'status'          => \App\Models\Order::STATUS_SHIPPED,
                'payment_status'  => \App\Models\Order::PAY_PAID,
                'fulfillment'     => \App\Models\Order::FUL_FULFILLED,
                'paid_at'         => $now->copy()->subDays(2),
                'confirmed_at'    => $now->copy()->subDays(2),
                'shipped_at'      => $now->copy()->subHours(8),
                'delivered_at'    => null,
                'payment_method'  => 'online_mock',
                'payment_status_row' => 'captured',
            ],
            [
                'tag'             => 'COD-PENDING',
                'status'          => \App\Models\Order::STATUS_PENDING_PAYMENT,
                'payment_status'  => \App\Models\Order::PAY_PENDING,
                'fulfillment'     => \App\Models\Order::FUL_UNFULFILLED,
                'paid_at'         => null,
                'confirmed_at'    => null,
                'shipped_at'      => null,
                'delivered_at'    => null,
                'payment_method'  => 'cod',
                'payment_status_row' => 'pending',
            ],
        ];

        foreach ($blueprints as $idx => $bp) {
            $order = \App\Models\Order::create([
                'number'                    => 'DEMO-ACTIONABLE-' . $bp['tag'] . '-' . $now->format('YmdHis'),
                'user_id'                   => $customer->id,
                'status'                    => $bp['status'],
                'payment_status'            => $bp['payment_status'],
                'fulfillment_status'        => $bp['fulfillment'],
                'currency'                  => 'KWD',
                'subtotal_minor'            => $price,
                'shipping_minor'            => 0,
                'tax_minor'                 => 0,
                'discount_minor'            => 0,
                'total_minor'               => $price,
                'platform_commission_minor' => $commission,
                'vendor_earnings_minor'     => $vendorEarning,
                'paid_at'                   => $bp['paid_at'],
                'confirmed_at'              => $bp['confirmed_at'],
                'shipped_at'                => $bp['shipped_at'],
                'delivered_at'              => $bp['delivered_at'],
                'earnings_release_at'       => $bp['delivered_at'] ? $bp['delivered_at']->copy()->addDays(7) : null,
            ]);

            \App\Models\OrderItem::create([
                'order_id'                => $order->id,
                'vendor_id'               => $vendor->id,
                'product_id'              => $product->id,
                'product_name'            => $product->name,
                'product_sku'             => $product->sku,
                'quantity'                => 1,
                'unit_price_minor'        => $price,
                'line_total_minor'        => $price,
                'currency'                => 'KWD',
                'commission_percent'      => 20.00,
                'commission_amount_minor' => $commission,
                'vendor_earning_minor'    => $vendorEarning,
                'fulfillment_status'      => $bp['fulfillment'] === \App\Models\Order::FUL_FULFILLED
                    ? \App\Models\OrderItem::FUL_FULFILLED
                    : \App\Models\OrderItem::FUL_UNFULFILLED,
            ]);

            \App\Models\OrderAddress::create([
                'order_id'       => $order->id,
                'type'           => 'shipping',
                'recipient_name' => $customer->name,
                'phone'          => $address->phone,
                'country'        => $address->country,
                'state'          => $address->state,
                'city'           => $address->city,
                'area'           => $address->area,
                'block'          => $address->block,
                'street'         => $address->street,
                'building'       => $address->building,
                'floor'          => $address->floor,
                'apartment'      => $address->apartment,
                'postal_code'    => $address->postal_code,
            ]);

            \App\Models\Payment::create([
                'order_id'     => $order->id,
                'method_slug'  => $bp['payment_method'],
                'provider'     => $bp['payment_method'] === 'cod' ? 'cod' : 'mock',
                'status'       => $bp['payment_status_row'],
                'amount_minor' => $price,
                'currency'     => 'KWD',
                'reference'    => strtoupper($bp['payment_method']) . '-' . $order->number,
                'captured_at'  => $bp['payment_status_row'] === 'captured' ? $now->copy()->subDays(1) : null,
            ]);
        }

        $this->command?->info('Phase 5 v6.3: 4 actionable demo orders seeded — admin can test '
            . 'Confirm (paid), Ship (confirmed), Deliver (shipped), Mark COD paid (pending COD).');
    }

    /**
     * Phase 6 — seed the supplier platform catalogue.
     *
     * Idempotent: matches by slug. Admin can later add more or toggle is_active.
     */
    private function seedSupplierPlatforms(): void
    {
        $platforms = [
            ['slug' => 'aliexpress',      'name' => 'AliExpress',      'integration_type' => 'manual', 'default_currency' => 'USD', 'default_delivery_days' => 18, 'website_url' => 'https://www.aliexpress.com',  'display_order' => 10],
            ['slug' => 'alibaba',         'name' => 'Alibaba',         'integration_type' => 'manual', 'default_currency' => 'USD', 'default_delivery_days' => 25, 'website_url' => 'https://www.alibaba.com',     'display_order' => 20],
            ['slug' => 'amazon',          'name' => 'Amazon',          'integration_type' => 'manual', 'default_currency' => 'USD', 'default_delivery_days' => 10, 'website_url' => 'https://www.amazon.com',      'display_order' => 30],
            ['slug' => 'temu',            'name' => 'Temu',            'integration_type' => 'manual', 'default_currency' => 'USD', 'default_delivery_days' => 14, 'website_url' => 'https://www.temu.com',        'display_order' => 40],
            ['slug' => 'daraz',           'name' => 'Daraz',           'integration_type' => 'manual', 'default_currency' => 'PKR', 'default_delivery_days' => 7,  'website_url' => 'https://www.daraz.pk',        'display_order' => 50],
            ['slug' => 'local-wholesale', 'name' => 'Local Wholesale', 'integration_type' => 'csv',    'default_currency' => 'KWD', 'default_delivery_days' => 3,  'website_url' => null,                          'display_order' => 60],
            ['slug' => 'private-supplier','name' => 'Private Supplier','integration_type' => 'manual', 'default_currency' => 'KWD', 'default_delivery_days' => 5,  'website_url' => null,                          'display_order' => 70],
        ];

        foreach ($platforms as $p) {
            \App\Models\SupplierPlatform::firstOrCreate(
                ['slug' => $p['slug']],
                array_merge($p, ['is_active' => true]),
            );
        }

        $this->command?->info('Phase 6: ' . count($platforms) . ' supplier platforms seeded.');
    }

    /**
     * Phase 6 — seed an integration for the demo vendor + 3 supplier products
     * (one pending, one mapped+pending-approval, one published — visible on
     * the storefront so customers can place a dropshipping order against it).
     *
     * Idempotent: skip if the demo integration already exists.
     */
    private function seedSupplierIntegrationsAndProducts(): void
    {
        $vendor = \App\Models\User::where('email', 'vendor@marketplace.test')->first()?->vendor;
        $platform = \App\Models\SupplierPlatform::where('slug', 'aliexpress')->first();
        if (! $vendor || ! $platform) {
            return;
        }

        $integration = $vendor->supplierIntegrations()->firstOrCreate(
            ['supplier_platform_id' => $platform->id, 'name' => 'AliExpress demo catalogue'],
            [
                'integration_type' => 'manual',
                'is_active'        => true,
                'credentials'      => ['api_key' => 'demo-AK-1234', 'api_secret' => 'demo-SK-abcd5678'],
            ],
        );

        // --- Supplier product 1: pending, awaiting vendor mapping ---
        \App\Models\SupplierProduct::firstOrCreate(
            ['vendor_id' => $vendor->id, 'external_product_id' => 'DEMO-AE-001'],
            [
                'supplier_platform_id'    => $platform->id,
                'supplier_integration_id' => $integration->id,
                'title'                   => 'Bluetooth Earbuds (demo supplier import)',
                'description'             => 'Demo supplier-imported earbuds. Vendor maps this into a marketplace listing.',
                'external_sku'            => 'AE-EARBUDS-001',
                'source_url'              => 'https://www.aliexpress.com/item/demo/001.html',
                'supplier_cost_minor'     => 800,   // 8 USD
                'supplier_currency'       => 'USD',
                'supplier_stock_status'   => 'in_stock',
                'supplier_stock_qty'      => 250,
                'estimated_delivery_days' => 18,
                'import_status'           => \App\Models\SupplierProduct::STATUS_PENDING,
                'imported_at'             => now()->subDays(2),
                'raw_payload'             => ['source' => 'demo_seed'],
            ],
        );

        // --- Supplier product 2: mapped, awaiting admin approval ---
        $sp2 = \App\Models\SupplierProduct::firstOrCreate(
            ['vendor_id' => $vendor->id, 'external_product_id' => 'DEMO-AE-002'],
            [
                'supplier_platform_id'    => $platform->id,
                'supplier_integration_id' => $integration->id,
                'title'                   => 'USB-C Fast Charging Cable',
                'description'             => 'Demo cable; mapped to a marketplace listing pending admin approval.',
                'external_sku'            => 'AE-CABLE-002',
                'source_url'              => 'https://www.aliexpress.com/item/demo/002.html',
                'supplier_cost_minor'     => 200,
                'supplier_currency'       => 'USD',
                'supplier_stock_status'   => 'in_stock',
                'supplier_stock_qty'      => 500,
                'estimated_delivery_days' => 14,
                'import_status'           => \App\Models\SupplierProduct::STATUS_PENDING,
                'imported_at'             => now()->subDays(3),
                'raw_payload'             => ['source' => 'demo_seed'],
            ],
        );
        if ($sp2->wasRecentlyCreated || ! $sp2->product_id) {
            try {
                app(\App\Domain\Supplier\SupplierProductMapper::class)->map($sp2, [
                    'name'        => 'USB-C Fast Charging Cable (2m)',
                    'description' => 'Demo dropshipping product. Awaiting admin approval.',
                    'category_id' => \App\Models\Category::first()?->id,
                    'price_minor' => 1500, // 1.500 KWD
                    'currency'    => 'KWD',
                    'stock'       => 50,
                    'estimated_delivery_days' => 14,
                    'fulfillment_mode' => \App\Models\Product::FULFILLMENT_DROPSHIP_MANUAL,
                ], \App\Models\User::where('email', 'vendor@marketplace.test')->first());
            } catch (\Throwable $e) {
                // Idempotent: silent on re-run
            }
        }

        // --- Supplier product 3: mapped + admin-approved + published ---
        $sp3 = \App\Models\SupplierProduct::firstOrCreate(
            ['vendor_id' => $vendor->id, 'external_product_id' => 'DEMO-AE-003'],
            [
                'supplier_platform_id'    => $platform->id,
                'supplier_integration_id' => $integration->id,
                'title'                   => 'LED Desk Lamp with Touch Controls',
                'description'             => 'Demo published dropshipping product. Buy this to test the dropship checkout flow.',
                'external_sku'            => 'AE-LAMP-003',
                'source_url'              => 'https://www.aliexpress.com/item/demo/003.html',
                'supplier_cost_minor'     => 1200,
                'supplier_currency'       => 'USD',
                'supplier_stock_status'   => 'in_stock',
                'supplier_stock_qty'      => 80,
                'estimated_delivery_days' => 16,
                'import_status'           => \App\Models\SupplierProduct::STATUS_PENDING,
                'imported_at'             => now()->subDays(5),
                'raw_payload'             => ['source' => 'demo_seed'],
            ],
        );
        if ($sp3->import_status !== \App\Models\SupplierProduct::STATUS_PUBLISHED) {
            try {
                $product = app(\App\Domain\Supplier\SupplierProductMapper::class)->map($sp3, [
                    'name'        => 'LED Desk Lamp — Touch Control (demo dropship)',
                    'description' => 'Demo published dropshipping product. Add to cart and check out to test the supplier order flow.',
                    'category_id' => \App\Models\Category::first()?->id,
                    'price_minor' => 4500, // 4.500 KWD
                    'currency'    => 'KWD',
                    'stock'       => 30,
                    'estimated_delivery_days' => 16,
                    'fulfillment_mode' => \App\Models\Product::FULFILLMENT_DROPSHIP_MANUAL,
                ], \App\Models\User::where('email', 'vendor@marketplace.test')->first());
                app(\App\Domain\Supplier\SupplierProductMapper::class)->publish(
                    $sp3->fresh(),
                    \App\Models\User::where('email', 'admin@marketplace.test')->first(),
                );
            } catch (\Throwable $e) {
                // Idempotent on re-run
            }
        }

        $this->command?->info('Phase 6: supplier integration + 3 supplier products seeded (1 pending, 1 mapped, 1 published).');
    }

    /**
     * Phase 6 — seed one customer order against the published demo
     * dropshipping product so a supplier_order row exists for vendor testing.
     */
    private function seedDropshippingOrder(): void
    {
        $customer = \App\Models\User::where('email', 'customer@marketplace.test')->first();
        $vendor   = \App\Models\User::where('email', 'vendor@marketplace.test')->first()?->vendor;
        $address  = $customer?->addresses()->where('is_default', true)->first();
        if (! $customer || ! $vendor || ! $address) {
            return;
        }

        // Find the published dropship demo product
        $product = \App\Models\Product::where('vendor_id', $vendor->id)
            ->where('type', \App\Models\Product::TYPE_DROPSHIP)
            ->where('status', \App\Models\Product::STATUS_PUBLISHED)
            ->first();
        if (! $product) {
            return;
        }

        // Idempotent guard
        if ($customer->orders()->where('number', 'like', 'DEMO-DROPSHIP-%')->exists()) {
            return;
        }

        $now = now();
        $lineTotal = (int) $product->price_minor;
        $commission = (int) round($lineTotal * 0.20);
        $vendorEarning = $lineTotal - $commission;

        $order = \App\Models\Order::create([
            'number'                    => 'DEMO-DROPSHIP-' . $now->format('YmdHis'),
            'user_id'                   => $customer->id,
            'status'                    => \App\Models\Order::STATUS_PAID,
            'payment_status'            => \App\Models\Order::PAY_PAID,
            'fulfillment_status'        => \App\Models\Order::FUL_UNFULFILLED,
            'currency'                  => 'KWD',
            'subtotal_minor'            => $lineTotal,
            'shipping_minor'            => 0,
            'tax_minor'                 => 0,
            'discount_minor'            => 0,
            'total_minor'               => $lineTotal,
            'platform_commission_minor' => $commission,
            'vendor_earnings_minor'     => $vendorEarning,
            'paid_at'                   => $now->copy()->subHours(1),
        ]);

        $item = \App\Models\OrderItem::create([
            'order_id'                => $order->id,
            'vendor_id'               => $vendor->id,
            'product_id'              => $product->id,
            'product_name'            => $product->name,
            'product_sku'             => $product->sku,
            'quantity'                => 1,
            'unit_price_minor'        => $lineTotal,
            'line_total_minor'        => $lineTotal,
            'currency'                => 'KWD',
            'commission_percent'      => 20.00,
            'commission_amount_minor' => $commission,
            'vendor_earning_minor'    => $vendorEarning,
            'supplier_cost_minor'     => (int) ($product->supplier_cost_minor ?? 0),
            'fulfillment_status'      => \App\Models\OrderItem::FUL_UNFULFILLED,
        ]);

        \App\Models\OrderAddress::create([
            'order_id'       => $order->id,
            'type'           => 'shipping',
            'recipient_name' => $customer->name,
            'phone'          => $address->phone,
            'country'        => $address->country,
            'state'          => $address->state,
            'city'           => $address->city,
            'area'           => $address->area,
            'block'          => $address->block,
            'street'         => $address->street,
            'building'       => $address->building,
            'floor'          => $address->floor,
            'apartment'      => $address->apartment,
            'postal_code'    => $address->postal_code,
        ]);

        \App\Models\Payment::create([
            'order_id'     => $order->id,
            'method_slug'  => 'cod',
            'provider'     => 'cod',
            'status'       => 'captured',
            'amount_minor' => $lineTotal,
            'currency'     => 'KWD',
            'reference'    => 'COD-' . $order->number,
            'captured_at'  => $now->copy()->subHours(1),
        ]);

        // Now run DropshipOrderCreator to materialise a supplier_order
        $supplierOrders = app(\App\Domain\Supplier\DropshipOrderCreator::class)->createFromOrder($order);

        $count = count($supplierOrders);
        $this->command?->info("Phase 6: 1 demo dropshipping customer order seeded + {$count} supplier order(s) auto-created.");
    }

    /*
    |--------------------------------------------------------------------------
    | Phase 7 — Customizable products / Print-on-Demand foundation
    |--------------------------------------------------------------------------
    | Seeds:
    |  - 1 customizable mug product (Demo Trading Co.) with 4 fields
    |  - 1 customizable T-shirt product (Demo Trading Co.) with 5 fields
    |  - 1 sample paid customer order for the mug, with customizations populated
    |    AND a vendor-uploaded proof in STATUS_SENT awaiting customer approval
    |    so the demo customer can test the approve/reject UI immediately.
    | Idempotent: guarded by product slug + order number prefix.
    */
    private function seedCustomizableProductsAndOrder(): void
    {
        $vendor = \App\Models\User::where('email', 'vendor@marketplace.test')->first()?->vendor;
        if (! $vendor) return;

        // ── 1. Customizable mug ──
        //
        // v7.2 — switched from firstOrCreate(['slug' => ...]) to
        // updateOrCreate(['vendor_id' => ..., 'sku' => ...]) so the lookup
        // matches the ACTUAL unique constraint on the products table
        // (products_vendor_id_sku_unique). This also means re-running the
        // seeder updates the demo data in place instead of trying to insert
        // a new row that would collide. SKU renamed to DEMO-CUSTOM-MUG-001
        // (unique within the vendor — no collision with other demo products).
        $mug = \App\Models\Product::updateOrCreate(
            ['vendor_id' => $vendor->id, 'sku' => 'DEMO-CUSTOM-MUG-001'],
            [
                'slug'               => 'demo-custom-mug',
                'name'               => 'Personalized Photo Mug',
                'short_description'  => '11oz ceramic mug — upload your photo, choose a color and font.',
                'description'        => 'High-quality ceramic mug printed with your design. Dishwasher and microwave safe.',
                'type'               => \App\Models\Product::TYPE_CUSTOM,
                'status'             => \App\Models\Product::STATUS_PUBLISHED,
                'price_minor'        => 350,   // 3.50 KWD base
                'currency'           => 'KWD',
                'track_stock'        => false,
                'stock'              => 0,
                'fulfillment_mode'   => \App\Models\Product::FULFILLMENT_VENDOR_SELF,
                'published_at'       => now(),
            ]
        );

        if (! $mug->customizationFields()->exists()) {
            $mug->customizationFields()->createMany([
                [
                    'key' => 'photo', 'label' => 'Your photo / logo', 'type' => 'image',
                    'required' => true, 'sort_order' => 1,
                    'allowed_file_types' => ['jpg','jpeg','png','webp'],
                    'max_file_size_kb' => 5120, 'extra_fee_minor' => 0, 'is_active' => true,
                    'helper_text' => 'High-resolution image works best (min 1000x1000px).',
                ],
                [
                    'key' => 'custom_text', 'label' => 'Custom text (optional)', 'type' => 'text',
                    'required' => false, 'sort_order' => 2,
                    'max_text_length' => 30, 'extra_fee_minor' => 250, // 2.50 KWD
                    'placeholder' => 'e.g. Happy Birthday Mom!', 'is_active' => true,
                ],
                [
                    'key' => 'color', 'label' => 'Mug color', 'type' => 'color',
                    'required' => true, 'sort_order' => 3, 'is_active' => true,
                    'options' => [
                        ['value' => 'white', 'label' => 'White', 'extra_fee' => 0],
                        ['value' => 'black', 'label' => 'Black', 'extra_fee' => 100],
                        ['value' => 'blue',  'label' => 'Blue',  'extra_fee' => 100],
                    ],
                ],
                [
                    'key' => 'placement', 'label' => 'Image placement', 'type' => 'placement',
                    'required' => true, 'sort_order' => 4, 'is_active' => true,
                    'options' => [
                        ['value' => 'front',  'label' => 'Front only'],
                        ['value' => 'wrap',   'label' => 'Wrap-around', 'extra_fee' => 200],
                    ],
                ],
            ]);
        }

        // ── 2. Customizable T-shirt ──
        //
        // v7.2 BUG FIX: was sku='DEMO-TSHIRT-001' which COLLIDES with the
        // existing Phase 3 demo product "Cotton T-Shirt — Classic Fit"
        // (same vendor) — the products_vendor_id_sku_unique constraint
        // rejected the insert on the first run. Renamed to
        // DEMO-CUSTOM-TSHIRT-001 so it's globally unique within the vendor.
        // Lookup key switched to (vendor_id, sku) to match the actual unique
        // index, so a re-run updates rather than re-inserts.
        $tshirt = \App\Models\Product::updateOrCreate(
            ['vendor_id' => $vendor->id, 'sku' => 'DEMO-CUSTOM-TSHIRT-001'],
            [
                'slug'               => 'demo-custom-tshirt',
                'name'               => 'Custom Printed T-Shirt',
                'short_description'  => 'Cotton T-shirt — upload your design, pick size + color + font.',
                'description'        => 'Premium ringspun cotton. DTG-printed with your design — vibrant and durable.',
                'type'               => \App\Models\Product::TYPE_CUSTOM,
                'status'             => \App\Models\Product::STATUS_PUBLISHED,
                'price_minor'        => 800,   // 8.00 KWD base
                'currency'           => 'KWD',
                'track_stock'        => false,
                'stock'              => 0,
                'fulfillment_mode'   => \App\Models\Product::FULFILLMENT_VENDOR_SELF,
                'published_at'       => now(),
            ]
        );

        if (! $tshirt->customizationFields()->exists()) {
            $tshirt->customizationFields()->createMany([
                [
                    'key' => 'design', 'label' => 'Your design', 'type' => 'image',
                    'required' => true, 'sort_order' => 1,
                    'allowed_file_types' => ['jpg','jpeg','png','webp','svg','pdf'],
                    'max_file_size_kb' => 10240, 'extra_fee_minor' => 0, 'is_active' => true,
                    'helper_text' => 'PNG with transparent background recommended.',
                ],
                [
                    'key' => 'size', 'label' => 'Size', 'type' => 'size',
                    'required' => true, 'sort_order' => 2, 'is_active' => true,
                    'options' => [
                        ['value' => 'S',   'label' => 'Small'],
                        ['value' => 'M',   'label' => 'Medium'],
                        ['value' => 'L',   'label' => 'Large'],
                        ['value' => 'XL',  'label' => 'Extra Large',  'extra_fee' => 100],
                        ['value' => 'XXL', 'label' => 'Double XL',    'extra_fee' => 150],
                    ],
                ],
                [
                    'key' => 'color', 'label' => 'Shirt color', 'type' => 'color',
                    'required' => true, 'sort_order' => 3, 'is_active' => true,
                    'options' => [
                        ['value' => 'white', 'label' => 'White'],
                        ['value' => 'black', 'label' => 'Black'],
                        ['value' => 'navy',  'label' => 'Navy'],
                    ],
                ],
                [
                    'key' => 'text', 'label' => 'Custom text under design (optional)', 'type' => 'text',
                    'required' => false, 'sort_order' => 4,
                    'max_text_length' => 40, 'extra_fee_minor' => 300, // 3.00 KWD
                    'placeholder' => 'Team name, slogan, etc.', 'is_active' => true,
                ],
                [
                    'key' => 'font', 'label' => 'Font for the text', 'type' => 'font',
                    'required' => false, 'sort_order' => 5, 'is_active' => true,
                    'options' => [
                        ['value' => 'sans',   'label' => 'Modern Sans'],
                        ['value' => 'serif',  'label' => 'Classic Serif'],
                        ['value' => 'script', 'label' => 'Handwritten Script', 'extra_fee' => 50],
                    ],
                ],
            ]);
        }

        // ── 3. Sample customizable order (idempotent) ──
        $customer = \App\Models\User::where('email', 'customer@marketplace.test')->first();
        $address  = $customer?->addresses()->where('is_default', true)->first();
        if (! $customer || ! $address) return;
        if ($customer->orders()->where('number', 'like', 'DEMO-CUSTOM-%')->exists()) return;

        $now = now();
        // Pricing: mug 350 base + custom_text 250 + black color 100 + wrap-around 200 = 900
        $extraFee  = 250 + 100 + 200; // matches the chosen options below
        $linePriceUnit = (int) $mug->price_minor;
        $lineTotal     = $linePriceUnit * 1 + $extraFee;     // 350 + 550 = 900
        $commission    = (int) round($lineTotal * 0.20);
        $vendorEarning = $lineTotal - $commission;

        $order = \App\Models\Order::create([
            'number'                    => 'DEMO-CUSTOM-' . $now->format('YmdHis'),
            'user_id'                   => $customer->id,
            'status'                    => \App\Models\Order::STATUS_PAID,
            'payment_status'            => \App\Models\Order::PAY_PAID,
            'fulfillment_status'        => \App\Models\Order::FUL_UNFULFILLED,
            'currency'                  => 'KWD',
            'subtotal_minor'            => $lineTotal,
            'shipping_minor'            => 0,
            'tax_minor'                 => 0,
            'discount_minor'            => 0,
            'total_minor'               => $lineTotal,
            'platform_commission_minor' => $commission,
            'vendor_earnings_minor'     => $vendorEarning,
            'paid_at'                   => $now->copy()->subHours(2),
        ]);

        $item = \App\Models\OrderItem::create([
            'order_id'                  => $order->id,
            'vendor_id'                 => $vendor->id,
            'product_id'                => $mug->id,
            'product_name'              => $mug->name,
            'product_sku'               => $mug->sku,
            'quantity'                  => 1,
            'unit_price_minor'          => $linePriceUnit,
            'line_total_minor'          => $lineTotal,
            'currency'                  => 'KWD',
            'commission_percent'        => 20.00,
            'commission_amount_minor'   => $commission,
            'vendor_earning_minor'      => $vendorEarning,
            'customization_fee_minor'   => $extraFee,
            'customization_status'      => \App\Models\OrderItem::CUST_PROOF_UPLOADED,
            'fulfillment_status'        => \App\Models\OrderItem::FUL_UNFULFILLED,
        ]);

        // Customization snapshot rows (mirror what the validator would emit).
        //
        // v7.4 — write a real placeholder PNG for the photo upload so the
        // demo customer can actually download/view the file. We CHECK THE
        // RETURN VALUE of Storage::put (which is bool — does NOT throw on
        // failure) AND verify the file exists on disk after writing. If any
        // step fails, $photoPath stays null (the column IS nullable for
        // OrderItemCustomization, so the row is still inserted, just
        // without a file).
        $photoBytes = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
        );
        $photoPath  = "customizations/{$customer->id}/demo-family-photo.png";
        $photoSize  = strlen($photoBytes);
        $photoOk    = false;
        try {
            $putResult = \Illuminate\Support\Facades\Storage::disk('local')->put($photoPath, $photoBytes);
            // Storage::put returns bool — false means silent write failure
            if ($putResult === true && \Illuminate\Support\Facades\Storage::disk('local')->exists($photoPath)) {
                $photoOk = true;
            } else {
                $this->command?->warn("Phase 7 demo: Storage::put returned false for photo placeholder — file_path will be null on the customer's photo customization.");
            }
        } catch (\Throwable $e) {
            $this->command?->warn("Phase 7 demo: could not write photo placeholder ({$e->getMessage()}) — file_path will be null on the customer's photo customization.");
        }

        \App\Models\OrderItemCustomization::create([
            'order_item_id' => $item->id,
            'field_key' => 'photo', 'field_label' => 'Your photo / logo', 'field_type' => 'image',
            'value' => null,
            'file_path' => $photoOk ? $photoPath : null,        // nullable column — safe either way
            'file_original_name' => 'family-photo.png',
            'file_mime' => 'image/png',
            'file_size_bytes' => $photoOk ? $photoSize : null,
            'extra_fee_minor' => 0,
        ]);
        \App\Models\OrderItemCustomization::create([
            'order_item_id' => $item->id,
            'field_key' => 'custom_text', 'field_label' => 'Custom text (optional)', 'field_type' => 'text',
            'value' => 'Best Dad Ever ❤', 'extra_fee_minor' => 250,
        ]);
        \App\Models\OrderItemCustomization::create([
            'order_item_id' => $item->id,
            'field_key' => 'color', 'field_label' => 'Mug color', 'field_type' => 'color',
            'value' => 'black', 'extra_fee_minor' => 100,
        ]);
        \App\Models\OrderItemCustomization::create([
            'order_item_id' => $item->id,
            'field_key' => 'placement', 'field_label' => 'Image placement', 'field_type' => 'placement',
            'value' => 'wrap', 'extra_fee_minor' => 200,
        ]);

        // v7.4 — Vendor-uploaded proof in STATUS_SENT.
        //
        // ROOT CAUSE (v7.0-v7.3 bug): customization_proofs.file_path is NOT
        // NULL in the migration. v7.0-v7.2 inserted file_path=null and SQL
        // crashed. v7.3 added a try/catch around Storage::put, but
        // Storage::put returns BOOL on failure (does NOT throw), so the
        // try/catch never fired and the proof was still inserted with a
        // path that pointed at a missing file (different bug, but the SQL
        // constraint check still passed because the path was a non-null
        // string).
        //
        // v7.4 is rigorous:
        //  1. Check Storage::put's return value (bool — false = failure)
        //  2. Verify the file actually exists on disk after writing
        //  3. ONLY create the proof if both checks pass
        //  4. The CustomizationProof model now has a `creating` event that
        //     throws LogicException if file_path is empty — belt-and-
        //     suspenders defense against any code path (seeder, service,
        //     test, future contributor) that tries to skip the upload step.
        $proofPath  = "customization-proofs/{$vendor->id}/{$item->id}/demo-proof-v1.png";
        $proofSize  = strlen($photoBytes);
        $proofOk    = false;
        try {
            $putResult = \Illuminate\Support\Facades\Storage::disk('local')->put($proofPath, $photoBytes);
            if ($putResult === true && \Illuminate\Support\Facades\Storage::disk('local')->exists($proofPath)) {
                $proofOk = true;
            } else {
                $this->command?->warn("Phase 7 demo: Storage::put returned false for proof placeholder — proof row will NOT be seeded; demo approve/reject UI will be empty.");
            }
        } catch (\Throwable $e) {
            $this->command?->warn("Phase 7 demo: could not write proof placeholder ({$e->getMessage()}) — proof row will NOT be seeded; demo approve/reject UI will be empty.");
        }

        if ($proofOk) {
            \App\Models\CustomizationProof::create([
                'order_item_id'      => $item->id,
                'vendor_id'          => $vendor->id,
                'file_path'          => $proofPath,
                'file_original_name' => 'mug-proof-v1.png',
                'file_mime'          => 'image/png',
                'file_size_bytes'    => $proofSize,
                'status'             => \App\Models\CustomizationProof::STATUS_SENT,
                'vendor_note'        => 'First proof — please check the photo placement and text positioning.',
                'sent_at'            => $now->copy()->subMinutes(20),
            ]);
        }

        // Shipping address copy for the order (mirrors seedDropshippingOrder)
        \App\Models\OrderAddress::create([
            'order_id'       => $order->id,
            'type'           => 'shipping',
            'recipient_name' => $customer->name,
            'phone'          => $address->phone,
            'country'        => $address->country,
            'state'          => $address->state,
            'city'           => $address->city,
            'area'           => $address->area,
            'block'          => $address->block,
            'street'         => $address->street,
            'building'       => $address->building,
            'floor'          => $address->floor,
            'apartment'      => $address->apartment,
            'postal_code'    => $address->postal_code,
        ]);

        $this->command?->info('Phase 7: 2 customizable demo products seeded (mug, T-shirt) + 1 sample customization order with a SENT proof awaiting customer response.');
    }

    /**
     * Phase 8 — services marketplace demo data.
     *
     * Creates:
     *   - 2 demo services (Doctor Consultation @ vendor1, Home AC Cleaning @ vendor2)
     *   - 2 service providers (Dr. Sarah Ahmed @ vendor1, Ahmad Khalid @ vendor2)
     *   - Weekly availability Mon-Sat 10:00-20:00, 30-min slots, lunch break
     *   - 1 demo booking for tomorrow morning
     *
     * Idempotent via Product::updateOrCreate keyed on (vendor_id, sku),
     * ServiceProvider::updateOrCreate keyed on (vendor_id, slug), and
     * ServiceAvailability::updateOrCreate keyed on (provider, day_of_week).
     * The demo booking is guarded by an existence check to avoid creating
     * a new one on every seed run (would multiply booking numbers).
     */
    private function seedServicesAndBookings(): void
    {
        $vendor1 = \App\Models\Vendor::where('user_id',
            \App\Models\User::where('email', 'vendor@marketplace.test')->value('id')
        )->first();
        $vendor2 = \App\Models\Vendor::where('user_id',
            \App\Models\User::where('email', 'vendor2@marketplace.test')->value('id')
        )->first();
        $customer = \App\Models\User::where('email', 'customer@marketplace.test')->first();

        if (! $vendor1 || ! $vendor2 || ! $customer) {
            $this->command?->warn('Phase 8 seeder: missing one of vendor1/vendor2/customer — skipping.');
            return;
        }

        /* ── Service 1: Doctor Consultation (vendor1) ─────────────────── */
        $service1 = \App\Models\Product::updateOrCreate(
            ['vendor_id' => $vendor1->id, 'sku' => 'SVC-DEMO-DOCTOR-001'],
            [
                'name'         => 'General Doctor Consultation',
                'slug'         => 'demo-doctor-consultation',
                'description'  => "30-minute general consultation with a licensed physician. "
                                . "Suitable for non-emergency concerns, prescription refills, "
                                . "and follow-up visits.\n\nBring your previous medical records "
                                . "if any.",
                'type'         => \App\Models\Product::TYPE_SERVICE,
                'status'       => 'published',
                'price_minor'  => 15000,         // 15.000 KWD
                'currency'     => 'KWD',
                'stock'        => 0,
                'track_stock'  => false,         // services don't track inventory
            ]
        );
        \App\Models\ServiceDetail::updateOrCreate(
            ['product_id' => $service1->id],
            [
                'service_type'                 => \App\Models\ServiceDetail::TYPE_CONSULTATION,
                'location_mode'                => \App\Models\ServiceDetail::LOCATION_PROVIDER,
                'duration_minutes'             => 30,
                'service_area_text'            => 'Kuwait City, Salmiya, Hawalli',
                'min_lead_time_minutes'        => 60,
                'max_advance_days'             => 30,
                'allow_customer_provider_pick' => true,
                'is_active'                    => true,
            ]
        );

        /* ── Service 2: Home AC Cleaning (vendor2) ────────────────────── */
        $service2 = \App\Models\Product::updateOrCreate(
            ['vendor_id' => $vendor2->id, 'sku' => 'SVC-DEMO-AC-CLEAN-001'],
            [
                'name'         => 'Home AC Deep Cleaning',
                'slug'         => 'demo-home-ac-cleaning',
                'description'  => "Professional split AC unit deep cleaning at your home. "
                                . "Includes filter wash, coil cleaning, and drain pipe flush. "
                                . "Approximately 90 minutes per unit.\n\nPrice is per single unit; "
                                . "additional units quoted on site.",
                'type'         => \App\Models\Product::TYPE_SERVICE,
                'status'       => 'published',
                'price_minor'  => 12500,         // 12.500 KWD
                'currency'     => 'KWD',
                'stock'        => 0,
                'track_stock'  => false,         // services don't track inventory
            ]
        );
        \App\Models\ServiceDetail::updateOrCreate(
            ['product_id' => $service2->id],
            [
                'service_type'                 => \App\Models\ServiceDetail::TYPE_HOME_VISIT,
                'location_mode'                => \App\Models\ServiceDetail::LOCATION_CUSTOMER,
                'duration_minutes'             => 90,
                'service_area_text'            => 'All Kuwait',
                'min_lead_time_minutes'        => 120,
                'max_advance_days'             => 14,
                'allow_customer_provider_pick' => false,
                'is_active'                    => true,
            ]
        );

        /* ── Providers ────────────────────────────────────────────────── */
        $provider1 = \App\Models\ServiceProvider::updateOrCreate(
            ['vendor_id' => $vendor1->id, 'slug' => 'dr-sarah-ahmed'],
            [
                'name'           => 'Dr. Sarah Ahmed',
                'specialization' => 'General Medicine',
                'qualification'  => 'MBBS, MD — 12 years experience',
                'email'          => 'sarah.ahmed@example.com',
                'bio'            => 'Board-certified general physician with a focus on family medicine and preventative care.',
                'is_active'      => true,
            ]
        );
        $provider2 = \App\Models\ServiceProvider::updateOrCreate(
            ['vendor_id' => $vendor2->id, 'slug' => 'ahmad-khalid'],
            [
                'name'           => 'Ahmad Khalid',
                'specialization' => 'HVAC Technician',
                'qualification'  => 'Certified HVAC specialist — 8 years experience',
                'phone'          => '+965 9000-0001',
                'bio'            => 'Senior AC technician trained on all major split-unit brands. Carries spare parts and cleaning chemicals.',
                'is_active'      => true,
            ]
        );

        /* ── Provider ↔ Service assignments ───────────────────────────── */
        $service1->serviceProviders()->syncWithoutDetaching([$provider1->id]);
        $service2->serviceProviders()->syncWithoutDetaching([$provider2->id]);

        /* ── Weekly availability (Mon-Sat, 10:00-20:00, 30-min, lunch break) */
        foreach ([$provider1, $provider2] as $p) {
            // 1=Mon, 2=Tue, 3=Wed, 4=Thu, 5=Fri, 6=Sat. Sunday=0 closed.
            foreach ([1, 2, 3, 4, 5, 6] as $day) {
                \App\Models\ServiceAvailability::updateOrCreate(
                    ['service_provider_id' => $p->id, 'day_of_week' => $day],
                    [
                        'start_time'            => '10:00:00',
                        'end_time'              => '20:00:00',
                        'slot_duration_minutes' => 30,
                        'max_bookings_per_slot' => 1,
                        'break_start_time'      => '13:00:00',
                        'break_end_time'        => '14:00:00',
                        'is_active'             => true,
                    ]
                );
            }
        }

        /* ── 1 demo booking (idempotent: skip if customer already has one) */
        if (! \App\Models\ServiceBooking::where('user_id', $customer->id)
                ->where('number', 'like', 'SVC-DEMO-%')
                ->exists()) {
            // Find tomorrow at 10:00 — guaranteed weekday-ish for the demo
            // (if tomorrow falls on Sunday the seeder picks Monday).
            $tomorrow = \Carbon\Carbon::tomorrow();
            if ($tomorrow->dayOfWeek === 0) {     // Sunday is closed in our schedule
                $tomorrow->addDay();
            }

            \App\Models\ServiceBooking::create([
                'number'              => 'SVC-DEMO-' . $tomorrow->format('Ymd') . '-0001',
                'user_id'             => $customer->id,
                'vendor_id'           => $vendor1->id,
                'product_id'          => $service1->id,
                'service_provider_id' => $provider1->id,
                'order_id'            => null,
                'booked_for_date'     => $tomorrow->toDateString(),
                'booked_for_time'     => '10:00:00',
                'duration_minutes'    => 30,
                'location_mode'       => \App\Models\ServiceDetail::LOCATION_PROVIDER,
                'price_minor'         => 15000,
                'currency'            => 'KWD',
                'status'              => \App\Models\ServiceBooking::STATUS_CONFIRMED,
                'customer_notes'      => 'First-time visit — annual check-up.',
                'confirmed_at'        => now(),
            ]);
        }

        $this->command?->info('Phase 8: 2 demo services seeded (Doctor Consultation, Home AC Cleaning) + 2 providers + Mon-Sat 10:00-20:00 availability + 1 confirmed booking.');

        // ──────────────────────────────────────────────────────────────────
        // Phase 9 — Promotions / Coupons / Reviews / Support tickets
        // ──────────────────────────────────────────────────────────────────
        // All updateOrCreate calls are keyed on real unique indexes (v7.2
        // defense): promotions.slug, coupons.code, support_tickets.number.

        $admin = \App\Models\User::where('email', 'admin@marketplace.test')->first();
        if (! $admin) {
            $this->command?->warn('Phase 9 seeder: admin user missing — skipping promotions/coupons/tickets seed.');
            return;
        }

        // 1. Two promotions (one platform-wide flash sale, one vendor deal)
        $promo1 = \App\Models\Promotion::updateOrCreate(
            ['slug' => 'phase9-summer-flash-sale'],
            [
                'vendor_id'         => null,
                'created_by'        => $admin->id,
                'title'             => 'Summer Flash Sale — 20% off all products',
                'description'       => 'Platform-wide flash sale running this week.',
                'promotion_type'    => \App\Models\Promotion::TYPE_FLASH_SALE,
                'discount_type'     => \App\Models\Promotion::DISCOUNT_PERCENTAGE,
                'discount_value'    => 20,
                'starts_at'         => now()->subDay(),
                'ends_at'           => now()->addWeek(),
                'is_active'         => true,
                'approval_status'   => \App\Models\Promotion::APPROVAL_APPROVED,
                'currency'          => 'KWD',
            ]
        );

        $promo2 = \App\Models\Promotion::updateOrCreate(
            ['slug' => 'phase9-vendor1-deal-of-day'],
            [
                'vendor_id'         => $vendor1->id,
                'created_by'        => $admin->id,
                'title'             => 'Demo Trading Co. — Deal of the Day',
                'description'       => 'Vendor-specific promotion approved by admin.',
                'promotion_type'    => \App\Models\Promotion::TYPE_DEAL_OF_DAY,
                'discount_type'     => \App\Models\Promotion::DISCOUNT_PERCENTAGE,
                'discount_value'    => 15,
                'starts_at'         => now()->subHour(),
                'ends_at'           => now()->addDay(),
                'is_active'         => true,
                'approval_status'   => \App\Models\Promotion::APPROVAL_APPROVED,
                'currency'          => 'KWD',
            ]
        );

        // 2. Two coupons — one platform-wide percentage + one fixed-amount
        \App\Models\Coupon::updateOrCreate(
            ['code' => 'SAVE10'],
            [
                'vendor_id'         => null,
                'created_by'        => $admin->id,
                'description'       => '10% off your next order, no minimum.',
                'discount_type'     => \App\Models\Coupon::DISCOUNT_PERCENTAGE,
                'discount_value'    => 10,
                'min_order_minor'   => null,
                'max_discount_minor'=> 50000,           // cap at 50 KWD
                'starts_at'         => now()->subDay(),
                'ends_at'           => now()->addMonth(),
                'is_active'         => true,
                'usage_limit'       => 1000,
                'per_user_limit'    => 3,
                'currency'          => 'KWD',
            ]
        );

        \App\Models\Coupon::updateOrCreate(
            ['code' => 'WELCOME5'],
            [
                'vendor_id'         => null,
                'created_by'        => $admin->id,
                'description'       => '5 KWD off orders of 20 KWD or more.',
                'discount_type'     => \App\Models\Coupon::DISCOUNT_FIXED,
                'discount_value'    => 5000,             // 5 KWD in minor units
                'min_order_minor'   => 20000,            // 20 KWD minimum
                'max_discount_minor'=> null,
                'starts_at'         => now()->subDay(),
                'ends_at'           => now()->addMonth(),
                'is_active'         => true,
                'usage_limit'       => 100,
                'per_user_limit'    => 1,
                'currency'          => 'KWD',
            ]
        );

        // 3. Two reviews — one approved with verified-purchase, one with vendor response
        // Use the first product from vendor1 as the demo review target. If
        // none exists, skip (defensive: seeder must never explode).
        $demoProduct = \App\Models\Product::where('vendor_id', $vendor1->id)
            ->where('type', '!=', \App\Models\Product::TYPE_SERVICE)
            ->first();

        if ($demoProduct) {
            \App\Models\ProductReview::updateOrCreate(
                ['user_id' => $customer->id, 'product_id' => $demoProduct->id, 'order_item_id' => null],
                [
                    'rating'               => 5,
                    'title'                => 'Excellent quality!',
                    'body'                 => 'Great product, exactly as described. Fast shipping.',
                    'status'               => 'approved',
                    'is_verified_purchase' => true,
                    'approved_at'          => now(),
                    'vendor_response'      => 'Thank you for your kind words! We are delighted you enjoyed it.',
                    'vendor_responded_at'  => now(),
                ]
            );
        }

        $demoProduct2 = \App\Models\Product::where('vendor_id', $vendor2->id)
            ->where('type', '!=', \App\Models\Product::TYPE_SERVICE)
            ->first();
        if ($demoProduct2) {
            \App\Models\ProductReview::updateOrCreate(
                ['user_id' => $customer->id, 'product_id' => $demoProduct2->id, 'order_item_id' => null],
                [
                    'rating'               => 4,
                    'title'                => 'Good but could be better',
                    'body'                 => 'Solid build quality. Packaging could be improved.',
                    'status'               => 'approved',
                    'is_verified_purchase' => true,
                    'approved_at'          => now(),
                ]
            );
        }

        // 4. One support ticket with one reply
        $demoTicket = \App\Models\SupportTicket::updateOrCreate(
            ['number' => 'TKT-' . now()->format('ymd') . '-0001'],
            [
                'user_id'         => $customer->id,
                'ticket_type'     => \App\Models\SupportTicket::TYPE_GENERAL_INQUIRY,
                'subject'         => 'Question about shipping times',
                'priority'        => \App\Models\SupportTicket::PRIORITY_NORMAL,
                'status'          => \App\Models\SupportTicket::STATUS_ANSWERED,
                'last_replied_at' => now(),
            ]
        );

        \App\Models\SupportTicketMessage::updateOrCreate(
            ['support_ticket_id' => $demoTicket->id, 'user_id' => $customer->id, 'author_role' => 'customer'],
            [
                'body'         => 'How long does shipping usually take to Kuwait City?',
                'is_internal'  => false,
                'attachments'  => [],
            ]
        );

        \App\Models\SupportTicketMessage::updateOrCreate(
            ['support_ticket_id' => $demoTicket->id, 'user_id' => $admin->id, 'author_role' => 'admin'],
            [
                'body'         => 'For Kuwait City addresses we typically deliver within 1-2 business days. Thank you!',
                'is_internal'  => false,
                'attachments'  => [],
            ]
        );

        $this->command?->info('Phase 9: 2 promotions + 2 coupons (SAVE10, WELCOME5) + 2 reviews + 1 ticket w/ reply seeded.');
    }
}
